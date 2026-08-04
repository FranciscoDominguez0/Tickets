<?php
/**
 * Vista de error 500 — Internal Server Error
 * Esta es la página más crítica: el usuario NUNCA debe ver detalles internos.
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
  <title>Error del Servidor — <?= htmlspecialchars($appName) ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <style>
    body { background: #f8f9fa; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
    .error-card { max-width: 580px; width: 100%; }
    .error-code-badge { font-family: monospace; font-size: .9rem; background: #1e293b; color: #94a3b8;
                        padding: .4rem .85rem; border-radius: 6px; display: inline-block;
                        border: 1px solid #334155; letter-spacing: .05em; }
    .http-code { font-size: 5rem; font-weight: 800; color: #dee2e6; line-height: 1; }
    .support-box { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.5rem; }
  </style>
</head>
<body>
  <div class="error-card p-3">
    <div class="text-center mb-4">
      <div class="http-code">500</div>
      <h1 class="h4 fw-semibold mt-2 mb-1">Ha ocurrido un error inesperado</h1>
      <p class="text-muted">
        Algo salió mal en el servidor. Nuestro equipo ha sido notificado automáticamente.<br>
        Por favor, intenta de nuevo en unos minutos.
      </p>
    </div>

    <div class="support-box text-center">
      <p class="text-muted small mb-2">Código de referencia para soporte:</p>
      <div class="mb-3">
        <span class="error-code-badge"><?= htmlspecialchars($errorCode) ?></span>
      </div>
      <p class="text-muted small mb-0">
        Si el problema persiste, contacta a soporte técnico e indica el código de error mostrado arriba.
      </p>
    </div>

    <div class="text-center mt-4">
      <a href="<?= htmlspecialchars($appUrl) ?>" class="btn btn-primary btn-sm me-2">
        ← Volver al inicio
      </a>
      <a href="javascript:location.reload()" class="btn btn-outline-secondary btn-sm">
        Intentar de nuevo
      </a>
    </div>
  </div>
</body>
</html>
