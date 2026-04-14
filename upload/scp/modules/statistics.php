<?php

$endDate = new DateTime('today');
$endDate->setTime(23, 59, 59);

$startDate = (clone $endDate)->modify('-29 days');
$startDate->setTime(0, 0, 0);

$startInput = (string)($_GET['start'] ?? '');
$endInput = (string)($_GET['end'] ?? '');

if ($startInput !== '') {
    try {
        $startDate = new DateTime($startInput);
        $startDate->setTime(0, 0, 0);
    } catch (Exception $e) {
    }
}
if ($endInput !== '') {
    try {
        $endDate = new DateTime($endInput);
        $endDate->setTime(23, 59, 59);
    } catch (Exception $e) {
    }
}

if ($startDate > $endDate) {
    $tmp = $startDate;
    $startDate = $endDate;
    $endDate = $tmp;
    $startDate->setTime(0, 0, 0);
    $endDate->setTime(23, 59, 59);
}

$start = $startDate->format('Y-m-d');
$end = $endDate->format('Y-m-d');

$eid = empresaId();

function bindDynamicParams($stmt, string $types, array $params): bool {
    $refs = [];
    $refs[] = $types;
    foreach ($params as $k => $v) {
        $refs[] = &$params[$k];
    }
    return (bool)call_user_func_array([$stmt, 'bind_param'], $refs);
}

$resolvedStatusIds = [];
$stmt = $mysqli->prepare("SELECT id, name FROM ticket_status WHERE name IN ('Cerrado','Resuelto')");
if ($stmt && $stmt->execute()) {
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $id = (int)($row['id'] ?? 0);
        if ($id > 0) {
            $resolvedStatusIds[] = $id;
        }
    }
}

$totalCreated = 0;
$stmt = $mysqli->prepare('SELECT COUNT(*) c FROM tickets WHERE empresa_id = ? AND DATE(created) BETWEEN ? AND ?');
if ($stmt) {
    $stmt->bind_param('iss', $eid, $start, $end);
    if ($stmt->execute()) {
        $totalCreated = (int)(($stmt->get_result()->fetch_assoc()['c'] ?? 0));
    }
}

$totalResolved = 0;
if (!empty($resolvedStatusIds)) {
    $in = implode(',', array_fill(0, count($resolvedStatusIds), '?'));
    $sql = 'SELECT COUNT(*) c FROM tickets WHERE empresa_id = ? AND DATE(created) BETWEEN ? AND ? AND status_id IN (' . $in . ')';
    $stmt = $mysqli->prepare($sql);
    if ($stmt) {
        $types = 'iss' . str_repeat('i', count($resolvedStatusIds));
        $params = array_merge([$eid, $start, $end], $resolvedStatusIds);
        bindDynamicParams($stmt, $types, $params);
        if ($stmt->execute()) {
            $totalResolved = (int)(($stmt->get_result()->fetch_assoc()['c'] ?? 0));
        }
    }
} else {
    $stmt = $mysqli->prepare('SELECT COUNT(*) c FROM tickets WHERE empresa_id = ? AND DATE(created) BETWEEN ? AND ? AND closed IS NOT NULL');
    if ($stmt) {
        $stmt->bind_param('iss', $eid, $start, $end);
        if ($stmt->execute()) {
            $totalResolved = (int)(($stmt->get_result()->fetch_assoc()['c'] ?? 0));
        }
    }
}

$avgResolveHours = null;
$stmt = $mysqli->prepare('SELECT AVG(TIMESTAMPDIFF(MINUTE, created, closed)) AS avg_min FROM tickets WHERE empresa_id = ? AND closed IS NOT NULL AND DATE(closed) BETWEEN ? AND ?');
if ($stmt) {
    $stmt->bind_param('iss', $eid, $start, $end);
    if ($stmt->execute()) {
        $row = $stmt->get_result()->fetch_assoc();
        $avgMin = isset($row['avg_min']) ? (float)$row['avg_min'] : null;
        if ($avgMin !== null && $avgMin > 0) {
            $avgResolveHours = $avgMin / 60.0;
        }
    }
}

$avgFirstResponseHours = null;
$sqlFirstResp = "
    SELECT AVG(TIMESTAMPDIFF(MINUTE, t.created, fr.first_staff_reply)) AS avg_min
    FROM tickets t
    JOIN threads th ON th.ticket_id = t.id
    JOIN (
        SELECT te.thread_id, MIN(te.created) AS first_staff_reply
        FROM thread_entries te
        WHERE te.staff_id IS NOT NULL AND (te.is_internal IS NULL OR te.is_internal = 0)
        GROUP BY te.thread_id
    ) fr ON fr.thread_id = th.id
    WHERE t.empresa_id = ? AND DATE(t.created) BETWEEN ? AND ?
";
$stmt = $mysqli->prepare($sqlFirstResp);
if ($stmt) {
    $stmt->bind_param('iss', $eid, $start, $end);
    if ($stmt->execute()) {
        $row = $stmt->get_result()->fetch_assoc();
        $avgMin = isset($row['avg_min']) ? (float)$row['avg_min'] : null;
        if ($avgMin !== null && $avgMin > 0) {
            $avgFirstResponseHours = $avgMin / 60.0;
        }
    }
}

$byStatus = [];
$sqlStatus = "
    SELECT COALESCE(ts.name, 'Sin estado') AS status_name, COUNT(*) AS total
    FROM tickets t
    LEFT JOIN ticket_status ts ON ts.id = t.status_id
    WHERE t.empresa_id = ? AND DATE(t.created) BETWEEN ? AND ?
    GROUP BY COALESCE(ts.name, 'Sin estado')
    ORDER BY total DESC
";
$stmt = $mysqli->prepare($sqlStatus);
if ($stmt) {
    $stmt->bind_param('iss', $eid, $start, $end);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        $agg = [];
        while ($row = $res->fetch_assoc()) {
            $name = (string)($row['status_name'] ?? '');
            if ($name === 'Resuelto') {
                $name = 'Cerrado';
            }
            $agg[$name] = ($agg[$name] ?? 0) + (int)($row['total'] ?? 0);
        }
        foreach ($agg as $name => $total) {
            $byStatus[] = [
                'name' => (string)$name,
                'total' => (int)$total,
            ];
        }
    }
}

$byPriority = [];
$sqlPriority = "
    SELECT COALESCE(p.name, 'Sin prioridad') AS priority_name, COUNT(*) AS total
    FROM tickets t
    LEFT JOIN priorities p ON p.id = t.priority_id
    WHERE t.empresa_id = ? AND DATE(t.created) BETWEEN ? AND ?
    GROUP BY COALESCE(p.name, 'Sin prioridad')
    ORDER BY total DESC
";
$stmt = $mysqli->prepare($sqlPriority);
if ($stmt) {
    $stmt->bind_param('iss', $eid, $start, $end);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $byPriority[] = [
                'name' => (string)($row['priority_name'] ?? ''),
                'total' => (int)($row['total'] ?? 0),
            ];
        }
    }
}

$byDept = [];
$sqlDept = "
    SELECT COALESCE(d.name, 'Sin departamento') AS dept_name, COUNT(*) AS total
    FROM tickets t
    LEFT JOIN departments d ON d.id = t.dept_id
    WHERE t.empresa_id = ? AND DATE(t.created) BETWEEN ? AND ?
    GROUP BY COALESCE(d.name, 'Sin departamento')
    ORDER BY total DESC
";
$stmt = $mysqli->prepare($sqlDept);
if ($stmt) {
    $stmt->bind_param('iss', $eid, $start, $end);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $byDept[] = [
                'name' => (string)($row['dept_name'] ?? ''),
                'total' => (int)($row['total'] ?? 0),
            ];
        }
    }
}

$byAgent = [];
$sqlAgent = "
    SELECT
        CASE
            WHEN s.id IS NULL THEN 'Sin asignar'
            ELSE TRIM(CONCAT(COALESCE(s.firstname,''),' ',COALESCE(s.lastname,'')))
        END AS agent_name,
        COUNT(*) AS total
    FROM tickets t
    LEFT JOIN staff s ON s.id = t.staff_id
    WHERE t.empresa_id = ? AND DATE(t.created) BETWEEN ? AND ?
    GROUP BY agent_name
    ORDER BY total DESC
    LIMIT 8
";
$stmt = $mysqli->prepare($sqlAgent);
if ($stmt) {
    $stmt->bind_param('iss', $eid, $start, $end);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $name = trim((string)($row['agent_name'] ?? ''));
            if ($name === '') $name = 'Sin asignar';
            $byAgent[] = [
                'name' => $name,
                'total' => (int)($row['total'] ?? 0),
            ];
        }
    }
}

$byTopic = [];
$sqlTopic = "
    SELECT COALESCE(ht.name, 'Sin tema') AS topic_name, COUNT(*) AS total
    FROM tickets t
    LEFT JOIN help_topics ht ON ht.id = t.topic_id
    WHERE t.empresa_id = ? AND DATE(t.created) BETWEEN ? AND ?
    GROUP BY COALESCE(ht.name, 'Sin tema')
    ORDER BY total DESC
";
$stmt = $mysqli->prepare($sqlTopic);
if ($stmt) {
    $stmt->bind_param('iss', $eid, $start, $end);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $byTopic[] = [
                'name' => (string)($row['topic_name'] ?? ''),
                'total' => (int)($row['total'] ?? 0),
            ];
        }
    }
}

$createdByMonth = [];
$sqlCreatedMonth = "
    SELECT DATE_FORMAT(created, '%Y-%m') AS ym, COUNT(*) AS total
    FROM tickets
    WHERE empresa_id = ? AND DATE(created) BETWEEN ? AND ?
    GROUP BY DATE_FORMAT(created, '%Y-%m')
    ORDER BY ym
";
$stmt = $mysqli->prepare($sqlCreatedMonth);
if ($stmt) {
    $stmt->bind_param('iss', $eid, $start, $end);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $createdByMonth[] = [
                'ym' => (string)($row['ym'] ?? ''),
                'total' => (int)($row['total'] ?? 0),
            ];
        }
    }
}

$resolvedByMonth = [];
if (!empty($resolvedStatusIds)) {
    $in = implode(',', array_fill(0, count($resolvedStatusIds), '?'));
    $sqlResolvedMonth = "
        SELECT DATE_FORMAT(created, '%Y-%m') AS ym, COUNT(*) AS total
        FROM tickets
        WHERE empresa_id = ? AND DATE(created) BETWEEN ? AND ?
        AND status_id IN ($in)
        GROUP BY DATE_FORMAT(created, '%Y-%m')
        ORDER BY ym
    ";
    $stmt = $mysqli->prepare($sqlResolvedMonth);
    if ($stmt) {
        $types = 'iss' . str_repeat('i', count($resolvedStatusIds));
        $params = array_merge([$eid, $start, $end], $resolvedStatusIds);
        bindDynamicParams($stmt, $types, $params);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $resolvedByMonth[] = [
                    'ym' => (string)($row['ym'] ?? ''),
                    'total' => (int)($row['total'] ?? 0),
                ];
            }
        }
    }
} else {
    $sqlResolvedMonth = "
        SELECT DATE_FORMAT(created, '%Y-%m') AS ym, COUNT(*) AS total
        FROM tickets
        WHERE empresa_id = ? AND DATE(created) BETWEEN ? AND ?
        AND closed IS NOT NULL
        GROUP BY DATE_FORMAT(created, '%Y-%m')
        ORDER BY ym
    ";
    $stmt = $mysqli->prepare($sqlResolvedMonth);
    if ($stmt) {
        $stmt->bind_param('iss', $eid, $start, $end);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $resolvedByMonth[] = [
                    'ym' => (string)($row['ym'] ?? ''),
                    'total' => (int)($row['total'] ?? 0),
                ];
            }
        }
    }
}

$topicLabels = array_map(function ($r) { return $r['name']; }, $byTopic);
$topicTotals = array_map(function ($r) { return $r['total']; }, $byTopic);

$statusLabels = array_map(function ($r) { return $r['name']; }, $byStatus);
$statusTotals = array_map(function ($r) { return $r['total']; }, $byStatus);

$priorityLabels = array_map(function ($r) { return $r['name']; }, $byPriority);
$priorityTotals = array_map(function ($r) { return $r['total']; }, $byPriority);

$deptLabels = array_map(function ($r) { return $r['name']; }, $byDept);
$deptTotals = array_map(function ($r) { return $r['total']; }, $byDept);

$agentLabels = array_map(function ($r) { return $r['name']; }, $byAgent);
$agentTotals = array_map(function ($r) { return $r['total']; }, $byAgent);

$createdLabels = array_map(function ($r) { return $r['ym']; }, $createdByMonth);
$createdTotals = array_map(function ($r) { return $r['total']; }, $createdByMonth);

$resolvedLabels = array_map(function ($r) { return $r['ym']; }, $resolvedByMonth);
$resolvedTotals = array_map(function ($r) { return $r['total']; }, $resolvedByMonth);

?>

<style>
    .stats-page { padding-bottom: 18px; }
    .stats-hero {
        background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 55%, #0ea5e9 100%);
        border: 1px solid rgba(37, 99, 235, 0.2);
        border-radius: 14px;
        padding: 1.5rem 2rem;
        color: #fff;
        box-shadow: 0 14px 32px rgba(37, 99, 235, 0.28);
        margin-bottom: 16px;
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
    .stats-page .card {
        border-radius: 14px;
        border: 1px solid rgba(226, 232, 240, .9);
        box-shadow: 0 10px 26px rgba(15, 23, 42, 0.07);
    }
    .stats-page .card-body { padding: 18px; }
    .stats-page h5 { font-weight: 800; letter-spacing: .01em; color: #0f172a; }
    .stats-page .form-label {
        font-weight: 700;
        font-size: 12px;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: #475569;
    }
    .stats-page .form-control { border-radius: 10px; font-weight: 600; }
    .stats-page .btn { border-radius: 10px; font-weight: 700; }
    .stats-kpi .text-muted { font-size: .82rem; font-weight: 600; color: #64748b !important; }
    .stats-kpi .fs-3 { font-weight: 800; color: #0f172a; }
    .stats-page .table thead th {
        font-size: 12px;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: #475569;
        border-bottom-color: rgba(148, 163, 184, 0.25);
    }
</style>

<div class="stats-page">
    <div class="stats-hero d-flex align-items-start justify-content-between gap-3 flex-wrap mb-3">
        <div class="d-flex align-items-center gap-3">
            <span class="stats-hero-icon"><i class="bi bi-bar-chart-line"></i></span>
            <div>
                <h3 class="stats-hero-title">Estadísticas</h3>
                <div class="stats-hero-sub">Resumen y métricas de tickets según rango de fechas.</div>
            </div>
        </div>
        <span class="stats-range"><i class="bi bi-calendar3"></i> <?php echo html($start); ?> — <?php echo html($end); ?></span>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="get" action="statics.php" class="row g-3 align-items-end">
                <div class="col-12 col-md-4">
                    <label class="form-label" for="stat-start">Desde</label>
                    <input class="form-control" type="date" id="stat-start" name="start" value="<?php echo html($start); ?>">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label" for="stat-end">Hasta</label>
                    <input class="form-control" type="date" id="stat-end" name="end" value="<?php echo html($end); ?>">
                </div>
                <div class="col-12 col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                    <a class="btn btn-outline-secondary" href="statics.php">Limpiar</a>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card stats-kpi">
                <div class="card-body">
                    <div class="text-muted">Tickets creados</div>
                    <div class="fs-3 fw-bold"><?php echo (int)$totalCreated; ?></div>
                    <div class="text-muted small"><?php echo html($start); ?> a <?php echo html($end); ?></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card stats-kpi">
                <div class="card-body">
                    <div class="text-muted">Tickets resueltos</div>
                    <div class="fs-3 fw-bold"><?php echo (int)$totalResolved; ?></div>
                    <div class="text-muted small"><?php echo html($start); ?> a <?php echo html($end); ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-6">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h5 class="mb-0">Tickets por tema</h5>
                    </div>
                    <canvas id="topicChart" height="220"></canvas>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-6">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="mb-2">Creados vs Resueltos (mes)</h5>
                    <canvas id="monthChart" height="220"></canvas>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="mb-2">Tickets por estado</h5>
                    <canvas id="statusChart" height="220"></canvas>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="mb-2">Tickets por prioridad</h5>
                    <canvas id="priorityChart" height="220"></canvas>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="mb-2">Tickets por departamento</h5>
                    <canvas id="deptChart" height="220"></canvas>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="mb-2">Detalle por tema</h5>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Tema</th>
                                    <th class="text-end">Cantidad</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($byTopic)): ?>
                                    <tr><td colspan="2" class="text-muted">Sin datos para el rango seleccionado.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($byTopic as $r): ?>
                                        <tr>
                                            <td><?php echo html($r['name']); ?></td>
                                            <td class="text-end"><?php echo (int)$r['total']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    function safeParseJson(s, fallback) {
        try { return JSON.parse(s); } catch (e) { return fallback; }
    }

    var topicLabels = safeParseJson(<?php echo json_encode(json_encode($topicLabels, JSON_UNESCAPED_UNICODE)); ?>, []);
    var topicTotals = safeParseJson(<?php echo json_encode(json_encode($topicTotals)); ?>, []);

    var statusLabels = safeParseJson(<?php echo json_encode(json_encode($statusLabels, JSON_UNESCAPED_UNICODE)); ?>, []);
    var statusTotals = safeParseJson(<?php echo json_encode(json_encode($statusTotals)); ?>, []);

    var priorityLabels = safeParseJson(<?php echo json_encode(json_encode($priorityLabels, JSON_UNESCAPED_UNICODE)); ?>, []);
    var priorityTotals = safeParseJson(<?php echo json_encode(json_encode($priorityTotals)); ?>, []);

    var deptLabels = safeParseJson(<?php echo json_encode(json_encode($deptLabels, JSON_UNESCAPED_UNICODE)); ?>, []);
    var deptTotals = safeParseJson(<?php echo json_encode(json_encode($deptTotals)); ?>, []);

    var agentLabels = safeParseJson(<?php echo json_encode(json_encode($agentLabels, JSON_UNESCAPED_UNICODE)); ?>, []);
    var agentTotals = safeParseJson(<?php echo json_encode(json_encode($agentTotals)); ?>, []);

    var createdLabels = safeParseJson(<?php echo json_encode(json_encode($createdLabels)); ?>, []);
    var createdTotals = safeParseJson(<?php echo json_encode(json_encode($createdTotals)); ?>, []);

    var resolvedLabels = safeParseJson(<?php echo json_encode(json_encode($resolvedLabels)); ?>, []);
    var resolvedTotals = safeParseJson(<?php echo json_encode(json_encode($resolvedTotals)); ?>, []);

    var palette = [
        '#2563eb', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#0ea5e9', '#22c55e', '#e11d48', '#14b8a6', '#a855f7',
        '#64748b', '#f97316'
    ];
    var bg = function (n) {
        var out = [];
        for (var i = 0; i < n; i++) out.push(palette[i % palette.length] + 'CC');
        return out;
    };

    var donutOptions = {
        responsive: true,
        plugins: {
            legend: { position: 'bottom' },
            tooltip: { enabled: true }
        },
        cutout: '62%'
    };

    var topicCanvas = document.getElementById('topicChart');
    if (topicCanvas && window.Chart) {
        new Chart(topicCanvas, {
            type: 'bar',
            data: {
                labels: topicLabels,
                datasets: [{
                    label: 'Tickets',
                    data: topicTotals,
                    backgroundColor: 'rgba(37, 99, 235, 0.35)',
                    borderColor: 'rgba(37, 99, 235, 0.9)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false },
                    tooltip: { enabled: true }
                },
                scales: {
                    x: {
                        ticks: { autoSkip: true, maxRotation: 0 },
                        grid: { display: false }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0 }
                    }
                }
            }
        });
    }

    var statusCanvas = document.getElementById('statusChart');
    if (statusCanvas && window.Chart) {
        new Chart(statusCanvas, {
            type: 'doughnut',
            data: {
                labels: statusLabels,
                datasets: [{
                    label: 'Tickets',
                    data: statusTotals,
                    backgroundColor: bg(statusTotals.length),
                    borderWidth: 1
                }]
            },
            options: donutOptions
        });
    }

    var priorityCanvas = document.getElementById('priorityChart');
    if (priorityCanvas && window.Chart) {
        new Chart(priorityCanvas, {
            type: 'pie',
            data: {
                labels: priorityLabels,
                datasets: [{
                    label: 'Tickets',
                    data: priorityTotals,
                    backgroundColor: bg(priorityTotals.length),
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'bottom' }, tooltip: { enabled: true } }
            }
        });
    }

    var deptCanvas = document.getElementById('deptChart');
    if (deptCanvas && window.Chart) {
        new Chart(deptCanvas, {
            type: 'doughnut',
            data: {
                labels: deptLabels,
                datasets: [{
                    label: 'Tickets',
                    data: deptTotals,
                    backgroundColor: bg(deptTotals.length),
                    borderWidth: 1
                }]
            },
            options: donutOptions
        });
    }

    var monthCanvas = document.getElementById('monthChart');
    if (monthCanvas && window.Chart) {
        var labels = createdLabels;
        if (resolvedLabels.length > labels.length) labels = resolvedLabels;

        new Chart(monthCanvas, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Creados',
                        data: createdTotals,
                        backgroundColor: 'rgba(37, 99, 235, 0.55)'
                    },
                    {
                        label: 'Resueltos',
                        data: resolvedTotals,
                        backgroundColor: 'rgba(16, 185, 129, 0.55)'
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom' }
                },
                scales: {
                    y: { beginAtZero: true, precision: 0 }
                }
            }
        });
    }
})();
</script>
