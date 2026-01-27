<?php
// Módulo: Panel de control (dashboard)
// Subopciones internas + gráfica de actividad de tickets.

// Rango: últimos 30 días
$endDate   = new DateTime('today');
$startDate = (clone $endDate)->modify('-29 days');

// Estadísticas rápidas
$stats = [
    'total_tickets'  => 0,
    'created_30days' => 0,
];

// Total de tickets
$stmt = $mysqli->prepare('SELECT COUNT(*) AS count FROM tickets');
$stmt->execute();
$result = $stmt->get_result();
$stats['total_tickets'] = (int) $result->fetch_assoc()['count'];

// Tickets creados en el rango
$stmt = $mysqli->prepare('SELECT COUNT(*) AS count FROM tickets WHERE DATE(created) BETWEEN ? AND ?');
$start = $startDate->format('Y-m-d');
$end   = $endDate->format('Y-m-d');
$stmt->bind_param('ss', $start, $end);
$stmt->execute();
$result = $stmt->get_result();
$stats['created_30days'] = (int) $result->fetch_assoc()['count'];

// Datos para la gráfica: tickets creados por día en el rango
$sqlChart = "
    SELECT DATE(created) AS day, COUNT(*) AS total
    FROM tickets
    WHERE DATE(created) BETWEEN ? AND ?
    GROUP BY DATE(created)
    ORDER BY DATE(created)
";
$stmt = $mysqli->prepare($sqlChart);
$stmt->bind_param('ss', $start, $end);
$stmt->execute();
$chartResult = $stmt->get_result();

// Inicializar arrays con todos los días del rango para que la gráfica sea continua
$labels = [];
$values = [];
$cursor = clone $startDate;
$dataByDay = [];
while ($row = $chartResult->fetch_assoc()) {
    $dataByDay[$row['day']] = (int) $row['total'];
}
while ($cursor <= $endDate) {
    $dayKey = $cursor->format('Y-m-d');
    $labels[] = $cursor->format('d-m-Y');
    // Usamos null cuando no hay datos para que la línea no "baje" a cero
    $values[] = array_key_exists($dayKey, $dataByDay) ? $dataByDay[$dayKey] : null;
    $cursor->modify('+1 day');
}
?>

<!-- Subopciones internas del Panel de control -->
<div class="mb-3" style="background:#ffffff;border-radius:8px;border:1px solid #e5e7eb;padding:8px 16px;">
    <nav class="nav nav-pills small">
        <a class="nav-link active" aria-current="page" href="dashboard.php">
            Panel de control
        </a>
        <a class="nav-link" href="directory.php">
            Directorio del agente
        </a>
        <a class="nav-link" href="profile.php">
            Mi perfil
        </a>
    </nav>
</div>

<!-- Tarjeta principal de resumen -->
<div class="welcome-card">
    <h1>Panel de control</h1>
    <p>Actividad de tickets y estadísticas generales de los últimos 30 días.</p>
</div>

<!-- Gráfica de actividad de tickets -->
<div style="background:#ffffff;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,0.06);padding:20px;margin-bottom:24px;height:260px;position:relative;overflow:hidden;">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h5 class="mb-0">Actividad de Tickets</h5>
        <small class="text-muted">
            Rango: <?php echo $startDate->format('d-m-Y'); ?> - <?php echo $endDate->format('d-m-Y'); ?>
        </small>
    </div>
    <div style="position:absolute;inset:56px 8px 8px 0;">
        <canvas id="ticketsActivityChart"></canvas>
    </div>
</div>

<!-- Estadísticas de resumen -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-number"><?php echo $stats['total_tickets']; ?></div>
        <div class="stat-label">Total de Tickets</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?php echo $stats['created_30days']; ?></div>
        <div class="stat-label">Creados últimos 30 días</div>
    </div>
</div>

<!-- Scripts de la gráfica (solo para este módulo) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    (function () {
        const ctx = document.getElementById('ticketsActivityChart');
        if (!ctx) return;

        const labels = <?php echo json_encode($labels, JSON_UNESCAPED_UNICODE); ?>;
        const data = <?php echo json_encode($values, JSON_UNESCAPED_UNICODE); ?>;

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Tickets creados',
                    data: data,
                    fill: true,
                    tension: 0.25,
                    borderColor: '#1d4ed8',
                    backgroundColor: 'rgba(37, 99, 235, 0.15)',
                    pointRadius: 3,
                    pointBackgroundColor: '#1d4ed8',
                    spanGaps: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                },
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            autoSkip: true,
                            maxTicksLimit: 6,
                            maxRotation: 0,
                            minRotation: 0
                        }
                    },
                    y: {
                        beginAtZero: true,
                        precision: 0,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    })();
</script>


