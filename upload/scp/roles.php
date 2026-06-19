<?php
require_once '../../config.php';
require_once '../../includes/helpers.php';
require_once '../../includes/Auth.php';

if (!isset($_SESSION['staff_id'])) {
    header('Location: login.php');
    exit;
}

requireLogin('agente');
requireRolePermission('admin.access');
$staff = getCurrentUser();
$currentRoute = 'roles';

$eid = empresaId();
$rolesHasEmpresaId = true;
$staffHasEmpresaId  = true;

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

// La columna empresa_id ya existe en la tabla roles.

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
            <span class="badge bg-info-subtle text-info px-3 py-2 rounded-pill fw-bold border border-info-subtle"><i class="bi bi-shield me-1"></i><?php echo (int)$rolesCount; ?> Roles</span>
            <span class="badge bg-secondary-subtle text-secondary px-3 py-2 rounded-pill fw-bold border border-secondary-subtle"><i class="bi bi-people me-1"></i><?php echo (int)$agentsTotal; ?> Agentes</span>
        </div>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo html($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($msg): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i><?php echo html($msg); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="alert alert-danger alert-dismissible fade show d-none border-0 shadow-sm rounded-4 mb-4" role="alert" id="rolesClientError" aria-live="polite" data-alert-static="1">
    <i class="bi bi-exclamation-triangle-fill me-2"></i><span id="rolesClientErrorText"></span>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>

<form method="post" action="roles.php" id="rolesMassForm">
    <input type="hidden" name="do" value="mass_process">
    <?php csrfField(); ?>
    <input type="hidden" name="a" value="" id="rolesMassAction">

    <!-- Control Bar -->
    <div class="d-flex justify-content-between align-items-center mb-4 gap-3 flex-wrap p-3 rounded-4 shadow-sm" style="background: var(--role-card-bg); border: 1px solid var(--role-card-border); transition: all 0.3s;">
        <div class="d-flex align-items-center gap-3">
            <div class="form-check mb-0 d-flex align-items-center gap-2">
                <input type="checkbox" id="selectAllRoles" class="form-check-input ms-0 shadow-sm" style="width: 1.25rem; height: 1.25rem; cursor: pointer; border-radius: 4px;">
                <label class="form-check-label fw-bold text-muted" for="selectAllRoles" style="cursor: pointer; user-select: none; font-size: 0.9rem;">Seleccionar todos</label>
            </div>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <button type="button" class="btn btn-primary px-3 py-2 rounded-3 btn-add-custom shadow-sm d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#addRoleModal" style="background: linear-gradient(135deg, #2563eb, #1d4ed8); border: 0; font-weight: 600; font-size: 0.88rem;">
                <i class="bi bi-plus-circle-fill"></i> Añadir Nuevo Rol
            </button>
            <div class="dropdown">
                <button class="btn dropdown-toggle px-3 py-2 rounded-3 fw-bold d-flex align-items-center gap-2 btn-actions-custom" type="button" id="rolesMoreDropdown" data-bs-toggle="dropdown" aria-expanded="false" style="font-size: 0.88rem; background: var(--role-stat-bg); color: var(--role-text-main); border: 1px solid var(--role-card-border);">
                    <i class="bi bi-gear-fill"></i> Acciones
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0 p-2" aria-labelledby="rolesMoreDropdown" style="background: var(--role-card-bg); border: 1px solid var(--role-card-border); border-radius: 12px;">
                    <li><button class="dropdown-item fw-semibold rounded-3 py-2" type="button" data-role-action="enable" style="color: var(--role-text-main);"><i class="bi bi-check-circle-fill text-success me-2"></i>Habilitar</button></li>
                    <li><button class="dropdown-item fw-semibold rounded-3 py-2" type="button" data-role-action="disable" style="color: var(--role-text-main);"><i class="bi bi-slash-circle-fill text-warning me-2"></i>Deshabilitar</button></li>
                    <li><hr class="dropdown-divider" style="border-top: 1px solid var(--role-card-border);"></li>
                    <li><button class="dropdown-item fw-semibold rounded-3 py-2 text-danger" type="button" data-role-action="delete"><i class="bi bi-trash-fill me-2"></i>Eliminar</button></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Premium Table Layout -->
    <div class="premium-table-wrapper">
        <div class="table-responsive">
            <table class="premium-table">
                <thead>
                    <tr>
                        <th width="50" class="text-center d-none d-md-table-cell">#</th>
                        <th class="d-none d-md-table-cell">Rol</th>
                        <th class="d-none d-md-table-cell">Estado</th>
                        <th class="d-none d-md-table-cell">Creado en</th>
                        <th class="d-none d-md-table-cell">Última Actualización</th>
                        <th class="text-center d-none d-md-table-cell">Agentes</th>
                        <th class="text-center d-none d-md-table-cell">Activos</th>
                        <th width="140" class="text-end d-none d-md-table-cell">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($roles)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-5">
                                <span class="fs-2 mb-2 d-block"><i class="bi bi-shield-slash"></i></span>
                                No se encontraron roles registrados.
                            </td>
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
                            <tr data-role-row-id="<?php echo $rid; ?>" style="cursor: pointer;">
                                <!-- VISTA MÓVIL (Tarjeta Premium) -->
                                <td class="d-md-none p-0">
                                    <div class="role-mobile-card">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div class="d-flex align-items-center gap-3">
                                                <input type="checkbox" name="ids[]" value="<?php echo $rid; ?>" class="form-check-input role-checkbox m-0 shadow-sm" style="width: 1.25rem; height: 1.25rem; cursor: pointer; border-radius: 4px;">
                                                <?php if ($enabled): ?>
                                                <span class="role-badge active"><i class="bi bi-check-circle-fill me-1"></i>Activo</span>
                                                <?php else: ?>
                                                <span class="role-badge inactive"><i class="bi bi-pause-circle-fill me-1"></i>Inactivo</span>
                                                <?php endif; ?>
                                            </div>
                                            <a href="role_permissions.php?role=<?php echo urlencode($name); ?>" class="role-mobile-action-btn" title="Permisos">
                                                <i class="bi bi-chevron-right"></i>
                                            </a>
                                        </div>

                                        <div class="role-mobile-card-title">
                                            <a class="text-decoration-none" href="role_permissions.php?role=<?php echo urlencode($name); ?>" style="color: inherit;">
                                                <?php echo html($name); ?>
                                            </a>
                                        </div>
                                        
                                        <div class="role-mobile-card-meta">
                                            <i class="bi bi-calendar3 me-1 text-muted"></i> Creado: <?php echo date('d M Y', strtotime($created)); ?>
                                        </div>

                                        <div class="row g-2 mt-2 pt-3 role-stats-row">
                                            <div class="col-6">
                                                <div class="role-stat-box">
                                                    <div class="stat-label">
                                                        Total Agentes
                                                    </div>
                                                    <div class="stat-value">
                                                        <?php echo (int)$agentsCount; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="role-stat-box">
                                                    <div class="stat-label">
                                                        Agentes Activos
                                                    </div>
                                                    <div class="stat-value" style="color: #10b981;">
                                                        <?php echo (int)$agentsActive; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <!-- VISTA ESCRITORIO -->
                                <td class="text-center d-none d-md-table-cell" onclick="event.stopPropagation();">
                                    <input type="checkbox" name="ids[]" value="<?php echo $rid; ?>" class="form-check-input role-checkbox shadow-sm m-0" style="width: 1.25rem; height: 1.25rem; cursor: pointer; border-radius: 4px;">
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="fs-5" style="color: <?php echo $enabled ? '#2563eb' : 'var(--role-text-muted)'; ?>;"><i class="bi bi-shield-lock-fill"></i></span>
                                        <a class="text-decoration-none fw-bold text-card-title" href="role_permissions.php?role=<?php echo urlencode($name); ?>" style="color: var(--role-text-main); font-size: 0.95rem;">
                                            <?php echo html($name); ?>
                                        </a>
                                    </div>
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <?php if ($enabled): ?>
                                        <span class="role-badge active"><i class="bi bi-check-circle-fill"></i> Activo</span>
                                    <?php else: ?>
                                        <span class="role-badge inactive"><i class="bi bi-pause-circle-fill"></i> Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td class="d-none d-md-table-cell" style="font-size: 0.85rem; font-weight: 500; color: var(--role-text-muted);">
                                    <i class="bi bi-calendar3 me-1 text-muted"></i> <?php echo html(formatDate($created)); ?>
                                </td>
                                <td class="d-none d-md-table-cell" style="font-size: 0.85rem; font-weight: 500; color: var(--role-text-muted);">
                                    <i class="bi bi-arrow-clockwise me-1 text-muted"></i> <?php echo html(formatDate($updated)); ?>
                                </td>
                                <td class="text-center d-none d-md-table-cell">
                                    <span class="agent-count-badge"><?php echo (int)$agentsCount; ?></span>
                                </td>
                                <td class="text-center d-none d-md-table-cell">
                                    <span class="agent-count-badge <?php echo $agentsActive > 0 ? 'active-agents' : ''; ?>"><?php echo (int)$agentsActive; ?></span>
                                </td>
                                <td class="text-end d-none d-md-table-cell" onclick="event.stopPropagation();">
                                    <a href="role_permissions.php?role=<?php echo urlencode($name); ?>" class="btn-permissions">
                                        <i class="bi bi-sliders"></i> Permisos
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</form>

<div class="modal fade" id="addRoleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <form method="post" action="roles.php">
                <input type="hidden" name="do" value="create">
                <?php csrfField(); ?>
                <div class="modal-header d-flex align-items-center px-4 py-3">
                    <h5 class="modal-title d-flex align-items-center gap-2 fs-5"><i class="bi bi-plus-circle text-primary"></i> Añadir Nuevo Rol</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body px-4 py-4">
                    <label class="form-label mb-2 fw-semibold">Nombre del Rol</label>
                    <input type="text" name="name" class="form-control" placeholder="Ej. Supervisor de Soporte" required autocomplete="off">
                </div>
                <div class="modal-footer px-4 py-3 border-0">
                    <button type="button" class="btn btn-secondary px-4 py-2 fw-semibold rounded-3 btn-cancel-custom" data-bs-dismiss="modal" style="font-size: 0.9rem;">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-4 py-2 fw-semibold rounded-3 btn-save-custom" style="font-size: 0.9rem; background: linear-gradient(135deg, #2563eb, #1d4ed8); border: 0;"><i class="bi bi-check-circle me-1"></i> Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* ── Design System Variables ── */
:root {
    --role-card-bg: #ffffff;
    --role-card-border: #e2e8f0;
    --role-stat-bg: #f8fafc;
    --role-table-header-bg: #f8fafc;
    --role-table-row-hover: #f8fafc;
    --role-text-main: #0f172a;
    --role-text-muted: #64748b;
    --role-badge-active-bg: rgba(16, 185, 129, 0.1);
    --role-badge-active-color: #16a34a;
    --role-badge-inactive-bg: #f1f5f9;
    --role-badge-inactive-color: #64748b;
    --role-badge-count-bg: #e2e8f0;
    --role-badge-count-color: #1e293b;
    --role-btn-perm-hover-bg: #cbd5e1;
    --modal-close-filter: none;

    /* Mobile variables */
    --role-mobile-card-bg: #ffffff;
    --role-mobile-card-border: #e2e8f0;
    --role-mobile-stat-bg: #f8fafc;
    --role-mobile-stat-text: #0f172a;
    --role-mobile-card-title: #0f172a;
    --role-mobile-card-meta: #64748b;
    --role-mobile-dashed-border: #e2e8f0;
    --role-mobile-action-btn-bg: #f8fafc;
    --role-mobile-action-btn-color: #64748b;
}

body.dark-mode {
    --role-card-bg: #000000;
    --role-card-border: #2a2a2a;
    --role-stat-bg: #000000;
    --role-table-header-bg: #161616;
    --role-table-row-hover: #000000;
    --role-text-main: #e5e5e5;
    --role-text-muted: #888888;
    --role-badge-active-bg: rgba(16, 185, 129, 0.15);
    --role-badge-active-color: #34d399;
    --role-badge-inactive-bg: #000000;
    --role-badge-inactive-color: #888888;
    --role-badge-count-bg: #000000;
    --role-badge-count-color: #e5e5e5;
    --role-btn-perm-hover-bg: #000000;
    --modal-close-filter: invert(1);

    /* Mobile variables */
    --role-mobile-card-bg: #000000;
    --role-mobile-card-border: #2a2a2a;
    --role-mobile-stat-bg: #000000;
    --role-mobile-stat-text: #f1f5f9;
    --role-mobile-card-title: #f8fafc;
    --role-mobile-card-meta: #94a3b8;
    --role-mobile-dashed-border: #2a2a2a;
    --role-mobile-action-btn-bg: #000000;
    --role-mobile-action-btn-color: #94a3b8;
}

/* Premium Table Container */
.premium-table-wrapper {
    background: var(--role-card-bg);
    border: 1px solid var(--role-card-border);
    border-radius: 16px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.premium-table {
    width: 100%;
    border-collapse: collapse;
    margin: 0;
}

.premium-table th {
    background: var(--role-table-header-bg);
    color: var(--role-text-muted);
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 16px 20px;
    border-bottom: 1px solid var(--role-card-border);
}

.premium-table td {
    padding: 16px 20px;
    border-bottom: 1px solid var(--role-card-border);
    color: var(--role-text-main);
    vertical-align: middle;
    transition: all 0.2s ease;
}

.premium-table tr:last-child td {
    border-bottom: none;
}

/* Row Hover & Selected States */
.premium-table tr {
    transition: all 0.2s ease;
}

.premium-table tr.selected td {
    background: rgba(37, 99, 235, 0.02) !important;
}

body.dark-mode .premium-table tr.selected td {
    background: #000000 !important;
}

.premium-table tr:hover td {
    background: var(--role-table-row-hover);
}

/* Status & Count Badges */
.role-badge {
    font-size: 0.72rem;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 20px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.role-badge.active {
    background: var(--role-badge-active-bg);
    color: var(--role-badge-active-color);
}

.role-badge.inactive {
    background: var(--role-badge-inactive-bg);
    color: var(--role-badge-inactive-color);
}

.agent-count-badge {
    font-size: 0.8rem;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 8px;
    background: var(--role-badge-count-bg);
    color: var(--role-badge-count-color);
    display: inline-block;
    min-width: 32px;
    text-align: center;
}

.agent-count-badge.active-agents {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
}

body.dark-mode .agent-count-badge.active-agents {
    background: rgba(16, 185, 129, 0.15);
    color: #34d399;
}

/* Buttons */
.btn-permissions {
    padding: 6px 14px;
    font-size: 0.8rem;
    font-weight: 600;
    border-radius: 8px;
    background: var(--role-badge-count-bg);
    color: var(--role-text-main);
    border: none;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.btn-permissions:hover {
    background: var(--role-btn-perm-hover-bg);
    color: var(--role-text-main);
    transform: translateY(-1px);
}

.btn-add-custom:hover {
    background: linear-gradient(135deg, #1d4ed8, #1e40af) !important;
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(37,99,235,0.3) !important;
}

.btn-actions-custom:hover {
    background: var(--role-btn-perm-hover-bg) !important;
}

/* Modal Styling overrides */
.modal-content {
    background: var(--role-card-bg) !important;
    border: 1px solid var(--role-card-border) !important;
    color: var(--role-text-main) !important;
}
.modal-header {
    border-bottom: 1px solid var(--role-card-border) !important;
}
.modal-footer {
    border-top: 1px solid var(--role-card-border) !important;
}
.modal-content .form-label {
    font-weight: 600;
    color: var(--role-text-muted);
    font-size: 0.85rem;
}
.modal-content .form-control {
    background: var(--role-stat-bg);
    border: 1px solid var(--role-card-border);
    color: var(--role-text-main);
    border-radius: 10px;
    padding: 10px 14px;
    transition: all 0.2s;
}
.modal-content .form-control:focus {
    background: var(--role-card-bg);
    border-color: #3b82f6;
    box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
}
.modal-content .btn-close {
    filter: var(--modal-close-filter);
}
.btn-cancel-custom {
    background: var(--role-stat-bg);
    border: 1px solid var(--role-card-border);
    color: var(--role-text-main);
}
.btn-cancel-custom:hover {
    background: var(--role-btn-perm-hover-bg) !important;
    color: var(--role-text-main);
}

body.dark-mode .dropdown-item:hover {
    background-color: #000000 !important;
    color: #ffffff !important;
}

/* ── Mobile Cards Styles ── */
.role-mobile-card {
    padding: 16px;
    background: var(--role-mobile-card-bg);
    position: relative;
    text-align: left;
}
.role-mobile-card .role-mobile-card-title {
    font-size: 1.1rem;
    font-weight: 800;
    color: var(--role-mobile-card-title);
    margin-bottom: 4px;
    line-height: 1.2;
}
.role-mobile-card .role-mobile-card-meta {
    font-size: 0.75rem;
    color: var(--role-mobile-card-meta);
    font-weight: 600;
    margin-bottom: 12px;
}
.role-mobile-card .role-stats-row {
    border-top: 1px dashed var(--role-mobile-dashed-border);
}
.role-stat-box {
    background: var(--role-mobile-stat-bg);
    border-radius: 8px;
    padding: 8px 12px;
}
.role-stat-box .stat-label {
    font-size: 0.65rem;
    color: var(--role-mobile-card-meta);
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 2px;
}
.role-stat-box .stat-value {
    font-size: 1rem;
    color: var(--role-mobile-stat-text);
    font-weight: 800;
}
.role-mobile-action-btn {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--role-mobile-action-btn-bg) !important;
    color: var(--role-mobile-action-btn-color) !important;
    border: none !important;
    text-decoration: none;
}

/* Responsive Table -> Cards for Mobile */
@media (max-width: 768px) {
    .premium-table-wrapper { border: none !important; overflow: visible !important; background: transparent !important; box-shadow: none !important; }
    .premium-table { display: block !important; width: 100% !important; }
    .premium-table thead { display: none !important; }
    .premium-table tbody { display: block !important; width: 100% !important; }
    .premium-table tbody tr {
        display: block !important;
        margin-bottom: 1rem !important;
        background: var(--role-mobile-card-bg) !important;
        border: 1px solid var(--role-mobile-card-border) !important;
        border-radius: 16px !important;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05) !important;
        overflow: hidden !important;
        transition: all 0.25s ease !important;
    }
    .premium-table tbody tr.selected {
        border-color: #2563eb !important;
    }
    body.dark-mode .premium-table tbody tr.selected {
        border-color: #404040 !important;
        background: #000000 !important;
    }
    .premium-table tbody td.d-md-none {
        display: block !important;
        width: 100% !important;
        padding: 0 !important;
        border: none !important;
    }
    .premium-table tbody td.d-none {
        display: none !important;
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
                    + '<div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4" role="alert" id="rolesClientError" aria-live="polite" data-alert-static="1">'
                    + '  <i class="bi bi-exclamation-triangle-fill me-2"></i><span id="rolesClientErrorText"></span>'
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

    // Toggle row styling when checked
    function syncRowSelection(checkbox) {
        var row = checkbox.closest('tr');
        if (row) {
            if (checkbox.checked) {
                row.classList.add('selected');
            } else {
                row.classList.remove('selected');
            }
        }
    }

    var checkboxes = document.querySelectorAll('.role-checkbox');
    checkboxes.forEach(function(cb) {
        syncRowSelection(cb);
        cb.addEventListener('change', function() {
            syncRowSelection(cb);
        });
        
        // Also allow clicking anywhere on the row body except on checkbox container and action buttons/links to select it
        var row = cb.closest('tr');
        if (row) {
            row.addEventListener('click', function(e) {
                // If the user clicked on a link, button, or the checkbox itself, don't trigger
                if (e.target.closest('a') || e.target.closest('button') || e.target === cb || e.target.closest('.role-checkbox')) {
                    return;
                }
                cb.checked = !cb.checked;
                cb.dispatchEvent(new Event('change'));
            });
        }
    });

    var selectAll = document.getElementById('selectAllRoles');
    if (selectAll) {
        selectAll.addEventListener('change', function(){
            var boxes = document.querySelectorAll('.role-checkbox');
            boxes.forEach(function(b){ 
                b.checked = selectAll.checked; 
                syncRowSelection(b);
            });
        });
    }

    var actionButtons = document.querySelectorAll('[data-role-action]');
    actionButtons.forEach(function(btn){
        btn.addEventListener('click', function(){
            var ids = getCheckedIds();
            var action = btn.getAttribute('data-role-action') || '';
            if (action === 'delete') {
                if (!requireAtLeastOneRoleSelected(ids, 'Debe seleccionar un rol para eliminar')) return;
                if (!confirm('¿Estás seguro de que deseas eliminar los roles seleccionados?')) return;
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
