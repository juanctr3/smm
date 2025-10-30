<?php
// Guardar en: smm_panel/includes/mailer_handler.php

// Incluir la conexión a la base de datos para que get_config() funcione
if (!defined('DB_SERVER')) {
    // Si no está definida (si se llama directamente), asegurar la conexión
    require_once 'db_connect.php'; 
}
require_once 'config_global.php'; 
// NOTA: Para producción, necesitarás instalar PHPMailer y descomentar la sección
// require 'path/to/PHPMailer/PHPMailer.php';
// require 'path/to/PHPMailer/Exception.php';


/**
 * Función centralizada para enviar un email via SMTP.
 * REQUIERE: Instalar y configurar PHPMailer en el servidor.
 * @param string $to_email Email del destinatario.
 * @param string $subject Asunto del correo.
 * @param string $body Contenido HTML/Texto del correo.
 * @return bool True si se envió, False en caso de error.
 */
function sendEmail($to_email, $subject, $body) {
    
    // 1. Obtener Configuración SMTP desde la BD (admin/config.php)
    $host = get_config('SMTP_HOST');
    $user = get_config('SMTP_USER');
    $pass = get_config('SMTP_PASS');
    $port = get_config('SMTP_PORT');
    $from_email = get_config('SMTP_FROM_EMAIL');
    $from_name = get_config('SMTP_FROM_NAME');

    if (empty($host) || empty($user) || empty($from_email)) {
        error_log("Fallo el envío de email: Configuración SMTP incompleta o nula.");
        return false;
    }
    
    // -----------------------------------------------------------
    // --- CÓDIGO PHPMailer REAL IRÍA AQUÍ (ACTUALMENTE PLACEHOLDER) ---
    // -----------------------------------------------------------
    
    // Placeholder de desarrollo:
    // Si la configuración SMTP existe, asumimos que se enviará con éxito en desarrollo.
    // En un entorno real, debes descomentar e implementar PHPMailer aquí.
    error_log("Simulación de envío de Email: Asunto: {$subject} a {$to_email}");
    return true; 
}


// --- 2. Sistema de Plantillas de Mensajes ---
// Un array centralizado para manejar el texto de las plantillas de comunicación.
function getMessageTemplate($key, $replacements = []) {
    
    // Definición de Plantillas
    $templates = [
        'WELCOME_EMAIL' => [
            'subject' => '¡Bienvenido(a) a ' . get_config('SITE_NAME') . '!',
            'body_html' => "Hola [NOMBRE],<br><br>Gracias por registrarte. Tu cuenta ha sido creada:<br>Usuario: <strong>[EMAIL]</strong><br>Contraseña: <strong>[PASSWORD_GENERATED]</strong><br><br>Por favor, inicia sesión y cambia tu contraseña. ¡Te esperamos!",
            'body_whatsapp' => "🎉 ¡Bienvenido(a) [NOMBRE]! Tu cuenta en " . get_config('SITE_NAME') . " está lista. Ingresa con tu Email [EMAIL] y tu contraseña temporal: [PASSWORD_GENERATED]."
        ],
        'ORDER_RECEIVED_WHATSAPP' => [
            'body_whatsapp' => "🛒 Pedido Recibido: Hola [NOMBRE], tu orden #[PEDIDO_ID] por $[COSTO_TOTAL] ha sido creada. Estado: Pendiente de Pago/Procesando. Gracias por tu compra!"
        ],
        'ORDER_COMPLETED_WHATSAPP' => [
            'body_whatsapp' => "🎉 ¡Pedido Completado! Tu orden #[PEDIDO_ID] ([SERVICIO_NOMBRE]) ha sido finalizada con éxito. ¡Califícanos!"
        ],
        'PASSWORD_RESET' => [
            'subject' => 'Código de Acceso para Restablecer Contraseña',
            'body_html' => "Hola [NOMBRE],<br><br>Su código de verificación es: <strong>[TOKEN]</strong>.<br>Este código expirará en [EXPIRATION]. Si no solicitó este cambio, por favor, ignore este mensaje.<br><br>Gracias.",
            'body_whatsapp' => "🚨 Codigo de Acceso: [TOKEN]. Este código de 6 dígitos expira en [EXPIRATION]. No lo comparta con nadie."
        ]
        // Añadir más plantillas (Recarga de Saldo, Notificación de Error, etc.)
    ];

    if (!isset($templates[$key])) {
        return false;
    }

    $template = $templates[$key];
    
    // Reemplazar placeholders en el cuerpo del mensaje
    foreach ($template as $type => $content) {
        // Recorremos los reemplazos y los aplicamos
        foreach ($replacements as $placeholder => $value) {
            $template[$type] = str_replace('[' . strtoupper($placeholder) . ']', $value, $template[$type]);
        }
    }

    return $template;
}
?>