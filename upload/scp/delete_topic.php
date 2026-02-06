<?php
require_once '../../config.php';
require_once '../../includes/helpers.php';
require_once '../../includes/Auth.php';

if (!isset($_SESSION['staff_id'])) {
    header('Location: login.php');
    exit;
}

requireLogin('agente');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $topicId = $_POST['id'] ?? null;
    
    if (!$topicId) {
        echo json_encode(['success' => false, 'message' => 'ID de tema requerido']);
        exit;
    }
    
    // Verificar si el tema está siendo usado en tickets
    $ticketCount = fetchOne("SELECT COUNT(*) as count FROM ticket WHERE topic_id = ?", [$topicId]);
    
    if ($ticketCount['count'] > 0) {
        echo json_encode(['success' => false, 'message' => 'No se puede eliminar el tema porque está siendo usado en tickets']);
        exit;
    }
    
    // Eliminar el tema
    execute("DELETE FROM help_topics WHERE topic_id = ?", [$topicId]);
    
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}
?>
