<?php
/**
 * Scripts específicos por ruta (agente).
 * Compartido entre layout/layout.php (carga completa) y la navegación AJAX
 * (SPA), para que el contenido reemplazado por fetch reciba los mismos scripts.
 */
if (isset($currentRoute) && $currentRoute === 'profile'): ?>
<script src="js/profile.js"></script>
<?php endif; ?>
<?php if (isset($currentRoute) && $currentRoute === 'users'): ?>
<script src="js/users.js"></script>
<?php endif; ?>
<?php if (isset($currentRoute) && $currentRoute === 'dashboard'): ?>
<script src="js/vendor/chart.umd.min.js"></script>
<script src="js/dashboard.js?v=<?php echo (int)@filemtime(__DIR__ . '/../js/dashboard.js'); ?>"></script>
<?php endif; ?>
<?php if (isset($currentRoute) && $currentRoute === 'tickets'): ?>
<script src="js/vendor/jquery-3.6.0.min.js" defer></script>
<script src="js/vendor/summernote-lite.min.js" defer></script>
<script src="js/vendor/summernote-es-ES.min.js" defer></script>
<script src="js/tickets.js" defer></script>
<?php endif; ?>
<?php if (isset($currentRoute) && $currentRoute === 'tasks'): ?>
<script src="js/tasks.js"></script>
<?php endif; ?>
<?php if (isset($currentRoute) && $currentRoute === 'orgs'): ?>
<script src="js/orgs.js"></script>
<?php endif; ?>