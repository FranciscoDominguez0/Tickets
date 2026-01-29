<?php
// Módulo: Directorio de agentes
// Muestra lista de agentes con búsqueda, filtros y estadísticas

// Parámetros de búsqueda y filtros
$search = trim($_GET['q'] ?? '');
$deptFilter = isset($_GET['did']) && is_numeric($_GET['did']) ? (int)$_GET['did'] : 0;
$sort = strtolower($_GET['sort'] ?? 'name');
$order = strtoupper($_GET['order'] ?? 'ASC');

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
    LEFT JOIN tickets t ON t.staff_id = s.id
    WHERE 1=1
";

$params = [];
$types = '';

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
    $sql .= " AND s.dept_id = ?";
    $params[] = $deptFilter;
    $types .= 'i';
}

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
$deptStmt = $mysqli->prepare("SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name");
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
        <?php echo count($agents); ?> agente(s) encontrado(s)
    </span>
</div>

<!-- Tabla de agentes -->
<?php if (empty($agents)): ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle"></i> No se encontraron agentes con los criterios de búsqueda especificados.
    </div>
<?php else: ?>
    <div class="table-responsive" style="background: #fff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
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
<?php endif; ?>

