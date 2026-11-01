<?php
session_start();
include('conexion.php');
include('funciones.php');

// Verificar sesión
if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

$emailLogeado = $_SESSION['email'];

// Obtener el ID del usuario actual
$usuarios = obtenerUsuarios();
$usuarioId = null;
foreach ($usuarios as $u) {
    if ($u['email'] === $emailLogeado) {
        $usuarioId = $u['id'];
        break;
    }
}

// Consultar los equipos de este usuario en la base de datos
try {
    $query = "SELECT * FROM equipos WHERE usuario_id = :usuario_id";
    $stmt = $baseDatos->prepare($query);
    $stmt->execute([':usuario_id' => $usuarioId]);
    $misEquipos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error al cargar los equipos: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Equipos - BB CUP</title>
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
            max-width: 1000px; /* Un poco más ancho para que quepa bien el botón */
            text-align: center;
        }

        h1 {
            color: #4e94b8;
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 16px;
        }

        table thead {
            background-color: #000;
            color: #fff;
        }

        table th, table td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: center;
        }

        table tbody tr:nth-child(even) {
            background-color: #f2f2f2;
        }

        .btn-volver, .btn-accion {
            background-color: #6bb5d6;
            color: white;
            border: none;
            padding: 10px 20px;
            font-size: 16px;
            cursor: pointer;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
            margin-top: 20px;
            transition: background 0.3s;
        }

        /* Estilo ajustado específicamente para el botón de la tabla */
        .btn-accion {
            margin-top: 0;
            padding: 8px 15px;
            font-size: 14px;
        }

        .btn-volver:hover, .btn-accion:hover {
            background-color: #4e94b8;
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
        <h1>Mis Equipos Registrados</h1>

        <?php if (empty($misEquipos)): ?>
            <p>Aún no tienes ningún equipo creado. ¡Empieza creando uno nuevo!</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Nombre del Equipo</th>
                        <th>Raza</th>
                        <th>Valor (TV)</th>
                        <th>Tesorería</th>
                        <th>Re-rolls</th>
                        <th>Acciones</th> <!-- Nueva columna -->
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($misEquipos as $eq): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($eq['nombre_equipo']); ?></strong></td>
                        <td><?php echo htmlspecialchars($eq['raza']); ?></td>
                        <td><?php echo number_format($eq['valor_equipo'], 0, ',', '.'); ?> GP</td>
                        <td><?php echo number_format($eq['tesoreria'], 0, ',', '.'); ?> GP</td>
                        <td><?php echo $eq['rerolls']; ?></td>
                        <!-- Botón "Ver Roster" -->
                        <td><a href="ver_equipo.php?id=<?php echo $eq['id']; ?>" class="btn-accion">Ver Roster</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <br>
        <a href="index.php" class="btn-volver">Volver al Panel Principal</a>
    </div>
</body>
</html>