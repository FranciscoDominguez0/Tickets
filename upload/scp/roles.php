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
$rolesHasEmpresaId = false;
$staffHasEmpresaId = false;
if (isset($mysqli) && $mysqli) {
    try {
        $res = $mysqli->query("SHOW COLUMNS FROM roles LIKE 'empresa_id'");
        $rolesHasEmpresaId = ($res && $res->num_rows > 0);
    } catch (Throwable $e) {
        $rolesHasEmpresaId = false;
    }
    try {
        $res = $mysqli->query("SHOW COLUMNS FROM staff LIKE 'empresa_id'");
        $staffHasEmpresaId = ($res && $res->num_rows > 0);
    } catch (Throwable $e) {
        $staffHasEmpresaId = false;
    }
}

$collapseSettingsMenu = false;
$menuKey = 'admin_sidebar_menu_seen_' . (int)($_SESSION['staff_id'] ?? 0);
if ((string)($_SESSION['sidebar_panel_mode'] ?? '') !== 'admin') {
    unset($_SESSION[$menuKey]);
    $_SESSION['sidebar_panel_mode'] = 'admin';
}
if (!isset($_SESSION[$menuKey])) {
    $_SESSION[$menuKey] = 1;
    $collapseSettingsMenu = true;
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

if (isset($mysqli) && $mysqli) {
    try {
        $res = $mysqli->query("SHOW COLUMNS FROM roles LIKE 'empresa_id'");
        $hasEmpresaCol = ($res && $res->num_rows > 0);
        if (!$hasEmpresaCol) {
            $mysqli->query("ALTER TABLE roles ADD COLUMN empresa_id INT NOT NULL DEFAULT 1");
            $mysqli->query("ALTER TABLE roles ADD INDEX idx_roles_empresa (empresa_id)");
        }

        $res = $mysqli->query("SHOW COLUMNS FROM roles LIKE 'empresa_id'");
        $rolesHasEmpresaId = ($res && $res->num_rows > 0);
        if ($rolesHasEmpresaId) {
            $idx = $mysqli->query("SHOW INDEX FROM roles WHERE Key_name = 'uq_roles_name'");
            if ($idx && $idx->num_rows > 0) {
                $mysqli->query("ALTER TABLE roles DROP INDEX uq_roles_name");
            }
            $idx2 = $mysqli->query("SHOW INDEX FROM roles WHERE Key_name = 'uq_roles_empresa_name'");
            if (!$idx2 || $idx2->num_rows < 1) {
                $mysqli->query("ALTER TABLE roles ADD UNIQUE KEY uq_roles_empresa_name (empresa_id, name)");
            }
        }
    } catch (Throwable $e) {
    }
}

if (isset($mysqli) && $mysqli) {
    $rolesCount = 0;
    $sqlRc = 'SELECT COUNT(*) c FROM roles';
    if ($rolesHasEmpresaId) {
        $sqlRc .= ' WHERE empresa_id = ' . (int)$eid;
    }
    $rc = $mysqli->query($sqlRc);
    if ($rc) $rolesCount = (int)($rc->fetch_assoc()['c'] ?? 0);
    if ($rolesCount === 0) {
        if ($rolesHasEmpresaId) {
            $stmtSeed = $mysqli->prepare("INSERT IGNORE INTO roles (empresa_id, name, is_enabled, created, updated) VALUES (?, 'admin', 1, NOW(), NOW()), (?, 'supervisor', 1, NOW(), NOW()), (?, 'agent', 1, NOW(), NOW())");
            if ($stmtSeed) {
                $stmtSeed->bind_param('iii', $eid, $eid, $eid);
                $stmtSeed->execute();
            }
        } else {
            $mysqli->query("INSERT IGNORE INTO roles (name, is_enabled, created, updated) VALUES ('admin', 1, NOW(), NOW()), ('supervisor', 1, NOW(), NOW()), ('agent', 1, NOW(), NOW())");
        }
    }
}

$msg = '';
$error = '';
if (!empty($_SESSION['flash_msg'])) {
    $msg = (string)$_SESSION['flash_msg'];
    unset($_SESSION['flash_msg']);
}
if (!empty($_SESSION['flash_error'])) {
    $error = (string)$_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF()) {
        $_SESSION['flash_error'] = 'Token CSRF inválido.';
        header('Location: roles.php');
        exit;
    }

    $do = (string)($_POST['do'] ?? '');
    if ($do === 'create') {
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') {
            $_SESSION['flash_error'] = 'El nombre del rol es requerido.';
            header('Location: roles.php');
            exit;
        }

        $sqlCreate = 'INSERT INTO roles (name, is_enabled, created, updated) VALUES (?, 1, NOW(), NOW())';
        if ($rolesHasEmpresaId) {
            $sqlCreate = 'INSERT INTO roles (empresa_id, name, is_enabled, created, updated) VALUES (?, ?, 1, NOW(), NOW())';
        }
        $stmt = $mysqli->prepare($sqlCreate);
        if (!$stmt) {
            $_SESSION['flash_error'] = 'No se pudo crear el rol.';
            header('Location: roles.php');
            exit;
        }
        if ($rolesHasEmpresaId) {
            $stmt->bind_param('is', $eid, $name);
        } else {
            $stmt->bind_param('s', $name);
        }
        if ($stmt->execute()) {
            $_SESSION['flash_msg'] = 'Rol creado correctamente.';
        } else {
            $_SESSION['flash_error'] = 'No se pudo crear el rol (puede que ya exista).';
        }
        header('Location: roles.php');
        exit;
    }

    if ($do === 'mass_process') {
        $ids = $_POST['ids'] ?? [];
        $action = (string)($_POST['a'] ?? '');

        if (empty($ids) || !is_array($ids)) {
            $_SESSION['flash_error'] = 'Debe seleccionar al menos un rol.';
            header('Location: roles.php');
            exit;
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($v) => $v > 0)));
        if (empty($ids)) {
            $_SESSION['flash_error'] = 'Debe seleccionar al menos un rol.';
            header('Location: roles.php');
            exit;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));

        if ($action === 'enable' || $action === 'disable') {
            $enabled = $action === 'enable' ? 1 : 0;
            $sqlUp = "UPDATE roles SET is_enabled = ?, updated = NOW() WHERE id IN ($placeholders)";
            $typesUp = 'i' . $types;
            $bindUp = array_merge([$enabled], $ids);
            if ($rolesHasEmpresaId) {
                $sqlUp .= ' AND empresa_id = ?';
                $typesUp .= 'i';
                $bindUp[] = (int)$eid;
            }
            $stmt = $mysqli->prepare($sqlUp);
            if ($stmt) {
                $stmt->bind_param($typesUp, ...$bindUp);
                $stmt->execute();
                $_SESSION['flash_msg'] = $enabled ? 'Roles habilitados correctamente.' : 'Roles deshabilitados correctamente.';
            } else {
                $_SESSION['flash_error'] = 'No se pudo procesar la acción.';
            }
            header('Location: roles.php');
            exit;
        }

        if ($action === 'delete') {
            $sqlNames = "SELECT id, name FROM roles WHERE id IN ($placeholders)";
            $typesNames = $types;
            $idsNames = $ids;
            if ($rolesHasEmpresaId) {
                $sqlNames .= ' AND empresa_id = ?';
                $typesNames .= 'i';
                $idsNames[] = (int)$eid;
            }
            $stmtNames = $mysqli->prepare($sqlNames);
            $toDelete = [];
            if ($stmtNames) {
                $stmtNames->bind_param($typesNames, ...$idsNames);
                $stmtNames->execute();
                $resN = $stmtNames->get_result();
                while ($r = $resN->fetch_assoc()) {
                    $toDelete[] = $r;
                }
            }

            $blocked = 0;
            foreach ($toDelete as $r) {
                $roleName = (string)($r['name'] ?? '');
                $sqlUse = 'SELECT COUNT(*) c FROM staff WHERE role = ?';
                if ($staffHasEmpresaId) {
                    $sqlUse .= ' AND empresa_id = ?';
                }
                $stmtU = $mysqli->prepare($sqlUse);
                if ($stmtU) {
                    if ($staffHasEmpresaId) {
                        $stmtU->bind_param('si', $roleName, $eid);
                    } else {
                        $stmtU->bind_param('s', $roleName);
                    }
                    $stmtU->execute();
                    $row = $stmtU->get_result()->fetch_assoc();
                    if ((int)($row['c'] ?? 0) > 0) {
                        $blocked++;
                    }
                }
            }

            if ($blocked > 0) {
                $_SESSION['flash_error'] = 'No se pueden eliminar roles que están en uso por agentes.';
                header('Location: roles.php');
                exit;
            }

            $sqlDel = "DELETE FROM roles WHERE id IN ($placeholders)";
            $typesDel = $types;
            $idsDel = $ids;
            if ($rolesHasEmpresaId) {
                $sqlDel .= ' AND empresa_id = ?';
                $typesDel .= 'i';
                $idsDel[] = (int)$eid;
            }
            $stmt = $mysqli->prepare($sqlDel);
            if ($stmt) {
                $stmt->bind_param($typesDel, ...$idsDel);
                if ($stmt->execute()) {
                    $_SESSION['flash_msg'] = 'Roles eliminados correctamente.';
                } else {
                    $_SESSION['flash_error'] = 'No se pudieron eliminar los roles.';
                }
            } else {
                $_SESSION['flash_error'] = 'No se pudieron eliminar los roles.';
            }
            header('Location: roles.php');
            exit;
        }

        $_SESSION['flash_error'] = 'Acción no reconocida.';
        header('Location: roles.php');
        exit;
    }
}

$roles = [];
if (isset($mysqli) && $mysqli) {
    $staffEmpresaWhere = '';
    if ($staffHasEmpresaId) {
        $staffEmpresaWhere = ' AND s.empresa_id = ' . (int)$eid;
    }

    $sql = "SELECT r.*, (SELECT COUNT(*) FROM staff s WHERE s.role = r.name" . $staffEmpresaWhere . ") AS agents_total, (SELECT COUNT(*) FROM staff s WHERE s.role = r.name AND s.is_active = 1" . $staffEmpresaWhere . ") AS agents_active\n"
        . "FROM roles r\n";
    if ($rolesHasEmpresaId) {
        $sql .= 'WHERE r.empresa_id = ' . (int)$eid . "\n";
    }
    $sql .= "ORDER BY r.name ASC";
    $res = $mysqli->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $roles[] = $row;
        }
    }
}

$rolesCount = count($roles);
$agentsTotal = 0;
foreach ($roles as $r) {
    $agentsTotal += (int)($r['agents_total'] ?? 0);
}

ob_start();
?>

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
            <span class="badge bg-info"><?php echo (int)$rolesCount; ?> Roles</span>
            <span class="badge bg-secondary"><?php echo (int)$agentsTotal; ?> Agentes</span>
        </div>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i><?php echo html($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($msg): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i><?php echo html($msg); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="alert alert-danger alert-dismissible fade show d-none" role="alert" id="rolesClientError" aria-live="polite" data-alert-static="1">
    <i class="bi bi-exclamation-triangle me-2"></i><span id="rolesClientErrorText"></span>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>

<div class="row">
    <div class="col-12">
        <div class="card settings-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong><i class="bi bi-shield-lock"></i> Roles</strong>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addRoleModal">
                        <i class="bi bi-plus-circle"></i> Añadir Nuevo Rol
                    </button>
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" id="rolesMoreDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-gear"></i> Más
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="rolesMoreDropdown">
                            <li><button class="dropdown-item" type="button" data-role-action="enable"><i class="bi bi-check-circle me-2"></i>Habilitar</button></li>
                            <li><button class="dropdown-item" type="button" data-role-action="disable"><i class="bi bi-slash-circle me-2"></i>Deshabilitar</button></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><button class="dropdown-item text-danger" type="button" data-role-action="delete"><i class="bi bi-trash me-2"></i>Eliminar</button></li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <form method="post" action="roles.php" id="rolesMassForm">
                    <input type="hidden" name="do" value="mass_process">
                    <?php csrfField(); ?>
                    <input type="hidden" name="a" value="" id="rolesMassAction">

                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th width="30"><input type="checkbox" id="selectAllRoles" class="form-check-input"></th>
                                    <th>Nombre</th>
                                    <th>Estado</th>
                                    <th>Creado en</th>
                                    <th>Última actualización</th>
                                    <th class="text-center">Agentes</th>
                                    <th class="text-center">Activos</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($roles)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">No hay roles para mostrar.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($roles as $r): ?>
                                        <?php
                                        $rid = (int)($r['id'] ?? 0);
                                        $name = (string)($r['name'] ?? '');
                                        $enabled = (int)($r['is_enabled'] ?? 0) === 1;
                                        $created = $r['created'] ?? null;
                                        $updated = $r['updated'] ?? null;
                                        $agentsCount = (int)($r['agents_total'] ?? 0);
                                        $agentsActive = (int)($r['agents_active'] ?? 0);
                                        ?>
                                        <tr>
                                            <td><input type="checkbox" name="ids[]" value="<?php echo $rid; ?>" class="form-check-input role-checkbox"></td>
                                            <td class="fw-semibold">
                                                <a class="text-decoration-none" href="role_permissions.php?role=<?php echo urlencode($name); ?>" title="Permisos">
                                                    <?php echo html($name); ?>
                                                </a>
                                            </td>
                                            <td><?php if ($enabled): ?><span class="badge bg-success">Activo</span><?php else: ?><span class="badge bg-secondary">Inactivo</span><?php endif; ?></td>
                                            <td><?php echo html(formatDate($created)); ?></td>
                                            <td><?php echo html(formatDate($updated)); ?></td>
                                            <td class="text-center"><?php echo (int)$agentsCount; ?></td>
                                            <td class="text-center"><?php echo (int)$agentsActive; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addRoleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="roles.php">
                <input type="hidden" name="do" value="create">
                <?php csrfField(); ?>
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle text-primary"></i> Añadir Nuevo Rol</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Nombre</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle"></i> Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
@media (max-width: 768px) {
    #rolesMassForm .table-responsive { border: none; }
    #rolesMassForm .table thead { display: none; }
    #rolesMassForm .table tbody tr {
        display: block;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        margin-bottom: 12px;
        padding: 14px 16px;
        background: #fff;
        position: relative;
    }
    #rolesMassForm .table tbody td {
        display: block;
        padding: 4px 0;
        border: none;
        text-align: left !important;
    }
    /* Checkbox */
    #rolesMassForm .table tbody td:nth-child(1) {
        position: absolute; top: 18px; left: 16px; padding: 0;
    }
    /* Nombre y enlace */
    #rolesMassForm .table tbody td:nth-child(2) {
        padding-left: 32px; padding-right: 80px;
    }
    #rolesMassForm .table tbody td:nth-child(2) a {
        font-size: 16px; color: #0f172a; font-weight: 800; display: block;
    }
    /* Estado (Badge) */
    #rolesMassForm .table tbody td:nth-child(3) {
        position: absolute; top: 15px; right: 16px; padding: 0;
    }
    /* Creado en */
    #rolesMassForm .table tbody td:nth-child(4) {
        font-size: 13px; padding-left: 32px; color: #64748b; margin-top: 2px;
    }
    #rolesMassForm .table tbody td:nth-child(4)::before {
        content: "Creado: "; font-weight: 600;
    }
    /* Última actualización */
    #rolesMassForm .table tbody td:nth-child(5) {
        display: none;
    }
    
    /* Sección métricas dividida: Agentes y Activos */
    #rolesMassForm .table tbody td:nth-child(6),
    #rolesMassForm .table tbody td:nth-child(7) {
        border-top: 1px dashed #e2e8f0;
        margin-top: 14px; 
        padding-top: 12px; 
        padding-bottom: 0;
        font-size: 15px; 
        font-weight: 700;
        color: #0f172a; 
        display: inline-block; 
        width: 48%; 
        box-sizing: border-box;
    }
    #rolesMassForm .table tbody td:nth-child(6)::before {
        content: "Total Agentes"; color: #64748b; font-weight: 700;
        display: block; font-size: 10px; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.05em;
    }
    #rolesMassForm .table tbody td:nth-child(7)::before {
        content: "Activos"; color: #64748b; font-weight: 700;
        display: block; font-size: 10px; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.05em;
    }
    #rolesMassForm .table tbody tr::after {
        content: ""; display: table; clear: both;
    }
}
</style>

<script>
window.addEventListener('DOMContentLoaded', function(){
    function getCheckedIds(){
        var boxes = document.querySelectorAll('.role-checkbox:checked');
        var ids = [];
        boxes.forEach(function(b){ ids.push(b.value); });
        return ids;
    }

    function requireAtLeastOneRoleSelected(ids, message) {
        if (ids.length < 1) {
            var box = document.getElementById('rolesClientError');
            if (!box) {
                var wrapper = document.createElement('div');
                wrapper.innerHTML = ''
                    + '<div class="alert alert-danger alert-dismissible fade show" role="alert" id="rolesClientError" aria-live="polite" data-alert-static="1">'
                    + '  <i class="bi bi-exclamation-triangle me-2"></i><span id="rolesClientErrorText"></span>'
                    + '  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>'
                    + '</div>';
                var newEl = wrapper.firstElementChild;
                var hero = document.querySelector('.settings-hero');
                if (hero && hero.parentNode) {
                    hero.parentNode.insertBefore(newEl, hero.nextSibling);
                } else {
                    document.body.insertBefore(newEl, document.body.firstChild);
                }
                box = newEl;
            }
            var txt = document.getElementById('rolesClientErrorText');
            if (txt) txt.textContent = message || 'Debe seleccionar al menos un rol';
            box.classList.remove('d-none');
            box.scrollIntoView({ behavior: 'smooth', block: 'start' });
            try {
                if (box._autoHideTimer) window.clearTimeout(box._autoHideTimer);
                box._autoHideTimer = window.setTimeout(function(){
                    if (box) box.classList.add('d-none');
                }, 3500);
            } catch (e) {}
            return false;
        }
        return true;
    }

    var selectAll = document.getElementById('selectAllRoles');
    if (selectAll) {
        selectAll.addEventListener('change', function(){
            var boxes = document.querySelectorAll('.role-checkbox');
            boxes.forEach(function(b){ b.checked = selectAll.checked; });
        });
    }

    var actionButtons = document.querySelectorAll('[data-role-action]');
    actionButtons.forEach(function(btn){
        btn.addEventListener('click', function(){
            var ids = getCheckedIds();
            var action = btn.getAttribute('data-role-action') || '';
            if (action === 'delete') {
                if (!requireAtLeastOneRoleSelected(ids, 'Debe seleccionar un rol para eliminar')) return;
            } else {
                if (!requireAtLeastOneRoleSelected(ids)) return;
            }
            var form = document.getElementById('rolesMassForm');
            var act = document.getElementById('rolesMassAction');
            if (!form || !act) return;
            act.value = action;
            form.submit();
        });
    });
});
</script>

<?php
$content = ob_get_clean();
require_once 'layout_admin.php';
?>
