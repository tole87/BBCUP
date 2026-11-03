<?php
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
    $queryP = "SELECT p.*, l.nombre AS liga_nombre,
                      el.id AS local_id, el.nombre_equipo AS local_nombre, el.raza AS local_raza,
                      ev.id AS visitante_id, ev.nombre_equipo AS visitante_nombre, ev.raza AS visitante_raza
               FROM partidos p
               JOIN ligas l ON p.liga_id = l.id
               JOIN equipos el ON p.equipo_local_id = el.id
               LEFT JOIN equipos ev ON p.equipo_visitante_id = ev.id
               WHERE p.id = :id AND p.jugado = 1";
    $stmtP = $baseDatos->prepare($queryP);
    $stmtP->execute([':id' => $partidoId]);
    $partido = $stmtP->fetch(PDO::FETCH_ASSOC);

    if (!$partido) {
        die("Partido no encontrado o aún no se ha disputado.");
    }

    $queryActa = "SELECT aj.*, j.numero, j.nombre, j.posicion, j.equipo_id
                  FROM acta_jugadores aj
                  JOIN jugadores j ON aj.jugador_id = j.id
                  WHERE aj.partido_id = :pid
                  ORDER BY j.numero ASC";
    $stmtA = $baseDatos->prepare($queryActa);
    $stmtA->execute([':pid' => $partidoId]);
    $registros = $stmtA->fetchAll(PDO::FETCH_ASSOC);

    $actaLocal = [];
    $actaVisitante = [];
    foreach ($registros as $r) {
        // Mostramos solo jugadores que tuvieron alguna incidencia o estadística destacada (o todos si se desea)
        if ((int)$r['equipo_id'] === (int)$partido['local_id']) {
            $actaLocal[] = $r;
        } else {
            $actaVisitante[] = $r;
        }
    }

} catch (PDOException $e) {
    die("Error al cargar la crónica del partido: " . $e->getMessage());
}

function traducirLesion($codigo) {
    $mapa = [
        'ninguna' => '-',
        'leve' => 'Lesión Leve (BH)',
        'mng' => 'Se pierde próx. partido (MNG)',
        'ni' => 'Lesión Persistente (NI + MNG)',
        'menos_ma' => '-1 MA Permanente',
        'menos_pa' => '-1 PA Permanente',
        'menos_ag' => '-1 AG Permanente',
        'menos_av' => '-1 AV Permanente',
        'menos_st' => '-1 ST Permanente',
        'muerto' => '💀 MUERTO'
    ];
    return $mapa[$codigo] ?? $codigo;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crónica del Partido - BB CUP</title>
    <style>
        body { margin: 0; font-family: Arial, sans-serif; background-image: url('imagenes/fondo.webp'); background-size: cover; background-position: center; min-height: 100vh; color: #333; padding: 30px 0; }
        .contenedor { background: rgba(255, 255, 255, 0.96); border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.3); padding: 30px; width: 95%; max-width: 1200px; margin: 0 auto; }
        h1 { color: #4e94b8; text-align: center; margin-bottom: 5px; }
        h3 { color: #666; text-align: center; margin-top: 0; }
        .marcador-caja { display: flex; justify-content: space-around; align-items: center; background: #212529; color: #fff; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .equipo-col { text-align: center; flex: 1; }
        .equipo-col h2 { margin: 0; font-size: 24px; color: #ffc107; }
        .resultado-central { font-size: 42px; font-weight: bold; padding: 0 30px; letter-spacing: 3px; }
        .grid-actas { display: flex; gap: 20px; flex-wrap: wrap; }
        .columna-acta { flex: 1; min-width: 450px; background: #f8f9fa; border: 1px solid #ddd; border-radius: 8px; padding: 15px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 10px; }
        table thead { background: #343a40; color: #fff; }
        table th, table td { padding: 8px; border: 1px solid #ddd; text-align: center; }
        .badge-mvp { background: #ffc107; color: #000; padding: 2px 6px; border-radius: 4px; font-weight: bold; font-size: 11px; }
        .btn-volver { background-color: #6bb5d6; color: white; padding: 12px 25px; border-radius: 6px; text-decoration: none; display: inline-block; margin-top: 25px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="contenedor">
        <h1>📜 Acta Oficial del Encuentro</h1>
        <h3><?php echo htmlspecialchars($partido['liga_nombre']); ?> — Jornada <?php echo $partido['jornada']; ?></h3>

        <div class="marcador-caja">
            <div class="equipo-col">
                <h2><?php echo htmlspecialchars($partido['local_nombre']); ?></h2>
                <small><?php echo htmlspecialchars($partido['local_raza']); ?> | Bajas: <?php echo $partido['bajas_local']; ?></small>
                <?php if (!empty($partido['economia_resuelta'])): ?>
                    <div style="margin-top: 6px; color: #28a745; font-size: 13px; font-weight: bold;">Taquilla: +<?php echo number_format($partido['recaudacion_local'], 0, ',', '.'); ?> GP</div>
                <?php endif; ?>
            </div>
            <div class="resultado-central">
                <?php echo $partido['touchdowns_local']; ?> - <?php echo $partido['touchdowns_visitante']; ?>
            </div>
            <div class="equipo-col">
                <h2><?php echo htmlspecialchars($partido['visitante_nombre']); ?></h2>
                <small><?php echo htmlspecialchars($partido['visitante_raza']); ?> | Bajas: <?php echo $partido['bajas_visitante']; ?></small>
                <?php if (!empty($partido['economia_resuelta'])): ?>
                    <div style="margin-top: 6px; color: #28a745; font-size: 13px; font-weight: bold;">Taquilla: +<?php echo number_format($partido['recaudacion_visitante'], 0, ',', '.'); ?> GP</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="grid-actas">
            <?php 
            $bloques = [
                ['titulo' => $partido['local_nombre'], 'filas' => $actaLocal],
                ['titulo' => $partido['visitante_nombre'], 'filas' => $actaVisitante]
            ];
            foreach ($bloques as $b): 
            ?>
            <div class="columna-acta">
                <h3 style="color: #4e94b8; border-bottom: 2px solid #4e94b8; padding-bottom: 5px; text-align: left;"><?php echo htmlspecialchars($b['titulo']); ?></h3>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th style="text-align: left;">Jugador</th>
                            <th>TD</th>
                            <th>Bajas</th>
                            <th>Pases</th>
                            <th>Inter</th>
                            <th>MVP</th>
                            <th>SPP</th>
                            <th>Lesión</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($b['filas'] as $f): ?>
                        <tr>
                            <td><strong><?php echo $f['numero']; ?></strong></td>
                            <td style="text-align: left; font-weight: bold;"><?php echo htmlspecialchars($f['nombre']); ?></td>
                            <td><?php echo $f['touchdowns'] ?: '-'; ?></td>
                            <td><?php echo $f['bajas'] ?: '-'; ?></td>
                            <td><?php echo $f['pases'] ?: '-'; ?></td>
                            <td><?php echo $f['intercepciones'] ?: '-'; ?></td>
                            <td><?php echo $f['mvp'] ? '<span class="badge-mvp">★ MVP</span>' : '-'; ?></td>
                            <td><strong>+<?php echo $f['spp_ganados']; ?></strong></td>
                            <td style="color: <?php echo $f['lesion'] !== 'ninguna' ? '#dc3545' : '#666'; ?>; font-weight: bold;">
                                <?php echo traducirLesion($f['lesion']); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endforeach; ?>
        </div>

        <div style="text-align: center;">
            <a href="ver_liga.php?id=<?php echo $partido['liga_id']; ?>" class="btn-volver">← Volver a la Liga</a>
        </div>
    </div>
</body>
</html>
