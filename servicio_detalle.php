<?php
// Guardar en: smm_panel/servicio_detalle.php

session_start();
require_once 'includes/db_connect.php'; 
require_once 'includes/config_global.php'; 

$is_logged_in = isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;
$user_saldo = 0;
$site_name = get_config('SITE_NAME');
$whatsapp_num = get_config('WHATSAPP_NUMBER'); 
$url_slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
$servicio_data = null;
$paquetes = [];

if (empty($url_slug)) {
    header("Location: servicios.php");
    exit;
}

// 1. Obtener datos del servicio por el slug
$stmt = $conn->prepare("
    SELECT 
        s.*, c.nombre AS categoria_nombre 
    FROM servicios s
    JOIN categorias c ON s.categoria_id = c.id
    WHERE s.url_slug = ? AND s.activo = 1
");
$stmt->bind_param("s", $url_slug);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $servicio_data = $result->fetch_assoc();
}
$stmt->close();

// 2. Obtener los paquetes de precios
if ($servicio_data) {
    $stmt_paq = $conn->prepare("SELECT id, cantidad, precio_paquete, precio_rebajado FROM servicios_paquetes WHERE servicio_id = ? AND activo = 1 ORDER BY cantidad ASC");
    $stmt_paq->bind_param("i", $servicio_data['id']);
    $stmt_paq->execute();
    $result_paq = $stmt_paq->get_result();
    while ($paq = $result_paq->fetch_assoc()) {
        $paquetes[] = $paq;
    }
    $stmt_paq->close();
}

if (!$servicio_data) {
    // Servicio no encontrado o inactivo
    header("HTTP/1.0 404 Not Found");
    echo "<h1>Error 404</h1><p>El servicio solicitado no existe o no está activo.</p>";
    exit;
}

// 3. Definir Variables SEO Dinámicas
$meta_title = htmlspecialchars($servicio_data['meta_titulo'] ?? $servicio_data['nombre'] . ' - Comprar en ' . $site_name);
$meta_description = htmlspecialchars($servicio_data['meta_descripcion'] ?? substr(strip_tags($servicio_data['descripcion_larga']), 0, 150) . '...'); // strip_tags para limpiar HTML
$image_src = $servicio_data['imagen_url'] ? 'assets/img/' . basename($servicio_data['imagen_url']) : 'assets/img/default-service.png';

// La conexión se mantiene abierta para header_client.php
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?php echo $meta_title; ?></title>
    <meta name="description" content="<?php echo $meta_description; ?>">
    <meta property="og:title" content="<?php echo $meta_title; ?>">
    <link rel="canonical" href="<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]/servicio_detalle.php?slug={$url_slug}"; ?>">

    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        .service-detail-grid {
            display: grid;
            grid-template-columns: 2fr 1fr; /* Contenido ancho y sidebar de compra */
            gap: 40px;
            margin-top: 30px;
        }
        .buy-box {
            background: #f4f4f4; 
            padding: 25px; 
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            height: fit-content;
        }
        .price-display { font-size: 2em; font-weight: bold; color: var(--color-principal); margin-top: 10px; }
        .buy-box small { display: block; color: #6c757d; margin-top: 5px; }
        .video-container {
            position: relative;
            width: 100%;
            padding-bottom: 56.25%; /* 16:9 Aspect Ratio */
            margin-bottom: 20px;
        }
        .video-container iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border-radius: 8px;
        }
    </style>
    <script>
        // Función JS para actualizar el costo y los datos del formulario al cambiar el paquete
        function updatePackageDetail() {
            const serviceId = <?php echo $servicio_data['id']; ?>;
            const select = document.getElementById('paquete_select');
            const selectedOption = select.options[select.selectedIndex];
            
            const price = selectedOption.getAttribute('data-price');
            const discountedPrice = selectedOption.getAttribute('data-discount');
            const quantity = selectedOption.getAttribute('data-quantity');

            // Actualizar el costo total visible
            const finalPrice = discountedPrice || price;
            document.getElementById('total-price-display').innerHTML = 
                '$' + parseFloat(finalPrice).toFixed(2) + 
                '<small style="display: block; font-size: 0.5em; color: #999;">' + 
                'Total por ' + quantity + ' unidades' + 
                '</small>';

            // Actualizar los campos hidden que se envían a add_to_cart.php
            document.getElementById('form_paquete_id').value = selectedOption.value;
            document.getElementById('form_cantidad').value = quantity;
            document.getElementById('form_costo_total').value = finalPrice;
        }
    </script>
</head>
<body>

    <?php include 'header_client.php'; ?>

    <main class="container">
        
        <h1 style="color: var(--color-principal); margin-top: 40px;"><?php echo htmlspecialchars($servicio_data['nombre']); ?></h1>
        <p style="color: #6c757d; font-size: 1.1em;"><?php echo htmlspecialchars($servicio_data['descripcion_corta']); ?></p>

        <div class="service-detail-grid">
            
            <div class="content-main">
                
                <?php if ($servicio_data['imagen_url']): ?>
                    <img src="<?php echo htmlspecialchars('../' . $servicio_data['imagen_url']); ?>" alt="<?php echo htmlspecialchars($servicio_data['nombre']); ?>" style="width: 100%; max-width: 600px; height: auto; border-radius: 10px; margin-bottom: 25px;">
                <?php endif; ?>

                <?php if (!empty($servicio_data['video_url'])): 
                    // Simplificar la URL de YouTube para incrustar si es posible
                    $youtube_id = '';
                    if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/ ]{11})/i', $servicio_data['video_url'], $matches)) {
                        $youtube_id = $matches[1];
                    }
                ?>
                    <h2 style="border-bottom: 2px solid #ccc; padding-bottom: 10px;">Video Explicativo</h2>
                    <div class="video-container">
                        <?php if ($youtube_id): ?>
                            <iframe src="https://www.youtube.com/embed/<?php echo $youtube_id; ?>" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                        <?php else: ?>
                            <p>No se pudo cargar el video incrustado. URL original: <?php echo htmlspecialchars($servicio_data['video_url']); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <h2 style="border-bottom: 2px solid #ccc; padding-bottom: 10px; margin-top: 30px;">Descripción Detallada</h2>
                <div style="padding: 15px; background: white; border-radius: 8px;">
                    <?php echo $servicio_data['descripcion_larga']; ?>
                </div>

                <div style="margin-top: 30px; padding: 15px; border-left: 5px solid var(--color-acento); background: #e9f7ef;">
                    <p style="font-weight: bold;">Palabras Clave (SEO):</p>
                    <p><?php echo htmlspecialchars($servicio_data['keywords']); ?></p>
                </div>
            </div>

            <div class="sidebar-compra">
                <div class="buy-box">
                    <h3>Comprar <?php echo htmlspecialchars($servicio_data['nombre']); ?></h3>
                    <hr>
                    
                    <?php if (empty($paquetes)): ?>
                        <p style="color: red; font-weight: bold;">❌ Este servicio no tiene paquetes de precios activos.</p>
                        <a href="servicios.php" class="btn-primary" style="display: block; text-align: center;">Volver al Catálogo</a>
                    <?php else: 
                        $default_paquete = $paquetes[0];
                        $default_price = $default_paquete['precio_paquete'];
                        $default_discount = $default_paquete['precio_rebajado'];
                        $display_price = $default_discount ?? $default_price;
                    ?>
                        
                        <div class="price-display" id="total-price-display">
                            $<?php echo number_format($display_price, 2); ?>
                            <small style="display: block; font-size: 0.5em; color: #999;">Total por <?php echo number_format($default_paquete['cantidad']); ?> unidades</small>
                        </div>

                        <form action="add_to_cart.php" method="post" style="margin-top: 20px;">
                            <input type="hidden" name="service_id" value="<?php echo $servicio_data['id']; ?>">
                            
                            <input type="hidden" name="paquete_id" id="form_paquete_id" value="<?php echo $default_paquete['id']; ?>">
                            <input type="hidden" name="cantidad" id="form_cantidad" value="<?php echo $default_paquete['cantidad']; ?>">
                            <input type="hidden" name="costo_total" id="form_costo_total" value="<?php echo $display_price; ?>">

                            <div class="form-group">
                                <label for="paquete_select">Selecciona el Paquete:</label>
                                <select name="paquete_select" id="paquete_select" class="form-control" onchange="updatePackageDetail()">
                                    <?php foreach ($paquetes as $paq):
                                        $final_price = $paq['precio_rebajado'] ?? $paq['precio_paquete'];
                                        $is_offer = $paq['precio_rebajado'] !== NULL;
                                    ?>
                                        <option 
                                            value="<?php echo $paq['id']; ?>" 
                                            data-quantity="<?php echo $paq['cantidad']; ?>"
                                            data-price="<?php echo $paq['precio_paquete']; ?>"
                                            data-discount="<?php echo $paq['precio_rebajado'] ?? ''; ?>"
                                        >
                                            <?php echo number_format($paq['cantidad']); ?> Unidades 
                                            (<?php echo $is_offer ? 'OFERTA' : 'Precio'; ?>: $<?php echo number_format($final_price, 2); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="link_destino">Enlace/Link de Destino:</label>
                                <input type="url" name="link_destino" id="link_destino" placeholder="Ej: https://instagram.com/tu_perfil" required>
                            </div>

                            <button type="submit" class="btn-primary" style="width: 100%;" <?php echo $is_logged_in ? '' : 'disabled'; ?>>
                                <?php echo $is_logged_in ? 'Añadir al Carrito' : 'Iniciar Sesión para Comprar'; ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </main>
    
    <a href="https://wa.me/<?php echo urlencode($whatsapp_num); ?>" class="whatsapp-float" target="_blank" title="Contacta con el Administrador">
        💬 
    </a>
    
    <script>
        // Ejecutar la función de actualización inicial al cargar la página
        document.addEventListener('DOMContentLoaded', updatePackageDetail);
    </script>
</body>
</html>