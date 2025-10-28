<?php
// Guardar en: smm_panel/admin/dashboard.php

// 1. VERIFICACIÓN DE SEGURIDAD Y CONEXIÓN
// Asegura que solo los usuarios con rol 'admin' accedan y carga la sesión.
require_once 'auth_check.php'; 
// Incluir la conexión a la base de datos
require_once '../includes/db_connect.php'; 
// Incluir la configuración global (para SITE_NAME, etc.)
require_once '../includes/config_global.php'; 

// 2. LÓGICA: OBTENER MÉTRICAS CLAVE
$total_pedidos = 0;
$pedidos_pendientes = 0;
$total_usuarios = 0;
$ingresos_totales = 0.00;

try {
    // Consulta 1: Total de Pedidos y Pedidos Pendientes
    if ($result = $conn->query("SELECT COUNT(id) AS total, SUM(CASE WHEN estado = 'pendiente' OR estado = 'procesando' THEN 1 ELSE 0 END) AS pendientes FROM pedidos")) {
        $data = $result->fetch_assoc();
        $total_pedidos = $data['total'];
        $pedidos_pendientes = $data['pendientes'];
        $result->close();
    }

    // Consulta 2: Total de Usuarios (Clientes)
    if ($result = $conn->query("SELECT COUNT(id) FROM usuarios WHERE rol='cliente'")) {
        $total_usuarios = $result->fetch_row()[0];
        $result->close();
    }
    
    // Consulta 3: Ingresos Totales (Suma del costo de pedidos completados)
    if ($result = $conn->query("SELECT SUM(costo_total) FROM pedidos WHERE estado='completado'")) {
        $ingresos_totales = $result->fetch_row()[0] ?? 0.00;
        $result->close();
    }

} catch (mysqli_sql_exception $e) {
    // Esto captura errores si alguna tabla (ej. pedidos) aún no existe
    error_log("Error al cargar métricas: " . $e->getMessage());
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Admin - <?php echo get_config('SITE_NAME'); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <?php include 'sidebar_menu_css.php'; // Incluye los estilos de la barra lateral ?>
</head>
<body>

    <?php include 'sidebar_menu.php'; ?>

    <main class="admin-content">
        <h1>📊 Dashboard General</h1>
        <p>Bienvenido, **<?php echo htmlspecialchars($_SESSION["nombre"]); ?>**. Un resumen rápido de las operaciones del sitio.</p>

        <section style="margin-top: 30px;">
            <h2 style="margin-bottom: 20px;">Métricas Clave</h2>
            
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px;">
                
                <div class="metric-card">
                    <h3>Ingresos Totales</h3>
                    <p style="color: var(--color-acento);">$<?php echo number_format($ingresos_totales, 2); ?></p>
                </div>
                
                <div class="metric-card">
                    <h3>Pedidos Totales</h3>
                    <p><?php echo number_format($total_pedidos); ?></p>
                </div>

                <div class="metric-card">
                    <h3>Pedidos Pendientes</h3>
                    <p style="color: red;"><?php echo number_format($pedidos_pendientes); ?></p>
                </div>

                <div class="metric-card">
                    <h3>Usuarios Registrados</h3>
                    <p><?php echo number_format($total_usuarios); ?></p>
                </div>
            </div>
        </section>
        
        <hr style="margin: 40px 0;">

        <section>
            <h2>Gestión Rápida</h2>
            <div style="display: flex; gap: 20px;">
                <a href="categorias.php" class="btn-primary" style="background-color: #007bff;">
                    Gestionar Servicios & Precios
                </a>
                <a href="menu_crud.php" class="btn-primary" style="background-color: #ffc107; color: #333;">
                    Editar Menú (Enlaces)
                </a>
                <a href="config.php" class="btn-primary" style="background-color: #6c757d;">
                    Configuración (API & Sitio)
                </a>
            </div>
        </section>

    </main>
</body>
</html>