<?php
// =================================================================
// CONFIGURACIÓN DE LA API DE WHATSAPP (A completar por el administrador)
// =================================================================
define('SMSENLINEA_API_URL_SEND', 'https://whatsapp.smsenlinea.com/api/send/whatsapp');
define('SMSENLINEA_API_SECRET', 'TU_API_SECRET_DE_SMSENLINEA'); // Clave secreta
define('SMSENLINEA_ACCOUNT_ID', 'WHATSAPP_ACCOUNT_UNIQUE_ID'); // ID de tu cuenta de WhatsApp en smsenlinea.com

/**
 * Envía un mensaje de chat simple a través de la API de smsenlinea.com.
 *
 * @param string $numero_destino Número de teléfono del cliente (Ej: +522221234567 o 2221234567).
 * @param string $mensaje El contenido del mensaje de texto.
 * @param int $prioridad 1 para enviar inmediatamente (priority), 2 para cola normal.
 * @return bool Retorna true si el envío fue exitoso (código 200), false en caso contrario.
 */
function enviarNotificacionWhatsapp($numero_destino, $mensaje, $prioridad = 2) {
    // Datos requeridos por la API Send Single Chat
    $datos = [
        "secret"    => SMSENLINEA_API_SECRET,
        "account"   => SMSENLINEA_ACCOUNT_ID,
        "recipient" => $numero_destino,
        "type"      => "text", // Usamos 'text' para mensajes de chat simples
        "message"   => $mensaje,
        "priority"  => $prioridad 
    ];

    // Inicializar cURL
    $ch = curl_init();

    // Configurar cURL
    curl_setopt($ch, CURLOPT_URL, SMSENLINEA_API_URL_SEND);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $datos); // cURL gestiona multipart/form-data con un array
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    // Ejecutar la petición
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // Cerrar cURL
    curl_close($ch);

    // Analizar y manejar la respuesta
    if ($http_code === 200) {
        $respuesta_json = json_decode($response, true);
        if ($respuesta_json['status'] === 200) {
            // Éxito: El mensaje fue enviado o puesto en cola
            // Opcional: Registrar el messageId para seguimiento
            return true;
        }
    }
    
    // Fallo: error de HTTP o error en la respuesta JSON
    error_log("Fallo el envío de WhatsApp a {$numero_destino}. HTTP: {$http_code}, Respuesta: {$response}");
    return false;
}

// =================================================================
// Función de ejemplo para Notificación de Pedido Completado
// =================================================================
function notificarPedidoCompletado($pedido_id, $nombre_cliente, $numero_cliente, $servicio) {
    // URL amigable para que el cliente califique el servicio
    $url_calificacion = "http://tudominio.com/review?pedido={$pedido_id}";

    $mensaje = "Hola {$nombre_cliente}, ¡tenemos buenas noticias! 🎉 \n";
    $mensaje .= "Tu pedido #{$pedido_id} del servicio '{$servicio}' ha sido COMPLETADO exitosamente.\n";
    $mensaje .= "Esperamos que estés satisfecho. ¡Tu opinión es importante! Califícanos aquí: {$url_calificacion}";

    // Enviar con prioridad 1 para notificación inmediata
    return enviarNotificacionWhatsapp($numero_cliente, $mensaje, 1);
}

// ** NOTA: ** Este archivo debe ser incluido en tu lógica de pedidos del Panel Admin.
?>
