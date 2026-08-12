<?php
/**
 * Vista de error 403 — Forbidden / Sin permisos
 */
$appName   = defined('APP_NAME') ? APP_NAME : 'Sistema de Tickets';
$appUrl    = defined('APP_URL')  ? APP_URL  : '/';

$isStaff  = session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['staff_id']);
$isClient = session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user_id']);
if ($isStaff) {
    $backUrl   = rtrim($appUrl, '/') . '/upload/scp/tickets.php';
    $backLabel = 'Volver al panel';
} elseif ($isClient) {
    $backUrl   = rtrim($appUrl, '/') . '/upload/tickets.php';
    $backLabel = 'Volver a mis tickets';
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
  <title>Acceso Denegado — <?= htmlspecialchars($appName) ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <style>
    body { background: #f8f9fa; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
    .error-card { max-width: 580px; width: 100%; }
    .http-code { font-size: 5rem; font-weight: 800; color: #dee2e6; line-height: 1; }
    .support-box { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.5rem; }
  </style>
</head>
<body>
  <div class="error-card p-3">
    <div class="text-center mb-4">
      <div class="http-code">403</div>
      <h1 class="h4 fw-semibold mt-2 mb-1">Acceso denegado</h1>
      <p class="text-muted">
        No tienes permiso para acceder a este recurso.<br>
        Si crees que es un error, contacta al administrador del sistema.
      </p>
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