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

    $seedEmail = 'cuenta9fran@gmail.com';
    $stmt = $mysqli->prepare('SELECT id FROM email_accounts WHERE email = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('s', $seedEmail);
        $stmt->execute();
        $res = $stmt->get_result();
        $exists = $res && $res->fetch_assoc();
        $stmt->close();
        if (!$exists) {
            $stmtIns = $mysqli->prepare('INSERT INTO email_accounts (email, name, priority, dept_id, is_default, smtp_host, smtp_port, smtp_secure, smtp_user, smtp_pass, created, updated) VALUES (?, ?, ?, NULL, 0, NULL, NULL, NULL, NULL, NULL, NOW(), NOW())');
            if ($stmtIns) {
                $seedName = 'Notificaciones';
                $seedPriority = 'Normal';
                $stmtIns->bind_param('sss', $seedEmail, $seedName, $seedPriority);
                $stmtIns->execute();
                $stmtIns->close();
            }
        }
    }
}

$emailAccounts = [];
if (isset($mysqli) && $mysqli) {
    $res = $mysqli->query('SELECT id, email, name, is_default FROM email_accounts ORDER BY is_default DESC, id ASC');
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $emailAccounts[] = $r;
        }
    }
}

$findEmailAccountById = function ($id) use ($emailAccounts) {
    $id = (int)$id;
    foreach ($emailAccounts as $a) {
        if ((int)($a['id'] ?? 0) === $id) return $a;
    }
    return null;
};

// Guardar configuración global (en app_settings)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF()) {
        $_SESSION['flash_error'] = 'Token CSRF inválido.';
        header('Location: emailsettings.php');
        exit;
    }

    $mailFromId = (int)($_POST['mail_from_id'] ?? 0);
    $mailAlertFromId = (int)($_POST['mail_alert_from_id'] ?? 0);
    $adminNotifyId = (int)($_POST['admin_notify_id'] ?? 0);

    $fromAcc = $findEmailAccountById($mailFromId);
    $alertAcc = $findEmailAccountById($mailAlertFromId);
    $adminAcc = $findEmailAccountById($adminNotifyId);

    if (!$fromAcc) {
        $_SESSION['flash_error'] = 'Selecciona el correo remitente del sistema.';
        header('Location: emailsettings.php');
        exit;
    }
    if (!$alertAcc) {
        $_SESSION['flash_error'] = 'Selecciona el correo de alertas.';
        header('Location: emailsettings.php');
        exit;
    }
    if (!$adminAcc) {
        $_SESSION['flash_error'] = 'Selecciona el correo de notificaciones del administrador.';
        header('Location: emailsettings.php');
        exit;
    }

    $mailFrom = (string)($fromAcc['email'] ?? '');
    $mailFromName = trim((string)($fromAcc['name'] ?? ''));
    if ($mailFromName === '') $mailFromName = $mailFrom;

    $mailAlertFrom = (string)($alertAcc['email'] ?? '');
    $mailAlertFromName = trim((string)($alertAcc['name'] ?? 'Alerts'));
    if ($mailAlertFromName === '') $mailAlertFromName = 'Alerts';

    $adminNotify = (string)($adminAcc['email'] ?? '');

    setAppSetting('mail.from', $mailFrom);
    setAppSetting('mail.from_name', $mailFromName);
    setAppSetting('mail.alert_from', $mailAlertFrom);
    setAppSetting('mail.alert_from_name', $mailAlertFromName);
    setAppSetting('mail.admin_notify_email', $adminNotify);

    $_SESSION['flash_msg'] = 'Configuración guardada.';
    header('Location: emailsettings.php');
    exit;
}

// Valores actuales (fallback a config.php)
$valFrom = (string)getAppSetting('mail.from', defined('MAIL_FROM') ? (string)MAIL_FROM : '');
$valFromName = (string)getAppSetting('mail.from_name', defined('MAIL_FROM_NAME') ? (string)MAIL_FROM_NAME : '');
$valAlertFrom = (string)getAppSetting('mail.alert_from', defined('MAIL_FROM') ? (string)MAIL_FROM : '');
$valAlertFromName = (string)getAppSetting('mail.alert_from_name', 'Alerts');
$valAdminNotify = (string)getAppSetting('mail.admin_notify_email', defined('ADMIN_NOTIFY_EMAIL') ? (string)ADMIN_NOTIFY_EMAIL : '');

$selectedFromId = 0;
$selectedAlertFromId = 0;
$selectedAdminNotifyId = 0;
foreach ($emailAccounts as $a) {
    if ($selectedFromId === 0 && (string)($a['email'] ?? '') === $valFrom) $selectedFromId = (int)($a['id'] ?? 0);
    if ($selectedAlertFromId === 0 && (string)($a['email'] ?? '') === $valAlertFrom) $selectedAlertFromId = (int)($a['id'] ?? 0);
    if ($selectedAdminNotifyId === 0 && (string)($a['email'] ?? '') === $valAdminNotify) $selectedAdminNotifyId = (int)($a['id'] ?? 0);
}

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
                                <select name="mail_from_id" class="form-select">
                                    <?php foreach ($emailAccounts as $a): ?>
                                        <?php
                                        $aid = (int)($a['id'] ?? 0);
                                        $aEmail = (string)($a['email'] ?? '');
                                        $aName = trim((string)($a['name'] ?? ''));
                                        $label = ($aName !== '' ? $aName : $aEmail) . ' <' . $aEmail . '>';
                                        ?>
                                        <option value="<?php echo $aid; ?>" <?php echo $selectedFromId === $aid ? 'selected' : ''; ?>><?php echo html($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Nombre remitente (MAIL_FROM_NAME)</label>
                                <input type="text" class="form-control" value="<?php echo html($valFromName); ?>" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Correo electrónico de alerta por defecto</label>
                                <select name="mail_alert_from_id" class="form-select">
                                    <?php foreach ($emailAccounts as $a): ?>
                                        <?php
                                        $aid = (int)($a['id'] ?? 0);
                                        $aEmail = (string)($a['email'] ?? '');
                                        $aName = trim((string)($a['name'] ?? ''));
                                        $label = ($aName !== '' ? $aName : $aEmail) . ' <' . $aEmail . '>';
                                        ?>
                                        <option value="<?php echo $aid; ?>" <?php echo $selectedAlertFromId === $aid ? 'selected' : ''; ?>><?php echo html($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Nombre de alertas</label>
                                <input type="text" class="form-control" value="<?php echo html($valAlertFromName); ?>" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email para notificaciones admin (ADMIN_NOTIFY_EMAIL)</label>
                        <select name="admin_notify_id" class="form-select">
                            <?php foreach ($emailAccounts as $a): ?>
                                <?php
                                $aid = (int)($a['id'] ?? 0);
                                $aEmail = (string)($a['email'] ?? '');
                                $aName = trim((string)($a['name'] ?? ''));
                                $label = ($aName !== '' ? $aName : $aEmail) . ' <' . $aEmail . '>';
                                ?>
                                <option value="<?php echo $aid; ?>" <?php echo $selectedAdminNotifyId === $aid ? 'selected' : ''; ?>><?php echo html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
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
