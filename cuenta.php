<?php
// Guardar en: smm_panel/cuenta.php

session_start();

// 1. VERIFICACIÓN DE SEGURIDAD
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    // Si no está logueado, redirigir al login
    header("location: login.php");
    exit;
}

// Incluir la conexión y configuración global (para SITE_NAME, etc.)
require_once 'includes/db_connect.php'; 
require_once 'includes/config_global.php'; 

// 2. LÓGICA: OBTENER DATOS DEL USUARIO Y PEDIDOS

$user_id = $_SESSION["id"];
$user_name = htmlspecialchars($_SESSION["nombre"]);
$user_saldo = 0.00;
$pedidos_recientes = null;

// Obtener Saldo
if ($stmt = $conn->prepare("SELECT saldo FROM usuarios WHERE id = ?")) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($user_saldo);
    $stmt->fetch();
    $stmt->close();
}

// Obtener Pedidos Recientes (Máximo 10)
$sql_pedidos = "
    SELECT 
        p.id, p.costo_total, p.cantidad, p.estado, p.fecha_creacion, 
        s.nombre AS servicio_nombre,
        (SELECT COUNT(r.id) FROM reviews r WHERE r.pedido_id = p.id) AS review_count
    FROM pedidos p
    JOIN servicios s ON p.servicio_id = s.id
    WHERE p.usuario_id = ?
    ORDER BY p.fecha_creacion DESC
    LIMIT 10
";

if ($stmt_pedidos = $conn->prepare($sql_pedidos)) {
    $stmt_pedidos->bind_param("i", $user_id);
    $stmt_pedidos->execute();
    $pedidos_recientes = $stmt_pedidos->get_result();
    $stmt_pedidos->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Cuenta | <?php echo get_config('SITE_NAME'); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .dashboard-widgets { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
        .widget { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .widget h3 { color: #6c757d; font-size: 1em; margin-bottom: 5px; }
        .widget p { font-size: 2.5em; font-weight: bold; color: var(--color-principal); }
        .status-completado { color: green; }
        .status-procesando { color: #ffc107; }
        .status-pendiente, .status-pendiente_pago { color: orange; }
    </style>
</head>
<body>
    <?php include 'header_client.php'; ?>

    <main class="container" style="padding-top: 30px;">
        <h1>Hola, <?php echo $user_name; ?></h1>
        <p>Este es el resumen de tu cuenta y actividad reciente.</p>

        <div class="dashboard-widgets">
            
            <div class="widget">
                <h3>💸 Saldo Disponible</h3>
                <p>$<?php echo number_format($user_saldo, 2); ?></p>
                <a href="agregar_fondos.php" class="btn-primary" style="display: block; text-align: center; margin-top: 15px;">Añadir Fondos</a>
            </div>

            <div class="widget">
                <h3>🛒 Total Pedidos</h3>
                <p><?php echo number_format($pedidos_recientes ? $pedidos_recientes->num_rows : 0); ?></p>
                <a href="servicios.php" class="btn-primary" style="display: block; text-align: center; margin-top: 15px; background: var(--color-acento);">Comprar Servicios</a>
            </div>

            <div class="widget">
                <h3>⭐ Calificaciones Pendientes</h3>
                <p>0</p> 
                <a href="reviews.php" class="btn-primary" style="display: block; text-align: center; margin-top: 15px; background: #6c757d;">Calificar</a>
            </div>
            
        </div>

        <h2>Historial de Pedidos Recientes</h2>
        <table style="width: 100%; border-collapse: collapse; background: white; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <thead>
                <tr style="background: #f8f9fa;">
                    <th style="padding: 15px; text-align: left;">ID</th>
                    <th style="text-align: left;">Servicio</th>
                    <th style="text-align: center;">Cantidad</th>
                    <th style="text-align: center;">Total</th>
                    <th style="text-align: center;">Estado</th>
                    <th style="text-align: center;">Fecha</th>
                    <th style="text-align: left;">Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($pedidos_recientes && $pedidos_recientes->num_rows > 0): ?>
                    <?php while($row = $pedidos_recientes->fetch_assoc()): ?>
                    <tr>
                        <td style="padding: 10px;">#<?php echo $row['id']; ?></td>
                        <td><?php echo htmlspecialchars($row['servicio_nombre']); ?></td>
                        <td style="text-align: center;"><?php echo number_format($row['cantidad']); ?></td>
                        <td style="text-align: center;">$<?php echo number_format($row['costo_total'], 2); ?></td>
                        <td style="text-align: center;" class="status-<?php echo strtolower($row['estado']); ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $row['estado'])); ?>
                        </td>
                        <td style="text-align: center;"><?php echo date("Y-m-d", strtotime($row['fecha_creacion'])); ?></td>
                        <td>
                            <?php if ($row['estado'] == 'completado' && $row['review_count'] == 0): ?>
                                <a href="review_form.php?pedido_id=<?php echo $row['id']; ?>" class="nav-link" style="color: gold; font-weight: bold;">Calificar ⭐</a>
                            <?php elseif ($row['estado'] == 'completado'): ?>
                                <span style="color: green;">Calificado</span>
                            <?php else: ?>
                                En Proceso
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7" style="text-align: center; padding: 20px;">No has realizado ningún pedido aún.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

    </main>
    
    <a href="https://wa.me/<?php echo get_config('WHATSAPP_NUMBER'); ?>" class="whatsapp-float" target="_blank" title="Contacta con el Administrador">
        💬 
    </a>
</body>
</html>