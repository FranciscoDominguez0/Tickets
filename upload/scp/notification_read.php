<?php
require_once '../../config.php';
require_once '../../includes/helpers.php';
require_once '../../includes/Auth.php';

if (!isset($_SESSION['staff_id'])) {
    header('Location: login.php');
    exit;
}

requireLogin('agente');

$staffId = (int) $_SESSION['staff_id'];
$notifId = isset($_GET['id']) && is_numeric($_GET['id']) ? (int) $_GET['id'] : 0;

if ($notifId <= 0) {
    header('Location: index.php?page=tickets');
    exit;
}

$relatedId = null;
$type = 'general';
$stmt = $mysqli->prepare('SELECT related_id, type FROM notifications WHERE id = ? AND staff_id = ? LIMIT 1');
if ($stmt) {
    $stmt->bind_param('ii', $notifId, $staffId);
    if ($stmt->execute()) {
        $row = $stmt->get_result()->fetch_assoc();
        if ($row) {
            $relatedId = isset($row['related_id']) && is_numeric($row['related_id']) ? (int) $row['related_id'] : null;
            $type = (string)($row['type'] ?? 'general');
        }
    }
}

// Eliminar notificación (según lo solicitado)
$stmtD = $mysqli->prepare('DELETE FROM notifications WHERE id = ? AND staff_id = ?');
if ($stmtD) {
    $stmtD->bind_param('ii', $notifId, $staffId);
    $stmtD->execute();
}

if ($relatedId !== null && $relatedId > 0) {
    if ($type === 'task_assigned') {
        header('Location: tasks.php?id=' . (int) $relatedId);
        exit;
    }
    if ($type === 'ticket_assigned') {
        header('Location: tickets.php?id=' . (int) $relatedId);
        exit;
    }
}

header('Location: index.php?page=dashboard');
exit;
