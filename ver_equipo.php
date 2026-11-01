<?php
session_start();
include('conexion.php');
include('funciones.php');

if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

$emailLogeado = $_SESSION['email'];
$equipoId = $_GET['id'] ?? 0;

try {
    // 1. Obtener los datos del equipo
    $queryEquipo = "SELECT * FROM equipos WHERE id = :id";
    $stmt = $baseDatos->prepare($queryEquipo);
    $stmt->execute([':id' => $equipoId]);
    $equipo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$equipo) {
        die("El equipo no existe.");
    }

    // 2. Obtener los jugadores asociados a este equipo
    $queryJugadores = "SELECT * FROM jugadores WHERE equipo_id = :equipo_id ORDER BY numero ASC";
    $stmtJ = $baseDatos->prepare($queryJugadores);
    $stmtJ->execute([':equipo_id' => $equipoId]);
    $jugadores = $stmtJ->fetchAll(PDO::FETCH_ASSOC);

    // Indexar jugadores por su número de dorsal (1-16) para que cuadren en la tabla visual
    $jugadoresRoster = [];
    foreach ($jugadores as $j) {
        $jugadoresRoster[$j['numero']] = $j;
    }

} catch (PDOException $e) {
    die("Error al cargar el roster: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hoja de Equipo - <?php echo htmlspecialchars($equipo['nombre_equipo']); ?></title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background-image: url('imagenes/fondo.webp');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            color: #333;
        }

        .contenedor {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            padding: 20px;
            width: 98%;
            max-width: 1400px;
            margin: 40px 0;
        }

        h1 {
            text-align: center;
            font-size: 36px;
            color: #4e94b8;
            margin-bottom: 5px;
        }
        
        h3 {
            text-align: center;
            color: #555;
            margin-top: 0;
            margin-bottom: 30px;
        }

        .campo-superior {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            background: #e9ecef;
            padding: 15px;
            border-radius: 8px;
        }

        .bloque-info {
            display: flex;
            flex-direction: column;
            gap: 10px;
            width: 48%;
        }

        .info-fila {
            display: flex;
            justify-content: space-between;
            font-size: 16px;
            border-bottom: 1px solid #ccc;
            padding-bottom: 5px;
        }

        .info-fila span {
            font-weight: bold;
            color: #000;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 15px;
        }

        table thead {
            background-color: #000;
            color: #fff;
        }

        table th, table td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: center;
        }

        table tbody tr:nth-child(even) {
            background-color: #f2f2f2;
        }

        table tbody tr:hover {
            background-color: #e6f7ff;
        }

        .vacio {
            color: #aaa;
            font-style: italic;
        }

        .btn-volver {
            background-color: #6bb5d6;
            color: white;
            border: none;
            padding: 15px 30px;
            font-size: 18px;
            cursor: pointer;
            border-radius: 10px;
            margin-top: 20px;
            display: block;
            width: 100%;
            text-align: center;
            text-decoration: none;
            transition: background 0.3s;
            box-sizing: border-box;
        }

        .btn-volver:hover {
            background-color: #4e94b8;
        }
    </style>
</head>
<body>
    <div class="contenedor">
        <h1><?php echo htmlspecialchars($equipo['nombre_equipo']); ?></h1>
        <h3>Raza: <?php echo htmlspecialchars($equipo['raza']); ?></h3>

        <div class="campo-superior">
            <!-- Estadísticas principales -->
            <div class="bloque-info">
                <div class="info-fila">Valor del Equipo (TV): <span><?php echo number_format($equipo['valor_equipo'], 0, ',', '.'); ?> GP</span></div>
                <div class="info-fila">Tesorería: <span><?php echo number_format($equipo['tesoreria'], 0, ',', '.'); ?> GP</span></div>
            </div>

            <!-- Cuerpo técnico e incentivos -->
            <div class="bloque-info">
                <div class="info-fila">Re-rolls: <span><?php echo $equipo['rerolls']; ?></span></div>
                <div class="info-fila">Animadoras: <span><?php echo $equipo['animadoras']; ?></span></div>
                <div class="info-fila">Asistentes: <span><?php echo $equipo['ayudantes_entrenador']; ?></span></div>
                <div class="info-fila">Fans Dedicados: <span><?php echo $equipo['fans_dedicados']; ?></span></div>
                <div class="info-fila">Médico (Apoth): <span><?php echo $equipo['apotecario'] ? 'Sí' : 'No'; ?></span></div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Posición</th>
                    <th>Nombre</th>
                    <th>MA</th>
                    <th>ST</th>
                    <th>AG</th>
                    <th>PA</th>
                    <th>AV</th>
                    <th>Habilidades</th>
                    <th>Valor (GP)</th>
                </tr>
            </thead>
            <tbody>
                <?php for ($i = 1; $i <= 16; $i++): ?>
                    <?php if (isset($jugadoresRoster[$i])): 
                        $jug = $jugadoresRoster[$i];
                    ?>
                        <tr>
                            <td><strong><?php echo $i; ?></strong></td>
                            <td><?php echo htmlspecialchars($jug['posicion']); ?></td>
                            <td><strong><?php echo htmlspecialchars($jug['nombre']); ?></strong></td>
                            <td><?php echo htmlspecialchars($jug['ma']); ?></td>
                            <td><?php echo htmlspecialchars($jug['st']); ?></td>
                            <td><?php echo htmlspecialchars($jug['ag']); ?></td>
                            <td><?php echo htmlspecialchars($jug['pa']); ?></td>
                            <td><?php echo htmlspecialchars($jug['av']); ?></td>
                            <td style="font-size: 13px;"><?php echo htmlspecialchars($jug['habilidades']); ?></td>
                            <td><?php echo number_format($jug['coste'], 0, ',', '.'); ?></td>
                        </tr>
                    <?php else: ?>
                        <tr class="vacio">
                            <td><?php echo $i; ?></td>
                            <td>-</td>
                            <td>Vacío</td>
                            <td>-</td>
                            <td>-</td>
                            <td>-</td>
                            <td>-</td>
                            <td>-</td>
                            <td>-</td>
                            <td>-</td>
                        </tr>
                    <?php endif; ?>
                <?php endfor; ?>
            </tbody>
        </table>

        <a href="mis_equipos.php" class="btn-volver">Volver a Mis Equipos</a>
    </div>
</body>
</html>