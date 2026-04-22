<?php
// Módulo: Directorio de agentes
// Muestra lista de agentes con búsqueda, filtros y estadísticas

// Parámetros de búsqueda y filtros
$search = trim($_GET['q'] ?? '');
$deptFilter = isset($_GET['did']) && is_numeric($_GET['did']) ? (int)$_GET['did'] : 0;
$sort = strtolower($_GET['sort'] ?? 'name');
$order = strtoupper($_GET['order'] ?? 'ASC');
$pageNum = max(1, (int)($_GET['p'] ?? 1));
$perPage = 20;

$eid = empresaId();

$deptHasEmpresa = false;
try {
    $c = $mysqli->query("SHOW COLUMNS FROM departments LIKE 'empresa_id'");
    $deptHasEmpresa = ($c && $c->num_rows > 0);
} catch (Throwable $e) {
}

// Validar ordenamiento
$validSorts = ['name', 'email', 'dept', 'role', 'status', 'created', 'last_login'];
if (!in_array($sort, $validSorts)) {
    $sort = 'name';
}
$validOrders = ['ASC', 'DESC'];
if (!in_array($order, $validOrders)) {
    $order = 'ASC';
}

// Construir query base
$hasStaffDepartmentsTable = false;
if (isset($mysqli) && $mysqli) {
    try {
        $rt = $mysqli->query("SHOW TABLES LIKE 'staff_departments'");
        $hasStaffDepartmentsTable = ($rt && $rt->num_rows > 0);
    } catch (Throwable $e) {
        $hasStaffDepartmentsTable = false;
    }
}

if ($hasStaffDepartmentsTable) {
    $sql = "
        SELECT
            s.id,
            s.username,
            s.email,
            s.firstname,
            s.lastname,
            s.role,
            s.is_active,
            s.created,
            s.last_login,
            COALESCE(dp.name, MIN(d.name)) AS dept_name,
            COUNT(DISTINCT t.id) as total_tickets,
            COUNT(DISTINCT CASE WHEN t.status_id = (SELECT id FROM ticket_status WHERE name = 'Abierto' LIMIT 1) THEN t.id END) as open_tickets
        FROM staff s
        LEFT JOIN departments dp ON dp.id = s.dept_id
        LEFT JOIN staff_departments sd ON sd.staff_id = s.id
        LEFT JOIN departments d ON d.id = sd.dept_id
        LEFT JOIN tickets t ON t.staff_id = s.id AND t.empresa_id = ?
        WHERE s.empresa_id = ? AND s.role != 'superadmin'
    ";
} else {
    $sql = "
        SELECT
            s.id,
            s.username,
            s.email,
            s.firstname,
            s.lastname,
            s.role,
            s.is_active,
            s.created,
            s.last_login,
            d.id as dept_id,
            d.name as dept_name,
            COUNT(DISTINCT t.id) as total_tickets,
            COUNT(DISTINCT CASE WHEN t.status_id = (SELECT id FROM ticket_status WHERE name = 'Abierto' LIMIT 1) THEN t.id END) as open_tickets
        FROM staff s
        LEFT JOIN departments d ON s.dept_id = d.id
        LEFT JOIN tickets t ON t.staff_id = s.id AND t.empresa_id = ?
        WHERE s.empresa_id = ? AND s.role != 'superadmin'
    ";
}

$params = [];
$types = 'ii';
$params[] = $eid;
$params[] = $eid;

// Aplicar filtro de búsqueda
if ($search) {
    if (is_numeric($search)) {
        $sql .= " AND s.id = ?";
        $params[] = (int)$search;
        $types .= 'i';
    } elseif (strpos($search, '@') !== false && filter_var($search, FILTER_VALIDATE_EMAIL)) {
        $sql .= " AND s.email = ?";
        $params[] = $search;
        $types .= 's';
    } else {
        $sql .= " AND (
            s.firstname LIKE ? OR
            s.lastname LIKE ? OR
            s.email LIKE ? OR
            s.username LIKE ?
        )";
        $searchTerm = '%' . $search . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= 'ssss';
    }
}

// Aplicar filtro por departamento
if ($deptFilter > 0) {
    if ($hasStaffDepartmentsTable) {
        $sql .= " AND EXISTS (SELECT 1 FROM staff_departments sd2 WHERE sd2.staff_id = s.id AND sd2.dept_id = ?)";
        $params[] = $deptFilter;
        $types .= 'i';
    } else {
        $sql .= " AND s.dept_id = ?";
        $params[] = $deptFilter;
        $types .= 'i';
    }
}

// Conteo total
$countSql = "SELECT COUNT(*) AS total FROM staff s WHERE s.empresa_id = ? AND s.role != 'superadmin'";
$countParams = [$eid];
$countTypes = 'i';
if ($search) {
    if (is_numeric($search)) {
        $countSql .= " AND s.id = ?";
        $countParams[] = (int)$search;
        $countTypes .= 'i';
    } elseif (strpos($search, '@') !== false && filter_var($search, FILTER_VALIDATE_EMAIL)) {
        $countSql .= " AND s.email = ?";
        $countParams[] = $search;
        $countTypes .= 's';
    } else {
        $countSql .= " AND (s.firstname LIKE ? OR s.lastname LIKE ? OR s.email LIKE ? OR s.username LIKE ?)";
        $searchTerm2 = '%' . $search . '%';
        $countParams[] = $searchTerm2;
        $countParams[] = $searchTerm2;
        $countParams[] = $searchTerm2;
        $countParams[] = $searchTerm2;
        $countTypes .= 'ssss';
    }
}
if ($deptFilter > 0) {
    if ($hasStaffDepartmentsTable) {
        $countSql .= " AND EXISTS (SELECT 1 FROM staff_departments sd2 WHERE sd2.staff_id = s.id AND sd2.dept_id = ?)";
        $countParams[] = $deptFilter;
        $countTypes .= 'i';
    } else {
        $countSql .= " AND s.dept_id = ?";
        $countParams[] = $deptFilter;
        $countTypes .= 'i';
    }
}
$totalRows = 0;
$stmtCount = $mysqli->prepare($countSql);
if ($stmtCount) {
    $stmtCount->bind_param($countTypes, ...$countParams);
    if ($stmtCount->execute()) {
        $totalRows = (int)($stmtCount->get_result()->fetch_assoc()['total'] ?? 0);
    }
}
$totalPages = $totalRows > 0 ? (int)ceil($totalRows / $perPage) : 1;
if ($pageNum > $totalPages) $pageNum = $totalPages;
$offset = ($pageNum - 1) * $perPage;

// Agrupar
if ($hasStaffDepartmentsTable) {
    $sql .= " GROUP BY s.id, s.username, s.email, s.firstname, s.lastname, s.role, s.is_active, s.created, s.last_login";
} else {
    $sql .= " GROUP BY s.id, s.username, s.email, s.firstname, s.lastname, s.role, s.is_active, s.created, s.last_login, d.id, d.name";
}

// Ordenamiento
$sortColumns = [
    'name' => 's.firstname, s.lastname',
    'email' => 's.email',
    'dept' => 'dept_name',
    'role' => 's.role',
    'status' => 's.is_active',
    'created' => 's.created',
    'last_login' => 's.last_login'
];
$orderBy = $sortColumns[$sort] ?? 's.firstname, s.lastname';
$sql .= " ORDER BY $orderBy $order";

$sql .= " LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;
$types .= 'ii';

$stmt = $mysqli->prepare($sql);
if ($stmt) {
    if (!empty($params) && !empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
}
$result = $stmt->get_result();
$agents = [];
while ($row = $result->fetch_assoc()) {
    $agents[] = $row;
}

// Lista de departamentos
$deptSql = "SELECT id, name FROM departments WHERE is_active = 1";
$deptTypes = '';
$deptParams = [];
if ($deptHasEmpresa) {
    $deptSql .= " AND empresa_id = ?";
    $deptTypes = 'i';
    $deptParams[] = $eid;
}
$deptSql .= " ORDER BY name";

$deptStmt = $mysqli->prepare($deptSql);
if ($deptStmt && $deptTypes !== '') {
    $deptStmt->bind_param($deptTypes, ...$deptParams);
}
$deptStmt->execute();
$deptResult = $deptStmt->get_result();
$departments = [];
while ($dept = $deptResult->fetch_assoc()) {
    $departments[] = $dept;
}

// Helper badges
$roleBadges = [
    'admin' => ['bg' => 'bg-danger', 'label' => 'Admin'],
    'supervisor' => ['bg' => 'bg-warning text-dark', 'label' => 'Supervisor'],
    'agent' => ['bg' => 'bg-info text-dark', 'label' => 'Agente'],
];

// Generar URL para ordenamiento
$baseUrl = 'directory.php?';
$queryParams = [];
if ($search) $queryParams['q'] = $search;
if ($deptFilter) $queryParams['did'] = $deptFilter;
$currentSort = $sort;
$currentOrder = $order;
$nextOrder = $order === 'ASC' ? 'DESC' : 'ASC';

$sortIcons = [];
foreach ($validSorts as $sKey) {
    $sortIcons[$sKey] = 'bi-arrow-down-up';
    if ($currentSort === $sKey) {
        $sortIcons[$sKey] = $currentOrder === 'ASC' ? 'bi-arrow-up' : 'bi-arrow-down';
    }
}

function sortLink($field, $label, $queryParams, $currentSort, $nextOrder) {
    $params = array_merge($queryParams, ['sort' => $field, 'order' => ($currentSort === $field ? $nextOrder : 'ASC')]);
    return '<a href="directory.php?' . http_build_query($params) . '" class="text-decoration-none" style="color:inherit;">' . $label . '</a>';
}

$showStart = ($totalRows > 0) ? ($offset + 1) : 0;
$showEnd = min($offset + count($agents), $totalRows);

$basePaging = $queryParams;
$basePaging['sort'] = $currentSort;
$basePaging['order'] = $currentOrder;
?>

<style>
/* ── Header azul difuminado profesional ── */
.tickets-shell .tickets-header {
    background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 55%, #0ea5e9 100%);
    color: #fff;
    border-radius: 14px;
    padding: 28px 22px;
    margin-bottom: 20px;
    box-shadow: 0 8px 24px rgba(2, 6, 23, 0.15);
}
.tickets-shell .tickets-header h1 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 800;
    letter-spacing: -0.01em;
}
.tickets-shell .tickets-header .sub {
    margin-top: 6px;
    opacity: 0.92;
    font-size: 0.95rem;
    font-weight: 500;
}

/* ── Toolbar responsive ── */
@media (max-width: 576px) {
    .tickets-toolbar {
        flex-direction: column !important;
        align-items: stretch !important;
    }
    .tickets-filters,
    .tickets-search {
        width: 100% !important;
        max-width: 100% !important;
        min-width: auto !important;
    }
    .tickets-filters .form-select {
        width: 100%;
    }
}

/* ── Mobile table cards ── */
@media (max-width: 767px) {
    #agentsTable thead { display: none; }
    #agentsTable tbody tr {
        display: block;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 14px 16px;
        margin-bottom: 10px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    }
    #agentsTable tbody td {
        display: block;
        border: none;
        padding: 0 !important;
        width: 100% !important;
    }
    #agentsTable tbody td:last-child {
        text-align: right;
        margin-top: 10px;
    }
    .agent-mobile-meta {
        display: flex !important;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 10px;
    }
    .agent-mobile-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        border-radius: 8px;
        background: #eff6ff;
        color: #2563eb;
        text-decoration: none;
    }
}
@media (min-width: 768px) {
    .agent-mobile-meta { display: none !important; }
    .agent-mobile-action { display: none !important; }
}
</style>

<div class="tickets-shell">
    <div class="tickets-header">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2">
            <div>
                <h1>Directorio de Agentes</h1>
                <div class="sub"><?php echo (int)$totalRows; ?> agente<?php echo $totalRows !== 1 ? 's' : ''; ?> encontrado<?php echo $totalRows !== 1 ? 's' : ''; ?></div>
            </div>
        </div>
    </div>

    <!-- Toolbar: filtros y búsqueda -->
    <div class="tickets-panel" style="margin-bottom: 16px;">
        <div class="tickets-toolbar">
            <div class="tickets-filters">
                <select name="did" class="form-select form-select-sm" id="deptSelect" style="min-width: 200px; font-weight: 600; color: #475569;">
                    <option value="0">Todos los departamentos</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo $dept['id']; ?>" <?php echo $deptFilter == $dept['id'] ? 'selected' : ''; ?>><?php echo html($dept['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="tickets-search">
                <form method="GET" action="" class="input-group">
                    <span class="input-group-text bg-white" style="border-right: none; border-radius: 10px 0 0 10px;"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control" style="border-left: none; border-radius: 0;" placeholder="Buscar nombre, email o username..." value="<?php echo html($search); ?>" autocomplete="off">
                    <?php if ($deptFilter > 0): ?><input type="hidden" name="did" value="<?php echo (int)$deptFilter; ?>"><?php endif; ?>
                    <?php if ($currentSort !== 'name' || $currentOrder !== 'ASC'): ?>
                        <input type="hidden" name="sort" value="<?php echo html($currentSort); ?>">
                        <input type="hidden" name="order" value="<?php echo html($currentOrder); ?>">
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary" style="border-radius: 0 10px 10px 0; background: linear-gradient(135deg,#2563eb,#1d4ed8); border: none;"><i class="bi bi-search"></i></button>
                    <?php if ($search !== '' || $deptFilter > 0): ?>
                        <a href="directory.php" class="btn btn-outline-secondary" style="margin-left: 6px; border-radius: 10px;"><i class="bi bi-x-lg"></i></a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <!-- Lista de agentes -->
    <div class="tickets-table-wrap">
        <table class="table table-hover tickets-table mb-0" id="agentsTable">
            <thead class="table-light" style="border-bottom: 2px solid #e2e8f0; background-color: #f8fafc;">
                <tr>
                    <th style="font-weight: 700; color: #475569; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; padding-left: 20px;">
                        <?php echo sortLink('name', 'Agente', $queryParams, $currentSort, $nextOrder); ?>
                        <?php if ($currentSort === 'name'): ?> <i class="bi <?php echo $currentOrder === 'ASC' ? 'bi-arrow-up' : 'bi-arrow-down'; ?>" style="font-size:0.7rem; color:#2563eb;"></i><?php endif; ?>
                    </th>
                    <th class="d-none d-lg-table-cell" style="font-weight: 700; color: #475569; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em;">
                        <?php echo sortLink('dept', 'Departamento', $queryParams, $currentSort, $nextOrder); ?>
                        <?php if ($currentSort === 'dept'): ?> <i class="bi <?php echo $currentOrder === 'ASC' ? 'bi-arrow-up' : 'bi-arrow-down'; ?>" style="font-size:0.7rem; color:#2563eb;"></i><?php endif; ?>
                    </th>
                    <th class="d-none d-md-table-cell" style="font-weight: 700; color: #475569; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em;">
                        <?php echo sortLink('role', 'Rol', $queryParams, $currentSort, $nextOrder); ?>
                        <?php if ($currentSort === 'role'): ?> <i class="bi <?php echo $currentOrder === 'ASC' ? 'bi-arrow-up' : 'bi-arrow-down'; ?>" style="font-size:0.7rem; color:#2563eb;"></i><?php endif; ?>
                    </th>
                    <th style="font-weight: 700; color: #475569; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em;">Estado</th>
                    <th class="d-none d-lg-table-cell" style="font-weight: 700; color: #475569; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em;">Tickets</th>
                    <th class="d-none d-md-table-cell" style="font-weight: 700; color: #475569; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em;">
                        <?php echo sortLink('last_login', 'Último acceso', $queryParams, $currentSort, $nextOrder); ?>
                        <?php if ($currentSort === 'last_login'): ?> <i class="bi <?php echo $currentOrder === 'ASC' ? 'bi-arrow-up' : 'bi-arrow-down'; ?>" style="font-size:0.7rem; color:#2563eb;"></i><?php endif; ?>
                    </th>
                    <th style="width: 50px; text-align: right; font-weight: 700; color: #475569; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; padding-right: 20px;"></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($agents)): ?>
                    <tr>
                        <td colspan="7">
                            <div class="empty-state">
                                <i class="bi bi-people" style="font-size: 2rem; opacity: 0.6;"></i>
                                <div class="mt-2">No se encontraron agentes.</div>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($agents as $agent): ?>
                        <?php
                        $fullName = trim((string)($agent['firstname'] ?? '') . ' ' . (string)($agent['lastname'] ?? ''));
                        if ($fullName === '') $fullName = (string)($agent['email'] ?? 'Agente');
                        $email = (string)($agent['email'] ?? '');
                        $username = (string)($agent['username'] ?? '');
                        $deptName = (string)($agent['dept_name'] ?? '');
                        $isActive = (int)($agent['is_active'] ?? 0) === 1;
                        $totalTickets = (int)($agent['total_tickets'] ?? 0);
                        $openTickets = (int)($agent['open_tickets'] ?? 0);
                        $role = (string)($agent['role'] ?? '');

                        $parts = preg_split('/\s+/', trim($fullName));
                        $i1 = strtoupper((string)($parts[0][0] ?? ''));
                        $i2 = '';
                        if (count($parts) > 1) {
                            $i2 = strtoupper((string)($parts[1][0] ?? ''));
                        } elseif (strlen($fullName) > 1) {
                            $i2 = strtoupper(substr($fullName, 1, 1));
                        }
                        $initials = trim($i1 . $i2);
                        if ($initials === '') $initials = 'A';

                        $roleInfo = $roleBadges[$role] ?? ['bg' => 'bg-secondary', 'label' => ($role !== '' ? ucfirst($role) : 'Agente')];
                        $avatarColors = ['#2563eb','#7c3aed','#db2777','#ea580c','#16a34a','#0891b2'];
                        $avatarColor = $avatarColors[($agent['id'] ?? 0) % count($avatarColors)];
                        ?>
                        <tr class="ticket-row" style="background: #fff; cursor: default; transition: all 0.2s;">
                            <td style="vertical-align: middle; padding: 16px 12px 16px 20px;">
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <div style="width: 40px; height: 40px; border-radius: 50%; background: <?php echo html($avatarColor); ?>; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; font-weight: 700; flex-shrink: 0; letter-spacing: 0.02em;">
                                        <?php echo html($initials); ?>
                                    </div>
                                    <div style="min-width: 0; flex: 1;">
                                        <div style="font-weight: 700; font-size: 0.95rem; color: #1e293b;"><?php echo html($fullName); ?></div>
                                        <div style="font-size: 0.8rem; color: #64748b; display: flex; align-items: center; gap: 4px; margin-top: 2px; flex-wrap: wrap;">
                                            <span>@<?php echo html($username); ?></span>
                                            <?php if ($email !== ''): ?>
                                                <span style="color:#cbd5e1;">·</span>
                                                <a href="mailto:<?php echo html($email); ?>" style="color:#2563eb; text-decoration:none;" onclick="event.stopPropagation();"><?php echo html($email); ?></a>
                                            <?php endif; ?>
                                        </div>
                                        <!-- Mobile card meta -->
                                        <div class="agent-mobile-meta">
                                            <?php if ($deptName !== ''): ?>
                                                <span class="chip" style="background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; font-size:0.7rem; border-radius:6px; padding:3px 8px; font-weight:700;">
                                                    <i class="bi bi-building"></i> <?php echo html($deptName); ?>
                                                </span>
                                            <?php endif; ?>
                                            <span class="chip <?php echo html($roleInfo['bg']); ?>" style="font-size:0.7rem; border-radius:6px; padding:3px 8px; font-weight:700;">
                                                <?php echo html($roleInfo['label']); ?>
                                            </span>
                                            <?php if ($isActive): ?>
                                                <span class="chip" style="background: #16a34a15; color: #065f46; border: 1px solid #a7f3d0; font-size:0.7rem; border-radius:6px; padding:3px 8px; font-weight:700;">Activo</span>
                                            <?php else: ?>
                                                <span class="chip" style="background: #f1f5f9; color: #94a3b8; border: 1px solid #e2e8f0; font-size:0.7rem; border-radius:6px; padding:3px 8px; font-weight:700;">Inactivo</span>
                                            <?php endif; ?>
                                            <?php if ($totalTickets > 0): ?>
                                                <span class="chip" style="background: #eff6ff; color: #1e3a8a; border: 1px solid #bfdbfe; font-size:0.7rem; border-radius:6px; padding:3px 8px; font-weight:700;">
                                                    <i class="bi bi-ticket-perforated"></i> <?php echo $totalTickets; ?>
                                                    <?php if ($openTickets > 0): ?><span style="color:#ef4444;">(<?php echo $openTickets; ?>)</span><?php endif; ?>
                                                </span>
                                            <?php endif; ?>
                                            <span class="chip" style="background: #fffbeb; color: #92400e; border: 1px solid #fde68a; font-size:0.7rem; border-radius:6px; padding:3px 8px; font-weight:700;">
                                                <i class="bi bi-clock"></i> <?php echo $agent['last_login'] ? html(formatDate($agent['last_login'])) : 'Nunca'; ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="d-none d-lg-table-cell" style="vertical-align: middle;">
                                <?php if ($deptName !== ''): ?>
                                    <span class="chip" style="background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; padding: 6px 14px; font-weight: 700; font-size: 0.8rem; border-radius: 8px;">
                                        <i class="bi bi-building" style="margin-right: 4px;"></i><?php echo html($deptName); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted" style="font-size: 0.85rem;">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="d-none d-md-table-cell" style="vertical-align: middle;">
                                <span class="badge <?php echo html($roleInfo['bg']); ?>" style="font-size: 0.8rem; padding: 6px 12px; border-radius: 8px;">
                                    <?php echo html($roleInfo['label']); ?>
                                </span>
                            </td>
                            <td class="d-none d-md-table-cell" style="vertical-align: middle;">
                                <?php if ($isActive): ?>
                                    <span class="chip" style="background: #16a34a15; color: #065f46; border: 1px solid #a7f3d0; padding: 6px 14px; font-weight: 700; font-size: 0.8rem; border-radius: 8px;">
                                        <i class="bi bi-check-circle-fill" style="margin-right: 4px;"></i>Activo
                                    </span>
                                <?php else: ?>
                                    <span class="chip" style="background: #f1f5f9; color: #94a3b8; border: 1px solid #e2e8f0; padding: 6px 14px; font-weight: 700; font-size: 0.8rem; border-radius: 8px;">
                                        <i class="bi bi-x-circle" style="margin-right: 4px;"></i>Inactivo
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="d-none d-lg-table-cell" style="vertical-align: middle;">
                                <div style="display: flex; flex-direction: column; gap: 4px;">
                                    <span style="font-weight: 700; color: #1e293b; font-size: 0.9rem;"><?php echo (int)$totalTickets; ?> total</span>
                                    <?php if ($openTickets > 0): ?>
                                        <span style="font-size: 0.8rem; color: #ef4444; font-weight: 600;">
                                            <i class="bi bi-circle-fill" style="font-size: 0.5rem; vertical-align: middle;"></i> <?php echo (int)$openTickets; ?> abierto(s)
                                        </span>
                                    <?php else: ?>
                                        <span style="font-size: 0.8rem; color: #94a3b8;">Sin abiertos</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="d-none d-md-table-cell" style="vertical-align: middle; color: #64748b; font-size: 0.85rem; font-weight: 600;">
                                <div style="display:flex; align-items:center; gap:6px;">
                                    <i class="bi bi-clock-history" style="color:#94a3b8; font-size: 1rem;"></i>
                                    <?php echo $agent['last_login'] ? html(formatDate($agent['last_login'])) : 'Nunca'; ?>
                                </div>
                            </td>
                            <td style="vertical-align: middle; text-align: right; padding-right: 20px;">
                                <?php if ($email !== ''): ?>
                                    <a href="mailto:<?php echo html($email); ?>" class="btn btn-sm d-none d-md-inline-block" style="background: transparent; color: #94a3b8; border: none; font-size: 1.2rem; transition: all 0.2s;" onmouseover="this.style.color='#2563eb'" onmouseout="this.style.color='#94a3b8'" title="Contactar">
                                        <i class="bi bi-envelope"></i>
                                    </a>
                                    <a href="mailto:<?php echo html($email); ?>" class="agent-mobile-action" title="Contactar">
                                        <i class="bi bi-envelope-fill"></i>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Paginación -->
    <?php if ($totalPages > 1): ?>
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mt-3">
        <div class="text-muted" style="font-size:0.9rem;">
            Mostrando <?php echo (int)$showStart; ?>-<?php echo (int)$showEnd; ?> de <?php echo (int)$totalRows; ?>
            · Página <?php echo (int)$pageNum; ?> de <?php echo (int)$totalPages; ?>
        </div>
        <div class="d-flex gap-2">
            <?php if ($pageNum > 1): ?>
                <a class="btn btn-outline-secondary btn-sm" href="directory.php?<?php echo http_build_query(array_merge($basePaging, ['p' => $pageNum - 1])); ?>">
                    <i class="bi bi-chevron-left"></i> Anterior
                </a>
            <?php else: ?>
                <button class="btn btn-outline-secondary btn-sm" disabled><i class="bi bi-chevron-left"></i> Anterior</button>
            <?php endif; ?>

            <?php
            $range = 2;
            $start = max(1, $pageNum - $range);
            $end   = min($totalPages, $pageNum + $range);
            ?>
            <div class="d-none d-sm-flex gap-1">
                <?php if ($start > 1): ?>
                    <a href="directory.php?<?php echo http_build_query(array_merge($basePaging, ['p' => 1])); ?>" class="btn btn-sm btn-outline-secondary">1</a>
                    <?php if ($start > 2): ?><span class="text-muted small px-1" style="align-self:center;">&hellip;</span><?php endif; ?>
                <?php endif; ?>
                <?php for ($i = $start; $i <= $end; $i++): ?>
                    <a href="directory.php?<?php echo http_build_query(array_merge($basePaging, ['p' => $i])); ?>"
                       class="btn btn-sm <?php echo $i === $pageNum ? 'btn-primary' : 'btn-outline-secondary'; ?>"
                       <?php echo $i === $pageNum ? 'style="background:linear-gradient(135deg,#2563eb,#1d4ed8);border:none;"' : ''; ?>>
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                <?php if ($end < $totalPages): ?>
                    <?php if ($end < $totalPages - 1): ?><span class="text-muted small px-1" style="align-self:center;">&hellip;</span><?php endif; ?>
                    <a href="directory.php?<?php echo http_build_query(array_merge($basePaging, ['p' => $totalPages])); ?>" class="btn btn-sm btn-outline-secondary"><?php echo $totalPages; ?></a>
                <?php endif; ?>
            </div>

            <?php if ($pageNum < $totalPages): ?>
                <a class="btn btn-outline-secondary btn-sm" href="directory.php?<?php echo http_build_query(array_merge($basePaging, ['p' => $pageNum + 1])); ?>">
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
    document.getElementById('deptSelect').addEventListener('change', function() {
        var url = new URL(window.location.href);
        if (this.value === '0') {
            url.searchParams.delete('did');
        } else {
            url.searchParams.set('did', this.value);
        }
        url.searchParams.delete('p');
        window.location.href = url.toString();
    });
})();
</script>
