<?php
// Guardar en: smm_panel/admin/pedidos.php

require_once './auth_check.php';
require_once '../includes/db_connect.php';
require_once '../includes/config_global.php';
// Requerir el gestor de WhatsApp (necesario para la lógica de procesamiento)
require_once '../includes/whatsapp_handler.php'; 

$message = $error = '';

// Array de estados para el selector
$estados_disponibles = ['pendiente', 'procesando', 'completado', 'cancelado', 'error'];

// =================================================================
// 1. LÓGICA DE PROCESAMIENTO DE PEDIDOS (CAMBIO DE ESTADO)
// =================================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'update_status') {
    
    $pedido_id = (int)$_POST['pedido_id'];
    $nuevo_estado = $_POST['nuevo_estado'];
    $whatsapp_enviado = false;

    if (!in_array($nuevo_estado, $estados_disponibles)) {
        $error = "Estado inválido.";
    } else {
        // --- Lógica para obtener datos del cliente y pedido desde la BD --- 
        $stmt_data = $conn->prepare("
            SELECT 
                p.usuario_id, u.nombre, u.telefono, s.nombre AS servicio_nombre 
            FROM pedidos p
            JOIN usuarios u ON p.usuario_id = u.id
            JOIN servicios s ON p.servicio_id = s.id
            WHERE p.id = ?
        ");
        $stmt_data->bind_param("i", $pedido_id);
        $stmt_data->execute();
        $result_data = $stmt_data->get_result();
        $datos_pedido = $result_data->fetch_assoc();
        $stmt_data->close();

        if ($datos_pedido) {
            $nombre_cliente = $datos_pedido['nombre'];
            $numero_cliente = $datos_pedido['telefono'];
            $servicio_nombre = $datos_pedido['servicio_nombre'];

            // 1. Actualizar el estado en la base de datos
            $stmt_update = $conn->prepare("UPDATE pedidos SET estado = ? WHERE id = ?");
            $stmt_update->bind_param("si", $nuevo_estado, $pedido_id);
            $stmt_update->execute();
            $stmt_update->close();
            
            $message = "Estado del pedido #{$pedido_id} actualizado a '" . ucfirst($nuevo_estado) . "'.";

            // 2. Enviar notificación por WhatsApp si está completado
            if ($nuevo_estado === 'completado') {
                $api_secret = get_config('API_KEY'); 
                $account_id = get_config('API_ACCOUNT_ID'); // Asume que esta config existe
                
                $msg = "🎉 ¡Pedido Completado! Tu orden #{$pedido_id} ({$servicio_nombre}) ha sido finalizada con éxito. ¡Califícanos!";
                
                if (enviarNotificacionWhatsapp($numero_cliente, $msg, $api_secret, $account_id, 1)) {
                    $whatsapp_enviado = true;
                    $message .= " ✅ WhatsApp enviado al cliente.";
                } else {
                    $error = "Pedido completado, pero ❌ Error al enviar notificación de WhatsApp.";
                }
            }

        } else {
            $error = "Pedido no encontrado.";
        }
    }
}

// 2. OBTENER LISTA DE PEDIDOS para la tabla
$pedidos_result = $conn->query("
    SELECT 
        p.id, p.costo_total, p.cantidad, p.link_destino, p.estado, p.fecha_creacion,
        u.email AS cliente_email,
        s.nombre AS servicio_nombre
    FROM pedidos p
    JOIN usuarios u ON p.usuario_id = u.id
    JOIN servicios s ON p.servicio_id = s.id
    ORDER BY p.fecha_creacion DESC
");

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Pedidos | <?php echo get_config('SITE_NAME'); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <?php include './sidebar_menu_css.php'; ?>
    <style>
        .status-pendiente { color: orange; font-weight: bold; }
        .status-procesando { color: #007bff; font-weight: bold; }
        .status-completado { color: green; font-weight: bold; }
        .status-cancelado, .status-error { color: red; font-weight: bold; }
        .pedido-row td { padding: 10px; border-bottom: 1px solid #eee; }
    </style>
</head>
<body>
    <?php include './sidebar_menu.php'; ?>

    <main class="admin-content">
        <h1>🛒 Gestión de Pedidos</h1>
        <p>Revisa y actualiza el estado de los pedidos realizados por tus clientes.</p>

        <?php if (!empty($message)): ?><p style="color: green; background: #d4edda; padding: 10px; border-radius: 5px;"><?php echo $message; ?></p><?php endif; ?>
        <?php if (!empty($error)): ?><p style="color: red; background: #f8d7da; padding: 10px; border-radius: 5px;"><?php echo $error; ?></p><?php endif; ?>

        <h2>Lista de Pedidos</h2>
        <table style="width: 100%; border-collapse: collapse; background: white; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <thead>
                <tr style="background: #f8f9fa;">
                    <th style="padding: 15px; text-align: left;">ID</th>
                    <th style="text-align: left;">Cliente / Email</th>
                    <th style="text-align: left;">Servicio</th>
                    <th style="text-align: center;">Cantidad</th>
                    <th style="text-align: center;">Total</th>
                    <th style="text-align: center;">Estado</th>
                    <th style="text-align: left;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($pedidos_result && $pedidos_result->num_rows > 0): ?>
                    <?php while($row = $pedidos_result->fetch_assoc()): ?>
                    <tr class="pedido-row">
                        <td>#<?php echo $row['id']; ?></td>
                        <td><?php echo htmlspecialchars($row['cliente_email']); ?></td>
                        <td><?php echo htmlspecialchars($row['servicio_nombre']); ?></td>
                        <td style="text-align: center;"><?php echo number_format($row['cantidad']); ?></td>
                        <td style="text-align: center;">$<?php echo number_format($row['costo_total'], 2); ?></td>
                        <td style="text-align: center;" class="status-<?php echo strtolower($row['estado']); ?>">
                            <?php echo ucfirst($row['estado']); ?>
                        </td>
                        <td>
                            <form action="pedidos.php" method="post" style="display: flex; gap: 5px;">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="pedido_id" value="<?php echo $row['id']; ?>">
                                
                                <select name="nuevo_estado" style="padding: 5px; border-radius: 3px;">
                                    <?php foreach ($estados_disponibles as $estado): ?>
                                        <option value="<?php echo $estado; ?>" <?php if ($estado == $row['estado']) echo 'selected'; ?>>
                                            <?php echo ucfirst($estado); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn-primary" style="padding: 5px 10px; font-size: 0.8em; background: #007bff;">
                                    Actualizar
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7" style="text-align: center; padding: 20px;">No hay pedidos registrados en el sistema.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

    </main>
</body>
</html>