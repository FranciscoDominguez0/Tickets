<?php
/**
 * REABRIR TICKET
 * Endpoint AJAX - POST
 * Elimina firma del servidor y limpia campos en BD
 */

require_once '../config.php';
require_once '../includes/helpers.php';
require_once '../includes/Auth.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

if (!isset($_SESSION['staff_id'])) {
    echo json_encode(['success' => false, 'error' => 'Sesión expirada']);
    exit;
}

if (!isset($_POST['csrf_token']) || !Auth::validateCSRF($_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'error' => 'Token de seguridad inválido']);
    exit;
}

$ticket_id = (int)($_POST['ticket_id'] ?? 0);

if ($ticket_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID de ticket inválido']);
    exit;
}

$stmt = $mysqli->prepare(
    'SELECT t.id, t.status_id, t.client_signature FROM tickets t WHERE t.id = ?'
);
$stmt->bind_param('i', $ticket_id);
$stmt->execute();
$ticket = $stmt->get_result()->fetch_assoc();

if (!$ticket) {
    echo json_encode(['success' => false, 'error' => 'Ticket no encontrado']);
    exit;
}

if ((int)$ticket['status_id'] !== 5) {
    echo json_encode(['success' => false, 'error' => 'El ticket no está cerrado']);
    exit;
}

$signature_path = $ticket['client_signature'];

if ($signature_path && $signature_path !== '') {
    $full_path = realpath(__DIR__ . '/..') . '/' . $signature_path;
    if (file_exists($full_path)) {
        @unlink($full_path);
    }
}

$status_open = 1;

$has_closed_legacy_col = dbColumnExists('tickets', 'closed');
$legacy_closed_clear = $has_closed_legacy_col ? ', closed = NULL' : '';

$stmt = $mysqli->prepare(
    'UPDATE tickets
     SET status_id = ?, staff_id = ?, client_signature = NULL, close_message = NULL, closed_at = NULL' . $legacy_closed_clear . ', updated = NOW()
     WHERE id = ?'
);
$stmt->bind_param('iii', $status_open, $_SESSION['staff_id'], $ticket_id);

if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'error' => 'Error al actualizar el ticket']);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'Ticket reabierto correctamente'
]);
