<?php

session_start(); 
$emailLogeado = $_SESSION['email'] ?? '';

include('funciones.php');
$usuarios = obtenerUsuarios();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['login'])) {
        $_SESSION['email'] = $_POST['email'];
        echo existeUsuario($_POST['email'],$_POST['password']);
    } elseif (isset($_POST['registrar'])) {
        echo registrarUsuario($_POST['new_email'],$_POST['new_password']);
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BB CUP</title>
    <link rel="stylesheet" href="css/estilos_BB.css">
</head>
<body>
    <div class="login-container">
        <div class="tabs">
            <div class="tab active" id="login-tab">LOGIN</div>
            <div class="tab" id="registrar-tab">REGISTRARSE</div>
        </div>

        <form method="POST" action="" class="form active" id="login-form">
            <div class="form-group">
                <label for="email"><img src="https://img.icons8.com/material-rounded/16/000000/user.png" alt="icon" style="vertical-align: middle;"> Email</label>
                <input type="email" id="email" name="email" placeholder="Introduce Email" required>
            </div>
            <div class="form-group">
                <label for="password"><img src="https://img.icons8.com/material-rounded/16/000000/lock.png" alt="icon" style="vertical-align: middle;"> Password</label>
                <input type="password" id="password" name="password" placeholder="Introduce Password" required>
            </div>
            <button type="submit" class="login-button" name="login">LOGIN</button>
        </form>

        <form method="POST" action="" class="form" id="registrar-form">
            <div class="form-group">
                <label for="new_email"><img src="https://img.icons8.com/material-rounded/16/000000/email.png" alt="icon" style="vertical-align: middle;"> Nuevo Email</label>
                <input type="email" id="new_email" name="new_email" placeholder="Introduce tu email" required>
            </div>
            <div class="form-group">
                <label for="new_password"><img src="https://img.icons8.com/material-rounded/16/000000/lock.png" alt="icon" style="vertical-align: middle;"> Nueva Password</label>
                <input type="password" id="new_password" name="new_password" placeholder="Introduce tu password" required>
            </div>
            <button type="submit" class="login-button" name="registrar">REGISTRARSE</button>
        </form>
    </div>

    <script>
        const loginTab = document.getElementById('login-tab');
        const registrarTab = document.getElementById('registrar-tab');
        const loginForm = document.getElementById('login-form');
        const registrarForm = document.getElementById('registrar-form');

        loginTab.addEventListener('click', () => {
            loginTab.classList.add('active');
            registrarTab.classList.remove('active');
            loginForm.classList.add('active');
            registrarForm.classList.remove('active');
        });

        registrarTab.addEventListener('click', () => {
            registrarTab.classList.add('active');
            loginTab.classList.remove('active');
            registrarForm.classList.add('active');
            loginForm.classList.remove('active');
        });

        setTimeout(() => {
            const message = document.querySelector('.message');
            if (message) message.remove();
        }, 4000);
    </script>
</body>
</html>
