<?php
// Guardar en: smm_panel/admin/categorias.php

require_once './auth_check.php';
require_once '../includes/db_connect.php';
require_once '../includes/config_global.php';

$message = $error = '';

// Lógica de AGREGAR CATEGORÍA
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add') {
    $nombre = trim($_POST['nombre']);
    
    // Generación del slug (amigable para URLs y SEO)
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $nombre)));
    
    // NOTA: La subida de imagen/icono es una funcionalidad avanzada. 
    // Por ahora, usamos una ruta placeholder:
    $icono_url = 'assets/img/' . $slug . '-icon.png'; 

    if (empty($nombre)) {
        $error = "El nombre de la categoría es obligatorio.";
    } else {
        // Usamos una consulta preparada para insertar datos de forma segura
        $stmt = $conn->prepare("INSERT INTO categorias (nombre, slug, icono_url) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $nombre, $slug, $icono_url);
        
        if ($stmt->execute()) {
            $message = "Categoría '{$nombre}' creada con éxito.";
        } else {
            // Error común: Nombre duplicado (por UNIQUE en la BD)
            if ($conn->errno == 1062) {
                $error = "Error: Ya existe una categoría con este nombre.";
            } else {
                $error = "Error al crear la categoría: " . $conn->error;
            }
        }
        $stmt->close();
    }
}

// Lógica de ELIMINAR CATEGORÍA
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    
    // Intentamos eliminar la categoría
    $stmt = $conn->prepare("DELETE FROM categorias WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    
    if ($stmt->execute()) {
        $message = "Categoría eliminada con éxito.";
    } else {
        // El ON DELETE RESTRICT en la tabla servicios previene que se borre si tiene servicios.
        $error = "Error al eliminar. Asegúrate de que NO haya servicios relacionados con esta categoría.";
    }
    $stmt->close();
    // Redirigir para limpiar el parámetro GET y evitar re-ejecución
    header("Location: categorias.php?msg=" . urlencode($message) . "&err=" . urlencode($error)); 
    exit;
}

// Obtener todas las categorías para listarlas, y contar cuántos servicios tiene cada una
$categorias_result = $conn->query("
    SELECT 
        c.*, 
        (SELECT COUNT(id) FROM servicios s WHERE s.categoria_id = c.id) as servicio_count
    FROM categorias c
    ORDER BY c.nombre ASC
");

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categorías | Admin - <?php echo get_config('SITE_NAME'); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <?php include './sidebar_menu_css.php'; ?>
    <style>
        /* Estilos específicos para la gestión */
        .category-row td { padding: 10px; border-bottom: 1px solid #eee; }
    </style>
</head>
<body>
    <?php include './sidebar_menu.php'; ?>

    <main class="admin-content">
        <h1>🏷️ Gestión de Categorías (Redes Sociales)</h1>
        
        <?php 
        // Mostrar mensajes después de la redirección (GET)
        if (isset($_GET['msg']) && !empty($_GET['msg'])): ?>
            <p style="color: green; background: #d4edda; padding: 10px; border-radius: 5px;"><?php echo htmlspecialchars($_GET['msg']); ?></p>
        <?php endif; ?>
        <?php 
        if (!empty($error) || (isset($_GET['err']) && !empty($_GET['err']))): ?>
            <p style="color: red; background: #f8d7da; padding: 10px; border-radius: 5px;"><?php echo htmlspecialchars($error . (isset($_GET['err']) ? $_GET['err'] : '')); ?></p>
        <?php endif; ?>

        <div style="background: #f4f4f4; padding: 20px; border-radius: 8px; margin-bottom: 30px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h2>➕ Añadir Nueva Categoría</h2>
            <form action="categorias.php" method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add">
                
                <div style="display: flex; gap: 20px;">
                    <div class="form-group" style="flex: 1;">
                        <label for="nombre">Nombre de la Red Social (Ej: Instagram)</label>
                        <input type="text" name="nombre" id="nombre" required placeholder="Nombre de la Categoría">
                    </div>
                    
                    <div class="form-group" style="flex: 1;">
                        <label for="icono">Foto/Icono (Solo texto placeholder por ahora)</label>
                        <input type="text" disabled placeholder="La subida de archivo real se desarrollará después">
                    </div>
                </div>
                
                <button type="submit" class="btn-primary" style="margin-top: 10px;">Guardar Categoría</button>
            </form>
        </div>

        <h2>Lista de Categorías</h2>
        <table style="width: 100%; border-collapse: collapse; background: white; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <thead>
                <tr style="background: #e9ecef;">
                    <th style="padding: 15px; text-align: left;">ID</th>
                    <th style="text-align: left;">Nombre</th>
                    <th style="text-align: center;">Servicios</th>
                    <th style="text-align: left;">URL Slug</th>
                    <th style="text-align: left;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($categorias_result && $categorias_result->num_rows > 0): ?>
                    <?php while($row = $categorias_result->fetch_assoc()): ?>
                    <tr class="category-row">
                        <td><?php echo $row['id']; ?></td>
                        <td><?php echo htmlspecialchars($row['nombre']); ?></td>
                        <td style="text-align: center;">
                            <span style="font-weight: bold; color: <?php echo $row['servicio_count'] > 0 ? 'var(--color-principal)' : '#6c757d'; ?>;">
                                <?php echo number_format($row['servicio_count']); ?>
                            </span>
                        </td>
                        <td>/<?php echo htmlspecialchars($row['slug']); ?></td>
                        <td>
                            <a href="servicios_crud.php?cat_id=<?php echo $row['id']; ?>" class="nav-link">Ver/Añadir Servicios</a> | 
                            <a href="categorias.php?delete_id=<?php echo $row['id']; ?>" 
                                onclick="return confirm('¿Estás seguro de eliminar esta categoría? Si tiene servicios, la eliminación fallará.');" 
                                style="color: red;">Eliminar</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align: center; padding: 20px;">Aún no hay categorías creadas.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

    </main>
</body>
</html>