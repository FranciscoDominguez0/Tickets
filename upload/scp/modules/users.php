<?php
// Módulo: Directorio de usuarios (end users / clientes)
// Lista con búsqueda, ordenación, selección y paginación

// Añadir usuario (registro directo, sin confirmación por correo)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do']) && $_POST['do'] === 'add') {
    $add_errors = [];
    $email      = trim($_POST['email'] ?? '');
    $firstname  = trim($_POST['firstname'] ?? '');
    $lastname   = trim($_POST['lastname'] ?? '');
    $company    = trim($_POST['company'] ?? '');
    $password   = $_POST['password'] ?? '';
    $password2  = $_POST['password2'] ?? '';
    $status     = in_array($_POST['status'] ?? '', ['active', 'inactive', 'banned']) ? $_POST['status'] : 'active';

    if (!$email) $add_errors[] = 'El email es obligatorio.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $add_errors[] = 'Email no válido.';
    if (!$firstname) $add_errors[] = 'El nombre es obligatorio.';
    if (!$lastname) $add_errors[] = 'El apellido es obligatorio.';
    if (strlen($password) < 6) $add_errors[] = 'La contraseña debe tener al menos 6 caracteres.';
    elseif ($password !== $password2) $add_errors[] = 'Las contraseñas no coinciden.';

    if (empty($add_errors)) {
        $stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) {
            $add_errors[] = 'Ya existe un usuario con ese email.';
        }
    }

    if (empty($add_errors)) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $mysqli->prepare("INSERT INTO users (email, password, firstname, lastname, company, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('ssssss', $email, $hash, $firstname, $lastname, $company, $status);
        if ($stmt->execute()) {
            header('Location: users.php?added=1');
            exit;
        }
        $add_errors[] = 'Error al guardar. Inténtalo de nuevo.';
    }
}

$add_errors = $add_errors ?? [];

// Eliminar usuario (POST con confirmación desde modal)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do']) && $_POST['do'] === 'delete' && isset($_POST['id']) && is_numeric($_POST['id'])) {
    if (isset($_POST['csrf_token']) && Auth::validateCSRF($_POST['csrf_token'])) {
        $del_id = (int) $_POST['id'];
        $stmt = $mysqli->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param('i', $del_id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            header('Location: users.php?msg=user_deleted');
            exit;
        }
    }
}

// Asignar organización a usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do']) && $_POST['do'] === 'assign_org' && isset($_POST['user_id']) && isset($_POST['org_name'])) {
    if (isset($_POST['csrf_token']) && Auth::validateCSRF($_POST['csrf_token'])) {
        $user_id = (int) $_POST['user_id'];
        $org_name = trim($_POST['org_name']);
        // Verificar que la organización existe
        $stmt = $mysqli->prepare("SELECT id FROM organizations WHERE name = ? LIMIT 1");
        $stmt->bind_param('s', $org_name);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) {
            $stmt = $mysqli->prepare("UPDATE users SET company = ? WHERE id = ?");
            $stmt->bind_param('si', $org_name, $user_id);
            if ($stmt->execute()) {
                header('Location: users.php?id=' . $user_id . '&msg=org_assigned');
                exit;
            }
        }
    }
}

// Remover organización de usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do']) && $_POST['do'] === 'remove_org' && isset($_POST['user_id'])) {
    if (isset($_POST['csrf_token']) && Auth::validateCSRF($_POST['csrf_token'])) {
        $user_id = (int) $_POST['user_id'];
        $stmt = $mysqli->prepare("UPDATE users SET company = NULL WHERE id = ?");
        $stmt->bind_param('i', $user_id);
        if ($stmt->execute()) {
            header('Location: users.php?id=' . $user_id . '&msg=org_removed');
            exit;
        }
    }
}

// Cambiar estado de usuario (activo/inactivo/baneado)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do']) && $_POST['do'] === 'update_status' && isset($_POST['user_id']) && is_numeric($_POST['user_id'])) {
    if (isset($_POST['csrf_token']) && Auth::validateCSRF($_POST['csrf_token'])) {
        $user_id = (int) $_POST['user_id'];
        $new_status = $_POST['status'] ?? '';
        if (!in_array($new_status, ['active', 'inactive', 'banned'], true)) {
            $new_status = 'inactive';
        }
        $stmt = $mysqli->prepare("UPDATE users SET status = ?, updated = NOW() WHERE id = ?");
        $stmt->bind_param('si', $new_status, $user_id);
        if ($stmt->execute()) {
            header('Location: users.php?id=' . $user_id . '&msg=status_updated');
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do']) && $_POST['do'] === 'update_profile' && isset($_POST['user_id']) && is_numeric($_POST['user_id'])) {
    if (isset($_POST['csrf_token']) && Auth::validateCSRF($_POST['csrf_token'])) {
        $user_id = (int) $_POST['user_id'];
        $email = trim($_POST['email'] ?? '');
        $firstname = trim($_POST['firstname'] ?? '');
        $lastname = trim($_POST['lastname'] ?? '');
        $company = trim($_POST['company'] ?? '');

        $edit_errors = [];
        if ($email === '') {
            $edit_errors[] = 'El email es obligatorio.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $edit_errors[] = 'Email no válido.';
        }
        if ($firstname === '') $edit_errors[] = 'El nombre es obligatorio.';
        if ($lastname === '') $edit_errors[] = 'El apellido es obligatorio.';

        if (empty($edit_errors)) {
            $stmtE = $mysqli->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
            if ($stmtE) {
                $stmtE->bind_param('si', $email, $user_id);
                if ($stmtE->execute()) {
                    if ($stmtE->get_result()->fetch_assoc()) {
                        $edit_errors[] = 'Ya existe un usuario con ese email.';
                    }
                }
            }
        }

        if (empty($edit_errors)) {
            $companyVal = $company !== '' ? $company : null;
            $stmtU = $mysqli->prepare('UPDATE users SET email = ?, firstname = ?, lastname = ?, company = ?, updated = NOW() WHERE id = ?');
            if ($stmtU) {
                $stmtU->bind_param('ssssi', $email, $firstname, $lastname, $companyVal, $user_id);
                if ($stmtU->execute()) {
                    header('Location: users.php?id=' . $user_id . '&msg=profile_updated');
                    exit;
                }
            }
            $edit_errors[] = 'Error al actualizar el usuario.';
        }
        $add_errors = array_merge($add_errors ?? [], $edit_errors);
    }
}

// AJAX: buscar organizaciones
if (isset($_GET['ajax']) && $_GET['ajax'] === 'search_orgs' && isset($_GET['q'])) {
    $query = trim($_GET['q']);
    $stmt = $mysqli->prepare("SELECT name FROM organizations WHERE name LIKE ? ORDER BY name LIMIT 10");
    $like = '%' . $query . '%';
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    header('Content-Type: application/json');
    echo json_encode($results);
    exit;
}

// Vista de un usuario concreto (users.php?id=X)
$viewUser = null;
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $uid = (int) $_GET['id'];
    $stmt = $mysqli->prepare("SELECT id, email, firstname, lastname, company, status, created, updated FROM users WHERE id = ?");
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && ($row = $res->fetch_assoc())) $viewUser = $row;
}

if ($viewUser) {
    $stmt = $mysqli->prepare("SELECT t.id, t.ticket_number, t.subject, t.created, s.name as status_name FROM tickets t LEFT JOIN ticket_status s ON s.id = t.status_id WHERE t.user_id = ? ORDER BY t.created DESC");
    $stmt->bind_param('i', $viewUser['id']);
    $stmt->execute();
    $userTickets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $statusLabels = ['active' => 'Activo', 'inactive' => 'Inactivo', 'banned' => 'Bloqueado'];
    $viewUserName = trim($viewUser['firstname'] . ' ' . $viewUser['lastname']) ?: $viewUser['email'];
    require __DIR__ . '/user-view.inc.php';
    return;
}

$search   = trim($_GET['q'] ?? '');
$sort     = strtolower($_GET['sort'] ?? 'name');
$order    = strtoupper($_GET['order'] ?? 'ASC');
$pageNum  = max(1, (int)($_GET['p'] ?? 1));
$perPage  = 15;

$validSorts = ['name', 'status', 'created', 'updated'];
if (!in_array($sort, $validSorts)) $sort = 'name';
$validOrders = ['ASC', 'DESC'];
if (!in_array($order, $validOrders)) $order = 'ASC';

// Contar total (con filtro de búsqueda)
$countSql = "SELECT COUNT(*) AS total FROM users WHERE 1=1";
$countParams = [];
$countTypes = '';
if ($search !== '') {
    $term = '%' . $search . '%';
    $countSql .= " AND (firstname LIKE ? OR lastname LIKE ? OR email LIKE ? OR company LIKE ?)";
    $countParams = [$term, $term, $term, $term];
    $countTypes = 'ssss';
}
$countStmt = $mysqli->prepare($countSql);
if (!empty($countParams)) $countStmt->bind_param($countTypes, ...$countParams);
$countStmt->execute();
$totalRows = (int) $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = $totalRows ? (int) ceil($totalRows / $perPage) : 1;
$pageNum = min($pageNum, max(1, $totalPages));
$offset = ($pageNum - 1) * $perPage;

// Mapeo de ordenación
$sortColumns = [
    'name'    => 'CONCAT(u.firstname, \' \', u.lastname)',
    'status'  => 'u.status',
    'created' => 'u.created',
    'updated' => 'u.updated'
];
$orderBy = $sortColumns[$sort] ?? $sortColumns['name'];

$sql = "
    SELECT 
        u.id,
        u.email,
        u.firstname,
        u.lastname,
        u.company,
        u.status,
        u.created,
        u.updated,
        COUNT(t.id) AS ticket_count
    FROM users u
    LEFT JOIN tickets t ON t.user_id = u.id
    WHERE 1=1
";
$params = [];
$types = '';
if ($search !== '') {
    $term = '%' . $search . '%';
    $sql .= " AND (u.firstname LIKE ? OR u.lastname LIKE ? OR u.email LIKE ? OR u.company LIKE ?)";
    $params = [$term, $term, $term, $term];
    $types = 'ssss';
}
$sql .= " GROUP BY u.id, u.email, u.firstname, u.lastname, u.company, u.status, u.created, u.updated";
$sql .= " ORDER BY $orderBy $order LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;
$types .= 'ii';

$stmt = $mysqli->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

$baseUrl = 'users.php?';
$queryParams = [];
if ($search !== '') $queryParams['q'] = $search;
$currentSort = $sort;
$currentOrder = $order;
$nextOrder = $order === 'ASC' ? 'DESC' : 'ASC';

$statusLabels = [
    'active'   => 'Activo',
    'inactive' => 'Inactivo',
    'banned'   => 'Bloqueado'
];
$statusBadges = [
    'active'   => 'user-status-active',
    'inactive' => 'user-status-inactive',
    'banned'   => 'user-status-banned'
];
?>

<div class="users-directory">
    <?php if (!empty($add_errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo implode(' ', array_map('htmlspecialchars', $add_errors)); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['added']) && $_GET['added'] === '1'): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            Usuario añadido correctamente. El usuario puede iniciar sesión de inmediato (no requiere confirmación por correo).
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'user_deleted'): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            Usuario eliminado correctamente.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'org_assigned'): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            Organización asignada correctamente.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'org_removed'): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            Organización removida correctamente.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Búsqueda -->
    <div class="search-card">
        <form method="GET" action="users.php" class="search-form">
            <?php if ($currentSort !== 'name' || $currentOrder !== 'ASC'): ?>
                <input type="hidden" name="sort" value="<?php echo html($currentSort); ?>">
                <input type="hidden" name="order" value="<?php echo html($currentOrder); ?>">
            <?php endif; ?>
            <div class="search-wrap">
                <div class="search-icon-wrap">
                    <input type="text"
                           name="q"
                           class="form-control"
                           placeholder="Buscar por nombre, email o empresa..."
                           value="<?php echo html($search); ?>">
                </div>
                <button type="submit" class="btn btn-search">
                    <i class="bi bi-search"></i> Buscar
                </button>
            </div>
        </form>
    </div>

    <!-- Título y acciones -->
    <div class="page-header">
        <h1 class="page-title">Directorio de usuarios</h1>
        <div class="header-actions">
            <button type="button" class="btn btn-add" data-bs-toggle="modal" data-bs-target="#modalAddUser"><i class="bi bi-plus-lg"></i> Añadir usuario</button>
            <a href="#" class="btn btn-import"><i class="bi bi-upload"></i> Importar</a>
            <div class="dropdown">
                <button class="btn btn-more dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-gear"></i> Más <i class="bi bi-chevron-down" style="font-size:0.7rem;"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="#">Exportar seleccionados</a></li>
                    <li><a class="dropdown-item" href="#">Cambiar organización</a></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Tabla -->
    <?php if (empty($users)): ?>
        <div class="table-card" style="padding: 48px 24px; text-align: center;">
            <p class="text-muted mb-0">
                <i class="bi bi-people" style="font-size: 2.5rem; opacity: 0.5;"></i><br>
                No se encontraron usuarios<?php echo $search ? ' con ese criterio de búsqueda.' : '.'; ?>
            </p>
        </div>
    <?php else: ?>
        <div class="table-card">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th style="width: 44px;">
                            <input type="checkbox" class="form-check-input" id="selectAll" title="Seleccionar todos">
                        </th>
                        <th>
                            <a href="users.php?<?php echo http_build_query(array_merge($queryParams, ['sort' => 'name', 'order' => $currentSort === 'name' ? $nextOrder : 'ASC', 'p' => 1])); ?>">
                                Nombre
                                <?php if ($currentSort === 'name'): ?>
                                    <i class="bi bi-arrow-<?php echo $currentOrder === 'ASC' ? 'up' : 'down'; ?>"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>
                            <a href="users.php?<?php echo http_build_query(array_merge($queryParams, ['sort' => 'status', 'order' => $currentSort === 'status' ? $nextOrder : 'ASC', 'p' => 1])); ?>">
                                Estado
                                <?php if ($currentSort === 'status'): ?>
                                    <i class="bi bi-arrow-<?php echo $currentOrder === 'ASC' ? 'up' : 'down'; ?>"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>
                            <a href="users.php?<?php 
                                $ord = $currentSort === 'created' ? $nextOrder : 'DESC'; 
                                echo http_build_query(array_merge($queryParams, ['sort' => 'created', 'order' => $ord, 'p' => 1])); ?>">
                                Creado
                                <?php if ($currentSort === 'created'): ?>
                                    <i class="bi bi-arrow-<?php echo $currentOrder === 'ASC' ? 'up' : 'down'; ?>"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>
                            <a href="users.php?<?php 
                                $ord = $currentSort === 'updated' ? $nextOrder : 'DESC'; 
                                echo http_build_query(array_merge($queryParams, ['sort' => 'updated', 'order' => $ord, 'p' => 1])); ?>">
                                Actualizado
                                <?php if ($currentSort === 'updated'): ?>
                                    <i class="bi bi-arrow-<?php echo $currentOrder === 'ASC' ? 'up' : 'down'; ?>"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td>
                                <input type="checkbox" class="form-check-input user-row-cb" name="ids[]" value="<?php echo (int)$u['id']; ?>">
                            </td>
                            <td class="user-name-cell">
                                <a href="users.php?id=<?php echo (int)$u['id']; ?>">
                                    <?php echo html(trim($u['firstname'] . ' ' . $u['lastname']) ?: $u['email']); ?>
                                </a>
                                <?php if ((int)$u['ticket_count'] > 0): ?>
                                    <span class="ticket-count"><i class="bi bi-ticket-perforated"></i> (<?php echo (int)$u['ticket_count']; ?>)</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $status = $u['status'] ?? 'inactive';
                                $badgeClass = $statusBadges[$status] ?? 'user-status-inactive';
                                $label = $statusLabels[$status] ?? ucfirst($status);
                                ?>
                                <span class="badge <?php echo $badgeClass; ?>" style="padding: 6px 10px; border-radius: 8px; font-weight: 500;">
                                    <?php echo html($label); ?>
                                </span>
                            </td>
                            <td><?php echo $u['created'] ? date('d/m/y', strtotime($u['created'])) : '-'; ?></td>
                            <td><?php echo $u['updated'] ? date('d/m/y H:i:s', strtotime($u['updated'])) : '-'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Pie de tabla: selección, paginación, mostrando -->
            <div class="table-footer-bar">
                <div class="select-links">
                    Seleccionar:
                    <a href="#" data-select="all">Todos</a>
                    <a href="#" data-select="none">Ninguno</a>
                </div>
                <div class="pagination-wrap">
                    Página:
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <?php $qp = array_merge($queryParams, ['sort' => $currentSort, 'order' => $currentOrder, 'p' => $i]); ?>
                        <?php if ($i === $pageNum): ?>
                            <strong>[<?php echo $i; ?>]</strong>
                        <?php else: ?>
                            <a href="users.php?<?php echo http_build_query($qp); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <a href="users.php?<?php echo http_build_query(array_merge($queryParams, ['sort' => $currentSort, 'order' => $currentOrder, 'p' => $pageNum, 'a' => 'export'])); ?>">Exportar</a>
                </div>
                <div class="showing-text">
                    Mostrando <?php echo $totalRows ? $offset + 1 : 0; ?> - <?php echo min($offset + $perPage, $totalRows); ?> de <?php echo $totalRows; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Modal Añadir usuario -->
    <div class="modal fade" id="modalAddUser" tabindex="-1" aria-labelledby="modalAddUserLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 12px; border: none; box-shadow: 0 8px 32px rgba(0,0,0,0.12);">
                <div class="modal-header" style="border-bottom: 1px solid #e2e8f0;">
                    <h5 class="modal-title" id="modalAddUserLabel"><i class="bi bi-person-plus"></i> Añadir usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <form method="post" action="users.php">
                    <input type="hidden" name="do" value="add">
                    <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">
                    <div class="modal-body">
                        <p class="text-muted small mb-3">El usuario podrá iniciar sesión de inmediato (no se requiere confirmación por correo).</p>
                        <div class="mb-3">
                            <label for="add-email" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="add-email" name="email" required placeholder="usuario@ejemplo.com" value="<?php echo html($_POST['email'] ?? ''); ?>">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="add-firstname" class="form-label">Nombre <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="add-firstname" name="firstname" required placeholder="Nombre" value="<?php echo html($_POST['firstname'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="add-lastname" class="form-label">Apellido <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="add-lastname" name="lastname" required placeholder="Apellido" value="<?php echo html($_POST['lastname'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="add-company" class="form-label">Empresa</label>
                            <input type="text" class="form-control" id="add-company" name="company" placeholder="Opcional" value="<?php echo html($_POST['company'] ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                            <label for="add-password" class="form-label">Contraseña <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="add-password" name="password" required minlength="6" placeholder="Mínimo 6 caracteres">
                        </div>
                        <div class="mb-3">
                            <label for="add-password2" class="form-label">Repetir contraseña <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="add-password2" name="password2" required minlength="6" placeholder="Repetir contraseña">
                        </div>
                        <div class="mb-3">
                            <label for="add-status" class="form-label">Estado</label>
                            <select class="form-select" id="add-status" name="status">
                                <option value="active">Activo</option>
                                <option value="inactive">Inactivo</option>
                                <option value="banned">Bloqueado</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer" style="border-top: 1px solid #e2e8f0;">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success"><i class="bi bi-check-lg"></i> Añadir usuario</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
