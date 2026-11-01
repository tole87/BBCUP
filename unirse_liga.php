<?php
session_start();
include('conexion.php');
include('funciones.php');

if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

$emailLogeado = $_SESSION['email'];

// Obtener ID del usuario logueado
$usuarios = obtenerUsuarios();
$usuarioId = null;
foreach ($usuarios as $u) {
    if ($u['email'] === $emailLogeado) {
        $usuarioId = $u['id'];
        break;
    }
}

// Consultar los equipos del usuario que cumplan el mínimo oficial de 11 jugadores
try {
    $queryEquipos = "SELECT e.id, e.nombre_equipo, e.raza, e.valor_equipo, COUNT(j.id) AS total_jugadores 
                     FROM equipos e 
                     LEFT JOIN jugadores j ON e.id = j.equipo_id 
                     WHERE e.usuario_id = :uid 
                     GROUP BY e.id 
                     HAVING total_jugadores >= 11";
    $stmtEq = $baseDatos->prepare($queryEquipos);
    $stmtEq->execute([':uid' => $usuarioId]);
    $misEquiposValidos = $stmtEq->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $misEquiposValidos = [];
}

$error = '';
$exito = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigoAcceso = strtoupper(trim($_POST['codigo_acceso'] ?? ''));
    $equipoId = (int)($_POST['equipo_id'] ?? 0);

    if (empty($codigoAcceso)) {
        $error = "Debes ingresar el código de acceso de la liga.";
    } elseif ($equipoId <= 0) {
        $error = "Debes seleccionar un equipo válido para inscribirte.";
    } else {
        try {
            // 1. Buscar la liga por su código
            $queryLiga = "SELECT * FROM ligas WHERE codigo_acceso = :codigo";
            $stmtL = $baseDatos->prepare($queryLiga);
            $stmtL->execute([':codigo' => $codigoAcceso]);
            $liga = $stmtL->fetch(PDO::FETCH_ASSOC);

            if (!$liga) {
                $error = "No se ha encontrado ninguna liga con el código introducido.";
            } elseif ($liga['estado'] !== 'abierta') {
                $error = "No puedes unirte: La liga se encuentra en estado '" . htmlspecialchars($liga['estado']) . "'.";
            } else {
                $ligaId = (int)$liga['id'];

                // 2. Comprobar plazas disponibles
                $queryPlazas = "SELECT COUNT(*) FROM liga_equipos WHERE liga_id = :liga_id";
                $stmtP = $baseDatos->prepare($queryPlazas);
                $stmtP->execute([':liga_id' => $ligaId]);
                $equiposInscritos = (int)$stmtP->fetchColumn();

                if ($equiposInscritos >= (int)$liga['max_equipos']) {
                    $error = "La liga ya ha completado su número máximo de plazas (" . $liga['max_equipos'] . ").";
                } else {
                    // 3. Comprobar que el equipo no esté ya registrado en esta liga
                    $queryDup = "SELECT COUNT(*) FROM liga_equipos WHERE liga_id = :liga_id AND equipo_id = :equipo_id";
                    $stmtDup = $baseDatos->prepare($queryDup);
                    $stmtDup->execute([':liga_id' => $ligaId, ':equipo_id' => $equipoId]);
                    if ($stmtDup->fetchColumn() > 0) {
                        $error = "Este equipo ya está inscrito en la liga seleccionada.";
                    } else {
                        // 4. Inscribir el equipo en la liga
                        $queryInsert = "INSERT INTO liga_equipos (liga_id, equipo_id) VALUES (:liga_id, :equipo_id)";
                        $stmtIns = $baseDatos->prepare($queryInsert);
                        $stmtIns->execute([':liga_id' => $ligaId, ':equipo_id' => $equipoId]);

                        header("Location: ver_liga.php?id=" . $ligaId . "&exito=unido");
                        exit();
                    }
                }
            }
        } catch (PDOException $e) {
            $error = "Error al unirse a la liga: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unirse a una Liga - BB CUP</title>
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
            max-width: 550px;
            margin: 40px 0;
        }

        h1 {
            color: #4e94b8;
            text-align: center;
            margin-bottom: 25px;
        }

        .grupo-campo {
            margin-bottom: 20px;
            text-align: left;
        }

        .grupo-campo label {
            display: block;
            font-weight: bold;
            margin-bottom: 8px;
            font-size: 15px;
        }

        .grupo-campo input,
        .grupo-campo select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 15px;
            box-sizing: border-box;
        }

        .input-codigo {
            text-transform: uppercase;
            letter-spacing: 2px;
            font-family: monospace;
            font-size: 18px !important;
            text-align: center;
            font-weight: bold;
        }

        .btn-unirse {
            background-color: #6bb5d6;
            color: white;
            border: none;
            padding: 14px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            border-radius: 8px;
            width: 100%;
            margin-top: 10px;
            transition: background 0.3s;
        }

        .btn-unirse:hover {
            background-color: #4e94b8;
        }

        .btn-cancelar {
            display: block;
            text-align: center;
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
            text-align: center;
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
        <h1>Unirse a una Liga</h1>

        <?php if (!empty($error)): ?>
            <div class="alerta-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="unirse_liga.php">
            <div class="grupo-campo">
                <label for="codigo_acceso">Código de Invitación</label>
                <input type="text" id="codigo_acceso" name="codigo_acceso" class="input-codigo" placeholder="Ej: BB-9A2F" maxlength="15" required>
            </div>

            <div class="grupo-campo">
                <label for="equipo_id">Selecciona tu Equipo</label>
                <select id="equipo_id" name="equipo_id" required>
                    <option value="">-- Elige un equipo apto --</option>
                    <?php foreach ($misEquiposValidos as $eq): ?>
                        <option value="<?php echo $eq['id']; ?>">
                            <?php echo htmlspecialchars($eq['nombre_equipo']) . " (" . htmlspecialchars($eq['raza']) . " - TV " . number_format($eq['valor_equipo'], 0, ',', '.') . " GP)"; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($misEquiposValidos)): ?>
                    <small style="color: #c00; display: block; margin-top: 6px;">
                        No tienes ningún equipo con al menos 11 jugadores registrados. Debes completar tu plantilla antes de inscribirte.
                    </small>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn-unirse" <?php echo empty($misEquiposValidos) ? 'disabled style="background-color: #aaa; cursor: not-allowed;"' : ''; ?>>
                Inscribir Equipo
            </button>
            <a href="ligas.php" class="btn-cancelar">Cancelar y Volver</a>
        </form>
    </div>
</body>
</html>