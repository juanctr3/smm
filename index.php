
<link rel="manifest" href="/manifest.json">
<script>
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', () => {
        navigator.serviceWorker.register('/service-worker.js')
          .then(registration => {
            console.log('ServiceWorker registrado con éxito:', registration);
            // Función para suscribir al usuario a Push Notifications
            subscribeUserToPush(); 
          })
          .catch(error => {
            console.error('Fallo el registro de ServiceWorker:', error);
          });
      });
    }
</script>
