<?php
session_start();
include('conexion.php');
include('funciones.php');

if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

$equipoId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($equipoId <= 0) {
    header("Location: mis_equipos.php");
    exit();
}

$emailLogeado = $_SESSION['email'];
$mensajeExito = '';
$mensajeError = '';

try {
    // Recalcular siempre el TV al entrar para asegurar coherencia
    recalcularTV($equipoId);

    $queryEquipo = "SELECT e.*, u.email AS propietario_email, r.coste_reroll 
                    FROM equipos e 
                    JOIN usuarios u ON e.usuario_id = u.id
                    LEFT JOIN razas r ON e.raza = r.nombre
                    WHERE e.id = :id";
    $stmtEq = $baseDatos->prepare($queryEquipo);
    $stmtEq->execute([':id' => $equipoId]);
    $equipo = $stmtEq->fetch(PDO::FETCH_ASSOC);

    if (!$equipo) {
        die("Equipo no encontrado.");
    }

    $esPropietario = ($equipo['propietario_email'] === $emailLogeado);
    $costeRerollBase = !empty($equipo['coste_reroll']) ? (int)$equipo['coste_reroll'] : 50000;
    // En liga empezada, comprar Rerolls cuesta el doble según reglamento BB2020
    $costeRerollCompra = $costeRerollBase * 2;

    // Procesar acciones POST (Despedir jugador, Comprar Staff/Rerolls, Añadir Journeyman)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $esPropietario) {
        $accion = $_POST['accion'] ?? '';

        if ($accion === 'despedir_jugador') {
            $jugId = (int)($_POST['jugador_id'] ?? 0);
            $stDel = $baseDatos->prepare("DELETE FROM jugadores WHERE id = :jid AND equipo_id = :eid");
            $stDel->execute([':jid' => $jugId, ':eid' => $equipoId]);
            recalcularTV($equipoId);
            header("Location: ver_equipo.php?id={$equipoId}&msg=jugador_despedido");
            exit();
        }

        if ($accion === 'comprar_bien') {
            $tipoBien = $_POST['tipo_bien'] ?? '';
            $costeBien = 0;
            $campoSql = '';

            if ($tipoBien === 'reroll' && $equipo['rerolls'] < 8) {
                $costeBien = $costeRerollCompra;
                $campoSql = 'rerolls = rerolls + 1';
            } elseif ($tipoBien === 'apotecario' && empty($equipo['apotecario'])) {
                $costeBien = 50000;
                $campoSql = 'apotecario = 1';
            } elseif ($tipoBien === 'animadora' && $equipo['animadoras'] < 12) {
                $costeBien = 10000;
                $campoSql = 'animadoras = animadoras + 1';
            } elseif ($tipoBien === 'ayudante' && $equipo['ayudantes_entrenador'] < 6) {
                $costeBien = 10000;
                $campoSql = 'ayudantes_entrenador = ayudantes_entrenador + 1';
            }

            if ($costeBien > 0 && $equipo['tesoreria'] >= $costeBien && !empty($campoSql)) {
                $stUp = $baseDatos->prepare("UPDATE equipos SET tesoreria = tesoreria - :coste, {$campoSql} WHERE id = :id");
                $stUp->execute([':coste' => $costeBien, ':id' => $equipoId]);
                recalcularTV($equipoId);
                header("Location: ver_equipo.php?id={$equipoId}&msg=bien_comprado");
                exit();
            } else {
                $mensajeError = "No tienes tesorería suficiente o has alcanzado el límite permitido.";
            }
        }

        if ($accion === 'anadir_journeyman') {
            // Buscar perfil de Línea de su raza (el que tiene max_cantidad >= 12)
            $stLinea = $baseDatos->prepare("SELECT pr.* FROM posiciones_raza pr JOIN razas r ON pr.raza_id = r.id WHERE r.nombre = :raza AND pr.max_cantidad >= 12 ORDER BY pr.coste ASC LIMIT 1");
            $stLinea->execute([':raza' => $equipo['raza']]);
            $lineaBase = $stLinea->fetch(PDO::FETCH_ASSOC);

            if ($lineaBase) {
                $habsBase = ($lineaBase['habilidades'] && $lineaBase['habilidades'] !== 'Ninguna') 
                            ? $lineaBase['habilidades'] . ', Solitario (4+)' 
                            : 'Solitario (4+)';
                $stInsJ = $baseDatos->prepare("INSERT INTO jugadores (equipo_id, numero, nombre, posicion, coste, ma, st, ag, pa, av, habilidades, valor_actual, spp, spp_gastados, avances) VALUES (:eid, 98, :nom, 'Sustituto (Journeyman)', :coste, :ma, :st, :ag, :pa, :av, :habs, :coste, 0, 0, 0)");
                $stInsJ->execute([
                    ':eid' => $equipoId,
                    ':nom' => 'Sustituto ' . $lineaBase['nombre_posicion'],
                    ':coste' => $lineaBase['coste'],
                    ':ma' => $lineaBase['ma'],
                    ':st' => $lineaBase['st'],
                    ':ag' => $lineaBase['ag'],
                    ':pa' => $lineaBase['pa'],
                    ':av' => $lineaBase['av'],
                    ':habs' => $habsBase
                ]);
                recalcularTV($equipoId);
                header("Location: ver_equipo.php?id={$equipoId}&msg=journeyman_anadido");
                exit();
            }
        }
    }

    // Recargar datos del equipo tras posibles cambios
    $stmtEq->execute([':id' => $equipoId]);
    $equipo = $stmtEq->fetch(PDO::FETCH_ASSOC);

    $queryJugadores = "SELECT * FROM jugadores WHERE equipo_id = :id ORDER BY muerto ASC, numero ASC";
    $stmtJug = $baseDatos->prepare($queryJugadores);
    $stmtJug->execute([':id' => $equipoId]);
    $jugadores = $stmtJug->fetchAll(PDO::FETCH_ASSOC);

    // Contar cuántos jugadores están disponibles para el próximo partido (ni muertos ni con MNG)
    $disponiblesProximoPartido = 0;
    foreach ($jugadores as $j) {
        if (empty($j['muerto']) && empty($j['mng'])) {
            $disponiblesProximoPartido++;
        }
    }

} catch (PDOException $e) {
    die("Error de base de datos: " . $e->getMessage());
}

function calcStatNum($base, $lesiones, $tipo) {
    if ($lesiones > 0) {
        $actual = max(1, (int)$base - $lesiones);
        return "<span class='stat-lesion' title='-{$lesiones} {$tipo}'>{$actual}</span>";
    }
    return htmlspecialchars($base);
}

function calcStatObj($base, $lesiones, $tipo) {
    if ($base === '-' || empty($base)) return '-';
    if ($lesiones > 0) {
        $actual = (int)$base + $lesiones;
        return "<span class='stat-lesion' title='-{$lesiones} {$tipo}'>{$actual}+</span>";
    }
    return htmlspecialchars($base);
}

function calcStatAV($base, $lesiones) {
    if ($base === '-' || empty($base)) return '-';
    if ($lesiones > 0) {
        $actual = max(3, (int)$base - $lesiones);
        return "<span class='stat-lesion' title='-{$lesiones} AV'>{$actual}+</span>";
    }
    return htmlspecialchars($base);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($equipo['nombre_equipo']); ?> - Roster</title>
    <style>
        body { margin: 0; font-family: Arial, sans-serif; background-image: url('imagenes/fondo.webp'); background-size: cover; background-position: center; background-repeat: no-repeat; min-height: 100vh; display: flex; justify-content: center; align-items: flex-start; padding: 40px 0; color: #333; }
        .contenedor { background: rgba(255, 255, 255, 0.95); border-radius: 10px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2); padding: 30px; width: 95%; max-width: 1280px; }
        .header-equipo { display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 3px solid #4e94b8; padding-bottom: 15px; margin-bottom: 25px; }
        .header-equipo h1 { color: #4e94b8; margin: 0 0 5px 0; font-size: 32px; }
        .header-equipo h3 { margin: 0; color: #555; font-size: 18px; }
        .caja-stats { display: flex; gap: 15px; background: #fdfdfd; padding: 15px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 25px; }
        .stat-item { flex: 1; text-align: center; border-right: 1px solid #ddd; padding: 0 5px; }
        .stat-item:last-child { border-right: none; }
        .stat-item strong { display: block; font-size: 12px; color: #777; text-transform: uppercase; }
        .stat-item span { font-size: 20px; font-weight: bold; color: #212529; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        table thead { background-color: #212529; color: #fff; }
        table th, table td { padding: 10px; border: 1px solid #ddd; text-align: center; }
        table tbody tr:nth-child(even) { background-color: #f9f9f9; }
        table tbody tr:hover { background-color: #e6f7ff; }
        .fila-muerto { background-color: #e9ecef !important; color: #6c757d; text-decoration: line-through; }
        .stat-lesion { color: #d9534f; font-weight: bold; text-decoration: none !important; display: inline-block; }
        
        .badge { padding: 4px 6px; border-radius: 4px; font-size: 11px; font-weight: bold; display: inline-block; margin: 2px; text-decoration: none !important; }
        .badge-mng { background-color: #ffc107; color: #000; }
        .badge-ni { background-color: #fd7e14; color: #fff; }
        .badge-dead { background-color: #dc3545; color: #fff; }
        .badge-spp { background-color: #17a2b8; color: #fff; font-size: 13px; }

        .btn-volver { background-color: #6bb5d6; color: white; border: none; padding: 12px 25px; font-size: 16px; cursor: pointer; border-radius: 6px; text-decoration: none; display: inline-block; margin-top: 25px; transition: background 0.3s; }
        .btn-volver:hover { background-color: #4e94b8; }
        .btn-nivel { background-color: #28a745; color: white; padding: 4px 8px; border-radius: 4px; text-decoration: none; font-size: 11px; font-weight: bold; display: inline-block; margin-top: 4px; }
        .btn-nivel:hover { background-color: #218838; }
        .btn-mini-compra { background-color: #4e94b8; color: white; border: none; padding: 4px 8px; border-radius: 4px; font-size: 10px; font-weight: bold; cursor: pointer; margin-top: 6px; }
        .btn-mini-compra:hover { background-color: #3b7696; }
        .btn-despedir { background-color: #dc3545; color: white; border: none; padding: 4px 8px; border-radius: 4px; font-size: 11px; cursor: pointer; font-weight: bold; }
        .btn-despedir:hover { background-color: #b02a37; }

        .habilidad-etiqueta { position: relative; display: inline-block; cursor: help; background: #eef2f5; border: 1px solid #c9d6df; padding: 3px 6px; border-radius: 4px; margin: 2px; font-size: 12px; font-weight: 500; }
        .habilidad-etiqueta .tooltip-texto { visibility: hidden; width: 220px; background-color: #212529; color: #fff; text-align: left; border-radius: 6px; padding: 10px; position: absolute; z-index: 10; bottom: 125%; left: 50%; margin-left: -110px; opacity: 0; transition: opacity 0.3s; font-weight: normal; font-size: 12px; line-height: 1.4; box-shadow: 0px 4px 8px rgba(0,0,0,0.3); pointer-events: none; }
        .habilidad-etiqueta .tooltip-texto::after { content: ""; position: absolute; top: 100%; left: 50%; margin-left: -5px; border-width: 5px; border-style: solid; border-color: #212529 transparent transparent transparent; }
        .habilidad-etiqueta:hover { background: #4e94b8; color: white; border-color: #4e94b8; }
        .habilidad-etiqueta:hover .tooltip-texto { visibility: visible; opacity: 1; }
        .alerta-journeyman { background: #fff3cd; border: 1px solid #ffeeba; color: #856404; padding: 12px 18px; border-radius: 8px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
    </style>
</head>
<body>
    <div class="contenedor">
        
        <div class="header-equipo">
            <div>
                <h1><?php echo htmlspecialchars($equipo['nombre_equipo']); ?></h1>
                <h3>Raza: <?php echo htmlspecialchars($equipo['raza']); ?> | Jugadores Disponibles Próx. Partido: <?php echo $disponiblesProximoPartido; ?></h3>
            </div>
            <div style="text-align: right;">
                <span style="font-size: 13px; color: #666;">Valor de Equipo Actual (CTV - Sin bajas MNG)</span>
                <div style="font-size: 26px; font-weight: bold; color: #28a745;">
                    <?php echo number_format($equipo['valor_equipo'], 0, ',', '.'); ?> GP
                </div>
            </div>
        </div>

        <?php if (!empty($mensajeError)): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 10px; border-radius: 6px; margin-bottom: 15px; text-align: center; font-weight: bold;">
                <?php echo htmlspecialchars($mensajeError); ?>
            </div>
        <?php endif; ?>

        <?php if ($esPropietario && $disponiblesProximoPartido < 11): ?>
            <div class="alerta-journeyman">
                <div>
                    <strong>⚠️ Regla de Sustitutos (Journeymen):</strong> Tienes solo <strong><?php echo $disponiblesProximoPartido; ?></strong> jugadores sanos para el próximo partido (mínimo 11). Puedes incorporar un Línea Sustituto gratuito con <em>Solitario (4+)</em>.
                </div>
                <form method="POST" action="ver_equipo.php?id=<?php echo $equipoId; ?>" style="margin: 0;">
                    <input type="hidden" name="accion" value="anadir_journeyman">
                    <button type="submit" class="btn-nivel" style="border: none; cursor: pointer; padding: 8px 14px; font-size: 13px;">+ Añadir Sustituto Gratis</button>
                </form>
            </div>
        <?php endif; ?>

        <div class="caja-stats">
            <div class="stat-item">
                <strong>Tesorería</strong>
                <span><?php echo number_format($equipo['tesoreria'], 0, ',', '.'); ?> GP</span>
                <?php if ($esPropietario): ?>
                    <a href="fichar_jugador.php?id=<?php echo $equipoId; ?>" style="display: block; margin-top: 6px; background-color: #28a745; color: white; padding: 4px 0; border-radius: 3px; font-size: 10px; font-weight: bold; text-decoration: none;">+ FICHAR JUGADOR</a>
                    <a href="fichar_estrella.php?id=<?php echo $equipoId; ?>" style="display: block; margin-top: 4px; background-color: #d9534f; color: white; padding: 4px 0; border-radius: 3px; font-size: 10px; font-weight: bold; text-decoration: none;">⭐ JUGADOR ESTRELLA</a>
                <?php endif; ?>
            </div>
            <div class="stat-item">
                <strong>Segundas Oportunidades</strong>
                <span><?php echo $equipo['rerolls']; ?></span>
                <?php if ($esPropietario && $equipo['tesoreria'] >= $costeRerollCompra): ?>
                    <form method="POST" action="ver_equipo.php?id=<?php echo $equipoId; ?>" onsubmit="return confirm('¿Comprar 1 Reroll adicional por <?php echo number_format($costeRerollCompra, 0, ',', '.'); ?> GP (doble coste en liga)?');">
                        <input type="hidden" name="accion" value="comprar_bien">
                        <input type="hidden" name="tipo_bien" value="reroll">
                        <button type="submit" class="btn-mini-compra">+1 (<?php echo ($costeRerollCompra/1000); ?>k)</button>
                    </form>
                <?php endif; ?>
            </div>
            <div class="stat-item">
                <strong>Apotecario</strong>
                <span><?php echo $equipo['apotecario'] ? 'Sí' : 'No'; ?></span>
                <?php if ($esPropietario && empty($equipo['apotecario']) && $equipo['tesoreria'] >= 50000): ?>
                    <form method="POST" action="ver_equipo.php?id=<?php echo $equipoId; ?>" onsubmit="return confirm('¿Contratar Apotecario por 50.000 GP?');">
                        <input type="hidden" name="accion" value="comprar_bien">
                        <input type="hidden" name="tipo_bien" value="apotecario">
                        <button type="submit" class="btn-mini-compra">Contratar (50k)</button>
                    </form>
                <?php endif; ?>
            </div>
            <div class="stat-item">
                <strong>Animadoras</strong>
                <span><?php echo $equipo['animadoras']; ?></span>
                <?php if ($esPropietario && $equipo['tesoreria'] >= 10000): ?>
                    <form method="POST" action="ver_equipo.php?id=<?php echo $equipoId; ?>">
                        <input type="hidden" name="accion" value="comprar_bien">
                        <input type="hidden" name="tipo_bien" value="animadora">
                        <button type="submit" class="btn-mini-compra">+1 (10k)</button>
                    </form>
                <?php endif; ?>
            </div>
            <div class="stat-item">
                <strong>Ayudantes</strong>
                <span><?php echo $equipo['ayudantes_entrenador']; ?></span>
                <?php if ($esPropietario && $equipo['tesoreria'] >= 10000): ?>
                    <form method="POST" action="ver_equipo.php?id=<?php echo $equipoId; ?>">
                        <input type="hidden" name="accion" value="comprar_bien">
                        <input type="hidden" name="tipo_bien" value="ayudante">
                        <button type="submit" class="btn-mini-compra">+1 (10k)</button>
                    </form>
                <?php endif; ?>
            </div>
            <div class="stat-item">
                <strong>Fans Dedicados</strong>
                <span><?php echo $equipo['fans_dedicados']; ?></span>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th width="3%">#</th>
                    <th width="14%" style="text-align: left;">Nombre</th>
                    <th width="14%">Posición</th>
                    <th width="4%">MA</th>
                    <th width="4%">ST</th>
                    <th width="4%">AG</th>
                    <th width="4%">PA</th>
                    <th width="4%">AV</th>
                    <th width="21%" style="text-align: left;">Habilidades y Mutaciones</th>
                    <th width="10%">SPP</th>
                    <th width="8%">Estado</th>
                    <th width="5%">Valor</th>
                    <?php if ($esPropietario): ?><th width="5%">Gestión</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($jugadores)): ?>
                    <tr><td colspan="13" style="padding: 20px;">No hay jugadores en la plantilla.</td></tr>
                <?php else: ?>
                    <?php foreach ($jugadores as $j): 
                        $claseFila = $j['muerto'] ? 'fila-muerto' : '';
                    ?>
                    <tr class="<?php echo $claseFila; ?>">
                        <td><strong><?php echo $j['numero']; ?></strong></td>
                        <td style="text-align: left; font-weight: bold;"><?php echo htmlspecialchars($j['nombre']); ?></td>
                        <td><?php echo htmlspecialchars($j['posicion']); ?></td>
                        
                        <td><?php echo calcStatNum($j['ma'], $j['lesiones_ma'] ?? 0, 'MA'); ?></td>
                        <td><?php echo calcStatNum($j['st'], $j['lesiones_st'] ?? 0, 'ST'); ?></td>
                        <td><?php echo calcStatObj($j['ag'], $j['lesiones_ag'] ?? 0, 'AG'); ?></td>
                        <td><?php echo calcStatObj($j['pa'], $j['lesiones_pa'] ?? 0, 'PA'); ?></td>
                        <td><?php echo calcStatAV($j['av'], $j['lesiones_av'] ?? 0); ?></td>

                        <td style="text-align: left;">
                            <?php 
                            if (empty($j['habilidades']) || $j['habilidades'] === '-' || $j['habilidades'] === 'Ninguna') {
                                echo '<span style="color: #999;">Ninguna</span>';
                            } else {
                                $arrayHab = array_map('trim', explode(',', $j['habilidades']));
                                foreach ($arrayHab as $hab) {
                                    if (empty($hab)) continue;
                                    $descripcion = obtenerInfoHabilidad($hab);
                                    echo '<div class="habilidad-etiqueta">';
                                    echo htmlspecialchars($hab);
                                    echo '<span class="tooltip-texto">' . htmlspecialchars($descripcion) . '</span>';
                                    echo '</div>';
                                }
                            }
                            ?>
                        </td>

                        <td>
                            <?php if (!$j['muerto'] && $j['posicion'] !== 'Jugador Estrella'): 
                                $sppGastados = $j['spp_gastados'] ?? 0;
                                $sppDisponibles = $j['spp'] - $sppGastados;
                            ?>
                                <span class="badge badge-spp" title="Totales: <?php echo $j['spp']; ?> / Gastados: <?php echo $sppGastados; ?>">
                                    <?php echo $sppDisponibles; ?> SPP libres
                                </span>
                                <?php if ($esPropietario && $sppDisponibles >= 3): ?>
                                    <a href="subir_nivel.php?id=<?php echo $j['id']; ?>" class="btn-nivel">↑ Subir Nivel</a>
                                <?php endif; ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>

                        <td>
                            <?php if ($j['muerto']): ?>
                                <span class="badge badge-dead">MUERTO</span>
                            <?php else: ?>
                                <?php if (!empty($j['mng'])): ?>
                                    <span class="badge badge-mng" title="Miss Next Game (No suma al CTV este partido)">MNG</span>
                                <?php endif; ?>
                                <?php if (!empty($j['ni']) && $j['ni'] > 0): ?>
                                    <span class="badge badge-ni" title="Lesión Persistente">NI x<?php echo $j['ni']; ?></span>
                                <?php endif; ?>
                                <?php if (empty($j['mng']) && empty($j['ni'])): ?>
                                    <span style="color: #28a745; font-size: 12px; font-weight: bold;">Sano</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>

                        <td><?php echo number_format($j['valor_actual'], 0, ',', '.'); ?></td>

                        <?php if ($esPropietario): ?>
                        <td>
                            <form method="POST" action="ver_equipo.php?id=<?php echo $equipoId; ?>" onsubmit="return confirm('¿Seguro que quieres despedir/eliminar a <?php echo addslashes($j['nombre']); ?> de la plantilla?');">
                                <input type="hidden" name="accion" value="despedir_jugador">
                                <input type="hidden" name="jugador_id" value="<?php echo $j['id']; ?>">
                                <button type="submit" class="btn-despedir" title="Despedir jugador">✕</button>
                            </form>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div style="text-align: center;">
            <a href="mis_equipos.php" class="btn-volver">← Volver a Mis Equipos</a>
        </div>
    </div>
</body>
</html>
