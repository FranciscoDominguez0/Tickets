<?php
// Módulo: Notificaciones (preferencias de correos por asignación)

$notif_errors = [];
$notif_success = '';

$staff_id = (int)($_SESSION['staff_id'] ?? 0);
if ($staff_id <= 0) {
    $notif_errors[] = 'Sesión inválida.';
}

$kTicket = 'staff.' . $staff_id . '.email_ticket_assigned';
$kTask = 'staff.' . $staff_id . '.email_task_assigned';

$ticketEnabled = ((string)getAppSetting($kTicket, '1') === '1');
$taskEnabled = ((string)getAppSetting($kTask, '1') === '1');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCSRF($_POST['csrf_token'] ?? '')) {
        $notif_errors[] = 'Token de seguridad inválido.';
    } elseif ($staff_id <= 0) {
        $notif_errors[] = 'Sesión inválida.';
    } else {
        $ticketEnabled = isset($_POST['email_ticket_assigned']) && (string)$_POST['email_ticket_assigned'] === '1';
        $taskEnabled = isset($_POST['email_task_assigned']) && (string)$_POST['email_task_assigned'] === '1';

        $ok1 = setAppSetting($kTicket, $ticketEnabled ? '1' : '0');
        $ok2 = setAppSetting($kTask, $taskEnabled ? '1' : '0');

        if ($ok1 && $ok2) {
            $notif_success = 'Preferencias guardadas.';
            $_SESSION['flash_msg'] = $notif_success;
            header('Location: notifications.php');
            exit;
        }
        $notif_errors[] = 'No se pudieron guardar las preferencias.';
    }
}

?>

<div class="page-head">
    <div>
        <h1>Notificaciones</h1>
        <div class="text-muted">Controla si deseas recibir correos cuando te asignen tickets o tareas. Las notificaciones dentro de la app siempre se enviarán.</div>
    </div>
</div>

<?php if (!empty($notif_errors)): ?>
    <div class="alert alert-danger">
        <?php foreach ((array)$notif_errors as $e): ?>
            <div><?php echo html((string)$e); ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="card" style="border-radius: 14px;">
    <div class="card-body">
        <form method="post" action="notifications.php">
            <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">

            <div class="form-check form-switch" style="margin-bottom: 14px;">
                <input class="form-check-input" type="checkbox" role="switch" id="email_ticket_assigned" name="email_ticket_assigned" value="1" <?php echo $ticketEnabled ? 'checked' : ''; ?>>
                <label class="form-check-label" for="email_ticket_assigned"><strong>Correo al asignar tickets</strong></label>
                <div class="text-muted" style="font-size: 0.9rem; margin-top: 4px;">Si está activo, recibirás un correo cuando se te asigne un ticket.</div>
            </div>

            <div class="form-check form-switch" style="margin-bottom: 14px;">
                <input class="form-check-input" type="checkbox" role="switch" id="email_task_assigned" name="email_task_assigned" value="1" <?php echo $taskEnabled ? 'checked' : ''; ?>>
                <label class="form-check-label" for="email_task_assigned"><strong>Correo al asignar tareas</strong></label>
                <div class="text-muted" style="font-size: 0.9rem; margin-top: 4px;">Si está activo, recibirás un correo cuando se te asigne una tarea.</div>
            </div>

            <div class="d-flex justify-content-end">
                <button type="submit" class="btn btn-primary">Guardar</button>
            </div>
        </form>
    </div>
</div>
