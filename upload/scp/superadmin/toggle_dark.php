<?php
require_once '../../../config.php';
require_once '../../../includes/helpers.php';

requireLogin('agente');

if ((string)($_SESSION['staff_role'] ?? '') !== 'superadmin') {
    header('Location: ../index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

if (validateCSRF()) {
    $newVal = (string)($_POST['dark_mode'] ?? '0');
    $_SESSION['superadmin_dark_mode'] = ($newVal === '1') ? 1 : 0;
}

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (!empty($_SERVER['HTTP_ACCEPT']) && stripos((string)$_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'dark_mode' => (int)($_SESSION['superadmin_dark_mode'] ?? 0),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$return = (string)($_POST['return'] ?? 'index.php');
if ($return === '' || preg_match('~^(?:https?:)?//~i', $return)) {
    $return = 'index.php';
}
$return = ltrim($return, '/');

header('Location: ' . $return);
exit;
