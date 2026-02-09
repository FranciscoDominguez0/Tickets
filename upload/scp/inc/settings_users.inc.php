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
        $user_max_login_attempts = (string)($_POST['user_max_login_attempts'] ?? '10');
        $user_lockout_minutes = (string)($_POST['user_lockout_minutes'] ?? '1');
        $user_session_timeout_minutes = (string)($_POST['user_session_timeout_minutes'] ?? '30');

        $registration_required = isset($_POST['registration_required']) ? '1' : '0';

        if (!ctype_digit($user_max_login_attempts) || (int)$user_max_login_attempts < 1 || (int)$user_max_login_attempts > 50) {
            $error = 'Intentos fallidos permitidos debe estar entre 1 y 50.';
        } elseif (!ctype_digit($user_lockout_minutes) || (int)$user_lockout_minutes < 0 || (int)$user_lockout_minutes > 120) {
            $error = 'Minutos de bloqueo debe estar entre 0 y 120.';
        } elseif (!ctype_digit($user_session_timeout_minutes) || (int)$user_session_timeout_minutes < 0 || (int)$user_session_timeout_minutes > 1440) {
            $error = 'Tiempo de sesión debe estar entre 0 y 1440 minutos.';
        } else {
            setAppSetting('users.max_login_attempts', $user_max_login_attempts);
            setAppSetting('users.lockout_minutes', $user_lockout_minutes);
            setAppSetting('users.session_timeout_minutes', $user_session_timeout_minutes);

            setAppSetting('users.registration_required', $registration_required);

            $msg = 'Cambios guardados correctamente.';
        }
    }
}

$user_max_login_attempts = (string)getAppSetting('users.max_login_attempts', '10');
$user_lockout_minutes = (string)getAppSetting('users.lockout_minutes', '1');
$user_session_timeout_minutes = (string)getAppSetting('users.session_timeout_minutes', '30');

$registration_required = (string)getAppSetting('users.registration_required', '0') === '1';

ob_start();
?>

<div class="settings-hero" id="settings">
    <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
        <div class="d-flex align-items-center gap-3">
            <span class="settings-hero-icon"><i class="bi bi-people"></i></span>
            <div>
                <h1>Usuarios</h1>
                <p>Ajustes de identificación y sesión de clientes</p>
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
            <div class="card-header"><strong>Configuración de Identificación</strong></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="registration_required" name="registration_required" value="1" <?php echo $registration_required ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="registration_required">Registro requerido</label>
                        </div>
                        <div class="form-text">Se requiere registrarse para crear Tickets.</div>
                    </div>

                    <div class="col-12 col-lg-6">
                        <label class="form-label">Inicios de sesión de usuario excesivo</label>
                        <div class="input-group">
                            <input type="number" class="form-control" name="user_max_login_attempts" value="<?php echo html($user_max_login_attempts); ?>" min="1" max="50">
                            <span class="input-group-text">intento(s)</span>
                        </div>
                        <div class="form-text">Intentos fallidos permitidos antes de bloquear temporalmente.</div>
                    </div>

                    <div class="col-12 col-lg-6">
                        <label class="form-label">Minutos de bloqueo</label>
                        <div class="input-group">
                            <input type="number" class="form-control" name="user_lockout_minutes" value="<?php echo html($user_lockout_minutes); ?>" min="0" max="120">
                            <span class="input-group-text">min</span>
                        </div>
                    </div>

                    <div class="col-12 col-lg-6">
                        <label class="form-label">Tiempo de sesión de un usuario</label>
                        <div class="input-group">
                            <input type="number" class="form-control" name="user_session_timeout_minutes" value="<?php echo html($user_session_timeout_minutes); ?>" min="0" max="1440">
                            <span class="input-group-text">min</span>
                        </div>
                        <div class="form-text">(0 para desactivar)</div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <div class="col-12 d-flex gap-2">
        <button type="submit" class="btn btn-primary">Guardar cambios</button>
        <a class="btn btn-outline-secondary" href="settings.php?t=users#settings">Restaurar</a>
    </div>
</form>

<?php
$content = ob_get_clean();
