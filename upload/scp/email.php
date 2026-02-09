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

$collapseSettingsMenu = false;
if (!isset($_SESSION['admin_sidebar_menu_seen'])) {
    $_SESSION['admin_sidebar_menu_seen'] = 1;
    $collapseSettingsMenu = true;
}

$ensureEmailAccountsTable = function () use ($mysqli) {
    if (!isset($mysqli) || !$mysqli) return false;
    $sql = "CREATE TABLE IF NOT EXISTS email_accounts (\n"
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
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    return (bool)$mysqli->query($sql);
};
$ensureEmailAccountsTable();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: emails.php');
    exit;
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

$departments = [];
if (isset($mysqli) && $mysqli) {
    $res = $mysqli->query('SELECT id, name FROM departments ORDER BY name');
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $departments[] = $r;
        }
    }
}

$stmt = $mysqli->prepare('SELECT ea.*, d.name AS dept_name FROM email_accounts ea LEFT JOIN departments d ON d.id = ea.dept_id WHERE ea.id = ?');
if (!$stmt) {
    header('Location: emails.php');
    exit;
}
$stmt->bind_param('i', $id);
$stmt->execute();
$emailAccount = $stmt->get_result()->fetch_assoc();
if (!$emailAccount) {
    header('Location: emails.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF()) {
        $_SESSION['flash_error'] = 'Token CSRF inválido.';
        header('Location: email.php?id=' . $id . '#account');
        exit;
    }

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

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['flash_error'] = 'Correo electrónico inválido.';
        header('Location: email.php?id=' . $id . '#account');
        exit;
    }

    if ($keep_pass && $smtp_pass === '') {
        $stmtP = $mysqli->prepare('SELECT smtp_pass FROM email_accounts WHERE id = ?');
        if ($stmtP) {
            $stmtP->bind_param('i', $id);
            $stmtP->execute();
            $row = $stmtP->get_result()->fetch_assoc();
            $smtp_pass = (string)($row['smtp_pass'] ?? '');
        }
    }

    $stmtU = $mysqli->prepare('UPDATE email_accounts SET email = ?, name = ?, priority = ?, dept_id = ?, smtp_host = ?, smtp_port = ?, smtp_secure = ?, smtp_user = ?, smtp_pass = ? WHERE id = ?');
    if (!$stmtU) {
        $_SESSION['flash_error'] = 'No se pudo actualizar el email.';
        header('Location: email.php?id=' . $id . '#account');
        exit;
    }

    $dept_id_param = $dept_id;
    $smtp_port_param = $smtp_port;
    $stmtU->bind_param('sssisisssi', $email, $name, $priority, $dept_id_param, $smtp_host, $smtp_port_param, $smtp_secure, $smtp_user, $smtp_pass, $id);
    if ($stmtU->execute()) {
        $_SESSION['flash_msg'] = 'Email actualizado correctamente.';
    } else {
        $_SESSION['flash_error'] = 'No se pudo actualizar el email.';
    }

    header('Location: email.php?id=' . $id . '#account');
    exit;
}

ob_start();
?>

<div class="settings-hero">
    <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
        <div class="d-flex align-items-center gap-3">
            <span class="settings-hero-icon"><i class="bi bi-envelope"></i></span>
            <div>
                <h1>Configurar Email</h1>
                <p><?php echo html((string)($emailAccount['email'] ?? '')); ?> <?php if ((int)($emailAccount['is_default'] ?? 0) === 1): ?><span class="badge bg-success ms-2">Por defecto</span><?php endif; ?></p>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <a href="emails.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
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
    <style>
        #email-success-overlay{position:fixed;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.25);z-index:2000}
        #email-success-overlay .box{background:#fff;border-radius:14px;padding:18px 22px;min-width:280px;max-width:90vw;box-shadow:0 10px 30px rgba(0,0,0,.25)}
    </style>
    <div id="email-success-overlay" role="status" aria-live="polite">
        <div class="box">
            <div class="d-flex align-items-center gap-3">
                <div class="spinner-border text-success" role="status" aria-hidden="true"></div>
                <div>
                    <div class="fw-semibold"><?php echo html($msg); ?></div>
                    <div class="text-muted small">Guardado</div>
                </div>
            </div>
        </div>
    </div>
    <script>
        (function(){
            var el = document.getElementById('email-success-overlay');
            if (!el) return;
            window.setTimeout(function(){
                el.style.transition = 'opacity 220ms ease';
                el.style.opacity = '0';
                window.setTimeout(function(){ if (el && el.parentNode) el.parentNode.removeChild(el); }, 240);
            }, 2500);
        })();
    </script>
<?php endif; ?>

<div class="row">
    <div class="col-12">
        <div class="card settings-card" id="account">
            <div class="card-header">
                <strong><i class="bi bi-gear"></i> Configuración</strong>
            </div>
            <div class="card-body">
                <form method="post" action="email.php?id=<?php echo (int)$id; ?>#account">
                    <?php csrfField(); ?>

                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" value="<?php echo html((string)$emailAccount['email']); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Nombre (opcional)</label>
                        <input type="text" class="form-control" name="name" value="<?php echo html((string)($emailAccount['name'] ?? '')); ?>">
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Prioridad</label>
                                <?php $p = (string)($emailAccount['priority'] ?? 'Normal'); ?>
                                <select class="form-select" name="priority">
                                    <option value="Normal" <?php echo $p === 'Normal' ? 'selected' : ''; ?>>Normal</option>
                                    <option value="Alta" <?php echo $p === 'Alta' ? 'selected' : ''; ?>>Alta</option>
                                    <option value="Baja" <?php echo $p === 'Baja' ? 'selected' : ''; ?>>Baja</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Departamento</label>
                                <select class="form-select" name="dept_id">
                                    <option value="">Seleccionar</option>
                                    <?php foreach ($departments as $d): ?>
                                        <option value="<?php echo (int)$d['id']; ?>" <?php echo ((int)($emailAccount['dept_id'] ?? 0) === (int)$d['id']) ? 'selected' : ''; ?>>
                                            <?php echo html((string)$d['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div class="alert alert-info py-2">
                        <div class="fw-semibold mb-1">Ejemplos SMTP</div>
                        <div class="small">
                            Gmail: <span class="text-monospace">smtp.gmail.com</span> puerto <span class="text-monospace">587</span> seguridad <span class="text-monospace">tls</span> (o <span class="text-monospace">465</span> con <span class="text-monospace">ssl</span>)
                        </div>
                        <div class="small">
                            Outlook/Hotmail: <span class="text-monospace">smtp-mail.outlook.com</span> puerto <span class="text-monospace">587</span> seguridad <span class="text-monospace">tls</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">SMTP Host</label>
                        <input type="text" class="form-control" name="smtp_host" value="<?php echo html((string)($emailAccount['smtp_host'] ?? '')); ?>" placeholder="smtp.gmail.com">
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Puerto</label>
                                <input type="number" class="form-control" name="smtp_port" value="<?php echo html((string)($emailAccount['smtp_port'] ?? '')); ?>" placeholder="587">
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label">Seguridad</label>
                                <?php $sec = strtolower((string)($emailAccount['smtp_secure'] ?? '')); ?>
                                <select class="form-select" name="smtp_secure">
                                    <option value="" <?php echo $sec === '' ? 'selected' : ''; ?>>Ninguna</option>
                                    <option value="ssl" <?php echo $sec === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                    <option value="tls" <?php echo $sec === 'tls' ? 'selected' : ''; ?>>TLS (STARTTLS)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Usuario SMTP</label>
                        <input type="text" class="form-control" name="smtp_user" value="<?php echo html((string)($emailAccount['smtp_user'] ?? '')); ?>" placeholder="tuemail@gmail.com">
                    </div>

                    <div class="mb-2">
                        <label class="form-label">Contraseña de aplicación</label>
                        <input type="password" class="form-control" name="smtp_pass" value="" placeholder="(dejar vacío para mantener)">
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="keep_pass" id="keep_pass" checked>
                        <label class="form-check-label" for="keep_pass">Mantener contraseña actual si el campo está vacío</label>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a class="btn btn-outline-secondary" href="emails.php">Cerrar</a>
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
