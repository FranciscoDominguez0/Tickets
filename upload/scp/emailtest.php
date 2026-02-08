<?php
require_once '../../config.php';
require_once '../../includes/helpers.php';
require_once '../../includes/Auth.php';
require_once '../../includes/Mailer.php';

if (!isset($_SESSION['staff_id'])) {
    header('Location: login.php');
    exit;
}

requireLogin('agente');
$staff = getCurrentUser();
$currentRoute = 'emails';
$emailTab = 'test';

$collapseSettingsMenu = false;
if (!isset($_SESSION['admin_sidebar_menu_seen'])) {
    $_SESSION['admin_sidebar_menu_seen'] = 1;
    $collapseSettingsMenu = true;
}

// Asegurar tabla cuentas (para diagnóstico visual)
if (isset($mysqli) && $mysqli) {
    $mysqli->query("CREATE TABLE IF NOT EXISTS email_accounts (\n"
        . "  id INT PRIMARY KEY AUTO_INCREMENT,\n"
        . "  email VARCHAR(255) NOT NULL,\n"
        . "  name VARCHAR(255) NULL,\n"
        . "  priority VARCHAR(32) NULL,\n"
        . "  dept_id INT NULL,\n"
        . "  is_default TINYINT(1) NOT NULL DEFAULT 0,\n"
        . "  smtp_host VARCHAR(255) NULL,\n"
        . "  smtp_port INT NULL,\n"
        . "  smtp_secure VARCHAR(10) NULL,\n"
        . "  smtp_user VARCHAR(255) NULL,\n"
        . "  smtp_pass VARCHAR(255) NULL,\n"
        . "  created DATETIME DEFAULT CURRENT_TIMESTAMP,\n"
        . "  updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
        . "  KEY idx_email (email),\n"
        . "  KEY idx_default (is_default),\n"
        . "  KEY idx_dept (dept_id)\n"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
}

$msg = '';
$error = '';

$defaultAccount = null;
if (isset($mysqli) && $mysqli) {
    $res = $mysqli->query('SELECT * FROM email_accounts WHERE is_default = 1 LIMIT 1');
    if ($res) $defaultAccount = $res->fetch_assoc();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF()) {
        $error = 'Token CSRF inválido.';
    } else {
        $to = trim((string)($_POST['to'] ?? ''));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $error = 'Email destino inválido.';
        } else {
            $subject = 'Prueba de correo - ' . (defined('APP_NAME') ? APP_NAME : 'Sistema');
            $htmlBody = '<p>Este es un correo de prueba enviado desde el módulo de diagnóstico.</p>'
                . '<p><strong>Fecha:</strong> ' . html(date('Y-m-d H:i:s')) . '</p>';
            $ok = Mailer::send($to, $subject, $htmlBody);
            if ($ok) {
                $msg = 'Correo de prueba enviado correctamente.';
            } else {
                $error = 'Falló el envío: ' . (Mailer::$lastError ?: 'Error desconocido');
            }
        }
    }
}

ob_start();
?>

<div class="settings-hero">
    <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
        <div class="d-flex align-items-center gap-3">
            <span class="settings-hero-icon"><i class="bi bi-envelope"></i></span>
            <div>
                <h1>Correos Electrónicos</h1>
                <p>Diagnóstico / Envío de prueba</p>
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
    <div class="col-12 col-lg-7">
        <div class="card settings-card">
            <div class="card-header">
                <strong><i class="bi bi-send"></i> Enviar correo de prueba</strong>
            </div>
            <div class="card-body">
                <form method="post" action="emailtest.php">
                    <?php csrfField(); ?>
                    <div class="mb-3">
                        <label class="form-label">Enviar a</label>
                        <input type="email" name="to" class="form-control" required placeholder="destino@correo.com">
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-envelope"></i> Enviar</button>
                </form>
                <div class="text-muted small mt-3">
                    Si falla, revisa el error mostrado y la configuración SMTP de tu cuenta por defecto.
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-5">
        <div class="card settings-card">
            <div class="card-header">
                <strong><i class="bi bi-info-circle"></i> Datos actuales</strong>
            </div>
            <div class="card-body">
                <div class="mb-2"><strong>MAIL_FROM:</strong> <?php echo html(defined('MAIL_FROM') ? (string)MAIL_FROM : ''); ?></div>
                <div class="mb-2"><strong>MAIL_FROM_NAME:</strong> <?php echo html(defined('MAIL_FROM_NAME') ? (string)MAIL_FROM_NAME : ''); ?></div>
                <div class="mb-2"><strong>SMTP_HOST:</strong> <?php echo html(defined('SMTP_HOST') ? (string)SMTP_HOST : ''); ?></div>
                <div class="mb-2"><strong>SMTP_PORT:</strong> <?php echo html(defined('SMTP_PORT') ? (string)SMTP_PORT : ''); ?></div>
                <div class="mb-2"><strong>SMTP_SECURE:</strong> <?php echo html(defined('SMTP_SECURE') ? (string)SMTP_SECURE : ''); ?></div>
                <div class="mb-2"><strong>SMTP_USER:</strong> <?php echo html(defined('SMTP_USER') ? (string)SMTP_USER : ''); ?></div>

                <hr>

                <div class="fw-semibold mb-2">Cuenta por defecto (BD)</div>
                <?php if (!$defaultAccount): ?>
                    <div class="text-muted">No hay cuenta por defecto en la base de datos.</div>
                <?php else: ?>
                    <div class="mb-2"><strong>Email:</strong> <?php echo html((string)$defaultAccount['email']); ?></div>
                    <div class="mb-2"><strong>SMTP Host:</strong> <?php echo html((string)($defaultAccount['smtp_host'] ?? '')); ?></div>
                    <div class="mb-2"><strong>Puerto:</strong> <?php echo html((string)($defaultAccount['smtp_port'] ?? '')); ?></div>
                    <div class="mb-2"><strong>Seguridad:</strong> <?php echo html((string)($defaultAccount['smtp_secure'] ?? '')); ?></div>
                    <div class="mb-2"><strong>Usuario:</strong> <?php echo html((string)($defaultAccount['smtp_user'] ?? '')); ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once 'layout_admin.php';
?>
