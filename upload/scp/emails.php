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
$emailTab = 'emails';

$eid = empresaId();

$emailAccHasEmpresa = false;
$deptHasEmpresa = false;
if (isset($mysqli) && $mysqli) {
    $colE = $mysqli->query("SHOW COLUMNS FROM email_accounts LIKE 'empresa_id'");
    $emailAccHasEmpresa = ($colE && $colE->num_rows > 0);
    $colD = $mysqli->query("SHOW COLUMNS FROM departments LIKE 'empresa_id'");
    $deptHasEmpresa = ($colD && $colD->num_rows > 0);
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

$ensureEmailAccountsTable = function () use ($mysqli) {
    if (!isset($mysqli) || !$mysqli) return false;
    $sql = "CREATE TABLE IF NOT EXISTS email_accounts (\n"
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
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    return (bool)$mysqli->query($sql);
};

$ensureEmailAccountsTable();

// Sembrar la cuenta actual (config.php) si no existe ninguna
if (isset($mysqli) && $mysqli) {
    $seedSql = 'SELECT COUNT(*) c FROM email_accounts';
    if ($emailAccHasEmpresa) {
        $seedSql .= ' WHERE empresa_id = ' . (int)$eid;
    }
    $resSeed = $mysqli->query($seedSql);
    $countSeed = 0;
    if ($resSeed) {
        $rowSeed = $resSeed->fetch_assoc();
        $countSeed = (int)($rowSeed['c'] ?? 0);
    }
    if ($countSeed === 0) {
        $seedEmail = defined('MAIL_FROM') ? (string)MAIL_FROM : (defined('SMTP_USER') ? (string)SMTP_USER : '');
        $seedName = defined('MAIL_FROM_NAME') ? (string)MAIL_FROM_NAME : '';
        $seedHost = defined('SMTP_HOST') ? (string)SMTP_HOST : '';
        $seedPort = defined('SMTP_PORT') ? (int)SMTP_PORT : null;
        $seedSecure = defined('SMTP_SECURE') ? (string)SMTP_SECURE : '';
        $seedUser = defined('SMTP_USER') ? (string)SMTP_USER : '';
        $seedPass = defined('SMTP_PASS') ? (string)SMTP_PASS : '';

        if ($seedEmail !== '' && filter_var($seedEmail, FILTER_VALIDATE_EMAIL)) {
            if ($emailAccHasEmpresa) {
                $stmtSeed = $mysqli->prepare('INSERT INTO email_accounts (empresa_id, email, name, priority, dept_id, is_default, smtp_host, smtp_port, smtp_secure, smtp_user, smtp_pass, created, updated) VALUES (?, ?, ?, ?, NULL, 1, ?, ?, ?, ?, ?, NOW(), NOW())');
            } else {
                $stmtSeed = $mysqli->prepare('INSERT INTO email_accounts (email, name, priority, dept_id, is_default, smtp_host, smtp_port, smtp_secure, smtp_user, smtp_pass, created, updated) VALUES (?, ?, ?, NULL, 1, ?, ?, ?, ?, ?, NOW(), NOW())');
            }
            if ($stmtSeed) {
                $prioritySeed = 'Normal';
                $portSeed = $seedPort;
                if ($emailAccHasEmpresa) {
                    $stmtSeed->bind_param('issssisss', $eid, $seedEmail, $seedName, $prioritySeed, $seedHost, $portSeed, $seedSecure, $seedUser, $seedPass);
                } else {
                    $stmtSeed->bind_param('ssssisss', $seedEmail, $seedName, $prioritySeed, $seedHost, $portSeed, $seedSecure, $seedUser, $seedPass);
                }
                $stmtSeed->execute();
            }
        }
    }
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

$loadDepartments = function () use ($mysqli) {
    $departments = [];
    if (!isset($mysqli) || !$mysqli) return $departments;
    $deptHasEmpresa = false;
    $colD = $mysqli->query("SHOW COLUMNS FROM departments LIKE 'empresa_id'");
    $deptHasEmpresa = ($colD && $colD->num_rows > 0);

    $sql = 'SELECT id, name FROM departments';
    if ($deptHasEmpresa) {
        $sql .= ' WHERE empresa_id = ' . (int)empresaId();
    }
    $sql .= ' ORDER BY name';

    $res = $mysqli->query($sql);
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $departments[] = $r;
        }
    }
    return $departments;
};

$departments = $loadDepartments();

$fetchEmailAccounts = function () use ($mysqli, $eid, $emailAccHasEmpresa, $deptHasEmpresa) {
    $items = [];
    if (!isset($mysqli) || !$mysqli) return $items;
    $sql = "SELECT ea.*, d.name AS dept_name\n"
         . "FROM email_accounts ea\n"
         . "LEFT JOIN departments d ON d.id = ea.dept_id";
    if ($deptHasEmpresa) {
        $sql .= " AND d.empresa_id = " . (int)$eid;
    }
    $sql .= "\n";
    if ($emailAccHasEmpresa) {
        $sql .= "WHERE ea.empresa_id = " . (int)$eid . "\n";
    }
    $sql .= "ORDER BY ea.is_default DESC, ea.id ASC";
    $res = $mysqli->query($sql);
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $items[] = $r;
        }
    }
    return $items;
};

$getEmailAccount = function ($id) use ($mysqli, $eid, $emailAccHasEmpresa) {
    if (!isset($mysqli) || !$mysqli) return null;
    $id = (int)$id;
    if ($id <= 0) return null;
    if ($emailAccHasEmpresa) {
        $stmt = $mysqli->prepare('SELECT * FROM email_accounts WHERE id = ? AND empresa_id = ?');
    } else {
        $stmt = $mysqli->prepare('SELECT * FROM email_accounts WHERE id = ?');
    }
    if (!$stmt) return null;
    if ($emailAccHasEmpresa) {
        $stmt->bind_param('ii', $id, $eid);
    } else {
        $stmt->bind_param('i', $id);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    return $res ? $res->fetch_assoc() : null;
};

$setDefaultEmail = function ($id) use ($mysqli, $eid, $emailAccHasEmpresa) {
    if (!isset($mysqli) || !$mysqli) return false;
    $id = (int)$id;
    if ($id <= 0) return false;
    if ($emailAccHasEmpresa) {
        $mysqli->query('UPDATE email_accounts SET is_default = 0 WHERE empresa_id = ' . (int)$eid);
        $stmt = $mysqli->prepare('UPDATE email_accounts SET is_default = 1 WHERE id = ? AND empresa_id = ?');
    } else {
        $mysqli->query('UPDATE email_accounts SET is_default = 0');
        $stmt = $mysqli->prepare('UPDATE email_accounts SET is_default = 1 WHERE id = ?');
    }
    if (!$stmt) return false;
    if ($emailAccHasEmpresa) {
        $stmt->bind_param('ii', $id, $eid);
    } else {
        $stmt->bind_param('i', $id);
    }
    return $stmt->execute();
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF()) {
        $_SESSION['flash_error'] = 'Token CSRF inválido.';
        header('Location: emails.php');
        exit;
    }

    $do = (string)($_POST['do'] ?? '');
    if ($do === 'create') {
        $email = trim((string)($_POST['email'] ?? ''));
        $name = trim((string)($_POST['name'] ?? ''));
        $priority = trim((string)($_POST['priority'] ?? 'Normal'));
        $dept_id = isset($_POST['dept_id']) && $_POST['dept_id'] !== '' ? (int)$_POST['dept_id'] : null;
        $is_default = isset($_POST['is_default']) ? 1 : 0;

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash_error'] = 'Correo electrónico inválido.';
        } else {
            if ($is_default) {
                if ($emailAccHasEmpresa) {
                    $mysqli->query('UPDATE email_accounts SET is_default = 0 WHERE empresa_id = ' . (int)$eid);
                } else {
                    $mysqli->query('UPDATE email_accounts SET is_default = 0');
                }
            }
            if ($emailAccHasEmpresa) {
                $stmt = $mysqli->prepare('INSERT INTO email_accounts (empresa_id, email, name, priority, dept_id, is_default, created, updated) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())');
            } else {
                $stmt = $mysqli->prepare('INSERT INTO email_accounts (email, name, priority, dept_id, is_default, created, updated) VALUES (?, ?, ?, ?, ?, NOW(), NOW())');
            }
            if ($stmt) {
                $dept_id_param = $dept_id;
                if ($emailAccHasEmpresa) {
                    $stmt->bind_param('isssiii', $eid, $email, $name, $priority, $dept_id_param, $is_default);
                } else {
                    $stmt->bind_param('sssii', $email, $name, $priority, $dept_id_param, $is_default);
                }
                if ($stmt->execute()) {
                    $_SESSION['flash_msg'] = 'Email agregado correctamente.';
                } else {
                    $_SESSION['flash_error'] = 'No se pudo agregar el email.';
                }
            } else {
                $_SESSION['flash_error'] = 'No se pudo agregar el email.';
            }
        }
        header('Location: emails.php');
        exit;
    }

    if ($do === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $email = trim((string)($_POST['email'] ?? ''));
        $name = trim((string)($_POST['name'] ?? ''));
        $priority = trim((string)($_POST['priority'] ?? 'Normal'));
        $dept_id = isset($_POST['dept_id']) && $_POST['dept_id'] !== '' ? (int)$_POST['dept_id'] : null;

        $smtp_host = trim((string)($_POST['smtp_host'] ?? ''));
        $smtp_port = isset($_POST['smtp_port']) && $_POST['smtp_port'] !== '' ? (int)$_POST['smtp_port'] : null;
        $smtp_secure = trim((string)($_POST['smtp_secure'] ?? ''));
        $smtp_user = trim((string)($_POST['smtp_user'] ?? ''));
        $smtp_pass = (string)($_POST['smtp_pass'] ?? '');
        $keep_pass = isset($_POST['keep_pass']) ? 1 : 0;

        if ($id <= 0) {
            $_SESSION['flash_error'] = 'ID inválido.';
            header('Location: emails.php');
            exit;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash_error'] = 'Correo electrónico inválido.';
            header('Location: emails.php?id=' . $id . '#account');
            exit;
        }

        if ($keep_pass && $smtp_pass === '') {
            if ($emailAccHasEmpresa) {
                $stmtP = $mysqli->prepare('SELECT smtp_pass FROM email_accounts WHERE id = ? AND empresa_id = ?');
            } else {
                $stmtP = $mysqli->prepare('SELECT smtp_pass FROM email_accounts WHERE id = ?');
            }
            if ($stmtP) {
                if ($emailAccHasEmpresa) {
                    $stmtP->bind_param('ii', $id, $eid);
                } else {
                    $stmtP->bind_param('i', $id);
                }
                $stmtP->execute();
                $row = $stmtP->get_result()->fetch_assoc();
                $smtp_pass = (string)($row['smtp_pass'] ?? '');
            }
        }

        if ($emailAccHasEmpresa) {
            $stmt = $mysqli->prepare('UPDATE email_accounts SET email = ?, name = ?, priority = ?, dept_id = ?, smtp_host = ?, smtp_port = ?, smtp_secure = ?, smtp_user = ?, smtp_pass = ? WHERE id = ? AND empresa_id = ?');
        } else {
            $stmt = $mysqli->prepare('UPDATE email_accounts SET email = ?, name = ?, priority = ?, dept_id = ?, smtp_host = ?, smtp_port = ?, smtp_secure = ?, smtp_user = ?, smtp_pass = ? WHERE id = ?');
        }
        if ($stmt) {
            $dept_id_param = $dept_id;
            $smtp_port_param = $smtp_port;
            if ($emailAccHasEmpresa) {
                $stmt->bind_param('sssisisssii', $email, $name, $priority, $dept_id_param, $smtp_host, $smtp_port_param, $smtp_secure, $smtp_user, $smtp_pass, $id, $eid);
            } else {
                $stmt->bind_param('sssisisssi', $email, $name, $priority, $dept_id_param, $smtp_host, $smtp_port_param, $smtp_secure, $smtp_user, $smtp_pass, $id);
            }
            if ($stmt->execute()) {
                $_SESSION['flash_msg'] = 'Email actualizado correctamente.';
            } else {
                $_SESSION['flash_error'] = 'No se pudo actualizar el email.';
            }
        } else {
            $_SESSION['flash_error'] = 'No se pudo actualizar el email.';
        }
        header('Location: emails.php?id=' . $id . '#account');
        exit;
    }

    if ($do === 'mass_process') {
        $ids = $_POST['ids'] ?? [];
        $action = (string)($_POST['a'] ?? '');

        if (empty($ids) || !is_array($ids)) {
            $_SESSION['flash_error'] = 'Debe seleccionar al menos un email.';
            header('Location: emails.php');
            exit;
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($v) => $v > 0)));
        if (empty($ids)) {
            $_SESSION['flash_error'] = 'Debe seleccionar al menos un email.';
            header('Location: emails.php');
            exit;
        }

        if ($action === 'delete') {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $types = str_repeat('i', count($ids));

            if ($emailAccHasEmpresa) {
                $stmtDflt = $mysqli->prepare("SELECT COUNT(*) c FROM email_accounts WHERE empresa_id = ? AND is_default = 1 AND id IN ($placeholders)");
            } else {
                $stmtDflt = $mysqli->prepare("SELECT COUNT(*) c FROM email_accounts WHERE is_default = 1 AND id IN ($placeholders)");
            }
            if ($stmtDflt) {
                if ($emailAccHasEmpresa) {
                    $stmtDflt->bind_param('i' . $types, $eid, ...$ids);
                } else {
                    $stmtDflt->bind_param($types, ...$ids);
                }
                $stmtDflt->execute();
                $row = $stmtDflt->get_result()->fetch_assoc();
                if ((int)($row['c'] ?? 0) > 0) {
                    $_SESSION['flash_error'] = 'No puedes eliminar el email por defecto.';
                    header('Location: emails.php');
                    exit;
                }
            }

            if ($emailAccHasEmpresa) {
                $stmt = $mysqli->prepare("DELETE FROM email_accounts WHERE empresa_id = ? AND id IN ($placeholders)");
            } else {
                $stmt = $mysqli->prepare("DELETE FROM email_accounts WHERE id IN ($placeholders)");
            }
            if ($stmt) {
                if ($emailAccHasEmpresa) {
                    $stmt->bind_param('i' . $types, $eid, ...$ids);
                } else {
                    $stmt->bind_param($types, ...$ids);
                }
                if ($stmt->execute()) {
                    $_SESSION['flash_msg'] = 'Emails eliminados correctamente.';
                } else {
                    $_SESSION['flash_error'] = 'No se pudieron eliminar los emails.';
                }
            } else {
                $_SESSION['flash_error'] = 'No se pudieron eliminar los emails.';
            }
            header('Location: emails.php');
            exit;
        }

        if ($action === 'set_default') {
            $id = (int)($ids[0] ?? 0);
            if ($id > 0 && $setDefaultEmail($id)) {
                $_SESSION['flash_msg'] = 'Email por defecto actualizado.';
            } else {
                $_SESSION['flash_error'] = 'No se pudo cambiar el email por defecto.';
            }
            header('Location: emails.php');
            exit;
        }

        $_SESSION['flash_error'] = 'Acción no reconocida.';
        header('Location: emails.php');
        exit;
    }
}

$emails = $fetchEmailAccounts();

ob_start();
?>

<div class="settings-hero">
    <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
        <div class="d-flex align-items-center gap-3">
            <span class="settings-hero-icon"><i class="bi bi-envelope"></i></span>
            <div>
                <h1>Direcciones de correo electrónico</h1>
                <p>Gestiona cuentas de envío (SMTP) y selecciona el email por defecto</p>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addEmailModal">
                <i class="bi bi-plus-circle"></i> Añadir nuevo Email
            </button>
        </div>
    </div>
</div>

<div class="alert alert-danger alert-dismissible fade show d-none" role="alert" id="emailsClientError" aria-live="polite" data-alert-static="1">
    <i class="bi bi-exclamation-triangle me-2"></i><span id="emailsClientErrorText"></span>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
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
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong><i class="bi bi-inbox"></i> Correos</strong>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="setDefaultBtn">
                        <i class="bi bi-star"></i> Poner por defecto
                    </button>
                    <button type="button" class="btn btn-outline-danger btn-sm" id="deleteEmailsBtn">
                        <i class="bi bi-trash"></i> Eliminar
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <form method="post" action="emails.php" id="emailsMassForm">
                    <input type="hidden" name="do" value="mass_process">
                    <?php csrfField(); ?>
                    <input type="hidden" name="a" value="" id="massActionInput">

                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th width="30"><input type="checkbox" id="selectAllEmails" class="form-check-input"></th>
                                    <th>Correo electrónico</th>
                                    <th>Prioridad</th>
                                    <th>Departamento</th>
                                    <th>Creado</th>
                                    <th>Última actualización</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($emails)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">No hay correos configurados.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($emails as $e): ?>
                                        <tr>
                                            <td><input type="checkbox" name="ids[]" value="<?php echo (int)$e['id']; ?>" class="form-check-input email-checkbox"></td>
                                            <td>
                                                <a href="email.php?id=<?php echo (int)$e['id']; ?>" class="fw-semibold text-decoration-none">
                                                    <?php echo html((string)$e['name'] ?: (string)$e['email']); ?>
                                                    &lt;<?php echo html((string)$e['email']); ?>&gt;
                                                </a>
                                                <?php if ((int)$e['is_default'] === 1): ?>
                                                    <span class="badge bg-success ms-2">Por defecto</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo html((string)($e['priority'] ?: 'Normal')); ?></td>
                                            <td><?php echo html((string)($e['dept_name'] ?: '')); ?></td>
                                            <td><?php echo html(formatDate($e['created'] ?? null)); ?></td>
                                            <td><?php echo html(formatDate($e['updated'] ?? null)); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addEmailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post" action="emails.php">
                <input type="hidden" name="do" value="create">
                <?php csrfField(); ?>
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Añadir nuevo Email</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Nombre (opcional)</label>
                                <input type="text" name="name" class="form-control">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Prioridad</label>
                                <select class="form-select" name="priority">
                                    <option value="Normal" selected>Normal</option>
                                    <option value="Alta">Alta</option>
                                    <option value="Baja">Baja</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label">Departamento</label>
                                <select class="form-select" name="dept_id">
                                    <option value="">Seleccionar</option>
                                    <?php foreach ($departments as $d): ?>
                                        <option value="<?php echo (int)$d['id']; ?>"><?php echo html((string)$d['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_default" id="new_is_default">
                        <label class="form-check-label" for="new_is_default">Establecer como por defecto</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Crear</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteEmailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-trash text-danger"></i> Eliminar emails</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                ¿Deseas eliminar los emails seleccionados?
                <div class="text-muted small mt-2">
                    Seleccionados: <strong><span id="deleteEmailsCount">0</span></strong>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteEmailsBtn"><i class="bi bi-trash"></i> Eliminar</button>
            </div>
        </div>
    </div>
</div>

<script>
window.addEventListener('DOMContentLoaded', function(){
    function getCheckedIds(){
        var boxes = document.querySelectorAll('.email-checkbox:checked');
        var ids = [];
        boxes.forEach(function(b){ ids.push(b.value); });
        return ids;
    }

    function requireAtLeastOneEmailSelected(ids) {
        if (ids.length < 1) {
            var box = document.getElementById('emailsClientError');
            if (!box) {
                var wrapper = document.createElement('div');

                wrapper.innerHTML = ''
                    + '<div class="alert alert-danger alert-dismissible fade show" role="alert" id="emailsClientError" aria-live="polite" data-alert-static="1">'
                    + '  <i class="bi bi-exclamation-triangle me-2"></i><span id="emailsClientErrorText"></span>'
                    + '  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>'
                    + '</div>';
                var newEl = wrapper.firstElementChild;
                var hero = document.querySelector('.settings-hero');
                if (hero && hero.parentNode) {
                    hero.parentNode.insertBefore(newEl, hero.nextSibling);
                } else {
                    document.body.insertBefore(newEl, document.body.firstChild);
                }
                box = newEl;
            }
            var txt = document.getElementById('emailsClientErrorText');
            if (txt) txt.textContent = 'Debe seleccionar al menos un email';
            box.classList.remove('d-none');
            box.scrollIntoView({ behavior: 'smooth', block: 'start' });
            try {
                if (box._autoHideTimer) window.clearTimeout(box._autoHideTimer);
                box._autoHideTimer = window.setTimeout(function(){
                    if (box) box.classList.add('d-none');
                }, 3500);
            } catch (e) {}
            return false;
        }
        return true;
    }

    var selectAll = document.getElementById('selectAllEmails');
    if (selectAll) {
        selectAll.addEventListener('change', function(){
            var boxes = document.querySelectorAll('.email-checkbox');
            boxes.forEach(function(b){ b.checked = selectAll.checked; });
        });
    }

    var setDefaultBtn = document.getElementById('setDefaultBtn');
    if (setDefaultBtn) {
        setDefaultBtn.addEventListener('click', function(){
            var ids = getCheckedIds();
            if (ids.length < 1) return;
            var form = document.getElementById('emailsMassForm');
            var act = document.getElementById('massActionInput');
            act.value = 'set_default';
            form.submit();
        });
    }

    var deleteBtn = document.getElementById('deleteEmailsBtn');
    if (deleteBtn) {
        deleteBtn.addEventListener('click', function(){
            var ids = getCheckedIds();
            if (!requireAtLeastOneEmailSelected(ids)) return;
            var countEl = document.getElementById('deleteEmailsCount');
            if (countEl) countEl.textContent = String(ids.length);
            var modalEl = document.getElementById('deleteEmailsModal');
            if (!modalEl || typeof bootstrap === 'undefined') return;
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        });
    }

    var confirmDeleteBtn = document.getElementById('confirmDeleteEmailsBtn');
    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', function(){
            var ids = getCheckedIds();
            if (!requireAtLeastOneEmailSelected(ids)) return;
            var form = document.getElementById('emailsMassForm');
            var act = document.getElementById('massActionInput');
            act.value = 'delete';
            form.submit();
        });
    }
});
</script>

<?php
$content = ob_get_clean();
require_once 'layout_admin.php';
?>