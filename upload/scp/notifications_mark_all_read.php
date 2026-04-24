<?php
/**
 * Marcar todas las notificaciones de staff como leídas (eliminarlas)
 */
require_once '../../config.php';
require_once '../../includes/helpers.php';
require_once '../../includes/Auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['staff_id'])) {
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

requireLogin('agente');

$staffId = (int) $_SESSION['staff_id'];

// Verificar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

// Eliminar todas las notificaciones del staff
$deleted = 0;
$stmt = $mysqli->prepare('DELETE FROM notifications WHERE staff_id = ?');
if ($stmt) {
    $stmt->bind_param('i', $staffId);
    if ($stmt->execute()) {
        $deleted = (int) $stmt->affected_rows;
    }
}

// Limpiar cache de notificaciones en sesión
$cacheKey = 'notif_cache_' . $staffId;
$cacheTsKey = 'notif_cache_ts_' . $staffId;
unset($_SESSION[$cacheKey], $_SESSION[$cacheTsKey]);

echo json_encode(['ok' => true, 'deleted' => $deleted]);
exit;
