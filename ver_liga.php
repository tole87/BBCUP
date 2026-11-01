<?php
session_start();
include('conexion.php');
include('funciones.php');

if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

$emailLogeado = $_SESSION['email'];
$ligaId = (int)($_GET['id'] ?? 0);

$usuarios = obtenerUsuarios();
$usuarioId = null;
foreach ($usuarios as $u) {
    if ($u['email'] === $emailLogeado) {
        $usuarioId = $u['id'];
        break;
    }
}

try {
    // 1. Obtener la liga
    $queryLiga = "SELECT l.*, u.email AS email_creador 
                  FROM ligas l 
                  JOIN usuarios u ON l.creador_id = u.id 
                  WHERE l.id = :id";
    $stmtL = $baseDatos->prepare($queryLiga);
    $stmtL->execute([':id' => $ligaId]);
    $liga = $stmtL->fetch(PDO::FETCH_ASSOC);

    if (!$liga) {
        die("La liga solicitada no existe.");
    }

    $esComisionado = ($usuarioId === (int)$liga['creador_id']);

    // 2. Obtener la tabla de clasificación
    $queryEquipos = "SELECT le.*, e.nombre_equipo, e.raza, e.valor_equipo, u.email AS entrenador 
                     FROM liga_equipos le
                     JOIN equipos e ON le.equipo_id = e.id
                     JOIN usuarios u ON e.usuario_id = u.id
                     WHERE le.liga_id = :liga_id
                     ORDER BY le.puntos DESC, (le.touchdowns_favor - le.touchdowns_contra) DESC, le.touchdowns_favor DESC";
    $stmtE = $baseDatos->prepare($queryEquipos);
    $stmtE->execute([':liga_id' => $ligaId]);
    $equiposClasificacion = $stmtE->fetchAll(PDO::FETCH_ASSOC);

    // 3. Obtener el calendario de partidos si la liga ya comenzó
    $partidosPorJornada = [];
    if ($liga['estado'] !== 'abierta') {
        $queryPartidos = "SELECT p.*, 
                                 el.nombre_equipo AS local_nombre, el.raza AS local_raza,
                                 ev.nombre_equipo AS visitante_nombre, ev.raza AS visitante_raza
                          FROM partidos p
                          JOIN equipos el ON p.equipo_local_id = el.id
                          LEFT JOIN equipos ev ON p.equipo_visitante_id = ev.id
                          WHERE p.liga_id = :liga_id
                          ORDER BY p.jornada ASC, p.id ASC";
        $stmtP = $baseDatos->prepare($queryPartidos);
        $stmtP->execute([':liga_id' => $ligaId]);
        $todosPartidos = $stmtP->fetchAll(PDO::FETCH_ASSOC);

        foreach ($todosPartidos as $partido) {
            $partidosPorJornada[$partido['jornada']][] = $partido;
        }
    }

} catch (PDOException $e) {
    die("Error al cargar los datos: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($liga['nombre']); ?> - BB CUP</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background-image: url('imagenes/fondo.webp');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            color: #333;
        }

        .contenedor {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            padding: 30px;
            width: 95%;
            max-width: 1100px;
            margin: 40px 0;
        }

        h1 {
            color: #4e94b8;
            margin-bottom: 5px;
            text-align: center;
        }

        .descripcion {
            text-align: center;
            color: #666;
            margin-bottom: 25px;
            font-size: 15px;
        }

        .tarjetas-resumen {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 25px;
        }

        .tarjeta-dato {
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            flex: 1;
            text-align: center;
        }

        .tarjeta-dato strong {
            display: block;
            font-size: 13px;
            color: #555;
            text-transform: uppercase;
            margin-bottom: 5px;
        }

        .tarjeta-dato span {
            font-size: 20px;
            font-weight: bold;
            color: #000;
        }

        .codigo-badge {
            background-color: #212529;
            color: #ffc107;
            padding: 4px 10px;
            border-radius: 5px;
            letter-spacing: 1px;
            font-family: monospace;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 15px;
        }

        table thead {
            background-color: #000;
            color: #fff;
        }

        table th, table td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: center;
        }

        table tbody tr:nth-child(even) {
            background-color: #f2f2f2;
        }

        table tbody tr:hover {
            background-color: #e6f7ff;
        }

        .btn-volver {
            background-color: #6bb5d6;
            color: white;
            border: none;
            padding: 12px 25px;
            font-size: 16px;
            cursor: pointer;
            border-radius: 6px;
            text-decoration: none;
            display: inline-block;
            margin-top: 25px;
            transition: background 0.3s;
        }

        .btn-volver:hover {
            background-color: #4e94b8;
        }

        .btn-iniciar {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 14px 20px;
            font-size: 18px;
            font-weight: bold;
            border-radius: 6px;
            cursor: pointer;
            width: 100%;
            margin-top: 20px;
            transition: background 0.3s;
        }

        .btn-iniciar:hover {
            background-color: #218838;
        }

        .jornada-bloque {
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
        }

        .jornada-titulo {
            font-size: 18px;
            color: #4e94b8;
            margin-bottom: 12px;
            border-bottom: 2px solid #4e94b8;
            padding-bottom: 5px;
        }

        .partido-fila {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #fff;
            padding: 10px 15px;
            border-radius: 6px;
            margin-bottom: 8px;
            border: 1px solid #e0e0e0;
        }

        .partido-equipo {
            flex: 1;
            font-weight: bold;
        }

        .partido-equipo.visitante {
            text-align: right;
        }

        .partido-vs {
            padding: 0 15px;
            font-weight: bold;
            color: #888;
        }

        .badge-bye {
            background-color: #ffc107;
            color: #000;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }

        .identificador {
            display: flex;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            padding: 5px 15px;
            position: fixed;
            top: 20px;
            right: 20px;
            align-items: center;
        }

        .identificador img {
            width: 30px;
            height: 30px;
            margin-right: 10px;
            border-radius: 50%;
        }
    </style>
</head>
<body>
    <div class="identificador">
        <img src="imagenes/icono_usuario.jpg" alt="Usuario">
        <p><?php echo htmlspecialchars($emailLogeado); ?></p>
    </div>

    <div class="contenedor">
        <h1><?php echo htmlspecialchars($liga['nombre']); ?></h1>
        <p class="descripcion"><?php echo !empty($liga['descripcion']) ? htmlspecialchars($liga['descripcion']) : 'Liga oficial de Blood Bowl - Temporada 3'; ?></p>

        <div class="tarjetas-resumen">
            <div class="tarjeta-dato">
                <strong>Código de Acceso</strong>
                <span class="codigo-badge"><?php echo htmlspecialchars($liga['codigo_acceso']); ?></span>
            </div>
            <div class="tarjeta-dato">
                <strong>Estado</strong>
                <span style="text-transform: capitalize;"><?php echo str_replace('_', ' ', $liga['estado']); ?></span>
            </div>
            <div class="tarjeta-dato">
                <strong>Equipos Inscritos</strong>
                <span><?php echo count($equiposClasificacion); ?> / <?php echo $liga['max_equipos']; ?></span>
            </div>
            <div class="tarjeta-dato">
                <strong>Comisionado</strong>
                <span style="font-size: 15px;"><?php echo htmlspecialchars($liga['email_creador']); ?></span>
            </div>
        </div>

        <!-- Panel de acción para el comisionado si la liga aún no ha comenzado -->
        <?php if ($esComisionado && $liga['estado'] === 'abierta'): ?>
            <form method="POST" action="iniciar_liga.php" onsubmit="return confirm('¿Seguro que deseas iniciar la liga? Se cerrarán las inscripciones y se generará el calendario de partidos.');">
                <input type="hidden" name="liga_id" value="<?php echo $liga['id']; ?>">
                <button type="submit" class="btn-iniciar" <?php echo count($equiposClasificacion) < 2 ? 'disabled style="background-color: #aaa; cursor: not-allowed;"' : ''; ?>>
                    <?php echo count($equiposClasificacion) < 2 ? 'Esperando al menos 2 equipos para iniciar' : '▶ Iniciar Competición y Generar Calendario'; ?>
                </button>
            </form>
        <?php endif; ?>

        <h2 style="margin-top: 30px;">Clasificación General</h2>

        <?php if (empty($equiposClasificacion)): ?>
            <p style="text-align: center; color: #777; margin: 20px 0;">Todavía no hay ningún equipo inscrito.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Pos</th>
                        <th>Equipo</th>
                        <th>Raza</th>
                        <th>Entrenador</th>
                        <th>TV</th>
                        <th>PTS</th>
                        <th>PJ</th>
                        <th>PG</th>
                        <th>PE</th>
                        <th>PP</th>
                        <th>TD (F/C)</th>
                        <th>Bajas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $pos = 1;
                    foreach ($equiposClasificacion as $eq): 
                    ?>
                    <tr>
                        <td><strong><?php echo $pos++; ?></strong></td>
                        <td style="text-align: left; padding-left: 15px;"><strong><?php echo htmlspecialchars($eq['nombre_equipo']); ?></strong></td>
                        <td><?php echo htmlspecialchars($eq['raza']); ?></td>
                        <td><?php echo htmlspecialchars($eq['entrenador']); ?></td>
                        <td><?php echo number_format($eq['valor_equipo'], 0, ',', '.'); ?> GP</td>
                        <td><strong style="color: #4e94b8; font-size: 16px;"><?php echo $eq['puntos']; ?></strong></td>
                        <td><?php echo $eq['partidos_jugados']; ?></td>
                        <td><?php echo $eq['partidos_ganados']; ?></td>
                        <td><?php echo $eq['partidos_empatados']; ?></td>
                        <td><?php echo $eq['partidos_perdidos']; ?></td>
                        <td><?php echo $eq['touchdowns_favor']; ?> : <?php echo $eq['touchdowns_contra']; ?></td>
                        <td><?php echo $eq['bajas_causadas']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <!-- Sección de Calendario de Jornadas -->
        <?php if (!empty($partidosPorJornada)): ?>
            <h2 style="margin-top: 40px;">Calendario de Competición</h2>
            <?php foreach ($partidosPorJornada as $numJornada => $partidos): ?>
                <div class="jornada-bloque">
                    <div class="jornada-titulo">Jornada <?php echo $numJornada; ?></div>
                    <?php foreach ($partidos as $partido): ?>
                        <div class="partido-fila">
                            <div class="partido-equipo">
                                <?php echo htmlspecialchars($partido['local_nombre']); ?> 
                                <span style="font-size: 12px; color: #777; font-weight: normal;">(<?php echo htmlspecialchars($partido['local_raza']); ?>)</span>
                            </div>

                            <div class="partido-vs">
                                <?php if ($partido['equipo_visitante_id'] === null): ?>
                                    <span class="badge-bye">DESCANSA ESTA JORNADA (BYE)</span>
                                <?php elseif ($partido['jugado']): ?>
                                    <span style="font-size: 16px; color: #000;"><?php echo $partido['touchdowns_local']; ?> - <?php echo $partido['touchdowns_visitante']; ?></span>
                                <?php else: ?>
                                    <span>VS</span>
                                <?php endif; ?>
                            </div>

                            <div class="partido-equipo visitante">
                                <?php if ($partido['equipo_visitante_id'] !== null): ?>
                                    <span style="font-size: 12px; color: #777; font-weight: normal;">(<?php echo htmlspecialchars($partido['visitante_raza']); ?>)</span>
                                    <?php echo htmlspecialchars($partido['visitante_nombre']); ?>
                                <?php else: ?>
                                    <span style="color: #999; font-weight: normal;">-</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div style="text-align: center;">
            <a href="ligas.php" class="btn-volver">← Volver a Mis Ligas</a>
        </div>
    </div>
</body>
</html>