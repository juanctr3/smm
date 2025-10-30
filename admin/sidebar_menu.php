<?php
// Guardar en: smm_panel/admin/sidebar_menu.php
require_once '../includes/db_connect.php'; 
require_once '../includes/config_global.php';
// Esta variable obtiene el nombre del archivo actual (ej: dashboard.php)
$current_page = basename($_SERVER['PHP_SELF']); 
?>

<aside class="admin-sidebar">
    <h2 style="text-align: center; margin-bottom: 30px; color: var(--color-principal);"><?php echo htmlspecialchars(get_config('SITE_NAME')); ?> Admin</h2>
    <div class="admin-menu">
        
        <a href="dashboard.php" class="<?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">📊 Dashboard</a>
        <a href="categorias.php" class="<?php echo ($current_page == 'categorias.php' || $current_page == 'servicios_crud.php') ? 'active' : ''; ?>">🏷️ Categorías & Servicios</a>
        <a href="pedidos.php" class="<?php echo ($current_page == 'pedidos.php') ? 'active' : ''; ?>">🛒 Gestión de Pedidos</a>
        <a href="usuarios.php" class="<?php echo ($current_page == 'usuarios.php') ? 'active' : ''; ?>">👥 Gestión de Usuarios</a>
        <a href="reviews.php" class="<?php echo ($current_page == 'reviews.php') ? 'active' : ''; ?>">⭐ Moderar Reseñas</a>
        
        <hr style="border-top: 1px solid #495057; margin: 10px 0;">

        <a href="menu_crud.php" class="<?php echo ($current_page == 'menu_crud.php') ? 'active' : ''; ?>">🔗 Gestión de Menús</a>
        <a href="config.php" class="<?php echo ($current_page == 'config.php') ? 'active' : ''; ?>">⚙️ Configuración (API/SEO)</a>
        
        <div style="padding: 15px; margin-top: 30px;">
            <a href="../logout.php" class="btn-primary" style="display: block; text-align: center;">Cerrar Sesión</a>
        </div>
    </div>
</aside>