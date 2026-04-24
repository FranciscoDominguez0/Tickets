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
/* ── Vista móvil: cards ── */
.rpt-card-list { display: none; padding: 0; gap: 10px; flex-direction: column; }
.rpt-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
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

@media (max-width: 767px) {
    .rpt-desktop-table { display: none !important; }
    .rpt-card-list { display: flex; }
}
</style>

<div class="tickets-shell">
    <div class="tickets-header">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2">
            <div>
                <h1>Reportes de Tickets</h1>
                <div class="sub">Tickets cerrados de departamentos que requieren reporte · <?php echo $totalTickets; ?> resultado<?php echo $totalTickets !== 1 ? 's' : ''; ?></div>
            </div>
            <a href="export_reports_csv.php?month=<?php echo urlencode($monthFilter); ?>&q=<?php echo urlencode($search); ?>" class="btn-new" style="background: linear-gradient(135deg, #16a34a, #15803d);">
                <i class="bi bi-file-earmark-excel me-1"></i> Exportar Excel
            </a>
        </div>
    </div>

    <!-- Toolbar: filtros y búsqueda -->
    <div class="tickets-panel" style="margin-bottom: 16px;">
        <div class="tickets-toolbar">
            <div class="tickets-filters">
                <select name="month" class="form-select form-select-sm" id="monthSelect" style="min-width: 160px; font-weight: 600; color: #475569;">
                    <?php
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
            <div class="tickets-search">
                <form method="GET" action="" class="input-group">
                    <span class="input-group-text bg-white" style="border-right: none; border-radius: 10px 0 0 10px;"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control" style="border-left: none; border-radius: 0 10px 10px 0;" placeholder="Buscar # ticket, depto, cliente..." value="<?php echo htmlspecialchars($search); ?>" autocomplete="off">
                    <input type="hidden" name="month" value="<?php echo htmlspecialchars($monthFilter); ?>">
                    <?php if ($search !== ''): ?>
                        <a href="?month=<?php echo urlencode($monthFilter); ?>" class="btn btn-outline-secondary" style="margin-left: 6px; border-radius: 10px;"><i class="bi bi-x-lg"></i></a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <!-- Lista de tickets -->
    <div class="tickets-table-wrap">
        <table class="table table-hover tickets-table rpt-desktop-table mb-0" id="ticketsTable">
            <thead class="table-light" style="border-bottom: 2px solid #e2e8f0; background-color: #f8fafc;">
                <tr>
                    <th style="font-weight: 700; color: #475569; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; padding-left: 20px;">Ticket</th>
                    <th class="d-none d-lg-table-cell" style="font-weight: 700; color: #475569; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em;">Departamento</th>
                    <th style="font-weight: 700; color: #475569; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em;">Estado Reporte</th>
                    <th class="d-none d-lg-table-cell" style="font-weight: 700; color: #475569; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em;">Fecha Cierre</th>
                    <th style="width: 120px; text-align: right; font-weight: 700; color: #475569; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; padding-right: 20px;">Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tickets)): ?>
                    <tr>
                        <td colspan="5">
                            <div class="empty-state">
                                <i class="bi bi-inbox" style="font-size: 2rem; opacity: 0.6;"></i>
                                <div class="mt-2">No hay tickets cerrados que requieran reporte.</div>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($tickets as $t): ?>
                        <?php
                        $staffName = trim(($t['staff_first'] ?? '') . ' ' . ($t['staff_last'] ?? ''));
                        if ($staffName === '') $staffName = 'Sin asignar';
                        $closedDate = !empty($t['closed']) ? date('d/m/Y H:i', strtotime($t['closed'])) : 'N/A';
                        $closedDateShort = !empty($t['closed']) ? date('d/m/Y', strtotime($t['closed'])) : 'N/A';
                        $hasReport = (int)($t['has_report'] ?? 0);
                        $isNew = !$hasReport && !in_array((int)$t['id'], $seenIds);
                        $reportUrl = 'reporte_costos.php?ticket_id=' . (int)$t['id'];
                        $viewUrl = 'tickets.php?id=' . (int)$t['id'];
                        ?>
                        <tr class="ticket-row" style="background: #fff; cursor: pointer; transition: all 0.2s;" onclick="if(!event.target.closest('a') && !event.target.closest('button')) window.location='<?php echo $hasReport ? $reportUrl : $viewUrl; ?>';">
                            <td style="vertical-align: middle; padding: 18px 12px 18px 20px;">
                                <div style="display: flex; align-items: baseline; gap: 8px; margin-bottom: 6px;">
                                    <a href="<?php echo $viewUrl; ?>" class="ticket-title" style="font-weight: 800; font-size: 1.05rem; color: #2563eb; text-decoration: none;" onclick="event.stopPropagation();">
                                        <i class="bi bi-hash" style="opacity: 0.5;"></i><?php echo html($t['ticket_number']); ?>
                                    </a>
                                    <?php if ($isNew): ?>
                                        <span class="badge" style="background:#ef4444; color: #fff; font-size: 0.65rem; padding: 4px 6px; letter-spacing: 0.05em; text-transform: uppercase; border-radius: 6px; box-shadow: 0 2px 4px rgba(239, 68, 68, 0.2);">NEW</span>
                                    <?php endif; ?>
                                    <div class="d-md-none text-muted ms-auto" style="font-size:0.75rem; font-weight:600;">
                                        <?php echo html($closedDateShort); ?>
                                    </div>
                                </div>
                                <div class="ticket-subject" style="font-weight: 600; color: #1e293b; font-size: 0.95rem; margin-bottom: 6px; line-height: 1.4; display: block; max-width: 55ch; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; text-transform: none;">
                                    <?php echo html((string)($t['subject'] ?? '')); ?>
                                </div>
                                <div style="display: flex; align-items: center; font-size: 0.8rem; color: #64748b; margin-bottom: 4px;">
                                    <span style="display:inline-flex; align-items:center; gap:5px;">
                                        <i class="bi bi-headset" style="color:#94a3b8;"></i> Asignado a: <strong style="color: #475569; font-weight:600;"><?php echo html($staffName); ?></strong>
                                    </span>
                                </div>
                                <!-- Mobile extra info -->
                                <div class="d-md-none mt-2" style="display:flex; gap:8px; flex-direction:column;">
                                    <div style="font-size: 0.85rem; color: #475569; display:flex; align-items:center; gap:6px;">
                                        <i class="bi bi-building" style="color:#cbd5e1;"></i> <strong><?php echo html($t['department_name']); ?></strong>
                                    </div>
                                    <div style="display:flex; gap:6px; flex-wrap:wrap; margin-top: 2px;">
                                        <?php if ($hasReport): ?>
                                            <span class="chip" style="background: #16a34a15; color: #16a34a; border: 1px solid #16a34a33; font-size:0.7rem; border-radius:6px; padding:3px 8px; font-weight:700;">
                                                <i class="bi bi-check-circle-fill"></i> Completado
                                            </span>
                                        <?php else: ?>
                                            <span class="chip" style="background: #f59e0b15; color: #92400e; border: 1px solid #f59e0b33; font-size:0.7rem; border-radius:6px; padding:3px 8px; font-weight:700;">
                                                <i class="bi bi-exclamation-circle"></i> Pendiente
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="d-none d-lg-table-cell" style="vertical-align: middle;">
                                <span class="chip" style="background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; padding: 6px 14px; font-weight: 700; font-size: 0.8rem; border-radius: 8px;">
                                    <i class="bi bi-building" style="margin-right: 4px;"></i><?php echo html($t['department_name']); ?>
                                </span>
                            </td>
                            <td style="vertical-align: middle;">
                                <div class="d-none d-md-flex flex-column gap-2 align-items-start">
                                    <?php if ($hasReport): ?>
                                        <span class="chip" style="background: #16a34a15; color: #065f46; border: 1px solid #a7f3d0; padding: 6px 14px; font-weight: 700; font-size: 0.8rem; border-radius: 8px;">
                                            <i class="bi bi-check-circle-fill" style="margin-right: 4px;"></i>Completado
                                        </span>
                                    <?php else: ?>
                                        <span class="chip" style="background: #fffbeb; color: #92400e; border: 1px solid #fde68a; padding: 6px 14px; font-weight: 700; font-size: 0.8rem; border-radius: 8px;">
                                            <i class="bi bi-exclamation-circle" style="margin-right: 4px;"></i>Pendiente
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="d-none d-lg-table-cell" style="vertical-align: middle; color: #64748b; font-size: 0.85rem; font-weight: 600;">
                                <div style="display:flex; align-items:center; gap:6px;">
                                    <i class="bi bi-calendar-check" style="color:#94a3b8;"></i>
                                    <?php echo html($closedDate); ?>
                                </div>
                            </td>
                            <td style="vertical-align: middle; text-align: right; padding-right: 20px;">
                                <a href="<?php echo $reportUrl; ?>"
                                   class="btn btn-sm <?php echo $hasReport ? 'btn-outline-primary' : 'btn-primary'; ?>"
                                   style="font-weight: 600; border-radius: 8px; <?php echo $hasReport ? '' : 'background: linear-gradient(135deg, #2563eb, #1d4ed8); border: none;'; ?>"
                                   onclick="event.stopPropagation();">
                                    <i class="bi <?php echo $hasReport ? 'bi-eye' : 'bi-plus-lg'; ?>"></i>
                                    <span class="d-none d-md-inline"><?php echo $hasReport ? 'Ver' : 'Reportar'; ?></span>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Vista móvil: cards -->
    <div class="rpt-card-list">
        <?php if (empty($tickets)): ?>
            <div class="empty-state" style="padding: 40px 20px; text-align:center;">
                <i class="bi bi-inbox" style="font-size: 2rem; opacity: 0.6;"></i>
                <div class="mt-2">No hay tickets cerrados que requieran reporte.</div>
            </div>
        <?php else: ?>
            <?php foreach ($tickets as $t): ?>
            <?php
                $staffName   = trim(($t['staff_first'] ?? '') . ' ' . ($t['staff_last'] ?? ''));
                if ($staffName === '') $staffName = 'Sin asignar';
                $closedDateShort = !empty($t['closed']) ? date('d/m/Y', strtotime($t['closed'])) : 'N/A';
                $hasReport   = (int)($t['has_report'] ?? 0);
                $isNew       = !$hasReport && !in_array((int)$t['id'], $seenIds);
                $reportUrl   = 'reporte_costos.php?ticket_id=' . (int)$t['id'];
                $viewUrl     = 'tickets.php?id=' . (int)$t['id'];
            ?>
            <div class="rpt-card">
                <div class="rpt-card-accent <?php echo $hasReport ? 'done' : ''; ?>"></div>
                <div class="rpt-card-body">
                    <div class="rpt-card-top">
                        <div class="rpt-card-num">
                            <span>#</span><?php echo html($t['ticket_number']); ?>
                            <?php if ($isNew): ?>
                                <span class="badge ms-1" style="background:#ef4444; color:#fff; font-size:0.6rem; padding:3px 6px; letter-spacing:0.05em; text-transform:uppercase; border-radius:5px;">NEW</span>
                            <?php endif; ?>
                        </div>
                        <div style="font-size:0.75rem; color:#94a3b8; font-weight:600;"><?php echo html($closedDateShort); ?></div>
                    </div>
                    <div style="font-size:0.9rem; font-weight:700; color:#1e293b; margin-bottom:10px; line-height:1.35;">
                        <?php echo html((string)($t['subject'] ?? '')); ?>
                    </div>
                    <div class="rpt-card-rows">
                        <div class="rpt-card-row">
                            <i class="bi bi-building"></i>
                            <span class="rpt-label">Depto.</span>
                            <span class="rpt-val"><?php echo html($t['department_name']); ?></span>
                        </div>
                        <div class="rpt-card-row">
                            <i class="bi bi-headset"></i>
                            <span class="rpt-label">Agente</span>
                            <span class="rpt-val"><?php echo html($staffName); ?></span>
                        </div>
                        <div class="rpt-card-row">
                            <i class="bi bi-circle-fill" style="font-size:0.5rem; color:<?php echo $hasReport ? '#16a34a' : '#f59e0b'; ?>;"></i>
                            <span class="rpt-label">Reporte</span>
                            <span class="rpt-val" style="color:<?php echo $hasReport ? '#16a34a' : '#92400e'; ?>;">
                                <?php echo $hasReport ? 'Completado' : 'Pendiente'; ?>
                            </span>
                        </div>
                    </div>
                    <div class="rpt-card-footer">
                        <a href="<?php echo $viewUrl; ?>" class="btn btn-sm btn-outline-secondary" style="border-radius:8px; font-weight:600;">
                            <i class="bi bi-ticket-detailed"></i> Ver Ticket
                        </a>
                        <a href="<?php echo $reportUrl; ?>" class="btn btn-sm <?php echo $hasReport ? 'btn-outline-primary' : 'btn-primary'; ?>" style="border-radius:8px; font-weight:600; <?php echo $hasReport ? '' : 'background:linear-gradient(135deg,#2563eb,#1d4ed8);border:none;'; ?>">
                            <i class="bi <?php echo $hasReport ? 'bi-eye' : 'bi-plus-lg'; ?>"></i>
                            <?php echo $hasReport ? 'Ver Reporte' : 'Reportar'; ?>
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Paginación -->
    <?php if ($totalPages > 1): ?>
    <?php
    $qParam = $search !== '' ? '&q=' . urlencode($search) : '';
    $mParam = '&month=' . urlencode($monthFilter);
    $allParams = $mParam . $qParam;
    ?>
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mt-3">
        <div class="text-muted" style="font-size:0.9rem;">
            Página <?php echo $page; ?> de <?php echo $totalPages; ?> · <?php echo $totalTickets; ?> ticket<?php echo $totalTickets !== 1 ? 's' : ''; ?>
        </div>
        <div class="d-flex gap-2">
            <?php if ($page > 1): ?>
                <a class="btn btn-outline-secondary btn-sm" href="?page=<?php echo $page - 1; ?><?php echo $allParams; ?>">
                    <i class="bi bi-chevron-left"></i> Anterior
                </a>
            <?php else: ?>
                <button class="btn btn-outline-secondary btn-sm" disabled><i class="bi bi-chevron-left"></i> Anterior</button>
            <?php endif; ?>

            <?php
            $range = 2;
            $start = max(1, $page - $range);
            $end   = min($totalPages, $page + $range);
            ?>
            <div class="d-none d-sm-flex gap-1">
                <?php if ($start > 1): ?>
                    <a href="?page=1<?php echo $allParams; ?>" class="btn btn-sm btn-outline-secondary">1</a>
                    <?php if ($start > 2): ?><span class="text-muted small px-1" style="align-self:center;">&hellip;</span><?php endif; ?>
                <?php endif; ?>
                <?php for ($i = $start; $i <= $end; $i++): ?>
                    <a href="?page=<?php echo $i; ?><?php echo $allParams; ?>"
                       class="btn btn-sm <?php echo $i === $page ? 'btn-primary' : 'btn-outline-secondary'; ?>"
                       <?php echo $i === $page ? 'style="background:linear-gradient(135deg,#2563eb,#1d4ed8);border:none;"' : ''; ?>>
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                <?php if ($end < $totalPages): ?>
                    <?php if ($end < $totalPages - 1): ?><span class="text-muted small px-1" style="align-self:center;">&hellip;</span><?php endif; ?>
                    <a href="?page=<?php echo $totalPages; ?><?php echo $allParams; ?>" class="btn btn-sm btn-outline-secondary"><?php echo $totalPages; ?></a>
                <?php endif; ?>
            </div>

            <?php if ($page < $totalPages): ?>
                <a class="btn btn-outline-secondary btn-sm" href="?page=<?php echo $page + 1; ?><?php echo $allParams; ?>">
                    Siguiente <i class="bi bi-chevron-right"></i>
                </a>
            <?php else: ?>
                <button class="btn btn-outline-secondary btn-sm" disabled>Siguiente <i class="bi bi-chevron-right"></i></button>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
(function() {
    document.getElementById('monthSelect').addEventListener('change', function() {
        var url = new URL(window.location.href);
        url.searchParams.set('month', this.value);
        url.searchParams.delete('page');
        window.location.href = url.toString();
    });
})();
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout/layout.php';
