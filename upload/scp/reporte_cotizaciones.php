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
$eid = empresaId();

$currentRoute = 'reporte_cotizaciones';

// Filtro por Mes (Default: Mes actual)
$monthFilter = trim($_GET['month'] ?? date('Y-m'));
if ($monthFilter !== 'all' && !preg_match('/^\d{4}-\d{2}$/', $monthFilter)) {
    $monthFilter = date('Y-m');
}

$statusFilter = trim($_GET['status'] ?? '');

$quotes = [];
$where = ["q.empresa_id = ?"];
$params = [$eid];
$types = "i";

if ($monthFilter !== 'all') {
    $where[] = "DATE_FORMAT(q.created_at, '%Y-%m') = ?";
    $params[] = $monthFilter;
    $types .= "s";
}

if ($statusFilter !== '') {
    $where[] = "q.status = ?";
    $params[] = $statusFilter;
    $types .= "s";
}

$whereSql = implode(' AND ', $where);

$sql = "SELECT q.*, 
        CONCAT(u.firstname, ' ', u.lastname) as user_name,
        CONCAT(s.firstname, ' ', s.lastname) as staff_name 
        FROM quotes q 
        LEFT JOIN users u ON q.user_id = u.id 
        LEFT JOIN staff s ON q.staff_id = s.id 
        WHERE $whereSql 
        ORDER BY q.created_at DESC";

$stmt = $mysqli->prepare($sql);
if ($stmt) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $quotes[] = $row;
    }
}

// Estadísticas rápidas
$stats = [
    'total' => count($quotes),
    'accepted' => 0,
    'rejected' => 0,
    'pending' => 0,
    'draft' => 0,
    'amount_accepted' => 0
];
foreach ($quotes as $q) {
    $stats[$q['status']]++;
    if ($q['status'] === 'accepted') {
        $stats['amount_accepted'] += $q['amount'];
    }
}

ob_start();
?>

<div class="tickets-shell">
    <div class="tickets-header mb-4">
        <h1>Reporte de Cotizaciones</h1>
        <div class="sub">Métricas y listado de cotizaciones</div>
    </div>

    <!-- Filtros -->
    <div class="card shadow-sm border-0 mb-4" style="border-radius: 12px;">
        <div class="card-body p-4">
            <form method="GET" action="reporte_cotizaciones.php" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label fw-bold">Mes</label>
                    <input type="month" name="month" class="form-control" value="<?php echo $monthFilter === 'all' ? '' : html($monthFilter); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Estado</label>
                    <select name="status" class="form-select">
                        <option value="">Todos</option>
                        <option value="draft" <?php echo $statusFilter === 'draft' ? 'selected' : ''; ?>>Borrador</option>
                        <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pendiente</option>
                        <option value="waiting_oc" <?php echo $statusFilter === 'waiting_oc' ? 'selected' : ''; ?>>En espera O/C</option>
                        <option value="accepted" <?php echo $statusFilter === 'accepted' ? 'selected' : ''; ?>>Aceptada</option>
                        <option value="rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>Rechazada</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary" style="background: #3b82f6; border-color: #3b82f6;">Filtrar</button>
                    <a href="reporte_cotizaciones.php?month=all" class="btn btn-light border ms-1">Limpiar</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Stats -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm" style="border-radius: 12px; background: #f8fafc;">
                <div class="card-body text-center">
                    <div class="text-muted text-uppercase" style="font-size: 0.8rem; letter-spacing: 1px;">Total</div>
                    <div class="fs-2 fw-bold text-dark"><?php echo $stats['total']; ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm" style="border-radius: 12px; background: #ecfdf5;">
                <div class="card-body text-center">
                    <div class="text-success text-uppercase" style="font-size: 0.8rem; letter-spacing: 1px;">Aceptadas</div>
                    <div class="fs-2 fw-bold text-success"><?php echo $stats['accepted']; ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm" style="border-radius: 12px; background: #fffbeb;">
                <div class="card-body text-center">
                    <div class="text-warning text-uppercase" style="font-size: 0.8rem; letter-spacing: 1px;">Pendientes</div>
                    <div class="fs-2 fw-bold text-warning" style="color: #d97706 !important;"><?php echo $stats['pending']; ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm" style="border-radius: 12px; background: #fef2f2;">
                <div class="card-body text-center">
                    <div class="text-danger text-uppercase" style="font-size: 0.8rem; letter-spacing: 1px;">Rechazadas</div>
                    <div class="fs-2 fw-bold text-danger"><?php echo $stats['rejected']; ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="alert alert-success d-flex align-items-center">
        <i class="bi bi-cash-stack fs-4 me-3"></i>
        <div>
            <strong>Monto Total Aceptado en este periodo:</strong> $<?php echo number_format($stats['amount_accepted'], 2); ?>
        </div>
    </div>

    <!-- Tabla -->
    <div class="card shadow-sm border-0" style="border-radius: 12px;">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">ID / Título</th>
                        <th>Cliente</th>
                        <th>Agente</th>
                        <th>Monto</th>
                        <th>Estado</th>
                        <th class="pe-4">Fecha</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($quotes)): ?>
                        <tr><td colspan="6" class="text-center p-4 text-muted">No hay datos en este reporte.</td></tr>
                    <?php else: ?>
                        <?php foreach ($quotes as $q): ?>
                            <tr>
                                <td class="ps-4">
                                    <a href="cotizaciones.php?id=<?php echo $q['id']; ?>" class="fw-bold text-decoration-none">#<?php echo $q['id']; ?></a>
                                    <div class="text-muted" style="font-size: 0.85rem;"><?php echo html($q['title']); ?></div>
                                </td>
                                <td><?php echo html($q['user_name'] ?: 'N/A'); ?></td>
                                <td><?php echo html($q['staff_name'] ?: 'N/A'); ?></td>
                                <td>$<?php echo number_format($q['amount'], 2); ?></td>
                                <td>
                                    <?php
                                    $statusColors = [
                                        'draft' => ['bg' => '#f1f5f9', 'color' => '#475569', 'label' => 'Borrador'],
                                        'pending' => ['bg' => '#fffbeb', 'color' => '#d97706', 'label' => 'Pendiente'],
                                        'waiting_oc' => ['bg' => '#fef3c7', 'color' => '#b45309', 'label' => 'En espera O/C'],
                                        'accepted' => ['bg' => '#dcfce7', 'color' => '#166534', 'label' => 'Aceptada'],
                                        'rejected' => ['bg' => '#fee2e2', 'color' => '#991b1b', 'label' => 'Rechazada']
                                    ];
                                    $st = $statusColors[$q['status']] ?? $statusColors['draft'];
                                    ?>
                                    <span class="badge" style="background-color: <?php echo $st['bg']; ?>; color: <?php echo $st['color']; ?>; border: 1px solid <?php echo $st['color']; ?>33; padding: 4px 8px; border-radius: 6px;">
                                        <?php echo $st['label']; ?>
                                    </span>
                                </td>
                                <td class="pe-4 text-muted" style="font-size: 0.85rem;"><?php echo date('d/m/Y', strtotime($q['created_at'])); ?></td>
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
require __DIR__ . '/layout/layout.php';
