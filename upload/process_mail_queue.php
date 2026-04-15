<?php
require_once '../config.php';
require_once '../includes/helpers.php';
require_once '../includes/Mailer.php';

ignore_user_abort(true);
@set_time_limit(0);

$isCli = (PHP_SAPI === 'cli');
$requestToken = trim((string)($_REQUEST['token'] ?? ''));
$requestEmpresaId = isset($_REQUEST['eid']) && is_numeric($_REQUEST['eid']) ? (int)$_REQUEST['eid'] : 0;

$readWorkerToken = function ($empresaId) use ($mysqli) {
    $empresaId = (int)$empresaId;
    if ($empresaId <= 0) return '';
    if (!isset($mysqli) || !$mysqli) return '';
    if (!ensureAppSettingsTable()) return '';
    $stmtT = $mysqli->prepare("SELECT `value` FROM app_settings WHERE empresa_id = ? AND `key` = 'mail.queue_worker_token' LIMIT 1");
    if (!$stmtT) return '';
    $stmtT->bind_param('i', $empresaId);
    if (!$stmtT->execute()) return '';
    $rowT = $stmtT->get_result()->fetch_assoc();
    return trim((string)($rowT['value'] ?? ''));
};

$workerToken = '';
if ($requestEmpresaId > 0) {
    $workerToken = $readWorkerToken($requestEmpresaId);
}
if ($workerToken === '') {
    $workerToken = trim((string)getAppSetting('mail.queue_worker_token', ''));
}
if ($workerToken === '' && $isCli) {
    $workerToken = bin2hex(random_bytes(24));
    setAppSetting('mail.queue_worker_token', $workerToken);
}

$authorized = $isCli || ($requestToken !== '' && hash_equals($workerToken, $requestToken));
if (!$authorized) {
    error_log('[mail_queue] forbidden token mismatch eid=' . (string)$requestEmpresaId . ' token_len=' . strlen($requestToken));
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'forbidden'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!ensureEmailQueueTable()) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'queue_table_unavailable'], JSON_UNESCAPED_UNICODE);
    exit;
}
ensureEmailLogsTable();

// Recuperar jobs que quedaron "processing" por corte de request/timeout.
$staleMinutes = 5;
$stmtStale = $mysqli->prepare(
    "UPDATE email_queue\n"
    . "SET status = 'retry',\n"
    . "    last_error = COALESCE(NULLIF(last_error, ''), 'Worker interrumpido; reintentando'),\n"
    . "    next_attempt_at = NOW(),\n"
    . "    updated_at = NOW()\n"
    . "WHERE status = 'processing' AND updated_at < DATE_SUB(NOW(), INTERVAL ? MINUTE)"
);
if ($stmtStale) {
    $stmtStale->bind_param('i', $staleMinutes);
    $stmtStale->execute();
}

$limit = isset($_REQUEST['limit']) && is_numeric($_REQUEST['limit']) ? (int)$_REQUEST['limit'] : 20;
if ($limit < 1) $limit = 1;
if ($limit > 100) $limit = 100;
error_log('[mail_queue] worker start limit=' . $limit . ' sapi=' . PHP_SAPI);

$stmt = $mysqli->prepare(
    "SELECT id, empresa_id, recipient_email, subject, body_html, body_text, attempts, max_attempts\n"
    . "FROM email_queue\n"
    . "WHERE status IN ('pending', 'retry') AND next_attempt_at <= NOW()\n"
    . "ORDER BY id ASC\n"
    . "LIMIT ?"
);
if (!$stmt) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'queue_select_failed'], JSON_UNESCAPED_UNICODE);
    exit;
}
$stmt->bind_param('i', $limit);
$stmt->execute();
$res = $stmt->get_result();

$jobs = [];
while ($res && ($row = $res->fetch_assoc())) {
    $jobs[] = $row;
}
error_log('[mail_queue] due jobs=' . count($jobs));

$processed = 0;
$sent = 0;
$failed = 0;
$retried = 0;

foreach ($jobs as $job) {
    $qid = (int)($job['id'] ?? 0);
    if ($qid <= 0) continue;

    $claim = $mysqli->prepare("UPDATE email_queue SET status = 'processing', updated_at = NOW() WHERE id = ? AND status IN ('pending', 'retry')");
    if (!$claim) continue;
    $claim->bind_param('i', $qid);
    $claim->execute();
    if ((int)$claim->affected_rows < 1) continue;

    $processed++;
    $to = trim((string)($job['recipient_email'] ?? ''));
    $subject = (string)($job['subject'] ?? '');
    $bodyHtml = (string)($job['body_html'] ?? '');
    $bodyText = (string)($job['body_text'] ?? '');
    $attempts = (int)($job['attempts'] ?? 0);
    $maxAttempts = (int)($job['max_attempts'] ?? 5);
    $empresaId = (int)($job['empresa_id'] ?? 1);
    if ($empresaId <= 0) $empresaId = 1;

    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $msg = 'Email inválido en cola';
        $updateInvalid = $mysqli->prepare("UPDATE email_queue SET status = 'failed', attempts = attempts + 1, last_error = ?, updated_at = NOW() WHERE id = ?");
        if ($updateInvalid) {
            $updateInvalid->bind_param('si', $msg, $qid);
            $updateInvalid->execute();
        }
        addEmailLog('failed', $msg, ['empresa_id' => $empresaId, 'queue_id' => $qid, 'recipient_email' => $to]);
        error_log('[mail_queue] invalid recipient queue_id=' . $qid . ' email=' . $to);
        $failed++;
        continue;
    }

    $ok = false;
    $lastError = '';
    try {
        $ok = Mailer::send($to, $subject, $bodyHtml, $bodyText);
        if (!$ok) $lastError = (string)(Mailer::$lastError ?? 'Error de envío');
    } catch (Throwable $e) {
        $ok = false;
        $lastError = $e->getMessage();
    }

    if ($ok) {
        $u = $mysqli->prepare("UPDATE email_queue SET status = 'sent', attempts = attempts + 1, sent_at = NOW(), last_error = NULL, updated_at = NOW() WHERE id = ?");
        if ($u) {
            $u->bind_param('i', $qid);
            $u->execute();
        }
        addEmailLog('sent', '', ['empresa_id' => $empresaId, 'queue_id' => $qid, 'recipient_email' => $to]);
        $sent++;
        continue;
    }

    $attemptsAfter = $attempts + 1;
    if ($attemptsAfter >= $maxAttempts) {
        $u = $mysqli->prepare("UPDATE email_queue SET status = 'failed', attempts = attempts + 1, last_error = ?, updated_at = NOW() WHERE id = ?");
        if ($u) {
            $u->bind_param('si', $lastError, $qid);
            $u->execute();
        }
        addEmailLog('failed', $lastError, ['empresa_id' => $empresaId, 'queue_id' => $qid, 'recipient_email' => $to]);
        error_log('[mail_queue] failed queue_id=' . $qid . ' email=' . $to . ' err=' . $lastError);
        $failed++;
    } else {
        $delayMinutes = min(30, max(1, (int)pow(2, $attemptsAfter - 1)));
        $nextAttempt = date('Y-m-d H:i:s', time() + ($delayMinutes * 60));
        $u = $mysqli->prepare("UPDATE email_queue SET status = 'retry', attempts = attempts + 1, last_error = ?, next_attempt_at = ?, updated_at = NOW() WHERE id = ?");
        if ($u) {
            $u->bind_param('ssi', $lastError, $nextAttempt, $qid);
            $u->execute();
        }
        addEmailLog('retry', $lastError, ['empresa_id' => $empresaId, 'queue_id' => $qid, 'recipient_email' => $to]);
        error_log('[mail_queue] retry queue_id=' . $qid . ' email=' . $to . ' err=' . $lastError);
        $retried++;
    }
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'ok' => true,
    'processed' => $processed,
    'sent' => $sent,
    'failed' => $failed,
    'retried' => $retried,
], JSON_UNESCAPED_UNICODE);
error_log('[mail_queue] worker end processed=' . $processed . ' sent=' . $sent . ' failed=' . $failed . ' retried=' . $retried);
