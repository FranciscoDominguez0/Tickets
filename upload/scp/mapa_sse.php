<?php
/**
 * Server-Sent Events (SSE) para el Mapa de Agentes en Tiempo Real
 * Transmite la posición de los técnicos activos al mapa sin necesidad de polling cada 15 segundos.
 */
require_once '../../config.php';
require_once '../../includes/helpers.php';
require_once '../../includes/Auth.php';

// Validar autenticación y permisos para ver el mapa
if (empty($_SESSION['staff_id'])) {
    http_response_code(401);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok' => false, 'error' => 'No session']);
    exit;
}

if (!roleHasPermission('agent.map')) {
    http_response_code(403);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok' => false, 'error' => 'No permission']);
    exit;
}

$eid = empresaId();

// 1. CRÍTICO: Liberar sesión inmediatamente
session_write_close();

// 2. Cabeceras SSE y desactivar buffers
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

// Reconectar en 3 segundos si se corta
echo "retry: 3000\n\n";
flush();

$startTime       = time();
$maxDuration     = 300; // 5 minutos de conexión continua
$lastFingerprint = null;
$lastPing        = time();

while ((time() - $startTime) < $maxDuration) {
    if (connection_aborted()) {
        break;
    }

    // A. Verificación ultrarrápida para saber si hubo cambios en coordenadas o cantidad de técnicos
    $stmtCheck = $mysqli->prepare("
        SELECT 
            MAX(sl.updated_at) as max_up, 
            COUNT(sl.id) as cnt,
            SUM(sl.lat + sl.lng) as sum_coords
        FROM staff_locations sl
        JOIN staff s ON s.id = sl.staff_id
        WHERE s.empresa_id = ?
    ");
    if (!$stmtCheck) {
        break;
    }
    $stmtCheck->bind_param('i', $eid);
    $stmtCheck->execute();
    $rCheck = $stmtCheck->get_result()->fetch_assoc();
    $stmtCheck->close();

    $currentFingerprint = ($rCheck['max_up'] ?? '') . '_' . ($rCheck['cnt'] ?? 0) . '_' . ($rCheck['sum_coords'] ?? 0);

    // B. Si cambió algo o es la primera conexión, enviar datos actualizados
    if ($lastFingerprint === null || $currentFingerprint !== $lastFingerprint) {
        $lastFingerprint = $currentFingerprint;
        $lastPing        = time();

        $query = "
            SELECT 
                s.id as staff_id, 
                CONCAT(s.firstname, ' ', s.lastname) as name,
                sl.lat, 
                sl.lng, 
                sl.updated_at,
                t.id as ticket_id,
                t.ticket_number,
                ts.name as status_name
            FROM staff s
            JOIN staff_locations sl ON s.id = sl.staff_id
            JOIN (
                SELECT staff_id, MAX(id) as max_ticket_id
                FROM tickets
                WHERE status_id IN (2, 3) AND empresa_id = ? AND closed IS NULL
                GROUP BY staff_id
            ) t_active ON t_active.staff_id = s.id
            JOIN tickets t ON t.id = t_active.max_ticket_id
            JOIN ticket_status ts ON t.status_id = ts.id
        ";

        $stmtL = $mysqli->prepare($query);
        $locations = [];
        if ($stmtL) {
            $stmtL->bind_param('i', $eid);
            $stmtL->execute();
            $resL = $stmtL->get_result();
            while ($row = $resL->fetch_assoc()) {
                $locations[] = [
                    'staff_id'      => (int)$row['staff_id'],
                    'name'          => (string)$row['name'],
                    'lat'           => (float)$row['lat'],
                    'lng'           => (float)$row['lng'],
                    'ticket_id'     => (int)$row['ticket_id'],
                    'ticket_number' => (string)$row['ticket_number'],
                    'status'        => (string)$row['status_name'],
                    'updated'       => $row['updated_at']
                ];
            }
            $stmtL->close();
        }

        echo "data: " . json_encode(['ok' => true, 'locations' => $locations]) . "\n\n";
        flush();
    } elseif ((time() - $lastPing) >= 15) {
        // Heartbeat cada 15s para mantener abierto el enlace
        echo ": keepalive\n\n";
        flush();
        $lastPing = time();
    }

    // Pausa de 2.5s antes de volver a verificar cambios en BD
    usleep(2500000);
}

exit;
