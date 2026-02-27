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

$selectedEmpresaId = isset($_POST['empresa_id']) && is_numeric($_POST['empresa_id']) ? (int)$_POST['empresa_id'] : 0;
$subjectVal = trim((string)($_POST['subject'] ?? 'Aviso del sistema'));
$messageVal = trim((string)($_POST['message'] ?? ''));

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
