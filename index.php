<?php

session_start(); 
$emailLogeado = $_SESSION['email'] ?? '';

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BB CUP</title>
    <link rel="stylesheet" href="css/dashboard.css">
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background-image: url('imagenes/fondo.webp');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }

        .identificador {
            display: flex;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            padding: 5px;
            text-align: center;
            width: 300px;
            position:fixed;
            margin: 0 auto;
            top: 20px;  
            right: 20px;          
            padding: 5px;
            align-items: center;

        }

        .identificador img{
            width: 10%;
            height: 10%;
            padding-right: 20px;
        }

        .contenido-princpial {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            padding: 20px;
            text-align: center;
            width: 300px;
        }

        .contenido-princpial h1 {
            font-size: 24px;
            margin-bottom: 20px;
            color: #4e94b8;
        }

        .contenido-princpial button {
            background-color: #6bb5d6;
            color: white;
            border: none;
            padding: 10px;
            margin: 10px 0;
            width: 100%;
            cursor: pointer;
            font-size: 16px;
            border-radius: 5px;
            transition: background 0.3s;
        }

        .contenido-princpial button:hover {
            background-color: #4e94b8;
        }
    </style>
</head>
<body>
    <div class="identificador">
        <img src="imagenes/icono_usuario.jpg" alt="Usuario">        
        <p><?php echo $emailLogeado; ?></p>
    </div>
    <div class="contenido-princpial">
        <h1>Bienvenido a BB CUP</h1>
        <button onclick="window.location.href='crear_equipo.php'">Crear Nuevo Equipo</button>
        <button onclick="window.location.href='mis_equipos.html'">Ver Mis Equipos</button>
        <button onclick="window.location.href='unirse_liga.html'">Unirse a una Liga</button>
    </div>
</body>
</html>
