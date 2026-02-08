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
$currentRoute = 'emails';
$emailTab = 'settings';

$collapseSettingsMenu = false;
if (!isset($_SESSION['admin_sidebar_menu_seen'])) {
    $_SESSION['admin_sidebar_menu_seen'] = 1;
    $collapseSettingsMenu = true;
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

// Guardar configuración global (en app_settings)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF()) {
        $_SESSION['flash_error'] = 'Token CSRF inválido.';
        header('Location: emailsettings.php');
        exit;
    }

    $mailFrom = trim((string)($_POST['mail_from'] ?? ''));
    $mailFromName = trim((string)($_POST['mail_from_name'] ?? ''));
    $adminNotify = trim((string)($_POST['admin_notify_email'] ?? ''));

    if ($mailFrom !== '' && !filter_var($mailFrom, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['flash_error'] = 'MAIL_FROM inválido.';
        header('Location: emailsettings.php');
        exit;
    }
    if ($adminNotify !== '' && !filter_var($adminNotify, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['flash_error'] = 'ADMIN_NOTIFY_EMAIL inválido.';
        header('Location: emailsettings.php');
        exit;
    }

    setAppSetting('mail.from', $mailFrom);
    setAppSetting('mail.from_name', $mailFromName);
    setAppSetting('mail.admin_notify_email', $adminNotify);

    $_SESSION['flash_msg'] = 'Configuración guardada.';
    header('Location: emailsettings.php');
    exit;
}

// Valores actuales (fallback a config.php)
$valFrom = (string)getAppSetting('mail.from', defined('MAIL_FROM') ? (string)MAIL_FROM : '');
$valFromName = (string)getAppSetting('mail.from_name', defined('MAIL_FROM_NAME') ? (string)MAIL_FROM_NAME : '');
$valAdminNotify = (string)getAppSetting('mail.admin_notify_email', defined('ADMIN_NOTIFY_EMAIL') ? (string)ADMIN_NOTIFY_EMAIL : '');

ob_start();
?>

<div class="settings-hero">
    <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
        <div class="d-flex align-items-center gap-3">
            <span class="settings-hero-icon"><i class="bi bi-envelope"></i></span>
            <div>
                <h1>Correos Electrónicos</h1>
                <p>Configuración global de correo</p>
            </div>
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

<div class="row">
    <div class="col-12">
        <div class="card settings-card">
            <div class="card-header">
                <strong><i class="bi bi-gear"></i> Ajustes globales</strong>
            </div>
            <div class="card-body">
                <form method="post" action="emailsettings.php">
                    <?php csrfField(); ?>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Email remitente (MAIL_FROM)</label>
                                <input type="email" name="mail_from" class="form-control" value="<?php echo html($valFrom); ?>" placeholder="noreply@tu-dominio.com">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Nombre remitente (MAIL_FROM_NAME)</label>
                                <input type="text" name="mail_from_name" class="form-control" value="<?php echo html($valFromName); ?>" placeholder="Sistema de Tickets">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email para notificaciones admin (ADMIN_NOTIFY_EMAIL)</label>
                        <input type="email" name="admin_notify_email" class="form-control" value="<?php echo html($valAdminNotify); ?>" placeholder="admin@tu-dominio.com">
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle"></i> Guardar</button>
                    </div>
                </form>

                <hr>

                <div class="alert alert-warning mb-0">
                    <div class="fw-semibold">Nota</div>
                    <div class="small">Actualmente el SMTP se configura por cuenta en <strong>Correos</strong>. En el siguiente paso se conectará el envío real para usar el email por defecto de la base de datos.</div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once 'layout_admin.php';
?>
