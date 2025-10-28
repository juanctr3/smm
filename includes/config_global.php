<?php
// Guardar en: smm_panel/includes/config_global.php

// Inicializar la variable global
$GLOBAL_CONFIG = [
    'SITE_NAME' => 'SMM Pro Panel', // Valor por defecto
    'API_URL' => '',
    'API_KEY' => '',
    'WHATSAPP_NUMBER' => 'TU_NUMERO_WHATSAPP_AQUI' // Número para el botón flotante
];

// Comprobar si la conexión a la DB está disponible
if (isset($conn)) {
    // Usar consultas no preparadas para CONFIGURACION, ya que la tabla es interna y confiable.
    $result = $conn->query("SELECT nombre, valor FROM configuracion WHERE nombre IN ('SITE_NAME', 'API_URL', 'API_KEY', 'WHATSAPP_NUMBER')");
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $GLOBAL_CONFIG[$row['nombre']] = $row['valor'];
        }
    }
}

// Función auxiliar para obtener la configuración fácilmente
function get_config($key) {
    global $GLOBAL_CONFIG;
    return $GLOBAL_CONFIG[$key] ?? 'Error Config';
}
?>