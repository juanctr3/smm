<?php
// Guardar en: smm_panel/admin/categorias.php

require_once './auth_check.php';
require_once '../includes/db_connect.php';
require_once '../includes/config_global.php';

$message = $error = '';
$edit_category = null;
$upload_dir = '../assets/img/';

// =================================================================
// 1. Lógica de CREAR / ACTUALIZAR CATEGORÍA
// =================================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    
    $category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
    $nombre = trim($_POST['nombre']);
    $activa = isset($_POST['activa']) ? 1 : 0;
    
    // Generación del slug (amigable para URLs y SEO)
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $nombre)));
    
    // 1.1: Inicializar icono_url con el valor actual si es edición y no hay nueva subida
    $icono_url = isset($_POST['current_icono_url']) ? trim($_POST['current_icono_url']) : NULL;
    
    // 1.2: Lógica de Subida de Archivo
    if (isset($_FILES['icono_file']) && $_FILES['icono_file']['error'] == 0) {
        $file_info = pathinfo($_FILES['icono_file']['name']);
        $file_ext = strtolower($file_info['extension']);
        $allowed_ext = ['png', 'jpg', 'jpeg', 'svg', 'webp']; // ¡WEBP AÑADIDO!
        
        // Generar un nombre único basado en el slug
        $new_filename = $slug . '-icon-' . time() . '.' . $file_ext;
        $upload_path = $upload_dir . $new_filename;
        
        if (!in_array($file_ext, $allowed_ext)) {
            $error = "Solo se permiten PNG, JPG, JPEG, SVG o WEBP para el icono.";
        } elseif ($_FILES['icono_file']['size'] > 500000) { // Máximo 500 KB
            $error = "El archivo del icono es demasiado grande (Máx. 500 KB).";
        } elseif (move_uploaded_file($_FILES['icono_file']['tmp_name'], $upload_path)) {
            // Éxito: Guardamos la ruta relativa que se mostrará al cliente (sin el '../')
            $icono_url = 'assets/img/' . $new_filename;
        } else {
            $error = "Error al mover el archivo subido.";
        }
    }
    // 1.3: Fin Lógica de Subida de Archivo

    if (empty($nombre) || empty($slug)) {
        $error = "El nombre de la categoría es obligatorio.";
    } elseif (empty($error)) {
        if ($category_id == 0) {
            // --- A. CREAR NUEVA CATEGORÍA ---
            $stmt = $conn->prepare("INSERT INTO categorias (nombre, slug, icono_url, activa) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sssi", $nombre, $slug, $icono_url, $activa);
            
            if (!$stmt->execute()) {
                $error = ($conn->errno == 1062) ? "Error: Ya existe una categoría con este nombre." : "Error al crear la categoría: " . $conn->error;
            } else {
                $message = "Categoría '{$nombre}' creada con éxito.";
            }
        } else {
            // --- B. ACTUALIZAR CATEGORÍA EXISTENTE ---
            $stmt = $conn->prepare("UPDATE categorias SET nombre=?, slug=?, icono_url=?, activa=? WHERE id=?");
            $stmt->bind_param("sssii", $nombre, $slug, $icono_url, $activa, $category_id);
            
            if (!$stmt->execute()) {
                $error = ($conn->errno == 1062) ? "Error: Ya existe otra categoría con este nombre o slug." : "Error al actualizar la categoría: " . $conn->error;
            } else {
                $message = "Categoría '{$nombre}' actualizada con éxito.";
            }
        }
        $stmt->close();
        
        // Redirigir para limpiar el POST y el file-upload
        if (empty($error)) {
            header("Location: categorias.php?msg=" . urlencode($message)); 
            exit;
        }
    }
}

// 2. Lógica para CARGAR DATOS EN MODO EDICIÓN
if (isset($_GET['edit_id'])) {
    $edit_id = (int)$_GET['edit_id'];
    $stmt = $conn->prepare("SELECT * FROM categorias WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_category = $result->fetch_assoc();
    $stmt->close();
}

// 3. Lógica de ELIMINAR CATEGORÍA
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM categorias WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    
    if ($stmt->execute()) {
        $message = "Categoría eliminada con éxito.";
    } else {
        $error = "Error al eliminar. Asegúrate de que NO haya servicios relacionados con esta categoría.";
    }
    $stmt->close();
    header("Location: categorias.php?msg=" . urlencode($message) . "&err=" . urlencode($error)); 
    exit;
}

// 4. Obtener todas las categorías para listarlas
$categorias_result = $conn->query("
    SELECT 
        c.*, 
        (SELECT COUNT(id) FROM servicios s WHERE s.categoria_id = c.id) as servicio_count
    FROM categorias c
    ORDER BY c.nombre ASC
");

// Cerramos la conexión AHORA
$conn->close();

if (isset($_GET['msg'])) { $message = htmlspecialchars($_GET['msg']); }
if (isset($_GET['err'])) { $error = htmlspecialchars($_GET['err']); }
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
        .category-row td { padding: 10px; border-bottom: 1px solid #eee; }
        .icon-preview { max-width: 50px; height: 50px; object-fit: contain; margin-right: 15px; border: 1px solid #ddd; padding: 5px; border-radius: 4px;}
    </style>
</head>
<body>
    <?php include './sidebar_menu.php'; ?>

    <main class="admin-content">
        <h1>🏷️ Gestión de Categorías (Redes Sociales)</h1>
        <p>Crea las categorías principales para organizar tus servicios.</p>

        <?php if (!empty($message)): ?><p style="color: green; background: #d4edda; padding: 10px; border-radius: 5px;"><?php echo $message; ?></p><?php endif; ?>
        <?php if (!empty($error)): ?><p style="color: red; background: #f8d7da; padding: 10px; border-radius: 5px;"><?php echo $error; ?></p><?php endif; ?>

        <div style="background: #f4f4f4; padding: 20px; border-radius: 8px; margin-bottom: 30px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h2><?php echo $edit_category ? '✏️ Editar Categoría: ' . htmlspecialchars($edit_category['nombre']) : '➕ Añadir Nueva Categoría'; ?></h2>
            
            <form action="categorias.php" method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="<?php echo $edit_category ? 'update' : 'add'; ?>">
                <input type="hidden" name="category_id" value="<?php echo $edit_category ? $edit_category['id'] : 0; ?>">
                <input type="hidden" name="current_icono_url" value="<?php echo $edit_category ? htmlspecialchars($edit_category['icono_url']) : ''; ?>">

                <div style="display: flex; gap: 20px;">
                    <div class="form-group" style="flex: 2;">
                        <label for="nombre">Nombre de la Categoría (Ej: Instagram, Facebook)</label>
                        <input type="text" name="nombre" id="nombre" required placeholder="Nombre de la Categoría" 
                               value="<?php echo $edit_category ? htmlspecialchars($edit_category['nombre']) : ''; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="icono_file">Subir Archivo de Ícono (PNG, JPG, SVG, WEBP - Máx. 500 KB):</label>
                    <input type="file" name="icono_file" id="icono_file" accept="image/png, image/jpeg, image/svg+xml, image/webp">
                    
                    <?php if ($edit_category && $edit_category['icono_url']): ?>
                        <p style="margin-top: 10px; display: flex; align-items: center;">
                            Ícono Actual: 
                            <img src="../<?php echo htmlspecialchars($edit_category['icono_url']); ?>" alt="Icono" class="icon-preview">
                            <small>Para cambiar, sube un nuevo archivo.</small>
                        </p>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <input type="checkbox" name="activa" id="activa" <?php if ($edit_category === null || $edit_category['activa'] == 1) echo 'checked'; ?>>
                    <label for="activa" style="display: inline;">Categoría Activa / Visible al Cliente</label>
                </div>
                
                <button type="submit" class="btn-primary" style="margin-top: 10px;">
                    <?php echo $edit_category ? 'Guardar Cambios' : 'Crear Categoría'; ?>
                </button>
                <?php if ($edit_category): ?>
                    <a href="categorias.php" class="nav-link" style="margin-left: 15px;">Cancelar Edición</a>
                <?php endif; ?>
            </form>
        </div>

        <h2>Lista de Categorías</h2>
        <table style="width: 100%; border-collapse: collapse; background: white; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <thead>
                <tr style="background: #e9ecef;">
                    <th style="padding: 15px; text-align: left;">ID / Nombre</th>
                    <th style="text-align: left;">Icono</th>
                    <th style="text-align: left;">URL Slug</th>
                    <th style="text-align: center;">Servicios</th>
                    <th style="text-align: center;">Activa</th>
                    <th style="text-align: left;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($categorias_result && $categorias_result->num_rows > 0): ?>
                    <?php while($row = $categorias_result->fetch_assoc()): ?>
                    <tr class="category-row">
                        <td>
                            #<?php echo $row['id']; ?> - <?php echo htmlspecialchars($row['nombre']); ?>
                        </td>
                        <td>
                            <?php if ($row['icono_url']): ?>
                                <img src="../<?php echo htmlspecialchars($row['icono_url']); ?>" alt="Icono" class="icon-preview">
                            <?php else: ?>
                                <span>(No Icono)</span>
                            <?php endif; ?>
                        </td>
                        <td>/<?php echo htmlspecialchars($row['slug']); ?></td>
                        <td style="text-align: center;">
                            <span style="font-weight: bold; color: <?php echo $row['servicio_count'] > 0 ? 'var(--color-principal)' : '#6c757d'; ?>;">
                                <?php echo number_format($row['servicio_count']); ?>
                            </span>
                        </td>
                        <td style="text-align: center;"><?php echo $row['activa'] ? '✅' : '❌'; ?></td>
                        <td>
                            <a href="categorias.php?edit_id=<?php echo $row['id']; ?>" class="nav-link">Editar</a> | 
                            <a href="servicios_crud.php?cat_id=<?php echo $row['id']; ?>" class="nav-link">Ver/Añadir Servicios</a> | 
                            <a href="categorias.php?delete_id=<?php echo $row['id']; ?>" 
                                onclick="return confirm('¿Seguro de eliminar esta categoría? Si tiene servicios, la eliminación fallará.');" 
                                style="color: red;">Eliminar</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" style="text-align: center; padding: 20px;">Aún no hay categorías creadas.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

    </main>
</body>
</html>