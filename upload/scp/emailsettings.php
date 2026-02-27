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

$eid = empresaId();
$emailAccHasEmpresa = false;
if (isset($mysqli) && $mysqli) {
    try {
        $colE = $mysqli->query("SHOW COLUMNS FROM email_accounts LIKE 'empresa_id'");
        $emailAccHasEmpresa = ($colE && $colE->num_rows > 0);
    } catch (Throwable $e) {
        $emailAccHasEmpresa = false;
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
        . "  empresa_id INT NULL,\n"
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
        . "  KEY idx_dept (dept_id),\n"
        . "  KEY idx_empresa (empresa_id)\n"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    try {
        $colE = $mysqli->query("SHOW COLUMNS FROM email_accounts LIKE 'empresa_id'");
        $emailAccHasEmpresa = ($colE && $colE->num_rows > 0);
        if (!$emailAccHasEmpresa) {
            $mysqli->query("ALTER TABLE email_accounts ADD COLUMN empresa_id INT NULL");
            $mysqli->query("ALTER TABLE email_accounts ADD INDEX idx_empresa (empresa_id)");
            $colE = $mysqli->query("SHOW COLUMNS FROM email_accounts LIKE 'empresa_id'");
            $emailAccHasEmpresa = ($colE && $colE->num_rows > 0);
        }
    } catch (Throwable $e) {
    }

    if (false) {
        $seedEmail = 'cuenta9fran@gmail.com';
        $sqlSeedChk = 'SELECT id FROM email_accounts WHERE email = ?';
        if ($emailAccHasEmpresa) {
            $sqlSeedChk .= ' AND empresa_id = ?';
        }
        $sqlSeedChk .= ' LIMIT 1';
        $stmt = $mysqli->prepare($sqlSeedChk);
        if ($stmt) {
            if ($emailAccHasEmpresa) {
                $stmt->bind_param('si', $seedEmail, $eid);
            } else {
                $stmt->bind_param('s', $seedEmail);
            }
            $stmt->execute();
            $res = $stmt->get_result();
            $exists = $res && $res->fetch_assoc();
            $stmt->close();
            if (!$exists) {
                if ($emailAccHasEmpresa) {
                    $stmtIns = $mysqli->prepare('INSERT INTO email_accounts (empresa_id, email, name, priority, dept_id, is_default, smtp_host, smtp_port, smtp_secure, smtp_user, smtp_pass, created, updated) VALUES (?, ?, ?, ?, NULL, 0, NULL, NULL, NULL, NULL, NULL, NOW(), NOW())');
                } else {
                    $stmtIns = $mysqli->prepare('INSERT INTO email_accounts (email, name, priority, dept_id, is_default, smtp_host, smtp_port, smtp_secure, smtp_user, smtp_pass, created, updated) VALUES (?, ?, ?, NULL, 0, NULL, NULL, NULL, NULL, NULL, NOW(), NOW())');
                }
                if ($stmtIns) {
                    $seedName = 'Notificaciones';
                    $seedPriority = 'Normal';
                    if ($emailAccHasEmpresa) {
                        $stmtIns->bind_param('isss', $eid, $seedEmail, $seedName, $seedPriority);
                    } else {
                        $stmtIns->bind_param('sss', $seedEmail, $seedName, $seedPriority);
                    }
                    $stmtIns->execute();
                    $stmtIns->close();
                }
            }
        }
    }
}

$emailAccounts = [];
if (isset($mysqli) && $mysqli) {
    $sql = 'SELECT id, email, name, is_default FROM email_accounts';
    if ($emailAccHasEmpresa) {
        $sql .= ' WHERE empresa_id = ' . (int)$eid;
    }
    $sql .= ' ORDER BY is_default DESC, id ASC';
    $res = $mysqli->query($sql);
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $emailAccounts[] = $r;
        }
    }
}

$defaultEmailAccount = null;
foreach ($emailAccounts as $a) {
    if ((int)($a['is_default'] ?? 0) === 1) {
        $defaultEmailAccount = $a;
        break;
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

    $adminNotifyId = (int)($_POST['admin_notify_id'] ?? 0);

    $mailFromId = (int)($_POST['mail_from_id'] ?? 0);
    $mailAlertFromId = (int)($_POST['mail_alert_from_id'] ?? 0);

    $fromAcc = $findEmailAccountById($mailFromId);
    $alertAcc = $findEmailAccountById($mailAlertFromId);
    $adminAcc = $findEmailAccountById($adminNotifyId);

    // Permitir que no haya email por defecto. Si no se selecciona cuenta, se guarda lo que venga de config/app_settings.
    if (!$adminAcc) {
        $_SESSION['flash_error'] = 'Selecciona el correo de notificaciones del administrador.';
        header('Location: emailsettings.php');
        exit;
    }

    $mailFrom = $fromAcc ? (string)($fromAcc['email'] ?? '') : (string)getAppSetting('mail.from', defined('MAIL_FROM') ? (string)MAIL_FROM : '');
    $mailFromName = $fromAcc ? trim((string)($fromAcc['name'] ?? '')) : (string)getAppSetting('mail.from_name', defined('MAIL_FROM_NAME') ? (string)MAIL_FROM_NAME : '');
    if ($mailFromName === '') $mailFromName = $mailFrom;

    $mailAlertFrom = $alertAcc ? (string)($alertAcc['email'] ?? '') : (string)getAppSetting('mail.alert_from', defined('MAIL_FROM') ? (string)MAIL_FROM : '');
    $mailAlertFromName = $alertAcc ? trim((string)($alertAcc['name'] ?? 'Alerts')) : (string)getAppSetting('mail.alert_from_name', 'Alerts');
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
$valFrom = $defaultEmailAccount ? (string)($defaultEmailAccount['email'] ?? '') : (string)getAppSetting('mail.from', defined('MAIL_FROM') ? (string)MAIL_FROM : '');
$valFromName = $defaultEmailAccount ? (string)($defaultEmailAccount['name'] ?? '') : (string)getAppSetting('mail.from_name', defined('MAIL_FROM_NAME') ? (string)MAIL_FROM_NAME : '');
$valAlertFrom = $defaultEmailAccount ? (string)($defaultEmailAccount['email'] ?? '') : (string)getAppSetting('mail.alert_from', defined('MAIL_FROM') ? (string)MAIL_FROM : '');
$valAlertFromName = (string)getAppSetting('mail.alert_from_name', 'Alerts');
$valAdminNotify = (string)getAppSetting('mail.admin_notify_email', defined('ADMIN_NOTIFY_EMAIL') ? (string)ADMIN_NOTIFY_EMAIL : '');

if ($valFromName === '') $valFromName = $valFrom;

$selectedFromId = $defaultEmailAccount ? (int)($defaultEmailAccount['id'] ?? 0) : 0;
$selectedAlertFromId = $defaultEmailAccount ? (int)($defaultEmailAccount['id'] ?? 0) : 0;
$selectedAdminNotifyId = 0;
foreach ($emailAccounts as $a) {
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
                                <select name="mail_from_id" class="form-select" disabled>
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
                                <input type="hidden" name="mail_from_id" value="<?php echo (int)$selectedFromId; ?>">
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
                                <select name="mail_alert_from_id" class="form-select" disabled>
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
                                <input type="hidden" name="mail_alert_from_id" value="<?php echo (int)$selectedAlertFromId; ?>">
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
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once 'layout_admin.php';
?>
