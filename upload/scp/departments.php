<?php
require_once '../../config.php';
require_once '../../includes/helpers.php';
require_once '../../includes/Auth.php';

if (!isset($_SESSION['staff_id'])) {
    header('Location: login.php');
    exit;
}

requireLogin('agente');
$staff = getCurrentUser();
$currentRoute = 'departments';

$departments = [];
$sql = "
    SELECT
        d.id,
        d.name,
        d.is_active,
        COUNT(DISTINCT s.id) AS staff_total,
        COUNT(DISTINCT t.id) AS ticket_total
    FROM departments d
    LEFT JOIN staff s ON s.dept_id = d.id
    LEFT JOIN tickets t ON t.dept_id = d.id
    GROUP BY d.id, d.name, d.is_active
    ORDER BY d.name
";
$res = $mysqli->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $departments[] = $row;
    }
}

$activeCount = 0;
$inactiveCount = 0;
foreach ($departments as $d) {
    if ((int)($d['is_active'] ?? 0) === 1) $activeCount++;
    else $inactiveCount++;
}

$content = '
<div class="settings-hero">
    <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
        <div class="d-flex align-items-center gap-3">
            <span class="settings-hero-icon"><i class="bi bi-diagram-3"></i></span>
            <div>
                <h1>Departamentos</h1>
                <p>Gestión de departamentos</p>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="badge bg-success">' . (int)$activeCount . ' Activos</span>
            <span class="badge bg-secondary">' . (int)$inactiveCount . ' Inactivos</span>
            <span class="badge bg-info">' . (int)count($departments) . ' Total</span>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card settings-card">
            <div class="card-header">
                <div class="d-flex align-items-center justify-content-between">
                    <h5 class="mb-0">Lista de Departamentos</h5>
                    <div class="text-muted small">' . count($departments) . ' registro(s)</div>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Departamento</th>
                                <th class="text-center">Estado</th>
                                <th class="text-center">Agentes</th>
                                <th class="text-center">Tickets</th>
                            </tr>
                        </thead>
                        <tbody>';

if (empty($departments)) {
    $content .= '<tr><td colspan="4" class="text-center text-muted py-4">No hay departamentos para mostrar.</td></tr>';
} else {
    foreach ($departments as $d) {
        $name = (string)($d['name'] ?? '');
        $active = (int)($d['is_active'] ?? 0) === 1;
        $staffTotal = (int)($d['staff_total'] ?? 0);
        $ticketTotal = (int)($d['ticket_total'] ?? 0);
        $content .= '<tr>';
        $content .= '<td><strong>' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</strong><br><small class="text-muted">#' . (int)($d['id'] ?? 0) . '</small></td>';
        $content .= '<td class="text-center">' . ($active ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-secondary">Inactivo</span>') . '</td>';
        $content .= '<td class="text-center"><strong>' . $staffTotal . '</strong></td>';
        $content .= '<td class="text-center"><strong>' . $ticketTotal . '</strong></td>';
        $content .= '</tr>';
    }
}

$content .= '
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
';

require_once 'layout_admin.php';
?>
