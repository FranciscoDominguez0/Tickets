<?php
require_once '../../config.php';
require_once '../../includes/helpers.php';
require_once '../../includes/Auth.php';

// Si no está logueado, redirigir al login
if (!isset($_SESSION['staff_id'])) {
    header('Location: login.php');
    exit;
}

requireLogin('agente');
$staff = getCurrentUser();

$currentRoute = 'reportes';
$eid = empresaId();

// Verify if ticket_reports table exists
$hasReportsTable = false;
$chk = $mysqli->query("SHOW TABLES LIKE 'ticket_reports'");
if ($chk && $chk->num_rows > 0) {
    $hasReportsTable = true;
}

// Find "cerrado/closed" status ID dynamically
$statusIdClosed = 0;
$rsSt = $mysqli->query('SELECT id, name FROM ticket_status');
if ($rsSt) {
    while ($st = $rsSt->fetch_assoc()) {
        $sname = strtolower(trim((string)($st['name'] ?? '')));
        if ($sname !== '' && (str_contains($sname, 'cerrad') || str_contains($sname, 'closed'))) {
            $statusIdClosed = (int)$st['id'];
            break;
        }
    }
}

// Búsqueda
$search = trim((string)($_GET['q'] ?? ''));
$searchLike = '%' . $search . '%';

// Filtro por Mes (Default: Mes actual)
$monthFilter = trim((string)($_GET['month'] ?? date('Y-m')));

// Paginación
$perPage = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;
$totalTickets = 0;

// Fetch tickets (paginado + búsqueda)
$tickets = [];
if ($statusIdClosed > 0) {
    // Condición de búsqueda compartida
    $searchWhere = $search !== ''
        ? " AND (t.ticket_number LIKE ? OR d.name LIKE ? OR CONCAT(u.firstname,' ',u.lastname) LIKE ? OR u.email LIKE ?)"
        : '';

    $monthWhere = " AND DATE_FORMAT(t.closed, '%Y-%m') = ?";

    // COUNT total
    $countJoin = $search !== '' ? ' LEFT JOIN users u ON t.user_id = u.id' : '';
    $countQuery = "SELECT COUNT(*) as total
                   FROM tickets t
                   JOIN departments d ON t.dept_id = d.id AND d.requires_report = 1
                   {$countJoin}
                   WHERE t.empresa_id = ? AND t.status_id = ? {$monthWhere} {$searchWhere}";
    $cStmt = $mysqli->prepare($countQuery);
    if ($cStmt) {
        if ($search !== '') {
            $cStmt->bind_param('iisssss', $eid, $statusIdClosed, $monthFilter, $searchLike, $searchLike, $searchLike, $searchLike);
        } else {
            $cStmt->bind_param('iis', $eid, $statusIdClosed, $monthFilter);
        }
        $cStmt->execute();
        $totalTickets = (int)($cStmt->get_result()->fetch_assoc()['total'] ?? 0);
    }

    $totalPages = max(1, (int)ceil($totalTickets / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    $dataJoin = ' LEFT JOIN users u ON t.user_id = u.id';
    $reportJoin = $hasReportsTable ? ' LEFT JOIN ticket_reports r ON r.ticket_id = t.id' : '';
    $reportSelect = $hasReportsTable ? 'IF(r.id IS NOT NULL, 1, 0)' : '0';

    $query = "SELECT t.id, t.ticket_number, t.subject, t.closed, t.staff_id,
                     d.name as department_name,
                     s.firstname as staff_first, s.lastname as staff_last,
                     u.firstname as user_first, u.lastname as user_last,
                     {$reportSelect} as has_report
              FROM tickets t
              JOIN departments d ON t.dept_id = d.id AND d.requires_report = 1
              LEFT JOIN staff s ON t.staff_id = s.id
              {$dataJoin}
              {$reportJoin}
              WHERE t.empresa_id = ? AND t.status_id = ? {$monthWhere} {$searchWhere}
              ORDER BY t.closed DESC, t.id DESC
              LIMIT ? OFFSET ?";

    $stmt = $mysqli->prepare($query);
    if ($stmt) {
        if ($search !== '') {
            $stmt->bind_param('iisssssii', $eid, $statusIdClosed, $monthFilter, $searchLike, $searchLike, $searchLike, $searchLike, $perPage, $offset);
        } else {
            $stmt->bind_param('iisii', $eid, $statusIdClosed, $monthFilter, $perPage, $offset);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $tickets[] = $row;
        }
    }
} else {
    $totalPages = 1;
}

// Obtener IDs de tickets vistos por este staff (para persistencia del badge NEW)
$seenIds = [];
$sid = (int)($_SESSION['staff_id'] ?? 0);
if ($sid > 0) {
    $resSeen = $mysqli->query("SELECT ticket_id FROM staff_reports_seen WHERE staff_id = $sid");
    if ($resSeen) {
        while ($rs = $resSeen->fetch_assoc()) {
            $seenIds[] = (int)$rs['ticket_id'];
        }
    }
}

ob_start();
?>
<style>
:root {
    --primary-gradient: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    --success-gradient: linear-gradient(135deg, #10b981 0%, #059669 100%);
    --warning-gradient: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
}

/* ── reporte_tickets.php – Vista de tarjetas para móvil ── */
.rpt-card-list { display: none; padding: 12px; gap: 12px; flex-direction: column; }

.rpt-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    transition: box-shadow 0.2s;
}
.rpt-card:active { box-shadow: 0 1px 4px rgba(0,0,0,0.1); }

.rpt-card-accent {
    height: 4px;
    background: linear-gradient(90deg, #f59e0b 0%, #ea580c 100%);
}
.rpt-card-accent.done {
    background: linear-gradient(90deg, #16a34a 0%, #22d3ee 100%);
}

.rpt-card-body { padding: 14px 16px 12px; }

.rpt-card-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 10px;
}
.rpt-card-num {
    font-size: 1.1rem;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: -0.01em;
}
.rpt-card-num span {
    font-size: 0.75rem;
    font-weight: 600;
    color: #94a3b8;
    margin-right: 4px;
    text-transform: uppercase;
    letter-spacing: 0.06em;
}

.rpt-card-rows { display: flex; flex-direction: column; gap: 7px; margin-bottom: 14px; }
.rpt-card-row {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.85rem;
    color: #334155;
}
.rpt-card-row i { color: #64748b; width: 16px; text-align: center; flex-shrink: 0; }
.rpt-card-row .rpt-label {
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #94a3b8;
    font-weight: 600;
    min-width: 70px;
}
.rpt-card-row .rpt-val { font-weight: 600; color: #0f172a; flex: 1; }

.rpt-card-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-top: 1px solid #f1f5f9;
    padding-top: 10px;
    gap: 8px;
}
.rpt-card-footer .btn { flex: 1; justify-content: center; display: flex; align-items: center; gap: 6px; }

@media (max-width: 640px) {
    .rpt-desktop-table { display: none !important; }
    .rpt-card-list { display: flex; }
}

/* ── Buscador y Filtros Profesional ── */
.filter-bar {
    background: #fff;
    border-radius: 16px;
    padding: 16px 20px;
    margin-bottom: 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.02);
    border: 1px solid #f1f5f9;
}
.search-input-group {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 4px 12px;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s;
    width: 100%;
}
.search-input-group:focus-within {
    border-color: #2563eb;
    background: #fff;
    box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
}
.search-input-group input {
    border: none;
    background: transparent;
    padding: 8px 0;
    font-size: 0.95rem;
    width: 100%;
}
.search-input-group input:focus { outline: none; }

.btn-action {
    height: 36px;
    padding: 0 16px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.85rem;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s;
}

/* ── Tabla Profesional ── */
.rpt-desktop-table {
    background: #fff;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0,0,0,0.05);
    border: 1px solid #f1f5f9;
}
.tickets-table thead { background: #f8fafc; }
.tickets-table th {
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #475569;
    padding: 16px 20px;
    border-bottom: 1px solid #f1f5f9;
}
.tickets-table td {
    padding: 16px 20px;
    vertical-align: middle;
    border-bottom: 1px solid #f8fafc;
    font-size: 0.92rem;
    color: #1e293b;
}
.ticket-row:hover td { background: #f8fbff; }

.ticket-id-badge {
    font-weight: 800;
    color: #0f172a;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.ticket-id-badge:hover { color: #2563eb; }

.modern-badge {
    padding: 6px 12px;
    border-radius: 8px;
    font-weight: 700;
    font-size: 0.75rem;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.badge-pending { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
.badge-done { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
.badge-dept { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }

/* ── Badge NEW ── */
.badge-new {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    background: #2563eb;
    color: #fff;
    font-size: 0.62rem;
    font-weight: 800;
    padding: 2px 8px;
    border-radius: 20px;
}
</style>

<div class="tickets-shell">
    <div class="tickets-header">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2">
            <div>
                <h1>Reportes de Tickets</h1>
                <div class="sub">Tickets cerrados de departamentos que requieren reporte</div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="badge bg-info"><?php echo $totalTickets; ?> Total</span>
            </div>
        </div>
    </div>

    <!-- === Barra de Filtros === -->
    <div class="filter-bar">
        <form method="GET" action="" class="row g-3 align-items-center">
            <div class="col-lg-4 col-md-6">
                <div class="search-input-group">
                    <i class="bi bi-search text-muted"></i>
                    <input type="text" name="q" placeholder="Buscar por # ticket, depto, cliente..."
                           value="<?php echo htmlspecialchars($search); ?>" autocomplete="off">
                    <?php if ($search !== ''): ?>
                        <a href="?month=<?php echo urlencode($monthFilter); ?>" class="text-muted"><i class="bi bi-x-circle-fill"></i></a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-auto">
                <select name="month" class="filter-select" onchange="this.form.submit()" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 8px 12px; font-size: 0.9rem; font-weight: 600; color: #475569; cursor: pointer; outline: none;">
                    <?php 
                    // Generar lista de los últimos 12 meses
                    for ($i = 0; $i < 12; $i++) {
                        $mDate = date('Y-m', strtotime("-$i months"));
                        $monthsEs = ['January'=>'Enero','February'=>'Febrero','March'=>'Marzo','April'=>'Abril','May'=>'Mayo','June'=>'Junio','July'=>'Julio','August'=>'Agosto','September'=>'Septiembre','October'=>'Octubre','November'=>'Noviembre','December'=>'Diciembre'];
                        $mLabel = str_replace(array_keys($monthsEs), array_values($monthsEs), date('F Y', strtotime($mDate . '-01')));

                        $selected = ($mDate === $monthFilter) ? 'selected' : '';
                        echo "<option value=\"$mDate\" $selected>$mLabel</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary btn-action" style="background:var(--primary-gradient); border:none;">
                    <i class="bi bi-funnel"></i> Filtrar
                </button>
            </div>
            <div class="col-auto ms-auto d-flex gap-2">
                <a href="export_reports_csv.php?month=<?php echo urlencode($monthFilter); ?>&q=<?php echo urlencode($search); ?>" 
                   class="btn btn-outline-success btn-action border-success">
                    <i class="bi bi-file-earmark-excel"></i> Exportar Excel (.xlsx)
                </a>
            </div>
            <?php if ($search !== ''): ?>
                <div class="col-auto">
                    <span class="text-muted small">
                        <?php echo $totalTickets; ?> resultado<?php echo $totalTickets !== 1 ? 's' : ''; ?>
                    </span>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <?php if (empty($tickets)): ?>
    <div class="tickets-panel p-0 rpt-desktop-table">
        <div class="tickets-table-wrap">
            <table class="tickets-table table table-hover mb-0">
                <tbody>
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                            No hay tickets cerrados que requieran reporte.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php else: ?>

    <!-- === VISTA DESKTOP: Tabla === -->
    <div class="rpt-desktop-table">
        <div class="table-responsive">
            <table class="tickets-table table table-hover mb-0">
                <thead>
                    <tr>
                        <th># Ticket</th>
                        <th>Departamento</th>
                        <th>Técnico</th>
                        <th>Fecha Cierre</th>
                        <th>Estado Reporte</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $t): ?>
                    <?php 
                        $staffName = trim(($t['staff_first'] ?? '') . ' ' . ($t['staff_last'] ?? ''));
                        if ($staffName === '') $staffName = 'Sin asignar';
                        $closedDate = !empty($t['closed']) ? date('d/m/Y H:i', strtotime($t['closed'])) : 'N/A';
                        $hasReport = (int)($t['has_report'] ?? 0);
                        $isNew = !$hasReport && !in_array((int)$t['id'], $seenIds);
                    ?>
                        <tr class="ticket-row">
                            <td class="ps-4">
                                <a href="tickets.php?id=<?php echo (int) $t['id']; ?>" class="ticket-id-badge">
                                    <i class="bi bi-hash text-muted"></i><?php echo htmlspecialchars($t['ticket_number']); ?>
                                </a>
                                <?php if ($isNew): ?><span class="badge-new ms-2">NEW</span><?php endif; ?>
                            </td>
                            <td><span class="modern-badge badge-dept"><i class="bi bi-building"></i> <?php echo htmlspecialchars($t['department_name']); ?></span></td>
                            <td class="fw-600"><?php echo htmlspecialchars($staffName); ?></td>
                            <td class="text-muted small"><?php echo htmlspecialchars($closedDate); ?></td>
                            <td>
                                <?php if ($hasReport): ?>
                                    <span class="modern-badge badge-done"><i class="bi bi-check-circle-fill"></i> Completado</span>
                                <?php else: ?>
                                    <span class="modern-badge badge-pending"><i class="bi bi-exclamation-circle"></i> Pendiente</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-4">
                                <a href="reporte_costos.php?ticket_id=<?php echo (int) $t['id']; ?>" 
                                   class="btn btn-action <?php echo $hasReport ? 'btn-outline-primary' : 'btn-primary'; ?>"
                                   style="<?php echo $hasReport ? '' : 'background:var(--primary-gradient); border:none;'; ?>">
                                    <i class="bi <?php echo $hasReport ? 'bi-eye' : 'bi-plus-lg'; ?>"></i> 
                                    <?php echo $hasReport ? 'Ver' : 'Reportar'; ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- === VISTA MÓVIL: Tarjetas === -->
    <div class="rpt-card-list">
        <?php foreach ($tickets as $t): ?>
            <?php 
            $staffName = trim(($t['staff_first'] ?? '') . ' ' . ($t['staff_last'] ?? ''));
            if ($staffName === '') $staffName = 'Sin asignar';
            $closedDate = !empty($t['closed']) ? date('d/m/Y', strtotime($t['closed'])) : 'N/A';
            $closedTime = !empty($t['closed']) ? date('H:i', strtotime($t['closed'])) : '';
            $hasReport = (int)($t['has_report'] ?? 0);
            $isNew = !$hasReport && !in_array((int)$t['id'], $seenIds);
            ?>
            <div class="rpt-card">
                <div class="rpt-card-accent <?php echo $hasReport ? 'done' : ''; ?>"></div>
                <div class="rpt-card-body">
                    <div class="rpt-card-top">
                        <div>
                            <div class="rpt-card-num">
                                <span>#</span><?php echo htmlspecialchars($t['ticket_number']); ?>
                            </div>
                            <?php if ($isNew): ?>
                                <span class="badge-new mt-1"><i class="bi bi-circle-fill" style="font-size:0.45rem;"></i> NEW</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($hasReport): ?>
                            <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Completado</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark"><i class="bi bi-clock me-1"></i>Pendiente</span>
                        <?php endif; ?>
                    </div>

                    <div class="rpt-card-rows">
                        <div class="rpt-card-row">
                            <i class="bi bi-building"></i>
                            <span class="rpt-label">Dpto</span>
                            <span class="rpt-val">
                                <span class="badge bg-secondary fw-normal"><?php echo htmlspecialchars($t['department_name']); ?></span>
                            </span>
                        </div>
                        <div class="rpt-card-row">
                            <i class="bi bi-person-badge"></i>
                            <span class="rpt-label">Técnico</span>
                            <span class="rpt-val"><?php echo htmlspecialchars($staffName); ?></span>
                        </div>
                        <div class="rpt-card-row">
                            <i class="bi bi-calendar-check"></i>
                            <span class="rpt-label">Cierre</span>
                            <span class="rpt-val"><?php echo htmlspecialchars($closedDate); ?> <small class="text-muted"><?php echo htmlspecialchars($closedTime); ?></small></span>
                        </div>
                    </div>

                    <div class="rpt-card-footer">
                        <a href="reporte_costos.php?ticket_id=<?php echo (int) $t['id']; ?>"
                           class="btn btn-sm <?php echo $hasReport ? 'btn-success' : 'btn-primary'; ?>"
                           style="<?php echo $hasReport ? '' : 'background:#2563eb; border:none;'; ?>">
                            <i class="bi <?php echo $hasReport ? 'bi-eye' : 'bi-plus-circle'; ?>"></i>
                            <?php echo $hasReport ? 'Ver Reporte' : 'Registrar Reporte'; ?>
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php endif; ?>

    <?php if ($totalPages > 1): ?>
    <!-- Paginación -->
    <?php 
    $qParam = $search !== '' ? '&q=' . urlencode($search) : ''; 
    $mParam = '&month=' . urlencode($monthFilter);
    $allParams = $mParam . $qParam;
    ?>
    <nav class="d-flex justify-content-center align-items-center gap-2 py-3 px-3" style="flex-wrap: wrap;">
        <?php if ($page > 1): ?>
            <a href="?page=<?php echo $page - 1; ?><?php echo $allParams; ?>" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-chevron-left"></i> Anterior
            </a>
        <?php else: ?>
            <button class="btn btn-sm btn-outline-secondary" disabled><i class="bi bi-chevron-left"></i> Anterior</button>
        <?php endif; ?>

        <?php
        $range = 2;
        $start = max(1, $page - $range);
        $end   = min($totalPages, $page + $range);
        if ($start > 1): ?>
            <a href="?page=1<?php echo $allParams; ?>" class="btn btn-sm btn-outline-secondary">1</a>
            <?php if ($start > 2): ?><span class="text-muted small px-1">&hellip;</span><?php endif; ?>
        <?php endif;
        for ($i = $start; $i <= $end; $i++): ?>
            <a href="?page=<?php echo $i; ?><?php echo $allParams; ?>"
               class="btn btn-sm <?php echo $i === $page ? 'btn-primary' : 'btn-outline-secondary'; ?>"
               <?php echo $i === $page ? 'style="background:linear-gradient(135deg,#2563eb,#1d4ed8);border:none;"' : ''; ?>>
                <?php echo $i; ?>
            </a>
        <?php endfor;
        if ($end < $totalPages): ?>
            <?php if ($end < $totalPages - 1): ?><span class="text-muted small px-1">&hellip;</span><?php endif; ?>
            <a href="?page=<?php echo $totalPages; ?><?php echo $allParams; ?>" class="btn btn-sm btn-outline-secondary"><?php echo $totalPages; ?></a>
        <?php endif; ?>

        <?php if ($page < $totalPages): ?>
            <a href="?page=<?php echo $page + 1; ?><?php echo $allParams; ?>" class="btn btn-sm btn-outline-secondary">
                Siguiente <i class="bi bi-chevron-right"></i>
            </a>
        <?php else: ?>
            <button class="btn btn-sm btn-outline-secondary" disabled>Siguiente <i class="bi bi-chevron-right"></i></button>
        <?php endif; ?>

        <span class="text-muted small ms-1">P&aacute;gina <?php echo $page; ?> de <?php echo $totalPages; ?></span>
    </nav>
    <?php endif; ?>

</div>
<?php
$content = ob_get_clean();

// Renderizar layout principal (header + sidebar estáticos)
require __DIR__ . '/layout/layout.php';
