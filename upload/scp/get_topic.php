<?php
require_once '../../config.php';
require_once '../../includes/helpers.php';
require_once '../../includes/Auth.php';

if (!isset($_SESSION['staff_id'])) {
    header('Location: login.php');
    exit;
}

requireLogin('agente');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $topicId = $_GET['id'] ?? null;
    
    if (!$topicId) {
        echo json_encode(['success' => false, 'message' => 'ID de tema requerido']);
        exit;
    }
    
    $topic = fetchOne("SELECT * FROM help_topics WHERE topic_id = ?", [$topicId]);
    
    if ($topic) {
        echo json_encode($topic);
    } else {
        echo json_encode(['success' => false, 'message' => 'Tema no encontrado']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}
?>
