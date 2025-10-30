<?php
// Guardar en: smm_panel/add_to_cart.php

session_start();
require_once 'includes/db_connect.php'; 
require_once 'includes/config_global.php'; 

// 1. Verificar si la sesión de carrito existe, sino, crearla
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$message = '';

// 2. Verificar datos mínimos de POST
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['service_id']) && isset($_POST['paquete_id']) && isset($_POST['link_destino'])) {
    
    $service_id = (int)$_POST['service_id'];
    $paquete_id = (int)$_POST['paquete_id'];
    $link_destino = trim($_POST['link_destino']);

    // 3. Obtener los detalles del paquete y servicio de la BD para asegurar el precio (Seguridad)
    $stmt = $conn->prepare("
        SELECT 
            sp.cantidad, sp.precio_paquete, sp.precio_rebajado,
            s.nombre AS servicio_nombre
        FROM servicios_paquetes sp
        JOIN servicios s ON sp.servicio_id = s.id
        WHERE sp.id = ? AND sp.servicio_id = ? AND sp.activo = 1
    ");
    $stmt->bind_param("ii", $paquete_id, $service_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $item_data = $result->fetch_assoc();
    $stmt->close();

    if ($item_data) {
        $costo_unitario = $item_data['precio_rebajado'] ?? $item_data['precio_paquete'];
        
        // 4. Construir el ítem del carrito
        $cart_item = [
            'service_id' => $service_id,
            'paquete_id' => $paquete_id,
            'nombre' => $item_data['servicio_nombre'],
            'cantidad' => $item_data['cantidad'],
            'link_destino' => $link_destino,
            'costo_total' => round($costo_unitario, 2)
        ];

        // 5. Añadir al carrito
        // Usamos un ID único basado en el tiempo para diferenciar ítems, incluso si son del mismo servicio
        $_SESSION['cart'][] = $cart_item;

        $message = urlencode("✅ Paquete de '{$cart_item['nombre']}' añadido al carrito.");
        
    } else {
        $message = urlencode("❌ Error: Paquete no encontrado o inactivo.");
    }

} else {
    $message = urlencode("❌ Error: Datos insuficientes para añadir al carrito.");
}

$conn->close();

// 6. Redirigir de vuelta al catálogo
header("Location: servicios.php?msg=" . $message);
exit;
?>