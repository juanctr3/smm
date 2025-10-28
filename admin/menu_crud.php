<?php
// Guardar en: smm_panel/admin/menu_crud.php

require_once './auth_check.php';
require_once '../includes/db_connect.php';
require_once '../includes/config_global.php';

$message = $error = '';
$edit_item = null;

// Lógica de CREAR/ACTUALIZAR ÍTEM
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $item_id = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
    $nombre = trim($_POST['nombre']);
    $url = trim($_POST['url']);
    $orden = (int)$_POST['orden'];
    $ubicacion = $_POST['ubicacion'];
    $activo = isset($_POST['activo']) ? 1 : 0;

    if (empty($nombre) || empty($url)) {
        $error = "El Nombre y la URL son obligatorios.";
    } else {
        if ($item_id == 0) {
            // INSERTAR NUEVO ÍTEM
            $stmt = $conn->prepare("INSERT INTO menu_items (nombre, url, orden, ubicacion, activo) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssisi", $nombre, $url, $orden, $ubicacion, $activo);
            
            if ($stmt->execute()) {
                $message = "Ítem de menú creado con éxito.";
            } else {
                $error = "Error al crear: " . $conn->error;
            }
        } else {
            // ACTUALIZAR ÍTEM EXISTENTE
            $stmt = $conn->prepare("UPDATE menu_items SET nombre=?, url=?, orden=?, ubicacion=?, activo=? WHERE id=?");
            $stmt->bind_param("ssisii", $nombre, $url, $orden, $ubicacion, $activo, $item_id);
            
            if ($stmt->execute()) {
                $message = "Ítem de menú actualizado con éxito.";
            } else {
                $error = "Error al actualizar: " . $conn->error;
            }
        }
        $stmt->close();
    }
}

// Lógica para CARGAR DATOS EN MODO EDICIÓN
if (isset($_GET['edit_id'])) {
    $edit_id = (int)$_GET['edit_id'];
    $stmt = $conn->prepare("SELECT * FROM menu_items WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_item = $result->fetch_assoc();
    $stmt->close();
}

// Lógica para ELIMINAR
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM menu_items WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    
    if ($stmt->execute()) {
        $message = "Ítem eliminado con éxito.";
    } else {
        $error = "Error al eliminar.";
    }
    $stmt->close();
    header("Location: menu_crud.php"); // Redirigir
    exit;
}

// Obtener todos los ítems para listarlos
$menu_items = $conn->query("SELECT * FROM menu_items ORDER BY ubicacion, orden ASC");

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Menús | Admin - <?php echo get_config('SITE_NAME'); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <?php include './sidebar_menu_css.php'; ?>
</head>
<body>
    <?php include './sidebar_menu.php'; ?>

    <main class="admin-content">
        <h1>🛠️ Gestión de Enlaces (Menú Dinámico)</h1>
        <p>Controla los enlaces que aparecen en la navegación principal del sitio.</p>

        <?php if (!empty($message)): ?><p style="color: green; background: #d4edda; padding: 10px; border-radius: 5px;"><?php echo $message; ?></p><?php endif; ?>
        <?php if (!empty($error)): ?><p style="color: red; background: #f8d7da; padding: 10px; border-radius: 5px;"><?php echo $error; ?></p><?php endif; ?>

        <div style="background: #f4f4f4; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 30px;">
            <h2>📝 <?php echo $edit_item ? 'Editar Ítem: ' . htmlspecialchars($edit_item['nombre']) : 'Añadir Nuevo Enlace'; ?></h2>
            <form action="menu_crud.php" method="post">
                <input type="hidden" name="item_id" value="<?php echo $edit_item ? $edit_item['id'] : 0; ?>">

                <div style="display: flex; gap: 20px;">
                    <div class="form-group" style="flex: 2;">
                        <label for="nombre">Nombre del Enlace:</label>
                        <input type="text" name="nombre" id="nombre" value="<?php echo $edit_item ? htmlspecialchars($edit_item['nombre']) : ''; ?>" required>
                    </div>
                    <div class="form-group" style="flex: 3;">
                        <label for="url">URL de Destino:</label>
                        <input type="text" name="url" id="url" placeholder="Ej: /servicios.php o https://blog.com" value="<?php echo $edit_item ? htmlspecialchars($edit_item['url']) : ''; ?>" required>
                    </div>
                </div>

                <div style="display: flex; gap: 20px;">
                    <div class="form-group" style="flex: 1;">
                        <label for="orden">Orden (Prioridad Baja = 0, Alta = 10):</label>
                        <input type="number" name="orden" id="orden" value="<?php echo $edit_item ? htmlspecialchars($edit_item['orden']) : 0; ?>">
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label for="ubicacion">Ubicación:</label>
                        <select name="ubicacion" id="ubicacion">
                            <option value="header" <?php if ($edit_item && $edit_item['ubicacion'] == 'header') echo 'selected'; ?>>Cabecera (Menú Principal)</option>
                            <option value="footer" <?php if ($edit_item && $edit_item['ubicacion'] == 'footer') echo 'selected'; ?>>Pie de Página</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <input type="checkbox" name="activo" id="activo" <?php if ($edit_item == null || $edit_item['activo'] == 1) echo 'checked'; ?>>
                    <label for="activo" style="display: inline;">Enlace Activo / Visible</label>
                </div>

                <button type="submit" class="btn-primary" style="margin-top: 15px;"><?php echo $edit_item ? 'Guardar Cambios' : 'Crear Enlace'; ?></button>
                <?php if ($edit_item): ?>
                    <a href="menu_crud.php" class="nav-link" style="margin-left: 15px;">Cancelar Edición</a>
                <?php endif; ?>
            </form>
        </div>

        <h2>Enlaces Activos</h2>
        <table style="width: 100%; border-collapse: collapse; background: white; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <thead>
                <tr style="background: #e9ecef;">
                    <th style="padding: 15px; text-align: left;">Orden</th>
                    <th>Nombre</th>
                    <th>URL</th>
                    <th>Ubicación</th>
                    <th>Activo</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($menu_items && $menu_items->num_rows > 0): ?>
                    <?php while($row = $menu_items->fetch_assoc()): ?>
                    <tr style="<?php echo $row['activo'] ? '' : 'background-color: #ffe0e0;'; ?>">
                        <td style="padding: 10px; text-align: center;"><?php echo $row['orden']; ?></td>
                        <td><?php echo htmlspecialchars($row['nombre']); ?></td>
                        <td><?php echo htmlspecialchars($row['url']); ?></td>
                        <td><?php echo htmlspecialchars(ucfirst($row['ubicacion'])); ?></td>
                         <td style="text-align: center;"><?php echo $row['activo'] ? '✅' : '❌'; ?></td>
                        <td style="text-align: center;">
                            <a href="menu_crud.php?edit_id=<?php echo $row['id']; ?>" class="nav-link">Editar</a> | 
                            <a href="menu_crud.php?delete_id=<?php echo $row['id']; ?>" onclick="return confirm('¿Seguro de eliminar?');" style="color: red;">Eliminar</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" style="text-align: center; padding: 20px;">No hay enlaces de menú creados.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

    </main>
</body>
</html>