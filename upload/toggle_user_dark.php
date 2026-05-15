<?php
require_once '../config.php';
require_once '../includes/helpers.php';

requireLogin('cliente');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: tickets.php');
    exit;
}

if (validateCSRF()) {
    $newVal = (string)($_POST['dark_mode'] ?? '0');
    $isDark = ($newVal === '1') ? 1 : 0;
    $_SESSION['client_dark_mode'] = $isDark;

    if (isset($mysqli) && $mysqli && !empty($_SESSION['user_id'])) {
        $uid = (int)$_SESSION['user_id'];
        
        $hasCol = false;
        try {
            $colRes = $mysqli->query("SHOW COLUMNS FROM users LIKE 'dark_mode'");
            if ($colRes && $colRes->num_rows > 0) {
                $hasCol = true;
            } else {
                $mysqli->query("ALTER TABLE users ADD COLUMN dark_mode TINYINT(1) NOT NULL DEFAULT 0");
                $hasCol = true;
            }
        } catch (Throwable $e) {}

        if ($hasCol) {
            $stmt = $mysqli->prepare("UPDATE users SET dark_mode = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("ii", $isDark, $uid);
                $stmt->execute();
            }
        }
    }
}

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (!empty($_SERVER['HTTP_ACCEPT']) && stripos((string)$_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'dark_mode' => (int)($_SESSION['client_dark_mode'] ?? 0),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$return = (string)($_POST['return'] ?? 'tickets.php');
if ($return === '' || preg_match('~^(?:https?:)?//~i', $return)) {
    $return = 'tickets.php';
}
$return = ltrim($return, '/');

header('Location: ' . $return);
exit;
