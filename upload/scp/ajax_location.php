<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/helpers.php';

header('Content-Type: application/json');

if (!isset($_SESSION['staff_id'])) {
    echo json_encode(['ok' => false, 'error' => 'No session']);
    exit;
}

// Validar CSRF en actualizaciones
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['csrf_token']) || !Auth::validateCSRF($_POST['csrf_token']))) {
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$eid = empresaId();
$sid = (int)$_SESSION['staff_id'];
$action = $_GET['action'] ?? '';

// Identificar ID del estado "En camino" (Prioridad ID 2 si existe)
$statusIdEnCamino = 2; 

// Asegurar tabla staff_locations con UNIQUE KEY
$mysqli->query("CREATE TABLE IF NOT EXISTS staff_locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    lat DECIMAL(10, 8) NOT NULL,
    lng DECIMAL(11, 8) NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_staff (staff_id),
    KEY idx_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if ($action === 'update') {
    $lat = (float)($_POST['lat'] ?? 0);
    $lng = (float)($_POST['lng'] ?? 0);

    if ($lat == 0 || $lng == 0) {
        echo json_encode(['ok' => false, 'error' => 'Invalid coords']);
        exit;
    }

    // Verificar si el agente tiene tickets "En camino"
    $stmtCheck = $mysqli->prepare("SELECT COUNT(*) as total FROM tickets WHERE staff_id = ? AND status_id = ? AND empresa_id = ?");
    $stmtCheck->bind_param('iii', $sid, $statusIdEnCamino, $eid);
    $stmtCheck->execute();
    $count = (int)($stmtCheck->get_result()->fetch_assoc()['total'] ?? 0);

    if ($count > 0) {
        $stmtIns = $mysqli->prepare("INSERT INTO staff_locations (staff_id, lat, lng, updated_at) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE lat = VALUES(lat), lng = VALUES(lng), updated_at = NOW()");
        $stmtIns->bind_param('idd', $sid, $lat, $lng);
        if ($stmtIns->execute()) {
            echo json_encode(['ok' => true]);
        } else {
            echo json_encode(['ok' => false, 'error' => $mysqli->error]);
        }
    } else {
        echo json_encode(['ok' => false, 'error' => 'No active "En camino" tickets']);
    }
    exit;
}

if ($action === 'get_locations') {
    // Solo agentes con tickets "En camino" en la última hora
    // Usamos una consulta compatible con only_full_group_by
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
            WHERE status_id = ? AND empresa_id = ? AND closed IS NULL
            GROUP BY staff_id
        ) t_active ON t_active.staff_id = s.id
        JOIN tickets t ON t.id = t_active.max_ticket_id
        JOIN ticket_status ts ON t.status_id = ts.id
        WHERE sl.updated_at > (NOW() - INTERVAL 1 HOUR)
    ";
    
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param('ii', $statusIdEnCamino, $eid);
    $stmt->execute();
    $res = $stmt->get_result();
    
    $locations = [];
    while ($row = $res->fetch_assoc()) {
        $locations[] = [
            'staff_id' => (int)$row['staff_id'],
            'name' => (string)$row['name'],
            'lat' => (float)$row['lat'],
            'lng' => (float)$row['lng'],
            'ticket_id' => (int)$row['ticket_id'],
            'ticket_number' => (string)$row['ticket_number'],
            'status' => (string)$row['status_name'],
            'updated' => $row['updated_at']
        ];
    }
    
    echo json_encode(['ok' => true, 'locations' => $locations]);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Invalid action']);
