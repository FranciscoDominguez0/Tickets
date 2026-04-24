<?php
require_once '../../config.php';
require_once '../../includes/helpers.php';
require_once '../../includes/Auth.php';

// Polling de notificaciones para el agente logueado
header('Content-Type: application/json; charset=UTF-8');

try {
    requireLogin('agente');
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => 'No session']);
    exit;
}

$staffId = (int)($_SESSION['staff_id'] ?? 0);
$eid = empresaId();
$lastId = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;

$response = [
    'ok' => true,
    'notifications' => [],
    'total_unread' => 0
];

if ($staffId > 0) {
    // 1. Buscar nuevas notificaciones no leídas con ID mayor al último visto
    $stmt = $mysqli->prepare("SELECT id, message, type, related_id, created_at FROM notifications 
                             WHERE staff_id = ? AND empresa_id = ? AND is_read = 0 AND id > ? 
                             ORDER BY id ASC LIMIT 5");
    $stmt->bind_param('iii', $staffId, $eid, $lastId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $response['notifications'][] = $row;
    }

    // 2. Contar total de no leídas para el badge de la campana
    $stmtC = $mysqli->prepare("SELECT COUNT(*) as total FROM notifications WHERE staff_id = ? AND empresa_id = ? AND is_read = 0");
    $stmtC->bind_param('ii', $staffId, $eid);
    $stmtC->execute();
    $resC = $stmtC->get_result()->fetch_assoc();
    $response['total_unread'] = (int)($resC['total'] ?? 0);

    // Si hay nuevas, invalidamos el caché de la sesión para que al refrescar se vean en el dropdown
    if (!empty($response['notifications'])) {
        $cacheKey = 'notif_cache_' . $staffId;
        $cacheTsKey = 'notif_cache_ts_' . $staffId;
        unset($_SESSION[$cacheKey], $_SESSION[$cacheTsKey]);
    }
}

echo json_encode($response);
exit;
