<?php
/**
 * Vista de error 403 — Forbidden
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
  <title>Acceso Denegado — <?= htmlspecialchars($appName) ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <style>
    body { background: #f8f9fa; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
    .error-card { max-width: 540px; width: 100%; }
    .error-code-badge { font-family: monospace; font-size: .85rem; background: #e9ecef; color: #495057;
                        padding: .35rem .75rem; border-radius: 6px; display: inline-block; }
    .http-code { font-size: 5rem; font-weight: 800; color: #dee2e6; line-height: 1; }
    .icon { font-size: 3rem; }
  </style>
</head>
<body>
  <div class="error-card text-center p-4">
    <div class="icon mb-2">🔒</div>
    <div class="http-code">403</div>
    <h1 class="h4 fw-semibold mt-2 mb-1">Acceso Denegado</h1>
    <p class="text-muted mb-4">
      No tienes permisos para acceder a este recurso.<br>
      Si crees que esto es un error, contacta al administrador del sistema.
    </p>

    <div class="d-flex justify-content-center gap-2 mt-4">
      <a href="<?= htmlspecialchars($appUrl) ?>" class="btn btn-primary btn-sm">
        ← Volver al inicio
      </a>
      <a href="javascript:history.back()" class="btn btn-outline-secondary btn-sm">
        Página anterior
      </a>
    </div>
  </div>
</body>
</html>
