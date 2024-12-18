<?php
session_start();
$emailLogeado = $_SESSION['email'] ?? '';
include ('funciones.php');
$equipo = obtenerEquipoUnico($_POST['equipo']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Equipo</title>
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
            height: 100vh;
            color: #333;
        }

        .contenedor {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            padding: 20px;
            width: 95%;
            max-width: 1200px;
            margin: 20px;
        }

        h1 {
            text-align: center;
            font-size: 36px;
            color: #4e94b8;
            margin-bottom: 20px;
        }

        .campo-superior {
            display: flex;
            
            margin-bottom: 20px;
        }

        .campo-superior div {
            margin-bottom: 10px;
            
            padding: 5px;
        }

        .campo-superior label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
            font-size: 16px;
        }

        .campo-superior input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
        }

        .campo-superior p{
            display: flex;
            align-items: flex-end;
        }

        .campo-superior p label{
            padding-right: 5px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 16px;
        }

        table thead {
            background-color: #000;
            color: #fff;
            font-weight: bold;
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

        input[type="text"],
        input[type="number"] {
            width: 90%;
            padding: 5px;
            border: 1px solid #ccc;
            border-radius: 5px;
            text-align: center;
        }

        .boton-guardar {
            background-color: #6bb5d6;
            color: white;
            border: none;
            padding: 15px 30px;
            font-size: 20px;
            cursor: pointer;
            border-radius: 10px;
            margin-top: 20px;
            display: block;
            text-align: center;
            transition: background 0.3s;
        }

        .boton-guardar:hover {
            background-color: #4e94b8;
        }

        .identificador {
            display: flex;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            padding: 10px;
            width: 300px;
            position: fixed;
            top: 20px;
            right: 20px;
            align-items: center;
            text-align: center;
        }

        .identificador img {
            width: 40px;
            height: 40px;
            margin-right: 10px;
            border-radius: 50%;
        }

        .identificador p {
            margin: 0;
            font-weight: bold;
            color: #555;
        }

        #reroll, #asistente, #animadoras, #fans, #medico {
            width: 30px;            
        }

        .addons {
            display: flex;
            justify-content: space-between;
            height: 20px;
        }

        .addons label{
            width: 30%;
        }

        #valor_reroll, #valor_asistente, #total_reroll, #total_asistente, #valor_animadoras, #total_animadoras, #valor_fans, #total_fans, #valor_medico, #total_medico, #nombre_equipo, #tesoreria, #valor_equipo {
            width: 100px;
        }

        .addons-izq {
            width: 50%;
        }
        
        .addons-drch {
            width: 50%;
        }

        .add {
           display: flex;
        }

        .add label{
            width: 30%;
        }     

      
    </style>
</head>
<body>
    <!-- Identificador de usuario -->
    <div class="identificador">
        <img src="imagenes/icono_usuario.jpg" alt="Usuario">
        <p><?php echo $emailLogeado; ?></p>
    </div>

    <!-- Contenedor Principal -->
    <div class="contenedor">
        <h1><?php echo $equipo['nombre']; ?></h1>
        <form method="POST" action="guardar_equipo.php">
            <!-- Campos adicionales encima de la tabla -->
            <div class="campo-superior">
                <div class="addons-izq">
                    <div class = "add">
                        <label for="nombre_equipo">Nombre del Equipo</label>
                        <input type="text" id="nombre_equipo" name="nombre_equipo" placeholder="Nombre del equipo" required>
                    </div>
                    <div class = "add">
                        <label for="tesoreria">Tesorería</label>                    
                        <input type="number" id="tesoreria" name="tesoreria" value="<?php echo $equipo['presupuesto']; ?>" min="0">
                    </div>
                    <div class = "add">
                        <label for="valor_equipo">Valor del Equipo</label>
                        <input type="number" id="valor_equipo" name="valor_equipo" placeholder="0" min="0">
                    </div>
                </div>
                <div class="addons-drch">
                    <div class = "addons">
                        <label for="reroll">Re-rolls Equipo:</label>
                        <input type="number" id="reroll" name="reroll" placeholder="0" min="0" max="8">
                        <p>  X  </p>
                        <input class="precios" type="number" id="valor_reroll" name="valor_reroll" value="<?php echo $equipo['reroll']; ?>" readonly>
                        <p>  =  </p>
                        <input class="precios" type="number" id="total_reroll" name="total_reroll" placeholder="0 GP" min="0">
                    </div>
                    <div class = "addons">
                        <label for="asistente">Asistente Entrenador:</label>
                        <input type="number" id="asistente" name="asistente" placeholder="0" min="0" max="6">
                        <p>  X  </p>
                        <input class="precios" type="number" id="valor_asistente" name="valor_asistente" value="10000" readonly>
                        <p>  =  </p>
                        <input class="precios" type="number" id="total_asistente" name="total_asistente" placeholder="0 GP" min="0">
                    </div>

                    <div class = "addons">
                        <label for="animadoras">Animadoras:</label>
                        <input type="number" id="animadoras" name="animadoras" placeholder="0" min="0" max="12">
                        <p>  X  </p>
                        <input class="precios" type="number" id="valor_animadoras" name="valor_animadoras" value="10000" readonly>
                        <p>  =  </p>
                        <input class="precios" type="number" id="total_animadoras" name="total_animadoras" placeholder="0 GP" min="0">
                    </div>
                    <div class = "addons">
                        <label for="fans">Fans Dedicados:</label>
                        <input type="number" id="fans" name="fans" placeholder="0" min="0" max="6">
                        <p>  X  </p>
                        <input class="precios" type="number" id="valor_fans" name="valor_fans" value="10000" readonly>
                        <p>  =  </p>
                        <input class="precios" type="number" id="total_fans" name="total_fans" placeholder="0 GP" min="0">
                    </div>
                    <div class = "addons">
                        <label for="medico">Médico:</label>
                        <input type="number" id="medico" name="medico" placeholder="0" min="0" max="1">
                        <p>  X  </p>
                        <input class="precios" type="number" id="valor_medico" name="valor_medico" value="50000" readonly>
                        <p>  =  </p>
                        <input class="precios" type="number" id="total_medico" name="total_medico" placeholder="0 GP" min="0">
                    </div>
                    
                    
                </div>
               
            </div>

            <!-- Tabla de jugadores -->
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nombre</th>
                        <th>Posición</th>
                        <th>MA</th>
                        <th>ST</th>
                        <th>AG</th>
                        <th>PA</th>
                        <th>AV</th>
                        <th>Habilidades</th>
                        <th>Valor</th>
                    </tr>
                </thead>
                <tbody>
                    <?php for ($i = 1; $i <= 16; $i++): ?>
                    <tr>
                        <td><input type="text" name="id_<?php echo $i; ?>" placeholder="<?php echo $i; ?>"></td>
                        <td><input type="text" name="nombre_<?php echo $i; ?>" placeholder="-"></td>
                        <td><input type="text" name="posicion_<?php echo $i; ?>" placeholder="-"></td>
                        <td><input type="number" name="ma_<?php echo $i; ?>" min="0"></td>
                        <td><input type="number" name="st_<?php echo $i; ?>" min="0"></td>
                        <td><input type="number" name="ag_<?php echo $i; ?>" min="0"></td>
                        <td><input type="number" name="pa_<?php echo $i; ?>" min="0"></td>
                        <td><input type="number" name="av_<?php echo $i; ?>" min="0"></td>
                        <td><input type="text" name="habilidades_<?php echo $i; ?>" placeholder="-"></td>
                        <td><input type="number" name="valor_<?php echo $i; ?>" min="0"></td>
                    </tr>
                    <?php endfor; ?>
                </tbody>
            </table>

            <!-- Botón Guardar -->
            <button type="submit" class="boton-guardar">Guardar Equipo</button>
        </form>
    </div>

    <script>
        document.getElementById("reroll").oninput = function() {
            document.getElementById("total_reroll").value = (document.getElementById("reroll").value * document.getElementById("valor_reroll").value);
        };
        document.getElementById("asistente").oninput = function() {
            document.getElementById("total_asistente").value = (document.getElementById("asistente").value * document.getElementById("valor_asistente").value);
        };
        document.getElementById("animadoras").oninput = function() {
            document.getElementById("total_animadoras").value = (document.getElementById("animadoras").value * document.getElementById("valor_animadoras").value);
        };
        document.getElementById("fans").oninput = function() {
            document.getElementById("total_fans").value = (document.getElementById("fans").value * document.getElementById("valor_fans").value);
        };
        document.getElementById("medico").oninput = function() {
            document.getElementById("total_medico").value = (document.getElementById("medico").value * document.getElementById("valor_medico").value);
        };

        document.addEventListener("DOMContentLoaded", function () {
            // Captura los inputs
            const reroll = document.getElementById("reroll");
            const asistente = document.getElementById("asistente");
            const animadoras = document.getElementById("animadoras");
            const fans = document.getElementById("fans");
            const medico = document.getElementById("medico");
            const valorEquipo = document.getElementById("valor_equipo");
            const tesoreria = document.getElementById("tesoreria");

            // Función para actualizar el input "resultado"
            function actualizarValor() {
                const valor1 = parseInt(reroll.value) || 0;
                const valor2 = parseInt(asistente.value) || 0;
                const valor3 = parseInt(animadoras.value) || 0;
                const valor4 = parseInt(fans.value) || 0;
                const valor5 = parseInt(medico.value) || 0;

                valorEquipo.value = (valor1 * document.getElementById("valor_reroll").value) + (valor2 * document.getElementById("valor_asistente").value) + (valor3 * document.getElementById("valor_animadoras").value) + (valor4 * document.getElementById("valor_fans").value)+ (valor5 * document.getElementById("valor_medico").value); // Cambia aquí la lógica según necesites
                tesoreria.value -= valorEquipo.value;
            }

            // Escuchar cambios en los 3 inputs
            reroll.addEventListener("input", actualizarValor);
            asistente.addEventListener("input", actualizarValor);
            animadoras.addEventListener("input", actualizarValor);
            fans.addEventListener("input", actualizarValor);
            medico.addEventListener("input", actualizarValor);
        });
    </script>
</body>
</html>
