<?php
require_once '../../../config.php';
require_once '../../../includes/helpers.php';

requireLogin('agente');

if ((string)($_SESSION['staff_role'] ?? '') !== 'superadmin') {
    header('Location: ../index.php');
    exit;
}

ob_start();

global $mysqli;

$err = '';
$msg = '';

$action = strtolower((string)($_POST['action'] ?? 'manual'));

$selectedEmpresaId = isset($_POST['empresa_id']) && is_numeric($_POST['empresa_id']) ? (int)$_POST['empresa_id'] : 0;
$subjectVal = trim((string)($_POST['subject'] ?? 'Aviso del sistema'));
$messageVal = trim((string)($_POST['message'] ?? ''));

$billingDaysVal = trim((string)getAppSetting('billing.notice_days', '3'));
$billingSubjectVal = trim((string)getAppSetting('billing.notice_subject', 'Aviso: vencimiento próximo'));
$billingMessageVal = trim((string)getAppSetting('billing.notice_message', 'Tu plan vence en {dias} día(s) ({vencimiento}).'));

$hasEmpresas = false;
if (isset($mysqli) && $mysqli) {
    try {
        $hasEmpresas = ($mysqli->query('SELECT 1 FROM empresas LIMIT 1') !== false);
    } catch (Throwable $e) {
        $hasEmpresas = false;
    }
}

$staffHasEmpresaId = false;
if (isset($mysqli) && $mysqli) {
    try {
        $res = $mysqli->query("SHOW COLUMNS FROM staff LIKE 'empresa_id'");
        $staffHasEmpresaId = ($res && $res->num_rows > 0);
    } catch (Throwable $e) {
        $staffHasEmpresaId = false;
    }
}

$hasNotifications = false;
if (isset($mysqli) && $mysqli) {
    try {
        $res = $mysqli->query("SHOW TABLES LIKE 'notifications'");
        $hasNotifications = ($res && $res->num_rows > 0);
    } catch (Throwable $e) {
        $hasNotifications = false;
    }
}

$empresas = [];
if ($hasEmpresas && isset($mysqli) && $mysqli) {
    $res = $mysqli->query('SELECT id, nombre FROM empresas ORDER BY nombre');
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $empresas[] = $r;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'billing_settings') {
    if (!validateCSRF()) {
        $err = 'Token de seguridad inválido.';
    } else {
        $billingDaysValNew = trim((string)($_POST['billing_notice_days'] ?? ''));
        $billingSubjectValNew = trim((string)($_POST['billing_notice_subject'] ?? ''));
        $billingMessageValNew = trim((string)($_POST['billing_notice_message'] ?? ''));

        if ($billingDaysValNew === '') {
            $err = 'Los días son obligatorios.';
        } elseif ($billingMessageValNew === '') {
            $err = 'El mensaje es obligatorio.';
        } else {
            setAppSetting('billing.notice_days', $billingDaysValNew);
            setAppSetting('billing.notice_subject', $billingSubjectValNew);
            setAppSetting('billing.notice_message', $billingMessageValNew);
            $billingDaysVal = $billingDaysValNew;
            $billingSubjectVal = $billingSubjectValNew;
            $billingMessageVal = $billingMessageValNew;
            $msg = 'Configuración guardada.';
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF()) {
        $err = 'Token de seguridad inválido.';
    } elseif (!$hasEmpresas) {
        $err = 'No se pudo acceder a la tabla empresas.';
    } elseif (!$staffHasEmpresaId) {
        $err = 'La tabla staff no tiene empresa_id.';
    } elseif (!$hasNotifications) {
        $err = 'No existe la tabla notifications.';
    } elseif ($selectedEmpresaId <= 0) {
        $err = 'Empresa inválida.';
    } elseif ($messageVal === '') {
        $err = 'El mensaje es obligatorio.';
    } else {
        $stmt = $mysqli->prepare("SELECT id, firstname, lastname FROM staff WHERE is_active = 1 AND role = 'admin' AND empresa_id = ? ORDER BY firstname, lastname");
        if (!$stmt) {
            $err = 'No se pudo preparar la consulta de administradores.';
        } else {
            $stmt->bind_param('i', $selectedEmpresaId);
            $stmt->execute();
            $resA = $stmt->get_result();

            $admins = [];
            if ($resA) {
                while ($row = $resA->fetch_assoc()) {
                    $admins[] = $row;
                }
            }

            if (empty($admins)) {
                $err = 'No hay administradores activos en esa empresa.';
            } else {
                $insertMsg = $messageVal;
                if ($subjectVal !== '') {
                    $insertMsg = '[' . $subjectVal . '] ' . $insertMsg;
                }
                $type = 'general';
                $relatedId = 0;

                $stmtI = $mysqli->prepare('INSERT INTO notifications (staff_id, message, type, related_id, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())');
                if (!$stmtI) {
                    $err = 'No se pudo preparar el insert de notificación.';
                } else {
                    $sent = 0;
                    foreach ($admins as $a) {
                        $sid = (int)($a['id'] ?? 0);
                        if ($sid <= 0) continue;
                        $stmtI->bind_param('issi', $sid, $insertMsg, $type, $relatedId);
                        if ($stmtI->execute()) {
                            $sent++;
                        }
                    }
                    if ($sent > 0) {
                        $msg = "Notificación creada para {$sent} administrador(es).";
                        $messageVal = '';
                    } else {
                        $err = 'No se pudo crear la notificación.';
                    }
                }
            }
        }
    }
}
?>

<link rel="stylesheet" href="css/empresas.css">

<div class="emp-hero mb-1">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="hero-icon" style="background:#0d6efd;">
                <i class="bi bi-bell"></i>
            </div>
            <div>
                <h1>Notificaciones</h1>
                <p>Envía avisos internos (campana) a los administradores de una empresa específica</p>
            </div>
        </div>
    </div>
</div>

<?php if ($err !== ''): ?>
    <div class="alert alert-danger mb-3"><?php echo html($err); ?></div>
<?php endif; ?>

<?php if ($msg !== ''): ?>
    <div class="alert alert-success mb-3"><?php echo html($msg); ?></div>
<?php endif; ?>

<p class="section-title"><i class="bi bi-clock"></i> Avisos automáticos de vencimiento</p>

<div class="card pro-card mb-4">
    <div class="card-header">
        <span class="card-title-sm"><i class="bi bi-bell me-1"></i>Configuración</span>
    </div>
    <div class="card-body">
        <form method="post" action="notificaciones.php">
            <?php csrfField(); ?>
            <input type="hidden" name="action" value="billing_settings">
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <label class="form-label">Días antes de vencer</label>
                    <input name="billing_notice_days" class="form-control" value="<?php echo html($billingDaysVal); ?>" placeholder="3,4,5">
                    <div class="form-text">Ejemplo: <strong>3</strong> o <strong>3,4,5</strong></div>
                </div>
                <div class="col-12 col-md-8">
                    <label class="form-label">Asunto (opcional)</label>
                    <input name="billing_notice_subject" class="form-control" value="<?php echo html($billingSubjectVal); ?>" placeholder="Aviso: vencimiento próximo">
                </div>
                <div class="col-12">
                    <label class="form-label">Mensaje</label>
                    <textarea name="billing_notice_message" class="form-control" rows="4" required><?php echo html($billingMessageVal); ?></textarea>
                    <div class="form-text">
                        Variables: <strong>{empresa}</strong>, <strong>{dias}</strong>, <strong>{vencimiento}</strong>
                    </div>
                </div>
            </div>
            <div class="d-flex justify-content-end mt-3">
                <button class="btn btn-primary btn-sm px-4" type="submit">
                    <i class="bi bi-save me-1"></i>Guardar
                </button>
            </div>
        </form>
    </div>
</div>

<p class="section-title"><i class="bi bi-send"></i> Enviar aviso manual</p>

<div class="card pro-card mb-4">
    <div class="card-header">
        <span class="card-title-sm"><i class="bi bi-bell me-1"></i>Nuevo aviso</span>
    </div>
    <div class="card-body">
        <?php if (!$hasEmpresas): ?>
            <div class="alert alert-info mb-0">No se pudo acceder a la tabla <strong>empresas</strong>.</div>
        <?php elseif (!$staffHasEmpresaId): ?>
            <div class="alert alert-info mb-0">La tabla <strong>staff</strong> no tiene <strong>empresa_id</strong>.</div>
        <?php elseif (!$hasNotifications): ?>
            <div class="alert alert-info mb-0">No existe la tabla <strong>notifications</strong>.</div>
        <?php else: ?>
            <form method="post" action="notificaciones.php">
                <?php csrfField(); ?>
                <input type="hidden" name="action" value="manual">
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label">Empresa</label>
                        <select name="empresa_id" class="form-select" required>
                            <option value="">Seleccione</option>
                            <?php foreach ($empresas as $e): ?>
                                <option value="<?php echo (int)$e['id']; ?>" <?php echo (int)$selectedEmpresaId === (int)$e['id'] ? 'selected' : ''; ?>>
                                    <?php echo html((string)$e['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label">Asunto</label>
                        <input name="subject" class="form-control" value="<?php echo html($subjectVal); ?>" placeholder="Asunto del correo">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Mensaje</label>
                        <textarea name="message" class="form-control" rows="5" required><?php echo html($messageVal); ?></textarea>
                        <div class="form-text">Se enviará a la <strong>campana</strong> de los usuarios <strong>admin</strong> activos de la empresa seleccionada.</div>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-3 mt-3 flex-wrap">
                    <button class="btn btn-primary btn-sm px-4" type="submit">
                        <i class="bi bi-send me-1"></i>Crear notificación
                    </button>
                    <a class="btn btn-outline-secondary btn-sm" href="notificaciones.php">Limpiar</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php
$content = (string)ob_get_clean();
$currentRoute = 'notificaciones';
require __DIR__ . '/layout.php';
