<?php
/**
 * Vista de error 503 — Service Unavailable
 * Usado cuando la DB no está disponible o el sistema está en mantenimiento.
 */
$errorCode = $errorCode ?? 'ERR-000000-000000';
$appName   = defined('APP_NAME') ? APP_NAME : 'Sistema de Tickets';
$appUrl    = defined('APP_URL')  ? APP_URL  : '/';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <!-- Retry-After: el navegador puede reintentar automáticamente -->
  <meta http-equiv="refresh" content="60">
  <title>Servicio No Disponible — <?= htmlspecialchars($appName) ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <style>
    body { background: #f8f9fa; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
    .error-card { max-width: 560px; width: 100%; }
    .error-code-badge { font-family: monospace; font-size: .85rem; background: #e9ecef; color: #495057;
                        padding: .35rem .75rem; border-radius: 6px; display: inline-block; }
    .http-code { font-size: 5rem; font-weight: 800; color: #dee2e6; line-height: 1; }
    .icon { font-size: 2.5rem; }
    .countdown { font-family: monospace; font-size: 1.1rem; color: #6c757d; }
  </style>
</head>
<body>
  <div class="error-card text-center p-4">
    <div class="icon mb-2">🔧</div>
    <div class="http-code">503</div>
    <h1 class="h4 fw-semibold mt-2 mb-1">Servicio Temporalmente No Disponible</h1>
    <p class="text-muted mb-3">
      El sistema se encuentra en mantenimiento o experimentando una sobrecarga temporal.<br>
      Esta página se actualizará automáticamente en <strong>60 segundos</strong>.
    </p>

    <div class="alert alert-warning py-2 px-3 text-start mb-4">
      <small class="text-muted d-block mb-1">Código de referencia:</small>
      <span class="error-code-badge"><?= htmlspecialchars($errorCode) ?></span>
    </div>

    <a href="javascript:location.reload()" class="btn btn-primary btn-sm">
      Intentar ahora
    </a>
  </div>

  <script>
    // Cuenta regresiva visual
    let t = 60;
    const msg = document.querySelector('strong');
    const iv = setInterval(() => {
      t--;
      if (msg) msg.textContent = t + ' segundos';
      if (t <= 0) clearInterval(iv);
    }, 1000);
  </script>
</body>
</html>
