<?php
/**
 * Server-Sent Events (SSE) para notificaciones en tiempo real del agente (SCP)
 * Reemplaza el polling periódico con una conexión unidireccional y eficiente.
 */
require_once '../../config.php';
require_once '../../includes/helpers.php';
require_once '../../includes/Auth.php';

// Validar sesión de agente
if (empty($_SESSION['staff_id'])) {
    http_response_code(401);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok' => false, 'error' => 'No session']);
    exit;
}

$staffId = (int)$_SESSION['staff_id'];
$eid     = empresaId();

// 1. CRÍTICO: Liberar el bloqueo de sesión en PHP de inmediato
// Si no se libera, ninguna otra petición del usuario podrá procesarse mientras SSE esté conectado.
session_write_close();

// 2. Establecer cabeceras SSE y desactivar buffers
@set_time_limit(360);
ignore_user_abort(false);

header('Content-Type: text/event-stream; charset=UTF-8');
header('Cache-Control: no-cache, no-transform');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

if (function_exists('apache_setenv')) {
    @apache_setenv('no-gzip', '1');
}
@ini_set('zlib.output_compression', '0');
@ini_set('implicit_flush', '1');
while (ob_get_level() > 0) {
    ob_end_flush();
}
ob_implicit_flush(1);

// 3. Determinar el último ID conocido
$lastId = 0;
if (!empty($_SERVER['HTTP_LAST_EVENT_ID']) && is_numeric($_SERVER['HTTP_LAST_EVENT_ID'])) {
    $lastId = (int)$_SERVER['HTTP_LAST_EVENT_ID'];
} elseif (isset($_GET['last_id']) && is_numeric($_GET['last_id'])) {
    $lastId = (int)$_GET['last_id'];
}

// Directiva al navegador: si se desconecta, reconectar en 2.5 segundos
echo "retry: 2500\n\n";
flush();

// 4. Conexión de larga duración estándar (5 minutos = 300 segundos)
$startTime   = time();
$maxDuration = 300;
$lastUnread  = null;
$lastPing    = time();

while ((time() - $startTime) < $maxDuration) {
    if (connection_aborted()) {
        break;
    }

    // A. Nuevas notificaciones
    $stmt = $mysqli->prepare("SELECT id, message, type, related_id, created_at 
                             FROM notifications 
                             WHERE staff_id = ? AND empresa_id = ? AND is_read = 0 AND id > ? 
                             ORDER BY id ASC LIMIT 5");
    if (!$stmt) {
        break;
    }
    $stmt->bind_param('iii', $staffId, $eid, $lastId);
    $stmt->execute();
    $res = $stmt->get_result();

    $newNotifications = [];
    while ($row = $res->fetch_assoc()) {
        $newNotifications[] = $row;
        if ((int)$row['id'] > $lastId) {
            $lastId = (int)$row['id'];
        }
    }
    $stmt->close();

    // B. Total de no leídas para la campana
    $stmtC = $mysqli->prepare("SELECT COUNT(*) as total FROM notifications WHERE staff_id = ? AND empresa_id = ? AND is_read = 0");
    if (!$stmtC) {
        break;
    }
    $stmtC->bind_param('ii', $staffId, $eid);
    $stmtC->execute();
    $resC = $stmtC->get_result()->fetch_assoc();
    $totalUnread = (int)($resC['total'] ?? 0);
    $stmtC->close();

    // C. Emitir evento si es la primera iteración, si hay nuevas o si el total cambió
    if ($lastUnread === null || !empty($newNotifications) || $totalUnread !== $lastUnread) {
        $lastUnread = $totalUnread;
        $lastPing   = time();

        $payload = [
            'ok'            => true,
            'notifications' => $newNotifications,
            'total_unread'  => $totalUnread,
            'last_id'       => $lastId
        ];

        echo "id: {$lastId}\n";
        echo "data: " . json_encode($payload) . "\n\n";
        flush();
    } elseif ((time() - $lastPing) >= 15) {
        // Heartbeat cada 15s para mantener el socket abierto sin consumir ancho de banda
        echo ": keepalive\n\n";
        flush();
        $lastPing = time();
    }

    // Pausa de 1.5s entre revisiones en BD (dormido, CPU 0%)
    usleep(1500000);
}

exit;
