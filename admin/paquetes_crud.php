<?php
// Guardar en: smm_panel/admin/paquetes_crud.php
// Maneja la creación y eliminación de paquetes de precios (servicios_paquetes)

require_once './auth_check.php';
require_once '../includes/db_connect.php';
require_once '../includes/config_global.php';

$message = $error = '';
$redirect_id = 0; // ID del servicio al que volver

// =================================================================
// 1. Lógica de AGREGAR PAQUETE (POST)
// =================================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add_package') {
    
    $servicio_id = (int)$_POST['servicio_id'];
    $cantidad = (int)$_POST['new_cantidad'];
    $precio_paquete = (float)$_POST['new_precio'];
    // El precio rebajado puede ser NULL
    $precio_rebajado = !empty($_POST['new_rebajado']) ? (float)$_POST['new_rebajado'] : NULL; 
    
    $redirect_id = $servicio_id;

    // Validación
    if ($servicio_id <= 0 || $cantidad <= 0 || $precio_paquete <= 0) {
        $error = "Error: El ID del servicio, la cantidad y el precio deben ser válidos.";
    } else {
        // Inserción del nuevo paquete
        $stmt = $conn->prepare("INSERT INTO servicios_paquetes (servicio_id, cantidad, precio_paquete, precio_rebajado, activo) VALUES (?, ?, ?, ?, 1)");
        // Los tipos son: iidd (ID_Servicio, Cantidad, Precio_Total, Precio_Rebajado)
        $stmt->bind_param("iidd", $servicio_id, $cantidad, $precio_paquete, $precio_rebajado);
        
        if ($stmt->execute()) {
            $message = "Paquete de {$cantidad} unidades agregado con éxito.";
        } else {
            // Error común: Cantidad duplicada para el mismo servicio
            if ($conn->errno == 1062) {
                $error = "Error: Ya existe un paquete con esta cantidad para este servicio.";
            } else {
                $error = "Error al agregar el paquete: " . $conn->error;
            }
        }
        $stmt->close();
    }
}

// =================================================================
// 2. Lógica de ELIMINAR PAQUETE (GET)
// =================================================================
if (isset($_GET['delete_id']) && isset($_GET['service_id'])) {
    
    $delete_id = (int)$_GET['delete_id'];
    $redirect_id = (int)$_GET['service_id'];

    $stmt = $conn->prepare("DELETE FROM servicios_paquetes WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    
    if ($stmt->execute()) {
        $message = "Paquete eliminado con éxito.";
    } else {
        $error = "Error al eliminar el paquete.";
    }
    $stmt->close();
}


// Cerramos la conexión AHORA, ya que vamos a redirigir.
$conn->close();

// 3. Redirigir de vuelta al formulario de edición del servicio
$url_params = "edit_id={$redirect_id}";
if (!empty($message)) {
    $url_params .= "&msg=" . urlencode($message);
}
if (!empty($error)) {
    $url_params .= "&err=" . urlencode($error);
}

header("Location: servicios_crud.php?" . $url_params);
exit;

?>