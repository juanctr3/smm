<?php
// Sustituye con tus credenciales reales de smsenlinea.com
define('SMSENLINEA_API_URL', 'URL_PANEL_1_WHATSAPP'); 
define('SMSENLINEA_TOKEN', 'TU_TOKEN_CLIENTE_PANEL_1'); 

function enviarNotificacionWhatsapp($numero_destino, $mensaje) {
    // La API de smsenlinea.com requiere que el mensaje sea una PLANTILLA APROBADA
    // Debes sustituir 'template_id' y 'parametros' con los datos de tu plantilla.
    $datos = [
        'token'         => SMSENLINEA_TOKEN,
        'telefono'      => $numero_destino, // Formato E.164 (ej: 573001234567)
        'template_id'   => 'id_de_tu_plantilla_aprobada',
        'parametros'    => [ // Los datos variables de la plantilla
            "1" => $mensaje // o un array si la plantilla usa varios parámetros
        ]
    ];

    $ch = curl_init(SMSENLINEA_API_URL . '/enviar_whatsapp'); 
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($datos));
    
    $respuesta = curl_exec($ch);
    curl_close($ch);

    // Lógica para registrar si el envío fue exitoso o falló
    if (json_decode($respuesta)->status === 'success') {
        return true;
    } else {
        error_log("Fallo el envío de WhatsApp: " . $respuesta);
        return false;
    }
}
// Ejemplo de uso:
// $resultado = enviarNotificacionWhatsapp('573001234567', '¡Pedido #1234 completado!');
?>
