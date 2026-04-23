<?php
/**
 * CERRAR TICKET (con o sin firma del cliente)
 * Endpoint AJAX - POST
 *
 * - Con firma: guarda PNG en /firmas/ticket_{id}.png, ruta en BD
 * - Sin firma: envía notificación interna a agentes configurados
 */

require_once '../config.php';
require_once '../includes/helpers.php';
require_once '../includes/Auth.php';
require_once '../includes/Mailer.php';

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
$close_message = trim((string)($_POST['close_message'] ?? ''));
$signature_data = trim((string)($_POST['signature_data'] ?? ''));
$requested_status_id = isset($_POST['status_id']) && is_numeric($_POST['status_id']) ? (int)$_POST['status_id'] : 5;

if ($ticket_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID de ticket inválido']);
    exit;
}

$stmt = $mysqli->prepare(
    'SELECT t.id, t.ticket_number, t.status_id, t.subject, t.empresa_id,
            u.firstname AS user_first, u.lastname AS user_last, u.email AS user_email
     FROM tickets t
     LEFT JOIN users u ON u.id = t.user_id
     WHERE t.id = ?'
);
$stmt->bind_param('i', $ticket_id);
$stmt->execute();
$ticket = $stmt->get_result()->fetch_assoc();

if (!$ticket) {
    echo json_encode(['success' => false, 'error' => 'Ticket no encontrado']);
    exit;
}

$status_closed = ($requested_status_id > 0 ? $requested_status_id : 5);
if ($status_closed !== 5) {
    $stmt_status = $mysqli->prepare('SELECT name FROM ticket_status WHERE id = ? LIMIT 1');
    if (!$stmt_status) {
        echo json_encode(['success' => false, 'error' => 'No se pudo validar el estado de cierre']);
        exit;
    }
    $stmt_status->bind_param('i', $status_closed);
    $stmt_status->execute();
    $status_row = $stmt_status->get_result()->fetch_assoc();
    $status_name = strtolower(trim((string)($status_row['name'] ?? '')));
    if ($status_name === '' || (!str_contains($status_name, 'cerrad') && !str_contains($status_name, 'closed'))) {
        echo json_encode(['success' => false, 'error' => 'Estado de cierre inválido']);
        exit;
    }
}

if ((int)$ticket['status_id'] === $status_closed) {
    echo json_encode(['success' => false, 'error' => 'El ticket ya está cerrado']);
    exit;
}

$signature_path = null;
$max_signature_bytes = 2 * 1024 * 1024;
$project_root = realpath(__DIR__ . '/..');

if ($signature_data !== '') {
    if (!preg_match('/^data:image\/png;base64,/', $signature_data)) {
        echo json_encode(['success' => false, 'error' => 'Formato de firma inválido. Solo se permite PNG']);
        exit;
    }

    $base64 = preg_replace('/^data:image\/png;base64,/', '', $signature_data);

    $image_data = base64_decode($base64, true);

    if ($image_data === false || strlen($image_data) < 10) {
        echo json_encode(['success' => false, 'error' => 'Datos de firma inválidos']);
        exit;
    }

    if (strlen($image_data) > $max_signature_bytes) {
        echo json_encode(['success' => false, 'error' => 'La firma es demasiado grande (máximo 2 MB)']);
        exit;
    }

    if (function_exists('getimagesizefromstring')) {
        $imgInfo = @getimagesizefromstring($image_data);
        if ($imgInfo === false || $imgInfo[2] !== IMAGETYPE_PNG) {
            echo json_encode(['success' => false, 'error' => 'La firma debe ser una imagen PNG válida']);
            exit;
        }
    }

    if ($project_root === false) {
        echo json_encode(['success' => false, 'error' => 'No se pudo resolver la ruta del proyecto']);
        exit;
    }

    $firmas_dir = $project_root . '/firmas';
    if (!is_dir($firmas_dir)) {
        mkdir($firmas_dir, 0755, true);
    }

    $filename = 'ticket_' . $ticket_id . '.png';
    $filepath = $firmas_dir . '/' . $filename;

    if (file_exists($filepath)) {
        @unlink($filepath);
    }

    if (file_put_contents($filepath, $image_data) === false) {
        echo json_encode(['success' => false, 'error' => 'Error al guardar la firma en el servidor']);
        exit;
    }

    @chmod($filepath, 0644);

    $signature_path = 'firmas/' . $filename;
}

$has_signature_col = dbColumnExists('tickets', 'client_signature');
$has_message_col = dbColumnExists('tickets', 'close_message');
$has_closed_at_col = dbColumnExists('tickets', 'closed_at');
$has_closed_legacy_col = dbColumnExists('tickets', 'closed');

if (!$has_signature_col) {
    @$mysqli->query('ALTER TABLE tickets ADD COLUMN client_signature VARCHAR(255) NULL');
}
if (!$has_message_col) {
    @$mysqli->query('ALTER TABLE tickets ADD COLUMN close_message TEXT NULL');
}
if (!$has_closed_at_col) {
    @$mysqli->query('ALTER TABLE tickets ADD COLUMN closed_at DATETIME NULL');
}

$legacy_closed_set = $has_closed_legacy_col ? ', closed = NOW()' : '';

if ($signature_path !== null) {
    $stmt = $mysqli->prepare(
        'UPDATE tickets
         SET status_id = ?, staff_id = ?, close_message = ?, client_signature = ?, closed_at = NOW()' . $legacy_closed_set . ', updated = NOW()
         WHERE id = ?'
    );
    $stmt->bind_param('iissi', $status_closed, $_SESSION['staff_id'], $close_message, $signature_path, $ticket_id);
} else {
    $stmt = $mysqli->prepare(
        'UPDATE tickets
         SET status_id = ?, staff_id = ?, close_message = ?, closed_at = NOW()' . $legacy_closed_set . ', updated = NOW()
         WHERE id = ?'
    );
    $stmt->bind_param('iisi', $status_closed, $_SESSION['staff_id'], $close_message, $ticket_id);
}

if (!$stmt->execute()) {
    if ($signature_path !== null) {
        $full = $project_root . '/' . $signature_path;
        if (file_exists($full)) {
            @unlink($full);
        }
    }
    echo json_encode(['success' => false, 'error' => 'Error al actualizar el ticket']);
    exit;
}

if ($signature_path === null) {
    $hasRecipients = dbTableExists('notification_recipients');
    if ($hasRecipients) {
        $stmt_nr = $mysqli->prepare(
            'SELECT nr.staff_id FROM notification_recipients nr WHERE nr.empresa_id = ?'
        );
        $eid = empresaId();
        $stmt_nr->bind_param('i', $eid);
        $stmt_nr->execute();
        $recipients = $stmt_nr->get_result()->fetch_all(MYSQLI_ASSOC);
        $hasNotif = dbTableExists('notifications');
        if ($hasNotif && !empty($recipients)) {
            $hasNotifEmpresa = dbColumnExists('notifications', 'empresa_id');
            $eid = empresaId();

            if ($hasNotifEmpresa) {
                $stmt_notif = $mysqli->prepare(
                    'INSERT INTO notifications (empresa_id, staff_id, message, type, related_id, is_read, created_at)
                     VALUES (?, ?, ?, ?, ?, 0, NOW())'
                );
            } else {
                $stmt_notif = $mysqli->prepare(
                    'INSERT INTO notifications (staff_id, message, type, related_id, is_read, created_at)
                     VALUES (?, ?, ?, ?, 0, NOW())'
                );
            }

            if ($stmt_notif) {
                $notif_msg = 'Ticket #' . $ticket['ticket_number'] . ' cerrado sin firma del cliente.';
                $notif_type = 'ticket_closed';

                foreach ($recipients as $rec) {
                    $sid = (int)$rec['staff_id'];
                    if ($sid === (int)$_SESSION['staff_id']) continue;

                    if ($hasNotifEmpresa) {
                        $stmt_notif->bind_param('iissi', $eid, $sid, $notif_msg, $notif_type, $ticket_id);
                    } else {
                        $stmt_notif->bind_param('issi', $sid, $notif_msg, $notif_type, $ticket_id);
                    }
                    $stmt_notif->execute();
                }
            }
        }
    }
}

$ticket_url_staff = rtrim((string)APP_URL, '/') . '/upload/scp/tickets.php?id=' . (int)$ticket_id;
$ticket_url_user = rtrim((string)APP_URL, '/') . '/upload/view-ticket.php?id=' . (int)$ticket_id;
$ticket_no = (string)($ticket['ticket_number'] ?? ('#' . $ticket_id));
$ticket_subject = trim((string)($ticket['subject'] ?? 'Ticket'));
$status_label = 'Cerrado';
$company_name = (string)(defined('APP_NAME') ? APP_NAME : 'Sistema de Tickets');
$eid_mail = (int)($ticket['empresa_id'] ?? empresaId());

$stmt_status_name = $mysqli->prepare('SELECT name FROM ticket_status WHERE id = ? LIMIT 1');
if ($stmt_status_name) {
    $stmt_status_name->bind_param('i', $status_closed);
    if ($stmt_status_name->execute()) {
        $r_st = $stmt_status_name->get_result()->fetch_assoc();
        $nm = trim((string)($r_st['name'] ?? ''));
        if ($nm !== '') $status_label = $nm;
    }
}

$client_name = trim((string)($ticket['user_first'] ?? '') . ' ' . (string)($ticket['user_last'] ?? ''));
if ($client_name === '') $client_name = 'Cliente';
$client_email = strtolower(trim((string)($ticket['user_email'] ?? '')));
$token = hash_hmac('sha256', (string)$ticket_id, defined('SECRET_KEY') ? SECRET_KEY : 'default-secret');
$client_pdf_url = rtrim((string)APP_URL, '/') . '/upload/ticket_pdf.php?id=' . (int)$ticket_id . '&t=' . $token;

// ==========================================
// 1. RESPUESTA INMEDIATA (AJAX CIERRE RÁPIDO)
// ==========================================
$response_payload = json_encode([
    'success' => true,
    'message' => 'Ticket cerrado correctamente',
    'signature' => $signature_path
]);

if (ob_get_level()) {
    ob_end_clean();
}
header('Connection: close');
header('Content-Length: ' . strlen($response_payload));
echo $response_payload;
flush();
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

// ==========================================
// 2. PROCESAMIENTO EN SEGUNDO PLANO (PDF/EMAIL)
// ==========================================

// Generar PDF internamente para adjuntar a los correos
$pdf_bytes = null;
try {
    $pdf_project_root = realpath(__DIR__ . '/..');
    if ($pdf_project_root !== false) {
        if (!class_exists('TicketPdfGenerator')) {
            $tpgFile = $pdf_project_root . '/includes/TicketPdfGenerator.php';
            if (is_file($tpgFile)) require_once $tpgFile;
        }
        if (class_exists('TicketPdfGenerator')) {
            $pdf_bytes = TicketPdfGenerator::generate((int)$ticket_id, $mysqli, $pdf_project_root);
        }
    }
} catch (Throwable $e) {
    $pdf_bytes = null;
}

$safe_ticket_no = preg_replace('~[^A-Za-z0-9_-]+~', '_', $ticket_no);
$pdf_attachment = ($pdf_bytes !== null) ? [
    [
        'filename'    => 'Ticket_' . $safe_ticket_no . '.pdf',
        'contentType' => 'application/pdf',
        'content'     => $pdf_bytes,
    ]
] : [];

if ($client_email !== '' && filter_var($client_email, FILTER_VALIDATE_EMAIL)) {
    $client_subject = '[Ticket cerrado] ' . $ticket_no . ' - ' . $ticket_subject;
    $client_body_html = '<div style="font-family:Segoe UI,Arial,sans-serif;max-width:680px;margin:0 auto;">'
        . '<h2 style="margin:0 0 10px;color:#1e3a5f;">Tu ticket fue cerrado</h2>'
        . '<p>Hola ' . html($client_name) . ',</p>'
        . '<p>Te informamos que tu ticket <strong>' . html($ticket_no) . '</strong> fue marcado como <strong>' . html($status_label) . '</strong>.</p>'
        . '<p><strong>Asunto:</strong> ' . html($ticket_subject) . '</p>'
        . '<p style="margin-top:14px;color:#64748b;font-size:12px;">' . html($company_name) . '</p>'
        . '</div>';
    $client_body_text = "Hola " . $client_name . ",\n\n"
        . "Tu ticket " . $ticket_no . " fue marcado como " . $status_label . ".\n"
        . "Asunto: " . $ticket_subject . "\n\n"
        . $company_name;
    if (!empty($pdf_attachment)) {
        Mailer::sendWithOptions($client_email, $client_subject, $client_body_html, $client_body_text, [
            'attachments' => $pdf_attachment,
        ]);
    } else {
        Mailer::send($client_email, $client_subject, $client_body_html, $client_body_text);
    }
}

$admin_recipients = [];
if (dbTableExists('notification_recipients')) {
    $staff_has_empresa = false;
    try {
        $staff_has_empresa = dbColumnExists('staff', 'empresa_id');
    } catch (Throwable $e) {
        $staff_has_empresa = false;
    }

    $sql_admin = "SELECT s.email FROM notification_recipients nr "
        . "INNER JOIN staff s ON s.id = nr.staff_id "
        . "WHERE nr.empresa_id = ? AND s.is_active = 1";
    if ($staff_has_empresa) {
        $sql_admin .= ' AND s.empresa_id = ?';
    }

    $stmt_admin = $mysqli->prepare($sql_admin);
    if ($stmt_admin) {
        if ($staff_has_empresa) {
            $stmt_admin->bind_param('ii', $eid_mail, $eid_mail);
        } else {
            $stmt_admin->bind_param('i', $eid_mail);
        }
        if ($stmt_admin->execute()) {
            $rs_admin = $stmt_admin->get_result();
            while ($rs_admin && ($row_admin = $rs_admin->fetch_assoc())) {
                $em = strtolower(trim((string)($row_admin['email'] ?? '')));
                if ($em === '' || !filter_var($em, FILTER_VALIDATE_EMAIL)) continue;
                $admin_recipients[$em] = true;
            }
        }
    }
}

$admin_pdf_url = rtrim((string)APP_URL, '/') . '/upload/scp/ticket_pdf.php?id=' . (int)$ticket_id . '&t=' . $token;

if (!empty($admin_recipients)) {
    $closed_with_signature = ($signature_path !== null);
    $admin_subject = '[Ticket cerrado] ' . $ticket_no . ' - ' . $ticket_subject;
    $admin_body_html = '<div style="font-family:Segoe UI,Arial,sans-serif;max-width:680px;margin:0 auto;">'
        . '<h2 style="margin:0 0 10px;color:#1e3a5f;">Ticket cerrado</h2>'
        . '<p>Un ticket fue cerrado en el sistema.</p>'
        . '<table style="width:100%;border-collapse:collapse;margin:10px 0 14px;">'
        . '<tr><td style="padding:6px 0;border-bottom:1px solid #e2e8f0;"><strong>Número:</strong></td><td style="padding:6px 0;border-bottom:1px solid #e2e8f0;">' . html($ticket_no) . '</td></tr>'
        . '<tr><td style="padding:6px 0;border-bottom:1px solid #e2e8f0;"><strong>Asunto:</strong></td><td style="padding:6px 0;border-bottom:1px solid #e2e8f0;">' . html($ticket_subject) . '</td></tr>'
        . '<tr><td style="padding:6px 0;border-bottom:1px solid #e2e8f0;"><strong>Estado:</strong></td><td style="padding:6px 0;border-bottom:1px solid #e2e8f0;">' . html($status_label) . '</td></tr>'
        . '<tr><td style="padding:6px 0;"><strong>Firma cliente:</strong></td><td style="padding:6px 0;">' . ($closed_with_signature ? 'Sí' : 'No') . '</td></tr>'
        . '</table>'
        . '<p style="margin-top:14px;color:#64748b;font-size:12px;">' . html($company_name) . '</p>'
        . '</div>';
    $admin_body_text = "Ticket cerrado\n\n"
        . "Numero: " . $ticket_no . "\n"
        . "Asunto: " . $ticket_subject . "\n"
        . "Estado: " . $status_label . "\n"
        . "Firma cliente: " . ($closed_with_signature ? 'Si' : 'No') . "\n\n"
        . $company_name;

    foreach (array_keys($admin_recipients) as $admin_email) {
        if (!empty($pdf_attachment)) {
            Mailer::sendWithOptions($admin_email, $admin_subject, $admin_body_html, $admin_body_text, [
                'attachments' => $pdf_attachment,
            ]);
        } else {
            Mailer::send($admin_email, $admin_subject, $admin_body_html, $admin_body_text);
        }
    }
}

triggerEmailQueueWorkerAsync(40);
// No enviamos JSON aquí porque ya se mandó en el paso 1
