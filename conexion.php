<?php
try {
    // Conexión a la base de datos mediante PDO
    $dsn = "mysql:host=localhost;dbname=bowlbbdd;charset=utf8mb4";
    $usuario = "tole";
    $contrasena = "Santander1987!12345";

    $baseDatos = new PDO($dsn, $usuario, $contrasena);

    // Configurar PDO para utilizar errores y excepciones
    $baseDatos->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
?>
