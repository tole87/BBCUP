<?php
session_start();
include('conexion.php');
include('funciones.php');

if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

$emailLogeado = $_SESSION['email'];
$ligaId = (int)($_POST['liga_id'] ?? 0);

if ($ligaId <= 0) {
    header("Location: ligas.php");
    exit();
}

// Obtener ID del usuario actual
$usuarios = obtenerUsuarios();
$usuarioId = null;
foreach ($usuarios as $u) {
    if ($u['email'] === $emailLogeado) {
        $usuarioId = $u['id'];
        break;
    }
}

try {
    // 1. Verificar que la liga exista, pertenezca al usuario y esté abierta
    $queryLiga = "SELECT * FROM ligas WHERE id = :id AND creador_id = :creador_id";
    $stmtL = $baseDatos->prepare($queryLiga);
    $stmtL->execute([':id' => $ligaId, ':creador_id' => $usuarioId]);
    $liga = $stmtL->fetch(PDO::FETCH_ASSOC);

    if (!$liga) {
        die("Acción no autorizada o la liga no existe.");
    }

    if ($liga['estado'] !== 'abierta') {
        die("La liga ya ha comenzado o está finalizada.");
    }

    // 2. Obtener los IDs de los equipos inscritos
    $queryEq = "SELECT equipo_id FROM liga_equipos WHERE liga_id = :liga_id";
    $stmtE = $baseDatos->prepare($queryEq);
    $stmtE->execute([':liga_id' => $ligaId]);
    $equipos = $stmtE->fetchAll(PDO::FETCH_COLUMN);

    $totalEquipos = count($equipos);
    if ($totalEquipos < 2) {
        die("Se necesitan al menos 2 equipos para iniciar la competición.");
    }

    // Si el número de equipos es impar, añadimos null como descanso (Bye)
    if ($totalEquipos % 2 !== 0) {
        $equipos[] = null;
        $totalEquipos++;
    }

    // Mezclar aleatoriamente el orden inicial para sorteo de calendario
    shuffle($equipos);

    $baseDatos->beginTransaction();

    // 3. Algoritmo Round-Robin (Rotación estándar con pivote fijo en la posición 0)
    $totalJornadas = $totalEquipos - 1;
    $partidosPorJornada = $totalEquipos / 2;

    $queryInsertPartido = "INSERT INTO partidos (liga_id, jornada, equipo_local_id, equipo_visitante_id) 
                           VALUES (:liga_id, :jornada, :local, :visitante)";
    $stmtP = $baseDatos->prepare($queryInsertPartido);

    for ($jornada = 1; $jornada <= $totalJornadas; $jornada++) {
        for ($p = 0; $p < $partidosPorJornada; $p++) {
            $local = $equipos[$p];
            $visitante = $equipos[$totalEquipos - 1 - $p];

            // Si alguno es null (descanso), aseguramos que el equipo real figure en 'local' y null en 'visitante'
            if ($local === null && $visitante !== null) {
                $local = $visitante;
                $visitante = null;
            }

            if ($local !== null) {
                $stmtP->execute([
                    ':liga_id' => $ligaId,
                    ':jornada' => $jornada,
                    ':local' => $local,
                    ':visitante' => $visitante
                ]);
            }
        }

        // Rotación de los elementos manteniendo fijo el índice 0
        $ultimo = array_pop($equipos);
        array_splice($equipos, 1, 0, [$ultimo]);
    }

    // 4. Actualizar el estado de la liga a 'en_curso'
    $queryUpdateLiga = "UPDATE ligas SET estado = 'en_curso' WHERE id = :id";
    $stmtU = $baseDatos->prepare($queryUpdateLiga);
    $stmtU->execute([':id' => $ligaId]);

    $baseDatos->commit();

    header("Location: ver_liga.php?id=" . $ligaId . "&exito=liga_iniciada");
    exit();

} catch (Exception $e) {
    if ($baseDatos->inTransaction()) {
        $baseDatos->rollBack();
    }
    die("Error al generar el calendario de la liga: " . $e->getMessage());
}
?>