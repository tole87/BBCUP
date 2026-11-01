<?php
session_start();
include('conexion.php');
include('funciones.php');

// 1. Validar que el usuario haya iniciado sesión
if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

$emailLogeado = $_SESSION['email'];

// Obtener el ID del usuario actual a partir de su email
$usuarios = obtenerUsuarios();
$usuarioId = null;
foreach ($usuarios as $u) {
    if ($u['email'] === $emailLogeado) {
        $usuarioId = $u['id']; 
        break;
    }
}

if (!$usuarioId) {
    die("Error: No se ha podido identificar al usuario logueado.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Iniciar transacción para asegurar que o se guarda todo o no se guarda nada
        $baseDatos->beginTransaction();

        // 2. Recoger los datos enviados desde editar_equipo.php
        $nombreEquipo = $_POST['nombre_equipo'] ?? '';
        $tipoEquipoId = $_POST['tipo_equipo_id'] ?? 1;
        
        // Obtenemos la información de la raza base seleccionada
        $razaBase = obtenerEquipoUnico($tipoEquipoId);
        $razaEquipo = $razaBase['nombre'] ?? 'Desconocida';

        $tesoreria = $_POST['tesoreria'] ?? 0;
        $valorEquipo = $_POST['valor_equipo'] ?? 0;
        $rerolls = $_POST['reroll'] ?? 0;
        $ayudantes = $_POST['asistente'] ?? 0;
        $animadoras = $_POST['animadoras'] ?? 0;
        $fansDedicados = $_POST['fans'] ?? 1;
        $apotecario = $_POST['medico'] ?? 0; // 0 o 1

        // 3. Insertar el equipo en la tabla 'equipos'
        $queryEquipo = "INSERT INTO equipos (usuario_id, nombre_equipo, raza, valor_equipo, tesoreria, rerolls, apotecario, animadoras, ayudantes_entrenador, fans_dedicados) 
                        VALUES (:usuario_id, :nombre_equipo, :raza, :valor_equipo, :tesoreria, :rerolls, :apotecario, :animadoras, :ayudantes, :fans)";
        
        $stmt = $baseDatos->prepare($queryEquipo);
        $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':nombre_equipo' => $nombreEquipo,
            ':raza' => $razaEquipo,
            ':valor_equipo' => $valorEquipo,
            ':tesoreria' => $tesoreria,
            ':rerolls' => $rerolls,
            ':apotecario' => $apotecario,
            ':animadoras' => $animadoras,
            ':ayudantes' => $ayudantes,
            ':fans' => $fansDedicados
        ]);

        // Obtener el ID del equipo recién insertado
        $equipoId = $baseDatos->lastInsertId();

        // 4. Recorrer las 16 filas de la plantilla de la tabla e insertar los jugadores
        for ($i = 1; $i <= 16; $i++) {
            $nombreJugador = $_POST["nombre_$i"] ?? '';
            $costeJugador = $_POST["valor_$i"] ?? 0;

            // Guardar el jugador solo si tiene nombre escrito o se le ha asignado algún coste
            if (!empty(trim($nombreJugador)) || $costeJugador > 0) {
                $queryJugador = "INSERT INTO jugadores (equipo_id, nombre, numero, posicion, coste, ma, st, ag, pa, av, habilidades, valor_actual) 
                                 VALUES (:equipo_id, :nombre, :numero, :posicion, :coste, :ma, :st, :ag, :pa, :av, :habilidades, :valor_actual)";
                
                $stmtJ = $baseDatos->prepare($queryJugador);
                $stmtJ->execute([
                    ':equipo_id' => $equipoId,
                    ':nombre' => !empty(trim($nombreJugador)) ? $nombreJugador : 'Sin nombre',
                    ':numero' => $_POST["id_$i"] ?? $i,
                    ':posicion' => $_POST["posicion_$i"] ?? 'Línea',
                    ':coste' => $costeJugador,
                    ':ma' => $_POST["ma_$i"] ?? 6,
                    ':st' => $_POST["st_$i"] ?? 3,
                    ':ag' => $_POST["ag_$i"] ?? 3,
                    ':pa' => $_POST["pa_$i"] ?? '-',
                    ':av' => $_POST["av_$i"] ?? 8,
                    ':habilidades' => $_POST["habilidades_$i"] ?? null,
                    ':valor_actual' => $costeJugador 
                ]);
            }
        }

        // Confirmar los cambios definitivamente en la base de datos
        $baseDatos->commit();

        // Redirigir al panel principal tras guardar con éxito
        header("Location: index.php?exito=equipo_guardado");
        exit();

    } catch (Exception $e) {
        // Si hay algún fallo, revertir cualquier cambio realizado
        $baseDatos->rollBack();
        die("Error crítico al guardar el equipo: " . $e->getMessage());
    }
} else {
    header("Location: crear_equipo.php");
    exit();
}
?>