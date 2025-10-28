/* --- PWA: assets/js/app.js --- */

function subscribeUserToPush() {
    // ... Código de suscripción a notificaciones Push ...
    
    // Llama a esta función SOLO si el Service Worker se registra por primera vez 
    // y si el usuario da permiso para notificaciones.
    trackPWAInstallation(); 
}

function trackPWAInstallation() {
    // Envía una petición asíncrona al backend para registrar la "descarga"
    fetch('/includes/pwa_tracker.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=install'
    })
    .then(response => response.json())
    .then(data => {
        console.log('PWA Tracker:', data.message);
    })
    .catch(error => {
        console.error('Error tracking PWA:', error);
    });
}
