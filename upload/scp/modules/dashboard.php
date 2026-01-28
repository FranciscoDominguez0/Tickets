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
            <button type="button" class="btn btn-link p-0" onclick="exportStats('dept')">
                <i class="bi bi-download"></i> Exportar
            </button>
        </div>
    </div>

    <!-- Tab Temas -->
    <div class="tab-pane fade" id="topics" role="tabpanel">
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i> La funcionalidad de Temas estará disponible próximamente.
        </div>
    </div>

    <!-- Tab Agente -->
    <div class="tab-pane fade" id="agent" role="tabpanel">
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i> La funcionalidad de Agente estará disponible próximamente.
        </div>
    </div>
</div>

<!-- Scripts de la gráfica -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    // Datos para la gráfica
    var dashboardLabels = <?php echo json_encode($labels, JSON_UNESCAPED_UNICODE); ?>;
    var dashboardCreated = <?php echo json_encode($createdData, JSON_UNESCAPED_UNICODE); ?>;
    var dashboardClosed = <?php echo json_encode($closedData, JSON_UNESCAPED_UNICODE); ?>;
    var dashboardDeleted = <?php echo json_encode($deletedData, JSON_UNESCAPED_UNICODE); ?>;
    
    console.log('=== DASHBOARD DEBUG ===');
    console.log('Labels:', dashboardLabels);
    console.log('Created:', dashboardCreated);
    console.log('Closed:', dashboardClosed);
    console.log('Deleted:', dashboardDeleted);
    console.log('Chart.js disponible:', typeof Chart !== 'undefined');
    
    // Función para crear la gráfica
    function crearGrafica() {
        var ctx = document.getElementById('ticketsActivityChart');
        var errorDiv = document.getElementById('chart-error');
        
        if (!ctx) {
            console.error('Canvas no encontrado');
            if (errorDiv) {
                errorDiv.style.display = 'block';
                errorDiv.innerHTML = 'Error: Canvas no encontrado';
            }
            return;
        }
        
        if (typeof Chart === 'undefined') {
            console.error('Chart.js no está cargado');
            if (errorDiv) {
                errorDiv.style.display = 'block';
                errorDiv.innerHTML = 'Error: Chart.js no está cargado. Recarga la página.';
            }
            return;
        }
        
        if (!dashboardLabels || dashboardLabels.length === 0) {
            console.warn('No hay datos para mostrar');
            if (errorDiv) {
                errorDiv.style.display = 'block';
                errorDiv.innerHTML = 'No hay datos disponibles para el período seleccionado.';
            }
            return;
        }
        
        console.log('Creando gráfica...');
        
        try {
            var chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: dashboardLabels,
                    datasets: [
                        {
                            label: 'created',
                            data: dashboardCreated,
                            borderColor: '#28a745',
                            backgroundColor: 'rgba(40, 167, 69, 0.15)',
                            fill: true,
                            tension: 0.4,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            pointBackgroundColor: '#28a745',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            borderWidth: 2
                        },
                        {
                            label: 'closed',
                            data: dashboardClosed,
                            borderColor: '#007bff',
                            backgroundColor: 'rgba(0, 123, 255, 0.15)',
                            fill: true,
                            tension: 0.4,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            pointBackgroundColor: '#007bff',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            borderWidth: 2
                        },
                        {
                            label: 'deleted',
                            data: dashboardDeleted,
                            borderColor: '#dc3545',
                            backgroundColor: 'rgba(220, 53, 69, 0.15)',
                            fill: true,
                            tension: 0.4,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            pointBackgroundColor: '#dc3545',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            borderWidth: 2
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            labels: {
                                usePointStyle: true,
                                padding: 15,
                                font: {
                                    size: 12,
                                    weight: 'bold'
                                }
                            }
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            padding: 12,
                            titleFont: {
                                size: 13,
                                weight: 'bold'
                            },
                            bodyFont: {
                                size: 12
                            },
                            borderColor: 'rgba(255, 255, 255, 0.1)',
                            borderWidth: 1,
                            callbacks: {
                                title: function(context) {
                                    return 'Fecha: ' + context[0].label;
                                },
                                label: function(context) {
                                    return context.dataset.label + ': ' + context.parsed.y + ' tickets';
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: true,
                                color: 'rgba(0, 0, 0, 0.05)'
                            },
                            ticks: {
                                autoSkip: true,
                                maxTicksLimit: 8, // Máximo 8 fechas en el eje X
                                maxRotation: 0,
                                minRotation: 0,
                                font: {
                                    size: 11
                                },
                                callback: function(value, index, ticks) {
                                    // Mostrar solo algunas fechas (cada 4-5 días aproximadamente)
                                    var skip = Math.ceil(ticks.length / 8);
                                    if (index % skip === 0 || index === ticks.length - 1) {
                                        return this.getLabelForValue(value);
                                    }
                                    return '';
                                }
                            }
                        },
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1,
                                font: {
                                    size: 11
                                }
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        }
                    }
                }
            });
            
            console.log('Gráfica creada exitosamente');
            
            // Crear leyenda
            var legendContainer = document.getElementById('line-chart-legend');
            if (legendContainer) {
                chart.data.datasets.forEach(function(dataset, index) {
                    var item = document.createElement('span');
                    item.style.cssText = 'margin: 0 15px; cursor: pointer;';
                    item.innerHTML = '<span style="display:inline-block;width:16px;height:16px;background:' + dataset.borderColor + ';margin-right:5px;border-radius:3px;"></span>' + dataset.label;
                    item.onclick = function() {
                        var meta = chart.getDatasetMeta(index);
                        meta.hidden = !meta.hidden;
                        chart.update();
                        item.style.opacity = meta.hidden ? '0.5' : '1';
                    };
                    legendContainer.appendChild(item);
                });
            }
        } catch (e) {
            console.error('Error creando gráfica:', e);
            if (errorDiv) {
                errorDiv.style.display = 'block';
                errorDiv.innerHTML = 'Error: ' + e.message;
            }
        }
    }
    
    // Ejecutar cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', crearGrafica);
    } else {
        setTimeout(crearGrafica, 100);
    }
</script>

<script>
function exportStats(type) {
    // Función para exportar estadísticas (implementar según necesidad)
    alert('Función de exportación en desarrollo');
}
</script>
