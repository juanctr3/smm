<?php
// Guardar en: smm_panel/admin/sidebar_menu_css.php

// Define los estilos de la barra lateral para su inclusión.
echo '<style>
    .admin-sidebar {
        width: 250px;
        background-color: #343a40; /* Fondo oscuro profesional */
        color: white;
        height: 100vh;
        position: fixed;
        padding-top: 20px;
        z-index: 1000; /* Asegura que esté por encima de otros elementos */
    }
    .admin-content {
        margin-left: 250px; /* Deja espacio para el menú */
        padding: 30px;
    }
    .admin-menu a {
        display: block;
        padding: 10px 20px;
        color: #ccc;
        text-decoration: none;
        border-left: 3px solid transparent;
        transition: all 0.2s;
    }
    .admin-menu a:hover, .admin-menu .active {
        color: white;
        background-color: #495057;
        border-left: 3px solid var(--color-principal); /* Azul principal */
    }
    /* Estilo para las tarjetas de métricas en el dashboard */
    .metric-card {
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        text-align: center;
        border-top: 5px solid var(--color-principal);
    }
</style>';
?>