<?php
/**
 * estadisticas.php  —  /scp/superadmin/estadisticas.php
 *
 * OPTIMIZACIONES DE RENDIMIENTO:
 *
 *  [PHP/BD]
 *  - 1 query INFORMATION_SCHEMA para verificar 4 tablas a la vez
 *  - KPIs principales con COUNT+CASE en 1 sola query
 *  - Resumen de pagos (mes + histórico) en 1 query con CASE
 *  - Crecimiento de empresas: SELECT de columnas indexadas únicamente
 *  - Comprobaciones de $res !== false antes de fetch (evita warnings fatales)
 *
 *  [PHP puro]
 *  - ob_start() al inicio para buffering eficiente
 *  - match() en vez de switch para helpers
 *  - Short-echo <?= en HTML para menos overhead de parser
 *  - Array de KPIs procesado una vez, no recalculado en el template
 *
 *  [Frontend]
 *  - Chart.js + estadisticas.js con atributo defer
 *  - window.dashData inyectado ANTES de los scripts diferidos
 *  - Sin <style> inline (todo en css/estadisticas.css, cacheable)
 *
 * Estilos: css/estadisticas.css
 * Scripts: js/estadisticas.js (defer)
 */

require_once '../../../config.php';
require_once '../../../includes/helpers.php';

ob_start();

/* ================================================================
   PASO 1 — Verificar tablas con 1 query INFORMATION_SCHEMA
   ================================================================ */
$hasEmpresas = $hasPagos = $hasTickets = $hasStaff = false;
$dbName = '';

if (isset($mysqli) && $mysqli) {
    try {
        $dbName = (string)($mysqli->query('SELECT DATABASE() db')->fetch_assoc()['db'] ?? '');

        if ($dbName !== '') {
            $esc  = $mysqli->real_escape_string($dbName);
            $res  = $mysqli->query("
                SELECT TABLE_NAME
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_SCHEMA = '{$esc}'
                  AND TABLE_NAME IN ('empresas','pagos_empresas','tickets','staff')
            ");
            $exist = [];
            if ($res) while ($r = $res->fetch_row()) $exist[$r[0]] = true;

            $hasEmpresas = isset($exist['empresas']);
            $hasPagos    = isset($exist['pagos_empresas']);
            $hasTickets  = isset($exist['tickets']);
            $hasStaff    = isset($exist['staff']);
        }
    } catch (Throwable $e) { /* silencioso */ }
}

/* ================================================================
   PASO 2 — KPIs en 1 query COUNT+CASE
   ================================================================ */
$kpiActivas = $kpiVencidas = $kpiBloqueadas = 0;
$pagosAlDia = $pagosVencidos = $pagosSuspendidos = 0;

if ($hasEmpresas) {
    $res = $mysqli->query("
        SELECT
            SUM(estado      = 'activa')      AS activas,
            SUM(estado_pago = 'vencido')     AS vencidas,
            SUM(bloqueada   = 1)             AS bloqueadas,
            SUM(estado_pago = 'al_dia')      AS pago_al_dia,
            SUM(estado_pago = 'vencido')     AS pago_vencido,
            SUM(estado_pago = 'suspendido')  AS pago_suspendido
        FROM empresas
    ");
    if ($res && $r = $res->fetch_assoc()) {
        $kpiActivas       = (int)($r['activas']         ?? 0);
        $kpiVencidas      = (int)($r['vencidas']        ?? 0);
        $kpiBloqueadas    = (int)($r['bloqueadas']       ?? 0);
        $pagosAlDia       = (int)($r['pago_al_dia']      ?? 0);
        $pagosVencidos    = (int)($r['pago_vencido']     ?? 0);
        $pagosSuspendidos = (int)($r['pago_suspendido']  ?? 0);
    }
}

/* ================================================================
   PASO 3 — Resumen pagos en 1 query
   ================================================================ */
$kpiIngresosMes = 0.0;
$kpiTotalPagos  = 0;
$incomeYears    = [];
$incomeByYear   = [];
$incomeYearDefault = (int)date('Y');

if ($hasPagos) {
    $res = $mysqli->query("
        SELECT
            SUM(CASE WHEN DATE_FORMAT(fecha_pago,'%Y-%m') = DATE_FORMAT(CURDATE(),'%Y-%m')
                     THEN monto ELSE 0 END) AS ingresos_mes,
            COUNT(*)                        AS total_pagos
        FROM pagos_empresas
    ");

    if ($res && $r = $res->fetch_assoc()) {
        $kpiIngresosMes = (float)($r['ingresos_mes'] ?? 0);
        $kpiTotalPagos  = (int)  ($r['total_pagos']  ?? 0);
    }

    /* Serie temporal: ingresos por mes (histórico completo) */
    $res = $mysqli->query("
        SELECT YEAR(fecha_pago) y, MONTH(fecha_pago) m, SUM(monto) total
        FROM pagos_empresas
        GROUP BY y, m
        ORDER BY y, m
    ");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $y = (int)($r['y'] ?? 0);
            $m = (int)($r['m'] ?? 0);
            $t = (float)($r['total'] ?? 0);
            if ($y <= 0 || $m <= 0) continue;
            if (!isset($incomeByYear[$y])) {
                $incomeByYear[$y] = [];
            }
            $incomeByYear[$y][(string)$m] = $t;
        }
    }

    if (!empty($incomeByYear)) {
        $incomeYears = array_keys($incomeByYear);
        sort($incomeYears);
        $maxYear = (int)max($incomeYears);
        $incomeYearDefault = in_array((int)date('Y'), $incomeYears, true) ? (int)date('Y') : $maxYear;
    }
}

/* ================================================================
   PASO 4 — Crecimiento de empresas
   ================================================================ */
$growthLabels  = [];
$growthTotals  = [];

if ($hasEmpresas) {
    $res = $mysqli->query("
        SELECT DATE_FORMAT(fecha_creacion,'%Y-%m') mes, COUNT(*) total
        FROM empresas
        GROUP BY mes ORDER BY mes
    ");
    if ($res) while ($r = $res->fetch_assoc()) {
        $growthLabels[] = $r['mes'];
        $growthTotals[] = (int)$r['total'];
    }
}

/* ================================================================
   PASO 5 — Últimos pagos con JOIN  (LIMIT 6, sólo columnas usadas)
   ================================================================ */
$ultimosPagos = [];

if ($hasPagos) {
    $res = $mysqli->query("
        SELECT p.monto, p.fecha_pago, p.metodo_pago, e.nombre AS empresa_nombre
        FROM pagos_empresas p
        JOIN empresas e ON e.id = p.empresa_id
        ORDER BY p.fecha_pago DESC, p.id DESC
        LIMIT 6
    ");
    if ($res) while ($r = $res->fetch_assoc()) $ultimosPagos[] = $r;
}

/* ================================================================
   PASO 6 — Tabla de empresas (columnas mínimas, LIMIT 50)
   ================================================================ */
$empresas = [];

if ($hasEmpresas) {
    $res = $mysqli->query("
        SELECT id, nombre, estado, fecha_vencimiento, estado_pago, bloqueada,
               CASE WHEN fecha_vencimiento IS NULL THEN NULL
                    ELSE DATEDIFF(fecha_vencimiento, CURDATE()) END AS dias_restantes
        FROM empresas
        ORDER BY id DESC
        LIMIT 50
    ");
    if ($res) while ($r = $res->fetch_assoc()) $empresas[] = $r;
}

/* ── Helpers badge ────────────────────────────────────────── */
function stEmpresa(string $v): string {
    return match(strtolower($v)) {
        'activa'     => 'bg-success bg-opacity-10 text-success',
        'suspendida' => 'bg-warning bg-opacity-10 text-warning',
        default      => 'bg-secondary bg-opacity-10 text-secondary',
    };
}
function stPago(string $v): string {
    return match($v) {
        'al_dia'    => 'bg-success bg-opacity-10 text-success',
        'vencido'   => 'bg-warning bg-opacity-10 text-warning',
        'suspendido'=> 'bg-danger bg-opacity-10 text-danger',
        default     => 'bg-secondary bg-opacity-10 text-secondary',
    };
}
function stDias(?int $d): string {
    if ($d === null) return 'bg-secondary bg-opacity-10 text-secondary';
    if ($d < 0)     return 'bg-danger text-white';
    if ($d <= 7)    return 'bg-warning text-dark';
    return 'bg-success bg-opacity-10 text-success';
}

/* ── Array de KPIs: sin "Tickets este mes", "Staff activo" ni "Pagos vencidos" ── */
$kpiCards = [
    ['bi-building-check', 'Empresas activas',  number_format($kpiActivas),             'primary',   'total registradas'],
    ['bi-cash-stack',     'Ingresos del mes',   '$'.number_format($kpiIngresosMes, 0), 'success',   date('M Y')],
    ['bi-slash-circle',   'Bloqueadas',          number_format($kpiBloqueadas),          'danger',    'acceso suspendido'],
    ['bi-receipt',        'Total pagos',         number_format($kpiTotalPagos),          'success',   'histórico'],
    ['bi-calendar-check', 'Suspendidas',         number_format($pagosSuspendidos),       'secondary', 'estado suspendido'],
];

?>

<!-- ══ CSS externo ══════════════════════════════════════════ -->
<link rel="stylesheet" href="css/estadisticas.css">

<!-- ══ HEADER ══════════════════════════════════════════════ -->
<div class="stats-hero mb-1">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="hero-icon"><i class="bi bi-bar-chart-line-fill"></i></div>
            <div>
                <h1>Estadísticas del sistema</h1>
                <p>Control global de clientes, pagos y operaciones</p>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <?php if ($dbName !== ''): ?>
                <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 px-3 py-2">
                    <i class="bi bi-database me-1"></i><?= html($dbName) ?>
                </span>
            <?php endif; ?>
            <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-3 py-2">
                <i class="bi bi-calendar3 me-1"></i><?= date('d M Y') ?>
            </span>
        </div>
    </div>
</div>

<!-- ══ KPIs ═════════════════════════════════════════════════ -->
<p class="section-title"><i class="bi bi-speedometer2"></i> Resumen general</p>

<div class="row g-3 mb-2">
    <?php foreach ($kpiCards as [$icon, $label, $value, $color, $sub]): ?>
    <div class="col-6 col-md-3">
        <div class="card kpi-card shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3 py-3">
                <div class="kpi-icon bg-<?= $color ?> bg-opacity-10 text-<?= $color ?>">
                    <i class="bi <?= $icon ?>"></i>
                </div>
                <div>
                    <div class="kpi-label text-muted"><?= $label ?></div>
                    <div class="kpi-number text-<?= $color ?>"><?= $value ?></div>
                    <div class="kpi-sub"><?= $sub ?></div>
                </div>
            </div>
            <div class="kpi-bar bg-<?= $color ?>"></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ══ GRÁFICAS FILA 1: Ingresos + Comparativo ══════════════ -->
<p class="section-title"><i class="bi bi-graph-up-arrow"></i> Analítica de ingresos</p>

<div class="row g-3 mb-2">
    <div class="col-xl-7">
        <div class="card chart-card shadow-sm h-100">
            <div class="card-header">
                <span class="chart-title">Ingresos por mes</span>
                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25" style="font-size:.67rem">
                    <i class="bi bi-currency-dollar"></i> Facturación
                </span>
                <div class="ms-auto d-flex align-items-center gap-2">
                    <span class="text-muted" style="font-size:.75rem">Año</span>
                    <select id="incomeYearSelect" class="form-select form-select-sm" style="width:auto">
                        <?php foreach ($incomeYears as $yy): ?>
                            <option value="<?= (int)$yy ?>" <?= ((int)$yy === (int)$incomeYearDefault) ? 'selected' : '' ?>><?= (int)$yy ?></option>
                        <?php endforeach; ?>
                        <?php if (empty($incomeYears)): ?>
                            <option value="<?= (int)$incomeYearDefault ?>" selected><?= (int)$incomeYearDefault ?></option>
                        <?php endif; ?>
                    </select>
                </div>
            </div>
            <div class="card-body pt-3 pb-2">
                <canvas id="incomeChart" style="max-height:240px"></canvas>
            </div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="card chart-card shadow-sm h-100">
            <div class="card-header">
                <span class="chart-title">Comparativo mensual</span>
                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25" style="font-size:.67rem">
                    <i class="bi bi-bar-chart-steps"></i> vs anterior
                </span>
            </div>
            <div class="card-body pt-3 pb-2">
                <canvas id="compareChart" style="max-height:240px"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- ══ GRÁFICAS FILA 2: Donut + Pagos + Crecimiento ═ -->
<p class="section-title"><i class="bi bi-pie-chart"></i> Distribución y crecimiento</p>

<div class="row g-3 mb-2">
    <div class="col-xl-3">
        <div class="card chart-card shadow-sm h-100">
            <div class="card-header"><span class="chart-title">Estado empresas</span></div>
            <div class="card-body d-flex flex-column align-items-center justify-content-center py-3">
                <canvas id="statusChart" style="max-height:160px;max-width:160px"></canvas>
                <div class="d-flex gap-3 mt-3 flex-wrap justify-content-center">
                    <div class="text-center">
                        <div class="donut-stat-num text-primary"><?= $kpiActivas ?></div>
                        <div class="donut-stat-label">Activas</div>
                    </div>
                    <div class="vr"></div>
                    <div class="text-center">
                        <div class="donut-stat-num text-warning"><?= $kpiVencidas ?></div>
                        <div class="donut-stat-label">Vencidas</div>
                    </div>
                    <div class="vr"></div>
                    <div class="text-center">
                        <div class="donut-stat-num text-danger"><?= $kpiBloqueadas ?></div>
                        <div class="donut-stat-label">Bloqueadas</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3">
        <div class="card chart-card shadow-sm h-100">
            <div class="card-header"><span class="chart-title">Distribución de pagos</span></div>
            <div class="card-body d-flex flex-column justify-content-center py-3">
                <canvas id="pagosDistChart" style="max-height:160px"></canvas>
            </div>
        </div>
    </div>
    <div class="col-xl-3">
        <div class="card chart-card shadow-sm h-100">
            <div class="card-header"><span class="chart-title">Crecimiento clientes</span></div>
            <div class="card-body pt-3 pb-2">
                <canvas id="growthChart" style="max-height:190px"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- ══ FILA 3: Últimos pagos (ancho completo) ═══════════════ -->
<p class="section-title"><i class="bi bi-activity"></i> Operaciones</p>

<div class="row g-3 mb-3">
    <div class="col-12">
        <div class="card chart-card shadow-sm">
            <div class="card-header">
                <span class="chart-title">Últimos pagos registrados</span>
                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25" style="font-size:.67rem">
                    <i class="bi bi-clock-history"></i> Recientes
                </span>
            </div>
            <div class="card-body py-2">
                <?php if (empty($ultimosPagos)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-receipt fs-2 d-block mb-2 opacity-25"></i>
                        Sin pagos registrados
                    </div>
                <?php else: ?>
                    <div class="row g-0">
                    <?php foreach ($ultimosPagos as $p): ?>
                    <div class="col-12 col-md-6">
                        <div class="pago-item px-3">
                            <div class="pago-avatar"><i class="bi bi-check-circle-fill"></i></div>
                            <div>
                                <div class="pago-empresa"><?= html((string)$p['empresa_nombre']) ?></div>
                                <div class="pago-fecha">
                                    <i class="bi bi-calendar3 me-1 opacity-50"></i>
                                    <?= html(date('d M Y', strtotime((string)$p['fecha_pago']))) ?>
                                    <?php if (!empty($p['metodo_pago'])): ?>&nbsp;·&nbsp;<?= html((string)$p['metodo_pago']) ?><?php endif; ?>
                                </div>
                            </div>
                            <div class="pago-monto">$<?= number_format((float)$p['monto'], 2) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ══ TABLA DE EMPRESAS ════════════════════════════════════ -->
<p class="section-title"><i class="bi bi-building"></i> Directorio de empresas</p>

<div class="card chart-card shadow-sm mb-4">
    <div class="card-header">
        <span class="chart-title">Empresas registradas</span>
        <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25" style="font-size:.68rem">
            <?= count($empresas) ?> registros
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive" style="max-height:480px;overflow-y:auto">
            <table class="table pro-table-lg mb-0">
                <thead>
                    <tr>
                        <th class="col-id">#</th>
                        <th>Empresa</th>
                        <th class="col-badge">Estado</th>
                        <th class="col-date">Vencimiento</th>
                        <th class="col-dias">Días rest.</th>
                        <th class="col-badge">Estado pago</th>
                        <th class="col-badge">Acceso</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($empresas)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">
                            <i class="bi bi-inbox fs-2 d-block mb-2 opacity-25"></i>
                            Sin datos disponibles
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($empresas as $e):
                        $dias = $e['dias_restantes'] !== null ? (int)$e['dias_restantes'] : null;
                    ?>
                    <tr>
                        <td class="text-muted fw-semibold" style="font-size:.82rem">#<?= $e['id'] ?></td>
                        <td class="fw-semibold"><?= html((string)$e['nombre']) ?></td>
                        <td><span class="badge-pill badge <?= stEmpresa((string)$e['estado']) ?>"><?= html((string)$e['estado']) ?></span></td>
                        <td>
                            <?php if (!empty($e['fecha_vencimiento'])): ?>
                                <span class="date-nowrap"><i class="bi bi-calendar3 me-1 text-muted opacity-50"></i><?= html((string)$e['fecha_vencimiento']) ?></span>
                            <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                        </td>
                        <td>
                            <?php if ($dias === null): ?><span class="text-muted">—</span>
                            <?php else: ?><span class="dias-pill <?= stDias($dias) ?>"><?= $dias > 0 ? "+{$dias}" : $dias ?>d</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge-pill badge <?= stPago((string)$e['estado_pago']) ?>"><?= html(str_replace('_',' ',(string)$e['estado_pago'])) ?></span></td>
                        <td>
                            <?php if ($e['bloqueada']): ?>
                                <span class="badge-pill badge bg-danger bg-opacity-10 text-danger"><i class="bi bi-lock-fill me-1"></i>Bloqueada</span>
                            <?php else: ?>
                                <span class="badge-pill badge bg-success bg-opacity-10 text-success"><i class="bi bi-check2 me-1"></i>Libre</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ══ Datos inyectados + scripts defer ════════════════════ -->
<script>
window.dashData = {
    incomeYearDefault: <?= (int)$incomeYearDefault ?>,
    incomeByYear     : <?= json_encode($incomeByYear, JSON_NUMERIC_CHECK) ?>,
    growthLabels    : <?= json_encode($growthLabels) ?>,
    growthTotals    : <?= json_encode($growthTotals,    JSON_NUMERIC_CHECK) ?>,
    kpiActivas      : <?= (int)$kpiActivas ?>,
    kpiVencidas     : <?= (int)$kpiVencidas ?>,
    kpiBloqueadas   : <?= (int)$kpiBloqueadas ?>,
    pagosAlDia      : <?= (int)$pagosAlDia ?>,
    pagosVencidos   : <?= (int)$pagosVencidos ?>,
    pagosSuspendidos: <?= (int)$pagosSuspendidos ?>,
};
</script>

<script src="https://cdn.jsdelivr.net/npm/chart.js" defer></script>
<script src="js/estadisticas.js" defer></script>

<?php
$content      = (string)ob_get_clean();
$currentRoute = 'dashboard';
require __DIR__ . '/layout.php';