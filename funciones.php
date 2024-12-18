<?php

// ************** OBTENER DATOS TABLA USUARIOS *********************************************************
function obtenerUsuarios() {
    try {        
        include('conexion.php');
        $query = "SELECT * FROM usuarios";
        //Preparamos y ejecutamos la consulta
        $stmt = $baseDatos->prepare($query);
        $stmt->execute();             
      
        $usuarios = [];
        // Guardamos las tiendas en un array
        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        
        } catch (PDOException $e) {
            die("Error al obtener las tiendas: " . $e->getMessage());
         }

    return $usuarios;
}

// ************** OBTENER DATOS TABLA EQUIPO *********************************************************
function obtenerEquipos() {
    try {        
        include('conexion.php');
        $query = "SELECT * FROM equipos";
        //Preparamos y ejecutamos la consulta
        $stmt = $baseDatos->prepare($query);
        $stmt->execute();             
      
        $equipos = [];
        // Guardamos las tiendas en un array
        $equipos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        
        } catch (PDOException $e) {
            die("Error al obtener las tiendas: " . $e->getMessage());
         }

    return $equipos;
}

// ************** OBTENER DATOS EQUIPO *********************************************************
function obtenerEquipoUnico($id) {
    try {        
        include('conexion.php');

        $query = "SELECT * FROM equipos WHERE id = :id";
        //Preparamos y ejecutamos la consulta
        $stmt = $baseDatos->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();             
      
       
        // Guardamos las tiendas en un array
        $equipo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        
        } catch (PDOException $e) {
            die("Error al obtener las tiendas: " . $e->getMessage());
         }

    return $equipo;
}
// ************** REGISTRAR NUEVO USUARIO EN TABLA USUARIOS *********************************************************
function registrarUsuario($email,$pass) {
    include('conexion.php');
    $usuarios = obtenerUsuarios();

    foreach ($usuarios as $u) {
        if ($u['email'] === $email) {
            return "No se puede registrar el usurio ya existe";
        }
    }

    try {            
        $query = "INSERT INTO usuarios (email, pass) VALUES (:email, :pass)";
        //Preparamos y ejecutamos la consulta
        $stmt = $baseDatos->prepare($query);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->bindParam(':pass', $pass, PDO::PARAM_STR);
        $stmt->execute();           

        } catch (PDOException $e) {
            die("Error al obtener las tiendas: " . $e->getMessage());
         }

   return "Usuario registrado correctamente";
}

// ************** COMPROBAR SI EXISTE USUARIO EN TABLA USUARIOS *********************************************************

function existeUsuario($email,$pass){
    include('conexion.php');
    $usuarios = obtenerUsuarios();
    $match = true;      
    foreach ($usuarios as $u) {
        if ($u['email'] === $email && $u['pass'] === $pass) {
            header("Location: index.php");
            die();
            
        }elseif($u['email'] === $email && $u['pass'] !== $pass){
            return "<div class='message error'>La contraseña es erronea</div>";
            
        }
    }

    if (!$match) {
        return "<div class='message error'>El email no está registrado aun.</div>";
    }

}

?>