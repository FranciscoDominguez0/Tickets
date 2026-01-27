<?php
/**
 * Punto de entrada único del panel (router + layout)
 * Mantiene header + sidebar estáticos y carga módulos dinámicamente.
 */

require_once '../../config.php';
require_once '../../includes/helpers.php';
require_once '../../includes/Auth.php';

// Si no está logueado, redirigir al login
if (!isset($_SESSION['staff_id'])) {
    header('Location: login.php');
    exit;
}

requireLogin('agente');
$staff = getCurrentUser();

// Mapa de rutas lógicas -> módulos
$routes = [
    'dashboard' => 'dashboard.php',   // Panel de control
    'tickets'   => 'tickets.php',     // Solicitudes
    'users'     => 'users.php',       // Usuarios
    'tasks'     => 'tasks.php',       // Tareas
    'canned'    => 'canned.php',      // Base de conocimientos
    'directory' => 'directory.php',   // Directorio de agentes
    'profile'   => 'profile.php',     // Mi perfil
    'orgs'      => 'orgs.php',        // Organizaciones
];

// Página por defecto
$page = $_GET['page'] ?? 'dashboard';
if (!isset($routes[$page])) {
    $page = 'dashboard';
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
