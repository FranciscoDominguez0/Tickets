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
    $topicId = $_POST['topic_id'] ?? null;
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $deptId = $_POST['dept_id'] ?? null;
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    
    if (empty($name) || empty($description)) {
        echo json_encode(['success' => false, 'message' => 'Nombre y descripción son requeridos']);
        exit;
    }
    
    if ($topicId) {
        // Actualizar tema existente
        $sql = "UPDATE help_topics SET name = ?, description = ?, dept_id = ?, is_active = ? WHERE topic_id = ?";
        execute($sql, [$name, $description, $deptId, $isActive, $topicId]);
    } else {
        // Insertar nuevo tema
        $sql = "INSERT INTO help_topics (name, description, dept_id, is_active, created) VALUES (?, ?, ?, ?, NOW())";
        execute($sql, [$name, $description, $deptId, $isActive]);
    }
    
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}
?>
