<?php
// Guardar en: smm_panel/admin/auth_check.php

// 1. Inicia la sesión si aún no ha sido iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Verifica si el usuario NO está logueado
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    // Si no está logueado, lo redirige al formulario de login en la raíz.
    header("location: ../login.php");
    exit;
}

// 3. Verifica si el usuario NO es administrador
if ($_SESSION["rol"] !== 'admin') {
    // Si es un cliente, lo redirige a su panel de cuenta o a la página principal.
    header("location: ../cuenta.php");
    exit;
}
// Si el script llega a este punto, el usuario es un administrador logueado.
?>