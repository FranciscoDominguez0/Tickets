<?php
/**
 * FIRMAR TICKET POR EL CLIENTE (Remoto)
 * Endpoint AJAX - POST
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

// En este caso el cliente debe estar logueado como cliente
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Sesión expirada o no autorizada']);
    exit;
}

$ticket_id = (int)($_POST['ticket_id'] ?? 0);
$token = trim((string)($_POST['token'] ?? ''));
$close_message = trim((string)($_POST['close_message'] ?? ''));
$signature_data = trim((string)($_POST['signature_data'] ?? ''));

if ($ticket_id <= 0 || $token === '') {
    echo json_encode(['success' => false, 'error' => 'Parámetros inválidos']);
    exit;
}

// Validar ticket y token
$eid = empresaId();
$uid = (int)$_SESSION['user_id'];
$stmt = $mysqli->prepare(
    'SELECT t.id, t.ticket_number, t.status_id, t.subject, t.empresa_id, t.signature_token,
            u.firstname AS user_first, u.lastname AS user_last, u.email AS user_email
     FROM tickets t
     LEFT JOIN users u ON u.id = t.user_id
     WHERE t.id = ? AND t.user_id = ? AND t.empresa_id = ? AND t.signature_token = ? AND t.closed IS NULL'
);
$stmt->bind_param('iiis', $ticket_id, $uid, $eid, $token);
$stmt->execute();
$ticket = $stmt->get_result()->fetch_assoc();

if (!$ticket) {
    echo json_encode(['success' => false, 'error' => 'Ticket no encontrado o ya cerrado']);
    exit;
}

// Validar firma
if ($signature_data === '') {
    echo json_encode(['success' => false, 'error' => 'La firma es obligatoria']);
    exit;
}

if (!preg_match('/^data:image\/png;base64,/', $signature_data)) {
    echo json_encode(['success' => false, 'error' => 'Formato de firma inválido']);
    exit;
}

$base64 = preg_replace('/^data:image\/png;base64,/', '', $signature_data);
$image_data = base64_decode($base64, true);

if (!$image_data) {
    echo json_encode(['success' => false, 'error' => 'Datos de firma corruptos']);
    exit;
}

// Guardar firma
$project_root = realpath(__DIR__ . '/..');
$firmas_dir = $project_root . '/firmas';
if (!is_dir($firmas_dir)) {
    mkdir($firmas_dir, 0755, true);
}

$filename = 'ticket_' . $ticket_id . '.png';
$filepath = $firmas_dir . '/' . $filename;

if (file_put_contents($filepath, $image_data) === false) {
    echo json_encode(['success' => false, 'error' => 'Error al guardar la firma']);
    exit;
}

$signature_path = 'firmas/' . $filename;

// Cerrar ticket (Estado 5 = Cerrado usualmente)
$status_closed = 5; 
// Intentar buscar el ID de un estado que se llame "Cerrado"
$resSt = $mysqli->query("SELECT id FROM ticket_status WHERE name LIKE '%Cerrad%' OR name LIKE '%Closed%' LIMIT 1");
if ($resSt && $r = $resSt->fetch_assoc()) {
    $status_closed = (int)$r['id'];
}

$mysqli->begin_transaction();

try {
    $has_closed_legacy_col = dbColumnExists('tickets', 'closed');
    $legacy_closed_set = $has_closed_legacy_col ? ', closed = NOW()' : '';

    $stmtUpd = $mysqli->prepare(
        'UPDATE tickets 
         SET status_id = ?, close_message = ?, client_signature = ?, closed_at = NOW()' . $legacy_closed_set . ', updated = NOW(), signature_requested = 0, signature_token = NULL
         WHERE id = ?'
    );
    $stmtUpd->bind_param('issi', $status_closed, $close_message, $signature_path, $ticket_id);
    $stmtUpd->execute();
    
    addLog('ticket_closed_client', 'Ticket cerrado con firma digital por el cliente remotamente', 'ticket', $ticket_id, 'user', $uid);
    
    $mysqli->commit();
} catch (Exception $e) {
    $mysqli->rollback();
    echo json_encode(['success' => false, 'error' => 'Error al cerrar el ticket: ' . $e->getMessage()]);
    exit;
}

// ==========================================
// 1. RESPUESTA INMEDIATA AL CLIENTE
// ==========================================
$response_payload = json_encode(['success' => true, 'message' => 'Ticket cerrado correctamente']);

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

$safe_ticket_no = preg_replace('~[^A-Za-z0-9_-]+~', '_', $ticket['ticket_number']);
$pdf_attachment = ($pdf_bytes !== null) ? [
    [
        'filename'    => 'Ticket_' . $safe_ticket_no . '.pdf',
        'contentType' => 'application/pdf',
        'content'     => $pdf_bytes,
    ]
] : [];

$client_name = trim((string)($ticket['user_first'] ?? '') . ' ' . (string)($ticket['user_last'] ?? ''));
if ($client_name === '') $client_name = 'Cliente';
$client_email = strtolower(trim((string)($ticket['user_email'] ?? '')));
$ticket_no = $ticket['ticket_number'];
$ticket_subject = $ticket['subject'];
$company_name = (string)(defined('APP_NAME') ? APP_NAME : 'Sistema de Tickets');

// Correo al cliente
if ($client_email !== '' && filter_var($client_email, FILTER_VALIDATE_EMAIL)) {
    $client_subject = '[Ticket cerrado] ' . $ticket_no . ' - ' . $ticket_subject;
    $client_body_html = '<div style="font-family:Segoe UI,Arial,sans-serif;max-width:680px;margin:0 auto;">'
        . '<h2 style="margin:0 0 10px;color:#1e3a5f;">Tu ticket fue cerrado</h2>'
        . '<p>Hola ' . htmlspecialchars($client_name) . ',</p>'
        . '<p>Te informamos que tu ticket <strong>' . htmlspecialchars($ticket_no) . '</strong> fue cerrado correctamente tras tu firma digital.</p>'
        . '<p><strong>Asunto:</strong> ' . htmlspecialchars($ticket_subject) . '</p>'
        . '<p style="margin-top:14px;color:#64748b;font-size:12px;">' . htmlspecialchars($company_name) . '</p>'
        . '</div>';
    $client_body_text = "Hola " . $client_name . ",\n\n"
        . "Tu ticket " . $ticket_no . " ha sido cerrado correctamente con tu firma digital.\n"
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

// Notificar a agentes seleccionados
$admin_recipients = [];
if (dbTableExists('notification_recipients')) {
    $staff_has_empresa = false;
    try { $staff_has_empresa = dbColumnExists('staff', 'empresa_id'); } catch (Throwable $e) {}

    $sql_admin = "SELECT s.email FROM notification_recipients nr "
        . "INNER JOIN staff s ON s.id = nr.staff_id "
        . "WHERE nr.empresa_id = ? AND s.is_active = 1";
    if ($staff_has_empresa) { $sql_admin .= ' AND s.empresa_id = ?'; }

    $stmt_admin = $mysqli->prepare($sql_admin);
    if ($stmt_admin) {
        if ($staff_has_empresa) { $stmt_admin->bind_param('ii', $eid, $eid); }
        else { $stmt_admin->bind_param('i', $eid); }
        
        if ($stmt_admin->execute()) {
            $rs_admin = $stmt_admin->get_result();
            while ($row_admin = $rs_admin->fetch_assoc()) {
                $em = strtolower(trim((string)($row_admin['email'] ?? '')));
                if ($em !== '' && filter_var($em, FILTER_VALIDATE_EMAIL)) {
                    $admin_recipients[$em] = true;
                }
            }
        }
    }
}

if (!empty($admin_recipients)) {
    $admin_subject = '[Firma Cliente] Ticket cerrado #' . $ticket_no;
    $admin_body_html = '<div style="font-family:Segoe UI,Arial,sans-serif;max-width:680px;margin:0 auto;">'
        . '<h2 style="margin:0 0 10px;color:#1e3a5f;">Ticket firmado por el cliente</h2>'
        . '<p>El cliente ha firmado y cerrado el ticket <strong>' . htmlspecialchars($ticket_no) . '</strong> remotamente.</p>'
        . '<p><strong>Asunto:</strong> ' . htmlspecialchars($ticket_subject) . '</p>'
        . '<p style="margin-top:14px;color:#64748b;font-size:12px;">' . htmlspecialchars($company_name) . '</p>'
        . '</div>';
    $admin_body_text = "Ticket firmado por el cliente\n\n"
        . "Numero: " . $ticket_no . "\n"
        . "Asunto: " . $ticket_subject . "\n"
        . "El cliente ha firmado y cerrado el ticket remotamente.\n\n"
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
