<?php
session_start();
include('conexion.php');
include('funciones.php');

if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

$jugadorId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($jugadorId <= 0) {
    header("Location: mis_equipos.php");
    exit();
}

$emailLogeado = $_SESSION['email'];

try {
    $query = "SELECT j.*, e.usuario_id, e.id AS equipo_id, e.nombre_equipo, e.valor_equipo 
              FROM jugadores j
              JOIN equipos e ON j.equipo_id = e.id
              JOIN usuarios u ON e.usuario_id = u.id
              WHERE j.id = :id AND u.email = :email";
    $stmt = $baseDatos->prepare($query);
    $stmt->execute([':id' => $jugadorId, ':email' => $emailLogeado]);
    $jugador = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$jugador) {
        die("Jugador no encontrado o no tienes permisos.");
    }

    $sppTotales = (int)$jugador['spp'];
    $sppGastados = (int)($jugador['spp_gastados'] ?? 0);
    $sppDisponibles = $sppTotales - $sppGastados;
    $avances = (int)($jugador['avances'] ?? 0);

    if ($avances >= 6) {
        die("Este jugador ha alcanzado el nivel máximo (Leyenda) y no puede adquirir más habilidades.");
    }

} catch (PDOException $e) {
    die("Error de base de datos: " . $e->getMessage());
}

// Matriz oficial de costes de SPP Temporada 3 según los avances actuales (0 a 5)
$costesSPP = [
    0 => ['pri_rnd' => 3, 'sec_rnd' => 6, 'pri_ele' => 6, 'sec_ele' => 12, 'stat' => 18],
    1 => ['pri_rnd' => 4, 'sec_rnd' => 8, 'pri_ele' => 8, 'sec_ele' => 14, 'stat' => 20],
    2 => ['pri_rnd' => 6, 'sec_rnd' => 12, 'pri_ele' => 12, 'sec_ele' => 18, 'stat' => 24],
    3 => ['pri_rnd' => 8, 'sec_rnd' => 16, 'pri_ele' => 16, 'sec_ele' => 22, 'stat' => 28],
    4 => ['pri_rnd' => 10, 'sec_rnd' => 20, 'pri_ele' => 20, 'sec_ele' => 26, 'stat' => 32],
    5 => ['pri_rnd' => 15, 'sec_rnd' => 30, 'pri_ele' => 30, 'sec_ele' => 40, 'stat' => 50]
];

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $categoriaMejora = $_POST['categoria_mejora'];
    $nuevaHabilidad = trim($_POST['nueva_habilidad']);
    
    // Determinar coste real en backend
    $costeReal = $costesSPP[$avances][$categoriaMejora] ?? 999;

    // Convertir las habilidades actuales en un array para comprobar duplicados
    $habilidadesActuales = $jugador['habilidades'] ?? '';
    $arrayHabilidades = array_map('trim', explode(',', $habilidadesActuales));

    if ($costeReal > $sppDisponibles) {
        $error = "No tienes suficientes SPP para esta mejora. Cuesta {$costeReal} SPP y tienes {$sppDisponibles}.";
    } elseif (empty($nuevaHabilidad)) {
        $error = "Debes seleccionar una habilidad o mejora obtenida.";
    } elseif ($categoriaMejora !== 'stat' && in_array($nuevaHabilidad, $arrayHabilidades)) {
        // Bloqueamos la inserción si la habilidad ya existe (permitiendo excepciones para mejoras de stats como +1 MA)
        $error = "El jugador ya posee la habilidad '{$nuevaHabilidad}'. El reglamento no permite tener la misma habilidad dos veces.";
    } else {
        // Incremento de valor de equipo (TV) oficial BB2020
        $incrementoTV = 0;
        if ($categoriaMejora === 'pri_rnd') $incrementoTV = 10000;
        if ($categoriaMejora === 'pri_ele' || $categoriaMejora === 'sec_rnd') $incrementoTV = 20000;
        if ($categoriaMejora === 'sec_ele') $incrementoTV = 40000;
        if ($categoriaMejora === 'stat') {
            if ($nuevaHabilidad === '+1 MA' || $nuevaHabilidad === '+1 AV') $incrementoTV = 20000;
            elseif ($nuevaHabilidad === '+1 PA' || $nuevaHabilidad === '+1 AG') $incrementoTV = 40000;
            elseif ($nuevaHabilidad === '+1 ST') $incrementoTV = 80000;
        }

        try {
            $baseDatos->beginTransaction();

            $habilidadesNuevas = ($habilidadesActuales === 'Ninguna' || $habilidadesActuales === '-' || empty($habilidadesActuales)) 
                                 ? $nuevaHabilidad 
                                 : $habilidadesActuales . ", " . $nuevaHabilidad;

            $stmtUpJ = $baseDatos->prepare("UPDATE jugadores SET habilidades = ?, spp_gastados = spp_gastados + ?, valor_actual = valor_actual + ?, avances = avances + 1 WHERE id = ?");
            $stmtUpJ->execute([$habilidadesNuevas, $costeReal, $incrementoTV, $jugadorId]);

            $stmtUpE = $baseDatos->prepare("UPDATE equipos SET valor_equipo = valor_equipo + ? WHERE id = ?");
            $stmtUpE->execute([$incrementoTV, $jugador['equipo_id']]);

            $baseDatos->commit();
            recalcularTV($jugador['equipo_id']);
            header("Location: ver_equipo.php?id=" . $jugador['equipo_id'] . "&exito=nivel_subido");
            exit();

        } catch (Exception $e) {
            $baseDatos->rollBack();
            $error = "Error al subir de nivel: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subir de Nivel - BB CUP</title>
    <style>
        body { margin: 0; font-family: Arial, sans-serif; background-image: url('imagenes/fondo.webp'); background-size: cover; background-position: center; display: flex; justify-content: center; align-items: center; min-height: 100vh; color: #333; }
        .contenedor { background: rgba(255, 255, 255, 0.95); border-radius: 10px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2); padding: 30px; width: 90%; max-width: 550px; }
        h1 { color: #4e94b8; text-align: center; margin-bottom: 5px; }
        .jugador-info { background: #f8f9fa; border: 1px solid #ddd; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; }
        .badge-spp { background-color: #17a2b8; color: white; padding: 6px 12px; border-radius: 20px; font-size: 16px; font-weight: bold; }
        .grupo-campo { margin-bottom: 15px; }
        .grupo-campo label { display: block; font-weight: bold; margin-bottom: 5px; }
        .grupo-campo select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 6px; font-size: 15px; }
        .caja-coste { background: #e9ecef; border: 1px dashed #6c757d; padding: 15px; text-align: center; border-radius: 6px; font-size: 18px; margin-bottom: 20px; }
        .caja-coste span { font-weight: bold; font-size: 24px; color: #dc3545; }
        .caja-coste.ok span { color: #28a745; }
        .btn-guardar { background-color: #28a745; color: white; border: none; padding: 14px; font-size: 16px; font-weight: bold; border-radius: 6px; cursor: pointer; width: 100%; }
        .btn-guardar:hover { background-color: #218838; }
        .btn-guardar:disabled { background-color: #ccc; cursor: not-allowed; }
        .btn-cancelar { display: block; text-align: center; margin-top: 15px; color: #666; text-decoration: none; }
        .alerta-error { background-color: #f8d7da; color: #721c24; padding: 10px; border-radius: 6px; margin-bottom: 15px; text-align: center; }
    </style>
</head>
<body>
    <div class="contenedor">
        <h1>Gastar SPP (Subida de Nivel)</h1>
        
        <div class="jugador-info">
            <h2 style="margin: 0 0 5px 0;"><?php echo htmlspecialchars($jugador['nombre']); ?></h2>
            <p style="margin: 0 0 10px 0; color: #666;">#<?php echo $jugador['numero']; ?> - Nivel de avance: <?php echo $avances; ?></p>
            <span class="badge-spp" id="spp_disponibles" data-spp="<?php echo $sppDisponibles; ?>"><?php echo $sppDisponibles; ?> SPP Disponibles</span>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alerta-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="subir_nivel.php?id=<?php echo $jugadorId; ?>" id="formNivel">
            
            <div class="grupo-campo">
                <label for="categoria_mejora">Ruta de Mejora Elegida</label>
                <select id="categoria_mejora" name="categoria_mejora" required>
                    <option value="" disabled selected>Selecciona una opción...</option>
                    <option value="pri_rnd">Habilidad Primaria Aleatoria (+10k TV)</option>
                    <option value="pri_ele">Habilidad Primaria Elegida (+20k TV)</option>
                    <option value="sec_rnd">Habilidad Secundaria Aleatoria (+20k TV)</option>
                    <option value="sec_ele">Habilidad Secundaria Elegida (+40k TV)</option>
                    <option value="stat">Mejora de Característica / Atributo</option>
                </select>
            </div>

            <div class="caja-coste" id="caja_coste">
                Coste calculado: <span id="texto_coste">-</span> SPP
                <div id="aviso_spp" style="font-size: 13px; color: #dc3545; margin-top: 5px; display: none;">No tienes suficientes SPP para esta mejora.</div>
            </div>

            <div class="grupo-campo">
                <label for="nueva_habilidad">Resultado de los Dados / Selección</label>
                <select id="nueva_habilidad" name="nueva_habilidad" required>
                    <option value="" disabled selected>Tira los dados y elige el resultado...</option>
                    
                    <optgroup label="Mejoras de Atributo">
                        <option value="+1 MA">+1 Movimiento (MA) [+20k TV]</option>
                        <option value="+1 AV">+1 Armadura (AV) [+20k TV]</option>
                        <option value="+1 PA">+1 Pase (PA) [+40k TV]</option>
                        <option value="+1 AG">+1 Agilidad (AG) [+40k TV]</option>
                        <option value="+1 ST">+1 Fuerza (ST) [+80k TV]</option>
                    </optgroup>
                    
                    <optgroup label="Agilidad (Agility)">
                        <option value="Atrapar">Atrapar (Catch)</option>
                        <option value="Echarse a un Lado">Echarse a un Lado (Side Step)</option>
                        <option value="En Pie de un Salto">En Pie de un Salto (Jump Up)</option>
                        <option value="Esquivar">Esquivar (Dodge)</option>
                        <option value="Manos Seguras">Manos Seguras (Sure Hands)</option>
                        <option value="Paso Ligero">Paso Ligero (Sprint)</option>
                        <option value="Pies Firmes">Pies Firmes (Sure Feet)</option>
                        <option value="Rastreador">Rastreador (Sneaky Git)</option>
                        <option value="Salto">Salto (Leap)</option>
                        <option value="Zafarse">Zafarse (Fend)</option>
                    </optgroup>

                    <optgroup label="General">
                        <option value="Agallas">Agallas (Dauntless)</option>
                        <option value="Aplastar">Aplastar (Piling On)</option>
                        <option value="Despojar el Balón">Despojar el Balón (Strip Ball)</option>
                        <option value="Furia">Furia (Frenzy)</option>
                        <option value="Juego Sucio">Juego Sucio (Dirty Player)</option>
                        <option value="Pasar en Carrera">Pasar en Carrera (Pass Block)</option>
                        <option value="Patada">Patada (Kick)</option>
                        <option value="Perseguir">Perseguir (Shadowing)</option>
                        <option value="Placar">Placar (Block)</option>
                        <option value="Robar Balón">Robar Balón (Tackle)</option>
                        <option value="Zancadilla">Zancadilla (Trip Up)</option>
                    </optgroup>

                    <optgroup label="Fuerza (Strength)">
                        <option value="Abrirse Paso">Abrirse Paso (Break Tackle)</option>
                        <option value="Apartar">Apartar (Grab)</option>
                        <option value="Brazo Fuerte">Brazo Fuerte (Strong Arm)</option>
                        <option value="Cabeza Dura">Cabeza Dura (Thick Skull)</option>
                        <option value="Defensa">Defensa (Guard)</option>
                        <option value="Golpe Mortífero">Golpe Mortífero (Mighty Blow)</option>
                        <option value="Juggernaut">Juggernaut</option>
                        <option value="Mantenerse Firme">Mantenerse Firme (Stand Firm)</option>
                    </optgroup>

                    <optgroup label="Mutación (Mutation)">
                        <option value="Brazos Adicionales">Brazos Adicionales (Extra Arms)</option>
                        <option value="Cola Prensil">Cola Prensil (Prehensile Tail)</option>
                        <option value="Cuernos">Cuernos (Horns)</option>
                        <option value="Dos Cabezas">Dos Cabezas (Two Heads)</option>
                        <option value="Garras">Garras (Claws)</option>
                        <option value="Piel de Hierro">Piel de Hierro (Iron Hard Skin)</option>
                        <option value="Presencia Perturbadora">Presencia Perturbadora (Disturbing Presence)</option>
                        <option value="Tentáculos">Tentáculos (Tentacles)</option>
                    </optgroup>

                    <optgroup label="Pase (Passing)">
                        <option value="Ave María">Ave María (Hail Mary Pass)</option>
                        <option value="Líder">Líder (Leader)</option>
                        <option value="Nervios de Acero">Nervios de Acero (Nerves of Steel)</option>
                        <option value="Pase">Pase (Pass)</option>
                        <option value="Pase Precipitado">Pase Precipitado (Dump-off)</option>
                        <option value="Precisión">Precisión (Accurate)</option>
                        <option value="Pase Seguro">Pase Seguro (Safe Pass)</option>
                    </optgroup>
                </select>
            </div>

            <button type="submit" class="btn-guardar" id="btn_submit" disabled>Confirmar Subida de Nivel</button>
            <a href="ver_equipo.php?id=<?php echo $jugador['equipo_id']; ?>" class="btn-cancelar">Cancelar</a>
        </form>
    </div>

    <script>
        // Matriz de costes transferida a JavaScript
        const costesSPP = <?php echo json_encode($costesSPP); ?>;
        const nivelActual = <?php echo $avances; ?>;
        const sppDisponibles = parseInt(document.getElementById('spp_disponibles').dataset.spp);

        const selectCategoria = document.getElementById('categoria_mejora');
        const cajaCoste = document.getElementById('caja_coste');
        const textoCoste = document.getElementById('texto_coste');
        const avisoSpp = document.getElementById('aviso_spp');
        const btnSubmit = document.getElementById('btn_submit');

        selectCategoria.addEventListener('change', function() {
            const categoria = this.value;
            const coste = costesSPP[nivelActual][categoria];
            
            textoCoste.innerText = coste;

            if (sppDisponibles >= coste) {
                cajaCoste.classList.add('ok');
                avisoSpp.style.display = 'none';
                btnSubmit.disabled = false;
            } else {
                cajaCoste.classList.remove('ok');
                avisoSpp.style.display = 'block';
                btnSubmit.disabled = true;
            }
        });
    </script>
</body>
</html>