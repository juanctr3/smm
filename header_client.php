<?php
// Guardar en: smm_panel/header_client.php

// 1. INICIAR SESIÓN Y CARGAR CONFIGURACIÓN
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Asegurarse de que la conexión a la DB esté disponible
if (!defined('DB_SERVER')) {
    require_once 'includes/db_connect.php'; 
}
require_once 'includes/config_global.php'; 

$is_logged_in = isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;
$user_saldo = 0;

if ($is_logged_in && isset($_SESSION["id"])) {
    // Obtener el saldo actual del usuario
    $stmt = $conn->prepare("SELECT saldo FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $_SESSION["id"]);
    $stmt->execute();
    $stmt->bind_result($user_saldo);
    $stmt->fetch();
    $stmt->close();
}

// 2. OBTENER ENLACES DINÁMICOS DEL MENÚ (de la tabla menu_items)
$menu_items_header = [];
if (isset($conn) && $conn->ping()) {
    $stmt = $conn->prepare("SELECT nombre, url FROM menu_items WHERE ubicacion = 'header' AND activo = 1 ORDER BY orden ASC");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($item = $result->fetch_assoc()) {
        $menu_items_header[] = $item;
    }
    $stmt->close();
}
$site_name = get_config('SITE_NAME');
?>

<header class="header">
    <div class="logo"><?php echo htmlspecialchars($site_name); ?></div>
    <nav>
        
        <?php foreach ($menu_items_header as $item): ?>
            <a href="<?php echo htmlspecialchars($item['url']); ?>" class="nav-link"><?php echo htmlspecialchars($item['nombre']); ?></a>
        <?php endforeach; ?>
        <?php if ($is_logged_in): ?>
            <a href="cuenta.php" class="nav-link">Mi Cuenta (Saldo: $<?php echo number_format($user_saldo, 2); ?>)</a>
            <a href="logout.php" class="btn-primary">Cerrar Sesión</a>
        <?php else: ?>
            <a href="login.php" class="nav-link">Iniciar Sesión</a>
            <a href="registro.php" class="btn-primary">Registrarse</a>
        <?php endif; ?>
        
    </nav>
</header>