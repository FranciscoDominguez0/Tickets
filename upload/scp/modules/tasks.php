<?php
// Módulo: Tareas
// Similar a osTicket tasks.php pero adaptado al sistema

$task = null;
$errors = [];
$success = false;
$tasksHasDept = false;

if (isset($_SESSION['task_success_flash']) && is_string($_SESSION['task_success_flash']) && $_SESSION['task_success_flash'] !== '') {
    $success = (string)$_SESSION['task_success_flash'];
    unset($_SESSION['task_success_flash']);
}

$chk = $mysqli->query("SHOW COLUMNS FROM tasks LIKE 'dept_id'");
if ($chk && $chk->num_rows > 0) {
    $tasksHasDept = true;
}

$departments = [];
$rDept = $mysqli->query("SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name");
if ($rDept) {
    while ($row = $rDept->fetch_assoc()) {
        $departments[] = $row;
    }
}

$agentsByDept = [];
$rAgents = $mysqli->query("SELECT id, CONCAT(firstname, ' ', lastname) AS name, dept_id FROM staff WHERE is_active = 1 AND role = 'agent' ORDER BY firstname, lastname");
if ($rAgents) {
    while ($row = $rAgents->fetch_assoc()) {
        $did = (int)($row['dept_id'] ?? 0);
        if (!isset($agentsByDept[$did])) $agentsByDept[$did] = [];
        $agentsByDept[$did][] = [
            'id' => (int)$row['id'],
            'name' => (string)$row['name'],
        ];
    }
}

$agentsJson = json_encode($agentsByDept, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

// Si viene acción POST desde la vista detalle, se envía task_id.
// Cargamos la tarea para que las acciones funcionen incluso si la URL no trae ?id=
if (!$task && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['task_id']) && is_numeric($_POST['task_id'])) {
    $_GET['id'] = (int) $_POST['task_id'];
}

// Cargar tarea específica si id está presente
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $task_id = (int) $_GET['id'];
    if ($tasksHasDept) {
        $stmt = $mysqli->prepare(
            "SELECT t.*, 
             CONCAT(s1.firstname, ' ', s1.lastname) AS assigned_name,
             CONCAT(s2.firstname, ' ', s2.lastname) AS created_name,
             d.name AS dept_name
             FROM tasks t
             LEFT JOIN staff s1 ON t.assigned_to = s1.id
             LEFT JOIN staff s2 ON t.created_by = s2.id
             LEFT JOIN departments d ON t.dept_id = d.id
             WHERE t.id = ?"
        );
    } else {
        $stmt = $mysqli->prepare(
            "SELECT t.*, 
             CONCAT(s1.firstname, ' ', s1.lastname) AS assigned_name,
             CONCAT(s2.firstname, ' ', s2.lastname) AS created_name
             FROM tasks t
             LEFT JOIN staff s1 ON t.assigned_to = s1.id
             LEFT JOIN staff s2 ON t.created_by = s2.id
             WHERE t.id = ?"
        );
    }
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
                $dept_id = isset($_POST['dept_id']) && is_numeric($_POST['dept_id']) ? (int) $_POST['dept_id'] : 0;
                $priority = $_POST['priority'] ?? 'normal';
                $due_date = trim($_POST['due_date'] ?? '');
                
                if (empty($title)) {
                    $errors[] = 'El título es obligatorio.';
                }

                if (!$tasksHasDept) {
                    $errors[] = "Falta configurar el departamento en tareas. Ejecuta: ALTER TABLE tasks ADD dept_id INT NOT NULL AFTER created_by; ALTER TABLE tasks ADD CONSTRAINT fk_tasks_dept FOREIGN KEY (dept_id) REFERENCES departments(id);";
                } else {
                    if ($dept_id <= 0) {
                        $errors[] = 'El departamento es obligatorio.';
                    }
                }

                if ($tasksHasDept && $assigned_to) {
                    $stmtA = $mysqli->prepare("SELECT id FROM staff WHERE id = ? AND is_active = 1 AND role = 'agent' AND dept_id = ? LIMIT 1");
                    if ($stmtA) {
                        $stmtA->bind_param('ii', $assigned_to, $dept_id);
                        $stmtA->execute();
                        $arow = $stmtA->get_result()->fetch_assoc();
                        if (!$arow) {
                            $errors[] = 'El agente seleccionado no pertenece al departamento.';
                        }
                    }
                }
                
                if (empty($errors)) {
                    $due_date_sql = $due_date ? date('Y-m-d H:i:s', strtotime($due_date)) : null;
                    $stmt = $mysqli->prepare(
                        "INSERT INTO tasks (title, description, assigned_to, created_by, dept_id, priority, due_date, created) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
                    );
                    $createdBy = (int)($_SESSION['staff_id'] ?? 0);
                    $stmt->bind_param('ssiiiss', $title, $description, $assigned_to, $createdBy, $dept_id, $priority, $due_date_sql);
                    if ($stmt->execute()) {
                        $taskId = (int)$mysqli->insert_id;

                        $mailProblem = false;

                        // Notificación en BD + correo (solo si se creó asignada a alguien)
                        $newAssignedTo = $assigned_to ? (int)$assigned_to : null;
                        if ($newAssignedTo !== null) {
                            addLog('task_assigned', 'Asignación de tarea a agente', 'task', $taskId, 'staff', $newAssignedTo);

                            $message = 'Se te ha asignado una nueva tarea: ' . (string)$title;
                            $type = 'task_assigned';
                            $stmtN = $mysqli->prepare('INSERT INTO notifications (staff_id, message, type, related_id, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())');
                            if ($stmtN) {
                                $stmtN->bind_param('issi', $newAssignedTo, $message, $type, $taskId);
                                $stmtN->execute();
                            } else {
                                addLog('task_assign_notification_failed', 'No se pudo preparar INSERT notifications', 'task', $taskId, 'staff', $newAssignedTo);
                            }

                            $stmtS = $mysqli->prepare('SELECT email, firstname, lastname FROM staff WHERE id = ? AND is_active = 1 LIMIT 1');
                            if ($stmtS) {
                                $stmtS->bind_param('i', $newAssignedTo);
                                if ($stmtS->execute()) {
                                    $srow = $stmtS->get_result()->fetch_assoc();
                                    $to = trim((string)($srow['email'] ?? ''));
                                    if ($to !== '' && filter_var($to, FILTER_VALIDATE_EMAIL)) {
                                        $staffName = trim((string)($srow['firstname'] ?? '') . ' ' . (string)($srow['lastname'] ?? ''));
                                        if ($staffName === '') $staffName = 'Agente';

                                        $subj = '[Asignación] Tarea #' . $taskId . ' - ' . ((string)$title !== '' ? (string)$title : 'Tarea');
                                        $viewUrl = (defined('APP_URL') ? APP_URL : '') . '/upload/scp/tasks.php?id=' . $taskId;

                                        $bodyHtml = '<div style="font-family: Segoe UI, sans-serif; max-width: 700px; margin: 0 auto;">'
                                            . '<h2 style="color:#1e3a5f; margin: 0 0 8px;">Se te asignó una tarea</h2>'
                                            . '<p style="color:#475569; margin: 0 0 12px;">Hola <strong>' . htmlspecialchars($staffName) . '</strong>, se te asignó la siguiente tarea:</p>'
                                            . '<table style="width:100%; border-collapse: collapse; margin: 12px 0;">'
                                            . '<tr><td style="padding: 6px 0; border-bottom:1px solid #eee;"><strong>ID:</strong></td><td style="padding: 6px 0; border-bottom:1px solid #eee;">#' . htmlspecialchars((string)$taskId) . '</td></tr>'
                                            . '<tr><td style="padding: 6px 0; border-bottom:1px solid #eee;"><strong>Título:</strong></td><td style="padding: 6px 0; border-bottom:1px solid #eee;">' . htmlspecialchars((string)$title) . '</td></tr>'
                                            . '<tr><td style="padding: 6px 0;"><strong>Departamento:</strong></td><td style="padding: 6px 0;">' . htmlspecialchars((string)($departments[$dept_id]['name'] ?? '')) . '</td></tr>'
                                            . '</table>'
                                            . '<p style="margin: 14px 0 0;"><a href="' . htmlspecialchars($viewUrl) . '" style="display:inline-block; background:#2563eb; color:#fff; padding:10px 16px; text-decoration:none; border-radius:8px;">Ver tarea</a></p>'
                                            . '<p style="color:#94a3b8; font-size:12px; margin-top: 14px;">' . htmlspecialchars(defined('APP_NAME') ? APP_NAME : 'Sistema de Tickets') . '</p>'
                                            . '</div>';

                                        $bodyText = 'Se te asignó una tarea: #' . $taskId . "\n" . 'Título: ' . (string)$title . "\n" . 'Ver: ' . $viewUrl;
                                        $mailOk = Mailer::send($to, $subj, $bodyHtml, $bodyText);
                                        if (!$mailOk) {
                                            $err = (string)(Mailer::$lastError ?? 'Error desconocido');
                                            addLog('task_assign_email_failed', $err, 'task', $taskId, 'staff', $newAssignedTo);
                                            $errors[] = 'No se pudo enviar el correo al agente asignado. ' . $err;
                                            $mailProblem = true;
                                        }
                                    } else {
                                        addLog('task_assign_email_missing', 'Agente sin email válido', 'task', $taskId, 'staff', $newAssignedTo);
                                        $errors[] = 'El agente asignado no tiene un email válido para enviar notificación.';
                                        $mailProblem = true;
                                    }
                                } else {
                                    addLog('task_assign_email_lookup_failed', 'No se pudo ejecutar SELECT staff(email)', 'task', $taskId, 'staff', $newAssignedTo);
                                }
                            } else {
                                addLog('task_assign_email_lookup_failed', 'No se pudo preparar SELECT staff(email)', 'task', $taskId, 'staff', $newAssignedTo);
                            }
                        }

                        $success = 'Tarea creada exitosamente.';
                        if (!$mailProblem) {
                            $_SESSION['task_success_flash'] = (string)$success;
                            header('Location: tasks.php?id=' . $taskId);
                            exit;
                        }
                    } else {
                        $errors[] = 'Error al crear la tarea.';
                    }
                }
                break;
                
            case 'update_status':
                if ($task && isset($_POST['status'])) {
                    $new_status = $_POST['status'];
                    $valid_statuses = ['pending', 'in_progress', 'completed'];
                    if (in_array($new_status, $valid_statuses)) {
                        $stmt = $mysqli->prepare("UPDATE tasks SET status = ?, updated = NOW() WHERE id = ?");
                        $stmt->bind_param('si', $new_status, $task['id']);
                        if ($stmt->execute()) {
                            $_SESSION['task_success_flash'] = 'Estado de la tarea actualizado.';
                            // Redirigir para limpiar POST
                            header('Location: ' . $_SERVER['REQUEST_URI']);
                            exit;
                        } else {
                            $errors[] = 'Error al actualizar el estado.';
                        }
                    }
                }
                break;
                
            case 'update':
                // Actualizar tarea
                if (!$task) {
                    $errors[] = 'Tarea no encontrada.';
                    break;
                }
                $previousAssignedTo = isset($task['assigned_to']) && $task['assigned_to'] !== '' ? (int)$task['assigned_to'] : null;
                $title = trim($_POST['title'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $status = $_POST['status'] ?? $task['status'];
                $assigned_to = isset($_POST['assigned_to']) && is_numeric($_POST['assigned_to']) ? (int) $_POST['assigned_to'] : null;
                $dept_id = isset($_POST['dept_id']) && is_numeric($_POST['dept_id']) ? (int) $_POST['dept_id'] : 0;
                $priority = $_POST['priority'] ?? $task['priority'];
                $due_date = trim($_POST['due_date'] ?? '');
                
                if (empty($title)) {
                    $errors[] = 'El título es obligatorio.';
                }

                if (!$tasksHasDept) {
                    $errors[] = "Falta configurar el departamento en tareas. Ejecuta: ALTER TABLE tasks ADD dept_id INT NOT NULL AFTER created_by; ALTER TABLE tasks ADD CONSTRAINT fk_tasks_dept FOREIGN KEY (dept_id) REFERENCES departments(id);";
                } else {
                    if ($dept_id <= 0) {
                        $errors[] = 'El departamento es obligatorio.';
                    }
                }

                if ($tasksHasDept && $assigned_to) {
                    $stmtA = $mysqli->prepare("SELECT id FROM staff WHERE id = ? AND is_active = 1 AND role = 'agent' AND dept_id = ? LIMIT 1");
                    if ($stmtA) {
                        $stmtA->bind_param('ii', $assigned_to, $dept_id);
                        $stmtA->execute();
                        $arow = $stmtA->get_result()->fetch_assoc();
                        if (!$arow) {
                            $errors[] = 'El agente seleccionado no pertenece al departamento.';
                        }
                    }
                }
                
                if (empty($errors)) {
                    $due_date_sql = $due_date ? date('Y-m-d H:i:s', strtotime($due_date)) : null;
                    $stmt = $mysqli->prepare(
                        "UPDATE tasks SET title = ?, description = ?, status = ?, assigned_to = ?, dept_id = ?, priority = ?, due_date = ?, updated = NOW() WHERE id = ?"
                    );
                    $stmt->bind_param('sssiissi', $title, $description, $status, $assigned_to, $dept_id, $priority, $due_date_sql, $task['id']);
                    if ($stmt->execute()) {
                        $mailProblem = false;

                        // Notificación en BD + correo (solo si se asignó a alguien y cambió)
                        $newAssignedTo = $assigned_to ? (int)$assigned_to : null;
                        if ($newAssignedTo !== null && (string)$previousAssignedTo !== (string)$newAssignedTo) {
                            $taskId = (int)$task['id'];
                            $taskTitle = (string)$title;

                            addLog('task_assigned', 'Asignación de tarea a agente', 'task', $taskId, 'staff', $newAssignedTo);

                            // Notificación
                            $message = 'Se te ha asignado una nueva tarea: ' . $taskTitle;
                            $type = 'task_assigned';
                            $stmtN = $mysqli->prepare('INSERT INTO notifications (staff_id, message, type, related_id, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())');
                            if ($stmtN) {
                                $stmtN->bind_param('issi', $newAssignedTo, $message, $type, $taskId);
                                $stmtN->execute();
                            } else {
                                addLog('task_assign_notification_failed', 'No se pudo preparar INSERT notifications', 'task', $taskId, 'staff', $newAssignedTo);
                            }

                            // Email
                            $stmtS = $mysqli->prepare('SELECT email, firstname, lastname FROM staff WHERE id = ? AND is_active = 1 LIMIT 1');
                            if ($stmtS) {
                                $stmtS->bind_param('i', $newAssignedTo);
                                if ($stmtS->execute()) {
                                    $srow = $stmtS->get_result()->fetch_assoc();
                                    $to = trim((string)($srow['email'] ?? ''));
                                    if ($to !== '' && filter_var($to, FILTER_VALIDATE_EMAIL)) {
                                        $staffName = trim((string)($srow['firstname'] ?? '') . ' ' . (string)($srow['lastname'] ?? ''));
                                        if ($staffName === '') $staffName = 'Agente';

                                        $subj = '[Asignación] Tarea #' . $taskId . ' - ' . ($taskTitle !== '' ? $taskTitle : 'Tarea');
                                        $viewUrl = (defined('APP_URL') ? APP_URL : '') . '/upload/scp/tasks.php?id=' . $taskId;

                                        $bodyHtml = '<div style="font-family: Segoe UI, sans-serif; max-width: 700px; margin: 0 auto;">'
                                            . '<h2 style="color:#1e3a5f; margin: 0 0 8px;">Se te asignó una tarea</h2>'
                                            . '<p style="color:#475569; margin: 0 0 12px;">Hola <strong>' . htmlspecialchars($staffName) . '</strong>, se te asignó la siguiente tarea:</p>'
                                            . '<table style="width:100%; border-collapse: collapse; margin: 12px 0;">'
                                            . '<tr><td style="padding: 6px 0; border-bottom:1px solid #eee;"><strong>ID:</strong></td><td style="padding: 6px 0; border-bottom:1px solid #eee;">#' . htmlspecialchars((string)$taskId) . '</td></tr>'
                                            . '<tr><td style="padding: 6px 0; border-bottom:1px solid #eee;"><strong>Título:</strong></td><td style="padding: 6px 0; border-bottom:1px solid #eee;">' . htmlspecialchars($taskTitle) . '</td></tr>'
                                            . '<tr><td style="padding: 6px 0;"><strong>Departamento:</strong></td><td style="padding: 6px 0;">' . htmlspecialchars((string)($task['dept_name'] ?? '')) . '</td></tr>'
                                            . '</table>'
                                            . '<p style="margin: 14px 0 0;"><a href="' . htmlspecialchars($viewUrl) . '" style="display:inline-block; background:#2563eb; color:#fff; padding:10px 16px; text-decoration:none; border-radius:8px;">Ver tarea</a></p>'
                                            . '<p style="color:#94a3b8; font-size:12px; margin-top: 14px;">' . htmlspecialchars(defined('APP_NAME') ? APP_NAME : 'Sistema de Tickets') . '</p>'
                                            . '</div>';

                                        $bodyText = 'Se te asignó una tarea: #' . $taskId . "\n" . 'Título: ' . $taskTitle . "\n" . 'Ver: ' . $viewUrl;
                                        $mailOk = Mailer::send($to, $subj, $bodyHtml, $bodyText);
                                        if (!$mailOk) {
                                            $err = (string)(Mailer::$lastError ?? 'Error desconocido');
                                            addLog('task_assign_email_failed', $err, 'task', $taskId, 'staff', $newAssignedTo);
                                            $errors[] = 'No se pudo enviar el correo al agente asignado. ' . $err;
                                            $mailProblem = true;
                                        }
                                    } else {
                                        addLog('task_assign_email_missing', 'Agente sin email válido', 'task', $taskId, 'staff', $newAssignedTo);
                                        $errors[] = 'El agente asignado no tiene un email válido para enviar notificación.';
                                        $mailProblem = true;
                                    }
                                } else {
                                    addLog('task_assign_email_lookup_failed', 'No se pudo ejecutar SELECT staff(email)', 'task', $taskId, 'staff', $newAssignedTo);
                                }
                            } else {
                                addLog('task_assign_email_lookup_failed', 'No se pudo preparar SELECT staff(email)', 'task', $taskId, 'staff', $newAssignedTo);
                            }
                        }

                        $success = 'Tarea actualizada exitosamente.';
                        // Redirigir para limpiar POST y mostrar cambios
                        if (!$mailProblem) {
                            $_SESSION['task_success_flash'] = (string)$success;
                            header('Location: ' . $_SERVER['REQUEST_URI']);
                            exit;
                        }
                    } else {
                        $errors[] = 'Error al actualizar la tarea.';
                    }
                }
                break;

            case 'delete':
                // Eliminar tarea
                if (!$task) {
                    $errors[] = 'Tarea no encontrada.';
                    break;
                }

                $stmt = $mysqli->prepare("DELETE FROM tasks WHERE id = ?");
                $stmt->bind_param('i', $task['id']);
                if ($stmt->execute()) {
                    header('Location: tasks.php?msg=deleted');
                    exit;
                }

                $errors[] = 'Error al eliminar la tarea.';
                break;
        }
    }
}

// Obtener lista de staff para asignación
$staff_list = [];
$result = $mysqli->query("SELECT id, CONCAT(firstname, ' ', lastname) AS name FROM staff WHERE is_active = 1 AND role = 'agent' ORDER BY firstname, lastname");
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
        ($tasksHasDept
            ? "SELECT t.*, 
             CONCAT(s1.firstname, ' ', s1.lastname) AS assigned_name,
             CONCAT(s2.firstname, ' ', s2.lastname) AS created_name,
             d.name AS dept_name
             FROM tasks t
             LEFT JOIN staff s1 ON t.assigned_to = s1.id
             LEFT JOIN staff s2 ON t.created_by = s2.id
             LEFT JOIN departments d ON t.dept_id = d.id
             $where_clause
             ORDER BY t.created DESC"
            : "SELECT t.*, 
             CONCAT(s1.firstname, ' ', s1.lastname) AS assigned_name,
             CONCAT(s2.firstname, ' ', s2.lastname) AS created_name
             FROM tasks t
             LEFT JOIN staff s1 ON t.assigned_to = s1.id
             LEFT JOIN staff s2 ON t.created_by = s2.id
             $where_clause
             ORDER BY t.created DESC"
        )
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

