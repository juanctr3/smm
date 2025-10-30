<?php
// Guardar en: smm_panel/servicios.php
ini_set('display_errors', 1); 
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'includes/db_connect.php';
require_once 'includes/config_global.php';

$is_logged_in = isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;
$user_saldo = 0;
$site_name = get_config('SITE_NAME');
$whatsapp_num = get_config('WHATSAPP_NUMBER');
$current_category_slug = isset($_GET['cat']) ? trim($_GET['cat']) : '';

// --- INICIALIZACIÓN Y CONTEO DEL CARRITO ---
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
$cart_item_count = count($_SESSION['cart']);
// ------------------------------------------

if ($is_logged_in) {
    // Obtener el saldo actual del usuario logueado
    if ($stmt = $conn->prepare("SELECT saldo FROM usuarios WHERE id = ?")) {
        $stmt->bind_param("i", $_SESSION["id"]);
        $stmt->execute();
        $stmt->bind_result($user_saldo);
        $stmt->fetch();
        $stmt->close();
    }
}

// 1. Obtener todas las categorías y servicios
$servicios_por_categoria = [];
$categorias_list = [];

// 1.1 Obtener ID de la categoría actual (si se especificó un slug)
$category_filter_id = 0;
if (!empty($current_category_slug)) {
    $stmt_cat = $conn->prepare("SELECT id FROM categorias WHERE slug = ?");
    $stmt_cat->bind_param("s", $current_category_slug);
    $stmt_cat->execute();
    $result_cat = $stmt_cat->get_result();
    if ($row_cat = $result_cat->fetch_assoc()) {
        $category_filter_id = $row_cat['id'];
    }
    $stmt_cat->close();
}

// 1.2 Obtener todas las categorías activas
$categorias_result = $conn->query("SELECT id, nombre, icono_url, slug FROM categorias WHERE activa = 1 ORDER BY nombre ASC");

while ($cat = $categorias_result->fetch_assoc()) {
    $categorias_list[] = $cat;

    // Solo cargar servicios para la categoría actual o si no hay filtro
    if ($category_filter_id == 0 || $cat['id'] == $category_filter_id) {
        $servicios_por_categoria[$cat['id']] = [
            'info' => $cat,
            'servicios' => []
        ];
        
        // Cargar campos necesarios del servicio
        $sql_servicios = "SELECT id, nombre, descripcion_corta, imagen_url, velocidad, url_slug FROM servicios WHERE categoria_id = " . $cat['id'] . " AND activo = 1 ORDER BY nombre ASC";
        $servicios_result = $conn->query($sql_servicios);

        while ($svc = $servicios_result->fetch_assoc()) {
            // CRÍTICO: Cargar los paquetes de precios para cada servicio
            $paquetes = [];
            $stmt_paq = $conn->prepare("SELECT id, cantidad, precio_paquete, precio_rebajado FROM servicios_paquetes WHERE servicio_id = ? AND activo = 1 ORDER BY cantidad ASC");
            $stmt_paq->bind_param("i", $svc['id']);
            $stmt_paq->execute();
            $result_paq = $stmt_paq->get_result();
            while ($paq = $result_paq->fetch_assoc()) {
                $paquetes[] = $paq;
            }
            $stmt_paq->close();

            // Solo mostrar el servicio si tiene al menos un paquete
            if (!empty($paquetes)) {
                $svc['paquetes'] = $paquetes;
                $servicios_por_categoria[$cat['id']]['servicios'][] = $svc;
            }
        }
    }
}
// La línea $conn->close(); se mantiene eliminada aquí.
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprar Servicios | <?php echo htmlspecialchars($site_name); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <style>
        .category-filter { margin-bottom: 20px; display: flex; flex-wrap: wrap; gap: 10px; }
        .category-filter .nav-link { 
            padding: 8px 15px; 
            border: 1px solid #ccc; 
            border-radius: 5px;
            text-decoration: none;
            transition: background-color 0.2s;
        }
        .category-filter .nav-link.active, .category-filter .nav-link:hover {
            background-color: var(--color-principal);
            color: white;
            border-color: var(--color-principal);
        }
        .service-block-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .service-info { flex: 1; padding-right: 20px; }
        .service-image { max-width: 100px; height: auto; border-radius: 8px; margin-right: 15px; }
        .price-section { font-size: 1.5em; font-weight: bold; }
        .original-price { text-decoration: line-through; color: #999; font-size: 0.7em; font-weight: normal; margin-left: 10px; }
        .offer-price { color: red; }
        /* Estilos para el contador del carrito */
        .cart-counter { 
            position: absolute;
            top: -5px;
            right: -5px;
            background: red;
            color: white;
            border-radius: 50%;
            padding: 2px 7px;
            font-size: 0.7em;
            font-weight: bold;
        }
    </style>
    <script>
        // Función JS para actualizar el costo y los datos del formulario al cambiar el paquete
        function updatePackage(serviceId) {
            const select = document.getElementById('paquete_select_' + serviceId);
            const selectedOption = select.options[select.selectedIndex];
            
            // Obtener datos del paquete seleccionado
            const price = selectedOption.getAttribute('data-price');
            const discountedPrice = selectedOption.getAttribute('data-discount');
            const quantity = selectedOption.getAttribute('data-quantity');

            // Actualizar el costo total visible
            const finalPrice = discountedPrice || price;
            document.getElementById('total-cost_' + serviceId).innerText = parseFloat(finalPrice).toFixed(2);

            // Actualizar los campos hidden que se envían a add_to_cart.php
            document.getElementById('form_cantidad_' + serviceId).value = quantity;
            document.getElementById('form_total_cost_' + serviceId).value = finalPrice;
        }
        
        // Inicializa los contadores al cargar la página
        document.addEventListener('DOMContentLoaded', () => {
             // Ejecutar la actualización para asegurar que el costo y los campos ocultos sean correctos al cargar
             const serviceSelects = document.querySelectorAll('select[id^="paquete_select_"]');
             serviceSelects.forEach(select => {
                 const serviceId = select.id.split('_').pop();
                 updatePackage(serviceId);
             });
        });

    </script>
</head>
<body>
    <?php include 'header_client.php'; ?>

    <main class="container" style="padding-top: 50px;">
        <h1 style="text-align: center; margin-bottom: 20px;">Catálogo de Servicios</h1>
        
        <div style="text-align: right; margin-bottom: 30px;">
            <a href="checkout.php" class="btn-primary" style="position: relative;">
                🛒 Carrito 
                <?php if ($cart_item_count > 0): ?>
                    <span class="cart-counter"><?php echo $cart_item_count; ?></span>
                <?php endif; ?>
            </a>
        </div>


        <?php if (!$is_logged_in): ?>
            <div style="background: #fff3cd; border: 1px solid #ffeeba; color: #856404; padding: 15px; border-radius: 5px; text-align: center; margin-bottom: 30px;">
                ⚠️ Debes <a href="login.php" style="font-weight: bold; color: #856404;">iniciar sesión</a> para realizar un pedido.
            </div>
        <?php endif; ?>

        <div class="category-filter">
            <a href="servicios.php" class="nav-link <?php echo empty($current_category_slug) ? 'active' : ''; ?>">Todas las Categorías</a>
            <?php foreach ($categorias_list as $cat): ?>
                <a href="servicios.php?cat=<?php echo htmlspecialchars($cat['slug']); ?>" class="nav-link <?php echo ($current_category_slug === $cat['slug']) ? 'active' : ''; ?>">
                    <?php echo htmlspecialchars($cat['nombre']); ?>
                </a>
            <?php endforeach; ?>
        </div>
        
        <?php $has_content = false; ?>

        <?php foreach ($servicios_por_categoria as $cat_id => $data): ?>
            <?php if (!empty($data['servicios'])): $has_content = true; ?>
                <section style="margin-bottom: 50px;">
                    <h2 style="border-bottom: 2px solid var(--color-principal); padding-bottom: 10px; margin-bottom: 20px;">
                        <?php echo htmlspecialchars($data['info']['nombre']); ?>
                    </h2>

                    <?php foreach ($data['servicios'] as $svc): 
                        // Obtener el paquete por defecto (el primero de la lista)
                        $default_paquete = $svc['paquetes'][0];
                        $default_price = $default_paquete['precio_paquete'];
                        $default_discount = $default_paquete['precio_rebajado'];

                        // Precio que se muestra por defecto
                        $display_price = $default_discount ?? $default_price;
                        
                        // Icono por defecto si no hay imagen
                        $default_image = '../assets/img/default-service.png';
                        $image_src = $svc['imagen_url'] ? '../' . $svc['imagen_url'] : $default_image;
                    ?>
                        <div class="service-block" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); margin-bottom: 15px;">
                            
                            <div class="service-block-header">
                                <div style="display: flex; align-items: center;">
                                    <img src="<?php echo htmlspecialchars($image_src); ?>" alt="<?php echo htmlspecialchars($svc['nombre']); ?>" class="service-image">

                                    <div class="service-info">
                                        <h3><?php echo htmlspecialchars($svc['nombre']); ?> 
                                            <span style="font-size: 0.7em; color: var(--color-acento);">
                                                (Velocidad: <?php echo ucfirst($svc['velocidad']); ?>)
                                            </span>
                                        </h3>
                                        <p style="color: #6c757d; font-size: 0.9em; margin-top: 5px;">
                                            <?php echo htmlspecialchars($svc['descripcion_corta']); ?>
                                            <a href="servicio_detalle.php?slug=<?php echo $svc['url_slug']; ?>"> Leer más</a>
                                        </p>
                                    </div>
                                </div>
                                
                                <div style="text-align: right;">
                                    <div class="price-section">
                                        <span id="total-cost_<?php echo $svc['id']; ?>">
                                            $<?php echo number_format($display_price, 2); ?>
                                        </span>
                                        <small style="font-size: 0.5em; display: block;">Precio Total</small>
                                    </div>

                                    <button class="btn-primary" 
                                        <?php echo $is_logged_in ? '' : 'disabled'; ?>
                                        onclick="document.getElementById('order-form-<?php echo $svc['id']; ?>').style.display='block';">
                                        <?php echo $is_logged_in ? 'Hacer Pedido' : 'Iniciar Sesión'; ?>
                                    </button>
                                </div>
                            </div>
                            
                            <div id="order-form-<?php echo $svc['id']; ?>" style="border-top: 1px dashed #ccc; margin-top: 15px; padding-top: 15px; display: none;">
                                <h4>Formulario de Pedido</h4>
                                <form action="add_to_cart.php" method="post">
                                    <input type="hidden" name="service_id" value="<?php echo $svc['id']; ?>">
                                    
                                    <input type="hidden" name="cantidad" id="form_cantidad_<?php echo $svc['id']; ?>" value="<?php echo $default_paquete['cantidad']; ?>">
                                    <input type="hidden" name="costo_total_fijo" id="form_total_cost_<?php echo $svc['id']; ?>" value="<?php echo $display_price; ?>">
                                    
                                    <div class="form-group">
                                        <label for="paquete_select_<?php echo $svc['id']; ?>">Selecciona un Paquete:</label>
                                        <select name="paquete_id" id="paquete_select_<?php echo $svc['id']; ?>" class="form-control" onchange="updatePackage(<?php echo $svc['id']; ?>)">
                                            <?php foreach ($svc['paquetes'] as $paq):
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
                                        <label for="link_destino_<?php echo $svc['id']; ?>">Enlace/Link de Destino:</label>
                                        <input type="url" name="link_destino" id="link_destino_<?php echo $svc['id']; ?>" placeholder="Ej: https://instagram.com/tu_perfil" required>
                                    </div>
                                    
                                    <div style="text-align: center; margin-top: 15px;">
                                        <button type="submit" class="btn-primary">Añadir al Carrito</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>

                </section>
            <?php endif; ?>
        <?php endforeach; ?>
        
        <?php if (!$has_content && empty($current_category_slug)): ?>
            <p style="text-align: center; padding: 30px; background: #f4f4f4; border-radius: 8px;">No hay servicios creados ni activos en ninguna categoría. ¡Es hora de usar el Admin Panel!</p>
        <?php elseif (!$has_content && !empty($current_category_slug)): ?>
            <p style="text-align: center; padding: 30px; background: #f4f4f4; border-radius: 8px;">No hay servicios activos en esta categoría.</p>
        <?php endif; ?>

    </main>
    
    <a href="https://wa.me/<?php echo urlencode($whatsapp_num); ?>" class="whatsapp-float" target="_blank" title="Contacta con el Administrador">
        💬 
    </a>
</body>
</html>