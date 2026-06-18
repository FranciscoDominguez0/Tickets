<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/Auth.php';

$tid = isset($_GET['id']) && is_numeric($_GET['id']) ? (int) $_GET['id'] : 0;
$t   = $_GET['t'] ?? '';

$valid_token_bypass = false;
if ($tid > 0 && $t !== '') {
    $expected_token = hash_hmac('sha256', (string)$tid, defined('SECRET_KEY') ? SECRET_KEY : 'default-secret');
    if (hash_equals($expected_token, $t)) {
        $valid_token_bypass = true;
    }
}

if (!$valid_token_bypass) {
    requireLogin('cliente');
}

if ($tid <= 0) {
    http_response_code(400);
    exit('Ticket inválido');
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if (!$valid_token_bypass && $userId <= 0) {
    http_response_code(403);
    exit('No autorizado');
}

$projectRoot = realpath(dirname(__DIR__));
if ($projectRoot === false) {
    http_response_code(500);
    exit('No se pudo resolver la ruta del proyecto');
}

$autoload = $projectRoot . '/vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(500);
    exit('Dependencia faltante: instala dompdf (composer require dompdf/dompdf)');
}

require_once $autoload;

// Pre-validación de propiedad si el cliente está logueado y no está usando el token de bypass
if (!$valid_token_bypass) {
    $eid = (int)($_SESSION['empresa_id'] ?? 1);
    if ($eid <= 0) $eid = 1;

    $stmt_check = $mysqli->prepare("SELECT user_id FROM tickets WHERE id = ? LIMIT 1");
    if ($stmt_check) {
        $stmt_check->bind_param('i', $tid);
        $stmt_check->execute();
        $ticketRow = $stmt_check->get_result()->fetch_assoc();
        
        if (!$ticketRow || !clientUserCanAccessTicket($mysqli, $userId, (int)$ticketRow['user_id'], $eid)) {
            http_response_code(403);
            exit('No autorizado para ver este ticket');
        }
    }
}

// Renderizar el HTML de scp/print_ticket.php
if (!defined('TICKET_PDF_RENDER')) {
    define('TICKET_PDF_RENDER', true);
}
$_GET['id'] = $tid;

ob_start();
require __DIR__ . '/scp/print_ticket.php';
$html = (string)ob_get_clean();

// Inyectar el script de impresión automática ya que TICKET_PDF_RENDER lo desactiva en la plantilla
$html = str_replace(
    '</body>',
    "<script>\nwindow.addEventListener('load', function () {\n    setTimeout(function () {\n        try { window.print(); } catch (e) {}\n    }, 300);\n});\n</script>\n</body>",
    $html
);

echo $html;
exit;
