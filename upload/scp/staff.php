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
$currentRoute = 'staff';

$search = trim((string)($_GET['q'] ?? ''));
$deptFilter = isset($_GET['did']) && is_numeric($_GET['did']) ? (int)$_GET['did'] : 0;

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
        d.id AS dept_id,
        d.name AS dept_name
    FROM staff s
    LEFT JOIN departments d ON s.dept_id = d.id
    WHERE 1=1
";
$params = [];
$types = '';

if ($search !== '') {
    $sql .= " AND (s.firstname LIKE ? OR s.lastname LIKE ? OR s.email LIKE ? OR s.username LIKE ?)";
    $term = '%' . $search . '%';
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $types .= 'ssss';
}
if ($deptFilter > 0) {
    $sql .= " AND s.dept_id = ?";
    $params[] = $deptFilter;
    $types .= 'i';
}
if (isset($_GET['status']) && ($_GET['status'] === 'active' || $_GET['status'] === 'inactive')) {
    $sql .= " AND s.is_active = ?";
    $params[] = ($_GET['status'] === 'active') ? 1 : 0;
    $types .= 'i';
}

$sql .= " ORDER BY s.firstname, s.lastname";

$agents = [];
$stmt = $mysqli->prepare($sql);
if ($stmt) {
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($res && ($row = $res->fetch_assoc())) {
        $agents[] = $row;
    }
}

$departments = [];
$deptStmt = $mysqli->prepare("SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name");
if ($deptStmt) {
    $deptStmt->execute();
    $deptRes = $deptStmt->get_result();
    while ($deptRes && ($d = $deptRes->fetch_assoc())) {
        $departments[] = $d;
    }
}

$activeCount = 0;
$inactiveCount = 0;
foreach ($agents as $a) {
    if ((int)($a['is_active'] ?? 0) === 1) $activeCount++;
    else $inactiveCount++;
}

$content = '
<div class="settings-hero">
    <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
        <div class="d-flex align-items-center gap-3">
            <span class="settings-hero-icon"><i class="bi bi-people"></i></span>
            <div>
                <h1>Agentes</h1>
                <p>Gestión de agentes y permisos</p>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="badge bg-success">' . (int)$activeCount . ' Activos</span>
            <span class="badge bg-secondary">' . (int)$inactiveCount . ' Inactivos</span>
            <span class="badge bg-info">' . (int)count($agents) . ' Total</span>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-12 col-md-5">
                <label class="form-label">Buscar</label>
                <input type="text" name="q" class="form-control" value="' . htmlspecialchars($search, ENT_QUOTES, 'UTF-8') . '" placeholder="Nombre, email o usuario">
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label">Departamento</label>
                <select name="did" class="form-select">
                    <option value="0">Todos</option>';

foreach ($departments as $d) {
    $did = (int)($d['id'] ?? 0);
    $dname = (string)($d['name'] ?? '');
    $content .= '<option value="' . $did . '" ' . ($deptFilter === $did ? 'selected' : '') . '>' . htmlspecialchars($dname, ENT_QUOTES, 'UTF-8') . '</option>';
}

$status = (string)($_GET['status'] ?? '');
$content .= '
               </select>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label">Estado</label>
                <select name="status" class="form-select">
                    <option value="" ' . ($status === '' ? 'selected' : '') . '>Todos</option>
                    <option value="active" ' . ($status === 'active' ? 'selected' : '') . '>Activos</option>
                    <option value="inactive" ' . ($status === 'inactive' ? 'selected' : '') . '>Inactivos</option>
                </select>
            </div>
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Buscar</button>
                <a href="staff.php" class="btn btn-outline-secondary">Limpiar</a>
            </div>
        </form>
    </div>
</div>

<div class="card settings-card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h5 class="mb-0">Lista de Agentes</h5>
        <div class="text-muted small">' . count($agents) . ' registro(s)</div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>Departamento</th>
                    <th>Rol</th>
                    <th class="text-center">Estado</th>
                    <th>Último acceso</th>
                </tr>
            </thead>
            <tbody>';

if (empty($agents)) {
    $content .= '<tr><td colspan="6" class="text-center text-muted py-4">No hay resultados.</td></tr>';
} else {
    foreach ($agents as $a) {
        $name = trim((string)($a['firstname'] ?? '') . ' ' . (string)($a['lastname'] ?? ''));
        if ($name === '') $name = (string)($a['username'] ?? '');
        $email = (string)($a['email'] ?? '');
        $dept = (string)($a['dept_name'] ?? '');
        $role = (string)($a['role'] ?? '');
        $active = (int)($a['is_active'] ?? 0) === 1;
        $last = $a['last_login'] ?? null;

        $content .= '<tr>';
        $content .= '<td><strong>' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</strong><br><small class="text-muted">@' . htmlspecialchars((string)($a['username'] ?? ''), ENT_QUOTES, 'UTF-8') . '</small></td>';
        $content .= '<td><a class="text-decoration-none" href="mailto:' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '</a></td>';
        $content .= '<td>' . ($dept !== '' ? '<span class="badge bg-secondary">' . htmlspecialchars($dept, ENT_QUOTES, 'UTF-8') . '</span>' : '<span class="text-muted">Sin asignar</span>') . '</td>';
        $content .= '<td>' . ($role !== '' ? '<span class="badge bg-info text-dark">' . htmlspecialchars($role, ENT_QUOTES, 'UTF-8') . '</span>' : '<span class="text-muted">—</span>') . '</td>';
        $content .= '<td class="text-center">' . ($active ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-secondary">Inactivo</span>') . '</td>';
        $content .= '<td>' . ($last ? htmlspecialchars(formatDate($last), ENT_QUOTES, 'UTF-8') : '<span class="text-muted">Nunca</span>') . '</td>';
        $content .= '</tr>';
    }
}

$content .= '
           </tbody>
        </table>
    </div>
</div>
';

require_once 'layout_admin.php';
?>