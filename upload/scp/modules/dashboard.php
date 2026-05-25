<?php
// Módulo: Panel de control (dashboard)
// Gráfica de actividad de tickets y estadísticas por departamento

// Procesar formulario de período
$period = $_POST['period'] ?? 'today';
$startDateInput = $_POST['start'] ?? '';

// Export CSV (GET)
if (isset($_GET['action']) && (string)$_GET['action'] === 'export_csv') {
    $period = isset($_GET['period']) ? (string)$_GET['period'] : $period;
    $startDateInput = isset($_GET['start']) ? (string)$_GET['start'] : $startDateInput;
}

$eid = empresaId();
$canViewAll = roleHasPermission('ticket.view_all');
$currentStaffId = (int)($_SESSION['staff_id'] ?? 0);

// Calcular fechas según el período
$endDate = new DateTime('today');
$endDate->setTime(23, 59, 59);

if ($startDateInput) {
    try {
        $startDate = new DateTime($startDateInput);
    } catch (Exception $e) {
        $startDate = (clone $endDate)->modify('first day of this month');
    }
} else {
    $startDate = (clone $endDate)->modify('first day of this month');
}

// Ajustar fecha final según período
switch ($period) {
    case 'today':
        $endDate = new DateTime('today');
        $endDate->setTime(23, 59, 59);
        break;
    case 'yesterday':
        $endDate = new DateTime('yesterday');
        $endDate->setTime(23, 59, 59);
        break;
    case 'week':
        $startDate = (clone $endDate)->modify('-7 days');
        break;
    case 'month':
        $startDate = (clone $endDate)->modify('-30 days');
        break;
    case 'lastmonth':
        $startDate = (clone $endDate)->modify('first day of last month');
        $startDate->setTime(0, 0, 0);
        $endDate = (clone $startDate)->modify('last day of this month');
        $endDate->setTime(23, 59, 59);
        break;
}

$startDate->setTime(0, 0, 0);
$start = $startDate->format('Y-m-d');
$end = $endDate->format('Y-m-d');

if (isset($_GET['action']) && (string)$_GET['action'] === 'export_csv') {
    $type = isset($_GET['type']) ? strtolower(trim((string)$_GET['type'])) : '';
    $allowed = ['dept', 'topics', 'agent'];
    if (!in_array($type, $allowed, true)) {
        http_response_code(400);
        echo 'Tipo de exportación inválido.';
        exit;
    }

    $filename = 'dashboard_' . $type . '_' . $start . '_to_' . $end . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    if (!$out) {
        http_response_code(500);
        echo 'No se pudo generar el archivo.';
        exit;
    }
    // UTF-8 BOM para Excel
    fwrite($out, "\xEF\xBB\xBF");

    if ($type === 'dept') {
        fputcsv($out, ['Departamento', 'Abierto', 'Asignado', 'Atrasado', 'Cerrado', 'Reabierto', 'Borrado', 'Tiempo de Servicio (h)', 'Tiempo de Respuesta (h)'], ',', '"', '\\');
        $sql = "SELECT 
            d.name as departamento,
            COUNT(t.id) as total_tickets,
            SUM(CASE WHEN t.status_id = (SELECT id FROM ticket_status WHERE name = 'Abierto' LIMIT 1) THEN 1 ELSE 0 END) as abierto,
            SUM(CASE WHEN t.staff_id IS NOT NULL THEN 1 ELSE 0 END) as asignado,
            SUM(CASE WHEN t.status_id = (SELECT id FROM ticket_status WHERE name = 'Cerrado' LIMIT 1) 
                AND t.closed IS NOT NULL 
                AND t.closed < NOW() 
                AND t.updated < DATE_ADD(NOW(), INTERVAL -1 DAY) THEN 1 ELSE 0 END) as atrasado,
            SUM(CASE WHEN t.status_id = (SELECT id FROM ticket_status WHERE name = 'Cerrado' LIMIT 1) THEN 1 ELSE 0 END) as cerrado,
            0 as reabierto,
            SUM(CASE WHEN t.status_id = (SELECT id FROM ticket_status WHERE name = 'Cerrado' LIMIT 1) 
                AND TIMESTAMPDIFF(HOUR, t.closed, t.updated) <= 1 THEN 1 ELSE 0 END) as borrado,
            AVG(CASE WHEN t.closed IS NOT NULL 
                THEN TIMESTAMPDIFF(HOUR, t.created, t.closed) 
                ELSE NULL END) as tiempo_servicio,
            AVG(CASE WHEN t.staff_id IS NOT NULL 
                THEN TIMESTAMPDIFF(HOUR, t.created, 
                    (SELECT MIN(created) FROM thread_entries WHERE thread_id = 
                        (SELECT id FROM threads WHERE ticket_id = t.id LIMIT 1) 
                        AND staff_id IS NOT NULL 
                        AND is_internal = 0 
                        LIMIT 1))
                ELSE NULL END) as tiempo_respuesta
        FROM departments d
        LEFT JOIN tickets t ON d.id = t.dept_id 
            AND t.empresa_id = ?
            AND t.created BETWEEN ? AND ?
        WHERE d.is_active = 1
        GROUP BY d.id, d.name
        HAVING total_tickets > 0
        ORDER BY d.name";
        $stmt = $mysqli->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('iss', $eid, $start, $end);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                fputcsv($out, [
                    (string)$row['departamento'],
                    (int)$row['abierto'],
                    (int)$row['asignado'],
                    (int)$row['atrasado'],
                    (int)$row['cerrado'],
                    (int)$row['reabierto'],
                    (int)$row['borrado'],
                    ($row['tiempo_servicio'] !== null ? number_format((float)$row['tiempo_servicio'], 1, '.', '') : ''),
                    ($row['tiempo_respuesta'] !== null ? number_format((float)$row['tiempo_respuesta'], 1, '.', '') : ''),
                ], ',', '"', '\\');
            }
        }
    }

    if ($type === 'topics') {
        $topicsTable = null;
        $topicsKeyColumn = null;
        $topicsNameColumn = null;
        $topicsIdColumn = null;
        $t = $mysqli->query("SHOW TABLES LIKE 'help_topics'");
        if ($t && $t->num_rows > 0) {
            $topicsTable = 'help_topics';
            $topicsIdColumn = 'id';
            $topicsNameColumn = 'name';
        }
        if (!$topicsTable) {
            $t = $mysqli->query("SHOW TABLES LIKE 'helptopics'");
            if ($t && $t->num_rows > 0) {
                $topicsTable = 'helptopics';
                $topicsIdColumn = 'id';
                $topicsNameColumn = 'name';
            }
        }
        if ($topicsTable) {
            $c = $mysqli->query("SHOW COLUMNS FROM tickets LIKE 'topic_id'");
            if ($c && $c->num_rows > 0) {
                $topicsKeyColumn = 'topic_id';
            }
            if (!$topicsKeyColumn) {
                $c = $mysqli->query("SHOW COLUMNS FROM tickets LIKE 'help_topic_id'");
                if ($c && $c->num_rows > 0) {
                    $topicsKeyColumn = 'help_topic_id';
                }
            }
            if (!$topicsKeyColumn) {
                $c = $mysqli->query("SHOW COLUMNS FROM tickets LIKE 'helptopic_id'");
                if ($c && $c->num_rows > 0) {
                    $topicsKeyColumn = 'helptopic_id';
                }
            }
        }

        fputcsv($out, ['Tema', 'Abierto', 'Asignado', 'Atrasado', 'Cerrado', 'Reabierto', 'Borrado', 'Tiempo de Servicio (h)', 'Tiempo de Respuesta (h)'], ',', '"', '\\');
        if ($topicsTable && $topicsKeyColumn) {
            $sql = "SELECT 
                ht.$topicsNameColumn AS tema,
                COUNT(t.id) AS total_tickets,
                SUM(CASE WHEN t.status_id = (SELECT id FROM ticket_status WHERE name = 'Abierto' LIMIT 1) THEN 1 ELSE 0 END) AS abierto,
                SUM(CASE WHEN t.staff_id IS NOT NULL THEN 1 ELSE 0 END) AS asignado,
                SUM(CASE WHEN t.status_id != (SELECT id FROM ticket_status WHERE name = 'Cerrado' LIMIT 1) AND t.closed IS NULL AND t.updated < DATE_ADD(NOW(), INTERVAL -1 DAY) THEN 1 ELSE 0 END) AS atrasado,
                SUM(CASE WHEN t.status_id = (SELECT id FROM ticket_status WHERE name = 'Cerrado' LIMIT 1) THEN 1 ELSE 0 END) AS cerrado,
                0 AS reabierto,
                SUM(CASE WHEN t.status_id = (SELECT id FROM ticket_status WHERE name = 'Cerrado' LIMIT 1) AND TIMESTAMPDIFF(HOUR, t.closed, t.updated) <= 1 THEN 1 ELSE 0 END) AS borrado,
                AVG(CASE WHEN t.closed IS NOT NULL THEN TIMESTAMPDIFF(HOUR, t.created, t.closed) ELSE NULL END) AS tiempo_servicio,
                AVG(CASE WHEN t.staff_id IS NOT NULL THEN TIMESTAMPDIFF(HOUR, t.created, (SELECT MIN(created) FROM thread_entries WHERE thread_id = (SELECT id FROM threads WHERE ticket_id = t.id LIMIT 1) AND staff_id IS NOT NULL AND is_internal = 0 LIMIT 1)) ELSE NULL END) AS tiempo_respuesta
            FROM $topicsTable ht
            LEFT JOIN tickets t ON t.$topicsKeyColumn = ht.$topicsIdColumn AND t.empresa_id = ? AND t.created BETWEEN ? AND ?
            GROUP BY ht.$topicsIdColumn, ht.$topicsNameColumn
            HAVING total_tickets > 0
            ORDER BY ht.$topicsNameColumn";
            $stmt = $mysqli->prepare($sql);
            if ($stmt) {
                $stmt->bind_param('iss', $eid, $start, $end);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    fputcsv($out, [
                        (string)$row['tema'],
                        (int)$row['abierto'],
                        (int)$row['asignado'],
                        (int)$row['atrasado'],
                        (int)$row['cerrado'],
                        (int)$row['reabierto'],
                        (int)$row['borrado'],
                        ($row['tiempo_servicio'] !== null ? number_format((float)$row['tiempo_servicio'], 1, '.', '') : ''),
                        ($row['tiempo_respuesta'] !== null ? number_format((float)$row['tiempo_respuesta'], 1, '.', '') : ''),
                    ], ',', '"', '\\');
                }
            }
        }
    }

    if ($type === 'agent') {
        fputcsv($out, ['Agente', 'Abierto', 'Asignado', 'Atrasado', 'Cerrado', 'Reabierto', 'Borrado', 'Tiempo de Servicio (h)', 'Tiempo de Respuesta (h)'], ',', '"', '\\');
        $sql = "SELECT
            CONCAT(TRIM(s.firstname), ' ', TRIM(s.lastname)) AS agente,
            COUNT(t.id) AS total_tickets,
            SUM(CASE WHEN t.status_id = (SELECT id FROM ticket_status WHERE name = 'Abierto' LIMIT 1) THEN 1 ELSE 0 END) AS abierto,
            SUM(CASE WHEN t.staff_id IS NOT NULL THEN 1 ELSE 0 END) AS asignado,
            SUM(CASE WHEN t.status_id != (SELECT id FROM ticket_status WHERE name = 'Cerrado' LIMIT 1) AND t.closed IS NULL AND t.updated < DATE_ADD(NOW(), INTERVAL -1 DAY) THEN 1 ELSE 0 END) AS atrasado,
            SUM(CASE WHEN t.status_id = (SELECT id FROM ticket_status WHERE name = 'Cerrado' LIMIT 1) THEN 1 ELSE 0 END) AS cerrado,
            0 AS reabierto,
            SUM(CASE WHEN t.status_id = (SELECT id FROM ticket_status WHERE name = 'Cerrado' LIMIT 1) AND TIMESTAMPDIFF(HOUR, t.closed, t.updated) <= 1 THEN 1 ELSE 0 END) AS borrado,
            AVG(CASE WHEN t.closed IS NOT NULL THEN TIMESTAMPDIFF(HOUR, t.created, t.closed) ELSE NULL END) AS tiempo_servicio,
            AVG(CASE WHEN t.staff_id IS NOT NULL THEN TIMESTAMPDIFF(HOUR, t.created, (SELECT MIN(created) FROM thread_entries WHERE thread_id = (SELECT id FROM threads WHERE ticket_id = t.id LIMIT 1) AND staff_id IS NOT NULL AND is_internal = 0 LIMIT 1)) ELSE NULL END) AS tiempo_respuesta
        FROM staff s
        LEFT JOIN tickets t ON t.staff_id = s.id AND t.empresa_id = ? AND t.created BETWEEN ? AND ?
        WHERE s.is_active = 1 AND s.empresa_id = ?
        GROUP BY s.id, s.firstname, s.lastname
        HAVING total_tickets > 0
        ORDER BY s.firstname, s.lastname";
        $stmt = $mysqli->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('issi', $eid, $start, $end, $eid);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                fputcsv($out, [
                    (string)$row['agente'],
                    (int)$row['abierto'],
                    (int)$row['asignado'],
                    (int)$row['atrasado'],
                    (int)$row['cerrado'],
                    (int)$row['reabierto'],
                    (int)$row['borrado'],
                    ($row['tiempo_servicio'] !== null ? number_format((float)$row['tiempo_servicio'], 1, '.', '') : ''),
                    ($row['tiempo_respuesta'] !== null ? number_format((float)$row['tiempo_respuesta'], 1, '.', '') : ''),
                ], ',', '"', '\\');
            }
        }
    }

    fclose($out);
    exit;
}

// ============================================================================
// DATOS PARA LA GRÁFICA: Created, Closed, Deleted por día
// ============================================================================

// Tickets creados por día
$sqlCreated = "
    SELECT DATE(created) AS day, COUNT(*) AS total
    FROM tickets
    WHERE empresa_id = ? AND DATE(created) BETWEEN ? AND ?
";
if (!$canViewAll) {
    $sqlCreated .= " AND staff_id = ?";
}
$sqlCreated .= "
    GROUP BY DATE(created)
    ORDER BY DATE(created)
";
$stmt = $mysqli->prepare($sqlCreated);
if (!$stmt) {
    error_log("Error preparing created query: " . $mysqli->error);
} else {
    if (!$canViewAll) {
        $stmt->bind_param('issi', $eid, $start, $end, $currentStaffId);
    } else {
        $stmt->bind_param('iss', $eid, $start, $end);
    }
    $stmt->execute();
    $createdResult = $stmt->get_result();
    $createdByDay = [];
    while ($row = $createdResult->fetch_assoc()) {
        $createdByDay[$row['day']] = (int) $row['total'];
    }
}
// Debug: verificar datos obtenidos
error_log("Created tickets by day: " . print_r($createdByDay, true));

// Tickets cerrados por día
$statusCerradoId = null;
$stmt = $mysqli->prepare("SELECT id FROM ticket_status WHERE name = 'Cerrado' LIMIT 1");
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $statusCerradoId = $row['id'];
}

$sqlClosed = "
    SELECT DATE(closed) AS day, COUNT(*) AS total
    FROM tickets
    WHERE empresa_id = ? AND DATE(closed) BETWEEN ? AND ?
    AND status_id = ?
    AND closed IS NOT NULL
";
if (!$canViewAll) {
    $sqlClosed .= " AND staff_id = ?";
}
$sqlClosed .= "
    GROUP BY DATE(closed)
    ORDER BY DATE(closed)
";
$stmt = $mysqli->prepare($sqlClosed);
if (!$stmt) {
    error_log("Error preparing closed query: " . $mysqli->error);
} else {
    if (!$canViewAll) {
        $stmt->bind_param('issii', $eid, $start, $end, $statusCerradoId, $currentStaffId);
    } else {
        $stmt->bind_param('issi', $eid, $start, $end, $statusCerradoId);
    }
    $stmt->execute();
    $closedResult = $stmt->get_result();
    $closedByDay = [];
    while ($row = $closedResult->fetch_assoc()) {
        $closedByDay[$row['day']] = (int) $row['total'];
    }
    // Debug: verificar datos obtenidos
    error_log("Closed tickets by day: " . print_r($closedByDay, true));
}

// Tickets "deleted" - Simulamos con tickets que fueron cerrados y luego "eliminados"
// En un sistema real, esto vendría de una tabla de logs o campo deleted_at
// Para esta simulación, usamos tickets cerrados donde updated está muy cerca de closed (simulando eliminación)
if ($statusCerradoId) {
    $sqlDeleted = "
        SELECT DATE(closed) AS day, COUNT(*) AS total
        FROM tickets
        WHERE empresa_id = ? AND DATE(closed) BETWEEN ? AND ?
        AND status_id = ?
        AND closed IS NOT NULL
        AND TIMESTAMPDIFF(MINUTE, closed, updated) BETWEEN 0 AND 60
    ";
    if (!$canViewAll) {
        $sqlDeleted .= " AND staff_id = ?";
    }
    $sqlDeleted .= "
        GROUP BY DATE(closed)
        ORDER BY DATE(closed)
    ";
    $stmt = $mysqli->prepare($sqlDeleted);
    if (!$stmt) {
        error_log("Error preparing deleted query: " . $mysqli->error);
        $deletedByDay = [];
    } else {
        if (!$canViewAll) {
            $stmt->bind_param('issii', $eid, $start, $end, $statusCerradoId, $currentStaffId);
        } else {
            $stmt->bind_param('issi', $eid, $start, $end, $statusCerradoId);
        }
        $stmt->execute();
        $deletedResult = $stmt->get_result();
        $deletedByDay = [];
        while ($row = $deletedResult->fetch_assoc()) {
            $deletedByDay[$row['day']] = (int) $row['total'];
        }
        // Debug: verificar datos obtenidos
        error_log("Deleted tickets by day: " . print_r($deletedByDay, true));
    }
} else {
    $deletedByDay = [];
}

// Inicializar arrays con todos los días del rango
$labels = [];
$createdData = [];
$closedData = [];
$deletedData = [];
$times = []; // Timestamps Unix para compatibilidad con osTicket
$cursor = clone $startDate;

while ($cursor <= $endDate) {
    $dayKey = $cursor->format('Y-m-d');
    $labels[] = $cursor->format('d-m-Y');
    $times[] = $cursor->getTimestamp(); // Timestamp Unix
    $createdData[] = isset($createdByDay[$dayKey]) ? (int)$createdByDay[$dayKey] : 0;
    $closedData[] = isset($closedByDay[$dayKey]) ? (int)$closedByDay[$dayKey] : 0;
    $deletedData[] = isset($deletedByDay[$dayKey]) ? (int)$deletedByDay[$dayKey] : 0;
    $cursor->modify('+1 day');
}

// Recortar días iniciales sin actividad para que la gráfica no se vea acumulada al final
$firstDataIndex = -1;
$dataCount = count($createdData);
for ($i = 0; $i < $dataCount; $i++) {
    if ($createdData[$i] > 0 || $closedData[$i] > 0 || $deletedData[$i] > 0) {
        $firstDataIndex = $i;
        break;
    }
}

// Para que tenga "forma de montaña", necesitamos que la gráfica empiece en 0 antes de subir.
// Dejamos 2 días de padding (ceros) antes del primer pico.
if ($firstDataIndex > 0) {
    $firstDataIndex = max(0, $firstDataIndex - 2);
    
    if ($firstDataIndex > 0) {
        $labels = array_slice($labels, $firstDataIndex);
        $times = array_slice($times, $firstDataIndex);
        $createdData = array_slice($createdData, $firstDataIndex);
        $closedData = array_slice($closedData, $firstDataIndex);
        $deletedData = array_slice($deletedData, $firstDataIndex);
    }
}

// Formato similar a osTicket: plots como objeto asociativo
$plots = [
    'created' => $createdData,
    'closed' => $closedData,
    'deleted' => $deletedData
];
$events = ['created', 'closed', 'deleted'];

// ============================================================================
// ESTADÍSTICAS POR DEPARTAMENTO
// ============================================================================

$sqlStats = "
    SELECT 
        d.id,
        d.name as departamento,
        COUNT(t.id) as total_tickets,
        SUM(CASE WHEN t.status_id = (SELECT id FROM ticket_status WHERE name = 'Abierto' LIMIT 1) THEN 1 ELSE 0 END) as abierto,
        SUM(CASE WHEN t.staff_id IS NOT NULL THEN 1 ELSE 0 END) as asignado,
        SUM(CASE WHEN t.status_id = (SELECT id FROM ticket_status WHERE name = 'Cerrado' LIMIT 1) 
            AND t.closed IS NOT NULL 
            AND t.closed < NOW() 
            AND t.updated < DATE_ADD(NOW(), INTERVAL -1 DAY) THEN 1 ELSE 0 END) as atrasado,
        SUM(CASE WHEN t.status_id = (SELECT id FROM ticket_status WHERE name = 'Cerrado' LIMIT 1) THEN 1 ELSE 0 END) as cerrado,
        0 as reabierto,
        SUM(CASE WHEN t.status_id = (SELECT id FROM ticket_status WHERE name = 'Cerrado' LIMIT 1) 
            AND TIMESTAMPDIFF(HOUR, t.closed, t.updated) <= 1 THEN 1 ELSE 0 END) as borrado,
        AVG(CASE WHEN t.closed IS NOT NULL 
            THEN TIMESTAMPDIFF(HOUR, t.created, t.closed) 
            ELSE NULL END) as tiempo_servicio,
        AVG(CASE WHEN t.staff_id IS NOT NULL 
            THEN TIMESTAMPDIFF(HOUR, t.created, 
                (SELECT MIN(created) FROM thread_entries WHERE thread_id = 
                    (SELECT id FROM threads WHERE ticket_id = t.id LIMIT 1) 
                    AND staff_id IS NOT NULL 
                    AND is_internal = 0 
                    LIMIT 1))
            ELSE NULL END) as tiempo_respuesta
    FROM departments d
    LEFT JOIN tickets t ON d.id = t.dept_id 
        AND t.empresa_id = ?
        AND t.created BETWEEN ? AND ?
";
if (!$canViewAll) {
    $sqlStats .= " AND t.staff_id = ?";
}
$sqlStats .= "
    WHERE d.is_active = 1
    GROUP BY d.id, d.name
    HAVING total_tickets > 0
    ORDER BY d.name
";

$stmt = $mysqli->prepare($sqlStats);
if (!$canViewAll) {
    $stmt->bind_param('issi', $eid, $start, $end, $currentStaffId);
} else {
    $stmt->bind_param('iss', $eid, $start, $end);
}
$stmt->execute();
$statsResult = $stmt->get_result();
$deptStats = [];
while ($row = $statsResult->fetch_assoc()) {
    $deptStats[] = $row;
}

$topicStats = [];
$topicStatsAvailable = false;
$agentStats = [];

$topicsTable = null;
$topicsKeyColumn = null;
$topicsNameColumn = null;
$topicsIdColumn = null;

$t = $mysqli->query("SHOW TABLES LIKE 'help_topics'");
if ($t && $t->num_rows > 0) {
    $topicsTable = 'help_topics';
    $topicsIdColumn = 'id';
    $topicsNameColumn = 'name';
}
if (!$topicsTable) {
    $t = $mysqli->query("SHOW TABLES LIKE 'helptopics'");
    if ($t && $t->num_rows > 0) {
        $topicsTable = 'helptopics';
        $topicsIdColumn = 'id';
        $topicsNameColumn = 'name';
    }
}
if ($topicsTable) {
    $c = $mysqli->query("SHOW COLUMNS FROM tickets LIKE 'topic_id'");
    if ($c && $c->num_rows > 0) {
        $topicsKeyColumn = 'topic_id';
    }
    if (!$topicsKeyColumn) {
        $c = $mysqli->query("SHOW COLUMNS FROM tickets LIKE 'help_topic_id'");
        if ($c && $c->num_rows > 0) {
            $topicsKeyColumn = 'help_topic_id';
        }
    }
    if (!$topicsKeyColumn) {
        $c = $mysqli->query("SHOW COLUMNS FROM tickets LIKE 'helptopic_id'");
        if ($c && $c->num_rows > 0) {
            $topicsKeyColumn = 'helptopic_id';
        }
    }
}

if ($topicsTable && $topicsKeyColumn) {
    $topicStatsAvailable = true;
    $sqlTopics = "SELECT 
      ht.$topicsNameColumn AS tema,
      COUNT(t.id) AS total_tickets,
      SUM(CASE WHEN t.status_id = (SELECT id FROM ticket_status WHERE name = 'Abierto' LIMIT 1) THEN 1 ELSE 0 END) AS abierto,
      SUM(CASE WHEN t.staff_id IS NOT NULL THEN 1 ELSE 0 END) AS asignado,
      SUM(CASE WHEN t.status_id != (SELECT id FROM ticket_status WHERE name = 'Cerrado' LIMIT 1) AND t.closed IS NULL AND t.updated < DATE_ADD(NOW(), INTERVAL -1 DAY) THEN 1 ELSE 0 END) AS atrasado,
      SUM(CASE WHEN t.status_id = (SELECT id FROM ticket_status WHERE name = 'Cerrado' LIMIT 1) THEN 1 ELSE 0 END) AS cerrado,
      0 AS reabierto,
      SUM(CASE WHEN t.status_id = (SELECT id FROM ticket_status WHERE name = 'Cerrado' LIMIT 1) AND TIMESTAMPDIFF(HOUR, t.closed, t.updated) <= 1 THEN 1 ELSE 0 END) AS borrado,
      AVG(CASE WHEN t.closed IS NOT NULL THEN TIMESTAMPDIFF(HOUR, t.created, t.closed) ELSE NULL END) AS tiempo_servicio,
      AVG(CASE WHEN t.staff_id IS NOT NULL THEN TIMESTAMPDIFF(HOUR, t.created, (SELECT MIN(created) FROM thread_entries WHERE thread_id = (SELECT id FROM threads WHERE ticket_id = t.id LIMIT 1) AND staff_id IS NOT NULL AND is_internal = 0 LIMIT 1)) ELSE NULL END) AS tiempo_respuesta
    FROM $topicsTable ht
    LEFT JOIN tickets t ON t.$topicsKeyColumn = ht.$topicsIdColumn AND t.empresa_id = ? AND t.created BETWEEN ? AND ?
";
    if (!$canViewAll) {
        $sqlTopics .= " AND t.staff_id = ?";
    }
    $sqlTopics .= "
    GROUP BY ht.$topicsIdColumn, ht.$topicsNameColumn
    HAVING total_tickets > 0
    ORDER BY ht.$topicsNameColumn";
    $stmt = $mysqli->prepare($sqlTopics);
    if (!$canViewAll) {
        $stmt->bind_param('issi', $eid, $start, $end, $currentStaffId);
    } else {
        $stmt->bind_param('iss', $eid, $start, $end);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $topicStats[] = $row;
    }
}

$sqlAgents = "SELECT
  s.id,
  CONCAT(TRIM(s.firstname), ' ', TRIM(s.lastname)) AS agente,
  COUNT(t.id) AS total_tickets,
  SUM(CASE WHEN t.status_id = (SELECT id FROM ticket_status WHERE name = 'Abierto' LIMIT 1) THEN 1 ELSE 0 END) AS abierto,
  SUM(CASE WHEN t.staff_id IS NOT NULL THEN 1 ELSE 0 END) AS asignado,
  SUM(CASE WHEN t.status_id != (SELECT id FROM ticket_status WHERE name = 'Cerrado' LIMIT 1) AND t.closed IS NULL AND t.updated < DATE_ADD(NOW(), INTERVAL -1 DAY) THEN 1 ELSE 0 END) AS atrasado,
  SUM(CASE WHEN t.status_id = (SELECT id FROM ticket_status WHERE name = 'Cerrado' LIMIT 1) THEN 1 ELSE 0 END) AS cerrado,
  0 AS reabierto,
  SUM(CASE WHEN t.status_id = (SELECT id FROM ticket_status WHERE name = 'Cerrado' LIMIT 1) AND TIMESTAMPDIFF(HOUR, t.closed, t.updated) <= 1 THEN 1 ELSE 0 END) AS borrado,
  AVG(CASE WHEN t.closed IS NOT NULL THEN TIMESTAMPDIFF(HOUR, t.created, t.closed) ELSE NULL END) AS tiempo_servicio,
  AVG(CASE WHEN t.staff_id IS NOT NULL THEN TIMESTAMPDIFF(HOUR, t.created, (SELECT MIN(created) FROM thread_entries WHERE thread_id = (SELECT id FROM threads WHERE ticket_id = t.id LIMIT 1) AND staff_id IS NOT NULL AND is_internal = 0 LIMIT 1)) ELSE NULL END) AS tiempo_respuesta
FROM staff s
LEFT JOIN tickets t ON t.staff_id = s.id AND t.empresa_id = ? AND t.created BETWEEN ? AND ?
WHERE s.is_active = 1 AND s.empresa_id = ?
";
if (!$canViewAll) {
    $sqlAgents .= " AND s.id = ?";
}
$sqlAgents .= "
GROUP BY s.id, s.firstname, s.lastname
HAVING total_tickets > 0
ORDER BY s.firstname, s.lastname";

$stmt = $mysqli->prepare($sqlAgents);
if (!$canViewAll) {
    $stmt->bind_param('issii', $eid, $start, $end, $eid, $currentStaffId);
} else {
    $stmt->bind_param('issi', $eid, $start, $end, $eid);
}
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $agentStats[] = $row;
}
?>

<style>
    @media (max-width: 576px) {
        .dashboard-period-form {
            padding: 10px !important;
            gap: 10px !important;
        }
        .dashboard-period-form label {
            font-size: 0.78rem;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 0 !important;
        }
        .dashboard-period-form input, 
        .dashboard-period-form select {
            width: 140px !important;
            font-size: 0.85rem !important;
            padding: 4px 8px !important;
        }
        .dashboard-period-form button {
            width: 100%;
            padding: 6px !important;
            font-size: 0.85rem !important;
            margin-top: 5px;
        }
        .dashboard-period-form strong {
            font-weight: 600;
        }
    }
        
    .mobile-filter-btn {
        border-radius: 10px;
        font-weight: 600;
        background-color: #fff;
        color: #1e293b;
        border: 1px solid #e2e8f0;
        transition: all 0.2s ease;
    }
    .mobile-filter-btn:hover, .mobile-filter-btn:focus, .mobile-filter-btn[aria-expanded="true"] {
        background-color: #f1f5f9;
        color: #0f172a;
        border-color: #cbd5e1;
    }
    
    body.dark-mode .mobile-filter-btn {
        background-color: #000;
        color: #e2e8f0;
        border-color: #333;
    }
    body.dark-mode .mobile-filter-btn:hover, body.dark-mode .mobile-filter-btn:focus, body.dark-mode .mobile-filter-btn[aria-expanded="true"] {
        background-color: #1a1a1a;
        color: #fff;
        border-color: #444;
    }

    @media (max-width: 767px) {
        .mobile-filter-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            z-index: 1050;
            width: 100%;
            max-width: 320px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            border-radius: 12px;
        }
        body.dark-mode .mobile-filter-dropdown {
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }
    }

    /* Premium Dashboard Hero Header styles */
    .stats-hero {
        background: radial-gradient(circle at 0% 0%, #ef4444 0%, #1a0000 35%, #000000 100%);
        border: 1px solid rgba(239, 68, 68, 0.2);
        border-radius: 14px;
        padding: 1.5rem 2rem;
        color: #fff;
        box-shadow: 0 14px 32px rgba(239, 68, 68, 0.28);
        margin-bottom: 20px;
    }
    .stats-hero-title {
        font-size: 1.45rem;
        font-weight: 700;
        margin: 0;
        color: #fff;
    }
    .stats-hero-sub {
        margin: .2rem 0 0;
        color: rgba(255, 255, 255, .9);
        font-size: .95rem;
        font-weight: 600;
    }
    .stats-hero-icon {
        width: 52px;
        height: 52px;
        background: rgba(255, 255, 255, .18);
        color: #fff;
        border-radius: 14px;
        flex-shrink: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.45rem;
        box-shadow: 0 4px 14px rgba(2, 6, 23, .2);
        border: 1px solid rgba(255, 255, 255, .22);
    }
    .stats-range {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 12px;
        border-radius: 999px;
        background: rgba(255, 255, 255, .15);
        border: 1px solid rgba(255, 255, 255, .28);
        font-weight: 800;
        color: #fff;
        font-size: 12px;
    }

    /* Modern Custom Interactive Legend Chips */
    .chart-legend-chip {
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        background: #f8fafc;
        padding: 6px 14px;
        border-radius: 20px;
        border: 1px solid #e2e8f0;
        transition: all 0.2s ease;
        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
    }
    .chart-legend-chip:hover {
        background: #f1f5f9;
        border-color: #cbd5e1;
        transform: translateY(-1px);
    }
    body.dark-mode .chart-legend-chip {
        background: #111;
        border-color: #222;
    }
    body.dark-mode .chart-legend-chip:hover {
        background: #1c1c1c;
        border-color: #333;
    }
    .chart-legend-dot {
        display: inline-block;
        width: 10px;
        height: 10px;
        margin-right: 8px;
        border-radius: 50%;
    }
    .chart-legend-label {
        font-size: 12px;
        font-weight: 700;
        color: #475569;
    }
    body.dark-mode .chart-legend-label {
        color: #cbd5e1;
    }
</style>

<!-- Encabezado Premium con Rango de Fechas -->
<div class="stats-hero d-flex align-items-center justify-content-between gap-3 flex-wrap mb-3">
    <div class="d-flex align-items-center gap-3">
        <span class="stats-hero-icon"><i class="bi bi-speedometer2"></i></span>
        <div>
            <h3 class="stats-hero-title">Panel de Control</h3>
            <div class="stats-hero-sub">Resumen de actividad y métricas de rendimiento.</div>
        </div>
    </div>
    
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <!-- Rango en badge de calendario premium -->
        <span class="stats-range"><i class="bi bi-calendar3"></i> <?php
            $meses = ['January' => 'Enero', 'February' => 'Febrero', 'March' => 'Marzo', 'April' => 'Abril', 'May' => 'Mayo', 'June' => 'Junio', 'July' => 'Julio', 'August' => 'Agosto', 'September' => 'Septiembre', 'October' => 'Octubre', 'November' => 'Noviembre', 'December' => 'Diciembre'];
            echo strtr($startDate->format('j \d\e F, Y'), $meses); ?> - <?php echo strtr($endDate->format('j \d\e F, Y'), $meses); ?></span>
    </div>
</div>

<!-- Formulario de selección de período -->
<div class="position-relative">
    <div class="d-md-none mb-3 d-flex justify-content-end">
        <button class="btn mobile-filter-btn" type="button" data-bs-toggle="collapse" data-bs-target="#mobileFiltersCollapseDashboard" aria-expanded="false" aria-controls="mobileFiltersCollapseDashboard">
            <i class="bi bi-sliders"></i> Filtros
        </button>
    </div>
    <div class="collapse d-md-block mobile-filter-dropdown" id="mobileFiltersCollapseDashboard">
        <form method="post" action="dashboard.php" class="mb-4 m-md-0">
            <div class="d-flex align-items-center gap-3 flex-wrap dashboard-period-form dashboard-card-bg">
                <label class="mb-0">
                    <strong>Reporte del Período:</strong>
                    <input type="date" 
                           name="start" 
                           class="form-control form-control-sm d-inline-block" 
                           style="width: auto; display: inline-block; margin-left: 5px;"
                           value="<?php echo $startDate->format('Y-m-d'); ?>"
                           placeholder="Último mes">
                </label>
                <label class="mb-0">
                    <strong>Período:</strong>
                    <select name="period" class="form-select form-select-sm d-inline-block" style="width: auto; display: inline-block; margin-left: 5px;">
                        <option value="today" <?php echo $period === 'today' ? 'selected' : ''; ?>>Hasta hoy</option>
                        <option value="yesterday" <?php echo $period === 'yesterday' ? 'selected' : ''; ?>>Ayer</option>
                        <option value="week" <?php echo $period === 'week' ? 'selected' : ''; ?>>Última semana</option>
                        <option value="month" <?php echo $period === 'month' ? 'selected' : ''; ?>>Último mes</option>
                        <option value="lastmonth" <?php echo $period === 'lastmonth' ? 'selected' : ''; ?>>Mes pasado</option>
                    </select>
                </label>
                <button type="submit" class="btn btn-primary btn-sm">Actualizar</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.addEventListener('click', function(event) {
        if (window.innerWidth >= 768) return; 
        
        var collapseEl = document.getElementById('mobileFiltersCollapseDashboard');
        var btnEl = document.querySelector('[data-bs-target="#mobileFiltersCollapseDashboard"]');
        
        if (collapseEl && collapseEl.classList.contains('show')) {
            if (!collapseEl.contains(event.target) && btnEl && !btnEl.contains(event.target)) {
                if (typeof bootstrap !== 'undefined') {
                    var bsCollapse = bootstrap.Collapse.getInstance(collapseEl);
                    if (bsCollapse) bsCollapse.hide();
                } else {
                    collapseEl.classList.remove('show');
                }
            }
        }
    });
});
</script>

<!-- Título de Actividad de Tickets -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">Actividad de Tickets 
        <i class="bi bi-question-circle" style="font-size: 0.8em; color: #666; cursor: help;" title="Gráfica de actividad de tickets"></i>
    </h2>
</div>

<!-- Gráfica de actividad de tickets -->
<div class="dashboard-card-bg chart-container-box">
    <div id="chart-container" style="position:relative;width:100%;height:300px;">
        <canvas id="ticketsActivityChart"></canvas>
    </div>
    <div id="line-chart-legend" style="margin-top:15px;text-align:center;"></div>
    <div id="chart-error" style="display:none;color:red;padding:20px;text-align:center;"></div>
</div>

<hr/>

<!-- Título de Estadísticas -->
<h2 class="mb-3">Estadísticas 
    <i class="bi bi-question-circle" style="font-size: 0.8em; color: #666; cursor: help;" title="Estadísticas de tickets"></i>
</h2>
<p class="text-muted mb-4">Las estadísticas de los Tickets se organizan por departamento, tema y agente.</p>

<!-- Tabs para diferentes vistas -->
<ul class="nav nav-tabs mb-3" id="statsTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="dept-tab" data-bs-toggle="tab" data-bs-target="#dept" type="button" role="tab">
            <i class="bi bi-building me-1"></i> Departamento
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="topics-tab" data-bs-toggle="tab" data-bs-target="#topics" type="button" role="tab">
            <i class="bi bi-tags me-1"></i> Temas
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="agent-tab" data-bs-toggle="tab" data-bs-target="#agent" type="button" role="tab">
            <i class="bi bi-people me-1"></i> Agente
        </button>
    </li>
</ul>

<!-- Contenido de los tabs -->
<div class="tab-content" id="statsTabContent">
    <!-- Tab Departamento -->
    <div class="tab-pane fade show active" id="dept" role="tabpanel">
        <div class="table-responsive">
            <table class="table table-hover align-middle stats-premium-table">
                <thead>
                    <tr>
                        <th width="30%" class="text-start">Departamento</th>
                        <th>Abierto <i class="bi bi-question-circle text-muted" style="font-size: 0.8em; cursor: help;" title="Tickets abiertos"></i></th>
                        <th>Asignado <i class="bi bi-question-circle text-muted" style="font-size: 0.8em; cursor: help;" title="Tickets asignados"></i></th>
                        <th>Atrasado <i class="bi bi-question-circle text-muted" style="font-size: 0.8em; cursor: help;" title="Tickets atrasados"></i></th>
                        <th>Cerrado <i class="bi bi-question-circle text-muted" style="font-size: 0.8em; cursor: help;" title="Tickets cerrados"></i></th>
                        <th>Reabierto <i class="bi bi-question-circle text-muted" style="font-size: 0.8em; cursor: help;" title="Tickets reabiertos"></i></th>
                        <th>Borrado <i class="bi bi-question-circle text-muted" style="font-size: 0.8em; cursor: help;" title="Tickets borrados"></i></th>
                        <th>Tiempo de Servicio <i class="bi bi-question-circle text-muted" style="font-size: 0.8em; cursor: help;" title="Tiempo promedio de servicio en horas"></i></th>
                        <th>Tiempo de Respuesta <i class="bi bi-question-circle text-muted" style="font-size: 0.8em; cursor: help;" title="Tiempo promedio de respuesta en horas"></i></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($deptStats)): ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">No hay datos para el período seleccionado</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($deptStats as $stat): ?>
                            <tr>
                                <th class="text-start">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="dept-dot"></span>
                                        <span><?php echo html($stat['departamento']); ?></span>
                                    </div>
                                </th>
                                <td><span class="badge-stat stat-open"><?php echo (int)$stat['abierto']; ?></span></td>
                                <td><span class="badge-stat stat-assigned"><?php echo (int)$stat['asignado']; ?></span></td>
                                <td><span class="badge-stat stat-overdue"><?php echo (int)$stat['atrasado']; ?></span></td>
                                <td><span class="badge-stat stat-closed"><?php echo (int)$stat['cerrado']; ?></span></td>
                                <td><span class="badge-stat stat-reopened"><?php echo (int)$stat['reabierto']; ?></span></td>
                                <td><span class="badge-stat stat-deleted"><?php echo (int)$stat['borrado']; ?></span></td>
                                <td><span class="badge-stat stat-service"><?php echo $stat['tiempo_servicio'] ? number_format($stat['tiempo_servicio'], 1) : '-'; ?></span></td>
                                <td><span class="badge-stat stat-response"><?php echo $stat['tiempo_respuesta'] ? number_format($stat['tiempo_respuesta'], 1) : '-'; ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="mt-3 text-end">
            <button type="button" class="btn btn-outline-danger btn-sm px-3 rounded-pill" data-action="dashboard-export" data-export-type="dept" style="font-weight:700;">
                <i class="bi bi-download me-1"></i> Exportar CSV
            </button>
        </div>
    </div>

    <!-- Tab Temas -->
    <div class="tab-pane fade" id="topics" role="tabpanel">
        <?php if (!$topicStatsAvailable): ?>
            <div class="alert alert-warning" style="border-radius: 12px;">
                <i class="bi bi-exclamation-triangle-fill me-2 text-warning"></i> No se encontró una estructura de Temas en la base de datos (tabla/columna). Si deseas esta pestaña, hay que agregar una tabla de temas (ej. help_topics) y una columna en tickets (ej. topic_id).
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle stats-premium-table">
                    <thead>
                        <tr>
                            <th width="30%" class="text-start">Tema</th>
                            <th>Abierto</th>
                            <th>Asignado</th>
                            <th>Atrasado</th>
                            <th>Cerrado</th>
                            <th>Reabierto</th>
                            <th>Borrado</th>
                            <th>Tiempo de Servicio</th>
                            <th>Tiempo de Respuesta</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($topicStats)): ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">No hay datos para el período seleccionado</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($topicStats as $stat): ?>
                                <tr>
                                    <th class="text-start">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="topic-dot"></span>
                                            <span><?php echo html($stat['tema']); ?></span>
                                        </div>
                                    </th>
                                    <td><span class="badge-stat stat-open"><?php echo (int)$stat['abierto']; ?></span></td>
                                    <td><span class="badge-stat stat-assigned"><?php echo (int)$stat['asignado']; ?></span></td>
                                    <td><span class="badge-stat stat-overdue"><?php echo (int)$stat['atrasado']; ?></span></td>
                                    <td><span class="badge-stat stat-closed"><?php echo (int)$stat['cerrado']; ?></span></td>
                                    <td><span class="badge-stat stat-reopened"><?php echo (int)$stat['reabierto']; ?></span></td>
                                    <td><span class="badge-stat stat-deleted"><?php echo (int)$stat['borrado']; ?></span></td>
                                    <td><span class="badge-stat stat-service"><?php echo $stat['tiempo_servicio'] ? number_format($stat['tiempo_servicio'], 1) : '-'; ?></span></td>
                                    <td><span class="badge-stat stat-response"><?php echo $stat['tiempo_respuesta'] ? number_format($stat['tiempo_respuesta'], 1) : '-'; ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-3 text-end">
                <button type="button" class="btn btn-outline-danger btn-sm px-3 rounded-pill" data-action="dashboard-export" data-export-type="topics" style="font-weight:700;">
                    <i class="bi bi-download me-1"></i> Exportar CSV
                </button>
            </div>
        <?php endif; ?>
    </div>

    <!-- Tab Agente -->
    <div class="tab-pane fade" id="agent" role="tabpanel">
        <div class="table-responsive">
            <table class="table table-hover align-middle stats-premium-table">
                <thead>
                    <tr>
                        <th width="30%" class="text-start">Agente</th>
                        <th>Abierto</th>
                        <th>Asignado</th>
                        <th>Atrasado</th>
                        <th>Cerrado</th>
                        <th>Reabierto</th>
                        <th>Borrado</th>
                        <th>Tiempo de Servicio</th>
                        <th>Tiempo de Respuesta</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($agentStats)): ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">No hay datos para el período seleccionado</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($agentStats as $stat): ?>
                            <tr>
                                <th class="text-start">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="agent-mini-avatar" aria-hidden="true"><?php echo strtoupper(substr($stat['agente'], 0, 1)); ?></div>
                                        <span><?php echo html($stat['agente']); ?></span>
                                    </div>
                                </th>
                                <td><span class="badge-stat stat-open"><?php echo (int)$stat['abierto']; ?></span></td>
                                <td><span class="badge-stat stat-assigned"><?php echo (int)$stat['asignado']; ?></span></td>
                                <td><span class="badge-stat stat-overdue"><?php echo (int)$stat['atrasado']; ?></span></td>
                                <td><span class="badge-stat stat-closed"><?php echo (int)$stat['cerrado']; ?></span></td>
                                <td><span class="badge-stat stat-reopened"><?php echo (int)$stat['reabierto']; ?></span></td>
                                <td><span class="badge-stat stat-deleted"><?php echo (int)$stat['borrado']; ?></span></td>
                                <td><span class="badge-stat stat-service"><?php echo $stat['tiempo_servicio'] ? number_format($stat['tiempo_servicio'], 1) : '-'; ?></span></td>
                                <td><span class="badge-stat stat-response"><?php echo $stat['tiempo_respuesta'] ? number_format($stat['tiempo_respuesta'], 1) : '-'; ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="mt-3 text-end">
            <button type="button" class="btn btn-outline-danger btn-sm px-3 rounded-pill" data-action="dashboard-export" data-export-type="agent" style="font-weight:700;">
                <i class="bi bi-download me-1"></i> Exportar CSV
            </button>
        </div>
    </div>
</div>

<?php
$dashboardData = [
    'labels' => $labels,
    'plots' => [
        'created' => $createdData,
        'closed' => $closedData,
        'deleted' => $deletedData,
    ],
    'range' => [
        'start_label' => $startDate->format('d-m-Y'),
        'end_label' => $endDate->format('d-m-Y'),
    ],
];
?>
<script id="dashboard-data" type="application/json"><?php echo json_encode($dashboardData, JSON_UNESCAPED_UNICODE); ?></script>
