<?php
/**
 * API: Ubicaciones de agentes con tickets "En Camino"
 * GET: upload/scp/api_ubicaciones.php
 * Retorna JSON con agentes en campo
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

if (!isset($_SESSION['staff_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'No autenticado']);
    exit;
}

$eid = empresaId();

if (!dbTableExists('ubicaciones_agentes')) {
    echo json_encode(['ok' => false, 'error' => 'Tabla ubicaciones_agentes no existe']);
    exit;
}

$sql = "SELECT
            s.id AS staff_id,
            CONCAT(s.firstname, ' ', s.lastname) AS agente_nombre,
            t.id AS ticket_id,
            t.ticket_number,
            t.subject AS ticket_asunto,
            ts.name AS ticket_estado,
            u.latitud,
            u.longitud,
            u.fecha_actualizacion
        FROM tickets t
        INNER JOIN ticket_status ts ON ts.id = t.status_id
        INNER JOIN staff s ON s.id = t.staff_id AND s.empresa_id = t.empresa_id
        INNER JOIN ubicaciones_agentes u ON u.staff_id = s.id AND u.empresa_id = t.empresa_id
        WHERE ts.name = 'En Camino'
          AND t.empresa_id = ?
        ORDER BY u.fecha_actualizacion DESC";

$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    echo json_encode(['ok' => false, 'error' => $mysqli->error]);
    exit;
}

$stmt->bind_param('i', $eid);
$stmt->execute();
$result = $stmt->get_result();

$agentes = [];
$vistos = [];

while ($row = $result->fetch_assoc()) {
    $sid = (int)$row['staff_id'];
    if (isset($vistos[$sid])) continue;
    $vistos[$sid] = true;

    $agentes[] = [
        'staff_id'            => $sid,
        'agente_nombre'       => trim((string)$row['agente_nombre']) ?: 'Sin nombre',
        'ticket_id'           => (int)$row['ticket_id'],
        'numero_ticket'       => (string)$row['ticket_number'],
        'ticket_asunto'       => (string)$row['ticket_asunto'],
        'ticket_estado'       => (string)$row['ticket_estado'],
        'latitud'             => (float)$row['latitud'],
        'longitud'            => (float)$row['longitud'],
        'fecha_actualizacion' => (string)$row['fecha_actualizacion'],
    ];
}

$stmt->close();

echo json_encode([
    'ok'        => true,
    'agentes'   => $agentes,
    'total'     => count($agentes),
    'timestamp' => date('Y-m-d H:i:s'),
], JSON_UNESCAPED_UNICODE);
