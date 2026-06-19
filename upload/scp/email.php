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
        header('Location: emails.php');
        exit;
    } else {
        $_SESSION['flash_error'] = 'No se pudo actualizar el email.';
        header('Location: email.php?id=' . $id . '#account');
        exit;
    }
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

<style>
    #email-saving-overlay{position:fixed;inset:0;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,.25);z-index:2000}
    #email-saving-overlay .box{background:#fff;border-radius:14px;padding:18px 22px;min-width:280px;max-width:90vw;box-shadow:0 10px 30px rgba(0,0,0,.25)}
</style>
<div id="email-saving-overlay" role="status" aria-live="polite" aria-busy="true">
    <div class="box">
        <div class="d-flex align-items-center gap-3">
            <div class="spinner-border text-primary" role="status" aria-hidden="true"></div>
            <div>
                <div class="fw-semibold">Guardando…</div>
                <div class="text-muted small">Por favor espera</div>
            </div>
        </div>
    </div>
</div>

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

<style>
.form-section-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: #334155;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 10px;
    border-bottom: 2px solid #f1f5f9;
    padding-bottom: 10px;
}
body.dark-mode .form-section-title {
    color: #e2e8f0;
    border-bottom-color: #334155;
}
.form-section-title i {
    color: #ef4444;
}
.modern-label {
    font-weight: 600;
    font-size: 0.9rem;
    color: #475569;
    margin-bottom: 6px;
}
body.dark-mode .modern-label {
    color: #94a3b8;
}
.modern-input {
    background-color: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 0.7rem 1rem;
    transition: all 0.2s;
    font-size: 0.95rem;
}
.modern-input:focus {
    background-color: #fff;
    border-color: #ef4444;
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
    outline: none;
}
body.dark-mode .modern-input {
    background-color: #1e293b;
    border-color: #334155;
    color: #f8fafc;
}
body.dark-mode .modern-input:focus {
    background-color: #0f172a;
    border-color: #ef4444;
}
.modern-select {
    appearance: none;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right 0.75rem center;
    background-size: 16px 12px;
}
body.dark-mode .modern-select {
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23eeeeee' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
}

/* Toggle Switch */
.toggle-wrapper {
    display: flex;
    align-items: center;
    gap: 12px;
    cursor: pointer;
}
.modern-switch {
    width: 46px;
    height: 24px;
    background-color: #cbd5e1;
    border-radius: 12px;
    position: relative;
    transition: background-color 0.3s;
    flex-shrink: 0;
}
.modern-switch::after {
    content: '';
    position: absolute;
    top: 2px;
    left: 2px;
    width: 20px;
    height: 20px;
    background-color: #fff;
    border-radius: 50%;
    transition: transform 0.3s;
    box-shadow: 0 2px 4px rgba(0,0,0,0.15);
}
input[type="checkbox"]:checked + .modern-switch {
    background-color: #ef4444;
}
input[type="checkbox"]:checked + .modern-switch::after {
    transform: translateX(22px);
}
body.dark-mode .modern-switch {
    background-color: #475569;
}

.info-card {
    background: linear-gradient(145deg, #fef2f2 0%, #f8fafc 100%);
    border: 1px solid #fecaca;
    border-radius: 12px;
    padding: 16px;
    color: #7f1d1d;
    display: flex;
    gap: 16px;
    align-items: flex-start;
}
.info-card i {
    font-size: 1.5rem;
    color: #ef4444;
}
body.dark-mode .info-card {
    background: linear-gradient(145deg, #450a0a 0%, #2a0a0a 100%);
    border-color: #7f1d1d;
    color: #fee2e2;
}
body.dark-mode .info-card i {
    color: #f87171;
}

.settings-panel {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.03);
    border: 1px solid #f1f5f9;
    padding: 30px;
}
body.dark-mode .settings-panel {
    background: #000000;
    border-color: #2a2a2a;
    box-shadow: 0 4px 20px rgba(0,0,0,0.2);
}
</style>

<div class="row">
    <div class="col-12">
        <div class="settings-panel" id="account">
            <form method="post" action="email.php?id=<?php echo (int)$id; ?>#account">
                <?php csrfField(); ?>

                <!-- Sección: Información Básica -->
                <h3 class="form-section-title">
                    <i class="bi bi-person-badge"></i> Información Básica
                </h3>
                
                <div class="row mb-4">
                    <div class="col-md-6 mb-3 mb-md-0">
                        <label class="modern-label">Correo Electrónico</label>
                        <input type="email" class="form-control modern-input" name="email" value="<?php echo html((string)$emailAccount['email']); ?>" required placeholder="ejemplo@empresa.com">
                    </div>
                    <div class="col-md-6">
                        <label class="modern-label">Nombre del Remitente (Opcional)</label>
                        <input type="text" class="form-control modern-input" name="name" value="<?php echo html((string)($emailAccount['name'] ?? '')); ?>" placeholder="Ej: Soporte Técnico">
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-6 mb-3 mb-md-0">
                        <label class="modern-label">Prioridad de Envío</label>
                        <?php $p = (string)($emailAccount['priority'] ?? 'Normal'); ?>
                        <select class="form-select modern-input modern-select" name="priority">
                            <option value="Normal" <?php echo $p === 'Normal' ? 'selected' : ''; ?>>Normal</option>
                            <option value="Alta" <?php echo $p === 'Alta' ? 'selected' : ''; ?>>Alta</option>
                            <option value="Baja" <?php echo $p === 'Baja' ? 'selected' : ''; ?>>Baja</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="modern-label">Asignar a Departamento</label>
                        <select class="form-select modern-input modern-select" name="dept_id">
                            <option value="">— Sin Asignar —</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?php echo (int)$d['id']; ?>" <?php echo ((int)($emailAccount['dept_id'] ?? 0) === (int)$d['id']) ? 'selected' : ''; ?>>
                                    <?php echo html((string)$d['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Sección: Configuración SMTP -->
                <h3 class="form-section-title mt-5">
                    <i class="bi bi-server"></i> Configuración de Servidor (SMTP)
                </h3>

                <div class="info-card mb-4">
                    <i class="bi bi-lightbulb"></i>
                    <div>
                        <div class="fw-bold mb-1">Guía Rápida de Configuración</div>
                        <div class="small mb-1"><strong>Gmail:</strong> Servidor <code>smtp.gmail.com</code> | Puerto <code>587</code> | Seguridad <code>TLS</code></div>
                        <div class="small"><strong>Outlook/Hotmail:</strong> Servidor <code>smtp-mail.outlook.com</code> | Puerto <code>587</code> | Seguridad <code>TLS</code></div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-12 mb-3">
                        <label class="modern-label">Servidor SMTP (Host)</label>
                        <input type="text" class="form-control modern-input" name="smtp_host" value="<?php echo html((string)($emailAccount['smtp_host'] ?? '')); ?>" placeholder="Ej: smtp.gmail.com">
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-4 mb-3 mb-md-0">
                        <label class="modern-label">Puerto</label>
                        <input type="number" class="form-control modern-input" name="smtp_port" value="<?php echo html((string)($emailAccount['smtp_port'] ?? '')); ?>" placeholder="587">
                    </div>
                    <div class="col-md-8">
                        <label class="modern-label">Tipo de Seguridad</label>
                        <?php $sec = strtolower((string)($emailAccount['smtp_secure'] ?? '')); ?>
                        <select class="form-select modern-input modern-select" name="smtp_secure">
                            <option value="" <?php echo $sec === '' ? 'selected' : ''; ?>>Sin Encriptación</option>
                            <option value="ssl" <?php echo $sec === 'ssl' ? 'selected' : ''; ?>>SSL (Estricto)</option>
                            <option value="tls" <?php echo $sec === 'tls' ? 'selected' : ''; ?>>TLS (Recomendado)</option>
                        </select>
                    </div>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-6 mb-3 mb-md-0">
                        <label class="modern-label">Usuario SMTP</label>
                        <input type="text" class="form-control modern-input" name="smtp_user" value="<?php echo html((string)($emailAccount['smtp_user'] ?? '')); ?>" placeholder="ejemplo@empresa.com">
                    </div>
                    <div class="col-md-6">
                        <label class="modern-label">Contraseña de Aplicación</label>
                        <input type="password" class="form-control modern-input" name="smtp_pass" value="" placeholder="••••••••••••">
                    </div>
                </div>

                <div class="mb-4 mt-2">
                    <label class="toggle-wrapper" for="keep_pass">
                        <input type="checkbox" name="keep_pass" id="keep_pass" class="d-none" checked>
                        <div class="modern-switch"></div>
                        <span class="text-muted small fw-medium">Mantener la contraseña actual si el campo se deja vacío</span>
                    </label>
                </div>

                <hr style="border-color: #e2e8f0; margin: 30px 0;">

                <div class="d-flex justify-content-end gap-3">
                    <a class="btn btn-light px-4 py-2" style="border-radius: 8px; font-weight: 500;" href="emails.php">Cancelar</a>
                    <button type="submit" class="btn btn-primary px-4 py-2" style="border-radius: 8px; font-weight: 600;" id="btn-email-save">
                        <i class="bi bi-check-circle me-1"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    (function(){
        try {
            var overlay = document.getElementById('email-saving-overlay');
            var btn = document.getElementById('btn-email-save');
            var form = btn ? btn.closest('form') : null;
            if (!form) return;
            form.addEventListener('submit', function(){
                if (overlay) overlay.style.display = 'flex';
                if (btn) btn.disabled = true;
            });
        } catch (e) {}
    })();
</script>

<?php
$content = ob_get_clean();
require_once 'layout_admin.php';
?>
