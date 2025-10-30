<?php
// Guardar en: smm_panel/login.php

// 1. INICIAR SESIÓN Y CARGAR CONFIGURACIÓN
session_start();
// Incluir la conexión a la base de datos
require_once 'includes/db_connect.php'; 
// Incluir la configuración global (para cargar el nombre del sitio, etc.)
require_once 'includes/config_global.php'; 

// Redirigir si el usuario ya está logueado
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    if ($_SESSION["rol"] === 'admin') {
        header("location: admin/dashboard.php");
    } else {
        header("location: cuenta.php");
    }
    exit;
}

$error_message = '';

// 2. LÓGICA DE LOGIN
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Recolección y limpieza de datos
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error_message = "Por favor, ingresa tu email y contraseña.";
    } else {
        
        $sql = "SELECT id, nombre, password_hash, rol FROM usuarios WHERE email = ?";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows == 1) {
                // Email encontrado, verificar contraseña
                $stmt->bind_result($id, $nombre, $hashed_password, $rol);
                $stmt->fetch();

                if (password_verify($password, $hashed_password)) {
                    
                    // Contraseña correcta: Iniciar la sesión
                    session_regenerate_id();
                    $_SESSION["loggedin"] = true;
                    $_SESSION["id"] = $id;
                    $_SESSION["nombre"] = $nombre;
                    $_SESSION["rol"] = $rol;

                    // Redirigir al panel correspondiente
                    if ($rol === 'admin') {
                        header("location: admin/dashboard.php");
                    } else {
                        header("location: cuenta.php");
                    }
                    exit;
                } else {
                    $error_message = "Email o Contraseña inválidos.";
                }
            } else {
                $error_message = "Email o Contraseña inválidos.";
            }
            $stmt->close();
        } else {
            $error_message = "Error interno del sistema al preparar la consulta.";
        }
    }
}
// ¡LÍNEA $conn->close(); ELIMINADA AQUÍ!
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión | <?php echo get_config('SITE_NAME'); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* Estilos específicos del formulario para centrado y estética */
        .login-container {
            max-width: 400px;
            margin: 50px auto;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <?php include 'header_client.php'; ?>

    <main class="container">
        <div class="login-container">
            <h2 style="text-align: center; margin-bottom: 20px;">Acceso a Cuenta</h2>

            <?php if ($error_message): ?>
                <p style="color: red; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; margin-bottom: 15px;"><?php echo $error_message; ?></p>
            <?php endif; ?>
            
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" name="email" id="email" required>
                </div>

                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <input type="password" name="password" id="password" required>
                </div>
                
                <button type="submit" class="btn-primary" style="width: 100%; margin-top: 10px;">Iniciar Sesión</button>
                <p style="text-align: center; margin-top: 10px;"><a href="recuperar_contrasena.php" style="font-weight: bold; color: #ffc107;">¿Olvidaste tu Contraseña?</a></p>
                <p style="text-align: center; margin-top: 15px;">¿No tienes cuenta? <a href="registro.php" style="font-weight: bold;">Regístrate aquí</a></p>
            </form>
        </div>
    </main>
    
    <a href="https://wa.me/<?php echo get_config('WHATSAPP_NUMBER'); ?>" class="whatsapp-float" target="_blank" title="Contacta con el Administrador">
        💬 
    </a>
</body>
</html>