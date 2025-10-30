<?php
// Guardar en: smm_panel/registro.php

// 1. INICIAR SESIÓN Y CARGAR CONFIGURACIÓN
session_start();
// Incluir la conexión a la base de datos
require_once 'includes/db_connect.php'; 
// Incluir la configuración global (para el nombre del sitio)
require_once 'includes/config_global.php'; 

// Lista de códigos de país comunes (Formato: Nombre => Código)
$country_codes = [
    'Colombia (+57)' => '+57',
    'México (+52)' => '+52',
    'España (+34)' => '+34',
    'Argentina (+54)' => '+54',
    'Chile (+56)' => '+56',
    'Perú (+51)' => '+51',
    'Ecuador (+593)' => '+593',
    'EE. UU. / Canadá (+1)' => '+1',
    'Otro' => '' // Opción para ingresar el código manualmente
];

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
    $codigo_pais = trim($_POST['codigo_pais']); // Nuevo
    $numero_telefono = trim($_POST['numero_telefono']); // Nuevo
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Combinar el código de país y el número
    $telefono = $codigo_pais . $numero_telefono;

    // Validación de datos básica
    if (empty($nombre) || empty($email) || empty($password) || empty($confirm_password) || empty($numero_telefono) || empty($codigo_pais)) {
        $error_message = "Todos los campos (incluyendo el número de teléfono con código de país) son obligatorios.";
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
            } else {
                $error_message = "Error al registrar: " . $conn->error;
            }
        }
        $stmt_check->close();
    }
}
// La línea $conn->close(); se mantiene eliminada de este archivo
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
        .telefono-group {
            display: flex;
            gap: 10px;
        }
        .telefono-group select {
            flex: 1;
        }
        .telefono-group input {
            flex: 2;
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
                    <label for="numero_telefono">Teléfono (WhatsApp - Para Notificaciones)</label>
                    <div class="telefono-group">
                        <select name="codigo_pais" id="codigo_pais" required>
                            <?php foreach ($country_codes as $name => $code): ?>
                                <option value="<?php echo htmlspecialchars($code); ?>" <?php echo ($code == '+57' ? 'selected' : ''); ?>>
                                    <?php echo htmlspecialchars($name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="numero_telefono" id="numero_telefono" placeholder="Número sin código" required>
                    </div>
                    <small style="display: block; margin-top: 5px; color: #6c757d;">El número de WhatsApp debe incluir el código de país. Ej: +57310xxxxxxx</small>
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