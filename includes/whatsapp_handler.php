<?php
// Guardar en: smm_panel/includes/whatsapp_handler.php

// Incluir la conexión a la base de datos para get_config()
if (!defined('DB_SERVER')) {
    require_once 'db_connect.php'; 
}
require_once 'config_global.php'; 
require_once 'mailer_handler.php'; // Incluye el gestor de plantillas

/**
 * Envía un mensaje de chat simple a través de la API de smsenlinea.com.
 *
 * @param string $numero_destino Número de teléfono del cliente (Ej: +522221234567 o 2221234567).
 * @param string $mensaje El contenido del mensaje de texto.
 * @return bool Retorna true si el envío fue exitoso (código 200), false en caso contrario.
 */
function enviarNotificacionWhatsapp($numero_destino, $mensaje) {
    
    $api_secret = get_config('API_KEY'); 
    $account_id = get_config('API_ACCOUNT_ID');
    $api_url_send = 'https://whatsapp.smsenlinea.com/api/send/whatsapp'; // URL de la API

    // Si la configuración crítica falta, no enviar
    if (empty($api_secret) || empty($account_id) || empty($numero_destino)) {
        error_log("Fallo el envío de WhatsApp: Configuración API o número de destino incompleto.");
        return false;
    }
    
    // Datos requeridos por la API
    $datos = [
        "secret"    => $api_secret,
        "account"   => $account_id,
        "recipient" => $numero_destino,
        "type"      => "text", 
        "message"   => $mensaje,
        "priority"  => 1 // Prioridad alta para notificaciones de código
    ];

    // Inicializar cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url_send);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $datos);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    // Ejecutar la petición
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Analizar la respuesta (asumiendo que 200 es éxito)
    if ($http_code === 200) {
        $respuesta_json = json_decode($response, true);
        if (isset($respuesta_json['status']) && $respuesta_json['status'] === 200) {
            return true;
        }
    }
    
    error_log("Fallo el envío de WhatsApp a {$numero_destino}. HTTP: {$http_code}, Respuesta: {$response}");
    return false;
}

?>