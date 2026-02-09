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
$currentRoute = 'roles';

$roles = [];
$res = $mysqli->query("SELECT role, COUNT(*) AS total, SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active_total FROM staff GROUP BY role ORDER BY role");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $roles[] = $row;
    }
}

$rolesCount = count($roles);
$agentsTotal = 0;
foreach ($roles as $r) {
    $agentsTotal += (int)($r['total'] ?? 0);
}

$content = '
<div class="settings-hero">
    <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
        <div class="d-flex align-items-center gap-3">
            <span class="settings-hero-icon"><i class="bi bi-shield-lock"></i></span>
            <div>
                <h1>Roles</h1>
                <p>Gestión de roles y permisos</p>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="badge bg-info">' . (int)$rolesCount . ' Roles</span>
            <span class="badge bg-secondary">' . (int)$agentsTotal . ' Agentes</span>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card settings-card">
            <div class="card-header">
                <div class="d-flex align-items-center justify-content-between">
                    <h5 class="mb-0">Lista de Roles</h5>
                    <div class="text-muted small">Basado en el campo <code>staff.role</code></div>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Rol</th>
                                <th class="text-center">Agentes</th>
                                <th class="text-center">Activos</th>
                            </tr>
                        </thead>
                        <tbody>';

if (empty($roles)) {
    $content .= '<tr><td colspan="3" class="text-center text-muted py-4">No hay roles para mostrar.</td></tr>';
} else {
    foreach ($roles as $r) {
        $role = trim((string)($r['role'] ?? ''));
        if ($role === '') $role = '(vacío)';
        $total = (int)($r['total'] ?? 0);
        $active = (int)($r['active_total'] ?? 0);
        $content .= '<tr>';
        $content .= '<td><span class="badge bg-info text-dark">' . htmlspecialchars($role, ENT_QUOTES, 'UTF-8') . '</span></td>';
        $content .= '<td class="text-center"><strong>' . $total . '</strong></td>';
        $content .= '<td class="text-center">' . ($active > 0 ? '<span class="badge bg-success">' . $active . '</span>' : '<span class="text-muted">0</span>') . '</td>';
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
