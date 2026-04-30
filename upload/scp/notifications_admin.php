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
$currentRoute = 'notifications_admin';

$eid = empresaId();
$staffHasEmpresaId = false;
if (isset($mysqli) && $mysqli) {
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

// Solo admins
$meRole = '';
$meId = (int)($_SESSION['staff_id'] ?? 0);
if ($meId > 0) {
    $sqlMe = 'SELECT role FROM staff WHERE id = ?';
    if ($staffHasEmpresaId) {
        $sqlMe .= ' AND empresa_id = ?';
    }
    $sqlMe .= ' LIMIT 1';
    $stmtMe = $mysqli->prepare($sqlMe);
    if ($stmtMe) {
        if ($staffHasEmpresaId) {
            $stmtMe->bind_param('ii', $meId, $eid);
        } else {
            $stmtMe->bind_param('i', $meId);
        }
        if ($stmtMe->execute()) {
            $meRow = $stmtMe->get_result()->fetch_assoc();
            $meRole = (string)($meRow['role'] ?? '');
        }
    }
}
if ($meRole !== 'admin') {
    $_SESSION['flash_error'] = 'No tienes permisos para acceder a Notificaciones.';
    header('Location: index.php');
    exit;
}

$errors = [];
$msg = '';

$adminNotifyEmail = trim((string)getAppSetting('mail.admin_notify_email', defined('ADMIN_NOTIFY_EMAIL') ? (string)ADMIN_NOTIFY_EMAIL : ''));
$adminNotifyEnabled = ((string)getAppSetting('mail.admin_notify_enabled', '1') === '1');

// Asegurar CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Guardar
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF()) {
        $errors[] = 'Token de seguridad inválido.';
    } else {
        $adminNotifyEnabledNew = isset($_POST['admin_notify_enabled']) ? '1' : '0';
        setAppSetting('mail.admin_notify_enabled', $adminNotifyEnabledNew);
        $adminNotifyEnabled = ($adminNotifyEnabledNew === '1');

        $ticketArr = isset($_POST['email_ticket_assigned']) && is_array($_POST['email_ticket_assigned']) ? $_POST['email_ticket_assigned'] : [];
        $taskArr = isset($_POST['email_task_assigned']) && is_array($_POST['email_task_assigned']) ? $_POST['email_task_assigned'] : [];

        $staffIds = [];
        foreach ($ticketArr as $sid => $val) {
            if (is_numeric($sid)) $staffIds[(int)$sid] = true;
        }
        foreach ($taskArr as $sid => $val) {
            if (is_numeric($sid)) $staffIds[(int)$sid] = true;
        }

        // También permitir que se apaguen todos (si no vienen, se apaga)
        $resAll = $mysqli->query("SELECT id FROM staff WHERE is_active = 1 AND role IN ('agent','admin') ORDER BY firstname, lastname");
        if ($resAll) {
            while ($row = $resAll->fetch_assoc()) {
                $sid = (int)($row['id'] ?? 0);
                if ($sid > 0) $staffIds[$sid] = true;
            }
        }

        foreach (array_keys($staffIds) as $sid) {
            $tEnabled = isset($ticketArr[$sid]) && (string)$ticketArr[$sid] === '1';
            $k1 = 'staff.' . (int)$sid . '.email_ticket_assigned';
            setAppSetting($k1, $tEnabled ? '1' : '0');

            $taskEnabled = isset($taskArr[$sid]) && (string)$taskArr[$sid] === '1';
            $k2 = 'staff.' . (int)$sid . '.email_task_assigned';
            setAppSetting($k2, $taskEnabled ? '1' : '0');
        }

        $_SESSION['flash_msg'] = 'Preferencias actualizadas.';
        header('Location: notifications_admin.php');
        exit;
    }
}

// Listar agentes + administradores
$agents = [];
$sqlStaff = "SELECT id, firstname, lastname, email, role FROM staff WHERE is_active = 1 AND role IN ('agent','admin')";
if ($staffHasEmpresaId) {
    $sqlStaff .= ' AND empresa_id = ' . (int)$eid;
}
$sqlStaff .= ' ORDER BY role DESC, firstname, lastname';
$res = $mysqli->query($sqlStaff);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $sid = (int)($row['id'] ?? 0);
        $agents[] = [
            'id' => $sid,
            'name' => trim((string)($row['firstname'] ?? '') . ' ' . (string)($row['lastname'] ?? '')),
            'email' => (string)($row['email'] ?? ''),
            'role' => (string)($row['role'] ?? ''),
            'ticket' => ((string)getAppSetting('staff.' . $sid . '.email_ticket_assigned', '1') === '1'),
            'task' => ((string)getAppSetting('staff.' . $sid . '.email_task_assigned', '1') === '1'),
        ];
    }
}

ob_start();
?>

<div class="settings-hero">
    <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
        <div class="d-flex align-items-center gap-3">
            <span class="settings-hero-icon"><i class="bi bi-bell"></i></span>
            <div>
                <h1>Notificaciones</h1>
                <p>Preferencias de correos para asignaciones y notificaciones admin</p>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="badge bg-info"><?php echo (int)count($agents); ?> Staff</span>
            <span class="badge <?php echo $adminNotifyEnabled ? 'bg-success' : 'bg-secondary'; ?>">
                <?php echo $adminNotifyEnabled ? 'Admin email: ON' : 'Admin email: OFF'; ?>
            </span>
        </div>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $e): ?>
            <div><?php echo html((string)$e); ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="card settings-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong><i class="bi bi-sliders"></i> Preferencias</strong>
    </div>
    <div class="card-body">
        <form method="post" action="notifications_admin.php">
            <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">

            <div class="row g-3">
                <div class="col-12">
                    <div class="card" style="border-radius: 14px;">
                        <div class="card-body">
                            <div class="d-flex align-items-start justify-content-between flex-wrap gap-2">
                                <div>
                                    <div class="fw-semibold"><i class="bi bi-shield-lock me-1"></i> Email para notificaciones admin</div>
                                    <div class="text-muted small"><?php echo html($adminNotifyEmail !== '' ? $adminNotifyEmail : 'No configurado'); ?></div>
                                    <div class="form-text">Activa o desactiva el envío de correos al email configurado en Correos Electrónicos.</div>
                                </div>
                                <div class="form-check form-switch m-0">
                                    <input class="form-check-input" type="checkbox" role="switch" name="admin_notify_enabled" value="1" <?php echo $adminNotifyEnabled ? 'checked' : ''; ?>>
                                    <label class="form-check-label">Habilitado</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Staff</th>
                                    <th>Correo</th>
                                    <th style="width: 220px;">Email ticket asignado</th>
                                    <th style="width: 220px;">Email tarea asignada</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($agents)): ?>
                                    <tr><td colspan="4" class="text-center text-muted py-4">No hay registros.</td></tr>
                                <?php endif; ?>
                                <?php foreach ($agents as $a): ?>
                                    <tr>
                                        <!-- VISTA MÓVIL (Tarjeta Premium) -->
                                        <td class="d-md-none p-0">
                                            <div style="padding: 16px; background: #ffffff;">
                                                <div class="d-flex justify-content-between align-items-start mb-3">
                                                    <div class="d-flex align-items-center gap-3">
                                                        <div style="background: rgba(37,99,235,0.08); color: #2563eb; width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; font-weight: 800;">
                                                            <?php echo strtoupper(substr($a['name'] !== '' ? $a['name'] : 'U', 0, 1)); ?>
                                                        </div>
                                                        <div style="line-height: 1.2;">
                                                            <div style="font-weight: 800; color: #0f172a; font-size: 1.05rem;">
                                                                <?php echo html($a['name'] !== '' ? $a['name'] : ('Usuario #' . (int)$a['id'])); ?>
                                                            </div>
                                                            <div style="font-size: 0.8rem; color: #64748b; margin-top: 4px; font-weight: 500;">
                                                                <?php echo html($a['email']); ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <?php if (($a['role'] ?? '') !== ''): ?>
                                                        <span style="background: #f1f5f9; color: #475569; padding: 4px 10px; border-radius: 20px; font-size: 0.65rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px;">
                                                            <?php echo html((string)$a['role']); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>

                                                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 14px; padding: 16px;">
                                                    <div style="color: #64748b; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 14px;">
                                                        Avisos por Correo
                                                    </div>
                                                    
                                                    <div class="d-flex justify-content-between align-items-center mb-3 pb-3" style="border-bottom: 1px dashed #cbd5e1;">
                                                        <div style="font-size: 0.9rem; font-weight: 600; color: #1e293b;">
                                                            <i class="bi bi-ticket-detailed me-2" style="color: #2563eb;"></i>Nuevos Tickets
                                                        </div>
                                                        <div class="form-check form-switch m-0" style="padding-left: 0;">
                                                            <input class="form-check-input ms-0 shadow-sm" type="checkbox" role="switch" name="email_ticket_assigned[<?php echo (int)$a['id']; ?>]" value="1" <?php echo $a['ticket'] ? 'checked' : ''; ?> style="width: 2.8em; height: 1.4em; cursor: pointer;">
                                                        </div>
                                                    </div>

                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div style="font-size: 0.9rem; font-weight: 600; color: #1e293b;">
                                                            <i class="bi bi-list-check me-2" style="color: #10b981;"></i>Nuevas Tareas
                                                        </div>
                                                        <div class="form-check form-switch m-0" style="padding-left: 0;">
                                                            <input class="form-check-input ms-0 shadow-sm" type="checkbox" role="switch" name="email_task_assigned[<?php echo (int)$a['id']; ?>]" value="1" <?php echo $a['task'] ? 'checked' : ''; ?> style="width: 2.8em; height: 1.4em; cursor: pointer;">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- VISTA ESCRITORIO -->
                                        <td class="d-none d-md-table-cell">
                                            <strong><?php echo html($a['name'] !== '' ? $a['name'] : ('Usuario #' . (int)$a['id'])); ?></strong>
                                            <?php if (($a['role'] ?? '') !== ''): ?>
                                                <div class="text-muted small"><?php echo html((string)$a['role']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="d-none d-md-table-cell"><?php echo html($a['email']); ?></td>
                                        <td class="d-none d-md-table-cell">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" role="switch" name="email_ticket_assigned[<?php echo (int)$a['id']; ?>]" value="1" <?php echo $a['ticket'] ? 'checked' : ''; ?>>
                                            </div>
                                        </td>
                                        <td class="d-none d-md-table-cell">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" role="switch" name="email_task_assigned[<?php echo (int)$a['id']; ?>]" value="1" <?php echo $a['task'] ? 'checked' : ''; ?>>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end mt-3">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle"></i> Guardar</button>
            </div>
        </form>
    </div>
</div>

<style>
/* Responsive Table -> Cards for Mobile */
@media (max-width: 768px) {
    .settings-card { background: transparent !important; box-shadow: none !important; }
    .settings-card .card-header { border-radius: 12px; margin-bottom: 12px; }
    .settings-card .table-responsive { border: none !important; overflow: visible; }
    .settings-card .table { background: transparent; }
    .settings-card .table thead { display: none; }
    .settings-card .table tbody tr {
        display: block;
        margin-bottom: 1rem;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        overflow: hidden;
    }
    .settings-card .table tbody td {
        border: none !important;
        padding: 0 !important;
    }
}
</style>

<?php
$content = ob_get_clean();
require_once 'layout_admin.php';
exit;
