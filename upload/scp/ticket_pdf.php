<?php
require_once '../../config.php';
require_once '../../includes/helpers.php';
require_once '../../includes/Auth.php';

$tid = isset($_GET['id']) && is_numeric($_GET['id']) ? (int) $_GET['id'] : 0;
$t   = $_GET['t'] ?? '';

$valid_token_bypass = false;
if ($tid > 0 && $t !== '') {
    $expected_token = hash_hmac('sha256', (string)$tid, defined('SECRET_KEY') ? SECRET_KEY : 'default-secret');
    if (hash_equals($expected_token, $t)) {
        $valid_token_bypass = true;
    }
}

if (!$valid_token_bypass && !isset($_SESSION['staff_id'])) {
    header('Location: login.php');
    exit;
}

if (!$valid_token_bypass) {
    requireLogin('agente');
}

if ($tid <= 0) {
    http_response_code(400);
    exit('Ticket inválido');
}

$projectRoot = realpath(dirname(__DIR__, 2));
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

if (!class_exists('Dompdf\Dompdf')) {
    http_response_code(500);
    exit('Dependencia faltante: Dompdf');
}

if (!class_exists('Dompdf\Options')) {
    http_response_code(500);
    exit('Dependencia faltante: Dompdf Options');
}

// Render HTML from the existing print view, without auto-print
define('TICKET_PDF_RENDER', true);
$_GET['id'] = $tid;

ob_start();
require __DIR__ . '/print_ticket.php';
$html = (string)ob_get_clean();

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
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdf));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

echo $pdf;
