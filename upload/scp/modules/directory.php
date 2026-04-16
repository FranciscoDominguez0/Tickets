<?php
// Módulo: Directorio de agentes
// Muestra lista de agentes con búsqueda, filtros y estadísticas

// Parámetros de búsqueda y filtros
$search = trim($_GET['q'] ?? '');
$deptFilter = isset($_GET['did']) && is_numeric($_GET['did']) ? (int)$_GET['did'] : 0;
$sort = strtolower($_GET['sort'] ?? 'name');
$order = strtoupper($_GET['order'] ?? 'ASC');
$pageNum = max(1, (int)($_GET['p'] ?? 1));
$perPage = 10;

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
// Check if staff_departments table exists
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
            GROUP_CONCAT(DISTINCT d.name ORDER BY d.name SEPARATOR ', ') AS dept_name,
            GROUP_CONCAT(DISTINCT d.id ORDER BY d.id SEPARATOR ',') AS dept_ids,
            COUNT(DISTINCT t.id) as total_tickets,
            SUM(CASE WHEN t.status_id = (SELECT id FROM ticket_status WHERE name = 'Abierto' LIMIT 1) THEN 1 ELSE 0 END) as open_tickets
        FROM staff s
        LEFT JOIN staff_departments sd ON sd.staff_id = s.id
        LEFT JOIN departments d ON d.id = sd.dept_id
        LEFT JOIN tickets t ON t.staff_id = s.id AND t.empresa_id = ?
        WHERE s.empresa_id = ?
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
            SUM(CASE WHEN t.status_id = (SELECT id FROM ticket_status WHERE name = 'Abierto' LIMIT 1) THEN 1 ELSE 0 END) as open_tickets
        FROM staff s
        LEFT JOIN departments d ON s.dept_id = d.id
        LEFT JOIN tickets t ON t.staff_id = s.id AND t.empresa_id = ?
        WHERE s.empresa_id = ?
    ";
}

$params = [];
$types = 'ii';
$params[] = $eid;
$params[] = $eid;

// Aplicar filtro de búsqueda
if ($search) {
    if (is_numeric($search)) {
        // Búsqueda por ID
        $sql .= " AND s.id = ?";
        $params[] = (int)$search;
        $types .= 'i';
    } elseif (strpos($search, '@') !== false && filter_var($search, FILTER_VALIDATE_EMAIL)) {
        // Búsqueda por email exacto
        $sql .= " AND s.email = ?";
        $params[] = $search;
        $types .= 's';
    } else {
        // Búsqueda por nombre, apellido o email (parcial)
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

// Conteo total (para paginación) - mismo filtro pero sin JOIN/GROUP BY
$countSql = "SELECT COUNT(*) AS total FROM staff s WHERE s.empresa_id = ?";
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

// Agrupar para contar tickets
$sql .= " GROUP BY s.id, s.username, s.email, s.firstname, s.lastname, s.role, s.is_active, s.created, s.last_login, d.id, d.name";

// Aplicar ordenamiento
$sortColumns = [
    'name' => 's.firstname, s.lastname',
    'email' => 's.email',
    'dept' => 'd.name',
    'role' => 's.role',
    'status' => 's.is_active',
    'created' => 's.created',
    'last_login' => 's.last_login'
];

$orderBy = $sortColumns[$sort] ?? 's.firstname, s.lastname';
$sql .= " ORDER BY $orderBy $order";

// Paginación
$sql .= " LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;
$types .= 'ii';

// Ejecutar query
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

// Obtener lista de departamentos para el filtro
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

// Generar URL para ordenamiento
$baseUrl = 'directory.php?';
$queryParams = [];
if ($search) $queryParams['q'] = $search;
if ($deptFilter) $queryParams['did'] = $deptFilter;
$currentSort = $sort;
$currentOrder = $order;
$nextOrder = $order === 'ASC' ? 'DESC' : 'ASC';
?>

<!-- Formulario de búsqueda y filtros -->
<div class="mb-4">
    <form method="GET" action="directory.php" class="d-flex align-items-center gap-3 flex-wrap" style="background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <div class="flex-grow-1" style="min-width: 200px;">
            <input type="text" 
                   name="q" 
                   class="form-control form-control-sm" 
                   placeholder="Buscar por nombre, email o username..." 
                   value="<?php echo html($search); ?>">
        </div>
        <div style="min-width: 200px;">
            <select name="did" class="form-select form-select-sm">
                <option value="0">Todos los departamentos</option>
                <?php foreach ($departments as $dept): ?>
                    <option value="<?php echo $dept['id']; ?>" <?php echo $deptFilter == $dept['id'] ? 'selected' : ''; ?>>
                        <?php echo html($dept['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="bi bi-search"></i> Buscar
            </button>
            <?php if ($search || $deptFilter): ?>
                <a href="directory.php" class="btn btn-outline-secondary btn-sm">Limpiar</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Título -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">Directorio de Agentes</h2>
    <span class="text-muted">
        <?php echo (int)$totalRows; ?> agente(s) encontrado(s)
    </span>
</div>

<!-- Vista móvil (cards) -->
<?php if (!empty($agents)): ?>
    <div class="directory-mobile d-md-none">
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

            $roleBadges = [
                'admin' => 'bg-danger',
                'supervisor' => 'bg-warning text-dark',
                'agent' => 'bg-info text-dark',
            ];
            $roleLabels = [
                'admin' => 'Admin',
                'supervisor' => 'Supervisor',
                'agent' => 'Agente',
            ];
            $badgeClass = $roleBadges[$role] ?? 'bg-secondary';
            $roleLabel = $roleLabels[$role] ?? ($role !== '' ? ucfirst($role) : 'Agente');
            ?>
            <div class="directory-card">
                <div class="directory-card-top">
                    <div class="directory-avatar" aria-hidden="true"><?php echo html($initials); ?></div>
                    <div class="directory-main">
                        <div class="directory-name-row">
                            <div class="directory-name"><?php echo html($fullName); ?></div>
                            <span class="badge <?php echo html($isActive ? 'bg-success' : 'bg-secondary'); ?>">
                                <?php echo $isActive ? 'Activo' : 'Inactivo'; ?>
                            </span>
                        </div>
                        <div class="directory-sub">
                            <?php if ($username !== ''): ?>
                                <span class="directory-user">@<?php echo html($username); ?></span>
                            <?php endif; ?>
                            <?php if ($deptName !== ''): ?>
                                <span class="directory-dot" aria-hidden="true">·</span>
                                <span class="directory-dept"><?php echo html($deptName); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="directory-badges">
                            <span class="badge <?php echo html($badgeClass); ?>"><?php echo html($roleLabel); ?></span>
                            <?php if ($openTickets > 0): ?>
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                    <?php echo (int)$openTickets; ?> abierto(s)
                                </span>
                            <?php else: ?>
                                <span class="badge bg-light text-muted border">Sin abiertos</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="directory-kpis">
                    <div class="directory-kpi">
                        <div class="kpi-label">Tickets</div>
                        <div class="kpi-value"><?php echo (int)$totalTickets; ?></div>
                    </div>
                    <div class="directory-kpi">
                        <div class="kpi-label">Último acceso</div>
                        <div class="kpi-value kpi-muted"><?php echo $agent['last_login'] ? html(formatDate($agent['last_login'])) : 'Nunca'; ?></div>
                    </div>
                </div>

                <div class="directory-actions">
                    <?php if ($email !== ''): ?>
                        <a class="btn btn-outline-primary btn-sm flex-grow-1" href="mailto:<?php echo html($email); ?>">
                            <i class="bi bi-envelope"></i> Contactar
                        </a>
                    <?php else: ?>
                        <button type="button" class="btn btn-outline-secondary btn-sm flex-grow-1" disabled>
                            <i class="bi bi-envelope"></i> Sin correo
                        </button>
                    <?php endif; ?>
                    <a class="btn btn-outline-secondary btn-sm" href="directory.php?<?php echo http_build_query(array_merge($queryParams, ['q' => $email !== '' ? $email : $username])); ?>" title="Buscar este agente">
                        <i class="bi bi-search"></i>
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Tabla de agentes -->
<?php if (empty($agents)): ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle"></i> No se encontraron agentes con los criterios de búsqueda especificados.
    </div>
<?php else: ?>
    <div class="table-responsive d-none d-md-block" style="background: #fff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th width="20%">
                        <a href="directory.php?<?php echo http_build_query(array_merge($queryParams, ['sort' => 'name', 'order' => $currentSort === 'name' ? $nextOrder : 'ASC'])); ?>" 
                           class="text-decoration-none text-dark">
                            Nombre
                            <?php if ($currentSort === 'name'): ?>
                                <i class="bi bi-arrow-<?php echo $currentOrder === 'ASC' ? 'up' : 'down'; ?>"></i>
                            <?php endif; ?>
                        </a>
                    </th>
                    <th width="20%">
                        <a href="directory.php?<?php echo http_build_query(array_merge($queryParams, ['sort' => 'email', 'order' => $currentSort === 'email' ? $nextOrder : 'ASC'])); ?>" 
                           class="text-decoration-none text-dark">
                            Email
                            <?php if ($currentSort === 'email'): ?>
                                <i class="bi bi-arrow-<?php echo $currentOrder === 'ASC' ? 'up' : 'down'; ?>"></i>
                            <?php endif; ?>
                        </a>
                    </th>
                    <th width="15%">
                        <a href="directory.php?<?php echo http_build_query(array_merge($queryParams, ['sort' => 'dept', 'order' => $currentSort === 'dept' ? $nextOrder : 'ASC'])); ?>" 
                           class="text-decoration-none text-dark">
                            Departamento
                            <?php if ($currentSort === 'dept'): ?>
                                <i class="bi bi-arrow-<?php echo $currentOrder === 'ASC' ? 'up' : 'down'; ?>"></i>
                            <?php endif; ?>
                        </a>
                    </th>
                    <th width="10%">
                        <a href="directory.php?<?php echo http_build_query(array_merge($queryParams, ['sort' => 'role', 'order' => $currentSort === 'role' ? $nextOrder : 'ASC'])); ?>" 
                           class="text-decoration-none text-dark">
                            Rol
                            <?php if ($currentSort === 'role'): ?>
                                <i class="bi bi-arrow-<?php echo $currentOrder === 'ASC' ? 'up' : 'down'; ?>"></i>
                            <?php endif; ?>
                        </a>
                    </th>
                    <th width="10%" class="text-center">Estado</th>
                    <th width="10%" class="text-center">Tickets</th>
                    <th width="15%">
                        <a href="directory.php?<?php echo http_build_query(array_merge($queryParams, ['sort' => 'last_login', 'order' => $currentSort === 'last_login' ? $nextOrder : 'DESC'])); ?>" 
                           class="text-decoration-none text-dark">
                            Último acceso
                            <?php if ($currentSort === 'last_login'): ?>
                                <i class="bi bi-arrow-<?php echo $currentOrder === 'ASC' ? 'up' : 'down'; ?>"></i>
                            <?php endif; ?>
                        </a>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($agents as $agent): ?>
                    <tr>
                        <td>
                            <strong><?php echo html($agent['firstname'] . ' ' . $agent['lastname']); ?></strong>
                            <br>
                            <small class="text-muted">@<?php echo html($agent['username']); ?></small>
                        </td>
                        <td>
                            <a href="mailto:<?php echo html($agent['email']); ?>" class="text-decoration-none">
                                <?php echo html($agent['email']); ?>
                            </a>
                        </td>
                        <td>
                            <?php if ($agent['dept_name']): ?>
                                <span class="badge bg-secondary"><?php echo html($agent['dept_name']); ?></span>
                            <?php else: ?>
                                <span class="text-muted">Sin asignar</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $roleBadges = [
                                'admin' => 'bg-danger',
                                'supervisor' => 'bg-warning',
                                'agent' => 'bg-info'
                            ];
                            $roleLabels = [
                                'admin' => 'Admin',
                                'supervisor' => 'Supervisor',
                                'agent' => 'Agente'
                            ];
                            $badgeClass = $roleBadges[$agent['role']] ?? 'bg-secondary';
                            $roleLabel = $roleLabels[$agent['role']] ?? ucfirst($agent['role']);
                            ?>
                            <span class="badge <?php echo $badgeClass; ?>"><?php echo $roleLabel; ?></span>
                        </td>
                        <td class="text-center">
                            <?php if ($agent['is_active']): ?>
                                <span class="badge bg-success">Activo</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <div>
                                <strong><?php echo (int)$agent['total_tickets']; ?></strong>
                                <?php if ($agent['open_tickets'] > 0): ?>
                                    <br>
                                    <small class="text-danger">
                                        <i class="bi bi-circle-fill" style="font-size: 0.6em;"></i>
                                        <?php echo (int)$agent['open_tickets']; ?> abierto(s)
                                    </small>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <?php if ($agent['last_login']): ?>
                                <?php echo formatDate($agent['last_login']); ?>
                            <?php else: ?>
                                <span class="text-muted">Nunca</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php
    $basePaging = $queryParams;
    $basePaging['sort'] = $currentSort;
    $basePaging['order'] = $currentOrder;

    $prevUrl = '';
    $nextUrl = '';
    if ($pageNum > 1) {
        $prevParams = $basePaging;
        $prevParams['p'] = $pageNum - 1;
        $prevUrl = 'directory.php?' . http_build_query($prevParams);
    }
    if ($pageNum < $totalPages) {
        $nextParams = $basePaging;
        $nextParams['p'] = $pageNum + 1;
        $nextUrl = 'directory.php?' . http_build_query($nextParams);
    }
    $showStart = ($totalRows > 0) ? ($offset + 1) : 0;
    $showEnd = min($offset + count($agents), $totalRows);
    ?>
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mt-3">
        <div class="text-muted" style="font-size:.9rem;">
            Mostrando <?php echo (int)$showStart; ?>-<?php echo (int)$showEnd; ?> de <?php echo (int)$totalRows; ?>
            · Página <?php echo (int)$pageNum; ?> de <?php echo (int)$totalPages; ?>
        </div>
        <div class="d-flex gap-2">
            <?php if ($prevUrl !== ''): ?>
                <a class="btn btn-outline-secondary btn-sm" href="<?php echo html($prevUrl); ?>"><i class="bi bi-chevron-left"></i> Anterior</a>
            <?php else: ?>
                <button type="button" class="btn btn-outline-secondary btn-sm" disabled><i class="bi bi-chevron-left"></i> Anterior</button>
            <?php endif; ?>
            <?php if ($nextUrl !== ''): ?>
                <a class="btn btn-outline-secondary btn-sm" href="<?php echo html($nextUrl); ?>">Siguiente <i class="bi bi-chevron-right"></i></a>
            <?php else: ?>
                <button type="button" class="btn btn-outline-secondary btn-sm" disabled>Siguiente <i class="bi bi-chevron-right"></i></button>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

