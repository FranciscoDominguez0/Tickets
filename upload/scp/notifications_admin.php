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

// Asegurar CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Guardar
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF()) {
        $errors[] = 'Token de seguridad inválido.';
    } else {
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
        $resAll = $mysqli->query("SELECT id FROM staff WHERE is_active = 1 AND role = 'agent' ORDER BY firstname, lastname");
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

// Listar agentes
$agents = [];
$res = $mysqli->query("SELECT id, firstname, lastname, email FROM staff WHERE is_active = 1 AND role = 'agent' ORDER BY firstname, lastname");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $sid = (int)($row['id'] ?? 0);
        $agents[] = [
            'id' => $sid,
            'name' => trim((string)($row['firstname'] ?? '') . ' ' . (string)($row['lastname'] ?? '')),
            'email' => (string)($row['email'] ?? ''),
            'ticket' => ((string)getAppSetting('staff.' . $sid . '.email_ticket_assigned', '1') === '1'),
            'task' => ((string)getAppSetting('staff.' . $sid . '.email_task_assigned', '1') === '1'),
        ];
    }
}

ob_start();
?>

<div class="page-header">
    <h1>Notificaciones</h1>
    <p class="text-muted" style="margin:0;">Configura si cada agente recibe correos cuando se le asignen tickets o tareas. Las notificaciones dentro de la app siempre se enviarán.</p>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $e): ?>
            <div><?php echo html((string)$e); ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="card" style="border-radius: 14px;">
    <div class="card-body">
        <form method="post" action="notifications_admin.php">
            <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">

            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th>Agente</th>
                            <th>Correo</th>
                            <th style="width: 220px;">Email ticket asignado</th>
                            <th style="width: 220px;">Email tarea asignada</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($agents)): ?>
                            <tr><td colspan="4" class="text-muted">No hay agentes.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($agents as $a): ?>
                            <tr>
                                <td><strong><?php echo html($a['name'] !== '' ? $a['name'] : ('Agente #' . (int)$a['id'])); ?></strong></td>
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

            <div class="d-flex justify-content-end">
                <button type="submit" class="btn btn-primary">Guardar</button>
            </div>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once 'layout_admin.php';
exit;
