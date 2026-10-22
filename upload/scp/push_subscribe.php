<?php
header('Content-Type: application/json; charset=utf-8');

require_once '../../config.php';
require_once '../../includes/helpers.php';
require_once '../../includes/Auth.php';

// Si no está logueado como staff, denegar
if (!isset($_SESSION['staff_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'No autorizado']);
    exit;
}

$staffId = (int) $_SESSION['staff_id'];
$empresaId = (int) empresaId();

// Asegurar que la tabla exista
if (function_exists('ensurePushSubscriptionsTable')) {
    ensurePushSubscriptionsTable();
}

// Manejar GET: Retorna la clave pública VAPID
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $publicKey = defined('VAPID_PUBLIC_KEY') ? VAPID_PUBLIC_KEY : '';
    echo json_encode([
        'ok' => true,
        'vapid_public_key' => $publicKey,
    ]);
    exit;
}

// Manejar POST: Guardar o eliminar suscripción push
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Datos JSON inválidos']);
        exit;
    }

    $action = $data['action'] ?? 'subscribe';
    $sub = $data['subscription'] ?? [];
    $endpoint = trim((string)($sub['endpoint'] ?? ''));

    if ($endpoint === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Endpoint requerido']);
        exit;
    }

    try {
        if ($action === 'unsubscribe') {
            $stmtDel = $mysqli->prepare("DELETE FROM push_subscriptions WHERE endpoint = ? AND user_type = 'staff' AND user_id = ?");
            if ($stmtDel) {
                $stmtDel->bind_param('si', $endpoint, $staffId);
                $stmtDel->execute();
            }
            echo json_encode(['ok' => true, 'unsubscribed' => true]);
            exit;
        }

        // Suscribir
        $keys = $sub['keys'] ?? [];
        $p256dh = trim((string)($keys['p256dh'] ?? ''));
        $auth = trim((string)($keys['auth'] ?? ''));
        $encoding = trim((string)($data['contentEncoding'] ?? 'aes128gcm'));
        $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250);

        if ($p256dh === '' || $auth === '') {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Claves p256dh y auth requeridas']);
            exit;
        }

        $stmtIns = $mysqli->prepare(
            "INSERT INTO push_subscriptions (empresa_id, user_type, user_id, endpoint, public_key, auth_token, content_encoding, user_agent, created_at, updated_at) " .
            "VALUES (?, 'staff', ?, ?, ?, ?, ?, ?, NOW(), NOW()) " .
            "ON DUPLICATE KEY UPDATE " .
            "empresa_id = VALUES(empresa_id), user_id = VALUES(user_id), public_key = VALUES(public_key), " .
            "auth_token = VALUES(auth_token), content_encoding = VALUES(content_encoding), user_agent = VALUES(user_agent), updated_at = NOW()"
        );

        if (!$stmtIns) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'Error de base de datos']);
            exit;
        }

        $stmtIns->bind_param('iisssss', $empresaId, $staffId, $endpoint, $p256dh, $auth, $encoding, $ua);
        $ok = $stmtIns->execute();

        echo json_encode(['ok' => (bool)$ok]);
        exit;
    } catch (\Throwable $e) {
        if (class_exists('\\Logger') && method_exists('\\Logger', 'error')) {
            \Logger::error('Error en push_subscribe: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Error al guardar suscripción: ' . $e->getMessage()]);
        exit;
    }
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
