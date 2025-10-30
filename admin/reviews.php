<?php
// Guardar en: smm_panel/admin/reviews.php

require_once './auth_check.php';
require_once '../includes/db_connect.php';
require_once '../includes/config_global.php';

$message = $error = '';

// =================================================================
// 1. Lógica de APROBAR / DESACTIVAR Reseña
// =================================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'update_review') {
    
    $review_id = (int)$_POST['review_id'];
    // El formulario usa 'activo' para enviar el valor 0 o 1
    $aprobada_value = (int)$_POST['activo']; 

    if ($review_id > 0) {
        // Usamos el nombre de columna REAL de tu BD: 'aprobada'
        $stmt = $conn->prepare("UPDATE reviews SET aprobada = ? WHERE id = ?");
        $stmt->bind_param("ii", $aprobada_value, $review_id);
        
        if ($stmt->execute()) {
            $status_txt = $aprobada_value == 1 ? 'Aprobada/Activada' : 'Desactivada/Rechazada';
            $message = "Reseña #{$review_id} ha sido {$status_txt} con éxito.";
        } else {
            $error = "Error al actualizar la reseña: " . $conn->error;
        }
        $stmt->close();
    }
}

// =================================================================
// 2. Lógica de ELIMINAR Reseña
// =================================================================
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM reviews WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    
    if ($stmt->execute()) {
        $message = "Reseña eliminada permanentemente con éxito.";
    } else {
        $error = "Error al eliminar la reseña.";
    }
    $stmt->close();
    header("Location: reviews.php"); // Redirigir para limpiar el parámetro GET
    exit;
}

// 3. Obtener todas las reseñas para listarlas
// Se usan ALIAS (AS) para que los nombres de columna coincidan con el HTML/PHP
$reviews_result = $conn->query("
    SELECT 
        r.id, 
        r.calificacion AS puntuacion,       /* Tu columna calificacion se convierte en puntuacion */
        r.comentario, 
        r.fecha_review AS fecha_creacion,   /* Tu columna fecha_review se convierte en fecha_creacion */
        r.aprobada AS activo,               /* Tu columna aprobada se convierte en activo */
        u.nombre AS cliente_nombre,
        s.nombre AS servicio_nombre,
        p.id AS pedido_id
    FROM reviews r
    JOIN usuarios u ON r.usuario_id = u.id
    JOIN pedidos p ON r.pedido_id = p.id
    JOIN servicios s ON p.servicio_id = s.id
    ORDER BY fecha_creacion DESC
");

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Moderación de Reseñas | Admin - <?php echo get_config('SITE_NAME'); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <?php include './sidebar_menu_css.php'; ?>
    <style>
        .review-row td { padding: 15px; border-bottom: 1px solid #eee; vertical-align: top; }
        .review-row.pending { background-color: #fff8e1; } /* Amarillo suave para pendientes */
        .stars { color: gold; font-size: 1.2em; }
    </style>
</head>
<body>
    <?php include './sidebar_menu.php'; ?>

    <main class="admin-content">
        <h1>⭐ Moderación de Reseñas</h1>
        <p>Aprueba o rechaza los comentarios de tus clientes antes de que aparezcan públicamente.</p>

        <?php if (!empty($message)): ?><p style="color: green; background: #d4edda; padding: 10px; border-radius: 5px;"><?php echo $message; ?></p><?php endif; ?>
        <?php if (!empty($error)): ?><p style="color: red; background: #f8d7da; padding: 10px; border-radius: 5px;"><?php echo $error; ?></p><?php endif; ?>

        <h2>Reseñas Recibidas (Total: <?php echo $reviews_result->num_rows ?? 0; ?>)</h2>
        <table style="width: 100%; border-collapse: collapse; background: white; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <thead>
                <tr style="background: #f8f9fa;">
                    <th style="padding: 15px; text-align: left;">ID / Cliente</th>
                    <th style="text-align: left;">Comentario</th>
                    <th style="text-align: center;">Puntuación</th>
                    <th style="text-align: left;">Pedido / Servicio</th>
                    <th style="text-align: center;">Estado</th>
                    <th style="text-align: left;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($reviews_result && $reviews_result->num_rows > 0): ?>
                    <?php while($row = $reviews_result->fetch_assoc()): ?>
                    <tr class="review-row <?php echo $row['activo'] == 0 ? 'pending' : ''; ?>">
                        <td>
                            #<?php echo $row['id']; ?> | <?php echo htmlspecialchars($row['cliente_nombre']); ?><br>
                            <small>Fecha: <?php echo date("Y-m-d", strtotime($row['fecha_creacion'])); ?></small>
                        </td>
                        <td><?php echo nl2br(htmlspecialchars($row['comentario'])); ?></td>
                        <td style="text-align: center;">
                            <span class="stars"><?php echo str_repeat('★', $row['puntuacion']) . str_repeat('☆', 5 - $row['puntuacion']); ?></span>
                        </td>
                        <td>
                            Pedido #<?php echo $row['pedido_id']; ?><br>
                            <small><?php echo htmlspecialchars($row['servicio_nombre']); ?></small>
                        </td>
                        <td style="text-align: center; color: <?php echo $row['activo'] == 1 ? 'green' : 'orange'; ?>;">
                            <?php echo $row['activo'] == 1 ? '✅ Aprobada' : '🟡 Pendiente'; ?>
                        </td>
                        <td>
                            <form action="reviews.php" method="post" style="margin-bottom: 5px;">
                                <input type="hidden" name="action" value="update_review">
                                <input type="hidden" name="review_id" value="<?php echo $row['id']; ?>">
                                <?php if ($row['activo'] == 0): ?>
                                    <input type="hidden" name="activo" value="1">
                                    <button type="submit" class="btn-primary" style="background: green; padding: 5px; font-size: 0.8em;">Aprobar</button>
                                <?php else: ?>
                                    <input type="hidden" name="activo" value="0">
                                    <button type="submit" class="btn-primary" style="background: orange; padding: 5px; font-size: 0.8em;">Desactivar</button>
                                <?php endif; ?>
                            </form>
                            <a href="reviews.php?delete_id=<?php echo $row['id']; ?>" 
                                onclick="return confirm('¿Eliminar esta reseña permanentemente?');" 
                                style="color: red; font-size: 0.8em;">Eliminar</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" style="text-align: center; padding: 20px;">No hay reseñas pendientes de moderación.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

    </main>
</body>
</html>