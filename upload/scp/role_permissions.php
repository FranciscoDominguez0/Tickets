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

$ensureRolesTable = function () {
    return true;
};
$ensureRolesTable();

$ensureRolePermissionsTable = function () {
    return true;
};
$ensureRolePermissionsTable();

$roleName = trim((string)($_GET['role'] ?? ''));
if ($roleName === '') {
    $_SESSION['flash_error'] = 'Rol inválido.';
    header('Location: roles.php');
    exit;
}

$rolesHasEmpresaId = true;

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
        'ticket.view_all' => ['title' => 'Ver todo', 'desc' => 'Habilidad para ver todos los tickets (si está desactivado, el agente solo verá los asignados a él)'],
        'ticket.reports' => ['title' => 'Reportes de tickets', 'desc' => 'Habilidad para acceder a la página de reportes de tickets'],
    ],
    'Tareas' => [
        'task.create' => ['title' => 'Crear', 'desc' => 'Crear tareas'],
        'task.edit' => ['title' => 'Editar', 'desc' => 'Editar tareas'],
        'task.close' => ['title' => 'Cerrar', 'desc' => 'Cerrar tareas'],
        'task.assign' => ['title' => 'Asignar', 'desc' => 'Asignar tareas'],
        'task.delete' => ['title' => 'Eliminar', 'desc' => 'Eliminar tareas'],
    ],
    'Directorio, Mapa y Estadísticas' => [
        'user.view' => ['title' => 'Ver usuarios', 'desc' => 'Habilidad para ver el directorio de usuarios'],
        'user.manage' => ['title' => 'Gestionar usuarios', 'desc' => 'Habilidad para crear, editar, eliminar y realizar acciones en usuarios'],
        'org.view' => ['title' => 'Ver organizaciones', 'desc' => 'Habilidad para ver el listado y detalles de organizaciones'],
        'org.manage' => ['title' => 'Gestionar organizaciones', 'desc' => 'Habilidad para crear, editar y eliminar organizaciones'],
        'org.reports' => ['title' => 'Informes a jefes', 'desc' => 'Crear y enviar informes a jefes de organización en su panel'],
        'agent.directory' => ['title' => 'Ver directorio de agentes', 'desc' => 'Habilidad para acceder al directorio del agente'],
        'agent.map' => ['title' => 'Ver mapa de agentes', 'desc' => 'Habilidad para acceder al mapa de agentes en tiempo real'],
        'stats.view' => ['title' => 'Ver estadísticas', 'desc' => 'Habilidad para visualizar el panel de estadísticas.'],
    ],
    'Administración' => [
        'admin.access' => ['title' => 'Acceso al Panel de Administración', 'desc' => 'Habilidad para acceder a la configuración, agentes, roles y configuraciones de correo electrónico.'],
    ],
    'Cotizaciones' => [
        'quote.view' => ['title' => 'Acceso a Cotizaciones', 'desc' => 'Habilidad para ver y gestionar cotizaciones.'],
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

if (!function_exists('renderPermissionGroupCard')) {
    function renderPermissionGroupCard($groupTitle, $perms, $enabledPerms) {
        $icon = 'bi-shield-lock-fill';
        $headerColor = '#64748b';
        $bgColor = '#f8fafc';
        if ($groupTitle === 'Tickets') {
            $icon = 'bi-ticket-detailed-fill';
            $headerColor = '#2563eb';
            $bgColor = 'rgba(37, 99, 235, 0.04)';
        } elseif ($groupTitle === 'Tareas') {
            $icon = 'bi-check2-square';
            $headerColor = '#10b981';
            $bgColor = 'rgba(16, 185, 129, 0.04)';
        } elseif ($groupTitle === 'Directorio, Mapa y Estadísticas') {
            $icon = 'bi-compass-fill';
            $headerColor = '#8b5cf6';
            $bgColor = 'rgba(139, 92, 246, 0.04)';
        } elseif ($groupTitle === 'Administración') {
            $icon = 'bi-sliders';
            $headerColor = '#dc2626';
            $bgColor = 'rgba(220, 38, 38, 0.04)';
        } elseif ($groupTitle === 'Cotizaciones') {
            $icon = 'bi-file-earmark-text-fill';
            $headerColor = '#ea580c';
            $bgColor = 'rgba(234, 88, 12, 0.04)';
        }
        $groupId = 'group_' . preg_replace('/[^a-z0-9]/', '', strtolower($groupTitle));
        ?>
        <div class="card border-0 shadow-sm mb-4" style="border-radius: 16px; overflow: hidden; background: #fff;">
            <!-- Cabecera del Grupo -->
            <div class="card-header border-0 d-flex align-items-center justify-content-between py-3 px-4" style="background: <?php echo $bgColor; ?>;">
                <div class="d-flex align-items-center gap-2">
                    <span class="fs-5" style="color: <?php echo $headerColor; ?>;"><i class="bi <?php echo $icon; ?>"></i></span>
                    <h2 class="h6 mb-0 fw-bold text-dark" style="font-size: 1.05rem; letter-spacing: -0.01em;"><?php echo html($groupTitle); ?></h2>
                </div>
                <div class="form-check form-switch mb-0 d-flex align-items-center gap-2">
                    <label class="form-check-label text-muted fw-semibold" style="font-size: 0.8rem; cursor: pointer; user-select: none;" for="<?php echo $groupId; ?>_toggle">Marcar todos</label>
                    <input class="form-check-input group-toggler-switch" type="checkbox" id="<?php echo $groupId; ?>_toggle" data-group-class="<?php echo $groupId; ?>" style="cursor: pointer;">
                </div>
            </div>
            <!-- Cuerpo del Grupo -->
            <div class="card-body p-4">
                <div class="d-flex flex-column gap-3">
                    <?php foreach ($perms as $key => $meta): ?>
                        <?php $checked = isset($enabledPerms[$key]); ?>
                        <div class="perm-item-card p-3 d-flex align-items-start justify-content-between gap-3" 
                             onclick="togglePermSwitch(this, '<?php echo html($key); ?>')">
                            <div style="min-width: 0;">
                                <div class="fw-bold text-dark mb-1" style="font-size: 0.92rem;"><?php echo html($meta['title']); ?></div>
                                <p class="text-muted mb-0" style="font-size: 0.82rem; line-height: 1.4;"><?php echo html($meta['desc']); ?></p>
                            </div>
                            <div class="form-check form-switch form-switch-xl pt-1" onclick="event.stopPropagation();">
                                <input class="form-check-input perm-checkbox <?php echo $groupId; ?>" type="checkbox" name="perms[]" id="perm_<?php echo html($key); ?>" value="<?php echo html($key); ?>" <?php echo $checked ? 'checked' : ''; ?>>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php
    }
}

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

<style>
    .perm-item-card {
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        background: #fff;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        cursor: pointer;
        user-select: none;
    }
    .perm-item-card:hover {
        transform: translateY(-2px);
        border-color: #cbd5e1 !important;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.04);
    }
    .btn-reset-custom {
        background: transparent;
        color: #64748b;
        transition: all 0.2s;
    }
    .btn-reset-custom:hover {
        background: rgba(100, 116, 139, 0.08) !important;
        color: #1e293b !important;
    }
    .btn-save-custom {
        border-radius: 10px;
        background: linear-gradient(135deg, #2563eb, #1d4ed8);
        box-shadow: 0 4px 12px rgba(37,99,235,0.25);
        border: 0;
        transition: all 0.2s;
    }
    .btn-save-custom:hover {
        background: linear-gradient(135deg, #1d4ed8, #1e40af) !important;
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(37,99,235,0.35);
    }
    /* Estilos extras para los switches grandes de Bootstrap */
    .form-switch.form-switch-xl .form-check-input {
        width: 2.8em;
        height: 1.45em;
        cursor: pointer;
    }

    /* === MODO OSCURO SOPORTE === */
    body.dark-mode .card {
        background: #111111 !important;
        border: 1px solid #2a2a2a !important;
    }
    body.dark-mode .card-header {
        background: #161616 !important;
        border-bottom: 1px solid #2a2a2a !important;
    }
    body.dark-mode .card-header h2 {
        color: #e5e5e5 !important;
    }
    body.dark-mode .card-header .text-muted {
        color: #888 !important;
    }
    body.dark-mode .perm-item-card {
        background: #1a1a1a !important;
        border-color: #2a2a2a !important;
    }
    body.dark-mode .perm-item-card:hover {
        border-color: #404040 !important;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.35) !important;
    }
    body.dark-mode .perm-item-card .text-dark {
        color: #e5e5e5 !important;
    }
    body.dark-mode .perm-item-card .text-muted {
        color: #888 !important;
    }
    body.dark-mode .btn-reset-custom {
        color: #888;
    }
    body.dark-mode .btn-reset-custom:hover {
        background: rgba(255, 255, 255, 0.06) !important;
        color: #e5e5e5 !important;
    }
    body.dark-mode .action-bar-container {
        background: #111111 !important;
        border-color: #2a2a2a !important;
    }
</style>

<div class="p-1">
    <form method="post" action="role_permissions.php?role=<?php echo urlencode($roleName); ?>">
        <?php csrfField(); ?>
        <input type="hidden" name="do" value="save">

        <div class="row g-4">
            <!-- Columna Izquierda: Tickets -->
            <div class="col-12 col-xl-6">
                <?php 
                if (isset($permissionGroups['Tickets'])) {
                    renderPermissionGroupCard('Tickets', $permissionGroups['Tickets'], $enabledPerms);
                }
                ?>
            </div>

            <!-- Columna Derecha: Tareas + Directorio / Mapa -->
            <div class="col-12 col-xl-6">
                <div class="d-flex flex-column">
                    <?php 
                    if (isset($permissionGroups['Tareas'])) {
                        renderPermissionGroupCard('Tareas', $permissionGroups['Tareas'], $enabledPerms);
                    }
                    if (isset($permissionGroups['Cotizaciones'])) {
                        renderPermissionGroupCard('Cotizaciones', $permissionGroups['Cotizaciones'], $enabledPerms);
                    }
                    if (isset($permissionGroups['Directorio, Mapa y Estadísticas'])) {
                        renderPermissionGroupCard('Directorio, Mapa y Estadísticas', $permissionGroups['Directorio, Mapa y Estadísticas'], $enabledPerms);
                    }
                    if (isset($permissionGroups['Administración'])) {
                        renderPermissionGroupCard('Administración', $permissionGroups['Administración'], $enabledPerms);
                    }
                    ?>
                </div>
            </div>
        </div>

        <!-- Barra de acciones -->
        <div class="d-flex flex-column flex-md-row align-items-stretch align-items-md-center justify-content-between gap-3 mt-4 p-3 p-md-4 action-bar-container" style="background: #f8fafc; border-radius: 16px; border: 1px solid #e2e8f0;">
            <div class="order-2 order-md-1 text-center text-md-start">
                <a href="roles.php" class="btn btn-link text-decoration-none text-muted fw-bold px-3 py-2" style="font-size: 0.9rem;"><i class="bi bi-arrow-left me-2"></i> Cancelar y Volver</a>
            </div>
            <div class="order-1 order-md-2 d-flex flex-column flex-sm-row gap-2">
                <button type="button" class="btn btn-reset-custom px-4 py-2 fw-bold w-100" onclick="confirmReset()" style="font-size: 0.9rem;">Restablecer</button>
                <button type="submit" class="btn btn-primary btn-save-custom px-4 py-2 fw-bold w-100" style="font-size: 0.9rem;">Guardar cambios</button>
            </div>
        </div>
    </form>
</div>

<form id="resetForm" method="post" action="role_permissions.php?role=<?php echo urlencode($roleName); ?>" style="display: none;">
    <?php csrfField(); ?>
    <input type="hidden" name="do" value="reset">
</form>

<script>
function togglePermSwitch(card, key) {
    var checkbox = document.getElementById('perm_' + key);
    if (checkbox) {
        checkbox.checked = !checkbox.checked;
        checkbox.dispatchEvent(new Event('change'));
        updateCardStyle(card, checkbox.checked);
    }
}

function updateCardStyle(card, isChecked) {
    var isDarkMode = document.body.classList.contains('dark-mode');
    if (isChecked) {
        if (isDarkMode) {
            card.style.borderColor = '#404040';
            card.style.background = '#222222';
            card.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.25)';
        } else {
            card.style.borderColor = '#93c5fd';
            card.style.background = '#f8fafc';
            card.style.boxShadow = '0 4px 12px rgba(37, 99, 235, 0.03)';
        }
    } else {
        if (isDarkMode) {
            card.style.borderColor = '#2a2a2a';
            card.style.background = '#1a1a1a';
            card.style.boxShadow = 'none';
        } else {
            card.style.borderColor = '#e2e8f0';
            card.style.background = '#fff';
            card.style.boxShadow = 'none';
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Sincronizar estilo inicial de las tarjetas
    document.querySelectorAll('.perm-item-card').forEach(function(card) {
        var checkbox = card.querySelector('.perm-checkbox');
        if (checkbox) {
            updateCardStyle(card, checkbox.checked);
            checkbox.addEventListener('change', function() {
                updateCardStyle(card, checkbox.checked);
                var classes = checkbox.className.split(' ');
                var groupClass = classes.find(function(c) { return c.startsWith('group_'); });
                updateGroupToggler(groupClass);
            });
        }
    });

    // Controladores de "Marcar todos"
    document.querySelectorAll('.group-toggler-switch').forEach(function(toggler) {
        var groupClass = toggler.getAttribute('data-group-class');
        updateGroupToggler(groupClass);

        toggler.addEventListener('change', function() {
            var checked = this.checked;
            document.querySelectorAll('.' + groupClass).forEach(function(checkbox) {
                if (checkbox.checked !== checked) {
                    checkbox.checked = checked;
                    checkbox.dispatchEvent(new Event('change'));
                }
            });
        });
    });

    function updateGroupToggler(groupClass) {
        if (!groupClass) return;
        var toggler = document.querySelector('.group-toggler-switch[data-group-class="' + groupClass + '"]');
        if (!toggler) return;
        var checkboxes = document.querySelectorAll('.' + groupClass);
        if (checkboxes.length === 0) return;
        var allChecked = true;
        checkboxes.forEach(function(cb) {
            if (!cb.checked) allChecked = false;
        });
        toggler.checked = allChecked;
    }
});

function confirmReset() {
    if (confirm('¿Estás seguro de que deseas restablecer todos los permisos de este rol?')) {
        document.getElementById('resetForm').submit();
    }
}
</script>

<?php
$content = ob_get_clean();
require_once 'layout_admin.php';
?>
