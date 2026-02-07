<?php
require_once '../../config.php';
require_once '../../includes/helpers.php';
require_once '../../includes/Auth.php';

if (!isset($_SESSION['staff_id'])) {
    header('Location: login.php');
    exit;
}

requireLogin('agente');
$staff = getCurrentUser();
$currentRoute = 'settings';

$collapseSettingsMenu = false;
if (!isset($_SESSION['admin_settings_menu_seen'])) {
    $_SESSION['admin_settings_menu_seen'] = 1;
    $collapseSettingsMenu = true;
}

$allowedTargets = ['pages','system','tickets','tasks','agents','users'];
$target = (string)($_GET['t'] ?? 'pages');
if (!in_array($target, $allowedTargets, true)) {
    $target = 'pages';
}

$msg = '';
$error = '';

require_once __DIR__ . '/inc/settings_helpers.inc.php';

if ($target === 'pages') {
    require __DIR__ . '/inc/settings_pages.inc.php';
} elseif ($target === 'system') {
    require __DIR__ . '/inc/settings_system.inc.php';
} else {
    $content = '<div class="page-header"><h1>Configuración</h1><p>Sección en construcción.</p></div>';
}

require_once 'layout_admin.php';
exit;