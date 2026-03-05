<?php
require_once '../config.php';
require_once '../includes/helpers.php';
require_once '../includes/Mailer.php';

requireLogin('cliente');

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (!empty($_SERVER['HTTP_ACCEPT']) && stripos((string)$_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$isAjax) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'bad_request'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!validateCSRF()) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'csrf'], JSON_UNESCAPED_UNICODE);
    exit;
}

$queue = [];
if (isset($_SESSION['pending_mail_queue']) && is_array($_SESSION['pending_mail_queue'])) {
    $queue = $_SESSION['pending_mail_queue'];
}

$_SESSION['pending_mail_queue'] = [];
unset($_SESSION['pending_mail_queue_needs_process']);

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true, 'queued' => is_array($queue) ? count($queue) : 0], JSON_UNESCAPED_UNICODE);

if (function_exists('fastcgi_finish_request')) {
    @fastcgi_finish_request();
} else {
    if (function_exists('ob_get_level')) {
        while (ob_get_level() > 0) {
            @ob_end_flush();
        }
    }
    @flush();
}

if (!is_array($queue) || empty($queue)) {
    exit;
}

ignore_user_abort(true);
@set_time_limit(0);

foreach ($queue as $job) {
    try {
        $to = trim((string)($job['to'] ?? ''));
        $subj = (string)($job['subj'] ?? '');
        $html = (string)($job['html'] ?? '');
        $text = (string)($job['text'] ?? '');
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            continue;
        }
        $ok = Mailer::send($to, $subj, $html, $text);
        if (!$ok) {
            $err = (string)(Mailer::$lastError ?? 'Error desconocido');
            error_log('[mail_queue] send failed: ' . $to . ' | ' . $err);
        }
    } catch (Throwable $e) {
        error_log('[mail_queue] exception: ' . $e->getMessage());
    }
}
