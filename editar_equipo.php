<?php
session_start();
$emailLogeado = $_SESSION['email'] ?? '';
include ('funciones.php');

$equipo = obtenerEquipoUnico($_POST['equipo'] ?? 1); 
$posicionesDisponibles = obtenerPosicionesPorRaza($equipo['id']);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración de Equipo - Blood Bowl</title>
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
            margin-bottom: 20px;
        }

        .campo-superior {
            display: flex;
            gap: 20px;
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

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 15px;
        }

        table thead {
            background-color: #000;
            color: #fff;
            font-weight: bold;
        }

        table th, table td {
            padding: 8px;
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
        input[type="number"],
        select {
            width: 95%;
            padding: 6px;
            border: 1px solid #ccc;
            border-radius: 5px;
            text-align: center;
            font-size: 14px;
        }

        input.autofill {
            background-color: #e9ecef;
            border: none;
            font-weight: bold;
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
            width: 100%;
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
            z-index: 100;
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
            width: 40px;            
        }

        .addons {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
        }

        .addons label {
            width: 35%;
            font-size: 14px;
        }

        .precios, #total_reroll, #total_asistente, #total_animadoras, #total_fans, #total_medico, #nombre_equipo, #tesoreria, #valor_equipo {
            width: 90px;
            background-color: #e9ecef;
        }

        .addons-izq, .addons-drch {
            width: 50%;
        }

        .add {
            display: flex;
            flex-direction: column;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="identificador">
        <img src="imagenes/icono_usuario.jpg" alt="Usuario">
        <p><?php echo htmlspecialchars($emailLogeado); ?></p>
    </div>

    <div class="contenedor">
        <h1>Configuración de Equipo: <?php echo htmlspecialchars($equipo['nombre']); ?></h1>
        <form method="POST" action="guardar_equipo.php" id="form-equipo">
            <input type="hidden" name="tipo_equipo_id" value="<?php echo $equipo['id']; ?>">
            
            <div class="campo-superior">
                <div class="addons-izq">
                    <div class="add">
                        <label for="nombre_equipo">Nombre del Equipo</label>
                        <input type="text" id="nombre_equipo" name="nombre_equipo" placeholder="Nombre del equipo" required>
                    </div>
                    <div class="add">
                        <label for="tesoreria">Tesorería (GP)</label>                    
                        <input type="number" id="tesoreria" name="tesoreria" value="<?php echo $equipo['presupuesto_inicial']; ?>" readonly>
                    </div>
                    <div class="add">
                        <label for="valor_equipo">Valor del Equipo (TV)</label>
                        <input type="number" id="valor_equipo" name="valor_equipo" value="0" readonly>
                    </div>
                </div>

                <div class="addons-drch">
                    <div class="addons">
                        <label for="reroll">Re-rolls:</label>
                        <input type="number" id="reroll" name="reroll" value="0" min="0" max="8">
                        <p>×</p>
                        <input class="precios" type="number" id="valor_reroll" value="<?php echo $equipo['coste_reroll']; ?>" readonly>
                        <p>=</p>
                        <input class="precios" type="number" id="total_reroll" value="0" readonly>
                    </div>
                    <div class="addons">
                        <label for="asistente">Asistentes:</label>
                        <input type="number" id="asistente" name="asistente" value="0" min="0" max="10">
                        <p>×</p>
                        <input class="precios" type="number" id="valor_asistente" value="10000" readonly>
                        <p>=</p>
                        <input class="precios" type="number" id="total_asistente" value="0" readonly>
                    </div>
                    <div class="addons">
                        <label for="animadoras">Animadoras:</label>
                        <input type="number" id="animadoras" name="animadoras" value="0" min="0" max="10">
                        <p>×</p>
                        <input class="precios" type="number" id="valor_animadoras" value="10000" readonly>
                        <p>=</p>
                        <input class="precios" type="number" id="total_animadoras" value="0" readonly>
                    </div>
                    <div class="addons">
                        <label for="fans">Fans Dedicados:</label>
                        <input type="number" id="fans" name="fans" value="1" min="1" max="6">
                        <p>×</p>
                        <input class="precios" type="number" id="valor_fans" value="10000" readonly>
                        <p>=</p>
                        <input class="precios" type="number" id="total_fans" value="0" readonly>
                    </div>
                    <div class="addons">
                        <label for="medico">Médico (Apoth):</label>
                        <input type="number" id="medico" name="medico" value="0" min="0" max="1">
                        <p>×</p>
                        <input class="precios" type="number" id="valor_medico" value="50000" readonly>
                        <p>=</p>
                        <input class="precios" type="number" id="total_medico" value="0" readonly>
                    </div>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Seleccionar Posición</th>
                        <th>Disponibilidad</th>
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
                    <tr class="fila-jugador" data-index="<?php echo $i; ?>">
                        <td><input type="text" name="id_<?php echo $i; ?>" value="<?php echo $i; ?>" style="width: 30px;" readonly></td>
                        
                        <td>
                            <select name="posicion_base_<?php echo $i; ?>" class="selector-posicion">
                                <option value="">-- Vacío --</option>
                                <?php foreach ($posicionesDisponibles as $pos): ?>
                                    <option value="<?php echo htmlspecialchars($pos['nombre_posicion']); ?>"
                                            data-max="<?php echo $pos['max_cantidad']; ?>"
                                            data-coste="<?php echo $pos['coste']; ?>"
                                            data-ma="<?php echo $pos['ma']; ?>"
                                            data-st="<?php echo $pos['st']; ?>"
                                            data-ag="<?php echo $pos['ag']; ?>"
                                            data-pa="<?php echo $pos['pa']; ?>"
                                            data-av="<?php echo $pos['av']; ?>"
                                            data-habilidades="<?php echo htmlspecialchars($pos['habilidades']); ?>">
                                        <?php echo htmlspecialchars($pos['nombre_posicion']) . " (" . number_format($pos['coste'], 0, ',', '.') . " GP)"; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="posicion_<?php echo $i; ?>" class="input-posicion" value="-">
                        </td>

                        <td><input type="text" class="autofill input-conteo" value="-" readonly style="width: 70px;"></td>
                        <td><input type="text" name="nombre_<?php echo $i; ?>" placeholder="Apodo/Nombre"></td>
                        <td><input type="text" name="ma_<?php echo $i; ?>" class="autofill input-ma" value="-" readonly></td>
                        <td><input type="text" name="st_<?php echo $i; ?>" class="autofill input-st" value="-" readonly></td>
                        <td><input type="text" name="ag_<?php echo $i; ?>" class="autofill input-ag" value="-" readonly></td>
                        <td><input type="text" name="pa_<?php echo $i; ?>" class="autofill input-pa" value="-" readonly></td>
                        <td><input type="text" name="av_<?php echo $i; ?>" class="autofill input-av" value="-" readonly></td>
                        <td><input type="text" name="habilidades_<?php echo $i; ?>" class="autofill input-habilidades" value="-" readonly></td>
                        <td><input type="number" class="autofill valor-jugador input-valor" name="valor_<?php echo $i; ?>" value="0" readonly></td>
                    </tr>
                    <?php endfor; ?>
                </tbody>
            </table>

            <button type="submit" class="boton-guardar">Guardar Equipo en Base de Datos</button>
        </form>
    </div>

    <!-- 1. Configuramos variables globales que JS necesita leer desde PHP -->
    <script>
        const presupuestoInicial = <?php echo (int)$equipo['presupuesto_inicial']; ?>;
    </script>
    
    <!-- 2. Llamamos al motor lógico separado en la carpeta js/ -->
    <script src="js/funciones_equipo.js"></script>
</body>
</html>