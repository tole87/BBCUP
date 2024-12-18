<?php

session_start(); 
$emailLogeado = $_SESSION['email'] ?? '';
include('funciones.php');
$equipos = obtenerEquipos();

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Creacion de Equipo</title>
    <!-- Ruta corregida de CSS -->
    
    <!-- CSS de Select2 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />
    <!-- jQuery primero -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Select2 después -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>


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
    position: fixed;
    top: 20px;  
    right: 20px;          
    align-items: center;
}

.identificador img {
    width: 10%;
    height: 10%;
    padding-right: 20px;
}

.contenido-principal {
    background: rgba(255, 255, 255, 0.9);
    border-radius: 10px;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
    padding: 40px; /* Aumentado */
    text-align: center;
    width: 600px; /* Aumentado */
}

.contenido-principal h1 {
    font-size: 48px; /* Aumentado */
    margin-bottom: 40px; /* Aumentado */
    color: #4e94b8;
}

.contenido-principal button {
    background-color: #6bb5d6;
    color: white;
    border: none;
    padding: 20px; /* Aumentado */
    margin: 20px 0; /* Aumentado */
    width: 100%;
    cursor: pointer;
    font-size: 32px; /* Aumentado */
    border-radius: 10px; /* Aumentado */
    transition: background 0.3s;
}

.contenido-principal button:hover {
    background-color: #4e94b8;
}

.dropdown-logo {
        width: 40px;
        height: 40px;
        margin-right: 20px;
        vertical-align: middle;
        border-radius: 5px;
    }

    /* Ajusta la altura de cada opción del dropdown */
    .select2-results__option {
        
        display: flex;
        align-items: center;
        height: 60px; /* Incrementa la altura */
        font-size: 20px; /* Opcional, aumenta el tamaño de texto */
    }

    /* Ajusta la altura del contenedor seleccionado */
    .select2-selection__rendered {
        padding-top: 10px;
        display: flex;
        align-items: center;
        height: 60px; /* Coincide con la altura de las opciones */
        font-size: 20px;
    }

    /* Aumenta el contenedor del dropdown completo */
    .select2-container--default .select2-selection--single {
        height: 60px; /* Coincide con las opciones */
    }

    /* Ajusta la imagen dentro de las opciones */
    .select2-results__option img {
        width: 40px;
        height: 40px;
        margin-right: 10px;
    }


    </style>
</head>
<body>
    <div class="identificador">
        <img src="imagenes/icono_usuario.jpg" alt="Usuario">        
        <p><?php echo $emailLogeado; ?></p>
    </div>
    <div class="contenido-principal">
        <h1>Elije Equipo</h1>
        <form method="POST" action="editar_equipo.php">
            <select name="equipo" id="equipo" required>
                <option value="">-- Selecciona un equipo --</option>
                <?php foreach ($equipos as $equipo): ?>
                    <option value="<?= $equipo['id'] ?>" data-logo="<?= htmlspecialchars($equipo['logo']) ?>">
                        <?= htmlspecialchars($equipo['nombre']) ?>
                        
                    </option>
                    
                <?php endforeach; ?>
            </select>
            <button type="submit" name="seleccion">Crear Equipo</button>
        </form>
    </div>

    <script>
    $(document).ready(function () {
        $('#equipo').select2({
            templateResult: formatOption,
            templateSelection: formatOption,
            width: '100%'
        });

        function formatOption(option) {
            if (!option.id) {
                return option.text;
            }
            var logo = $(option.element).data('logo');
            if (logo) {
                return $(
                    '<span><img src="' + logo + '" class="dropdown-logo" /> ' + option.text + '</span>'
                );
            }
            return option.text;
        }
    });
    </script>
</body>
</html>
