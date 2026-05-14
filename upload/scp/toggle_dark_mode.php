<?php
require_once dirname(__DIR__, 2) . '/config.php';
if (!isset($_SESSION['staff_id'])) {
    http_response_code(403);
    exit;
}

$mode = (string)($_POST['mode'] ?? '');
if ($mode === 'dark') {
    $_SESSION['scp_dark_mode'] = '1';
} else {
    $_SESSION['scp_dark_mode'] = '0';
}

header('Content-Type: application/json');
echo json_encode(['ok' => true, 'mode' => $mode]);
