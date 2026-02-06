<?php
// Módulo: Panel de control (dashboard)
// Gráfica de actividad de tickets y estadísticas por departamento

// Procesar formulario de período
$period = $_POST['period'] ?? 'today';
$startDateInput = $_POST['start'] ?? '';

// Calcular fechas según el período
$endDate = new DateTime('today');
$endDate->setTime(23, 59, 59);

if ($startDateInput) {
    try {
        $startDate = new DateTime($startDateInput);
    } catch (Exception $e) {
        $startDate = (clone $endDate)->modify('-29 days');
    }
} else {
    $startDate = (clone $endDate)->modify('-29 days'); // Último mes por defecto
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

// ============================================================================
// DATOS PARA LA GRÁFICA: Created, Closed, Deleted por día
// ============================================================================

// Tickets creados por día
$sqlCreated = "
    SELECT DATE(created) AS day, COUNT(*) AS total
    FROM tickets
    WHERE DATE(created) BETWEEN ? AND ?
    GROUP BY DATE(created)
    ORDER BY DATE(created)
";
$stmt = $mysqli->prepare($sqlCreated);
if (!$stmt) {
    error_log("Error preparing created query: " . $mysqli->error);
}
$stmt->bind_param('ss', $start, $end);
$stmt->execute();
$createdResult = $stmt->get_result();
$createdByDay = [];
while ($row = $createdResult->fetch_assoc()) {
    $createdByDay[$row['day']] = (int) $row['total'];
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
    WHERE DATE(closed) BETWEEN ? AND ?
    AND status_id = ?
    AND closed IS NOT NULL
    GROUP BY DATE(closed)
    ORDER BY DATE(closed)
";
$stmt = $mysqli->prepare($sqlClosed);
if (!$stmt) {
    error_log("Error preparing closed query: " . $mysqli->error);
} else {
    $stmt->bind_param('ssi', $start, $end, $statusCerradoId);
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
        WHERE DATE(closed) BETWEEN ? AND ?
        AND status_id = ?
        AND closed IS NOT NULL
        AND TIMESTAMPDIFF(MINUTE, closed, updated) BETWEEN 0 AND 60
        GROUP BY DATE(closed)
        ORDER BY DATE(closed)
    ";
    $stmt = $mysqli->prepare($sqlDeleted);
    if (!$stmt) {
        error_log("Error preparing deleted query: " . $mysqli->error);
        $deletedByDay = [];
    } else {
        $stmt->bind_param('ssi', $start, $end, $statusCerradoId);
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
    // Formato de fecha más simple: 12-27-2025
    $labels[] = $cursor->format('m-d-Y');
    $times[] = $cursor->getTimestamp(); // Timestamp Unix
    $createdData[] = isset($createdByDay[$dayKey]) ? (int)$createdByDay[$dayKey] : 0;
    $closedData[] = isset($closedByDay[$dayKey]) ? (int)$closedByDay[$dayKey] : 0;
    $deletedData[] = isset($deletedByDay[$dayKey]) ? (int)$deletedByDay[$dayKey] : 0;
    $cursor->modify('+1 day');
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
        AND t.created BETWEEN ? AND ?
    WHERE d.is_active = 1
    GROUP BY d.id, d.name
    HAVING total_tickets > 0
    ORDER BY d.name
";

$stmt = $mysqli->prepare($sqlStats);
$stmt->bind_param('ss', $start, $end);
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
    LEFT JOIN tickets t ON t.$topicsKeyColumn = ht.$topicsIdColumn AND t.created BETWEEN ? AND ?
    GROUP BY ht.$topicsIdColumn, ht.$topicsNameColumn
    HAVING total_tickets > 0
    ORDER BY ht.$topicsNameColumn";
    $stmt = $mysqli->prepare($sqlTopics);
    $stmt->bind_param('ss', $start, $end);
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
LEFT JOIN tickets t ON t.staff_id = s.id AND t.created BETWEEN ? AND ?
WHERE s.is_active = 1
GROUP BY s.id, s.firstname, s.lastname
HAVING total_tickets > 0
ORDER BY s.firstname, s.lastname";

$stmt = $mysqli->prepare($sqlAgents);
$stmt->bind_param('ss', $start, $end);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $agentStats[] = $row;
}
?>

<!-- Formulario de selección de período -->
<form method="post" action="dashboard.php" class="mb-4">
    <div class="d-flex align-items-center gap-3 flex-wrap" style="background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
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

<!-- Título de Actividad de Tickets -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">Actividad de Tickets 
        <i class="bi bi-question-circle" style="font-size: 0.8em; color: #666; cursor: help;" title="Gráfica de actividad de tickets"></i>
    </h2>
</div>

<!-- Gráfica de actividad de tickets -->
<div style="background:#ffffff;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,0.06);padding:20px;margin-bottom:24px;min-height:350px;">
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
<p class="text-muted">Las estadísticas de los Tickets se organizan por departamento, tema y agente.</p>
<p><strong>Rango: </strong><?php echo $startDate->format('F j, Y'); ?> - <?php echo $endDate->format('F j, Y'); ?> (America/Bogota)</p>

<!-- Tabs para diferentes vistas -->
<ul class="nav nav-tabs mb-3" id="statsTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="dept-tab" data-bs-toggle="tab" data-bs-target="#dept" type="button" role="tab">
            Departamento
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="topics-tab" data-bs-toggle="tab" data-bs-target="#topics" type="button" role="tab">
            Temas
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="agent-tab" data-bs-toggle="tab" data-bs-target="#agent" type="button" role="tab">
            Agente
        </button>
    </li>
</ul>

<!-- Contenido de los tabs -->
<div class="tab-content" id="statsTabContent">
    <!-- Tab Departamento -->
    <div class="tab-pane fade show active" id="dept" role="tabpanel">
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead class="table-light">
                    <tr>
                        <th width="30%" class="text-start">Departamento</th>
                        <th>Abierto <i class="bi bi-question-circle" style="font-size: 0.8em; cursor: help;" title="Tickets abiertos"></i></th>
                        <th>Asignado <i class="bi bi-question-circle" style="font-size: 0.8em; cursor: help;" title="Tickets asignados"></i></th>
                        <th>Atrasado <i class="bi bi-question-circle" style="font-size: 0.8em; cursor: help;" title="Tickets atrasados"></i></th>
                        <th>Cerrado <i class="bi bi-question-circle" style="font-size: 0.8em; cursor: help;" title="Tickets cerrados"></i></th>
                        <th>Reabierto <i class="bi bi-question-circle" style="font-size: 0.8em; cursor: help;" title="Tickets reabiertos"></i></th>
                        <th>Borrado <i class="bi bi-question-circle" style="font-size: 0.8em; cursor: help;" title="Tickets borrados"></i></th>
                        <th>Tiempo de Servicio <i class="bi bi-question-circle" style="font-size: 0.8em; cursor: help;" title="Tiempo promedio de servicio en horas"></i></th>
                        <th>Tiempo de Respuesta <i class="bi bi-question-circle" style="font-size: 0.8em; cursor: help;" title="Tiempo promedio de respuesta en horas"></i></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($deptStats)): ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted">No hay datos para el período seleccionado</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($deptStats as $stat): ?>
                            <tr>
                                <th class="text-start"><?php echo html($stat['departamento']); ?></th>
                                <td><?php echo (int)$stat['abierto']; ?></td>
                                <td><?php echo (int)$stat['asignado']; ?></td>
                                <td><?php echo (int)$stat['atrasado']; ?></td>
                                <td><?php echo (int)$stat['cerrado']; ?></td>
                                <td><?php echo (int)$stat['reabierto']; ?></td>
                                <td><?php echo (int)$stat['borrado']; ?></td>
                                <td><?php echo $stat['tiempo_servicio'] ? number_format($stat['tiempo_servicio'], 1) : '-'; ?></td>
                                <td><?php echo $stat['tiempo_respuesta'] ? number_format($stat['tiempo_respuesta'], 1) : '-'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="mt-3">
            <button type="button" class="btn btn-link p-0" data-action="dashboard-export" data-export-type="dept">
                <i class="bi bi-download"></i> Exportar
            </button>
        </div>
    </div>

    <!-- Tab Temas -->
    <div class="tab-pane fade" id="topics" role="tabpanel">
        <?php if (!$topicStatsAvailable): ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i> No se encontró una estructura de Temas en la base de datos (tabla/columna). Si deseas esta pestaña, hay que agregar una tabla de temas (ej. help_topics) y una columna en tickets (ej. topic_id).
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
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
                                <td colspan="9" class="text-center text-muted">No hay datos para el período seleccionado</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($topicStats as $stat): ?>
                                <tr>
                                    <th class="text-start"><?php echo html($stat['tema']); ?></th>
                                    <td><?php echo (int)$stat['abierto']; ?></td>
                                    <td><?php echo (int)$stat['asignado']; ?></td>
                                    <td><?php echo (int)$stat['atrasado']; ?></td>
                                    <td><?php echo (int)$stat['cerrado']; ?></td>
                                    <td><?php echo (int)$stat['reabierto']; ?></td>
                                    <td><?php echo (int)$stat['borrado']; ?></td>
                                    <td><?php echo $stat['tiempo_servicio'] ? number_format($stat['tiempo_servicio'], 1) : '-'; ?></td>
                                    <td><?php echo $stat['tiempo_respuesta'] ? number_format($stat['tiempo_respuesta'], 1) : '-'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-3">
                <button type="button" class="btn btn-link p-0" data-action="dashboard-export" data-export-type="topics">
                    <i class="bi bi-download"></i> Exportar
                </button>
            </div>
        <?php endif; ?>
    </div>

    <!-- Tab Agente -->
    <div class="tab-pane fade" id="agent" role="tabpanel">
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead class="table-light">
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
                            <td colspan="9" class="text-center text-muted">No hay datos para el período seleccionado</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($agentStats as $stat): ?>
                            <tr>
                                <th class="text-start"><?php echo html($stat['agente']); ?></th>
                                <td><?php echo (int)$stat['abierto']; ?></td>
                                <td><?php echo (int)$stat['asignado']; ?></td>
                                <td><?php echo (int)$stat['atrasado']; ?></td>
                                <td><?php echo (int)$stat['cerrado']; ?></td>
                                <td><?php echo (int)$stat['reabierto']; ?></td>
                                <td><?php echo (int)$stat['borrado']; ?></td>
                                <td><?php echo $stat['tiempo_servicio'] ? number_format($stat['tiempo_servicio'], 1) : '-'; ?></td>
                                <td><?php echo $stat['tiempo_respuesta'] ? number_format($stat['tiempo_respuesta'], 1) : '-'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="mt-3">
            <button type="button" class="btn btn-link p-0" data-action="dashboard-export" data-export-type="agent">
                <i class="bi bi-download"></i> Exportar
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
];
?>
<script id="dashboard-data" type="application/json"><?php echo json_encode($dashboardData, JSON_UNESCAPED_UNICODE); ?></script>
