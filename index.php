<?php
// Guardar en: smm_panel/index.php

// 1. INICIAR SESIÓN Y CARGAR CONFIGURACIÓN
// Aseguramos que la sesión esté iniciada.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Incluimos la conexión a la DB y la configuración global.
require_once 'includes/db_connect.php'; 
require_once 'includes/config_global.php'; 

// 2. OBTENER VARIABLES DINÁMICAS GLOBALES
$site_name = get_config('SITE_NAME');
$whatsapp_num = get_config('WHATSAPP_NUMBER'); // Usado para el botón flotante

// Definiciones de SEO para la página de inicio
$meta_title = htmlspecialchars($site_name) . " | El Mejor Panel SMM para Comprar Servicios";
$meta_description = "Venta de servicios de redes sociales: seguidores, likes, vistas y más. Calidad garantizada y despacho instantáneo en " . htmlspecialchars($site_name) . ".";

// 3. OBTENER CATEGORÍAS DINÁMICAS DE LA BASE DE DATOS
$categorias = [];
// Seleccionamos nombre, slug, y la ruta del icono para las tarjetas.
$sql_categorias = "SELECT id, nombre, slug, icono_url FROM categorias WHERE activa = 1 ORDER BY id ASC";
$result = $conn->query($sql_categorias);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $categorias[] = $row;
    }
}
// ¡IMPORTANTE! Se ELIMINÓ la línea $conn->close(); aquí. 
// La conexión se mantendrá abierta para ser usada por header_client.php.
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?php echo $meta_title; ?></title>
    
    <meta name="description" content="<?php echo $meta_description; ?>">
    <meta property="og:title" content="<?php echo $meta_title; ?>">
    <meta property="og:description" content="<?php echo $meta_description; ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]"; ?>">

    <link rel="apple-touch-icon" href="assets/img/icon-192x192.png">
    <link rel="manifest" href="manifest.json">
    <link rel="icon" type="image/png" href="assets/img/favicon.png">
    <meta name="theme-color" content="#007bff"> <link rel="stylesheet" href="assets/css/style.css?v=1.2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLMDJ/cnV+6Z8A+mY9u/Tof/j0O4Q4/0y6mK/wT42bYl/jA0j5h4Q==" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>
<body>

    <?php include 'header_client.php'; ?>

    <main class="container" style="padding-top: 50px;">
        
        <section class="hero" style="text-align: center; margin-bottom: 50px;">
            <h1><?php echo htmlspecialchars($site_name); ?>: Impulsa tu Presencia Social</h1>
            <p>El mejor servicio de crecimiento para tus redes sociales. Seguidores, Likes y Vistas reales y garantizados. ¡Comienza ahora!</p>
            <a href="servicios.php" class="btn-primary" style="margin-top: 20px;">Ver Todos los Servicios</a>
        </section>

        <section class="categories-section">
            <h2 style="text-align: center; margin-bottom: 30px;">Nuestros Servicios por Categoría</h2>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                
                <?php if (empty($categorias)): ?>
                    <div style="grid-column: 1 / -1; text-align: center; padding: 30px; background: #fff3cd; border-radius: 8px;">
                        <p style="color: #856404;">Aún no hay categorías de servicios activas. Por favor, créalas desde el panel de administrador.</p>
                        <a href="admin/categorias.php" class="nav-link">Ir a Admin</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($categorias as $cat): ?>
                        <a href="servicios.php?cat=<?php echo htmlspecialchars($cat['slug']); ?>" class="service-card" style="text-decoration: none;">
                            <?php if (strpos($cat['icono_url'], 'assets/') !== false): ?>
                                <img src="<?php echo htmlspecialchars($cat['icono_url']); ?>" alt="Icono de <?php echo htmlspecialchars($cat['nombre']); ?>" class="service-icon">
                            <?php else: ?>
                                <i class="<?php echo htmlspecialchars($cat['icono_url'] ?? 'fas fa-globe'); ?> service-icon" style="font-size: 3em; color: var(--color-principal);"></i>
                            <?php endif; ?>
                            <h3><?php echo htmlspecialchars($cat['nombre']); ?></h3>
                            <p style="font-size: 0.9em; color: #6c757d;">Ver todos los servicios de <?php echo htmlspecialchars($cat['nombre']); ?></p>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>

            </div>
        </section>

        <section style="margin-top: 50px; padding: 30px; background: #e9ecef; border-radius: 8px;">
            <h2 style="text-align: center; margin-bottom: 30px;">¿Por Qué Elegirnos?</h2>
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
                <div class="feature-card" style="background: white; padding: 20px; border-radius: 8px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                    <h3>🚀 Despacho Instantáneo</h3>
                    <p>Nuestra API automatizada garantiza que tu pedido comience a procesarse al instante.</p>
                </div>
                <div class="feature-card" style="background: white; padding: 20px; border-radius: 8px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                    <h3>🔒 100% Seguro y Privado</h3>
                    <p>Tus datos están protegidos. Usamos conexiones cifradas y no requerimos contraseñas de tus cuentas.</p>
                </div>
                <div class="feature-card" style="background: white; padding: 20px; border-radius: 8px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                    <h3>💸 Precios Insuperables</h3>
                    <p>Ofrecemos la mejor relación calidad-precio del mercado SMM para maximizar tu ganancia.</p>
                </div>
            </div>
        </section>

    </main>

    <footer class="footer" style="text-align: center; padding: 20px; margin-top: 40px; border-top: 1px solid #ccc;">
        <p>&copy; <?php echo date("Y"); ?> <?php echo htmlspecialchars($site_name); ?>. Todos los derechos reservados.</p>
        <div class="footer-links" style="margin-top: 10px;">
            <a href="terminos.php" class="nav-link">Términos y Condiciones</a> | 
            <a href="privacidad.php" class="nav-link">Política de Privacidad</a>
        </div>
    </footer>

    <?php if (!empty($whatsapp_num)): ?>
        <a href="https://wa.me/<?php echo urlencode($whatsapp_num); ?>" class="whatsapp-float" target="_blank" title="Contáctanos por WhatsApp">
            💬
        </a>
    <?php endif; ?>
    
    <script src="assets/js/app.js"></script>
    <script>
        // Nota: app.js contiene el script para registrar el service-worker.
    </script>
</body>
</html>