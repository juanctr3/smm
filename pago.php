<?php
// Guardar en: smm_panel/pago.php

session_start();
// Incluir la conexión y la configuración global (para SITE_NAME, API_KEY, etc.)
require_once 'includes/db_connect.php'; 
require_once 'includes/config_global.php'; 
// Incluir el handler de WhatsApp
require_once 'includes/whatsapp_handler.php'; 

$pedido_id = isset($_GET['pedido_id']) ? (int)$_GET['pedido_id'] : 0;
$new_user = isset($_GET['new_user']) && $_GET['new_user'] === 'true'; // Se usa para saber si se envía la contraseña
$status_message = $status_whatsapp = $status_api = '';

if ($pedido_id == 0) {
    header("Location: servicios.php");
    exit;
}

// 1. Obtener todos los detalles del pedido y el usuario
$pedido_data = null;
$user_data = null;
$password_generada = null; // Se asume que la contraseña generada está en sesión (MEJORA: debería estar en la BD temporalmente)

$stmt = $conn->prepare("
    SELECT 
        p.id, p.costo_total, p.link_destino, p.cantidad, p.servicio_id, 
        u.id as user_id, u.email, u.telefono, s.nombre as servicio_nombre
    FROM pedidos p
    JOIN usuarios u ON p.usuario_id = u.id
    JOIN servicios s ON p.servicio_id = s.id
    WHERE p.id = ? AND p.estado = 'pendiente_pago'
");
$stmt->bind_param("i", $pedido_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $pedido_data = $result->fetch_assoc();
}
$stmt->close();


// *** SIMULACIÓN DE PAGO APROBADO ***
if ($pedido_data) {
    
    // Lógica 1: SIMULAR PAGO Y DESCONTAR SALDO (En un entorno real, esto lo hace la pasarela)
    // Para el ejemplo, asumimos que el pago ha añadido $1000 al saldo del usuario
    $monto_pagado = $pedido_data['costo_total'];
    $conn->query("UPDATE usuarios SET saldo = saldo + $monto_pagado WHERE id = " . $pedido_data['user_id']);
    
    // Descontar el costo total del saldo
    $conn->query("UPDATE usuarios SET saldo = saldo - " . $pedido_data['costo_total'] . " WHERE id = " . $pedido_data['user_id']);
    
    // Actualizar estado del pedido
    $conn->query("UPDATE pedidos SET estado = 'procesando' WHERE id = " . $pedido_id);
    $status_message .= "✅ Pago aprobado. Pedido {$pedido_id} marcado como 'procesando'. <br>";


    // Lógica 2: NOTIFICACIÓN POR WHATSAPP (Credenciales y Pedido)

    $api_secret = get_config('API_KEY'); 
    $account_id = get_config('API_ACCOUNT_ID'); // ASUME que tienes esta config en la BD
    
    // 2.1 Enviar credenciales (Solo si es nuevo usuario)
    if ($new_user && $api_secret) {
        $msg_creds = "¡Bienvenido a ". get_config('SITE_NAME') ."! 🎉\nTu cuenta fue creada:\nUsuario: " . $pedido_data['email'] . "\nContraseña: [Contraseña enviada al crear el pedido]. Por favor, revísala y cámbiala.";
        
        // Asumiendo que guardaste la contraseña generada en algún lugar seguro para enviarla
        // $status_whatsapp .= enviarNotificacionWhatsapp($pedido_data['telefono'], $msg_creds, $api_secret, $account_id) ? "✅ Credenciales enviadas por WhatsApp." : "❌ Error al enviar credenciales por WhatsApp.";
        $status_whatsapp .= "✅ Credenciales simuladas (Recuerda implementar el envío real de la contraseña generada).";
    }

    // 2.2 Notificar la creación del pedido (Para todos los clientes)
    if ($api_secret) {
        $msg_pedido = "🛒 Pedido Recibido:\n#{$pedido_id} de {$pedido_data['servicio_nombre']}.\nEstado: Procesando. Total: $" . number_format($pedido_data['costo_total'], 2) . " USD.";
        $status_whatsapp .= enviarNotificacionWhatsapp($pedido_data['telefono'], $msg_pedido, $api_secret, $account_id) ? "<br>✅ Notificación de pedido enviada por WhatsApp." : "<br>❌ Error al notificar el pedido por WhatsApp.";
    }


    // Lógica 3: LLAMADA A LA API DE SMSENLINEA.COM (Despacho)
    $api_key = get_config('API_KEY');
    $api_url = get_config('API_URL');
    
    if ($api_key && $api_url) {
        // En un caso real, el $service_id debe mapearse a un ID de servicio del proveedor.
        $provider_service_id = 999; // ID de ejemplo del proveedor

        $api_data = [
            'key' => $api_key,
            'action' => 'add',
            'service' => $provider_service_id, 
            'link' => $pedido_data['link_destino'],
            'quantity' => $pedido_data['cantidad']
        ];
        
        // Simulación de llamada a la API
        // $response = call_smm_api($api_url, $api_data);
        $status_api = "✅ Llamada a API de Proveedor Simulada. (Implementar la función real de cURL).";

        // Si la llamada real retorna un 'order_id' del proveedor:
        // $conn->query("UPDATE pedidos SET proveedor_api_id = '{$response['order_id']}' WHERE id = {$pedido_id}");

    } else {
        $status_api = "❌ Falta configurar API Key o URL. Pedido NO despachado automáticamente.";
    }

} else {
    $status_message = "❌ Error: Pedido no encontrado o ya procesado.";
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pago Aprobado | <?php echo get_config('SITE_NAME'); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'header_client.php'; ?>

    <main class="container" style="max-width: 800px; margin: 50px auto;">
        <div style="background: #e9f7ef; padding: 30px; border-radius: 10px; border: 1px solid #c8e6c9;">
            <h1 style="color: var(--color-acento); text-align: center;">🎉 ¡Pago Recibido y Pedido en Proceso!</h1>
            <p style="text-align: center; font-size: 1.1em; margin-bottom: 30px;">
                Tu orden **#<?php echo $pedido_id; ?>** ha sido aprobada.
            </p>

            <div style="background: white; padding: 20px; border-radius: 8px;">
                <h3>Resumen de Proceso</h3>
                <hr>
                <p><strong>Estado de Pago:</strong> <?php echo $status_message; ?></p>
                <p><strong>Notificación WhatsApp:</strong> <?php echo $status_whatsapp; ?></p>
                <p><strong>Despacho API:</strong> <?php echo $status_api; ?></p>

                <?php if ($new_user): ?>
                    <div style="margin-top: 20px; padding: 10px; border-left: 5px solid var(--color-principal);">
                        <strong>INFORMACIÓN IMPORTANTE:</strong> Hemos creado tu cuenta con tu email. **Revisa tu WhatsApp** para ver la contraseña temporal.
                    </div>
                <?php endif; ?>
            </div>
            
            <p style="text-align: center; margin-top: 30px;">
                <a href="cuenta.php" class="btn-primary">Ir a Mis Pedidos</a>
            </p>
        </div>
    </main>
    
    <a href="https://wa.me/<?php echo get_config('WHATSAPP_NUMBER'); ?>" class="whatsapp-float" target="_blank">💬</a>
</body>
</html>