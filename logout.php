<?php
// Guardar en: smm_panel/logout.php

// 1. Iniciar la sesión si aún no está iniciada (necesario para acceder a la sesión)
session_start();

// 2. Destruir todas las variables de sesión
$_SESSION = array();
 
// 3. Destruir la sesión.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();
 
// 4. Redirigir al usuario a la página de inicio (index.php)
header("location: index.php");
exit;
?>