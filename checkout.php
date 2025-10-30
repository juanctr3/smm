<?php
// Guardar en: smm_panel/checkout.php

session_start();
require_once 'includes/db_connect.php';
require_once 'includes/config_global.php';

// Incluimos el gestor de plantillas
require_once 'includes/mailer_handler.php'; 

// Lista de códigos de país comunes
$country_codes = [
    'Colombia (+57)' => '+57', 'México (+52)' => '+52', 'España (+34)' => '+34',
    'Argentina (+54)' => '+54', 'Chile (+56)' => '+56', 'Perú (+51)' => '+51',
    'EE. UU. / Canadá (+1)' => '+1', 'Otro' => '' 
];

// Redirigir si no hay datos de pedido
if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    header("Location: servicios.php?err=" . urlencode("El carrito está vacío. Añade un servicio para continuar."));
    exit;
}

// 1. VALIDAR Y CALCULAR TOTALES DEL CARRITO
$cart_items = $_SESSION['cart'];
$total_cost = 0;
$item_summary_details = [];

// Bucle para calcular totales y crear resumen de ítems
foreach ($cart_items as $item) {
    $item_cost = is_numeric($item['costo_total']) ? (float)$item['costo_total'] : 0;
    
    $total_cost += $item_cost;
    
    $item_summary_details[] = [
        'nombre' => $item['nombre'],
        'cantidad' => $item['cantidad'],
        'link_destino' => $item['link_destino'],
        'costo' => $item_cost
    ];
}

$costo_total = round($total_cost, 2); 

$error_message = '';
$password_generada = null; 
$usuario_id = 0;
$email_recuperado = ''; 

// 2. Lógica de PROCESAR CHECKOUT (se ejecutará al enviar el formulario de datos personales)
if (isset($_POST['action']) && $_POST['action'] == 'confirm_order') {
    
    $email = trim($_POST['email']);
    $codigo_pais = trim($_POST['codigo_pais']); 
    $numero_telefono = trim($_POST['numero_telefono']); 
    
    $email_recuperado = $email;
    $telefono = $codigo_pais . $numero_telefono; 
    
    // Validación mínima
    if (empty($email) || empty($numero_telefono) || empty($codigo_pais)) {
        $error_message = "Por favor, completa el email y el teléfono con código de país.";
    } else {

        $password_temporal = substr(md5(rand()), 0, 8);
        $password_hash = password_hash($password_temporal, PASSWORD_BCRYPT);
        $nombre_usuario = explode('@', $email)[0];

        // *** A. IDENTIFICACIÓN Y REGISTRO AUTOMÁTICO ***
        $stmt_check = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stmt_check->bind_param("s", $email);
        $stmt_check->execute();
        $stmt_check->store_result();
        
        if ($stmt_check->num_rows == 0) {
            // Cliente NO existe: CREAR NUEVA CUENTA
            $stmt_insert = $conn->prepare("INSERT INTO usuarios (nombre, email, telefono, password_hash, saldo, rol) VALUES (?, ?, ?, ?, 0.00, 'cliente')");
            $stmt_insert->bind_param("ssss", $nombre_usuario, $email, $telefono, $password_hash);
            
            if ($stmt_insert->execute()) {
                $usuario_id = $conn->insert_id;
                $password_generada = $password_temporal; // Contraseña para la notificación
            } else {
                $error_message = "Error al crear la cuenta: " . $conn->error;
            }
        } else {
            // Cliente SÍ existe: IDENTIFICACIÓN SILENCIOSA
            $stmt_check->bind_result($usuario_id);
            $stmt_check->fetch();
            // NOTA: Si ya existe, no se genera ni se envía contraseña.
        }
        
        $stmt_check->close();
    }

    // *** B. CREACIÓN DEL PEDIDO (si la identificación/registro fue exitosa) ***
    if ($usuario_id > 0 && empty($error_message)) {
        
        $detalles_compra_json = json_encode($cart_items);
        $servicio_id_dummy = 0; 
        $link_destino_dummy = ''; 
        $cantidad_dummy = 0; 
        
        // El pedido se registra con el costo total y el detalle JSON del carrito
        $stmt_pedido = $conn->prepare("INSERT INTO pedidos (usuario_id, servicio_id, link_destino, cantidad, costo_total, estado, detalles_compra_json) VALUES (?, ?, ?, ?, ?, 'pendiente_pago', ?)");
        
        $stmt_pedido->bind_param("iisids", 
            $usuario_id, 
            $servicio_id_dummy, 
            $link_destino_dummy, 
            $cantidad_dummy, 
            $costo_total, 
            $detalles_compra_json
        );
        $stmt_pedido->execute();
        $pedido_id = $conn->insert_id;
        $stmt_pedido->close();

        // 5. Limpiar Carrito y Redirigir al Pago
        unset($_SESSION['cart']); 

        $redirect_url = "pago.php?pedido_id=" . $pedido_id;
        if ($password_generada) {
            // Solo pasamos la contraseña si se acaba de crear el usuario
            $redirect_url .= "&new_user=true&temp_pass=" . urlencode($password_generada);
        }
        header("Location: " . $redirect_url);
        exit;
    }
}

// ¡LÍNEA $conn->close(); ELIMINADA para que header_client.php pueda usar la conexión!
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout | Confirmar Pedido</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .checkout-box {
            max-width: 800px;
            margin: 50px auto;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .summary-table th, .summary-table td { border: 1px solid #ddd; padding: 8px; font-size: 0.9em; }
        .summary-table th { background-color: #f8f9fa; }
    </style>
</head>
<body>
    <?php include 'header_client.php'; ?>

    <main class="container">
        <div class="checkout-box">
            <h1 style="color: var(--color-principal);">🛒 Finalizar Compra Rápida</h1>

            <div class="summary">
                <h4>Resumen del Carrito</h4>
                <table class="summary-table" style="width: 100%; border-collapse: collapse; margin-bottom: 10px; font-size: 0.9em;">
                    <thead>
                        <tr style="background-color: #f8f9fa;">
                            <th style="padding: 10px; text-align: left;">Servicio</th>
                            <th style="padding: 10px; border: 1px solid #ddd;">Cantidad</th>
                            <th style="padding: 10px; border: 1px solid #ddd;">Link</th>
                            <th style="padding: 10px; text-align: right; border: 1px solid #ddd;">Costo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($item_summary_details as $item): ?>
                            <tr>
                                <td style="padding: 8px; border: 1px solid #eee; text-align: left; font-weight: bold;"><?php echo htmlspecialchars($item['nombre']); ?></td>
                                <td style="padding: 8px; border: 1px solid #eee; text-align: center;"><?php echo number_format($item['cantidad']); ?></td>
                                <td style="padding: 8px; border: 1px solid #eee; text-align: center; font-size: 0.8em;"><?php echo substr(htmlspecialchars($item['link_destino']), 0, 35) . (strlen($item['link_destino']) > 35 ? '...' : ''); ?></td>
                                <td style="padding: 8px; border: 1px solid #eee; text-align: right;">$<?php echo number_format($item['costo'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <hr>
                <h3 style="color: red; text-align: right; padding-right: 15px;">TOTAL FINAL: $<?php echo number_format($costo_total, 2); ?> USD</h3>
            </div>

            <?php if ($error_message): ?>
                <p style="color: red; background: #f8d7da; padding: 10px; border-radius: 5px; margin-bottom: 15px;"><?php echo $error_message; ?></p>
            <?php endif; ?>

            <h2>Identificación y Contacto</h2>
            <p style="font-size: 0.9em; margin-bottom: 20px;">Ingrese su email. Si ya tiene cuenta, la identificaremos automáticamente. Si no, crearemos una nueva.</p>
            
            <form action="checkout.php" method="post">
                <input type="hidden" name="action" value="confirm_order">
                
                <div class="form-group">
                    <label for="email">Email (Su Usuario)</label>
                    <input type="email" name="email" id="email" required placeholder="tu@correo.com" value="<?php echo htmlspecialchars($email_recuperado); ?>">
                </div>
                
                <div class="form-group">
                    <label for="numero_telefono">Teléfono (WhatsApp - Para notificaciones de pedido)</label>
                    <div class="telefono-group" style="display: flex; gap: 10px;">
                        <select name="codigo_pais" id="codigo_pais" required style="flex: 1; padding: 10px; border: 1px solid #ccc; border-radius: 5px;">
                            <?php foreach ($country_codes as $name => $code): ?>
                                <option value="<?php echo htmlspecialchars($code); ?>" <?php echo ($code == '+57' ? 'selected' : ''); ?>>
                                    <?php echo htmlspecialchars($name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="numero_telefono" id="numero_telefono" placeholder="Número sin código" required style="flex: 2;">
                    </div>
                    <small>El número de WhatsApp se guardará con el código de país seleccionado (Ej: +57310xxxxxxx).</small>
                </div>

                <p style="font-size: 0.9em; margin-top: 20px;">*Al proceder, si es nuevo, su contraseña será enviada por email/WhatsApp.</p>

                <button type="submit" class="btn-primary" style="width: 100%;">Proceder al Pago de $<?php echo number_format($costo_total, 2); ?> USD</button>
            </form>
        </div>
    </main>
    
    <a href="https://wa.me/<?php echo get_config('WHATSAPP_NUMBER'); ?>" class="whatsapp-float" target="_blank" title="Contacta con el Administrador">💬</a>
</body>
</html>