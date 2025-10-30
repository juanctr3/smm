<?php
// Guardar en: smm_panel/recuperar_contrasena.php

session_start();
require_once 'includes/db_connect.php'; 
require_once 'includes/config_global.php';
// Incluimos el gestor de comunicaciones (para la lista de países)
require_once 'includes/whatsapp_handler.php'; 

// Lista de códigos de país comunes
$country_codes = [
    'Colombia (+57)' => '+57', 'México (+52)' => '+52', 'España (+34)' => '+34',
    'Argentina (+54)' => '+54', 'Chile (+56)' => '+56', 'Perú (+51)' => '+51',
    'EE. UU. / Canadá (+1)' => '+1', 'Otro' => '' 
];

$error_message = '';
$success_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $recovery_method = $_POST['recovery_method'];
    $identifier = '';
    $phone_full = '';

    if ($recovery_method == 'email') {
        $identifier = trim($_POST['email']);
    } elseif ($recovery_method == 'phone') {
        $codigo_pais = trim($_POST['codigo_pais']);
        $numero_telefono = trim($_POST['numero_telefono']);
        $phone_full = $codigo_pais . $numero_telefono;
        $identifier = $phone_full; // Usaremos el número completo para la búsqueda
    }
    
    if (empty($identifier)) {
        $error_message = "Por favor, ingresa un valor de recuperación.";
    } else {
        // Redirigimos a la página que genera el código y lo envía.
        header("location: verificar_recuperacion.php?method={$recovery_method}&id=" . urlencode($identifier));
        exit;
    }
}

// ¡LÍNEA $conn->close(); ELIMINADA AQUÍ!
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña | <?php echo get_config('SITE_NAME'); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .recovery-container { max-width: 450px; margin: 50px auto; padding: 30px; background: white; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .telefono-group { display: flex; gap: 10px; }
        .input-hidden { display: none; }
    </style>
</head>
<body>
    <?php include 'header_client.php'; ?>

    <main class="container">
        <div class="recovery-container">
            <h2 style="text-align: center; margin-bottom: 20px; color: var(--color-principal);">🔐 Recuperar Acceso</h2>
            <p style="text-align: center; margin-bottom: 20px;">Selecciona cómo deseas recibir el código de verificación.</p>

            <?php if ($error_message): ?><p style="color: red; padding: 10px; background: #f8d7da; border-radius: 5px; margin-bottom: 15px;"><?php echo $error_message; ?></p><?php endif; ?>
            
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="form-group">
                    <label for="recovery_method">Método de Recuperación:</label>
                    <select name="recovery_method" id="recovery_method" onchange="toggleInputs()">
                        <option value="email">Email</option>
                        <option value="phone">WhatsApp / SMS</option>
                    </select>
                </div>

                <div class="form-group" id="input_email">
                    <label for="email">Ingresa tu Email</label>
                    <input type="email" name="email" id="email" placeholder="tu@correo.com">
                </div>

                <div class="input-hidden" id="input_phone">
                    <label for="numero_telefono">Teléfono (WhatsApp)</label>
                    <div class="telefono-group">
                        <select name="codigo_pais" id="codigo_pais">
                            <?php foreach ($country_codes as $name => $code): ?>
                                <option value="<?php echo htmlspecialchars($code); ?>" <?php echo ($code == '+57' ? 'selected' : ''); ?>>
                                    <?php echo htmlspecialchars($name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="numero_telefono" id="numero_telefono" placeholder="Número sin código">
                    </div>
                </div>
                
                <button type="submit" class="btn-primary" style="width: 100%; margin-top: 20px;">Enviar Código de Acceso</button>
            </form>

            <p style="text-align: center; margin-top: 15px;"><a href="login.php" style="font-weight: bold;">Volver al Login</a></p>
        </div>
    </main>
    
    <script>
        // Función para mostrar/ocultar campos
        function toggleInputs() {
            const method = document.getElementById('recovery_method').value;
            const emailDiv = document.getElementById('input_email');
            const phoneDiv = document.getElementById('input_phone');
            
            if (method === 'email') {
                emailDiv.style.display = 'block';
                phoneDiv.style.display = 'none';
                document.getElementById('email').required = true;
                document.getElementById('numero_telefono').required = false;
            } else {
                emailDiv.style.display = 'none';
                phoneDiv.style.display = 'block';
                document.getElementById('email').required = false;
                document.getElementById('numero_telefono').required = true;
            }
        }
        document.addEventListener('DOMContentLoaded', toggleInputs); // Inicializar
    </script>
</body>
</html>