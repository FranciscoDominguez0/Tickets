<?php
/**
 * Controlador de vista de detalle de Cotización
 */
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: cotizaciones.php');
    exit;
}

$errors = [];
$success = '';

// Obtener la cotización
$stmt = $mysqli->prepare("SELECT q.*, 
            o.name as org_name, o.website as org_website,
            CONCAT(s.firstname, ' ', s.lastname) as staff_name,
            (SELECT CONCAT(u.firstname, ' ', u.lastname) FROM user_organizations uo JOIN users u ON u.id = uo.user_id WHERE uo.organization_id = o.id AND u.org_tickets_view = 1 AND u.empresa_id = ? LIMIT 1) as org_boss_name,
            (SELECT u.id FROM user_organizations uo JOIN users u ON u.id = uo.user_id WHERE uo.organization_id = o.id AND u.org_tickets_view = 1 AND u.empresa_id = ? LIMIT 1) as org_boss_id
            FROM quotes q 
            LEFT JOIN organizations o ON q.org_id = o.id 
            LEFT JOIN staff s ON q.staff_id = s.id 
            WHERE q.id = ? AND q.empresa_id = ?");
if ($stmt) {
    $stmt->bind_param('iiii', $eid, $eid, $id, $eid);
    $stmt->execute();
    $qResult = $stmt->get_result();
    $quote = $qResult->fetch_assoc();
}

if (!$quote) {
    $_SESSION['flash_error'] = 'Cotización no encontrada.';
    header('Location: cotizaciones.php');
    exit;
}

// Obtener el hilo de mensajes
$messages = [];
$stmtMsg = $mysqli->prepare("SELECT m.*, 
    CONCAT(s.firstname, ' ', s.lastname) as staff_name,
    CONCAT(u.firstname, ' ', u.lastname) as user_name
    FROM quote_messages m
    LEFT JOIN staff s ON m.staff_id = s.id
    LEFT JOIN users u ON m.user_id = u.id
    WHERE m.quote_id = ?
    ORDER BY m.created_at ASC");
if ($stmtMsg) {
    $stmtMsg->bind_param('i', $id);
    $stmtMsg->execute();
    $msgResult = $stmtMsg->get_result();
    while ($row = $msgResult->fetch_assoc()) {
        $messages[] = $row;
    }
}

// Procesar acciones (Subir archivo, Publicar Mensaje)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::validateCSRF($_POST['csrf_token'] ?? '');
    $actionType = $_POST['action_type'] ?? '';

    if ($actionType === 'post_message') {
        $messageText = trim($_POST['message'] ?? '');
        $staffId = (int)($_SESSION['staff_id'] ?? 0);

        if (empty($messageText)) {
            $errors[] = 'El mensaje no puede estar vacío.';
        } else {
            $dbPath = null;
            if (isset($_FILES['quote_file']) && $_FILES['quote_file']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/../../../uploads/attachments/';
                if (!is_dir($uploadDir)) {
                    @mkdir($uploadDir, 0755, true);
                }
                $filename = basename($_FILES['quote_file']['name']);
                $filename = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $filename);
                $newFileName = time() . '_' . $filename;
                $destPath = $uploadDir . $newFileName;
                
                if (move_uploaded_file($_FILES['quote_file']['tmp_name'], $destPath)) {
                    $dbPath = 'upload/uploads/attachments/' . $newFileName;
                } else {
                    $errors[] = 'Error al mover el archivo subido.';
                }
            }

            if (empty($errors)) {
                $insStmt = $mysqli->prepare("INSERT INTO quote_messages (quote_id, staff_id, message, file_path) VALUES (?, ?, ?, ?)");
                if ($insStmt) {
                    $insStmt->bind_param('iiss', $id, $staffId, $messageText, $dbPath);
                    $insStmt->execute();
                    
                    // Actualizar el archivo principal de la cotización si se adjuntó uno nuevo, y cambiar a answered (Esperando Aprobación)
                    if ($dbPath) {
                        $updStmt = $mysqli->prepare("UPDATE quotes SET file_path = ?, status = 'answered' WHERE id = ?");
                        $updStmt->bind_param('si', $dbPath, $id);
                    } else {
                        // Cambiar estado a answered
                        $updStmt = $mysqli->prepare("UPDATE quotes SET status = 'answered' WHERE id = ?");
                        $updStmt->bind_param('i', $id);
                    }
                    $updStmt->execute();
                    
                    // Enviar correo de notificación al jefe de la organización
                    sendQuoteEmailToOrgBoss($id, $messageText, false, $mysqli);
                    
                    $_SESSION['flash_msg'] = 'Mensaje publicado correctamente.';
                    header("Location: cotizaciones.php?id=" . $id);
                    exit;
                }
            }
        }
    }
}

require __DIR__ . '/cotizaciones-view-view.inc.php';
