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

if (isset($mysqli) && $mysqli) {
    ensureNotificationRecipientsTable();
}

$staffHasEmpresa = false;
if (isset($mysqli) && $mysqli) {
    try {
        $staffHasEmpresa = dbColumnExists('staff', 'empresa_id');
    } catch (Throwable $e) {
        $staffHasEmpresa = false;
    }
}

$notificationCandidates = [];
if (isset($mysqli) && $mysqli) {
    $sqlCandidates = 'SELECT id, firstname, lastname, email FROM staff WHERE is_active = 1';
    if ($staffHasEmpresa) $sqlCandidates .= ' AND empresa_id = ?';
    $sqlCandidates .= ' ORDER BY firstname ASC, lastname ASC, email ASC';

    $stmtC = $mysqli->prepare($sqlCandidates);
    if ($stmtC) {
        if ($staffHasEmpresa) {
            $stmtC->bind_param('i', $eid);
        }
        if ($stmtC->execute()) {
            $rsC = $stmtC->get_result();
            while ($rsC && ($r = $rsC->fetch_assoc())) {
                $email = trim((string)($r['email'] ?? ''));
                if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) continue;
                $notificationCandidates[] = $r;
            }
        }
    }
}

$selectedRecipientIds = [];
if (isset($mysqli) && $mysqli && ensureNotificationRecipientsTable()) {
    $stmtSel = $mysqli->prepare('SELECT staff_id FROM notification_recipients WHERE empresa_id = ?');
    if ($stmtSel) {
        $stmtSel->bind_param('i', $eid);
        if ($stmtSel->execute()) {
            $rsSel = $stmtSel->get_result();
            while ($rsSel && ($r = $rsSel->fetch_assoc())) {
                $sid = (int)($r['staff_id'] ?? 0);
                if ($sid > 0) $selectedRecipientIds[$sid] = true;
            }
        }
    }
}

// Guardar configuración global (en app_settings)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF()) {
        $_SESSION['flash_error'] = 'Token CSRF inválido.';
        header('Location: emailsettings.php');
        exit;
    }

    $recipientIds = $_POST['notification_recipients'] ?? [];
    if (!is_array($recipientIds)) $recipientIds = [];
    $recipientIds = array_values(array_unique(array_filter(array_map('intval', $recipientIds), function ($v) {
        return $v > 0;
    })));

    $mailFromId = (int)($_POST['mail_from_id'] ?? 0);
    $mailAlertFromId = (int)($_POST['mail_alert_from_id'] ?? 0);

    $fromAcc = $findEmailAccountById($mailFromId);
    $alertAcc = $findEmailAccountById($mailAlertFromId);

    $mailFrom = $fromAcc ? (string)($fromAcc['email'] ?? '') : (string)getAppSetting('mail.from', defined('MAIL_FROM') ? (string)MAIL_FROM : '');
    $mailFromName = $fromAcc ? trim((string)($fromAcc['name'] ?? '')) : (string)getAppSetting('mail.from_name', defined('MAIL_FROM_NAME') ? (string)MAIL_FROM_NAME : '');
    if ($mailFromName === '') $mailFromName = $mailFrom;

    $mailAlertFrom = $alertAcc ? (string)($alertAcc['email'] ?? '') : (string)getAppSetting('mail.alert_from', defined('MAIL_FROM') ? (string)MAIL_FROM : '');
    $mailAlertFromName = $alertAcc ? trim((string)($alertAcc['name'] ?? 'Alerts')) : (string)getAppSetting('mail.alert_from_name', 'Alerts');
    if ($mailAlertFromName === '') $mailAlertFromName = 'Alerts';

    setAppSetting('mail.from', $mailFrom);
    setAppSetting('mail.from_name', $mailFromName);
    setAppSetting('mail.alert_from', $mailAlertFrom);
    setAppSetting('mail.alert_from_name', $mailAlertFromName);

    $allowedMap = [];
    foreach ($notificationCandidates as $cand) {
        $allowedMap[(int)($cand['id'] ?? 0)] = true;
    }
    $validRecipientIds = [];
    foreach ($recipientIds as $sid) {
        if (isset($allowedMap[$sid])) $validRecipientIds[] = $sid;
    }

    if (ensureNotificationRecipientsTable()) {
        $stmtDel = $mysqli->prepare('DELETE FROM notification_recipients WHERE empresa_id = ?');
        if ($stmtDel) {
            $stmtDel->bind_param('i', $eid);
            $stmtDel->execute();
        }
        if (!empty($validRecipientIds)) {
            $stmtIns = $mysqli->prepare('INSERT INTO notification_recipients (empresa_id, staff_id, created_at) VALUES (?, ?, NOW())');
            if ($stmtIns) {
                foreach ($validRecipientIds as $sid) {
                    $stmtIns->bind_param('ii', $eid, $sid);
                    $stmtIns->execute();
                }
            }
        }
        setAppSetting('mail.admin_notify_email', '');
        $_SESSION['flash_msg'] = 'Configuración guardada. Destinatarios seleccionados: ' . (string)count($validRecipientIds);
    } else {
        $_SESSION['flash_error'] = 'No se pudo guardar destinatarios de notificación.';
    }
    header('Location: emailsettings.php');
    exit;
}

// Valores actuales (fallback a config.php)
$valFrom = $defaultEmailAccount ? (string)($defaultEmailAccount['email'] ?? '') : (string)getAppSetting('mail.from', defined('MAIL_FROM') ? (string)MAIL_FROM : '');
$valFromName = $defaultEmailAccount ? (string)($defaultEmailAccount['name'] ?? '') : (string)getAppSetting('mail.from_name', defined('MAIL_FROM_NAME') ? (string)MAIL_FROM_NAME : '');
$valAlertFrom = $defaultEmailAccount ? (string)($defaultEmailAccount['email'] ?? '') : (string)getAppSetting('mail.alert_from', defined('MAIL_FROM') ? (string)MAIL_FROM : '');
$valAlertFromName = (string)getAppSetting('mail.alert_from_name', 'Alerts');
if ($valFromName === '') $valFromName = $valFrom;

$selectedFromId = $defaultEmailAccount ? (int)($defaultEmailAccount['id'] ?? 0) : 0;
$selectedAlertFromId = $defaultEmailAccount ? (int)($defaultEmailAccount['id'] ?? 0) : 0;
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
                        <label class="form-label">Destinatarios de notificaciones</label>
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#recipientsModal">
                                Seleccionar destinatarios
                            </button>
                            <span class="text-muted small" id="selectedRecipientsLabel">Seleccionados: <?php echo (int)count($selectedRecipientIds); ?></span>
                        </div>
                    </div>

                    <div class="modal fade" id="recipientsModal" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-scrollable">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Seleccionar destinatarios</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                </div>
                                <div class="modal-body">
                                    <?php if (empty($notificationCandidates)): ?>
                                        <div class="text-muted">No hay usuarios activos con email válido.</div>
                                    <?php else: ?>
                                        <?php foreach ($notificationCandidates as $cand): ?>
                                            <?php
                                                $sid = (int)($cand['id'] ?? 0);
                                                $first = trim((string)($cand['firstname'] ?? ''));
                                                $last = trim((string)($cand['lastname'] ?? ''));
                                                $fullName = trim($first . ' ' . $last);
                                                if ($fullName === '') $fullName = 'Sin nombre';
                                                $email = trim((string)($cand['email'] ?? ''));
                                            ?>
                                            <div class="form-check mb-2">
                                                <input
                                                    class="form-check-input recipient-checkbox"
                                                    type="checkbox"
                                                    name="notification_recipients[]"
                                                    id="recipient_<?php echo $sid; ?>"
                                                    value="<?php echo $sid; ?>"
                                                    <?php echo isset($selectedRecipientIds[$sid]) ? 'checked' : ''; ?>
                                                >
                                                <label class="form-check-label" for="recipient_<?php echo $sid; ?>">
                                                    <?php echo html($fullName); ?> - <?php echo html($email); ?>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-recipient-cancel>Cancelar</button>
                                    <button type="submit" class="btn btn-primary" data-recipient-save>Guardar</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle"></i> Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        var modalEl = document.getElementById('recipientsModal');
        if (!modalEl) return;
        var checkboxes = modalEl.querySelectorAll('.recipient-checkbox');
        var saveBtn = modalEl.querySelector('[data-recipient-save]');
        var cancelBtn = modalEl.querySelector('[data-recipient-cancel]');
        var selectedLabel = document.getElementById('selectedRecipientsLabel');
        var snapshot = [];

        function countSelected() {
            var n = 0;
            checkboxes.forEach(function (cb) { if (cb.checked) n++; });
            if (selectedLabel) selectedLabel.textContent = 'Seleccionados: ' + String(n);
        }

        function saveSnapshot() {
            snapshot = [];
            checkboxes.forEach(function (cb) { snapshot.push(!!cb.checked); });
        }

        function restoreSnapshot() {
            checkboxes.forEach(function (cb, idx) {
                cb.checked = !!snapshot[idx];
            });
        }

        modalEl.addEventListener('show.bs.modal', function () {
            saveSnapshot();
        });

        if (cancelBtn) {
            cancelBtn.addEventListener('click', function () {
                restoreSnapshot();
                countSelected();
                var m = bootstrap.Modal.getOrCreateInstance(modalEl);
                m.hide();
            });
        }

        if (saveBtn) {
            saveBtn.addEventListener('click', function () {
                countSelected();
            });
        }

        checkboxes.forEach(function (cb) {
            cb.addEventListener('change', countSelected);
        });
        countSelected();
    })();
</script>

<?php
$content = ob_get_clean();
require_once 'layout_admin.php';
?>
