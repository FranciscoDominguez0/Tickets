<?php
// Módulo: Tareas
// Similar a osTicket tasks.php pero adaptado al sistema

$task = null;
$errors = [];
$success = false;

// Cargar tarea específica si id está presente
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $task_id = (int) $_GET['id'];
    $stmt = $mysqli->prepare(
        "SELECT t.*, 
         CONCAT(s1.firstname, ' ', s1.lastname) AS assigned_name,
         CONCAT(s2.firstname, ' ', s2.lastname) AS created_name
         FROM tasks t
         LEFT JOIN staff s1 ON t.assigned_to = s1.id
         LEFT JOIN staff s2 ON t.created_by = s2.id
         WHERE t.id = ?"
    );
    $stmt->bind_param('i', $task_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $task = $result->fetch_assoc();
    if (!$task) {
        $errors[] = 'Tarea no encontrada.';
    }
}

// Procesar acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do'])) {
    if (!isset($_POST['csrf_token']) || !Auth::validateCSRF($_POST['csrf_token'])) {
        $errors[] = 'Token de seguridad inválido.';
    } else {
        switch ($_POST['do']) {
            case 'create':
                // Crear nueva tarea
                $title = trim($_POST['title'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $assigned_to = isset($_POST['assigned_to']) && is_numeric($_POST['assigned_to']) ? (int) $_POST['assigned_to'] : null;
                $priority = $_POST['priority'] ?? 'normal';
                $due_date = trim($_POST['due_date'] ?? '');
                
                if (empty($title)) {
                    $errors[] = 'El título es obligatorio.';
                }
                
                if (empty($errors)) {
                    $due_date_sql = $due_date ? date('Y-m-d H:i:s', strtotime($due_date)) : null;
                    $stmt = $mysqli->prepare(
                        "INSERT INTO tasks (title, description, assigned_to, created_by, priority, due_date, created) 
                         VALUES (?, ?, ?, ?, ?, ?, NOW())"
                    );
                    $stmt->bind_param('ssisss', $title, $description, $assigned_to, $_SESSION['staff_id'], $priority, $due_date_sql);
                    if ($stmt->execute()) {
                        $success = 'Tarea creada exitosamente.';
                        // Redirigir a la vista de la tarea
                        header('Location: tasks.php?id=' . $mysqli->insert_id);
                        exit;
                    } else {
                        $errors[] = 'Error al crear la tarea.';
                    }
                }
                break;
                
            case 'update_status':
                if ($task && isset($_POST['status'])) {
                    $new_status = $_POST['status'];
                    $valid_statuses = ['pending', 'in_progress', 'completed', 'cancelled'];
                    if (in_array($new_status, $valid_statuses)) {
                        $stmt = $mysqli->prepare("UPDATE tasks SET status = ?, updated = NOW() WHERE id = ?");
                        $stmt->bind_param('si', $new_status, $task['id']);
                        if ($stmt->execute()) {
                            $success = 'Estado de la tarea actualizado.';
                            // Recargar tarea
                            $stmt = $mysqli->prepare(
                                "SELECT t.*, 
                                 CONCAT(s1.firstname, ' ', s1.lastname) AS assigned_name,
                                 CONCAT(s2.firstname, ' ', s2.lastname) AS created_name
                                 FROM tasks t
                                 LEFT JOIN staff s1 ON t.assigned_to = s1.id
                                 LEFT JOIN staff s2 ON t.created_by = s2.id
                                 WHERE t.id = ?"
                            );
                            $stmt->bind_param('i', $task['id']);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            $task = $result->fetch_assoc();
                        } else {
                            $errors[] = 'Error al actualizar el estado.';
                        }
                    }
                }
                break;
                
            case 'delete':
                if ($task) {
                    $stmt = $mysqli->prepare("DELETE FROM tasks WHERE id = ?");
                    $stmt->bind_param('i', $task['id']);
                    if ($stmt->execute()) {
                        header('Location: tasks.php?msg=deleted');
                        exit;
                    } else {
                        $errors[] = 'Error al eliminar la tarea.';
                    }
                }
                break;
        }
    }
}

// Obtener lista de staff para asignación
$staff_list = [];
$result = $mysqli->query("SELECT id, CONCAT(firstname, ' ', lastname) AS name FROM staff WHERE is_active = 1 ORDER BY firstname, lastname");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $staff_list[] = $row;
    }
}

// Determinar qué vista mostrar
if ($task) {
    // Vista detallada de tarea
    $taskView = $task;
    require __DIR__ . '/task-view.inc.php';
} elseif (isset($_GET['a']) && $_GET['a'] === 'create' || (!empty($errors) && isset($_POST['do']) && $_POST['do'] === 'create')) {
    // Formulario de creación
    require __DIR__ . '/task-create.inc.php';
} else {
    // Lista de tareas
    // Obtener tareas con filtros
    $status_filter = $_GET['status'] ?? '';
    $assigned_filter = $_GET['assigned'] ?? '';
    
    $where = [];
    $params = [];
    $types = '';
    
    if ($status_filter) {
        $where[] = 't.status = ?';
        $params[] = $status_filter;
        $types .= 's';
    }
    
    if ($assigned_filter === 'me') {
        $where[] = 't.assigned_to = ?';
        $params[] = $_SESSION['staff_id'];
        $types .= 'i';
    } elseif ($assigned_filter === 'unassigned') {
        $where[] = 't.assigned_to IS NULL';
    }
    
    $where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $stmt = $mysqli->prepare(
        "SELECT t.*, 
         CONCAT(s1.firstname, ' ', s1.lastname) AS assigned_name,
         CONCAT(s2.firstname, ' ', s2.lastname) AS created_name
         FROM tasks t
         LEFT JOIN staff s1 ON t.assigned_to = s1.id
         LEFT JOIN staff s2 ON t.created_by = s2.id
         $where_clause
         ORDER BY t.created DESC"
    );
    
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $tasks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Estadísticas
    $stats = [];
    $result = $mysqli->query("SELECT status, COUNT(*) as count FROM tasks GROUP BY status");
    while ($row = $result->fetch_assoc()) {
        $stats[$row['status']] = $row['count'];
    }
    
    require __DIR__ . '/tasks.inc.php';
}
?>

