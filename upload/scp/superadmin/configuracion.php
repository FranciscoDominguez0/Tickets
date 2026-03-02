<?php
require_once '../../../config.php';
require_once '../../../includes/helpers.php';
require_once '../../../includes/Auth.php';

requireLogin('agente');

if ((string)($_SESSION['staff_role'] ?? '') !== 'superadmin') {
    header('Location: ../index.php');
    exit;
}

$currentRoute = 'configuracion';

$msg = '';
$error = '';

$settingsRedirectUrl = 'configuracion.php';

require_once __DIR__ . '/../inc/settings_helpers.inc.php';
require_once __DIR__ . '/../inc/settings_system.inc.php';

require __DIR__ . '/layout.php';
