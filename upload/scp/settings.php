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
$menuKey = 'admin_settings_menu_seen_' . (int)($_SESSION['staff_id'] ?? 0);
if ((string)($_SESSION['sidebar_panel_mode'] ?? '') !== 'admin') {
    unset($_SESSION[$menuKey]);
    $_SESSION['sidebar_panel_mode'] = 'admin';
}
if (!isset($_SESSION[$menuKey])) {
    $_SESSION[$menuKey] = 1;
    $collapseSettingsMenu = true;
}

$allowedTargets = ['pages','system','tickets','tasks','agents','users','billing'];
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
} elseif ($target === 'tickets') {
    require __DIR__ . '/inc/settings_tickets.inc.php';
} elseif ($target === 'tasks') {
    require __DIR__ . '/inc/settings_tasks.inc.php';
} elseif ($target === 'agents') {
    require __DIR__ . '/inc/settings_agents.inc.php';
} elseif ($target === 'users') {
    require __DIR__ . '/inc/settings_users.inc.php';
} elseif ($target === 'billing') {
    require __DIR__ . '/inc/settings_billing.inc.php';
} else {
    $content = '<div class="page-header"><h1>Configuración</h1><p>Sección en construcción.</p></div>';
}

require_once 'layout_admin.php';
exit;