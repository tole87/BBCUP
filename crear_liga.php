<?php
session_start();
include('conexion.php');
include('funciones.php');

// Validar sesión
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

// Consultar los equipos del usuario que tengan mínimo 11 jugadores para poder inscribirse
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
    $nombreLiga = trim($_POST['nombre_liga'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $maxEquipos = (int)($_POST['max_equipos'] ?? 8);
    $puntosVictoria = (int)($_POST['puntos_victoria'] ?? 3);
    $puntosEmpate = (int)($_POST['puntos_empate'] ?? 1);
    $puntosDerrota = (int)($_POST['puntos_derrota'] ?? 0);
    $equipoInscritoId = (int)($_POST['equipo_id'] ?? 0);

    if (empty($nombreLiga)) {
        $error = "Debes asignar un nombre a la liga.";
    } else {
        try {
            $baseDatos->beginTransaction();

            // Generar código de acceso alfanumérico aleatorio de 6 caracteres (ej: BB-X94K)
            $codigoAcceso = 'BB-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));

            // 1. Insertar la nueva liga
            $queryLiga = "INSERT INTO ligas (nombre, descripcion, creador_id, codigo_acceso, max_equipos, puntos_victoria, puntos_empate, puntos_derrota, estado) 
                          VALUES (:nombre, :descripcion, :creador_id, :codigo, :max_eq, :p_vic, :p_emp, :p_der, 'abierta')";
            $stmt = $baseDatos->prepare($queryLiga);
            $stmt->execute([
                ':nombre' => $nombreLiga,
                ':descripcion' => $descripcion,
                ':creador_id' => $usuarioId,
                ':codigo' => $codigoAcceso,
                ':max_eq' => $maxEquipos,
                ':p_vic' => $puntosVictoria,
                ':p_emp' => $puntosEmpate,
                ':p_der' => $puntosDerrota
            ]);

            $ligaId = $baseDatos->lastInsertId();

            // 2. Si el creador seleccionó un equipo propio, inscribirlo de inmediato
            if ($equipoInscritoId > 0) {
                $queryInscribir = "INSERT INTO liga_equipos (liga_id, equipo_id) VALUES (:liga_id, :equipo_id)";
                $stmtIns = $baseDatos->prepare($queryInscribir);
                $stmtIns->execute([
                    ':liga_id' => $ligaId,
                    ':equipo_id' => $equipoInscritoId
                ]);
            }

            $baseDatos->commit();

            // Redirigir al panel de ligas
            header("Location: ligas.php?exito=liga_creada");
            exit();

        } catch (Exception $e) {
            $baseDatos->rollBack();
            $error = "Error al crear la liga: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Liga - BB CUP</title>
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
            max-width: 600px;
            margin: 40px 0;
        }

        h1 {
            color: #4e94b8;
            text-align: center;
            margin-bottom: 25px;
        }

        .grupo-campo {
            margin-bottom: 18px;
            text-align: left;
        }

        .grupo-campo label {
            display: block;
            font-weight: bold;
            margin-bottom: 6px;
            font-size: 15px;
        }

        .grupo-campo input,
        .grupo-campo select,
        .grupo-campo textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 15px;
            box-sizing: border-box;
        }

        .fila-puntos {
            display: flex;
            gap: 15px;
        }

        .fila-puntos div {
            flex: 1;
        }

        .btn-crear {
            background-color: #6bb5d6;
            color: white;
            border: none;
            padding: 14px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            border-radius: 8px;
            width: 100%;
            margin-top: 15px;
            transition: background 0.3s;
        }

        .btn-crear:hover {
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
        <h1>Fundar Nueva Liga</h1>

        <?php if (!empty($error)): ?>
            <div class="alerta-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="crear_liga.php">
            <div class="grupo-campo">
                <label for="nombre_liga">Nombre de la Liga *</label>
                <input type="text" id="nombre_liga" name="nombre_liga" placeholder="Ej: Blood Bowl Open Cantabria" required>
            </div>

            <div class="grupo-campo">
                <label for="descripcion">Descripción / Normas Locales</label>
                <textarea id="descripcion" name="descripcion" rows="3" placeholder="Detalles de rondas, plazos por jornada..."></textarea>
            </div>

            <div class="grupo-campo">
                <label for="max_equipos">Número Máximo de Equipos</label>
            <select id="max_equipos" name="max_equipos">
                <?php for ($n = 3; $n <= 16; $n++): ?>
                    <option value="<?php echo $n; ?>" <?php echo $n === 8 ? 'selected' : ''; ?>>
                        <?php echo $n; ?> Equipos <?php echo ($n % 2 !== 0) ? '(1 descanso por jornada)' : ''; ?>
                    </option>
                <?php endfor; ?>
            </select>
            </div>

            <!-- Sistema de puntuación oficial BB Temporada 3 -->
            <div class="grupo-campo">
                <label>Sistema de Puntos (Temporada 3)</label>
                <div class="fila-puntos">
                    <div>
                        <small>Victoria</small>
                        <input type="number" name="puntos_victoria" value="3" min="1" max="10">
                    </div>
                    <div>
                        <small>Empate</small>
                        <input type="number" name="puntos_empate" value="1" min="0" max="5">
                    </div>
                    <div>
                        <small>Derrota</small>
                        <input type="number" name="puntos_derrota" value="0" min="0" max="2">
                    </div>
                </div>
            </div>

            <!-- Inscripción inicial del creador -->
            <div class="grupo-campo">
                <label for="equipo_id">Inscribir uno de mis equipos (Opcional)</label>
                <select id="equipo_id" name="equipo_id">
                    <option value="0">-- No inscribir ningún equipo ahora --</option>
                    <?php foreach ($misEquiposValidos as $eq): ?>
                        <option value="<?php echo $eq['id']; ?>">
                            <?php echo htmlspecialchars($eq['nombre_equipo']) . " (" . htmlspecialchars($eq['raza']) . " - TV " . number_format($eq['valor_equipo'], 0, ',', '.') . " GP)"; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($misEquiposValidos)): ?>
                    <small style="color: #666; display: block; margin-top: 4px;">Solo aparecen equipos que cumplan la regla oficial de al menos 11 jugadores.</small>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn-crear">Crear Liga y Generar Código</button>
            <a href="ligas.php" class="btn-cancelar">Cancelar y Volver</a>
        </form>
    </div>
</body>
</html>