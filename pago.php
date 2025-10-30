<?php
// Guardar en: smm_panel/pago.php

session_start();
// Incluir la conexión y la configuración global
require_once 'includes/db_connect.php'; 
require_once 'includes/config_global.php'; 
// Incluir el gestor de comunicaciones (email y whatsapp)
require_once 'includes/whatsapp_handler.php'; 
require_once 'includes/mailer_handler.php'; 

$pedido_id = isset($_GET['pedido_id']) ? (int)$_GET['pedido_id'] : 0;
$new_user = isset($_GET['new_user']) && $_GET['new_user'] === 'true'; 
$password_generada = isset($_GET['temp_pass']) ? $_GET['temp_pass'] : null; 
$status_message = $status_whatsapp = $status_email = $status_despacho = '';

if ($pedido_id == 0) {
    header("Location: servicios.php?err=" . urlencode("Pedido no especificado."));
    exit;
}

// 1. Obtener detalles del pedido y el usuario
$pedido_data = null;
$stmt = $conn->prepare("
    SELECT 
        p.id, p.costo_total, p.estado, p.detalles_compra_json,
        u.id as user_id, u.email, u.telefono, u.nombre
    FROM pedidos p
    JOIN usuarios u ON p.usuario_id = u.id
    WHERE p.id = ? 
");
$stmt->bind_param("i", $pedido_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $pedido_data = $result->fetch_assoc();
}
$stmt->close();

// Inicializar resumen para la UI
$cart_items_summary = [];
$total_cost = 0;

if ($pedido_data) {
    $total_cost = $pedido_data['costo_total'];
    
    // Decodificar el JSON del carrito para mostrar el resumen
    $detalles_json = $pedido_data['detalles_compra_json'];
    $cart_items_summary = json_decode($detalles_json, true);

    if ($pedido_data['estado'] == 'pendiente_pago') {
        
        // Lógica 1: SIMULAR PAGO Y DESCONTAR SALDO
        $user_id = $pedido_data['user_id'];
        
        // Descontar el costo total del saldo (Simulación de pago exitoso)
        // Usamos una transacción para asegurar la integridad de la operación (RECOMENDADO)
        $conn->begin_transaction();
        
        $stmt_update_saldo = $conn->prepare("UPDATE usuarios SET saldo = saldo - ? WHERE id = ?");
        $stmt_update_saldo->bind_param("di", $total_cost, $user_id);
        
        if ($stmt_update_saldo->execute()) {
            
            // Actualizar estado del pedido: 'procesando'
            $stmt_update_estado = $conn->prepare("UPDATE pedidos SET estado = 'procesando' WHERE id = ?");
            $stmt_update_estado->bind_param("i", $pedido_id);
            $stmt_update_estado->execute();
            
            $conn->commit();
            
            $status_message = "✅ Pago APROBADO (simulación). Pedido #{$pedido_id} marcado como 'procesando' y saldo descontado. <br>";


            // Lógica 2: NOTIFICACIÓN Y BIENVENIDA

            // 2.1 Enviar credenciales (Solo si es nuevo usuario)
            if ($new_user && $password_generada) {
                $replacements = [
                    'NOMBRE' => $pedido_data['nombre'],
                    'EMAIL' => $pedido_data['email'],
                    'PASSWORD_GENERATED' => $password_generada,
                    'SITE_NAME' => get_config('SITE_NAME')
                ];

                $template_email = getMessageTemplate('WELCOME_EMAIL', $replacements);
                $template_whatsapp = getMessageTemplate('WELCOME_EMAIL', $replacements);
                
                // Enviar EMAIL de Bienvenida 
                // NOTA: La función sendEmail es un PLACEHOLDER y siempre retornará TRUE en desarrollo.
                $status_email .= sendEmail($pedido_data['email'], $template_email['subject'], $template_email['body_html']) 
                                ? "✅ Email de bienvenida enviado." 
                                : "❌ Error al enviar email de bienvenida (Revise Config. SMTP).";
                
                // Enviar WHATSAPP de Bienvenida
                $status_whatsapp .= enviarNotificacionWhatsapp($pedido_data['telefono'], $template_whatsapp['body_whatsapp']) 
                                    ? "✅ Credenciales enviadas por WhatsApp." 
                                    : "❌ Error al enviar credenciales por WhatsApp.";
            }

            // 2.2 Notificar la creación del pedido (Para todos los clientes)
            $replacements_pedido = [
                'NOMBRE' => $pedido_data['nombre'],
                'PEDIDO_ID' => $pedido_id,
                'COSTO_TOTAL' => number_format($total_cost, 2)
            ];
            $template_pedido = getMessageTemplate('ORDER_RECEIVED_WHATSAPP', $replacements_pedido);
            
            $status_whatsapp .= (empty($status_whatsapp) ? "" : "<br>") . enviarNotificacionWhatsapp($pedido_data['telefono'], $template_pedido['body_whatsapp']) 
                                ? "✅ Notificación de pedido enviada por WhatsApp." 
                                : "❌ Error al notificar el pedido por WhatsApp.";

            // Lógica 3: DESPACHO (MANUAL)
            $status_despacho = "➡️ Pedido registrado y pendiente de procesamiento manual por el administrador.";

        } else {
            $conn->rollback();
            $status_message = "❌ Error: Falló la actualización del saldo o del estado del pedido. Contacte a soporte.";
        }
        
    } elseif ($pedido_data['estado'] != 'pendiente_pago') {
         $status_message = "⚠️ Error: El pedido #{$pedido_id} ya fue procesado y su estado es '" . ucfirst($pedido_data['estado']) . "'.";
    }

} else {
    $status_message = "❌ Error: Pedido no encontrado.";
}

// La conexión se mantiene abierta para header_client.php
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pago Aprobado | <?php echo get_config('SITE_NAME'); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .summary-table th, .summary-table td { border: 1px solid #ddd; padding: 8px; font-size: 0.9em; }
        .summary-table th { background-color: #f8f9fa; }
    </style>
</head>
<body>
    <?php include 'header_client.php'; ?>

    <main class="container" style="max-width: 900px; margin: 50px auto;">
        <div style="background: #e9f7ef; padding: 30px; border-radius: 10px; border: 1px solid #c8e6c9;">
            <h1 style="color: var(--color-acento); text-align: center;">🎉 ¡Pago Recibido y Pedido Registrado!</h1>
            <p style="text-align: center; font-size: 1.1em; margin-bottom: 30px;">
                Tu orden **#<?php echo $pedido_id; ?>** ha sido aprobada y está en proceso.
            </p>

            <div style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                <h3>Resumen de Pedido</h3>
                <hr>
                
                <table class="summary-table" style="width: 100%; border-collapse: collapse; margin-top: 15px;">
                    <thead>
                        <tr>
                            <th style="text-align: left;">Servicio</th>
                            <th>Cantidad</th>
                            <th style="text-align: right;">Costo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($cart_items_summary): ?>
                            <?php foreach ($cart_items_summary as $item): ?>
                                <tr>
                                    <td style="text-align: left; font-weight: bold;"><?php echo htmlspecialchars($item['nombre']); ?></td>
                                    <td style="text-align: center;"><?php echo number_format($item['cantidad']); ?></td>
                                    <td style="text-align: right;">$<?php echo number_format($item['costo_total'], 2); ?></td>
                                </tr>
                                <tr>
                                    <td colspan="3" style="font-size: 0.75em; color: #6c757d; text-align: left;">
                                        Link: <?php echo htmlspecialchars(substr($item['link_destino'], 0, 70)) . (strlen($item['link_destino']) > 70 ? '...' : ''); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="3" style="text-align: center;">Detalles del pedido no disponibles.</td></tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background-color: #e9f7ef;">
                            <td colspan="2" style="font-weight: bold; text-align: left;">TOTAL FINAL</td>
                            <td style="font-weight: bold; text-align: right;">$<?php echo number_format($total_cost, 2); ?> USD</td>
                        </tr>
                    </tfoot>
                </table>

            </div>

            <div style="background: white; padding: 20px; border-radius: 8px;">
                <h3>Estado de Proceso</h3>
                <hr>
                <p><strong>Estado de Pago/Saldo:</strong> <?php echo $status_message; ?></p>
                <p><strong>Notificación Email:</strong> <?php echo $status_email ?? 'N/A'; ?></p>
                <p><strong>Notificación WhatsApp:</strong> <?php echo $status_whatsapp ?? 'N/A'; ?></p>
                <p><strong>Estado de Despacho:</strong> <?php echo $status_despacho ?? 'N/A'; ?></p>

                <?php if ($new_user): ?>
                    <div style="margin-top: 20px; padding: 10px; border-left: 5px solid var(--color-principal);">
                        <strong>INFORMACIÓN IMPORTANTE:</strong> Hemos creado tu cuenta con tu email. **Revisa tu Email/WhatsApp** para ver la contraseña temporal.
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