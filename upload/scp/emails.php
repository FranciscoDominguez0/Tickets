<?php
require_once '../../config.php';
require_once '../../includes/helpers.php';
require_once '../../includes/Auth.php';

if (!isset($_SESSION['staff_id'])) {
    header('Location: login.php');
    exit;
}

requireLogin('agente');
requireRolePermission('admin.access');
$staff = getCurrentUser();
$currentRoute = 'emails';
$emailTab = 'emails';

$eid = empresaId();

$emailAccHasEmpresa = true;
$deptHasEmpresa = true;

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

$ensureEmailAccountsTable = function () {
    return true;
};

$ensureEmailAccountsTable();

// Sembrar la cuenta actual (config.php) si no existe ninguna
if (false && isset($mysqli) && $mysqli) {
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
                $stmtSeed = $mysqli->prepare('INSERT INTO email_accounts (empresa_id, email, name, priority, dept_id, is_default, smtp_host, smtp_port, smtp_secure, smtp_user, smtp_pass, created, updated) VALUES (?, ?, ?, ?, NULL, 0, ?, ?, ?, ?, ?, NOW(), NOW())');
            } else {
                $stmtSeed = $mysqli->prepare('INSERT INTO email_accounts (email, name, priority, dept_id, is_default, smtp_host, smtp_port, smtp_secure, smtp_user, smtp_pass, created, updated) VALUES (?, ?, ?, NULL, 0, ?, ?, ?, ?, ?, NOW(), NOW())');
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
    $deptHasEmpresa = true;

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

$clearDefaultEmail = function () use ($mysqli, $eid, $emailAccHasEmpresa) {
    if (!isset($mysqli) || !$mysqli) return false;
    if ($emailAccHasEmpresa) {
        return (bool)$mysqli->query('UPDATE email_accounts SET is_default = 0 WHERE empresa_id = ' . (int)$eid);
    }
    return (bool)$mysqli->query('UPDATE email_accounts SET is_default = 0');
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
        $is_default = 0;

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash_error'] = 'Correo electrónico inválido.';
        } else {
            if ($emailAccHasEmpresa) {
                $stmt = $mysqli->prepare('INSERT INTO email_accounts (empresa_id, email, name, priority, dept_id, is_default, created, updated) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())');
            } else {
                $stmt = $mysqli->prepare('INSERT INTO email_accounts (email, name, priority, dept_id, is_default, created, updated) VALUES (?, ?, ?, ?, ?, NOW(), NOW())');
            }
            if ($stmt) {
                $dept_id_param = $dept_id;
                if ($emailAccHasEmpresa) {
                    $stmt->bind_param('isssii', $eid, $email, $name, $priority, $dept_id_param, $is_default);
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
            $targetId = (int)($ids[0] ?? 0);
            if ($targetId <= 0) {
                $_SESSION['flash_error'] = 'Debe seleccionar al menos un email.';
                header('Location: emails.php');
                exit;
            }

            if ($emailAccHasEmpresa) {
                $stmtChk = $mysqli->prepare('SELECT id FROM email_accounts WHERE id = ? AND empresa_id = ? LIMIT 1');
            } else {
                $stmtChk = $mysqli->prepare('SELECT id FROM email_accounts WHERE id = ? LIMIT 1');
            }
            $ok = false;
            if ($stmtChk) {
                if ($emailAccHasEmpresa) {
                    $stmtChk->bind_param('ii', $targetId, $eid);
                } else {
                    $stmtChk->bind_param('i', $targetId);
                }
                $stmtChk->execute();
                $resChk = $stmtChk->get_result();
                $ok = ($resChk && $resChk->fetch_assoc());
            }

            if (!$ok) {
                $_SESSION['flash_error'] = 'Email no encontrado.';
                header('Location: emails.php');
                exit;
            }

            $clearDefaultEmail();

            if ($emailAccHasEmpresa) {
                $stmtSet = $mysqli->prepare('UPDATE email_accounts SET is_default = 1 WHERE id = ? AND empresa_id = ?');
            } else {
                $stmtSet = $mysqli->prepare('UPDATE email_accounts SET is_default = 1 WHERE id = ?');
            }
            if ($stmtSet) {
                if ($emailAccHasEmpresa) {
                    $stmtSet->bind_param('ii', $targetId, $eid);
                } else {
                    $stmtSet->bind_param('i', $targetId);
                }
                if ($stmtSet->execute()) {
                    $_SESSION['flash_msg'] = 'Email establecido como por defecto.';
                } else {
                    $_SESSION['flash_error'] = 'No se pudo establecer el email por defecto.';
                }
            } else {
                $_SESSION['flash_error'] = 'No se pudo establecer el email por defecto.';
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
            <span class="settings-hero-icon" style="background: rgba(239, 68, 68, 0.1); color: #ef4444;"><i class="bi bi-envelope"></i></span>
            <div>
                <h1>Direcciones de correo electrónico</h1>
                <p>Gestiona cuentas de recepción/envío y selecciona el email por defecto</p>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <button type="button" class="btn btn-danger btn-sm px-3 shadow-sm" style="border-radius: 10px; font-weight: 600; padding: 8px 16px;" data-bs-toggle="modal" data-bs-target="#addEmailModal">
                <i class="bi bi-plus-circle me-1"></i> Añadir nuevo Email
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
            <div class="card-header d-flex justify-content-between align-items-center" style="padding: 16px 20px;">
                <strong><i class="bi bi-inbox text-danger me-1"></i> Correos Registrados</strong>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-light btn-sm shadow-sm" style="border-radius: 8px; font-weight: 600; border: 1px solid rgba(0,0,0,0.05);" id="setDefaultBtn">
                        <i class="bi bi-star-fill text-warning me-1"></i> Por defecto
                    </button>
                    <button type="button" class="btn btn-outline-danger btn-sm" style="border-radius: 8px; font-weight: 600;" id="deleteEmailsBtn">
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
                                        <td colspan="6" class="text-center text-muted py-5">
                                            <i class="bi bi-envelope-x display-4 d-block mb-2 opacity-50"></i>
                                            <span class="fw-semibold">No hay correos configurados.</span>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($emails as $e): ?>
                                        <tr class="align-middle premium-row">
                                            <!-- VISTA MÓVIL (Tarjeta Premium) -->
                                            <td class="d-md-none p-0">
                                                <div class="mobile-card-premium">
                                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                                        <div class="d-flex align-items-center gap-3">
                                                            <input type="checkbox" name="ids[]" value="<?php echo (int)$e['id']; ?>" class="form-check-input email-checkbox m-0 shadow-sm" style="width: 1.25rem; height: 1.25rem;">
                                                            <?php if ((int)$e['is_default'] === 1): ?>
                                                            <span class="premium-badge-success"><i class="bi bi-star-fill me-1"></i>Por defecto</span>
                                                            <?php else: ?>
                                                            <span class="premium-badge-neutral">Opcional</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>

                                                    <div class="mobile-card-title">
                                                        <a href="email.php?id=<?php echo (int)$e['id']; ?>" class="text-decoration-none" style="color: inherit;">
                                                            <?php echo html((string)$e['name'] ?: 'Sin nombre'); ?>
                                                        </a>
                                                    </div>
                                                    <div class="mobile-card-subtitle">
                                                        <?php echo html((string)$e['email']); ?>
                                                    </div>

                                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                                        <div class="mobile-card-detail">
                                                            <i class="bi bi-bar-chart-steps me-1"></i> Prioridad: <span class="fw-bold"><?php echo html((string)($e['priority'] ?: 'Normal')); ?></span>
                                                        </div>
                                                        <div class="mobile-card-detail">
                                                            <i class="bi bi-clock me-1"></i> <?php echo date('d M, Y', strtotime($e['updated'] ?? $e['created'] ?? null)); ?>
                                                        </div>
                                                    </div>

                                                    <div class="d-flex justify-content-between align-items-center mt-2 pt-3 border-top-dashed">
                                                        <div class="d-flex align-items-center">
                                                            <div class="mobile-card-label">Dpto:</div>
                                                            <?php if ($e['dept_name']): ?>
                                                            <span class="premium-badge-dept">
                                                                <i class="bi bi-building me-1"></i><?php echo html((string)$e['dept_name']); ?>
                                                            </span>
                                                            <?php else: ?>
                                                            <span class="premium-badge-global">Global</span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <a href="email.php?id=<?php echo (int)$e['id']; ?>" class="btn-premium-edit">
                                                            Editar <i class="bi bi-chevron-right ms-1"></i>
                                                        </a>
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- VISTA ESCRITORIO -->
                                            <td class="d-none d-md-table-cell text-center" style="width: 50px;">
                                                <input type="checkbox" name="ids[]" value="<?php echo (int)$e['id']; ?>" class="form-check-input email-checkbox">
                                            </td>
                                            <td class="d-none d-md-table-cell">
                                                <div class="d-flex align-items-center">
                                                    <?php
                                                    $displayName = (string)$e['name'] ?: explode('@', (string)$e['email'])[0];
                                                    $initial = strtoupper(substr($displayName, 0, 1));
                                                    $colors = ['#ef4444', '#f97316', '#8b5cf6', '#0ea5e9', '#10b981'];
                                                    $color = $colors[crc32($e['email']) % count($colors)];
                                                    ?>
                                                    <div class="agent-avatar me-3" style="background-color: <?php echo $color; ?>20; color: <?php echo $color; ?>; width: 42px; height: 42px; display: flex; align-items: center; justify-content: center; border-radius: 12px; font-weight: 800; font-size: 1.15rem;">
                                                        <?php echo html($initial); ?>
                                                    </div>
                                                    <div>
                                                        <a href="email.php?id=<?php echo (int)$e['id']; ?>" class="agent-card-title text-decoration-none" style="font-size: 1.05rem; font-weight: 700; display: block; margin-bottom: 2px;">
                                                            <?php echo html((string)$e['name'] ?: 'Sin nombre'); ?>
                                                            <?php if ((int)$e['is_default'] === 1): ?>
                                                                <span class="badge ms-2" style="background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2); border-radius: 20px; font-weight: 700; font-size: 0.65rem; padding: 4px 8px; vertical-align: middle;"><i class="bi bi-star-fill me-1"></i>Por defecto</span>
                                                            <?php endif; ?>
                                                        </a>
                                                        <div class="agent-card-username" style="font-size: 0.85rem; color: #64748b; font-weight: 500;">
                                                            <?php echo html((string)$e['email']); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="d-none d-md-table-cell">
                                                <span style="background: rgba(100, 116, 139, 0.1); color: #64748b; padding: 5px 10px; border-radius: 8px; font-weight: 600; font-size: 0.8rem;">
                                                    <i class="bi bi-bar-chart-steps me-1"></i><?php echo html((string)($e['priority'] ?: 'Normal')); ?>
                                                </span>
                                            </td>
                                            <td class="d-none d-md-table-cell">
                                                <?php if ($e['dept_name']): ?>
                                                    <span style="background: rgba(37,99,235,0.08); color: #2563eb; padding: 5px 10px; border-radius: 8px; font-weight: 700; font-size: 0.8rem;">
                                                        <i class="bi bi-building me-1"></i><?php echo html((string)$e['dept_name']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span style="background: rgba(100, 116, 139, 0.08); color: #64748b; padding: 5px 10px; border-radius: 8px; font-weight: 600; font-size: 0.8rem;">Global</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="d-none d-md-table-cell text-muted" style="font-size: 0.85rem; font-weight: 500;">
                                                <i class="bi bi-calendar-event me-1"></i><?php echo html(formatDate($e['created'] ?? null)); ?>
                                            </td>
                                            <td class="d-none d-md-table-cell text-end pe-4">
                                                <a href="email.php?id=<?php echo (int)$e['id']; ?>" class="btn btn-sm btn-light shadow-sm" style="border-radius: 8px; font-weight: 700; color: #475569; padding: 6px 14px; font-size: 0.8rem;">
                                                    Editar <i class="bi bi-chevron-right ms-1" style="font-size: 0.7rem;"></i>
                                                </a>
                                            </td>
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
                        <input class="form-check-input" type="checkbox" id="new_is_default" disabled>
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
            if (!requireAtLeastOneEmailSelected(ids)) return;
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

<style>
/* CSS Variables for Premium Mobile Cards */
:root {
    --card-bg: #ffffff;
    --card-border: #e2e8f0;
    --text-primary: #0f172a;
    --text-secondary: #475569;
    --text-muted: #64748b;
    --badge-neutral-bg: #f1f5f9;
    --badge-neutral-border: #e2e8f0;
    --badge-success-bg: #f0fdf4;
    --badge-success-text: #16a34a;
    --badge-success-border: #bbf7d0;
    --border-dashed: #e2e8f0;
}

body.dark-mode {
    --card-bg: #000000;
    --card-border: #27272a;
    --text-primary: #f8fafc;
    --text-secondary: #cbd5e1;
    --text-muted: #94a3b8;
    --badge-neutral-bg: #000000;
    --badge-neutral-border: #3f3f46;
    --badge-success-bg: rgba(16, 185, 129, 0.1);
    --badge-success-text: #10b981;
    --badge-success-border: rgba(16, 185, 129, 0.2);
    --border-dashed: #3f3f46;
}

.premium-row {
    transition: all 0.2s ease;
}
.premium-row:hover {
    background-color: rgba(239, 68, 68, 0.02) !important;
}
body.dark-mode .premium-row:hover {
    background-color: rgba(255, 255, 255, 0.02) !important;
}
body.dark-mode .agent-card-title {
    color: #f8fafc !important;
}

body.dark-mode .btn-light {
    background: #000000 !important;
    border-color: #3f3f46 !important;
    color: #f8fafc !important;
}
body.dark-mode .btn-light:hover {
    background: #3f3f46 !important;
}

.mobile-card-premium {
    padding: 16px;
    background: var(--card-bg);
    position: relative;
    border-radius: 16px;
    border: 1px solid var(--card-border);
    margin: 10px;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
}
.mobile-card-title {
    font-size: 1.1rem;
    font-weight: 800;
    color: var(--text-primary);
    margin-bottom: 4px;
    line-height: 1.2;
}
.mobile-card-subtitle {
    font-size: 0.85rem;
    color: var(--text-secondary);
    margin-bottom: 14px;
    font-weight: 500;
}
.mobile-card-detail {
    font-size: 0.8rem;
    color: var(--text-muted);
    font-weight: 600;
}
.border-top-dashed {
    border-top: 1px dashed var(--border-dashed);
}
.mobile-card-label {
    font-size: 0.75rem;
    color: var(--text-muted);
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-right: 8px;
}
.premium-badge-success {
    background: var(--badge-success-bg);
    color: var(--badge-success-text);
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 800;
    border: 1px solid var(--badge-success-border);
}
.premium-badge-neutral {
    background: var(--badge-neutral-bg);
    color: var(--text-muted);
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 800;
    border: 1px solid var(--badge-neutral-border);
}
.premium-badge-dept {
    background: rgba(37,99,235,0.08);
    color: #3b82f6;
    padding: 4px 10px;
    border-radius: 8px;
    font-weight: 800;
    font-size: 0.75rem;
}
body.dark-mode .premium-badge-dept {
    color: #60a5fa;
}
.premium-badge-global {
    background: var(--badge-neutral-bg);
    color: var(--text-muted);
    padding: 4px 10px;
    border-radius: 8px;
    font-weight: 700;
    font-size: 0.75rem;
}
.btn-premium-edit {
    display: inline-block;
    color: #ef4444;
    background: rgba(239, 68, 68, 0.08);
    border-radius: 8px;
    font-weight: 800;
    font-size: 0.75rem;
    padding: 6px 14px;
    text-decoration: none;
    transition: all 0.2s;
}
.btn-premium-edit:hover {
    background: rgba(239, 68, 68, 0.15);
    color: #dc2626;
}

/* Responsive Table -> Cards for Mobile */
@media (max-width: 768px) {
    .settings-card { background: transparent !important; box-shadow: none !important; }
    .settings-card .card-header { border-radius: 12px; margin-bottom: 12px; }
    .settings-card .table-responsive { border: none !important; overflow: visible !important; }
    .settings-card .table { background: transparent !important; }
    .settings-card .table thead { display: none !important; }
    .settings-card .table tbody tr {
        display: block !important;
        margin-bottom: 0 !important;
        background: transparent !important;
        border: none !important;
        box-shadow: none !important;
    }
    .settings-card .table tbody td.d-md-none {
        display: block !important;
        width: 100% !important;
        padding: 0 !important;
        border: none !important;
    }
    .settings-card .table tbody td.d-none {
        display: none !important;
    }
}
</style>

<?php
$content = ob_get_clean();
require_once 'layout_admin.php';
?>