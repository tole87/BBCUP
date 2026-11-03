<?php
// Forzar visualización de errores para evitar pantallas en blanco
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include('conexion.php');
include('funciones.php');

if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

$partidoId = (int)($_GET['id'] ?? 0);

if ($partidoId <= 0) {
    header("Location: ligas.php");
    exit();
}

try {
    $queryP = "SELECT p.*, 
                      el.id AS local_id, el.nombre_equipo AS local_nombre, el.fans_dedicados AS fans_local, el.usuario_id AS uid_local,
                      ev.id AS visitante_id, ev.nombre_equipo AS visitante_nombre, ev.fans_dedicados AS fans_visitante, ev.usuario_id AS uid_visitante
               FROM partidos p
               JOIN equipos el ON p.equipo_local_id = el.id
               JOIN equipos ev ON p.equipo_visitante_id = ev.id
               WHERE p.id = :id";
    $stmtP = $baseDatos->prepare($queryP);
    $stmtP->execute([':id' => $partidoId]);
    $partido = $stmtP->fetch(PDO::FETCH_ASSOC);

    if (!$partido || empty($partido['jugado']) || !empty($partido['economia_resuelta'])) {
        die("Partido no válido, no jugado o la economía ya ha sido resuelta.");
    }

} catch (PDOException $e) {
    die("Error en la base de datos al cargar el partido: " . $e->getMessage());
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $d6TaqL = (int)$_POST['d6_taq_local'];
    $d6FanL = (int)$_POST['d6_fan_local'];
    $d6TaqV = (int)$_POST['d6_taq_visitante'];
    $d6FanV = (int)$_POST['d6_fan_visitante'];

    if (min($d6TaqL, $d6FanL, $d6TaqV, $d6FanV) < 1 || max($d6TaqL, $d6FanL, $d6TaqV, $d6FanV) > 6) {
        $error = "Todos los valores de los dados deben estar entre 1 y 6.";
    } else {
        $tdLocal = (int)$partido['touchdowns_local'];
        $tdVisitante = (int)$partido['touchdowns_visitante'];
        
        $fansL = (int)$partido['fans_local'];
        $fansV = (int)$partido['fans_visitante'];

        // Cálculo de Tesorería: (1D6 + Fans) x 10k
        $oroLocal = ($d6TaqL + $fansL) * 10000;
        $oroVisitante = ($d6TaqV + $fansV) * 10000;

        // Modificación de Fans Dedicados (Reglas Temporada 3)
        if ($tdLocal > $tdVisitante) {
            if ($d6FanL >= 5) $fansL++;
        } elseif ($tdLocal < $tdVisitante) {
            if ($d6FanL < $fansL) $fansL = max(0, $fansL - 1);
        } else {
            if ($d6FanL >= 5) $fansL++;
        }

        if ($tdVisitante > $tdLocal) {
            if ($d6FanV >= 5) $fansV++;
        } elseif ($tdVisitante < $tdLocal) {
            if ($d6FanV < $fansV) $fansV = max(0, $fansV - 1);
        } else {
            if ($d6FanV >= 5) $fansV++;
        }

        try {
            $baseDatos->beginTransaction();

            $stmtEqL = $baseDatos->prepare("UPDATE equipos SET tesoreria = tesoreria + ?, fans_dedicados = ? WHERE id = ?");
            $stmtEqL->execute([$oroLocal, $fansL, $partido['local_id']]);

            $stmtEqV = $baseDatos->prepare("UPDATE equipos SET tesoreria = tesoreria + ?, fans_dedicados = ? WHERE id = ?");
            $stmtEqV->execute([$oroVisitante, $fansV, $partido['visitante_id']]);

            $stmtUpP = $baseDatos->prepare("UPDATE partidos SET economia_resuelta = 1, recaudacion_local = ?, recaudacion_visitante = ? WHERE id = ?");
            $stmtUpP->execute([$oroLocal, $oroVisitante, $partidoId]);

            // COMPROBACIÓN AUTOMÁTICA DE FIN DE LIGA
            // Verificamos si queda algún partido en la liga que no esté jugado o cuya economía no esté resuelta
            $stmtPendientes = $baseDatos->prepare("SELECT COUNT(*) FROM partidos WHERE liga_id = ? AND equipo_visitante_id IS NOT NULL AND (jugado = 0 OR economia_resuelta = 0)");
            $stmtPendientes->execute([$partido['liga_id']]);
            if ((int)$stmtPendientes->fetchColumn() === 0) {
                $stmtFin = $baseDatos->prepare("UPDATE ligas SET estado = 'finalizada' WHERE id = ?");
                $stmtFin->execute([$partido['liga_id']]);
            }

            $baseDatos->commit();
            header("Location: ver_liga.php?id=" . $partido['liga_id'] . "&exito=economia_resuelta");
            exit();

        } catch (Exception $e) {
            $baseDatos->rollBack();
            $error = "Error al guardar la economía: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Economía Post-Partido - BB CUP</title>
    <style>
        body { margin: 0; font-family: Arial, sans-serif; background-image: url('imagenes/fondo.webp'); background-size: cover; background-position: center; display: flex; justify-content: center; align-items: center; min-height: 100vh; color: #333; }
        .contenedor { background: rgba(255, 255, 255, 0.95); border-radius: 10px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2); padding: 30px; width: 90%; max-width: 600px; }
        h1 { color: #ffc107; text-align: center; margin-bottom: 5px; text-shadow: 1px 1px 2px #000; }
        .bloque-equipo { background: #f8f9fa; border: 1px solid #ddd; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .bloque-equipo h3 { margin: 0 0 10px 0; color: #4e94b8; border-bottom: 2px solid #ccc; padding-bottom: 5px; }
        .grupo-dados { display: flex; gap: 15px; }
        .campo-dado { flex: 1; }
        .campo-dado label { display: block; font-size: 13px; font-weight: bold; margin-bottom: 5px; }
        .campo-dado input { width: 100%; padding: 8px; font-size: 16px; border: 1px solid #ccc; border-radius: 4px; text-align: center; box-sizing: border-box; }
        .ayuda-reglas { font-size: 12px; color: #666; margin-top: 5px; }
        .btn-guardar { background-color: #ffc107; color: #000; border: none; padding: 12px; font-size: 16px; font-weight: bold; border-radius: 6px; cursor: pointer; width: 100%; margin-top: 10px; }
        .btn-guardar:hover { background-color: #e0a800; }
        .alerta-error { background-color: #f8d7da; color: #721c24; padding: 10px; border-radius: 6px; margin-bottom: 15px; text-align: center; }
    </style>
</head>
<body>
    <div class="contenedor">
        <h1>💰 Cobrar Taquilla y Fans</h1>
        <p style="text-align: center; color: #555; margin-bottom: 20px;">Introduce el resultado de los dados 1D6 lanzados tras el partido.</p>

        <?php if (!empty($error)): ?>
            <div class="alerta-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="economia_partido.php?id=<?php echo $partidoId; ?>">
            
            <div class="bloque-equipo">
                <h3><?php echo htmlspecialchars($partido['local_nombre']); ?> (Fans actuales: <?php echo $partido['fans_local']; ?>)</h3>
                <div class="grupo-dados">
                    <div class="campo-dado">
                        <label>1D6 Taquilla (Ganador repite)</label>
                        <input type="number" name="d6_taq_local" min="1" max="6" required>
                    </div>
                    <div class="campo-dado">
                        <label>1D6 Modificación Fans</label>
                        <input type="number" name="d6_fan_local" min="1" max="6" required>
                        <div class="ayuda-reglas">
                            <?php if ($partido['touchdowns_local'] > $partido['touchdowns_visitante']): ?>
                                Ganador: Con un 5 o 6, suma +1 Fan.
                            <?php elseif ($partido['touchdowns_local'] < $partido['touchdowns_visitante']): ?>
                                Perdedor: Si sacas menos de <?php echo $partido['fans_local']; ?>, pierdes -1 Fan.
                            <?php else: ?>
                                Empate: Con un 5 o 6, suma +1 Fan.
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bloque-equipo">
                <h3><?php echo htmlspecialchars($partido['visitante_nombre']); ?> (Fans actuales: <?php echo $partido['fans_visitante']; ?>)</h3>
                <div class="grupo-dados">
                    <div class="campo-dado">
                        <label>1D6 Taquilla (Ganador repite)</label>
                        <input type="number" name="d6_taq_visitante" min="1" max="6" required>
                    </div>
                    <div class="campo-dado">
                        <label>1D6 Modificación Fans</label>
                        <input type="number" name="d6_fan_visitante" min="1" max="6" required>
                        <div class="ayuda-reglas">
                            <?php if ($partido['touchdowns_visitante'] > $partido['touchdowns_local']): ?>
                                Ganador: Con un 5 o 6, suma +1 Fan.
                            <?php elseif ($partido['touchdowns_visitante'] < $partido['touchdowns_local']): ?>
                                Perdedor: Si sacas menos de <?php echo $partido['fans_visitante']; ?>, pierdes -1 Fan.
                            <?php else: ?>
                                Empate: Con un 5 o 6, suma +1 Fan.
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn-guardar">Registrar Ingresos en Tesorería</button>
            <a href="ver_liga.php?id=<?php echo $partido['liga_id']; ?>" style="display: block; text-align: center; margin-top: 15px; color: #666; text-decoration: none;">Cancelar</a>
        </form>
    </div>
</body>
</html>