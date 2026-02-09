<?php

if (isset($_SESSION['flash_msg'])) {
    $msg = (string)$_SESSION['flash_msg'];
    unset($_SESSION['flash_msg']);
}
if (isset($_SESSION['flash_error'])) {
    $error = (string)$_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

if ($_POST) {
    if (!validateCSRF()) {
        $error = 'Token de seguridad inválido';
    } else {
        $staff_max_login_attempts = (string)($_POST['staff_max_login_attempts'] ?? '4');
        $staff_lockout_minutes = (string)($_POST['staff_lockout_minutes'] ?? '2');
        $staff_session_timeout_minutes = (string)($_POST['staff_session_timeout_minutes'] ?? '30');
        $staff_bind_session_ip = isset($_POST['staff_bind_session_ip']) ? '1' : '0';

        if (!ctype_digit($staff_max_login_attempts) || (int)$staff_max_login_attempts < 1 || (int)$staff_max_login_attempts > 20) {
            $error = 'Intentos fallidos permitidos debe estar entre 1 y 20.';
        } elseif (!ctype_digit($staff_lockout_minutes) || (int)$staff_lockout_minutes < 0 || (int)$staff_lockout_minutes > 120) {
            $error = 'Minutos de bloqueo debe estar entre 0 y 120.';
        } elseif (!ctype_digit($staff_session_timeout_minutes) || (int)$staff_session_timeout_minutes < 0 || (int)$staff_session_timeout_minutes > 1440) {
            $error = 'Tiempo de desconexión debe estar entre 0 y 1440 minutos.';
        } else {
            setAppSetting('agents.max_login_attempts', $staff_max_login_attempts);
            setAppSetting('agents.lockout_minutes', $staff_lockout_minutes);
            setAppSetting('agents.session_timeout_minutes', $staff_session_timeout_minutes);
            setAppSetting('agents.bind_session_ip', $staff_bind_session_ip);

            $msg = 'Cambios guardados correctamente.';
        }
    }
}

$staff_max_login_attempts = (string)getAppSetting('agents.max_login_attempts', '4');
$staff_lockout_minutes = (string)getAppSetting('agents.lockout_minutes', '2');
$staff_session_timeout_minutes = (string)getAppSetting('agents.session_timeout_minutes', '30');
$staff_bind_session_ip = (string)getAppSetting('agents.bind_session_ip', '0') === '1';

ob_start();
?>

<div class="settings-hero" id="settings">
    <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
        <div class="d-flex align-items-center gap-3">
            <span class="settings-hero-icon"><i class="bi bi-person-badge"></i></span>
            <div>
                <h1>Agentes</h1>
                <p>Ajustes importantes de seguridad y sesión</p>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo html($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (!empty($msg)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo html($msg); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<form method="post" class="row g-3">
    <?php csrfField(); ?>

    <div class="col-12">
        <div class="card settings-card">
            <div class="card-header"><strong>Identificación y sesión</strong></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-lg-6">
                        <label class="form-label">Excesivas identificaciones de un Agente</label>
                        <div class="input-group">
                            <input type="number" class="form-control" name="staff_max_login_attempts" value="<?php echo html($staff_max_login_attempts); ?>" min="1" max="20">
                            <span class="input-group-text">intento(s)</span>
                        </div>
                        <div class="form-text">Intentos fallidos permitidos antes de bloquear temporalmente.</div>
                    </div>

                    <div class="col-12 col-lg-6">
                        <label class="form-label">Minutos de bloqueo</label>
                        <div class="input-group">
                            <input type="number" class="form-control" name="staff_lockout_minutes" value="<?php echo html($staff_lockout_minutes); ?>" min="0" max="120">
                            <span class="input-group-text">min</span>
                        </div>
                    </div>

                    <div class="col-12 col-lg-6">
                        <label class="form-label">Tiempo de desconexión de la cuenta de un agente</label>
                        <div class="input-group">
                            <input type="number" class="form-control" name="staff_session_timeout_minutes" value="<?php echo html($staff_session_timeout_minutes); ?>" min="0" max="1440">
                            <span class="input-group-text">min</span>
                        </div>
                        <div class="form-text">(0 para desactivar)</div>
                    </div>

                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="staff_bind_session_ip" name="staff_bind_session_ip" value="1" <?php echo $staff_bind_session_ip ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="staff_bind_session_ip">Unir la sesión de un agente a una IP</label>
                        </div>
                        <div class="form-text">Si cambia la IP durante la sesión, se cerrará la sesión.</div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <div class="col-12 d-flex gap-2">
        <button type="submit" class="btn btn-primary">Guardar cambios</button>
        <a class="btn btn-outline-secondary" href="settings.php?t=agents#settings">Restaurar</a>
    </div>
</form>

<?php
$content = ob_get_clean();
