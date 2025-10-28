<?php
// Guardar en: smm_panel/registro.php

// 1. INICIAR SESIÓN Y CARGAR CONFIGURACIÓN
session_start();
// Incluir la conexión a la base de datos
require_once 'includes/db_connect.php'; 
// Incluir la configuración global (para el nombre del sitio)
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

$error_message = $success_message = '';

// 2. LÓGICA DE REGISTRO
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Recolección y saneamiento de datos
    $nombre = trim($_POST['nombre']);
    $email = trim($_POST['email']);
    $telefono = trim($_POST['telefono']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validación de datos básica
    if (empty($nombre) || empty($email) || empty($password) || empty($confirm_password)) {
        $error_message = "Todos los campos son obligatorios.";
    } elseif ($password !== $confirm_password) {
        $error_message = "Las contraseñas no coinciden.";
    } elseif (strlen($password) < 6) {
        $error_message = "La contraseña debe tener al menos 6 caracteres.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "El formato del email es inválido.";
    } else {
        // Verificación de si el email ya existe
        $stmt_check = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stmt_check->bind_param("s", $email);
        $stmt_check->execute();
        $stmt_check->store_result();
        
        if ($stmt_check->num_rows > 0) {
            $error_message = "Este email ya está registrado. Por favor, inicia sesión.";
        } else {
            // Encriptación de la contraseña (SEGURIDAD CRÍTICA)
            $password_hash = password_hash($password, PASSWORD_BCRYPT);
            
            // Inserción del nuevo usuario
            $stmt_insert = $conn->prepare("INSERT INTO usuarios (nombre, email, telefono, password_hash) VALUES (?, ?, ?, ?)");
            $stmt_insert->bind_param("ssss", $nombre, $email, $telefono, $password_hash);
            
            if ($stmt_insert->execute()) {
                $success_message = "¡Registro exitoso! Ya puedes iniciar sesión.";
                // Aquí podrías agregar la lógica de envío de WhatsApp con las credenciales si fuera un registro de checkout.
            } else {
                $error_message = "Error al registrar: " . $conn->error;
            }
        }
        $stmt_check->close();
    }
}
$conn->close(); // Cerramos la conexión a la BD aquí, antes de que termine el bloque PHP.
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Cuenta | <?php echo get_config('SITE_NAME'); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* Estilos para centrar y estilizar la caja de registro */
        .registro-container {
            max-width: 450px;
            margin: 50px auto;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <?php include 'header_client.php'; ?>

    <main class="container">
        <div class="registro-container">
            <h2 style="text-align: center; margin-bottom: 20px; color: var(--color-principal);">🌟 Únete a <?php echo get_config('SITE_NAME'); ?></h2>

            <?php if ($error_message): ?>
                <p style="color: red; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; margin-bottom: 15px;"><?php echo $error_message; ?></p>
            <?php endif; ?>
            
            <?php if ($success_message): ?>
                <p style="color: green; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px; margin-bottom: 15px;"><?php echo $success_message; ?></p>
            <?php endif; ?>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="form-group">
                    <label for="nombre">Nombre Completo</label>
                    <input type="text" name="nombre" id="nombre" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email (Usuario de Acceso)</label>
                    <input type="email" name="email" id="email" required>
                </div>
                
                <div class="form-group">
                    <label for="telefono">Teléfono (WhatsApp - Para Notificaciones)</label>
                    <input type="text" name="telefono" id="telefono" placeholder="+CódigoPaísNúmero" required>
                </div>

                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <input type="password" name="password" id="password" required>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirmar Contraseña</label>
                    <input type="password" name="confirm_password" id="confirm_password" required>
                </div>
                
                <p style="font-size: 0.8em; margin-bottom: 15px; text-align: center;">Al registrarte, aceptas nuestros <a href="#">Términos y Condiciones</a>.</p>

                <button type="submit" class="btn-primary" style="width: 100%;">Registrarse</button>
                
                <p style="text-align: center; margin-top: 15px;">¿Ya tienes cuenta? <a href="login.php" style="font-weight: bold;">Inicia Sesión</a></p>
            </form>
        </div>
    </main>
    
    <a href="https://wa.me/<?php echo get_config('WHATSAPP_NUMBER'); ?>" class="whatsapp-float" target="_blank" title="Contacta con el Administrador">
        💬 
    </a>
</body>
</html>