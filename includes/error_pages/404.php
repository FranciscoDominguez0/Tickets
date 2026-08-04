<?php
/**
 * Vista de error 404 — Not Found
 */
$appName = defined('APP_NAME') ? APP_NAME : 'Sistema de Tickets';
$appUrl  = defined('APP_URL')  ? APP_URL  : '/';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Página No Encontrada — <?= htmlspecialchars($appName) ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <style>
    body { background: #f8f9fa; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
    .error-card { max-width: 580px; width: 100%; }
    .error-code-badge { font-family: monospace; font-size: .85rem; background: #e9ecef; color: #495057;
                        padding: .35rem .75rem; border-radius: 6px; display: inline-block; }
    .http-code { font-size: 5rem; font-weight: 800; color: #dee2e6; line-height: 1; }
  </style>
</head>
<body>
  <div class="error-card text-center p-4">
    <div class="http-code">404</div>
    <h1 class="h4 fw-semibold mt-2 mb-1">Página No Encontrada</h1>
    <p class="text-muted mb-4">
      La página que buscas no existe o ha sido movida.<br>
      Verifica la URL e intenta de nuevo.
    </p>


    <a href="<?= htmlspecialchars($appUrl) ?>" class="btn btn-primary btn-sm me-2">
      ← Ir al inicio
    </a>
    <a href="javascript:history.back()" class="btn btn-outline-secondary btn-sm">
      Página anterior
    </a>
  </div>
</body>
</html>
