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

    <div class="tickets-panel p-0">
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
                    <?php if (empty($tickets)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">
                                <i class="bi bi-inbox fs-4 d-block mb-2"></i>
                                No hay tickets cerrados que requieran reporte.
                            </td>
                        </tr>
                    <?php else: ?>
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
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="scp-avatar-sm" style="width: 28px; height: 28px; font-size: 0.8rem;"><?php echo strtoupper(substr($staffName, 0, 1)); ?></div>
                                        <span><?php echo htmlspecialchars($staffName); ?></span>
                                    </div>
                                </td>
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
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();

// Renderizar layout principal (header + sidebar estáticos)
require __DIR__ . '/layout/layout.php';
