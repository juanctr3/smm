<?php
// Guardar en: smm_panel/servicios.php
ini_set('display_errors', 1); // Mostrar errores en pantalla
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'includes/db_connect.php';

$is_logged_in = isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;
$user_saldo = 0;

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
$categorias_result = $conn->query("SELECT id, nombre, icono_url, slug FROM categorias WHERE activa = 1 ORDER BY nombre ASC");

while ($cat = $categorias_result->fetch_assoc()) {
    $servicios_por_categoria[$cat['id']] = [
        'info' => $cat,
        'servicios' => []
    ];
    
    $servicios_result = $conn->query("SELECT * FROM servicios WHERE categoria_id = " . $cat['id'] . " AND activo = 1 ORDER BY nombre ASC");
    while ($svc = $servicios_result->fetch_assoc()) {
        $servicios_por_categoria[$cat['id']]['servicios'][] = $svc;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprar Seguidores y Likes | Servicios SMM</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script>
        // Función JS para calcular el costo en tiempo real
        function calculateCost(pricePerThousand, quantity) {
            const cost = (pricePerThousand / 1000) * quantity;
            document.getElementById('total-cost').innerText = cost.toFixed(2);
        }
    </script>
</head>
<body>
    <?php // Cabecera/navegación ?>
    <header class="header">
        <div class="logo">SMM Pro Panel</div>
        <nav>
            <a href="index.php" class="nav-link">Inicio</a>
            <a href="servicios.php" class="nav-link active">Servicios</a>
            <?php if ($is_logged_in): ?>
                <a href="cuenta.php" class="nav-link">Mi Cuenta (Saldo: $<?php echo number_format($user_saldo, 2); ?>)</a>
                <a href="logout.php" class="btn-primary">Cerrar Sesión</a>
            <?php else: ?>
                <a href="login.php" class="nav-link">Iniciar Sesión</a>
                <a href="registro.php" class="btn-primary">Registrarse</a>
            <?php endif; ?>
        </nav>
    </header>

    <main class="container" style="padding-top: 50px;">
        <h1 style="text-align: center; margin-bottom: 40px;">Catálogo de Servicios</h1>

        <?php if (!$is_logged_in): ?>
            <div style="background: #fff3cd; border: 1px solid #ffeeba; color: #856404; padding: 15px; border-radius: 5px; text-align: center; margin-bottom: 30px;">
                ⚠️ Debes <a href="login.php" style="font-weight: bold; color: #856404;">iniciar sesión</a> para realizar un pedido.
            </div>
        <?php endif; ?>

        <?php foreach ($servicios_por_categoria as $cat_id => $data): ?>
            <section style="margin-bottom: 50px;">
                <h2 style="border-bottom: 2px solid var(--color-principal); padding-bottom: 10px; margin-bottom: 20px;">
                    <?php echo htmlspecialchars($data['info']['nombre']); ?>
                </h2>

                <?php if (empty($data['servicios'])): ?>
                    <p>No hay servicios activos en esta categoría aún. Vuelve pronto.</p>
                <?php else: ?>
                    
                    <?php foreach ($data['servicios'] as $svc): ?>
                        <div class="service-block" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); margin-bottom: 15px;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <h3><?php echo htmlspecialchars($svc['nombre']); ?> <span style="font-size: 0.8em; color: var(--color-acento);">(Velocidad: <?php echo ucfirst($svc['velocidad']); ?>)</span></h3>
                                    <p style="color: #6c757d; font-size: 0.9em; margin-top: 5px;">
                                        <?php echo nl2br(htmlspecialchars(substr($svc['descripcion_larga'], 0, 150))) . '...'; ?>
                                        <a href="servicio_detalle.php?slug=<?php echo $svc['url_slug']; ?>"> Leer más</a>
                                    </p>
                                    <div style="margin-top: 10px; font-weight: bold;">
                                        Precio: <span style="color: var(--color-principal); font-size: 1.2em;">$<?php echo number_format($svc['precio'] / 1000, 4); ?></span> por unidad
                                    </div>
                                </div>
                                
                                <button class="btn-primary" 
                                    <?php echo $is_logged_in ? '' : 'disabled'; ?>
                                    onclick="document.getElementById('order-form-<?php echo $svc['id']; ?>').style.display='block';">
                                    <?php echo $is_logged_in ? 'Hacer Pedido' : 'Iniciar Sesión'; ?>
                                </button>
                            </div>
                            
                            <div id="order-form-<?php echo $svc['id']; ?>" style="border-top: 1px dashed #ccc; margin-top: 15px; padding-top: 15px; display: none;">
                                <h4>Formulario de Pedido</h4>
                                <form action="checkout.php" method="post">
                                    <input type="hidden" name="service_id" value="<?php echo $svc['id']; ?>">
                                    
                                    <div class="form-group">
                                        <label for="link_destino_<?php echo $svc['id']; ?>">Enlace/Link de Destino:</label>
                                        <input type="url" name="link_destino" id="link_destino_<?php echo $svc['id']; ?>" placeholder="Ej: https://instagram.com/tu_perfil" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="cantidad_<?php echo $svc['id']; ?>">Cantidad (Min: <?php echo $svc['min_cantidad']; ?> / Max: <?php echo $svc['max_cantidad']; ?>):</label>
                                        <input type="number" name="cantidad" id="cantidad_<?php echo $svc['id']; ?>" min="<?php echo $svc['min_cantidad']; ?>" max="<?php echo $svc['max_cantidad']; ?>" required value="<?php echo $svc['min_cantidad']; ?>"
                                            oninput="calculateCost(<?php echo $svc['precio'] / 1000; ?>, this.value)">
                                    </div>
                                    
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 15px;">
                                        <div style="font-weight: bold; font-size: 1.1em;">
                                            Costo Total: $<span id="total-cost">0.00</span> USD
                                        </div>
                                        <button type="submit" class="btn-primary">Confirmar Compra</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>

                <?php endif; ?>
            </section>
        <?php endforeach; ?>

    </main>
    
    <?php // Botón WhatsApp Flotante (ya implementado en CSS) ?>
    <a href="https://wa.me/TU_NUMERO_WHATSAPP_AQUI" class="whatsapp-float" target="_blank" title="Contacta con el Administrador">
        💬 
    </a>
</body>
</html>