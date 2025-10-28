<?php
// Guardar en: includes/db_connect.php

// =================================================================
// 1. DEFINICIÓN DE CREDENCIALES (Confirmadas por el usuario)
// =================================================================
// DB_SERVER: 'localhost' es la opción más común y funciona en la mayoría de los casos.
define('DB_SERVER', 'localhost'); 
define('DB_USERNAME', 'buyf_smm_01'); 
define('DB_PASSWORD', 'RS6NmA2fxlegY1L'); 
define('DB_NAME', 'buyf_smm_01'); 

// =================================================================
// 2. INTENTAR ESTABLECER LA CONEXIÓN
// =================================================================
// Creamos la conexión que será accesible globalmente mediante $conn
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// 3. VERIFICAR SI LA CONEXIÓN FALLÓ
if ($conn->connect_error) {
    // Si la conexión falla, detenemos la ejecución y mostramos un error (solo en entorno de desarrollo)
    // Esto es lo que causaría un Error 500 si hay un problema con las credenciales
    die("ERROR de Conexión a la Base de Datos: " . $conn->connect_error);
}

// 4. CONFIGURAR CODIFICACIÓN
$conn->set_charset("utf8mb4");

// La variable $conn contiene la conexión activa y lista para ser usada.
// No cerramos la conexión aquí, ya que otros archivos (como login.php, config.php) 
// la necesitan y deben cerrarla al final de su script.
?>