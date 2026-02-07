<?php
require_once '../../config.php';
require_once '../../includes/helpers.php';
require_once '../../includes/Auth.php';

header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['staff_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'No autorizado']);
    exit;
}

requireLogin('agente');

$staffId = (int) $_SESSION['staff_id'];

$count = 0;
$items = [];

$stmtN = $mysqli->prepare('SELECT COUNT(*) c FROM notifications WHERE staff_id = ? AND is_read = 0');
if ($stmtN) {
    $stmtN->bind_param('i', $staffId);
    if ($stmtN->execute()) {
        $count = (int) (($stmtN->get_result()->fetch_assoc()['c'] ?? 0));
    }
}

$stmtL = $mysqli->prepare('SELECT id, message, type, related_id, created_at FROM notifications WHERE staff_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 8');
if ($stmtL) {
    $stmtL->bind_param('i', $staffId);
    if ($stmtL->execute()) {
        $res = $stmtL->get_result();
        while ($row = $res->fetch_assoc()) {
            $items[] = [
                'id' => (int)($row['id'] ?? 0),
                'message' => (string)($row['message'] ?? ''),
                'type' => (string)($row['type'] ?? 'general'),
                'related_id' => isset($row['related_id']) ? (int)$row['related_id'] : null,
                'created_at' => (string)($row['created_at'] ?? ''),
            ];
        }
    }
}

echo json_encode([
    'ok' => true,
    'count' => $count,
    'items' => $items,
]);
