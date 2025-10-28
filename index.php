<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel SMM Pro | Seguidores, Likes y Comentarios</title>
    
    <meta name="description" content="Aumenta tu presencia en redes sociales con nuestros servicios de seguidores, likes y comentarios. ¡Entrega rápida y garantizada!">

    <link rel="stylesheet" href="assets/css/style.css">

    <link rel="manifest" href="/manifest.json">

    <script>
        if ('serviceWorker' in navigator) {
          window.addEventListener('load', () => {
            navigator.serviceWorker.register('/service-worker.js')
              .then(registration => {
                console.log('ServiceWorker registrado con éxito:', registration);
              })
              .catch(error => {
                console.error('Fallo el registro de ServiceWorker:', error);
              });
          });
        }
    </script>
</head>
<body>

    <header class="header">
        <div class="logo">Panel SMM Pro</div>
        <nav>
            <a href="index.php" class="nav-link">Inicio</a>
            <a href="servicios.php" class="nav-link">Servicios</a>
            <a href="login.php" class="nav-link">Iniciar Sesión</a>
            <a href="registro.php" class="btn-primary">Registrarse</a>
        </nav>
    </header>

    <main class="container">
        <h1>Impulsa tu Presencia Digital</h1>
        <p>Somos tu panel de confianza para **comprar seguidores reales** y aumentar la interacción en tus redes sociales favoritas.</p>
        
        <div id="categorias">
            <div class="service-card">
                <img src="assets/img/instagram-icon.png" alt="Icono Instagram" class="service-icon">
                <h3>Instagram</h3>
                <p>Likes, Seguidores y Vistas para tus Reels.</p>
            </div>
            </div>

    </main>

    <a href="https://wa.me/573100000000" class="whatsapp-float" target="_blank" title="Contacta con el Administrador">
        💬 
    </a>
    
    <script src="assets/js/app.js"></script>
</body>
</html>
