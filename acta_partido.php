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

$usuarios = obtenerUsuarios();
$usuarioId = null;
foreach ($usuarios as $u) {
    if ($u['email'] === $emailLogeado) {
        $usuarioId = $u['id'];
        break;
    }
}

try {
    $queryP = "SELECT p.*, 
                      l.id AS liga_id, l.nombre AS liga_nombre, l.creador_id,
                      l.puntos_victoria, l.puntos_empate, l.puntos_derrota,
                      el.id AS local_id, el.nombre_equipo AS local_nombre, el.raza AS local_raza, el.usuario_id AS local_uid,
                      ev.id AS visitante_id, ev.nombre_equipo AS visitante_nombre, ev.raza AS visitante_raza, ev.usuario_id AS visitante_uid
               FROM partidos p
               JOIN ligas l ON p.liga_id = l.id
               JOIN equipos el ON p.equipo_local_id = el.id
               LEFT JOIN equipos ev ON p.equipo_visitante_id = ev.id
               WHERE p.id = :id";
    $stmtP = $baseDatos->prepare($queryP);
    $stmtP->execute([':id' => $partidoId]);
    $partido = $stmtP->fetch(PDO::FETCH_ASSOC);

    if (!$partido || $partido['equipo_visitante_id'] === null) {
        die("Partido inválido o de descanso.");
    }

    $esAutorizado = ($usuarioId === (int)$partido['creador_id'] || $usuarioId === (int)$partido['local_uid'] || $usuarioId === (int)$partido['visitante_uid']);
    if (!$esAutorizado) {
        die("No tienes permisos para registrar el acta de este partido.");
    }

    $queryJ = "SELECT * FROM jugadores WHERE equipo_id = :eq_id AND (muerto = 0 OR muerto IS NULL) ORDER BY numero ASC";
    $stmtJL = $baseDatos->prepare($queryJ);
    $stmtJL->execute([':eq_id' => $partido['local_id']]);
    $jugadoresLocal = $stmtJL->fetchAll(PDO::FETCH_ASSOC);

    $stmtJV = $baseDatos->prepare($queryJ);
    $stmtJV->execute([':eq_id' => $partido['visitante_id']]);
    $jugadoresVisitante = $stmtJV->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error al cargar datos: " . $e->getMessage());
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mvpLocal = (int)($_POST['mvp_local'] ?? 0);
    $mvpVisitante = (int)($_POST['mvp_visitante'] ?? 0);
    $statsLocal = $_POST['jug_local'] ?? [];
    $statsVisitante = $_POST['jug_visitante'] ?? [];

    if ($mvpLocal === 0 || $mvpVisitante === 0) {
        $error = "Error: Es obligatorio seleccionar un MVP para ambos equipos.";
    } else {
        $totalTdLocal = array_sum(array_column($statsLocal, 'td'));
        $totalBajasLocal = array_sum(array_column($statsLocal, 'bajas'));
        $totalTdVisitante = array_sum(array_column($statsVisitante, 'td'));
        $totalBajasVisitante = array_sum(array_column($statsVisitante, 'bajas'));

        try {
            $baseDatos->beginTransaction();

            $ptsLocal = 0; $ptsVisitante = 0;
            $pgL = 0; $peL = 0; $ppL = 0;
            $pgV = 0; $peV = 0; $ppV = 0;

            if ($totalTdLocal > $totalTdVisitante) {
                $ptsLocal = (int)$partido['puntos_victoria']; $ptsVisitante = (int)$partido['puntos_derrota'];
                $pgL = 1; $ppV = 1;
            } elseif ($totalTdLocal < $totalTdVisitante) {
                $ptsLocal = (int)$partido['puntos_derrota']; $ptsVisitante = (int)$partido['puntos_victoria'];
                $ppL = 1; $pgV = 1;
            } else {
                $ptsLocal = (int)$partido['puntos_empate']; $ptsVisitante = (int)$partido['puntos_empate'];
                $peL = 1; $peV = 1;
            }

            $stmtUpP = $baseDatos->prepare("UPDATE partidos SET touchdowns_local = ?, touchdowns_visitante = ?, bajas_local = ?, bajas_visitante = ?, jugado = 1, fecha_partido = NOW() WHERE id = ?");
            $stmtUpP->execute([$totalTdLocal, $totalTdVisitante, $totalBajasLocal, $totalBajasVisitante, $partidoId]);

            $stmtLE = $baseDatos->prepare("UPDATE liga_equipos SET puntos = puntos + ?, partidos_jugados = partidos_jugados + 1, partidos_ganados = partidos_ganados + ?, partidos_empatados = partidos_empatados + ?, partidos_perdidos = partidos_perdidos + ?, touchdowns_favor = touchdowns_favor + ?, touchdowns_contra = touchdowns_contra + ?, bajas_causadas = bajas_causadas + ? WHERE liga_id = ? AND equipo_id = ?");
            
            $stmtLE->execute([$ptsLocal, $pgL, $peL, $ppL, $totalTdLocal, $totalTdVisitante, $totalBajasLocal, $partido['liga_id'], $partido['local_id']]);
            $stmtLE->execute([$ptsVisitante, $pgV, $peV, $ppV, $totalTdVisitante, $totalTdLocal, $totalBajasVisitante, $partido['liga_id'], $partido['visitante_id']]);

            $stmtActaJ = $baseDatos->prepare("INSERT INTO acta_jugadores (partido_id, jugador_id, touchdowns, bajas, pases, intercepciones, mvp, lesion, spp_ganados) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmtUpJ = $baseDatos->prepare("UPDATE jugadores SET spp = spp + ?, touchdowns = touchdowns + ?, bajas_causadas = bajas_causadas + ?, pases = pases + ?, intercepciones = intercepciones + ?, mvps = mvps + ?, partidos_jugados = partidos_jugados + 1, mng = ?, muerto = ?, ni = ni + ?, lesiones_ma = lesiones_ma + ?, lesiones_st = lesiones_st + ?, lesiones_ag = lesiones_ag + ?, lesiones_pa = lesiones_pa + ?, lesiones_av = lesiones_av + ? WHERE id = ?");
            $stmtResetMng = $baseDatos->prepare("UPDATE jugadores SET mng = 0 WHERE id = ?");

            $procesarPlantilla = function($jugadoresEquipo, $stats, $mvpSeleccionado) use ($partidoId, $stmtActaJ, $stmtUpJ, $stmtResetMng) {
                foreach ($jugadoresEquipo as $jug) {
                    $jId = (int)$jug['id'];

                    // Si el jugador tenía MNG=1 antes de empezar el partido, cumple su partido de baja y se recupera para el siguiente
                    if (!empty($jug['mng'])) {
                        $stmtResetMng->execute([$jId]);
                        continue;
                    }

                    $d = $stats[$jId] ?? [];
                    $td = max(0, (int)($d['td'] ?? 0));
                    $bajas = max(0, (int)($d['bajas'] ?? 0));
                    $pases = max(0, (int)($d['pases'] ?? 0));
                    $inter = max(0, (int)($d['inter'] ?? 0));
                    $lesion = $d['lesion'] ?? 'ninguna';
                    $esMvp = ($jId === $mvpSeleccionado) ? 1 : 0;

                    // Los Jugadores Estrella no acumulan SPP regulares
                    $esEstrella = ($jug['posicion'] === 'Jugador Estrella');
                    $spp = $esEstrella ? 0 : (($td * 3) + ($bajas * 2) + ($pases * 1) + ($inter * 2) + ($esMvp * 4));
                    
                    $mng = in_array($lesion, ['mng', 'ni', 'menos_ma', 'menos_st', 'menos_ag', 'menos_pa', 'menos_av']) ? 1 : 0;
                    $muerto = ($lesion === 'muerto') ? 1 : 0;
                    
                    $addNi = ($lesion === 'ni') ? 1 : 0;
                    $addMa = ($lesion === 'menos_ma') ? 1 : 0;
                    $addSt = ($lesion === 'menos_st') ? 1 : 0;
                    $addAg = ($lesion === 'menos_ag') ? 1 : 0;
                    $addPa = ($lesion === 'menos_pa') ? 1 : 0;
                    $addAv = ($lesion === 'menos_av') ? 1 : 0;

                    if (!$esEstrella) {
                        $stmtActaJ->execute([$partidoId, $jId, $td, $bajas, $pases, $inter, $esMvp, $lesion, $spp]);
                    }
                    $stmtUpJ->execute([$spp, $td, $bajas, $pases, $inter, $esMvp, $mng, $muerto, $addNi, $addMa, $addSt, $addAg, $addPa, $addAv, $jId]);
                }
            };

            $procesarPlantilla($jugadoresLocal, $statsLocal, $mvpLocal);
            $procesarPlantilla($jugadoresVisitante, $statsVisitante, $mvpVisitante);

            // Limpiar Jugadores Estrella y Mercenarios temporales (Journeymen) al concluir el encuentro
            $stmtDelTemp = $baseDatos->prepare("DELETE FROM jugadores WHERE equipo_id IN (?, ?) AND posicion IN ('Jugador Estrella', 'Sustituto (Journeyman)')");
            $stmtDelTemp->execute([$partido['local_id'], $partido['visitante_id']]);

            // Comprobar si quedan partidos reales (no descansos BYE) sin jugar en la liga; si no quedan, finalizar la liga
            $stmtPendientes = $baseDatos->prepare("SELECT COUNT(*) FROM partidos WHERE liga_id = ? AND equipo_visitante_id IS NOT NULL AND jugado = 0");
            $stmtPendientes->execute([$partido['liga_id']]);
            if ((int)$stmtPendientes->fetchColumn() === 0) {
                $stmtFin = $baseDatos->prepare("UPDATE ligas SET estado = 'finalizada' WHERE id = ?");
                $stmtFin->execute([$partido['liga_id']]);
            }

            $baseDatos->commit();

            // Recalcular el Valor de Equipo (CTV) de ambos equipos teniendo en cuenta las bajas y los nuevos MNG
            recalcularTV($partido['local_id']);
            recalcularTV($partido['visitante_id']);

            header("Location: ver_liga.php?id=" . $partido['liga_id'] . "&exito=acta_completa");
            exit();

        } catch (Exception $e) {
            $baseDatos->rollBack();
            $error = "Error al procesar el acta: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acta Oficial de Partido - Blood Bowl</title>
    <style>
        body { margin: 0; font-family: Arial, sans-serif; background-image: url('imagenes/fondo.webp'); background-size: cover; background-position: center; background-repeat: no-repeat; min-height: 100vh; color: #333; padding: 30px 0; }
        .contenedor { background: rgba(255, 255, 255, 0.98); border-radius: 10px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3); padding: 30px; width: 95%; max-width: 1400px; margin: 0 auto; }
        h1 { color: #4e94b8; margin-bottom: 5px; text-align: center; }
        h3 { color: #666; margin-top: 0; margin-bottom: 25px; text-align: center; }
        .header-partido { display: flex; justify-content: space-between; align-items: center; background: #212529; color: white; padding: 15px 25px; border-radius: 8px; margin-bottom: 25px; }
        .equipo-titular { font-size: 20px; font-weight: bold; }
        .bloque-equipo { background: #fdfdfd; border: 1px solid #ddd; border-radius: 8px; padding: 15px; margin-bottom: 30px; overflow-x: auto; }
        .titulo-equipo { color: #4e94b8; font-size: 18px; border-bottom: 2px solid #4e94b8; padding-bottom: 8px; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        table thead { background-color: #343a40; color: white; }
        table th, table td { padding: 8px; border: 1px solid #ddd; text-align: center; }
        table tbody tr:nth-child(even) { background-color: #f8f9fa; }
        .fila-mng { background-color: #fff3cd !important; color: #856404; }
        .input-mini { width: 40px; text-align: center; padding: 4px; font-size: 14px; border: 1px solid #ccc; border-radius: 4px; }
        .select-lesion { padding: 4px; font-size: 12px; border-radius: 4px; border: 1px solid #ccc; width: 160px; }
        .btn-guardar { background-color: #28a745; color: white; border: none; padding: 15px 30px; font-size: 18px; font-weight: bold; border-radius: 8px; cursor: pointer; width: 100%; transition: background 0.3s; }
        .btn-guardar:hover { background-color: #218838; }
        .btn-cancelar { display: block; text-align: center; margin-top: 15px; color: #666; text-decoration: none; }
        .alerta-error { background-color: #f8d7da; color: #721c24; padding: 12px; border-radius: 6px; margin-bottom: 20px; font-weight: bold; text-align: center; }
    </style>
</head>
<body>
    <div class="contenedor">
        <h1>Acta de Partido Oficial (Temporada 3)</h1>
        <h3><?php echo htmlspecialchars($partido['liga_nombre']); ?> — Jornada <?php echo $partido['jornada']; ?></h3>

        <?php if (!empty($error)): ?>
            <div class="alerta-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="header-partido">
            <div class="equipo-titular"><?php echo htmlspecialchars($partido['local_nombre']); ?> (Local)</div>
            <div style="font-size: 24px; font-weight: bold; color: #ffc107;">VS</div>
            <div class="equipo-titular"><?php echo htmlspecialchars($partido['visitante_nombre']); ?> (Visitante)</div>
        </div>

        <form method="POST" action="acta_partido.php?id=<?php echo $partidoId; ?>">

            <?php 
            $equiposEncuentro = [
                ['tipo' => 'local', 'nombre' => $partido['local_nombre'], 'raza' => $partido['local_raza'], 'jugadores' => $jugadoresLocal],
                ['tipo' => 'visitante', 'nombre' => $partido['visitante_nombre'], 'raza' => $partido['visitante_raza'], 'jugadores' => $jugadoresVisitante]
            ];
            
            foreach ($equiposEncuentro as $bando): 
                $tipo = $bando['tipo'];
            ?>
            <div class="bloque-equipo">
                <div class="titulo-equipo">
                    <span><?php echo htmlspecialchars($bando['nombre']); ?> (<?php echo htmlspecialchars($bando['raza']); ?>)</span>
                    <span style="font-size: 14px; color: #dc3545; font-weight: bold;">¡Obligatorio marcar un MVP!</span>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Jugador</th>
                            <th>Posición</th>
                            <th>TD (+3)</th>
                            <th>Bajas (+2)</th>
                            <th>Pases (+1)</th>
                            <th>Inter. (+2)</th>
                            <th>MVP (+4)</th>
                            <th>Lesión Sufrida (Tirada Bajas)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bando['jugadores'] as $jug): 
                            $estaMng = !empty($jug['mng']);
                        ?>
                        <tr class="<?php echo $estaMng ? 'fila-mng' : ''; ?>">
                            <td><strong><?php echo $jug['numero']; ?></strong></td>
                            <td style="text-align: left; font-weight: bold;"><?php echo htmlspecialchars($jug['nombre']); ?></td>
                            <td><?php echo htmlspecialchars($jug['posicion']); ?></td>
                            
                            <?php if ($estaMng): ?>
                                <td colspan="6" style="font-weight: bold; color: #856404;">
                                    🚑 En enfermería este partido (MNG). Se recuperará automáticamente al cerrar el acta.
                                </td>
                            <?php else: ?>
                                <td><input type="number" min="0" max="10" value="0" name="jug_<?php echo $tipo; ?>[<?php echo $jug['id']; ?>][td]" class="input-mini"></td>
                                <td><input type="number" min="0" max="10" value="0" name="jug_<?php echo $tipo; ?>[<?php echo $jug['id']; ?>][bajas]" class="input-mini"></td>
                                <td><input type="number" min="0" max="10" value="0" name="jug_<?php echo $tipo; ?>[<?php echo $jug['id']; ?>][pases]" class="input-mini"></td>
                                <td><input type="number" min="0" max="10" value="0" name="jug_<?php echo $tipo; ?>[<?php echo $jug['id']; ?>][inter]" class="input-mini"></td>
                                <td><input type="radio" name="mvp_<?php echo $tipo; ?>" value="<?php echo $jug['id']; ?>" required></td>
                                <td>
                                    <select name="jug_<?php echo $tipo; ?>[<?php echo $jug['id']; ?>][lesion]" class="select-lesion">
                                        <option value="ninguna">Sin lesión (01-06 / Aturdido)</option>
                                        <option value="leve">Leve (07-09 Badly Hurt)</option>
                                        <option value="mng">Falta próx. partido (10-12 MNG)</option>
                                        <option value="ni">Lesión Persistente (13-14 NI+MNG)</option>
                                        <option value="menos_ma">-1 MA y MNG (15)</option>
                                        <option value="menos_pa">-1 PA y MNG (15)</option>
                                        <option value="menos_ag">-1 AG y MNG (15)</option>
                                        <option value="menos_av">-1 AV y MNG (15)</option>
                                        <option value="menos_st">-1 ST y MNG (16)</option>
                                        <option value="muerto">Muerto (15-16 DEAD)</option>
                                    </select>
                                </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endforeach; ?>

            <button type="submit" class="btn-guardar">Cerrar Acta y Aplicar Estadísticas Individuales</button>
            <a href="ver_liga.php?id=<?php echo $partido['liga_id']; ?>" class="btn-cancelar">Cancelar y volver a la liga</a>
        </form>
    </div>
</body>
</html>