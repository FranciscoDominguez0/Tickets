<?php
/**
 * CSS específicos por ruta (agente).
 * Compartido entre layout/layout.php (carga completa) y la navegación AJAX
 * (SPA), para que el contenido reemplazado por fetch reciba los mismos estilos.
 */
if (isset($currentRoute) && $currentRoute === 'dashboard'): ?>
<link rel="stylesheet" href="css/dashboard.css?v=<?php echo (int)@filemtime(__DIR__ . '/../css/dashboard.css'); ?>">
<?php endif; ?>
<?php if (isset($currentRoute) && $currentRoute === 'profile'): ?>
<link rel="stylesheet" href="css/profile.css?v=<?php echo (int)@filemtime(__DIR__ . '/../css/profile.css'); ?>">
<?php endif; ?>
<?php if (isset($currentRoute) && $currentRoute === 'users'): ?>
<link rel="stylesheet" href="css/users.css?v=<?php echo (int)@filemtime(__DIR__ . '/../css/users.css'); ?>">
<?php endif; ?>
<?php if (isset($currentRoute) && in_array($currentRoute, ['tickets', 'reportes', 'informes_jefes', 'cotizaciones'])): ?>
<link rel="stylesheet" href="css/tickets.css?v=<?php echo (int)@filemtime(__DIR__ . '/../css/tickets.css'); ?>">
<?php endif; ?>
<?php if (isset($currentRoute) && $currentRoute === 'tickets'): ?>
<link rel="stylesheet" href="css/vendor/summernote-lite.min.css">
<?php endif; ?>
<?php if (isset($currentRoute) && $currentRoute === 'orgs'): ?>
<link rel="stylesheet" href="css/orgs.css?v=<?php echo (int)@filemtime(__DIR__ . '/../css/orgs.css'); ?>">
<?php endif; ?>
<?php if (isset($currentRoute) && $currentRoute === 'tasks'): ?>
<link rel="stylesheet" href="css/tasks.css?v=<?php echo (int)@filemtime(__DIR__ . '/../css/tasks.css'); ?>">
<?php endif; ?>