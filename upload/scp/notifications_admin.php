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
    $stmtMe = $mysqli->prepare('SELECT role FROM staff WHERE id = ? LIMIT 1');
    if ($stmtMe) {
        $stmtMe->bind_param('i', $meId);
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
$res = $mysqli->query("SELECT id, firstname, lastname, email, role FROM staff WHERE is_active = 1 AND role IN ('agent','admin') ORDER BY role DESC, firstname, lastname");
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
                                        <td>
                                            <strong><?php echo html($a['name'] !== '' ? $a['name'] : ('Usuario #' . (int)$a['id'])); ?></strong>
                                            <?php if (($a['role'] ?? '') !== ''): ?>
                                                <div class="text-muted small"><?php echo html((string)$a['role']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo html($a['email']); ?></td>
                                        <td>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" role="switch" name="email_ticket_assigned[<?php echo (int)$a['id']; ?>]" value="1" <?php echo $a['ticket'] ? 'checked' : ''; ?>>
                                            </div>
                                        </td>
                                        <td>
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

<?php
$content = ob_get_clean();
require_once 'layout_admin.php';
exit;
