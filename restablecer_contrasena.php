<?php
// Guardar en: smm_panel/restablecer_contrasena.php

session_start();
require_once 'includes/db_connect.php'; 
require_once 'includes/config_global.php';

// Verificar si el usuario ha pasado por la verificación
if (!isset($_SESSION['reset_user_id'])) {
    header("location: recuperar_contrasena.php");
    exit;
}

$user_id = $_SESSION['reset_user_id'];
$error_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if ($password !== $confirm_password) {
        $error_message = "Las contraseñas no coinciden.";
    } elseif (strlen($password) < 6) {
        $error_message = "La contraseña debe tener al menos 6 caracteres.";
    } else {
        $password_hash = password_hash($password, PASSWORD_BCRYPT);
        
        // Actualizar la contraseña
        $stmt = $conn->prepare("UPDATE usuarios SET password_hash = ? WHERE id = ?");
        $stmt->bind_param("si", $password_hash, $user_id);
        
        if ($stmt->execute()) {
            // Éxito: Limpiar la sesión y redirigir al login
            unset($_SESSION['reset_user_id']);
            unset($_SESSION['reset_token_verified']);
            $conn->close();
            header("location: login.php?msg=" . urlencode("✅ Su contraseña ha sido restablecida con éxito. Inicie sesión."));
            exit;
        } else {
            $error_message = "Error al actualizar la contraseña: " . $conn->error;
        }
    }
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer Contraseña | <?php echo get_config('SITE_NAME'); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style> .recovery-container { max-width: 450px; margin: 50px auto; padding: 30px; background: white; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); } </style>
</head>
<body>
    <?php include 'header_client.php'; ?>

    <main class="container">
        <div class="recovery-container">
            <h2 style="text-align: center; margin-bottom: 20px; color: var(--color-principal);">🔑 Nueva Contraseña</h2>
            <p style="text-align: center; margin-bottom: 20px;">Ingrese y confirme su nueva contraseña.</p>

            <?php if ($error_message): ?><p style="color: red; padding: 10px; background: #f8d7da; border-radius: 5px; margin-bottom: 15px;"><?php echo $error_message; ?></p><?php endif; ?>
            
            <form action="restablecer_contrasena.php" method="post">

                <div class="form-group">
                    <label for="password">Nueva Contraseña</label>
                    <input type="password" name="password" id="password" required>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirmar Contraseña</label>
                    <input type="password" name="confirm_password" id="confirm_password" required>
                </div>
                
                <button type="submit" class="btn-primary" style="width: 100%; margin-top: 20px;">Guardar Nueva Contraseña</button>
            </form>
        </div>
    </main>

</body>
</html>