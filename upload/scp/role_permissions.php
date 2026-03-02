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

$eid = empresaId();

$flashMsg = '';
$flashError = '';
if (!empty($_SESSION['flash_msg'])) {
    $flashMsg = (string)$_SESSION['flash_msg'];
    unset($_SESSION['flash_msg']);
}
if (!empty($_SESSION['flash_error'])) {
    $flashError = (string)$_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

$currentStaffId = (int)($_SESSION['staff_id'] ?? 0);
$currentStaffRole = '';
if ($currentStaffId > 0) {
    $stmtMe = $mysqli->prepare("SELECT role FROM staff WHERE id = ? LIMIT 1");
    if ($stmtMe) {
        $stmtMe->bind_param('i', $currentStaffId);
        $stmtMe->execute();
        $me = $stmtMe->get_result()->fetch_assoc();
        $currentStaffRole = (string)($me['role'] ?? '');
    }
}

if ($currentStaffRole !== 'admin') {
    $_SESSION['flash_error'] = 'No tienes permisos para administrar permisos.';
    header('Location: roles.php');
    exit;
}

$ensureRolesTable = function () use ($mysqli) {
    if (!isset($mysqli) || !$mysqli) return false;
    $sql = "CREATE TABLE IF NOT EXISTS roles (\n"
        . "  id INT PRIMARY KEY AUTO_INCREMENT,\n"
        . "  name VARCHAR(100) NOT NULL,\n"
        . "  is_enabled TINYINT(1) NOT NULL DEFAULT 1,\n"
        . "  created DATETIME DEFAULT CURRENT_TIMESTAMP,\n"
        . "  updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
        . "  UNIQUE KEY uq_roles_name (name)\n"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    return (bool)$mysqli->query($sql);
};
$ensureRolesTable();

$ensureRolePermissionsTable = function () use ($mysqli) {
    if (!isset($mysqli) || !$mysqli) return false;
    $sql = "CREATE TABLE IF NOT EXISTS role_permissions (\n"
        . "  id INT PRIMARY KEY AUTO_INCREMENT,\n"
        . "  empresa_id INT NOT NULL DEFAULT 1,\n"
        . "  role_name VARCHAR(100) NOT NULL,\n"
        . "  perm_key VARCHAR(120) NOT NULL,\n"
        . "  is_enabled TINYINT(1) NOT NULL DEFAULT 1,\n"
        . "  created DATETIME DEFAULT CURRENT_TIMESTAMP,\n"
        . "  updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
        . "  UNIQUE KEY uq_role_perm (empresa_id, role_name, perm_key),\n"
        . "  KEY idx_role (role_name)\n"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $ok = (bool)$mysqli->query($sql);

    try {
        $res = $mysqli->query("SHOW COLUMNS FROM role_permissions LIKE 'empresa_id'");
        $hasEmpresaId = ($res && $res->num_rows > 0);
        if (!$hasEmpresaId) {
            $mysqli->query("ALTER TABLE role_permissions ADD COLUMN empresa_id INT NOT NULL DEFAULT 1");
            $mysqli->query("ALTER TABLE role_permissions ADD INDEX idx_role_perm_empresa (empresa_id, role_name)");
        }

        $idxOld = $mysqli->query("SHOW INDEX FROM role_permissions WHERE Key_name = 'uq_role_perm'");
        if ($idxOld && $idxOld->num_rows > 0) {
            $mysqli->query("ALTER TABLE role_permissions DROP INDEX uq_role_perm");
        }

        $idxNew = $mysqli->query("SHOW INDEX FROM role_permissions WHERE Key_name = 'uq_role_perm_empresa_role_perm'");
        if (!$idxNew || $idxNew->num_rows < 1) {
            $mysqli->query("ALTER TABLE role_permissions ADD UNIQUE KEY uq_role_perm_empresa_role_perm (empresa_id, role_name, perm_key)");
        }
    } catch (Throwable $e) {
    }

    return $ok;
};
$ensureRolePermissionsTable();

$roleName = trim((string)($_GET['role'] ?? ''));
if ($roleName === '') {
    $_SESSION['flash_error'] = 'Rol inválido.';
    header('Location: roles.php');
    exit;
}

$rolesHasEmpresaId = false;
if (isset($mysqli) && $mysqli) {
    try {
        $res = $mysqli->query("SHOW COLUMNS FROM roles LIKE 'empresa_id'");
        $rolesHasEmpresaId = ($res && $res->num_rows > 0);
    } catch (Throwable $e) {
        $rolesHasEmpresaId = false;
    }
}

$stmtRole = $rolesHasEmpresaId
    ? $mysqli->prepare('SELECT name FROM roles WHERE empresa_id = ? AND name = ? LIMIT 1')
    : $mysqli->prepare('SELECT name FROM roles WHERE name = ? LIMIT 1');
if (!$stmtRole) {
    $_SESSION['flash_error'] = 'No se pudo cargar el rol.';
    header('Location: roles.php');
    exit;
}

if ($rolesHasEmpresaId) {
    $stmtRole->bind_param('is', $eid, $roleName);
} else {
    $stmtRole->bind_param('s', $roleName);
}
$stmtRole->execute();
$roleRow = $stmtRole->get_result()->fetch_assoc();
if (!$roleRow) {
    $_SESSION['flash_error'] = 'Rol no encontrado.';
    header('Location: roles.php');
    exit;
}

if (isset($mysqli) && $mysqli) {
    $stmtDelKb = $mysqli->prepare('DELETE FROM role_permissions WHERE perm_key LIKE ?');
    if ($stmtDelKb) {
        $like = 'kb.%';
        $stmtDelKb->bind_param('s', $like);
        $stmtDelKb->execute();
    }
}

$permissionGroups = [
    'Tickets' => [
        'ticket.assign' => ['title' => 'Asignar', 'desc' => 'Asignar tickets a moderadores o equipos'],
        'ticket.close' => ['title' => 'Cerrar', 'desc' => 'Cerrar tickets'],
        'ticket.create' => ['title' => 'Crear', 'desc' => 'Abrir tickets en nombre de los usuarios'],
        'ticket.delete' => ['title' => 'Eliminar', 'desc' => 'Eliminar tickets'],
        'ticket.edit' => ['title' => 'Editar', 'desc' => 'Modificar tickets'],
        'ticket.edit_thread' => ['title' => 'Editar Asunto', 'desc' => 'Editar los elementos del hilo de otros moderadores'],
        'ticket.link' => ['title' => 'Link', 'desc' => 'Posibilidad para vincular tickets'],
        'ticket.markanswered' => ['title' => 'Marcar como contestados', 'desc' => 'Posibilidad de marcar un ticket como respondido/no respondido'],
        'ticket.merge' => ['title' => 'Unir', 'desc' => 'Posibilidad de fusionar tickets'],
        'ticket.reply' => ['title' => 'Publicar Respuesta', 'desc' => 'Publicar una respuesta al ticket'],
        'ticket.refer' => ['title' => 'Referido', 'desc' => 'Habilidad para administrar tickets referidos'],
        'ticket.post' => ['title' => 'Publicado', 'desc' => 'Habilidad para realizar una asignación de ticket'],
        'ticket.transfer' => ['title' => 'Transferir', 'desc' => 'Transferir tickets entre departamentos'],
    ],
    'Tareas' => [
        'task.create' => ['title' => 'Crear', 'desc' => 'Crear tareas'],
        'task.edit' => ['title' => 'Editar', 'desc' => 'Editar tareas'],
        'task.close' => ['title' => 'Cerrar', 'desc' => 'Cerrar tareas'],
        'task.assign' => ['title' => 'Asignar', 'desc' => 'Asignar tareas'],
        'task.delete' => ['title' => 'Eliminar', 'desc' => 'Eliminar tareas'],
    ],
];

$allPermKeys = [];
foreach ($permissionGroups as $g) {
    foreach ($g as $k => $meta) {
        $allPermKeys[$k] = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF()) {
        $_SESSION['flash_error'] = 'Token CSRF inválido.';
        header('Location: role_permissions.php?role=' . urlencode($roleName));
        exit;
    }

    $do = (string)($_POST['do'] ?? '');

    if ($do === 'reset') {
        $stmtDel = $mysqli->prepare('DELETE FROM role_permissions WHERE empresa_id = ? AND role_name = ?');
        if ($stmtDel) {
            $stmtDel->bind_param('is', $eid, $roleName);
            $stmtDel->execute();
        }
        $_SESSION['flash_msg'] = 'Permisos restablecidos.';
        header('Location: role_permissions.php?role=' . urlencode($roleName));
        exit;
    }

    if ($do === 'save') {
        $selected = $_POST['perms'] ?? [];
        if (!is_array($selected)) $selected = [];

        $selectedKeys = [];
        foreach ($selected as $k) {
            $k = trim((string)$k);
            if ($k !== '' && isset($allPermKeys[$k])) {
                $selectedKeys[$k] = true;
            }
        }

        $mysqli->begin_transaction();
        try {
            $stmtDel = $mysqli->prepare('DELETE FROM role_permissions WHERE empresa_id = ? AND role_name = ?');
            if ($stmtDel) {
                $stmtDel->bind_param('is', $eid, $roleName);
                $stmtDel->execute();
            }

            if (!empty($selectedKeys)) {
                $stmtIns = $mysqli->prepare('INSERT INTO role_permissions (empresa_id, role_name, perm_key, is_enabled, created, updated) VALUES (?, ?, ?, 1, NOW(), NOW())');
                if ($stmtIns) {
                    foreach (array_keys($selectedKeys) as $k) {
                        $stmtIns->bind_param('iss', $eid, $roleName, $k);
                        $stmtIns->execute();
                    }
                }
            }

            $mysqli->commit();
            $_SESSION['flash_msg'] = 'Se aplicaron los permisos.';
        } catch (Throwable $e) {
            $mysqli->rollback();
            $_SESSION['flash_error'] = 'No se pudieron guardar los permisos.';
        }

        if (!empty($_SESSION['flash_error'])) {
            header('Location: role_permissions.php?role=' . urlencode($roleName));
        } else {
            header('Location: roles.php');
        }
        exit;
    }
}

$enabledPerms = [];
$stmtP = $mysqli->prepare('SELECT perm_key FROM role_permissions WHERE empresa_id = ? AND role_name = ? AND is_enabled = 1');
if ($stmtP) {
    $stmtP->bind_param('is', $eid, $roleName);
    $stmtP->execute();
    $resP = $stmtP->get_result();
    while ($resP && ($row = $resP->fetch_assoc())) {
        $k = (string)($row['perm_key'] ?? '');
        if ($k !== '') $enabledPerms[$k] = true;
    }
}

$hasAnySaved = !empty($enabledPerms);

ob_start();
?>

<div class="settings-hero">
    <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
        <div class="d-flex align-items-center gap-3">
            <span class="settings-hero-icon"><i class="bi bi-shield-check"></i></span>
            <div>
                <h1>Permisos</h1>
                <p>Rol: <strong><?php echo html($roleName); ?></strong></p>
            </div>
        </div>
    </div>
</div>

<?php if ($flashError): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i><?php echo html($flashError); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($flashMsg): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i><?php echo html($flashMsg); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card settings-card">
    <div class="card-body">
        <form method="post" action="role_permissions.php?role=<?php echo urlencode($roleName); ?>">
            <?php csrfField(); ?>
            <input type="hidden" name="do" value="save">

            <?php foreach ($permissionGroups as $groupTitle => $perms): ?>
                <div class="mb-3">
                    <div class="fw-semibold mb-2"><?php echo html($groupTitle); ?></div>
                    <div class="list-group">
                        <?php foreach ($perms as $key => $meta): ?>
                            <?php
                            $checked = isset($enabledPerms[$key]);
                            ?>
                            <label class="list-group-item d-flex gap-2 align-items-start">
                                <input class="form-check-input mt-1" type="checkbox" name="perms[]" value="<?php echo html($key); ?>" <?php echo $checked ? 'checked' : ''; ?>>
                                <span>
                                    <span class="fw-semibold"><?php echo html($meta['title']); ?></span>
                                    <span class="text-muted"> — <?php echo html($meta['desc']); ?></span>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="d-flex justify-content-center gap-2 flex-wrap mt-4">
                <button type="submit" class="btn btn-outline-primary">Guardar cambios</button>
            </div>
        </form>

        <div class="d-flex justify-content-center gap-2 flex-wrap mt-2">
            <form method="post" action="role_permissions.php?role=<?php echo urlencode($roleName); ?>" class="d-inline">
                <?php csrfField(); ?>
                <input type="hidden" name="do" value="reset">
                <button type="submit" class="btn btn-outline-secondary">Restablecer</button>
            </form>
            <a href="roles.php" class="btn btn-secondary">Cancelar</a>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once 'layout_admin.php';
?>
