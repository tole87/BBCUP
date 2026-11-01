<?php
session_start();
include('conexion.php');
include('funciones.php');

if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

$emailLogeado = $_SESSION['email'];
$partidoId = (int)($_GET['id'] ?? 0);

if ($partidoId <= 0) {
    header("Location: ligas.php");
    exit();
}

// Obtener ID del usuario logueado
$usuarios = obtenerUsuarios();
$usuarioId = null;
foreach ($usuarios as $u) {
    if ($u['email'] === $emailLogeado) {
        $usuarioId = $u['id'];
        break;
    }
}

try {
    // 1. Consultar datos del partido, equipos y reglas de la liga
    $query = "SELECT p.*, 
                     l.id AS liga_id, l.nombre AS liga_nombre, l.creador_id,
                     l.puntos_victoria, l.puntos_empate, l.puntos_derrota,
                     el.id AS local_id, el.nombre_equipo AS local_nombre, el.raza AS local_raza, el.usuario_id AS local_uid,
                     ev.id AS visitante_id, ev.nombre_equipo AS visitante_nombre, ev.raza AS visitante_raza, ev.usuario_id AS visitante_uid
              FROM partidos p
              JOIN ligas l ON p.liga_id = l.id
              JOIN equipos el ON p.equipo_local_id = el.id
              LEFT JOIN equipos ev ON p.equipo_visitante_id = ev.id
              WHERE p.id = :id";
    $stmt = $baseDatos->prepare($query);
    $stmt->execute([':id' => $partidoId]);
    $partido = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$partido) {
        die("El partido no existe.");
    }

    if ($partido['equipo_visitante_id'] === null) {
        die("Este emparejamiento corresponde a una jornada de descanso (Bye).");
    }

    // Permisos: creador de la liga o cualquiera de los dos entrenadores
    $esAutorizado = ($usuarioId === (int)$partido['creador_id'] || 
                     $usuarioId === (int)$partido['local_uid'] || 
                     $usuarioId === (int)$partido['visitante_uid']);

    if (!$esAutorizado) {
        die("No tienes permisos para registrar el acta de este partido.");
    }

} catch (PDOException $e) {
    die("Error al cargar el partido: " . $e->getMessage());
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tdLocal = (int)($_POST['td_local'] ?? 0);
    $tdVisitante = (int)($_POST['td_visitante'] ?? 0);
    $bajasLocal = (int)($_POST['bajas_local'] ?? 0);
    $bajasVisitante = (int)($_POST['bajas_visitante'] ?? 0);

    if ($tdLocal < 0 || $tdVisitante < 0 || $bajasLocal < 0 || $bajasVisitante < 0) {
        $error = "Los valores de Touchdowns y Bajas no pueden ser negativos.";
    } else {
        try {
            $baseDatos->beginTransaction();

            // Determinar resultado y puntos
            $pVic = (int)$partido['puntos_victoria'];
            $pEmp = (int)$partido['puntos_empate'];
            $pDer = (int)$partido['puntos_derrota'];

            $ptsLocal = 0;
            $ptsVisitante = 0;
            $pgLocal = 0; $peLocal = 0; $ppLocal = 0;
            $pgVisitante = 0; $peVisitante = 0; $ppVisitante = 0;

            if ($tdLocal > $tdVisitante) {
                $ptsLocal = $pVic;
                $ptsVisitante = $pDer;
                $pgLocal = 1;
                $ppVisitante = 1;
            } elseif ($tdLocal < $tdVisitante) {
                $ptsLocal = $pDer;
                $ptsVisitante = $pVic;
                $ppLocal = 1;
                $pgVisitante = 1;
            } else {
                $ptsLocal = $pEmp;
                $ptsVisitante = $pEmp;
                $peLocal = 1;
                $peVisitante = 1;
            }

            // 1. Actualizar el partido
            $updatePartido = "UPDATE partidos 
                              SET touchdowns_local = :td_l, touchdowns_visitante = :td_v,
                                  bajas_local = :b_l, bajas_visitante = :b_v,
                                  jugado = 1, fecha_partido = NOW()
                              WHERE id = :id";
            $stmtUpP = $baseDatos->prepare($updatePartido);
            $stmtUpP->execute([
                ':td_l' => $tdLocal,
                ':td_v' => $tdVisitante,
                ':b_l' => $bajasLocal,
                ':b_v' => $bajasVisitante,
                ':id' => $partidoId
            ]);

            // 2. Actualizar estadísticas del equipo local
            $updateLocal = "UPDATE liga_equipos 
                            SET puntos = puntos + :pts,
                                partidos_jugados = partidos_jugados + 1,
                                partidos_ganados = partidos_ganados + :pg,
                                partidos_empatados = partidos_empatados + :pe,
                                partidos_perdidos = partidos_perdidos + :pp,
                                touchdowns_favor = touchdowns_favor + :td_f,
                                touchdowns_contra = touchdowns_contra + :td_c,
                                bajas_causadas = bajas_causadas + :bajas
                            WHERE liga_id = :liga_id AND equipo_id = :equipo_id";
            $stmtUpL = $baseDatos->prepare($updateLocal);
            $stmtUpL->execute([
                ':pts' => $ptsLocal,
                ':pg' => $pgLocal,
                ':pe' => $peLocal,
                ':pp' => $ppLocal,
                ':td_f' => $tdLocal,
                ':td_c' => $tdVisitante,
                ':bajas' => $bajasLocal,
                ':liga_id' => $partido['liga_id'],
                ':equipo_id' => $partido['local_id']
            ]);

            // 3. Actualizar estadísticas del equipo visitante
            $updateVisitante = "UPDATE liga_equipos 
                                SET puntos = puntos + :pts,
                                    partidos_jugados = partidos_jugados + 1,
                                    partidos_ganados = partidos_ganados + :pg,
                                    partidos_empatados = partidos_empatados + :pe,
                                    partidos_perdidos = partidos_perdidos + :pp,
                                    touchdowns_favor = touchdowns_favor + :td_f,
                                    touchdowns_contra = touchdowns_contra + :td_c,
                                    bajas_causadas = bajas_causadas + :bajas
                                WHERE liga_id = :liga_id AND equipo_id = :equipo_id";
            $stmtUpV = $baseDatos->prepare($updateVisitante);
            $stmtUpV->execute([
                ':pts' => $ptsVisitante,
                ':pg' => $pgVisitante,
                ':pe' => $peVisitante,
                ':pp' => $ppVisitante,
                ':td_f' => $tdVisitante,
                ':td_c' => $tdLocal,
                ':bajas' => $bajasVisitante,
                ':liga_id' => $partido['liga_id'],
                ':equipo_id' => $partido['visitante_id']
            ]);

            $baseDatos->commit();
            header("Location: ver_liga.php?id=" . $partido['liga_id'] . "&exito=acta_guardada");
            exit();

        } catch (Exception $e) {
            $baseDatos->rollBack();
            $error = "Error al registrar el acta: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acta de Partido - Jornada <?php echo $partido['jornada']; ?></title>
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
            width: 90%;
            max-width: 650px;
            text-align: center;
        }

        h1 {
            color: #4e94b8;
            margin-bottom: 5px;
        }

        h3 {
            color: #666;
            margin-top: 0;
            margin-bottom: 25px;
        }

        .enfrentamiento-header {
            display: flex;
            justify-content: space-around;
            align-items: center;
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 25px;
        }

        .equipo-caja {
            width: 40%;
        }

        .equipo-caja strong {
            display: block;
            font-size: 18px;
        }

        .equipo-caja span {
            font-size: 13px;
            color: #666;
        }

        .vs {
            font-size: 20px;
            font-weight: bold;
            color: #888;
        }

        .formulario-acta {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 25px;
        }

        .columna-datos {
            flex: 1;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
        }

        .columna-datos label {
            display: block;
            font-weight: bold;
            font-size: 14px;
            margin-top: 10px;
            margin-bottom: 5px;
        }

        .columna-datos input {
            width: 80px;
            padding: 8px;
            font-size: 20px;
            font-weight: bold;
            text-align: center;
            border: 1px solid #ccc;
            border-radius: 6px;
        }

        .btn-guardar {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 14px 20px;
            font-size: 18px;
            font-weight: bold;
            border-radius: 6px;
            cursor: pointer;
            width: 100%;
            transition: background 0.3s;
        }

        .btn-guardar:hover {
            background-color: #218838;
        }

        .btn-cancelar {
            display: inline-block;
            margin-top: 15px;
            color: #666;
            text-decoration: none;
            font-size: 14px;
        }

        .btn-cancelar:hover {
            text-decoration: underline;
        }

        .alerta-error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="contenedor">
        <h1>Acta de Partido</h1>
        <h3><?php echo htmlspecialchars($partido['liga_nombre']); ?> — Jornada <?php echo $partido['jornada']; ?></h3>

        <?php if (!empty($error)): ?>
            <div class="alerta-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="enfrentamiento-header">
            <div class="equipo-caja">
                <strong><?php echo htmlspecialchars($partido['local_nombre']); ?></strong>
                <span>(<?php echo htmlspecialchars($partido['local_raza']); ?>)</span>
            </div>
            <div class="vs">VS</div>
            <div class="equipo-caja">
                <strong><?php echo htmlspecialchars($partido['visitante_nombre']); ?></strong>
                <span>(<?php echo htmlspecialchars($partido['visitante_raza']); ?>)</span>
            </div>
        </div>

        <form method="POST" action="acta_partido.php?id=<?php echo $partidoId; ?>">
            <div class="formulario-acta">
                <div class="columna-datos">
                    <h4 style="margin: 0 0 10px 0; color: #4e94b8;">Local</h4>
                    <label for="td_local">Touchdowns</label>
                    <input type="number" id="td_local" name="td_local" min="0" max="20" value="<?php echo $partido['touchdowns_local'] ?? 0; ?>" required>
                    <label for="bajas_local">Bajas Causadas</label>
                    <input type="number" id="bajas_local" name="bajas_local" min="0" max="20" value="<?php echo $partido['bajas_local'] ?? 0; ?>" required>
                </div>

                <div class="columna-datos">
                    <h4 style="margin: 0 0 10px 0; color: #4e94b8;">Visitante</h4>
                    <label for="td_visitante">Touchdowns</label>
                    <input type="number" id="td_visitante" name="td_visitante" min="0" max="20" value="<?php echo $partido['touchdowns_visitante'] ?? 0; ?>" required>
                    <label for="bajas_visitante">Bajas Causadas</label>
                    <input type="number" id="bajas_visitante" name="bajas_visitante" min="0" max="20" value="<?php echo $partido['bajas_visitante'] ?? 0; ?>" required>
                </div>
            </div>

            <button type="submit" class="btn-guardar">Confirmar y Guardar Acta</button>
            <a href="ver_liga.php?id=<?php echo $partido['liga_id']; ?>" class="btn-cancelar">Cancelar y volver a la liga</a>
        </form>
    </div>
</body>
</html>