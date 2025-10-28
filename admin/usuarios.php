<?php
// Guardar en: smm_panel/admin/usuarios.php

require_once './auth_check.php';
require_once '../includes/db_connect.php';
require_once '../includes/config_global.php';

$message = $error = '';

// Array de roles para validación
$roles_disponibles = ['cliente', 'admin'];

// =================================================================
// 1. LÓGICA DE ACTUALIZACIÓN DE USUARIO (Saldo o Rol)
// =================================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'update_user') {
    
    $user_id = (int)$_POST['user_id'];
    $nuevo_saldo = (float)$_POST['saldo'];
    $nuevo_rol = $_POST['rol'];

    if (!in_array($nuevo_rol, $roles_disponibles)) {
        $error = "Rol inválido.";
    } elseif ($nuevo_saldo < 0) {
        $error = "El saldo no puede ser negativo.";
    } else {
        // Usamos una consulta preparada para actualizar rol y saldo
        $stmt = $conn->prepare("UPDATE usuarios SET saldo = ?, rol = ? WHERE id = ?");
        $stmt->bind_param("dsi", $nuevo_saldo, $nuevo_rol, $user_id);
        
        if ($stmt->execute()) {
            $message = "Usuario #{$user_id} actualizado con éxito. Nuevo saldo: $". number_format($nuevo_saldo, 2);
        } else {
            $error = "Error al actualizar usuario: " . $conn->error;
        }
        $stmt->close();
    }
}

// 2. OBTENER LISTA DE TODOS LOS USUARIOS
$usuarios_result = $conn->query("
    SELECT 
        u.id, u.nombre, u.email, u.telefono, u.saldo, u.rol, u.fecha_registro,
        (SELECT COUNT(id) FROM pedidos p WHERE p.usuario_id = u.id) AS total_pedidos
    FROM usuarios u
    ORDER BY u.fecha_registro DESC
");

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios | Admin - <?php echo get_config('SITE_NAME'); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <?php include './sidebar_menu_css.php'; ?>
    <style>
        .user-row td { padding: 10px; border-bottom: 1px solid #eee; }
        .user-row input[type="number"], .user-row select { width: 100px; padding: 5px; border-radius: 3px; }
    </style>
</head>
<body>
    <?php include './sidebar_menu.php'; ?>

    <main class="admin-content">
        <h1>👥 Gestión de Usuarios</h1>
        <p>Administra las cuentas de tus clientes y el saldo disponible.</p>

        <?php if (!empty($message)): ?><p style="color: green; background: #d4edda; padding: 10px; border-radius: 5px;"><?php echo $message; ?></p><?php endif; ?>
        <?php if (!empty($error)): ?><p style="color: red; background: #f8d7da; padding: 10px; border-radius: 5px;"><?php echo $error; ?></p><?php endif; ?>

        <h2>Lista de Cuentas</h2>
        <table style="width: 100%; border-collapse: collapse; background: white; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <thead>
                <tr style="background: #f8f9fa;">
                    <th style="padding: 15px; text-align: left;">ID / Email</th>
                    <th style="text-align: left;">Teléfono (WA)</th>
                    <th style="text-align: center;">Registro</th>
                    <th style="text-align: center;">Pedidos</th>
                    <th style="text-align: left;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($usuarios_result && $usuarios_result->num_rows > 0): ?>
                    <?php while($row = $usuarios_result->fetch_assoc()): ?>
                    <tr class="user-row" style="<?php echo $row['rol'] === 'admin' ? 'background-color: #ffe4b2;' : ''; ?>">
                        <td>
                            <span style="font-weight: bold;">#<?php echo $row['id']; ?></span> - <?php echo htmlspecialchars($row['email']); ?><br>
                            <small><?php echo htmlspecialchars($row['nombre']); ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($row['telefono']); ?></td>
                        <td style="text-align: center;"><?php echo date("Y-m-d", strtotime($row['fecha_registro'])); ?></td>
                        <td style="text-align: center;"><?php echo number_format($row['total_pedidos']); ?></td>
                        <td>
                            <form action="usuarios.php" method="post" style="display: flex; flex-direction: column; gap: 5px;">
                                <input type="hidden" name="action" value="update_user">
                                <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                
                                <div style="display: flex; gap: 5px; align-items: center;">
                                    <label>Saldo:</label>
                                    <input type="number" step="0.01" name="saldo" value="<?php echo htmlspecialchars($row['saldo']); ?>">
                                </div>
                                
                                <div style="display: flex; gap: 5px; align-items: center;">
                                    <label>Rol:</label>
                                    <select name="rol">
                                        <?php foreach ($roles_disponibles as $rol): ?>
                                            <option value="<?php echo $rol; ?>" <?php if ($rol == $row['rol']) echo 'selected'; ?>>
                                                <?php echo ucfirst($rol); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <button type="submit" class="btn-primary" style="padding: 5px; font-size: 0.8em; background: #007bff; margin-top: 5px;">
                                    Guardar Cambios
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align: center; padding: 20px;">No hay usuarios registrados en el sistema.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

    </main>
</body>
</html>