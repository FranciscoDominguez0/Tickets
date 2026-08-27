<?php
/**
 * Respuesta AJAX para navegación tipo SPA del panel de agente.
 * Si la petición trae X-Requested-With: XMLHttpRequest y X-SCP-AJAX: 1,
 * se devuelve SOLO el contenido ($content) y los assets de la ruta (CSS/JS),
 * sin renderizar el layout. Así el sidebar, el header y el shell permanecen
 * intactos en el DOM y no hay parpadeo al cambiar de opción.
 *
 * Uso: incluir DESPUÉS de construir $content y $currentRoute, ANTES de requerir el layout.
 */
if ((string)($_SERVER['HTTP_X_SCP_AJAX'] ?? '') === '1'
    && (string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') {
    header('Content-Type: application/json; charset=utf-8');
    ob_start();
    require __DIR__ . '/route-css.inc.php';
    require __DIR__ . '/route-scripts.inc.php';
    $assets = ob_get_clean();
    echo json_encode([
        'ok'     => true,
        'html'   => (string)($content ?? ''),
        'assets' => $assets,
        'route'  => (string)($currentRoute ?? ''),
    ]);
    exit;
}