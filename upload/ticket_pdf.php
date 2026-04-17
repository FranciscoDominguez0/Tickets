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
    $stmt_check = $mysqli->prepare("SELECT 1 FROM tickets WHERE id = ? AND user_id = ? LIMIT 1");
    if ($stmt_check) {
        $stmt_check->bind_param('ii', $tid, $userId);
        $stmt_check->execute();
        if (!$stmt_check->get_result()->fetch_assoc()) {
            http_response_code(403);
            exit('No autorizado para ver este ticket');
        }
    }
}

// Renderizar el HTML de scp/print_ticket.php para que el PDF sea exacto a la vista de impresión
if (!defined('TICKET_PDF_RENDER')) {
    define('TICKET_PDF_RENDER', true);
}
$_GET['id'] = $tid;

ob_start();
require __DIR__ . '/scp/print_ticket.php';
$html = (string)ob_get_clean();

if (!class_exists('Dompdf\Dompdf') || !class_exists('Dompdf\Options')) {
    http_response_code(500);
    exit('Dependencia faltante: Dompdf');
}

try {
    $options = new Dompdf\Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', false);
    $options->set('chroot', $projectRoot);

    $dompdf = new Dompdf\Dompdf($options);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $pdf = $dompdf->output();
} catch (Throwable $e) {
    http_response_code(500);
    exit('Error al generar PDF: ' . $e->getMessage());
}

$filename = 'ticket_' . $tid . '.pdf';
header('Content-Type: application/pdf; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

echo $pdf;
exit;
