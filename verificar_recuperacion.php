<?php
// Guardar en: smm_panel/verificar_recuperacion.php

session_start();
require_once 'includes/db_connect.php'; 
require_once 'includes/config_global.php'; 
require_once 'includes/whatsapp_handler.php'; // Para enviar WhatsApp
require_once 'includes/mailer_handler.php'; // Para enviar Email y plantillas

$method = isset($_GET['method']) ? $_GET['method'] : '';
$identifier = isset($_GET['id']) ? $_GET['id'] : ''; // Email o Teléfono Completo
$user_data = null;
$error_message = '';
$success_message = '';
$token_length = 6;
$is_verification_stage = false;

// 1. Verificar el identificador
if (empty($method) || empty($identifier)) {
    $error_message = "Método o identificador de recuperación no especificado.";
} else {
    $field = ($method == 'email') ? 'email' : 'telefono';
    
    $stmt = $conn->prepare("SELECT id, nombre, email, telefono FROM usuarios WHERE {$field} = ?");
    $stmt->bind_param("s", $identifier);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_data = $result->fetch_assoc();
    $stmt->close();
    
    if (!$user_data) {
        $error_message = "Usuario no encontrado con ese {$method}.";
    }
}

// 2. Lógica de GENERACIÓN Y ENVÍO DE CÓDIGO (Solo si el usuario fue encontrado y no es una verificación)
if ($user_data && !isset($_POST['action'])) {
    
    // Generar un código numérico de 6 dígitos
    $reset_token = str_pad(mt_rand(1, 999999), $token_length, '0', STR_PAD_LEFT);
    // Token expira en 5 minutos
    $expiry_time = date('Y-m-d H:i:s', strtotime('+5 minutes'));
    
    // Guardar el token en la BD
    $stmt_update = $conn->prepare("UPDATE usuarios SET reset_token = ?, reset_expiry = ? WHERE id = ?");
    $stmt_update->bind_param("ssi", $reset_token, $expiry_time, $user_data['id']);
    $stmt_update->execute();
    $stmt_update->close();
    
    // Enviar el código al usuario
    $replacements = [
        'NOMBRE' => $user_data['nombre'],
        'TOKEN' => $reset_token,
        'EXPIRATION' => '5 minutos'
    ];
    $template = getMessageTemplate('PASSWORD_RESET', $replacements);
    $sent = false;

    if ($method == 'email') {
        // Enviar por Email (usando la plantilla HTML)
        $sent = sendEmail($user_data['email'], $template['subject'], $template['body_html']);
        $success_message = $sent ? "✅ Código enviado a su email ({$user_data['email']})." : "❌ Error al enviar email. Revise la configuración SMTP.";
    } elseif ($method == 'phone') {
        // Enviar por WhatsApp (usando la plantilla de WhatsApp)
        $sent = enviarNotificacionWhatsapp($user_data['telefono'], $template['body_whatsapp']);
        $success_message = $sent ? "✅ Código enviado a su WhatsApp ({$user_data['telefono']})." : "❌ Error al enviar WhatsApp. Revise la configuración API.";
    }

    if ($sent) {
        $is_verification_stage = true;
    }
}

// 3. Lógica de VERIFICACIÓN DE CÓDIGO (POST)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'verify') {
    $submitted_token = trim($_POST['reset_token']);
    
    // Volvemos a buscar el usuario y el token
    $field = ($method == 'email') ? 'email' : 'telefono';
    $stmt_verify = $conn->prepare("SELECT id, reset_token, reset_expiry FROM usuarios WHERE {$field} = ?");
    $stmt_verify->bind_param("s", $identifier);
    $stmt_verify->execute();
    $result_verify = $stmt_verify->get_result();
    $user_verify = $result_verify->fetch_assoc();
    $stmt_verify->close();

    if ($user_verify) {
        $now = time();
        $expiry = strtotime($user_verify['reset_expiry']);

        if ($user_verify['reset_token'] == $submitted_token && $now < $expiry) {
            // Código válido y no expirado: Redirigir a restablecer_contrasena.php
            $_SESSION['reset_user_id'] = $user_verify['id'];
            $_SESSION['reset_token_verified'] = $submitted_token;
            
            // Borrar el token de la BD después de verificar (para seguridad)
            $stmt_clear = $conn->prepare("UPDATE usuarios SET reset_token = NULL, reset_expiry = NULL WHERE id = ?");
            $stmt_clear->bind_param("i", $user_verify['id']);
            $stmt_clear->execute();
            
            header("location: restablecer_contrasena.php");
            exit;
        } elseif ($user_verify['reset_token'] != $submitted_token) {
            $error_message = "Código de verificación incorrecto.";
            $is_verification_stage = true; // Mantener la vista de verificación
        } elseif ($now >= $expiry) {
            $error_message = "Código expirado. Por favor, solicite uno nuevo.";
            $is_verification_stage = true;
        }
    } else {
        $error_message = "Error de sesión. Por favor, reinicie el proceso.";
    }
}
// ¡LÍNEA $conn->close(); ELIMINADA AQUÍ!
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación de Código | <?php echo get_config('SITE_NAME'); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style> .recovery-container { max-width: 450px; margin: 50px auto; padding: 30px; background: white; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); } </style>
</head>
<body>
    <?php include 'header_client.php'; ?>

    <main class="container">
        <div class="recovery-container">
            <h2 style="text-align: center; margin-bottom: 20px; color: var(--color-principal);">#️⃣ Ingrese el Código</h2>
            <p style="text-align: center; margin-bottom: 20px;">Hemos enviado un código de 6 dígitos a su **<?php echo $method; ?>**.</p>
            
            <?php if ($success_message): ?><p style="color: green; padding: 10px; background: #d4edda; border-radius: 5px; margin-bottom: 15px;"><?php echo $success_message; ?></p><?php endif; ?>
            <?php if ($error_message): ?><p style="color: red; padding: 10px; background: #f8d7da; border-radius: 5px; margin-bottom: 15px;"><?php echo $error_message; ?></p><?php endif; ?>

            <?php if ($is_verification_stage): ?>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . "?method={$method}&id=" . urlencode($identifier); ?>" method="post">
                <input type="hidden" name="action" value="verify">

                <div class="form-group">
                    <label for="reset_token">Código de 6 dígitos:</label>
                    <input type="text" name="reset_token" id="reset_token" maxlength="6" required placeholder="XXXXXX" style="text-align: center; font-size: 1.5em; letter-spacing: 5px;">
                </div>
                
                <button type="submit" class="btn-primary" style="width: 100%; margin-top: 20px;">Verificar Código</button>
            </form>
            <?php endif; ?>
            
            <p style="text-align: center; margin-top: 15px;"><a href="recuperar_contrasena.php">Solicitar un código nuevo</a></p>
        </div>
    </main>
</body>
</html>