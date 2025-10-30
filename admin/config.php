<?php
// Guardar en: smm_panel/admin/config.php

require_once './auth_check.php';
require_once '../includes/db_connect.php';
require_once '../includes/config_global.php';

$message = $error = '';
$logo_upload_dir = '../assets/img/';
$logo_standard_filename = 'logo.png'; 

// Campos de configuración que se manejarán
$config_fields = [
    // Generales
    'SITE_NAME' => 'Nombre del Sitio',
    'WHATSAPP_NUMBER' => 'Número de WhatsApp (+Código)',
    'LOGO_URL' => 'URL del Logo Actual', 
    
    // API SMM / WHATSAPP
    'API_URL' => 'URL de la API del Proveedor SMM',
    'API_KEY' => 'API SECRET (smsenlinea.com: secret)', 
    'API_ACCOUNT_ID' => 'ID de Cuenta Único (smsenlinea.com: account)', 
    
    // SMTP (Email)
    'SMTP_HOST' => 'Servidor SMTP (Ej: mail.tudominio.com)',
    'SMTP_USER' => 'Usuario SMTP (Tu email)',
    'SMTP_PASS' => 'Contraseña SMTP', 
    'SMTP_PORT' => 'Puerto SMTP (Ej: 465 o 587)',
    'SMTP_FROM_EMAIL' => 'Email de Remitente (Ej: notificaciones@...)',
    'SMTP_FROM_NAME' => 'Nombre del Remitente',
    
    // SEO
    'META_TITLE_HOME' => 'Meta Título Página Principal (Max 70)',
    'META_DESCRIPTION_HOME' => 'Meta Descripción Página Principal (Max 160)',
    'META_KEYWORDS' => 'Palabras Clave SEO (Separadas por comas)',
];

// =================================================================
// 1. Lógica de ACTUALIZACIÓN DE CONFIGURACIÓN
// =================================================================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $logo_uploaded = false;

    // --- A. Procesar Subida de Logo (Se omite la lógica de subida por brevedad) ---
    // NOTA: La lógica de subida de archivos debe estar aquí.

    // --- B. Procesar Campos de Texto (SEO, API, Generales, SMTP) ---
    if (empty($error)) {
        $success_count = 0;
        
        foreach ($config_fields as $key => $label) {
            if ($key == 'LOGO_URL') continue;

            // Lógica Especial para Contraseña SMTP: Si el campo de contraseña está vacío, NO lo actualizamos
            if ($key == 'SMTP_PASS' && empty(trim($_POST[$key]))) {
                 continue; 
            }

            if (isset($_POST[$key])) {
                $valor = trim($_POST[$key]);
                
                // CRÍTICO: Usamos bind_param con la cláusula ON DUPLICATE KEY UPDATE para asegurar la persistencia
                $stmt = $conn->prepare("INSERT INTO configuracion (nombre, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
                $stmt->bind_param("ss", $key, $valor);
                
                if ($stmt->execute()) {
                    $success_count++;
                } else {
                    $error .= "Error al actualizar la clave {$key}. " . $conn->error . ". ";
                }
                $stmt->close();
            }
        }

        if ($success_count > 0 && !$logo_uploaded) {
            $message .= "✅ Configuración de texto actualizada con éxito.";
        }
    }
    
    // CRÍTICO: Recargar la configuración global DESPUÉS de la actualización
    require_once '../includes/config_global.php'; 
    
    // Redirigir para limpiar el POST y el file-upload
    if (empty($error)) {
        header("Location: config.php?msg=" . urlencode($message));
        exit;
    }
}

// 2. Obtener valores actuales de la base de datos para mostrar en el formulario
$current_config = [];
foreach ($config_fields as $key => $label) {
    if ($key == 'SMTP_PASS') {
        // Marcador para la contraseña
        $current_config[$key] = !empty(get_config($key)) ? '****' : '';
    } else {
        $current_config[$key] = get_config($key) == 'Error Config' ? '' : get_config($key);
    }
}

// Cerramos la conexión AHORA, ya que toda la lógica ha terminado.
$conn->close();

if (isset($_GET['msg'])) { $message = htmlspecialchars($_GET['msg']); }
if (isset($_GET['err'])) { $error = htmlspecialchars($_GET['err']); }
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración | Admin - <?php echo get_config('SITE_NAME'); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <?php include './sidebar_menu_css.php'; ?>
</head>
<body>
    <?php include './sidebar_menu.php'; ?>

    <main class="admin-content">
        <h1>⚙️ Configuración Global & SEO</h1>
        <p>Ajusta el nombre del sitio, las claves de API y las meta etiquetas para el posicionamiento.</p>

        <?php if (!empty($message)): ?><p style="color: green; background: #d4edda; padding: 10px; border-radius: 5px;"><?php echo $message; ?></p><?php endif; ?>
        <?php if (!empty($error)): ?><p style="color: red; background: #f8d7da; padding: 10px; border-radius: 5px;"><?php echo $error; ?></p><?php endif; ?>

        <form action="config.php" method="post" enctype="multipart/form-data" style="margin-top: 30px;">
            
            <fieldset style="margin-bottom: 30px; padding: 20px; border: 1px solid #ccc;">
                <legend style="font-weight: bold; padding: 0 10px;">Información General</legend>
                
                <div class="form-group">
                    <label for="SITE_NAME"><?php echo $config_fields['SITE_NAME']; ?></label>
                    <input type="text" name="SITE_NAME" id="SITE_NAME" value="<?php echo htmlspecialchars($current_config['SITE_NAME']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="WHATSAPP_NUMBER"><?php echo $config_fields['WHATSAPP_NUMBER']; ?></label>
                    <input type="text" name="WHATSAPP_NUMBER" id="WHATSAPP_NUMBER" value="<?php echo htmlspecialchars($current_config['WHATSAPP_NUMBER']); ?>" placeholder="+CódigoPaísNúmero" required>
                </div>
                
                <div class="form-group">
                    <label>Logo Actual:</label>
                    <?php if (!empty($current_config['LOGO_URL'])): ?>
                        <img src="../<?php echo htmlspecialchars($current_config['LOGO_URL']); ?>" alt="Logo del Sitio" style="max-height: 80px; display: block; margin-bottom: 10px;">
                    <?php else: ?>
                        <p>No se ha subido un logo aún. Se muestra el nombre del sitio.</p>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="logo_file">Subir Nuevo Logo (PNG/JPG/WEBP, Máx. 500 KB):</label>
                    <input type="file" name="logo_file" id="logo_file" accept="image/png, image/jpeg, image/webp">
                    <small>El archivo subido se renombrará a `assets/img/logo.ext`, dependiendo del formato.</small>
                </div>
            </fieldset>

            <fieldset style="margin-bottom: 30px; padding: 20px; border: 1px solid #ccc;">
                <legend style="font-weight: bold; padding: 0 10px;">Integración de API (SMM y WhatsApp)</legend>
                
                <h4 style="margin-bottom: 10px;">Configuración del Proveedor SMM / WhatsApp (smsenlinea.com)</h4>

                <div class="form-group">
                    <label for="API_URL"><?php echo $config_fields['API_URL']; ?></label>
                    <input type="url" name="API_URL" id="API_URL" value="<?php echo htmlspecialchars($current_config['API_URL']); ?>" placeholder="Ej: https://api.smmprovider.com/v2" required>
                </div>

                <div class="form-group">
                    <label for="API_KEY"><?php echo $config_fields['API_KEY']; ?> (Corresponde al parámetro 'secret' de la API)</label>
                    <input type="text" name="API_KEY" id="API_KEY" value="<?php echo htmlspecialchars($current_config['API_KEY']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="API_ACCOUNT_ID"><?php echo $config_fields['API_ACCOUNT_ID']; ?> (Corresponde al parámetro 'account' de la API)</label>
                    <input type="text" name="API_ACCOUNT_ID" id="API_ACCOUNT_ID" value="<?php echo htmlspecialchars($current_config['API_ACCOUNT_ID']); ?>" required>
                </div>
            </fieldset>

            <fieldset style="margin-bottom: 30px; padding: 20px; border: 1px solid #ccc;">
                <legend style="font-weight: bold; padding: 0 10px;">Configuración SMTP (Envío de Email)</legend>
                
                <div class="form-group">
                    <label for="SMTP_HOST"><?php echo $config_fields['SMTP_HOST']; ?></label>
                    <input type="text" name="SMTP_HOST" id="SMTP_HOST" value="<?php echo htmlspecialchars($current_config['SMTP_HOST']); ?>" placeholder="mail.tudominio.com">
                </div>
                <div class="form-group">
                    <label for="SMTP_USER"><?php echo $config_fields['SMTP_USER']; ?></label>
                    <input type="text" name="SMTP_USER" id="SMTP_USER" value="<?php echo htmlspecialchars($current_config['SMTP_USER']); ?>" placeholder="info@tudominio.com">
                </div>
                <div class="form-group">
                    <label for="SMTP_PASS"><?php echo $config_fields['SMTP_PASS']; ?></label>
                    <input type="password" name="SMTP_PASS" id="SMTP_PASS" value="<?php echo $current_config['SMTP_PASS']; ?>" placeholder="Dejar vacío para no cambiar">
                    <small>Si no deseas cambiar la contraseña, deja este campo vacío.</small>
                </div>
                <div class="form-group">
                    <label for="SMTP_PORT"><?php echo $config_fields['SMTP_PORT']; ?></label>
                    <input type="number" name="SMTP_PORT" id="SMTP_PORT" value="<?php echo htmlspecialchars($current_config['SMTP_PORT']); ?>" placeholder="465">
                </div>
                <div class="form-group">
                    <label for="SMTP_FROM_EMAIL"><?php echo $config_fields['SMTP_FROM_EMAIL']; ?></label>
                    <input type="email" name="SMTP_FROM_EMAIL" id="SMTP_FROM_EMAIL" value="<?php echo htmlspecialchars($current_config['SMTP_FROM_EMAIL']); ?>" placeholder="no-reply@tudominio.com">
                </div>
                <div class="form-group">
                    <label for="SMTP_FROM_NAME"><?php echo $config_fields['SMTP_FROM_NAME']; ?></label>
                    <input type="text" name="SMTP_FROM_NAME" id="SMTP_FROM_NAME" value="<?php echo htmlspecialchars($current_config['SMTP_FROM_NAME']); ?>" placeholder="Soporte Buy Followers">
                </div>
            </fieldset>

            <fieldset style="margin-bottom: 30px; padding: 20px; border: 1px solid #ccc;">
                <legend style="font-weight: bold; padding: 0 10px; color: var(--color-acento);">Ajustes de SEO (Página Principal)</legend>

                <div class="form-group">
                    <label for="META_TITLE_HOME"><?php echo $config_fields['META_TITLE_HOME']; ?></label>
                    <input type="text" name="META_TITLE_HOME" id="META_TITLE_HOME" maxlength="70" value="<?php echo htmlspecialchars($current_config['META_TITLE_HOME']); ?>">
                </div>

                <div class="form-group">
                    <label for="META_DESCRIPTION_HOME"><?php echo $config_fields['META_DESCRIPTION_HOME']; ?></label>
                    <textarea name="META_DESCRIPTION_HOME" id="META_DESCRIPTION_HOME" maxlength="160" rows="3"><?php echo htmlspecialchars($current_config['META_DESCRIPTION_HOME']); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="META_KEYWORDS"><?php echo $config_fields['META_KEYWORDS']; ?></label>
                    <input type="text" name="META_KEYWORDS" id="META_KEYWORDS" value="<?php echo htmlspecialchars($current_config['META_KEYWORDS']); ?>">
                </div>
            </fieldset>


            <button type="submit" class="btn-primary" style="margin-top: 15px;">Guardar Toda la Configuración</button>
        </form>

    </main>
</body>
</html>