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

// Lógica de CREAR/ACTUALIZAR SERVICIO
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 1. Recolección y Saneamiento de Datos
    $service_id = isset($_POST['service_id']) ? (int)$_POST['service_id'] : 0;
    $categoria_id = (int)$_POST['categoria_id'];
    $nombre = trim($_POST['nombre']);
    $descripcion_larga = trim($_POST['descripcion_larga']);
    $precio = (float)$_POST['precio'];
    $min_cantidad = (int)$_POST['min_cantidad'];
    $max_cantidad = (int)$_POST['max_cantidad'];
    $velocidad = $_POST['velocidad'];
    $activo = isset($_POST['activo']) ? 1 : 0;

    // Campos SEO (¡CRÍTICO!)
    $meta_titulo = trim($_POST['meta_titulo']);
    $meta_descripcion = trim($_POST['meta_descripcion']);
    // Asegura un slug limpio y sin errores
    $url_slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $_POST['url_slug']))); 
    
    // Validación
    if (empty($nombre) || empty($descripcion_larga) || $precio <= 0) {
        $error = "Nombre, Descripción y Precio son obligatorios.";
    } else {
        if ($service_id == 0) {
            // INSERTAR NUEVO SERVICIO
            $stmt = $conn->prepare("INSERT INTO servicios (categoria_id, nombre, descripcion_larga, precio, min_cantidad, max_cantidad, velocidad, meta_titulo, meta_descripcion, url_slug, activo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isddiiisssi", $categoria_id, $nombre, $descripcion_larga, $precio, $min_cantidad, $max_cantidad, $velocidad, $meta_titulo, $meta_descripcion, $url_slug, $activo);
            
            if ($stmt->execute()) {
                $message = "Servicio '{$nombre}' creado con éxito.";
            } else {
                $error = "Error al crear: " . $conn->error;
            }
        } else {
            // ACTUALIZAR SERVICIO EXISTENTE
            $stmt = $conn->prepare("UPDATE servicios SET categoria_id=?, nombre=?, descripcion_larga=?, precio=?, min_cantidad=?, max_cantidad=?, velocidad=?, meta_titulo=?, meta_descripcion=?, url_slug=?, activo=? WHERE id=?");
            $stmt->bind_param("isddiiisssii", $categoria_id, $nombre, $descripcion_larga, $precio, $min_cantidad, $max_cantidad, $velocidad, $meta_titulo, $meta_descripcion, $url_slug, $activo, $service_id);
            
            if ($stmt->execute()) {
                $message = "Servicio '{$nombre}' actualizado con éxito.";
            } else {
                $error = "Error al actualizar: " . $conn->error;
            }
        }
        $stmt->close();
    }
}

// Lógica para EDITAR (Cargar datos)
if (isset($_GET['edit_id'])) {
    $edit_id = (int)$_GET['edit_id'];
    $stmt = $conn->prepare("SELECT * FROM servicios WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_service = $result->fetch_assoc();
    $stmt->close();
}

// Lógica para ELIMINAR
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM servicios WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        $message = "Servicio eliminado con éxito.";
    } else {
        $error = "Error al eliminar.";
    }
    $stmt->close();
    header("Location: servicios_crud.php");
    exit;
}

// Obtener todos los servicios para listarlos
$sql_list = "SELECT s.*, c.nombre AS categoria_nombre FROM servicios s JOIN categorias c ON s.categoria_id = c.id";
if ($category_id > 0) {
    $sql_list .= " WHERE s.categoria_id = $category_id";
}
$servicios = $conn->query($sql_list);

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Servicios | Admin - <?php echo get_config('SITE_NAME'); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <?php include './sidebar_menu_css.php'; // Incluye los estilos de la barra lateral ?>
</head>
<body>
    <?php include './sidebar_menu.php'; ?>

    <main class="admin-content">
        <h1>🛠️ Gestión de Servicios</h1>
        <p>Añade y configura los productos (Followers, Likes) que se mostrarán en la web. **La configuración SEO es obligatoria.**</p>

        <?php if (!empty($message)): ?><p style="color: green; background: #d4edda; padding: 10px; border-radius: 5px;"><?php echo $message; ?></p><?php endif; ?>
        <?php if (!empty($error)): ?><p style="color: red; background: #f8d7da; padding: 10px; border-radius: 5px;"><?php echo $error; ?></p><?php endif; ?>

        <div style="background: #f4f4f4; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 30px;">
            <h2>📝 <?php echo $edit_service ? 'Editar Servicio: ' . htmlspecialchars($edit_service['nombre']) : 'Añadir Nuevo Servicio'; ?></h2>
            <form action="servicios_crud.php" method="post">
                <input type="hidden" name="service_id" value="<?php echo $edit_service ? $edit_service['id'] : 0; ?>">

                <fieldset style="margin-bottom: 20px; padding: 15px; border: 1px solid #ccc;">
                    <legend style="font-weight: bold; padding: 0 10px;">Información General</legend>
                    
                    <div class="form-group">
                        <label for="categoria_id">Categoría (Red Social):</label>
                        <select name="categoria_id" id="categoria_id" required>
                            <?php foreach ($categorias_list as $id => $nombre): ?>
                                <option value="<?php echo $id; ?>" 
                                    <?php 
                                    $current_cat_id = $edit_service ? $edit_service['categoria_id'] : $category_id;
                                    if ($id == $current_cat_id) echo 'selected'; 
                                    ?>>
                                    <?php echo htmlspecialchars($nombre); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="nombre">Nombre del Servicio:</label>
                        <input type="text" name="nombre" id="nombre" value="<?php echo $edit_service ? htmlspecialchars($edit_service['nombre']) : ''; ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="descripcion_larga">Descripción Detallada (¡Contenido SEO: Mín. 300 palabras!):</label>
                        <textarea name="descripcion_larga" id="descripcion_larga" rows="5" required><?php echo $edit_service ? htmlspecialchars($edit_service['descripcion_larga']) : ''; ?></textarea>
                    </div>

                    <div style="display: flex; gap: 20px;">
                        <div class="form-group" style="flex: 1;">
                            <label for="precio">Precio (x 1 unidad):</label>
                            <input type="number" step="0.0001" name="precio" id="precio" value="<?php echo $edit_service ? htmlspecialchars($edit_service['precio']) : ''; ?>" required>
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label for="min_cantidad">Mín. Cantidad:</label>
                            <input type="number" name="min_cantidad" id="min_cantidad" value="<?php echo $edit_service ? htmlspecialchars($edit_service['min_cantidad']) : 100; ?>" required>
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label for="max_cantidad">Máx. Cantidad:</label>
                            <input type="number" name="max_cantidad" id="max_cantidad" value="<?php echo $edit_service ? htmlspecialchars($edit_service['max_cantidad']) : 10000; ?>" required>
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label for="velocidad">Velocidad:</label>
                            <select name="velocidad" id="velocidad">
                                <option value="rapida" <?php if ($edit_service && $edit_service['velocidad'] == 'rapida') echo 'selected'; ?>>Rápida</option>
                                <option value="media" <?php if ($edit_service && $edit_service['velocidad'] == 'media') echo 'selected'; ?>>Media</option>
                                <option value="lenta" <?php if ($edit_service && $edit_service['velocidad'] == 'lenta') echo 'selected'; ?>>Lenta</option>
                            </select>
                        </div>
                    </div>
                </fieldset>

                <fieldset style="margin-bottom: 20px; padding: 15px; border: 1px solid #ccc;">
                    <legend style="font-weight: bold; padding: 0 10px; color: var(--color-acento);">SEO Avanzado (Posicionamiento)</legend>

                    <div class="form-group">
                        <label for="meta_titulo">Meta Título (Máx. 70 caracteres - ¡Palabra Clave Principal!):</label>
                        <input type="text" name="meta_titulo" id="meta_titulo" maxlength="70" value="<?php echo $edit_service ? htmlspecialchars($edit_service['meta_titulo']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="meta_descripcion">Meta Descripción (Máx. 160 caracteres - Llamada a la Acción):</label>
                        <textarea name="meta_descripcion" id="meta_descripcion" maxlength="160" rows="2"><?php echo $edit_service ? htmlspecialchars($edit_service['meta_descripcion']) : ''; ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="url_slug">URL Amigable (Slug) - Sin espacios:</label>
                        <input type="text" name="url_slug" id="url_slug" placeholder="Ej: seguidores-reales-rapidos" value="<?php echo $edit_service ? htmlspecialchars($edit_service['url_slug']) : ''; ?>" required>
                    </div>
                </fieldset>

                <div class="form-group">
                    <input type="checkbox" name="activo" id="activo" <?php if ($edit_service == null || $edit_service['activo'] == 1) echo 'checked'; ?>>
                    <label for="activo" style="display: inline;">Servicio Activo / Visible</label>
                </div>

                <button type="submit" class="btn-primary" style="margin-top: 15px;"><?php echo $edit_service ? 'Guardar Cambios' : 'Crear Servicio'; ?></button>
                <?php if ($edit_service): ?>
                    <a href="servicios_crud.php" class="nav-link" style="margin-left: 15px;">Cancelar Edición</a>
                <?php endif; ?>
            </form>
        </div>

        <h2>Lista de Servicios Creados</h2>
        <table style="width: 100%; border-collapse: collapse; background: white; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <thead>
                <tr style="background: #e9ecef;">
                    <th style="padding: 15px; text-align: left;">ID</th>
                    <th style="text-align: left;">Categoría</th>
                    <th style="text-align: left;">Nombre</th>
                    <th style="text-align: center;">Precio/Unidad</th>
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
                        <td style="text-align: center;">$<?php echo number_format($row['precio'], 4); ?></td>
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