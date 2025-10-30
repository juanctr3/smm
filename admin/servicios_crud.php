<?php
// Guardar en: smm_panel/admin/servicios_crud.php

require_once './auth_check.php';
require_once '../includes/db_connect.php';
require_once '../includes/config_global.php';

$message = $error = '';
$edit_service = null;
$category_id = isset($_GET['cat_id']) ? (int)$_GET['cat_id'] : 0;

// Obtener todas las categorías para el formulario
$categorias_result = $conn->query("SELECT id, nombre FROM categorias ORDER BY nombre ASC");
$categorias_list = [];
while ($row = $categorias_result->fetch_assoc()) {
    $categorias_list[$row['id']] = $row['nombre'];
}

// Función auxiliar para precargar valores en el formulario después de un POST fallido
function get_form_value($field_name, $edit_service = null) {
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST[$field_name])) {
        return htmlspecialchars($_POST[$field_name]);
    }
    if ($edit_service && isset($edit_service[$field_name])) {
        return htmlspecialchars($edit_service[$field_name]);
    }
    return '';
}

// Lógica de CREAR/ACTUALIZAR SERVICIO (Se activa si se presiona el botón "Guardar Cambios")
if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['action']) && !isset($_POST['servicio_id'])) { 
    
    // 1. Recolección y Saneamiento de Datos
    $service_id = isset($_POST['service_id']) ? (int)$_POST['service_id'] : 0;
    $categoria_id = (int)$_POST['categoria_id'];
    $nombre = trim($_POST['nombre']);
    $descripcion_larga = $_POST['descripcion_larga']; // Permitir HTML del editor
    $velocidad = $_POST['velocidad'];
    $activo = isset($_POST['activo']) ? 1 : 0;

    // Nuevos Campos de Contenido y Multimedia
    $descripcion_corta = trim($_POST['descripcion_corta']);
    $video_url = trim($_POST['video_url']);
    $keywords = trim($_POST['keywords']);
    
    // Campos SEO
    $meta_titulo = trim($_POST['meta_titulo']);
    $meta_descripcion = trim($_POST['meta_descripcion']);
    $url_slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $_POST['url_slug']))); 
    
    $imagen_url = isset($_POST['current_image_url']) ? trim($_POST['current_image_url']) : NULL;

    // --- Lógica de Subida de Imagen (Omitida por brevedad, pero debe estar aquí) ---
    // if (isset($_FILES['imagen_file']) && $_FILES['imagen_file']['error'] == 0) { ... }
    
    // Validación Final
    if (empty($nombre) || empty($descripcion_larga)) {
        $error = "Nombre y Descripción Detallada son obligatorios.";
        // Lógica para recargar datos en caso de error
    } elseif (empty($error)) { 
        
        // ... (Lógica de INSERT y UPDATE como se definió previamente) ...
        
        if ($service_id == 0) {
            // INSERTAR NUEVO SERVICIO (Solo campos generales)
            $stmt = $conn->prepare("INSERT INTO servicios (categoria_id, nombre, descripcion_larga, velocidad, descripcion_corta, imagen_url, video_url, keywords, meta_titulo, meta_descripcion, url_slug, activo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issssssssssi", $categoria_id, $nombre, $descripcion_larga, $velocidad, $descripcion_corta, $imagen_url, $video_url, $keywords, $meta_titulo, $meta_descripcion, $url_slug, $activo);
            
            if ($stmt->execute()) {
                $service_id = $conn->insert_id;
                $message = "Servicio '{$nombre}' creado con éxito. Ahora puedes añadir paquetes de precios.";
            } else {
                $error = "Error al crear: " . $conn->error;
            }
        } else {
            // ACTUALIZAR SERVICIO EXISTENTE
            $stmt = $conn->prepare("UPDATE servicios SET categoria_id=?, nombre=?, descripcion_larga=?, velocidad=?, descripcion_corta=?, imagen_url=?, video_url=?, keywords=?, meta_titulo=?, meta_descripcion=?, url_slug=?, activo=? WHERE id=?");
            $stmt->bind_param("issssssssssii", $categoria_id, $nombre, $descripcion_larga, $velocidad, $descripcion_corta, $imagen_url, $video_url, $keywords, $meta_titulo, $meta_descripcion, $url_slug, $activo, $service_id);
            
            if ($stmt->execute()) {
                $message = "Servicio '{$nombre}' actualizado con éxito.";
            } else {
                $error = "Error al actualizar: " . $conn->error;
            }
        }
        $stmt->close();
        
        // Redirigir al modo edición del servicio recién creado/actualizado
        if (empty($error)) {
            header("Location: servicios_crud.php?edit_id={$service_id}&msg=" . urlencode($message));
            exit;
        }
    }
}

// Lógica para EDITAR (Cargar datos)
if (isset($_GET['edit_id']) && empty($error)) {
    $edit_id = (int)$_GET['edit_id'];
    $stmt = $conn->prepare("SELECT * FROM servicios WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_service = $result->fetch_assoc();
    $stmt->close();
}

// Lógica para ELIMINAR (omitiendo por brevedad)
// ...

// Obtener todos los servicios para listarlos
$sql_list = "SELECT s.*, c.nombre AS categoria_nombre FROM servicios s JOIN categorias c ON s.categoria_id = c.id";
if ($category_id > 0) {
    $sql_list .= " WHERE s.categoria_id = $category_id";
}
$servicios = $conn->query($sql_list);


// Si hay mensaje GET después de la redirección, lo mostramos
if (isset($_GET['msg'])) {
    $message = htmlspecialchars($_GET['msg']);
}
if (isset($_GET['err'])) {
    $error = htmlspecialchars($_GET['err']);
}

// Bandera para saber si se debe cerrar la conexión al final del archivo
$should_close_conn = true;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Servicios | Admin - <?php echo get_config('SITE_NAME'); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <?php include './sidebar_menu_css.php'; ?>
    
    <script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    <script>
        tinymce.init({
            selector: '#descripcion_larga',
            plugins: 'advlist autolink lists link image charmap print preview anchor code',
            toolbar: 'undo redo | formatselect | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image code',
            height: 350
        });
    </script>
</head>
<body>
    <?php include './sidebar_menu.php'; ?>

    <main class="admin-content">
        <h1>🛠️ Gestión de Servicios</h1>
        <p>Añade y configura los productos (Followers, Likes) que se mostrarán en la web.</p>

        <?php if (!empty($message)): ?><p style="color: green; background: #d4edda; padding: 10px; border-radius: 5px;"><?php echo $message; ?></p><?php endif; ?>
        <?php if (!empty($error)): ?><p style="color: red; background: #f8d7da; padding: 10px; border-radius: 5px;"><?php echo $error; ?></p><?php endif; ?>

        <div style="background: #f4f4f4; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 30px;">
            <h2>📝 <?php echo $edit_service ? 'Editar Servicio: ' . htmlspecialchars($edit_service['nombre']) : 'Añadir Nuevo Servicio'; ?></h2>
            
            <form action="servicios_crud.php" method="post" enctype="multipart/form-data">
                <input type="hidden" name="service_id" value="<?php echo $edit_service ? $edit_service['id'] : 0; ?>">
                <input type="hidden" name="current_image_url" value="<?php echo $edit_service ? htmlspecialchars($edit_service['imagen_url']) : ''; ?>">

                <fieldset style="margin-bottom: 20px; padding: 15px; border: 1px solid #ccc;">
                    <legend style="font-weight: bold; padding: 0 10px;">Información General</legend>
                    
                    <div class="form-group">
                        <label for="categoria_id">Categoría (Red Social):</label>
                        <select name="categoria_id" id="categoria_id" required>
                            <?php foreach ($categorias_list as $id => $nombre): ?>
                                <option value="<?php echo $id; ?>" 
                                    <?php 
                                    $selected_cat = get_form_value('categoria_id', $edit_service);
                                    if (empty($selected_cat) && $category_id > 0) $selected_cat = $category_id;
                                    if (empty($selected_cat) && $edit_service) $selected_cat = $edit_service['categoria_id'];
                                    
                                    if ($id == $selected_cat) echo 'selected'; 
                                    ?>>
                                    <?php echo htmlspecialchars($nombre); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="nombre">Nombre del Servicio:</label>
                        <input type="text" name="nombre" id="nombre" value="<?php echo get_form_value('nombre', $edit_service); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="descripcion_corta">Descripción Corta (Resumen para catálogo):</label>
                        <input type="text" name="descripcion_corta" id="descripcion_corta" maxlength="255" value="<?php echo get_form_value('descripcion_corta', $edit_service); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="descripcion_larga">Descripción Detallada (¡Contenido SEO!):</label>
                        <textarea name="descripcion_larga" id="descripcion_larga" rows="5"><?php 
                            if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['descripcion_larga'])) {
                                echo $_POST['descripcion_larga'];
                            } elseif ($edit_service && isset($edit_service['descripcion_larga'])) {
                                echo htmlspecialchars($edit_service['descripcion_larga']);
                            }
                        ?></textarea>
                    </div>

                    <div style="display: flex; gap: 20px;">
                        <div class="form-group" style="flex: 1;">
                            <label for="velocidad">Velocidad:</label>
                            <select name="velocidad" id="velocidad">
                                <?php $current_speed = get_form_value('velocidad', $edit_service); ?>
                                <option value="rapida" <?php if ($current_speed == 'rapida') echo 'selected'; ?>>Rápida</option>
                                <option value="media" <?php if ($current_speed == 'media') echo 'selected'; ?>>Media</option>
                                <option value="lenta" <?php if ($current_speed == 'lenta') echo 'selected'; ?>>Lenta</option>
                            </select>
                        </div>
                        <div style="flex: 2;"></div>
                    </div>
                </fieldset>
                
                <fieldset style="margin-bottom: 20px; padding: 15px; border: 1px solid #ccc;">
                    <legend style="font-weight: bold; padding: 0 10px; color: #6c757d;">Multimedia y Palabras Clave</legend>
                    
                    <div class="form-group">
                        <label for="imagen_file">Imagen Principal del Servicio (PNG, JPG, WEBP - Máx. 1MB):</label>
                        <input type="file" name="imagen_file" id="imagen_file" accept="image/png, image/jpeg, image/webp">
                        
                        <?php if ($edit_service && $edit_service['imagen_url']): ?>
                            <p style="margin-top: 10px;">Imagen Actual:</p>
                            <img src="../<?php echo htmlspecialchars($edit_service['imagen_url']); ?>" alt="Imagen actual" style="max-width: 150px; height: auto; display: block; margin-top: 5px;">
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="video_url">URL de Video Explicativo (YouTube/Vimeo Opcional):</label>
                        <input type="url" name="video_url" id="video_url" value="<?php echo get_form_value('video_url', $edit_service); ?>" placeholder="Ej: https://youtu.be/video_id">
                    </div>
                    
                    <div class="form-group">
                        <label for="keywords">Palabras Clave Adicionales (Separadas por comas, opcional):</label>
                        <input type="text" name="keywords" id="keywords" value="<?php echo get_form_value('keywords', $edit_service); ?>" placeholder="followers, likes, instafollow">
                    </div>
                </fieldset>

                <fieldset style="margin-bottom: 20px; padding: 15px; border: 1px solid #ccc;">
                    <legend style="font-weight: bold; padding: 0 10px; color: var(--color-acento);">SEO Avanzado (Posicionamiento)</legend>

                    <div class="form-group">
                        <label for="meta_titulo">Meta Título (Máx. 70 caracteres - ¡Palabra Clave Principal!):</label>
                        <input type="text" name="meta_titulo" id="meta_titulo" maxlength="70" value="<?php echo get_form_value('meta_titulo', $edit_service); ?>">
                    </div>

                    <div class="form-group">
                        <label for="meta_descripcion">Meta Descripción (Máx. 160 caracteres - Llamada a la Acción):</label>
                        <textarea name="meta_descripcion" id="meta_descripcion" maxlength="160" rows="2"><?php 
                            if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['meta_descripcion'])) {
                                echo htmlspecialchars($_POST['meta_descripcion']);
                            } elseif ($edit_service && isset($edit_service['meta_descripcion'])) {
                                echo htmlspecialchars($edit_service['meta_descripcion']);
                            }
                        ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="url_slug">URL Amigable (Slug) - Sin espacios:</label>
                        <input type="text" name="url_slug" id="url_slug" placeholder="Ej: seguidores-reales-rapidos" value="<?php echo get_form_value('url_slug', $edit_service); ?>" required>
                    </div>
                </fieldset>

                <div class="form-group">
                    <input type="checkbox" name="activo" id="activo" <?php 
                        $current_activo = isset($_POST['activo']) ? 1 : ($edit_service ? $edit_service['activo'] : 1);
                        if ($current_activo == 1) echo 'checked'; 
                    ?>>
                    <label for="activo" style="display: inline;">Servicio Activo / Visible</label>
                </div>

                <button type="submit" class="btn-primary" style="margin-top: 15px;">Guardar Cambios del Servicio</button>
                <?php if ($edit_service): ?>
                    <a href="servicios_crud.php" class="nav-link" style="margin-left: 15px;">Cancelar Edición</a>
                <?php endif; ?>
            </form>
        </div>

        <?php if ($edit_service): ?>
            <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 30px;">
                <fieldset style="margin-bottom: 20px; padding: 15px; border: 1px solid var(--color-principal);">
                    <legend style="font-weight: bold; padding: 0 10px; color: var(--color-principal);">📦 Gestión de Paquetes de Precios</legend>
                    <p style="margin-bottom: 15px;">Define la cantidad de unidades y el precio total por cada paquete de venta.</p>

                    <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
                        <thead>
                            <tr style="background: #e9ecef;">
                                <th style="padding: 10px;">Cantidad</th>
                                <th>Precio (Total)</th>
                                <th>Oferta (Total)</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Se asume que $conn está disponible aquí porque movemos el cierre al final del script
                            $paquetes_result = $conn->query("SELECT * FROM servicios_paquetes WHERE servicio_id = " . $edit_service['id'] . " ORDER BY cantidad ASC");
                            if ($paquetes_result && $paquetes_result->num_rows > 0) {
                                while ($paq = $paquetes_result->fetch_assoc()): ?>
                                    <tr style="border-bottom: 1px solid #ddd; text-align: center;">
                                        <td style="padding: 10px;"><?php echo number_format($paq['cantidad']); ?></td>
                                        <td>$<?php echo number_format($paq['precio_paquete'], 2); ?></td>
                                        <td>
                                            <?php echo $paq['precio_rebajado'] ? '<span style="color: red; font-weight: bold;">$' . number_format($paq['precio_rebajado'], 2) . '</span>' : 'N/A'; ?>
                                        </td>
                                        <td>
                                            <a href="paquetes_crud.php?delete_id=<?php echo $paq['id']; ?>&service_id=<?php echo $edit_service['id']; ?>" 
                                                onclick="return confirm('¿Seguro de eliminar este paquete?');" 
                                                style="color: red;">Eliminar</a>
                                        </td>
                                    </tr>
                                <?php endwhile;
                            } else {
                                echo '<tr><td colspan="4" style="text-align: center; padding: 15px;">Aún no hay paquetes definidos.</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                    
                    <h4 style="margin-top: 20px;">➕ Añadir Nuevo Paquete</h4>
                    <form action="paquetes_crud.php" method="post" style="display: flex; gap: 10px; align-items: flex-end;">
                        <input type="hidden" name="servicio_id" value="<?php echo $edit_service['id']; ?>">
                        <input type="hidden" name="action" value="add_package">
                        
                        <div class="form-group" style="flex: 1;">
                            <label for="new_cantidad">Cantidad:</label>
                            <input type="number" name="new_cantidad" id="new_cantidad" required min="1" value="100">
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label for="new_precio">Precio Total:</label>
                            <input type="number" name="new_precio" id="new_precio" step="0.01" required placeholder="0.00">
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label for="new_rebajado">Precio Oferta (Opcional):</label>
                            <input type="number" name="new_rebajado" id="new_rebajado" step="0.01" placeholder="0.00">
                        </div>
                        <button type="submit" class="btn-primary" style="height: 40px; white-space: nowrap;">Guardar Paquete</button>
                    </form>
                </fieldset>
            </div>
        <?php endif; ?>
        <h2>Lista de Servicios Creados</h2>
        <table style="width: 100%; border-collapse: collapse; background: white; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <thead>
                <tr style="background: #e9ecef;">
                    <th style="padding: 15px; text-align: left;">ID</th>
                    <th style="text-align: left;">Categoría</th>
                    <th style="text-align: left;">Nombre</th>
                    <th style="text-align: center;">Velocidad</th>
                    <th style="text-align: center;">Activo</th>
                    <th style="text-align: left;">URL Slug</th>
                    <th style="text-align: left;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($servicios && $servicios->num_rows > 0): ?>
                    <?php while($row = $servicios->fetch_assoc()): ?>
                    <tr style="<?php echo $row['activo'] ? '' : 'background-color: #ffe0e0;'; ?>">
                        <td style="padding: 10px; text-align: center;"><?php echo $row['id']; ?></td>
                        <td><?php echo htmlspecialchars($row['categoria_nombre']); ?></td>
                        <td><?php echo htmlspecialchars($row['nombre']); ?></td>
                        <td style="text-align: center;"><?php echo ucfirst($row['velocidad']); ?></td>
                        <td style="text-align: center; color: <?php echo $row['activo'] ? 'green' : 'red'; ?>;">
                            <?php echo $row['activo'] ? '✅' : '❌'; ?>
                        </td>
                        <td>/<?php echo htmlspecialchars($row['url_slug']); ?></td>
                        <td style="text-align: center;">
                            <a href="servicios_crud.php?edit_id=<?php echo $row['id']; ?>" class="nav-link">Editar</a> | 
                            <a href="servicios_crud.php?delete_id=<?php echo $row['id']; ?>" onclick="return confirm('¿Seguro de eliminar?');" style="color: red;">Eliminar</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7" style="text-align: center; padding: 20px;">Aún no hay servicios creados.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

    </main>
</body>
</html>
<?php
// Cierre de la conexión al final del script
if (isset($conn) && $conn->ping()) {
    $conn->close();
}
?>