<?php
// Guardar en: smm_panel/checkout.php

session_start();
require_once 'includes/db_connect.php';

// Redirigir si no hay datos de pedido
if ($_SERVER["REQUEST_METHOD"] != "POST" || !isset($_POST['service_id'])) {
    header("Location: servicios.php");
    exit;
}

$service_id = (int)$_POST['service_id'];
$cantidad = (int)$_POST['cantidad'];
$link_destino = trim($_POST['link_destino']);

// 1. Obtener detalles del servicio (precio, nombre, etc.)
$service = null;
if ($stmt = $conn->prepare("SELECT nombre, precio, min_cantidad, max_cantidad FROM servicios WHERE id = ?")) {
    $stmt->bind_param("i", $service_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $service = $result->fetch_assoc();
    $stmt->close();
}

if (!$service) {
    // Si el servicio no existe, redirigir con error
    header("Location: servicios.php?error=servicio_invalido");
    exit;
}

// Cálculo final del costo
$costo_unidad = $service['precio'] / 1000;
$costo_total = $costo_unidad * $cantidad;

$error_message = '';

// 2. Lógica de PROCESAR CHECKOUT (se ejecutará al enviar el formulario de datos personales)
if (isset($_POST['action']) && $_POST['action'] == 'confirm_order') {
    
    $email = trim($_POST['email']);
    $telefono = trim($_POST['telefono']);
    $password_generada = substr(md5(rand()), 0, 8); // Generar contraseña aleatoria de 8 caracteres
    $password_hash = password_hash($password_generada, PASSWORD_BCRYPT);
    $nombre_usuario = explode('@', $email)[0]; // Usar la primera parte del email como nombre

    // Verificar si el email ya existe
    $stmt_check = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
    $stmt_check->bind_param("s", $email);
    $stmt_check->execute();
    $stmt_check->store_result();

    $usuario_id = 0;
    
    if ($stmt_check->num_rows == 0) {
        // 3. CREAR NUEVO USUARIO (Registro Automático)
        $stmt_insert = $conn->prepare("INSERT INTO usuarios (nombre, email, telefono, password_hash, saldo, rol) VALUES (?, ?, ?, ?, 0.00, 'cliente')");
        $stmt_insert->bind_param("ssss", $nombre_usuario, $email, $telefono, $password_hash);
        
        if ($stmt_insert->execute()) {
            $usuario_id = $conn->insert_id;
            
            // ** 4. ENVIAR CREDENCIALES (EMAIL y WHATSAPP) **
            // Esto se ejecutaría después de confirmar el pago (ver Fase 18)
            // Por ahora, solo se imprime la info:
            $credential_message = "¡Bienvenido! Tu cuenta ha sido creada:\nUsuario: {$email}\nContraseña: {$password_generada}";
            
        } else {
            $error_message = "Error al crear la cuenta: " . $conn->error;
            $conn->close();
        }
    } else {
        // El usuario ya existe, solo obtenemos el ID para el pedido
        $stmt_check->bind_result($usuario_id);
        $stmt_check->fetch();
        $error_message = "Ya tienes una cuenta registrada. Por favor, realiza el pago.";
        $password_generada = null; // No regenerar y enviar contraseña si ya existe
    }
    
    $stmt_check->close();

    if ($usuario_id > 0) {
        // 5. Crear el Pedido (Inicialmente en estado Pendiente de Pago)
        $stmt_pedido = $conn->prepare("INSERT INTO pedidos (usuario_id, servicio_id, link_destino, cantidad, costo_total, estado) VALUES (?, ?, ?, ?, ?, 'pendiente_pago')");
        $stmt_pedido->bind_param("iisid", $usuario_id, $service_id, $link_destino, $cantidad, $costo_total);
        $stmt_pedido->execute();
        $pedido_id = $conn->insert_id;
        $stmt_pedido->close();

        // 6. Redirigir a la Pasarela de Pago
        // Aquí iría la integración con PayPal, Stripe, etc.
        // Por ahora, redirigimos a una página de confirmación.
        header("Location: pago.php?pedido_id=" . $pedido_id . ($password_generada ? "&new_user=true" : ""));
        exit;
    }
}

$conn->close();
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
            max-width: 600px;
            margin: 50px auto;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .summary {
            border: 1px dashed #ccc;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <?php include 'header_client.php'; // Usaremos un include para el header del cliente ?>

    <main class="container">
        <div class="checkout-box">
            <h1 style="color: var(--color-principal);">🛒 Confirmación de Pedido</h1>

            <div class="summary">
                <h4>Resumen de Compra</h4>
                <p><strong>Servicio:</strong> <?php echo htmlspecialchars($service['nombre']); ?></p>
                <p><strong>Cantidad:</strong> <?php echo number_format($cantidad); ?></p>
                <p><strong>Link de Destino:</strong> <?php echo htmlspecialchars($link_destino); ?></p>
                <hr>
                <h3 style="color: red;">Total a Pagar: $<?php echo number_format($costo_total, 2); ?> USD</h3>
            </div>

            <?php if ($error_message): ?>
                <p style="color: red; background: #f8d7da; padding: 10px; border-radius: 5px; margin-bottom: 15px;"><?php echo $error_message; ?></p>
            <?php endif; ?>

            <h2>Tus Datos (Crearemos tu Cuenta)</h2>
            <form action="checkout.php" method="post">
                <input type="hidden" name="action" value="confirm_order">
                <input type="hidden" name="service_id" value="<?php echo $service_id; ?>">
                <input type="hidden" name="cantidad" value="<?php echo $cantidad; ?>">
                <input type="hidden" name="link_destino" value="<?php echo htmlspecialchars($link_destino); ?>">

                <div class="form-group">
                    <label for="email">Email (Será tu usuario de acceso)</label>
                    <input type="email" name="email" id="email" required placeholder="tu@correo.com">
                </div>
                
                <div class="form-group">
                    <label for="telefono">Teléfono (WhatsApp - Para notificaciones)</label>
                    <input type="text" name="telefono" id="telefono" required placeholder="+57310xxxxxxx">
                </div>

                <p style="font-size: 0.9em; margin-bottom: 20px;">Al confirmar, aceptas que usemos tu Email como usuario y te enviaremos una contraseña automática y el estado de tu pedido por WhatsApp.</p>

                <button type="submit" class="btn-primary" style="width: 100%;">Proceder al Pago</button>
            </form>
        </div>
    </main>
    
    <?php // Botón WhatsApp Flotante ?>
    <a href="https://wa.me/TU_NUMERO_WHATSAPP_AQUI" class="whatsapp-float" target="_blank" title="Contacta con el Administrador">💬</a>
</body>
</html>