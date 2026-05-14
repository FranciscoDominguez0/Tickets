<?php
require_once dirname(__DIR__, 2) . '/config.php';
if (!isset($_SESSION['staff_id'])) {
    http_response_code(403);
    exit;
}

$mode = (string)($_POST['mode'] ?? '');
$val = ($mode === 'dark') ? 1 : 0;
$_SESSION['scp_dark_mode'] = (string)$val;

// Persistir en base de datos
$sid = (int)$_SESSION['staff_id'];
$stmt = $mysqli->prepare('UPDATE staff SET dark_mode = ?, updated = NOW() WHERE id = ?');
$stmt->bind_param('ii', $val, $sid);
$stmt->execute();

header('Content-Type: application/json');
echo json_encode(['ok' => true, 'mode' => $mode]);
