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

// Consultar ligas donde el usuario tiene al menos un equipo inscrito o es creador
try {
    $query = "SELECT DISTINCT l.* 
              FROM ligas l
              LEFT JOIN liga_equipos le ON l.id = le.liga_id
              LEFT JOIN equipos e ON le.equipo_id = e.id
              WHERE l.creador_id = :uid_creador OR e.usuario_id = :uid_jugador";
    $stmt = $baseDatos->prepare($query);
    $stmt->execute([
        ':uid_creador' => $usuarioId,
        ':uid_jugador' => $usuarioId
    ]);
    $misLigas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error al cargar las ligas: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Ligas - BB CUP</title>
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
            margin-bottom: 25px;
        }

        .seccion-botones {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
        }

        .btn-accion-liga {
            background-color: #6bb5d6;
            color: white;
            border: none;
            padding: 15px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            border-radius: 8px;
            flex: 1;
            text-decoration: none;
            display: inline-block;
            transition: background 0.3s;
        }

        .btn-accion-liga:hover {
            background-color: #4e94b8;
        }

        .seccion-activas {
            border-top: 1px solid #ddd;
            padding-top: 20px;
            text-align: left;
        }

        .seccion-activas h2 {
            font-size: 20px;
            color: #333;
            margin-bottom: 15px;
            text-align: center;
        }

        .tarjeta-liga {
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 12px 18px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .estado-abierta {
            background-color: #28a745;
            color: white;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 12px;
            text-transform: uppercase;
        }

        .estado-en_curso {
            background-color: #ffc107;
            color: #212529;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 12px;
            text-transform: uppercase;
            font-weight: bold;
        }

        .btn-entrar {
            background-color: #4e94b8;
            color: white;
            padding: 8px 14px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 14px;
        }

        .btn-volver {
            margin-top: 20px;
            display: inline-block;
            color: #666;
            text-decoration: none;
            font-size: 15px;
        }

        .btn-volver:hover {
            text-decoration: underline;
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
        <h1>Gestión de Ligas</h1>

        <!-- Opciones principales -->
        <div class="seccion-botones">
            <a href="crear_liga.php" class="btn-accion-liga">Crear una Liga</a>
            <a href="unirse_liga.php" class="btn-accion-liga">Unirse a una Liga</a>
        </div>

        <!-- Ligas en las que participa el usuario -->
        <div class="seccion-activas">
            <h2>Ligas en Curso / Disponibles</h2>
            <?php if (empty($misLigas)): ?>
                <p style="text-align: center; color: #777;">Aún no formas parte de ninguna liga.</p>
            <?php else: ?>
                <?php foreach ($misLigas as $liga): ?>
                    <div class="tarjeta-liga">
                        <div>
                            <strong><?php echo htmlspecialchars($liga['nombre']); ?></strong>
                            <div style="font-size: 13px; color: #666; margin-top: 4px;">
                                Código: <code><?php echo htmlspecialchars($liga['codigo_acceso']); ?></code>
                                <span class="estado-<?php echo $liga['estado']; ?>"><?php echo str_replace('_', ' ', $liga['estado']); ?></span>
                            </div>
                        </div>
                        <a href="ver_liga.php?id=<?php echo $liga['id']; ?>" class="btn-entrar">Acceder</a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <a href="index.php" class="btn-volver">← Volver al Panel Principal</a>
    </div>
</body>
</html>