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
    $queryEq = "SELECT e.* FROM equipos e JOIN usuarios u ON e.usuario_id = u.id WHERE e.id = :id AND u.email = :email";
    $stmtEq = $baseDatos->prepare($queryEq);
    $stmtEq->execute([':id' => $equipoId, ':email' => $emailLogeado]);
    $equipo = $stmtEq->fetch(PDO::FETCH_ASSOC);

    if (!$equipo) {
        die("Equipo no encontrado o sin permisos.");
    }

    // 2. Comprobar cuántos jugadores activos y cuántas estrellas tiene el equipo actualmente
    $queryJug = "SELECT COUNT(*) FROM jugadores WHERE equipo_id = :id AND (muerto = 0 OR muerto IS NULL)";
    $stmtJug = $baseDatos->prepare($queryJug);
    $stmtJug->execute([':id' => $equipoId]);
    $totalJugadoresActivos = (int)$stmtJug->fetchColumn();

    $queryEstActuales = "SELECT COUNT(*) FROM jugadores WHERE equipo_id = :id AND posicion = 'Jugador Estrella' AND (muerto = 0 OR muerto IS NULL)";
    $stmtEstAct = $baseDatos->prepare($queryEstActuales);
    $stmtEstAct->execute([':id' => $equipoId]);
    $totalEstrellasActivas = (int)$stmtEstAct->fetchColumn();

    // 3. Obtener estrellas disponibles compatibles con su raza (o 'Any Team')
    $queryEstrellas = "SELECT * FROM jugadores_estrella WHERE regiones_permitidas LIKE :raza OR regiones_permitidas = 'Any Team' ORDER BY coste ASC";
    $stmtEst = $baseDatos->prepare($queryEstrellas);
    $stmtEst->execute([':raza' => '%' . $equipo['raza'] . '%']);
    $estrellasDisponibles = $stmtEst->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error en base de datos: " . $e->getMessage());
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $estrellaId = (int)($_POST['estrella_id'] ?? 0);

    $querySel = "SELECT * FROM jugadores_estrella WHERE id = :id";
    $stmtSel = $baseDatos->prepare($querySel);
    $stmtSel->execute([':id' => $estrellaId]);
    $estrella = $stmtSel->fetch(PDO::FETCH_ASSOC);

    if (!$estrella) {
        $error = "Jugador Estrella no válido.";
    } elseif ($totalEstrellasActivas >= 2) {
        $error = "El reglamento de Temporada 3 permite contratar un máximo de 2 Jugadores Estrella por partido.";
    } elseif ($totalJugadoresActivos >= 16) {
        $error = "No puedes contratar un Jugador Estrella si tu plantilla alcanza los 16 jugadores.";
    } elseif ($estrella['coste'] > $equipo['tesoreria']) {
        $error = "No tienes suficiente tesorería para pagar la contratación de " . htmlspecialchars($estrella['nombre']) . ".";
    } else {
        try {
            $baseDatos->beginTransaction();

            $insertEstrella = "INSERT INTO jugadores (equipo_id, numero, nombre, posicion, coste, ma, st, ag, pa, av, habilidades, valor_actual, spp, spp_gastados, avances) 
                              VALUES (:eq_id, 99, :nom, 'Jugador Estrella', :coste, :ma, :st, :ag, :pa, :av, :habs, :coste, 0, 0, 0)";
            $stmtIns = $baseDatos->prepare($insertEstrella);
            $stmtIns->execute([
                ':eq_id' => $equipoId,
                ':nom' => $estrella['nombre'] . ' (Estrella)',
                ':coste' => $estrella['coste'],
                ':ma' => $estrella['ma'],
                ':st' => $estrella['st'],
                ':ag' => $estrella['ag'],
                ':pa' => $estrella['pa'],
                ':av' => $estrella['av'],
                ':habs' => $estrella['habilidades']
            ]);

            $updateEq = "UPDATE equipos SET tesoreria = tesoreria - :coste WHERE id = :eq_id";
            $stmtUp = $baseDatos->prepare($updateEq);
            $stmtUp->execute([':coste' => $estrella['coste'], ':eq_id' => $equipoId]);

            $baseDatos->commit();
            recalcularTV($equipoId);
            header("Location: ver_equipo.php?id=" . $equipoId . "&exito=estrella_fichada");
            exit();

        } catch (Exception $e) {
            $baseDatos->rollBack();
            $error = "Error al contratar al Jugador Estrella: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contratar Jugador Estrella - BB CUP</title>
    <style>
        body { margin: 0; font-family: Arial, sans-serif; background-image: url('imagenes/fondo.webp'); background-size: cover; background-position: center; display: flex; justify-content: center; align-items: center; min-height: 100vh; color: #333; }
        .contenedor { background: rgba(255, 255, 255, 0.95); border-radius: 10px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2); padding: 30px; width: 90%; max-width: 850px; }
        h1 { color: #d9534f; text-align: center; margin-bottom: 5px; }
        .info-banco { background: #f8f9fa; border: 1px solid #ddd; padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; margin-bottom: 20px; }
        table thead { background-color: #212529; color: white; }
        table th, table td { padding: 8px; border: 1px solid #ddd; text-align: center; }
        .btn-contratar { background-color: #d9534f; color: white; border: none; padding: 6px 12px; font-weight: bold; border-radius: 4px; cursor: pointer; }
        .btn-contratar:hover { background-color: #c9302c; }
        .alerta-error { background-color: #f8d7da; color: #721c24; padding: 10px; border-radius: 6px; margin-bottom: 15px; text-align: center; font-weight: bold; }
        .btn-volver { display: block; text-align: center; color: #666; text-decoration: none; margin-top: 15px; }
    </style>
</head>
<body>
    <div class="contenedor">
        <h1>⭐ Contratación de Jugadores Estrella</h1>
        <p style="text-align: center; color: #555;">Los Jugadores Estrella se contratan por 1 partido (Máximo 2 por encuentro) y abandonan el equipo tras cerrar el acta.</p>

        <div class="info-banco">
            <div>Equipo: <strong><?php echo htmlspecialchars($equipo['nombre_equipo']); ?> (<?php echo htmlspecialchars($equipo['raza']); ?>)</strong></div>
            <div>Estrellas en plantilla: <strong><?php echo $totalEstrellasActivas; ?> / 2</strong></div>
            <div>Tesorería: <strong style="color: #28a745;"><?php echo number_format($equipo['tesoreria'], 0, ',', '.'); ?> GP</strong></div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alerta-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <table>
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Coste</th>
                    <th>MA</th>
                    <th>ST</th>
                    <th>AG</th>
                    <th>PA</th>
                    <th>AV</th>
                    <th>Habilidades</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($estrellasDisponibles)): ?>
                    <tr><td colspan="9" style="padding: 20px;">No hay Jugadores Estrella disponibles para esta raza en este momento.</td></tr>
                <?php else: ?>
                    <?php foreach ($estrellasDisponibles as $est): ?>
                    <tr>
                        <td style="font-weight: bold; text-align: left; padding-left: 10px;"><?php echo htmlspecialchars($est['nombre']); ?></td>
                        <td style="color: #28a745; font-weight: bold;"><?php echo number_format($est['coste'], 0, ',', '.'); ?> GP</td>
                        <td><?php echo $est['ma']; ?></td>
                        <td><?php echo $est['st']; ?></td>
                        <td><?php echo $est['ag']; ?></td>
                        <td><?php echo $est['pa']; ?></td>
                        <td><?php echo $est['av']; ?></td>
                        <td style="text-align: left; font-size: 11px;"><?php echo htmlspecialchars($est['habilidades']); ?></td>
                        <td>
                            <?php if ($est['coste'] <= $equipo['tesoreria'] && $totalJugadoresActivos < 16 && $totalEstrellasActivas < 2): ?>
                                <form method="POST" action="fichar_estrella.php?id=<?php echo $equipoId; ?>" onsubmit="return confirm('¿Seguro que deseas contratar a <?php echo addslashes($est['nombre']); ?> para el próximo partido?');">
                                    <input type="hidden" name="estrella_id" value="<?php echo $est['id']; ?>">
                                    <button type="submit" class="btn-contratar">Contratar</button>
                                </form>
                            <?php else: ?>
                                <span style="color: #999; font-size: 11px;">No disponible</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <a href="ver_equipo.php?id=<?php echo $equipoId; ?>" class="btn-volver">← Volver al Roster del Equipo</a>
    </div>
</body>
</html>
