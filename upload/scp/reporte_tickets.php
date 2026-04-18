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

// Fetch tickets
$tickets = [];
if ($statusIdClosed > 0) {
    if ($hasReportsTable) {
        $query = "SELECT t.id, t.ticket_number, t.subject, t.closed, t.staff_id, 
                         d.name as department_name,
                         s.firstname as staff_first, s.lastname as staff_last,
                         IF(r.id IS NOT NULL, 1, 0) as has_report
                  FROM tickets t
                  JOIN departments d ON t.dept_id = d.id AND d.requires_report = 1
                  LEFT JOIN staff s ON t.staff_id = s.id
                  LEFT JOIN ticket_reports r ON r.ticket_id = t.id
                  WHERE t.empresa_id = ? AND t.status_id = ?
                  ORDER BY t.closed DESC, t.id DESC";
    } else {
        $query = "SELECT t.id, t.ticket_number, t.subject, t.closed, t.staff_id, 
                         d.name as department_name,
                         s.firstname as staff_first, s.lastname as staff_last,
                         0 as has_report
                  FROM tickets t
                  JOIN departments d ON t.dept_id = d.id AND d.requires_report = 1
                  LEFT JOIN staff s ON t.staff_id = s.id
                  WHERE t.empresa_id = ? AND t.status_id = ?
                  ORDER BY t.closed DESC, t.id DESC";
    }
    
    $stmt = $mysqli->prepare($query);
    if ($stmt) {
        $stmt->bind_param('ii', $eid, $statusIdClosed);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $tickets[] = $row;
        }
    }
}

ob_start();
?>
<style>
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
    background: linear-gradient(90deg, #2563eb 0%, #7c3aed 100%);
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
</style>

<div class="tickets-shell">
    <div class="tickets-header">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2">
            <div>
                <h1>Reportes de Tickets</h1>
                <div class="sub">Tickets cerrados de departamentos que requieren reporte</div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="badge bg-info"><?php echo (int)count($tickets); ?> Total</span>
            </div>
        </div>
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
    <div class="tickets-panel p-0 rpt-desktop-table">
        <div class="tickets-table-wrap">
            <table class="tickets-table table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 border-bottom-0"># Ticket</th>
                        <th class="border-bottom-0">Departamento</th>
                        <th class="border-bottom-0">Técnico asignado</th>
                        <th class="border-bottom-0">Fecha de cierre</th>
                        <th class="border-bottom-0">Estado del reporte</th>
                        <th class="text-end pe-3 border-bottom-0">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $t): ?>
                        <?php 
                        $staffName = trim(($t['staff_first'] ?? '') . ' ' . ($t['staff_last'] ?? ''));
                        if ($staffName === '') $staffName = 'Sin asignar';
                        $closedDate = !empty($t['closed']) ? date('d/m/Y H:i', strtotime($t['closed'])) : 'N/A';
                        $hasReport = (int)($t['has_report'] ?? 0);
                        ?>
                        <tr class="ticket-row">
                            <td class="ps-3"><a href="tickets.php?id=<?php echo (int) $t['id']; ?>" class="ticket-title"><?php echo htmlspecialchars($t['ticket_number']); ?></a></td>
                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($t['department_name']); ?></span></td>
                            <td><?php echo htmlspecialchars($staffName); ?></td>
                            <td class="ticket-meta"><?php echo htmlspecialchars($closedDate); ?></td>
                            <td>
                                <?php if ($hasReport): ?>
                                    <span class="badge bg-success">Completado</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">Pendiente</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-3">
                                <a href="reporte_costos.php?ticket_id=<?php echo (int) $t['id']; ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi <?php echo $hasReport ? 'bi-eye' : 'bi-plus-circle'; ?>"></i> <?php echo $hasReport ? 'Ver' : 'Registrar'; ?>
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
            ?>
            <div class="rpt-card">
                <div class="rpt-card-accent <?php echo $hasReport ? 'done' : ''; ?>"></div>
                <div class="rpt-card-body">
                    <div class="rpt-card-top">
                        <div>
                            <div class="rpt-card-num">
                                <span>#</span><?php echo htmlspecialchars($t['ticket_number']); ?>
                            </div>
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
                           style="<?php echo $hasReport ? '' : 'background: linear-gradient(135deg,#2563eb,#7c3aed); border:none;'; ?>">
                            <i class="bi <?php echo $hasReport ? 'bi-eye' : 'bi-plus-circle'; ?>"></i>
                            <?php echo $hasReport ? 'Ver Reporte' : 'Registrar Reporte'; ?>
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();

// Renderizar layout principal (header + sidebar estáticos)
require __DIR__ . '/layout/layout.php';
