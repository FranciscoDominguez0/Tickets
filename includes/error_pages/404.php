<?php
/**
 * Vista de error 404 — Not Found / Ruta no encontrada
 */
$appName   = defined('APP_NAME') ? APP_NAME : 'Sistema de Tickets';
$appUrl    = defined('APP_URL')  ? APP_URL  : '/';

$requestedPath = '';
if (!empty($_SERVER['REDIRECT_URL'])) {
    $requestedPath = htmlspecialchars($_SERVER['REDIRECT_URL'], ENT_QUOTES, 'UTF-8');
} elseif (!empty($_SERVER['REQUEST_URI'])) {
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '';
    $requestedPath = htmlspecialchars($uri, ENT_QUOTES, 'UTF-8');
}

$isStaff  = session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['staff_id']);
$isClient = session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user_id']);
if ($isStaff) {
    $backUrl   = rtrim($appUrl, '/') . '/upload/scp/tickets.php';
    $backLabel = 'Volver al panel';
} elseif ($isClient) {
    $backUrl   = rtrim($appUrl, '/') . '/upload/tickets.php';
    $backLabel = 'Mis tickets';
} else {
    $backUrl   = $appUrl;
    $backLabel = 'Ir al inicio';
}
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
    .http-code { font-size: 5rem; font-weight: 800; color: #dee2e6; line-height: 1; }
    .path-badge { font-family: monospace; font-size: .82rem; background: #f1f5f9; color: #64748b;
                  padding: .3rem .65rem; border-radius: 5px; border: 1px solid #e2e8f0;
                  display: inline-block; word-break: break-all; }
  </style>
</head>
<body>
  <div class="error-card p-3">
    <div class="text-center mb-4">
      <div class="http-code">404</div>
      <h1 class="h4 fw-semibold mt-2 mb-1">Página no encontrada</h1>
      <p class="text-muted">
        La URL que solicitaste no existe o fue movida.<br>
        Verifica la dirección e intenta de nuevo.
      </p>
      <?php if ($requestedPath && $requestedPath !== '/'): ?>
      <div class="mt-2">
        <span class="path-badge"><?= $requestedPath ?></span>
      </div>
      <?php endif; ?>
    </div>

    <div class="text-center mt-4">
      <a href="<?= htmlspecialchars($backUrl) ?>" class="btn btn-primary btn-sm me-2">
        ← <?= htmlspecialchars($backLabel) ?>
      </a>
      <a href="javascript:history.back()" class="btn btn-outline-secondary btn-sm">
        Página anterior
      </a>
    </div>
  </div>
</body>
</html>