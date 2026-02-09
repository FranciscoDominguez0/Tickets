<?php
require_once '../../config.php';
require_once '../../includes/helpers.php';
require_once '../../includes/Auth.php';

if (!isset($_SESSION['staff_id'])) {
    header('Location: login.php');
    exit;
}

requireLogin('agente');

$type = (string)($_GET['type'] ?? '');
$start = (string)($_GET['start'] ?? '');
$end = (string)($_GET['end'] ?? '');

if (!in_array($type, ['dept', 'topics', 'agent'], true)) {
    http_response_code(400);
    echo 'Tipo inválido';
    exit;
}

if (!preg_match('~^\d{4}-\d{2}-\d{2}$~', $start) || !preg_match('~^\d{4}-\d{2}-\d{2}$~', $end)) {
    http_response_code(400);
    echo 'Rango inválido';
    exit;
}

$startDt = DateTime::createFromFormat('Y-m-d', $start);
$endDt = DateTime::createFromFormat('Y-m-d', $end);
if (!$startDt || !$endDt) {
    http_response_code(400);
    echo 'Rango inválido';
    exit;
}

$startDt->setTime(0, 0, 0);
$endDt->setTime(23, 59, 59);
$startTs = $startDt->format('Y-m-d H:i:s');
$endTs = $endDt->format('Y-m-d H:i:s');

$filenameType = $type === 'dept' ? 'departamentos' : ($type === 'topics' ? 'temas' : 'agentes');
$filename = 'export_' . $filenameType . '_' . $start . '_a_' . $end . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');
if (!$out) {
    http_response_code(500);
    exit;
}

fwrite($out, "\xEF\xBB\xBF");

$writeCsv = function (array $headerRow, array $rows) use ($out) {
    fputcsv($out, $headerRow);
    foreach ($rows as $r) {
        fputcsv($out, $r);
    }
};

if ($type === 'dept') {
    $sql = "SELECT 
        d.name as departamento,
        SUM(CASE WHEN t.status_id = (SELECT id FROM ticket_status WHERE name = 'Abierto' LIMIT 1) THEN 1 ELSE 0 END) as abierto,
        SUM(CASE WHEN t.staff_id IS NOT NULL THEN 1 ELSE 0 END) as asignado,
        SUM(CASE WHEN t.status_id != (SELECT id FROM ticket_status WHERE name = 'Cerrado' LIMIT 1) AND t.closed IS NULL AND t.updated < DATE_ADD(NOW(), INTERVAL -1 DAY) THEN 1 ELSE 0 END) as atrasado,
        SUM(CASE WHEN t.status_id = (SELECT id FROM ticket_status WHERE name = 'Cerrado' LIMIT 1) THEN 1 ELSE 0 END) as cerrado,
        0 as reabierto,
        SUM(CASE WHEN t.status_id = (SELECT id FROM ticket_status WHERE name = 'Cerrado' LIMIT 1) AND TIMESTAMPDIFF(HOUR, t.closed, t.updated) <= 1 THEN 1 ELSE 0 END) as borrado,
        AVG(CASE WHEN t.closed IS NOT NULL THEN TIMESTAMPDIFF(HOUR, t.created, t.closed) ELSE NULL END) as tiempo_servicio,
        AVG(CASE WHEN t.staff_id IS NOT NULL THEN TIMESTAMPDIFF(HOUR, t.created, (SELECT MIN(created) FROM thread_entries WHERE thread_id = (SELECT id FROM threads WHERE ticket_id = t.id LIMIT 1) AND staff_id IS NOT NULL AND is_internal = 0 LIMIT 1)) ELSE NULL END) as tiempo_respuesta
    FROM departments d
    LEFT JOIN tickets t ON d.id = t.dept_id AND t.created BETWEEN ? AND ?
    WHERE d.is_active = 1
    GROUP BY d.id, d.name
    HAVING (abierto + asignado + atrasado + cerrado + borrado) > 0
    ORDER BY d.name";

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('ss', $startTs, $endTs);
    $stmt->execute();
    $res = $stmt->get_result();

    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = [
            (string)$row['departamento'],
            (string)(int)$row['abierto'],
            (string)(int)$row['asignado'],
            (string)(int)$row['atrasado'],
            (string)(int)$row['cerrado'],
            (string)(int)$row['reabierto'],
            (string)(int)$row['borrado'],
            $row['tiempo_servicio'] !== null ? (string)number_format((float)$row['tiempo_servicio'], 1, '.', '') : '',
            $row['tiempo_respuesta'] !== null ? (string)number_format((float)$row['tiempo_respuesta'], 1, '.', '') : '',
        ];
    }

    $writeCsv([
        'Departamento',
        'Abierto',
        'Asignado',
        'Atrasado',
        'Cerrado',
        'Reabierto',
        'Borrado',
        'Tiempo de Servicio (h)',
        'Tiempo de Respuesta (h)'
    ], $rows);

    fclose($out);
    exit;
}

if ($type === 'agent') {
    $sql = "SELECT
      CONCAT(TRIM(s.firstname), ' ', TRIM(s.lastname)) AS agente,
      SUM(CASE WHEN t.status_id = (SELECT id FROM ticket_status WHERE name = 'Abierto' LIMIT 1) THEN 1 ELSE 0 END) AS abierto,
      SUM(CASE WHEN t.staff_id IS NOT NULL THEN 1 ELSE 0 END) AS asignado,
      SUM(CASE WHEN t.status_id != (SELECT id FROM ticket_status WHERE name = 'Cerrado' LIMIT 1) AND t.closed IS NULL AND t.updated < DATE_ADD(NOW(), INTERVAL -1 DAY) THEN 1 ELSE 0 END) AS atrasado,
      SUM(CASE WHEN t.status_id = (SELECT id FROM ticket_status WHERE name = 'Cerrado' LIMIT 1) THEN 1 ELSE 0 END) AS cerrado,
      0 AS reabierto,
      SUM(CASE WHEN t.status_id = (SELECT id FROM ticket_status WHERE name = 'Cerrado' LIMIT 1) AND TIMESTAMPDIFF(HOUR, t.closed, t.updated) <= 1 THEN 1 ELSE 0 END) AS borrado,
      AVG(CASE WHEN t.closed IS NOT NULL THEN TIMESTAMPDIFF(HOUR, t.created, t.closed) ELSE NULL END) AS tiempo_servicio,
      AVG(CASE WHEN t.staff_id IS NOT NULL THEN TIMESTAMPDIFF(HOUR, t.created, (SELECT MIN(created) FROM thread_entries WHERE thread_id = (SELECT id FROM threads WHERE ticket_id = t.id LIMIT 1) AND staff_id IS NOT NULL AND is_internal = 0 LIMIT 1)) ELSE NULL END) AS tiempo_respuesta
    FROM staff s
    LEFT JOIN tickets t ON t.staff_id = s.id AND t.created BETWEEN ? AND ?
    WHERE s.is_active = 1
    GROUP BY s.id, s.firstname, s.lastname
    HAVING (abierto + asignado + atrasado + cerrado + borrado) > 0
    ORDER BY s.firstname, s.lastname";

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('ss', $startTs, $endTs);
    $stmt->execute();
    $res = $stmt->get_result();

    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = [
            (string)$row['agente'],
            (string)(int)$row['abierto'],
            (string)(int)$row['asignado'],
            (string)(int)$row['atrasado'],
            (string)(int)$row['cerrado'],
            (string)(int)$row['reabierto'],
            (string)(int)$row['borrado'],
            $row['tiempo_servicio'] !== null ? (string)number_format((float)$row['tiempo_servicio'], 1, '.', '') : '',
            $row['tiempo_respuesta'] !== null ? (string)number_format((float)$row['tiempo_respuesta'], 1, '.', '') : '',
        ];
    }

    $writeCsv([
        'Agente',
        'Abierto',
        'Asignado',
        'Atrasado',
        'Cerrado',
        'Reabierto',
        'Borrado',
        'Tiempo de Servicio (h)',
        'Tiempo de Respuesta (h)'
    ], $rows);

    fclose($out);
    exit;
}

$topicsTable = null;
$topicsNameColumn = null;
$topicsIdColumn = null;
$topicsKeyColumn = null;

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

if (!$topicsTable || !$topicsKeyColumn) {
    http_response_code(400);
    echo 'No hay soporte de Temas en la base de datos.';
    exit;
}

$sql = "SELECT 
  ht.$topicsNameColumn AS tema,
  SUM(CASE WHEN t.status_id = (SELECT id FROM ticket_status WHERE name = 'Abierto' LIMIT 1) THEN 1 ELSE 0 END) AS abierto,
  SUM(CASE WHEN t.staff_id IS NOT NULL THEN 1 ELSE 0 END) AS asignado,
  SUM(CASE WHEN t.status_id != (SELECT id FROM ticket_status WHERE name = 'Cerrado' LIMIT 1) AND t.closed IS NULL AND t.updated < DATE_ADD(NOW(), INTERVAL -1 DAY) THEN 1 ELSE 0 END) AS atrasado,
  SUM(CASE WHEN t.status_id = (SELECT id FROM ticket_status WHERE name = 'Cerrado' LIMIT 1) THEN 1 ELSE 0 END) AS cerrado,
  0 AS reabierto,
  SUM(CASE WHEN t.status_id = (SELECT id FROM ticket_status WHERE name = 'Cerrado' LIMIT 1) AND TIMESTAMPDIFF(HOUR, t.closed, t.updated) <= 1 THEN 1 ELSE 0 END) AS borrado,
  AVG(CASE WHEN t.closed IS NOT NULL THEN TIMESTAMPDIFF(HOUR, t.created, t.closed) ELSE NULL END) AS tiempo_servicio,
  AVG(CASE WHEN t.staff_id IS NOT NULL THEN TIMESTAMPDIFF(HOUR, t.created, (SELECT MIN(created) FROM thread_entries WHERE thread_id = (SELECT id FROM threads WHERE ticket_id = t.id LIMIT 1) AND staff_id IS NOT NULL AND is_internal = 0 LIMIT 1)) ELSE NULL END) AS tiempo_respuesta
FROM $topicsTable ht
LEFT JOIN tickets t ON t.$topicsKeyColumn = ht.$topicsIdColumn AND t.created BETWEEN ? AND ?
GROUP BY ht.$topicsIdColumn, ht.$topicsNameColumn
HAVING (abierto + asignado + atrasado + cerrado + borrado) > 0
ORDER BY ht.$topicsNameColumn";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param('ss', $startTs, $endTs);
$stmt->execute();
$res = $stmt->get_result();

$rows = [];
while ($row = $res->fetch_assoc()) {
    $rows[] = [
        (string)$row['tema'],
        (string)(int)$row['abierto'],
        (string)(int)$row['asignado'],
        (string)(int)$row['atrasado'],
        (string)(int)$row['cerrado'],
        (string)(int)$row['reabierto'],
        (string)(int)$row['borrado'],
        $row['tiempo_servicio'] !== null ? (string)number_format((float)$row['tiempo_servicio'], 1, '.', '') : '',
        $row['tiempo_respuesta'] !== null ? (string)number_format((float)$row['tiempo_respuesta'], 1, '.', '') : '',
    ];
}

$writeCsv([
    'Tema',
    'Abierto',
    'Asignado',
    'Atrasado',
    'Cerrado',
    'Reabierto',
    'Borrado',
    'Tiempo de Servicio (h)',
    'Tiempo de Respuesta (h)'
], $rows);

fclose($out);
exit;
