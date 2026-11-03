<?php
session_start();
include('conexion.php');
include('funciones.php');

if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

$equipoId = (int)($_GET['id'] ?? 0);

if ($equipoId <= 0) {
    header("Location: mis_equipos.php");
    exit();
}

$emailLogeado = $_SESSION['email'];

try {
    // 1. Obtener datos del equipo
    $query = "SELECT e.* FROM equipos e JOIN usuarios u ON e.usuario_id = u.id WHERE e.id = :id AND u.email = :email";
    $stmt = $baseDatos->prepare($query);
    $stmt->execute([':id' => $equipoId, ':email' => $emailLogeado]);
    $equipo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$equipo) {
        die("Equipo no encontrado o no tienes permisos para gestionarlo.");
    }

    // 2. Comprobar límite de 16 jugadores activos y buscar dorsal libre
    $queryJugadores = "SELECT numero, posicion FROM jugadores WHERE equipo_id = :id AND (muerto = 0 OR muerto IS NULL) AND posicion != 'Jugador Estrella' ORDER BY numero ASC";
    $stmtJug = $baseDatos->prepare($queryJugadores);
    $stmtJug->execute([':id' => $equipoId]);
    $filasActivos = $stmtJug->fetchAll(PDO::FETCH_ASSOC);

    $jugadoresActivos = array_column($filasActivos, 'numero');
    $conteoPosiciones = [];
    foreach ($filasActivos as $f) {
        $posNombre = $f['posicion'];
        $conteoPosiciones[$posNombre] = ($conteoPosiciones[$posNombre] ?? 0) + 1;
    }

    if (count($jugadoresActivos) >= 16) {
        die("Tu plantilla ya tiene el máximo de 16 jugadores activos permitido por el reglamento.");
    }

    $dorsalAsignado = 1;
    for ($i = 1; $i <= 16; $i++) {
        if (!in_array($i, $jugadoresActivos)) {
            $dorsalAsignado = $i;
            break;
        }
    }

    // 3. Consulta de posiciones de la raza incluyendo cuántos tiene ya el equipo
    $queryPosiciones = "SELECT pr.* 
                        FROM posiciones_raza pr 
                        JOIN razas r ON pr.raza_id = r.id 
                        WHERE r.nombre = :raza_equipo 
                        ORDER BY pr.coste ASC";
    
    $stmtPos = $baseDatos->prepare($queryPosiciones);
    $stmtPos->execute([':raza_equipo' => $equipo['raza']]);
    $posicionesBD = $stmtPos->fetchAll(PDO::FETCH_ASSOC);

    foreach ($posicionesBD as &$posItem) {
        $posItem['actuales'] = $conteoPosiciones[$posItem['nombre_posicion']] ?? 0;
    }
    unset($posItem);

} catch (PDOException $e) {
    die("Error al cargar datos: " . $e->getMessage());
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $posicion = trim($_POST['posicion_texto']); 
    $coste = (int)$_POST['coste'];
    $ma = trim($_POST['ma']);
    $st = trim($_POST['st']);
    $ag = trim($_POST['ag']);
    $pa = trim($_POST['pa']);
    $av = trim($_POST['av']);
    $habilidades = trim($_POST['habilidades']);

    // Verificar max_cantidad en servidor
    $maxPermitido = 16;
    foreach ($posicionesBD as $pBD) {
        if ($pBD['nombre_posicion'] === $posicion) {
            $maxPermitido = (int)$pBD['max_cantidad'];
            break;
        }
    }
    $actualesEnPosicion = $conteoPosiciones[$posicion] ?? 0;

    if (empty($nombre) || empty($posicion) || $coste <= 0) {
        $error = "Por favor, rellena los campos obligatorios.";
    } elseif ($actualesEnPosicion >= $maxPermitido) {
        $error = "Límite alcanzado: Ya tienes {$actualesEnPosicion}/{$maxPermitido} jugadores en la posición de {$posicion}.";
    } elseif ($coste > $equipo['tesoreria']) {
        $error = "Fondos insuficientes. Cuesta " . number_format($coste, 0, ',', '.') . " GP pero tienes " . number_format($equipo['tesoreria'], 0, ',', '.') . " GP.";
    } else {
        try {
            $baseDatos->beginTransaction();

            $insertJugador = "INSERT INTO jugadores (equipo_id, numero, nombre, posicion, coste, ma, st, ag, pa, av, habilidades, valor_actual, spp, spp_gastados, avances) 
                              VALUES (:eq_id, :num, :nom, :pos, :coste, :ma, :st, :ag, :pa, :av, :habs, :coste, 0, 0, 0)";
            $stmtInsert = $baseDatos->prepare($insertJugador);
            $stmtInsert->execute([
                ':eq_id' => $equipoId,
                ':num' => $dorsalAsignado,
                ':nom' => $nombre,
                ':pos' => $posicion,
                ':coste' => $coste,
                ':ma' => $ma,
                ':st' => $st,
                ':ag' => $ag,
                ':pa' => $pa,
                ':av' => $av,
                ':habs' => $habilidades ?: 'Ninguna'
            ]);

            $updateEquipo = "UPDATE equipos SET tesoreria = tesoreria - :coste WHERE id = :eq_id";
            $stmtUpdate = $baseDatos->prepare($updateEquipo);
            $stmtUpdate->execute([':coste' => $coste, ':eq_id' => $equipoId]);

            $baseDatos->commit();
            recalcularTV($equipoId);
            header("Location: ver_equipo.php?id=" . $equipoId . "&exito=fichaje_completado");
            exit();

        } catch (Exception $e) {
            $baseDatos->rollBack();
            $error = "Error al completar el fichaje: " . $e->getMessage();
        }
    }
}

$rosterJson = json_encode($posicionesBD);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mercado de Fichajes - BB CUP</title>
    <style>
        body { margin: 0; font-family: Arial, sans-serif; background-image: url('imagenes/fondo.webp'); background-size: cover; background-position: center; display: flex; justify-content: center; align-items: center; min-height: 100vh; color: #333; }
        .contenedor { background: rgba(255, 255, 255, 0.95); border-radius: 10px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2); padding: 30px; width: 90%; max-width: 700px; }
        h1 { color: #4e94b8; text-align: center; margin-bottom: 5px; }
        .info-banco { background: #f8f9fa; border: 1px solid #ddd; padding: 15px; border-radius: 8px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; }
        .info-banco div { font-size: 14px; color: #666; }
        .info-banco strong { font-size: 22px; color: #28a745; display: block; }
        .fila-campos { display: flex; gap: 15px; margin-bottom: 15px; }
        .grupo-campo { flex: 1; }
        .grupo-campo label { display: block; font-weight: bold; margin-bottom: 5px; font-size: 14px; }
        .grupo-campo input, .grupo-campo select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box; font-size: 14px; }
        .input-corto { width: 60px !important; text-align: center; }
        input[readonly] { background-color: #e9ecef; color: #495057; cursor: not-allowed; font-weight: bold; border: 1px solid #ced4da; }
        .btn-fichar { background-color: #28a745; color: white; border: none; padding: 15px; font-size: 18px; font-weight: bold; border-radius: 6px; cursor: pointer; width: 100%; margin-top: 10px; transition: background 0.3s; }
        .btn-fichar:hover { background-color: #218838; }
        .btn-cancelar { display: block; text-align: center; margin-top: 15px; color: #666; text-decoration: none; }
        .alerta-error { background-color: #f8d7da; color: #721c24; padding: 12px; border-radius: 6px; margin-bottom: 20px; font-weight: bold; text-align: center; }
    </style>
</head>
<body>
    <div class="contenedor">
        <h1>Centro de Fichajes</h1>
        <p style="text-align: center; color: #666;">Dorsal asignado automáticamente: <strong>#<?php echo $dorsalAsignado; ?></strong></p>

        <div class="info-banco">
            <div>Equipo: <strong><?php echo htmlspecialchars($equipo['nombre_equipo']); ?> (<?php echo htmlspecialchars($equipo['raza']); ?>)</strong></div>
            <div style="text-align: right;">Tesorería Disponible: <strong id="tesoreria_texto" data-oro="<?php echo $equipo['tesoreria']; ?>"><?php echo number_format($equipo['tesoreria'], 0, ',', '.'); ?> GP</strong></div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alerta-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="fichar_jugador.php?id=<?php echo $equipoId; ?>">
            <input type="hidden" name="posicion_texto" id="posicion_texto" value="">

            <div class="fila-campos">
                <div class="grupo-campo" style="flex: 2;">
                    <label>Nombre del Jugador</label>
                    <input type="text" name="nombre" required placeholder="Ej: Bob el Grande">
                </div>
                <div class="grupo-campo" style="flex: 2;">
                    <label>Posición en el Equipo (Activos / Máx)</label>
                    <select id="selector_posicion" required>
                        <option value="" disabled selected>Cargando posiciones...</option>
                    </select>
                </div>
                <div class="grupo-campo">
                    <label>Coste (GP)</label>
                    <input type="number" name="coste" id="campo_coste" required readonly>
                </div>
            </div>

            <div class="fila-campos" style="justify-content: space-between; background: #e9ecef; padding: 10px; border-radius: 6px;">
                <div class="grupo-campo"><label>MA</label><input type="text" name="ma" id="campo_ma" class="input-corto" required readonly></div>
                <div class="grupo-campo"><label>ST</label><input type="text" name="st" id="campo_st" class="input-corto" required readonly></div>
                <div class="grupo-campo"><label>AG</label><input type="text" name="ag" id="campo_ag" class="input-corto" required readonly></div>
                <div class="grupo-campo"><label>PA</label><input type="text" name="pa" id="campo_pa" class="input-corto" required readonly></div>
                <div class="grupo-campo"><label>AV</label><input type="text" name="av" id="campo_av" class="input-corto" required readonly></div>
            </div>

            <div class="grupo-campo">
                <label>Habilidades Iniciales (Por reglamento)</label>
                <input type="text" name="habilidades" id="campo_habs" readonly>
            </div>

            <button type="submit" class="btn-fichar" id="btn_fichar" disabled>Selecciona una posición</button>
            <a href="ver_equipo.php?id=<?php echo $equipoId; ?>" class="btn-cancelar">Cancelar y volver al roster</a>
        </form>
    </div>

    <script>
        const datosRoster = <?php echo $rosterJson; ?>;
        const selectPosicion = document.getElementById('selector_posicion');
        const tesoreriaTotal = parseInt(document.getElementById('tesoreria_texto').dataset.oro);
        const btnFichar = document.getElementById('btn_fichar');

        const inputPosTexto = document.getElementById('posicion_texto');
        const inputCoste = document.getElementById('campo_coste');
        const inputMa = document.getElementById('campo_ma');
        const inputSt = document.getElementById('campo_st');
        const inputAg = document.getElementById('campo_ag');
        const inputPa = document.getElementById('campo_pa');
        const inputAv = document.getElementById('campo_av');
        const inputHabs = document.getElementById('campo_habs');

        selectPosicion.innerHTML = '<option value="" disabled selected>Elige la posición a fichar...</option>';
        
        if (datosRoster && datosRoster.length > 0) {
            datosRoster.forEach((jugador, index) => {
                let opt = document.createElement('option');
                opt.value = index; 
                const costeInt = parseInt(jugador.coste) || 0;
                const actuales = parseInt(jugador.actuales) || 0;
                const maxCant = parseInt(jugador.max_cantidad) || 16;
                opt.textContent = `${jugador.nombre_posicion} (${actuales}/${maxCant}) - ${costeInt.toLocaleString('es-ES')} GP`;
                if (actuales >= maxCant) {
                    opt.disabled = true;
                    opt.textContent += ' [MÁXIMO ALCANZADO]';
                }
                selectPosicion.appendChild(opt);
            });
        } else {
            selectPosicion.innerHTML = '<option value="" disabled selected>No hay posiciones para esta raza en la BD</option>';
        }

        selectPosicion.addEventListener('change', function() {
            const elegido = datosRoster[this.value];
            const actuales = parseInt(elegido.actuales) || 0;
            const maxCant = parseInt(elegido.max_cantidad) || 16;
            
            inputPosTexto.value = elegido.nombre_posicion;
            inputCoste.value = elegido.coste;
            inputMa.value = elegido.ma;
            inputSt.value = elegido.st;
            inputAg.value = elegido.ag;
            inputPa.value = elegido.pa;
            inputAv.value = elegido.av;
            inputHabs.value = elegido.habilidades || 'Ninguna';

            if (actuales >= maxCant) {
                btnFichar.disabled = true;
                btnFichar.textContent = 'Cupo Máximo de Posición Alcanzado';
                btnFichar.style.backgroundColor = '#dc3545';
            } else if (elegido.coste > tesoreriaTotal) {
                btnFichar.disabled = true;
                btnFichar.textContent = 'Tesorería Insuficiente';
                btnFichar.style.backgroundColor = '#dc3545';
            } else {
                btnFichar.disabled = false;
                btnFichar.textContent = 'Confirmar y Pagar Fichaje';
                btnFichar.style.backgroundColor = '#28a745';
            }
        });
    </script>
</body>
</html>
