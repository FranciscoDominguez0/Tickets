<?php
/**
 * Punto de entrada único del panel (router + layout)
 * Mantiene header + sidebar estáticos y carga módulos dinámicamente.
 */

require_once '../../config.php';
require_once '../../includes/helpers.php';
require_once '../../includes/Auth.php';

// Si no está logueado, redirigir al login (excepto para páginas públicas/ocultas como créditos)
$page = $_GET['page'] ?? 'dashboard';
if (!isset($_SESSION['staff_id']) && $page !== 'credits') {
    header('Location: login.php');
    exit;
}

// Solo requerir login de agente si no es la página de créditos
if ($page !== 'credits') {
    requireLogin('agente');
    $staff = getCurrentUser();
} else {
    // Para la página de créditos permitimos acceso público o sesión parcial
    $staff = isset($_SESSION['staff_id']) ? getCurrentUser() : ['name' => 'Invitado', 'role' => 'public'];
}

// Mapa de rutas lógicas -> módulos
$routes = [
    'dashboard' => 'dashboard.php',   // Panel de control
    'tickets'   => 'tickets.php',     // Solicitudes
    'statistics'=> 'statistics.php',  // Estadísticas
    'users'     => 'users.php',       // Usuarios
    'tasks'     => 'tasks.php',       // Tareas
    'canned'    => 'canned.php',      // Base de conocimientos
    'directory' => 'directory.php',   // Directorio de agentes
    'profile'   => 'profile.php',     // Mi perfil
    'orgs'      => 'orgs.php',        // Organizaciones
    'notifications' => 'notifications.php', // Notificaciones (preferencias de correo)
    'mapa'      => 'mapa-view.inc.php',   // Mapa de agentes en tiempo real
    'credits'   => 'credits.php',     // Créditos y autoría
];

// Página por defecto
$page = $_GET['page'] ?? 'dashboard';
if (!isset($routes[$page])) {
    $page = 'dashboard';
}

// Compatibilidad: la vista de estadísticas ahora es independiente
if ($page === 'statistics') {
    $qs = $_GET;
    unset($qs['page']);
    $to = 'statics.php' . (!empty($qs) ? ('?' . http_build_query($qs)) : '');
    header('Location: ' . $to);
    exit;
}

// El mapa es una página standalone con su propio layout
if ($page === 'mapa') {
    header('Location: mapa.php');
    exit;
}

$currentRoute = $page;
$moduleFile   = __DIR__ . '/modules/' . $routes[$page];

// Capturamos el contenido del módulo para inyectarlo en el layout
ob_start();
if (file_exists($moduleFile)) {
    require $moduleFile;
} else {
    echo '<div class="alert alert-danger">Módulo no encontrado.</div>';
}
$content = ob_get_clean();

// Renderizar layout principal (header + sidebar + shell)
require __DIR__ . '/layout/layout.php';
?>
