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
$currentRoute = 'settings';

$currentStaffId = (int)($_SESSION['staff_id'] ?? 0);
$currentStaffRole = '';
if ($currentStaffId > 0) {
    $stmtMe = $mysqli->prepare("SELECT role FROM staff WHERE id = ? LIMIT 1");
    if ($stmtMe) {
        $stmtMe->bind_param('i', $currentStaffId);
        $stmtMe->execute();
        $me = $stmtMe->get_result()->fetch_assoc();
        $currentStaffRole = (string)($me['role'] ?? '');
    }
}

if (!$staff || $currentStaffRole !== 'admin') {
    $_SESSION['flash_error'] = 'No tienes permisos para gestionar secuencias.';
    header('Location: settings.php?t=tickets');
    exit;
}

$msg = '';
$error = '';

if ($_POST) {
    if (!validateCSRF()) {
        $error = 'Token de seguridad inválido';
    } else {
        $action = (string)($_POST['action'] ?? '');
        
        if ($action === 'create') {
            $name = trim((string)($_POST['name'] ?? ''));
            $next = (int)($_POST['next'] ?? 1);
            $increment = (int)($_POST['increment'] ?? 1);
            $padding = (int)($_POST['padding'] ?? 0);

            if ($name === '') {
                $name = 'Nueva secuencia ' . date('YmdHis');
            }
            if ($next < 0) {
                $error = 'El siguiente número debe ser mayor o igual a 0.';
            } elseif ($increment < 1) {
                $error = 'El incremento debe ser al menos 1.';
            } elseif ($padding < 0 || $padding > 20) {
                $error = 'El relleno debe estar entre 0 y 20.';
            } else {
                $stmt = $mysqli->prepare('INSERT INTO sequences (name, next, increment, padding, created) VALUES (?, ?, ?, ?, NOW())');
                $stmt->bind_param('siii', $name, $next, $increment, $padding);
                if ($stmt->execute()) {
                    $msg = 'Secuencia creada correctamente.';
                } else {
                    if ($mysqli->errno === 1062) {
                        $error = 'Ya existe una secuencia con ese nombre.';
                    } else {
                        $error = 'Error al crear la secuencia: ' . $mysqli->error;
                    }
                }
            }
        } elseif ($action === 'update') {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim((string)($_POST['name'] ?? ''));
            $next = (int)($_POST['next'] ?? 1);
            $increment = (int)($_POST['increment'] ?? 1);
            $padding = (int)($_POST['padding'] ?? 0);
            
            if ($id <= 0) {
                $error = 'ID inválido.';
            } elseif ($name === '') {
                $error = 'El nombre es requerido.';
            } elseif ($next < 0) {
                $error = 'El siguiente número debe ser mayor o igual a 0.';
            } elseif ($increment < 1) {
                $error = 'El incremento debe ser al menos 1.';
            } elseif ($padding < 0 || $padding > 20) {
                $error = 'El relleno debe estar entre 0 y 20.';
            } else {
                $stmt = $mysqli->prepare('UPDATE sequences SET name = ?, next = ?, increment = ?, padding = ?, updated = NOW() WHERE id = ?');
                $stmt->bind_param('siiii', $name, $next, $increment, $padding, $id);
                if ($stmt->execute()) {
                    $msg = 'Secuencia actualizada correctamente.';
                } else {
                    if ($mysqli->errno === 1062) {
                        $error = 'Ya existe una secuencia con ese nombre.';
                    } else {
                        $error = 'Error al actualizar la secuencia: ' . $mysqli->error;
                    }
                }
            }
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                $error = 'ID inválido.';
            } else {
                $checkUsage = $mysqli->prepare("SELECT COUNT(*) as cnt FROM app_settings WHERE `key` = 'tickets.ticket_sequence_id' AND `value` = ?");
                $idStr = (string)$id;
                $checkUsage->bind_param('s', $idStr);
                $checkUsage->execute();
                $usage = $checkUsage->get_result()->fetch_assoc();
                if ((int)($usage['cnt'] ?? 0) > 0) {
                    $error = 'No se puede eliminar esta secuencia porque está en uso.';
                } else {
                    $stmt = $mysqli->prepare('DELETE FROM sequences WHERE id = ?');
                    $stmt->bind_param('i', $id);
                    if ($stmt->execute()) {
                        $msg = 'Secuencia eliminada correctamente.';
                    } else {
                        $error = 'Error al eliminar la secuencia: ' . $mysqli->error;
                    }
                }
            }
        }
    }
}

$sequences = [];
$res = $mysqli->query('SELECT id, name, next, increment, padding, created, updated FROM sequences ORDER BY id');
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $sequences[] = $row;
    }
}

$embed = (string)($_GET['embed'] ?? '') === '1';

$content = '';
ob_start();
?>
<?php if (!$embed): ?>
<div class="settings-hero">
    <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
        <div class="d-flex align-items-center gap-3">
            <span class="settings-hero-icon"><i class="bi bi-arrow-repeat"></i></span>
            <div>
                <h1>Gestionar las secuencias</h1>
                <p>Las secuencias se utilizan para generar números secuenciales. Se pueden usar varias secuencias para generar secuencias diferentes para diferentes propósitos.</p>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo html($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
<?php if ($msg): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo html($msg); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($embed): ?>
    <div class="d-flex align-items-center justify-content-between mb-2">
        <div class="fw-semibold">Secuencias</div>
        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#createSequenceModal">
            <i class="bi bi-plus"></i> Agregar
        </button>
    </div>

    <?php if (empty($sequences)): ?>
        <div class="text-muted small">No hay secuencias creadas.</div>
    <?php endif; ?>

    <div class="d-flex flex-column gap-2">
        <?php foreach ($sequences as $seq): ?>
            <div class="border rounded p-2">
                <form method="post" class="m-0">
                    <?php csrfField(); ?>
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" value="<?php echo (int)$seq['id']; ?>">
                    <input type="hidden" name="next" value="<?php echo html((string)$seq['next']); ?>">

                    <div class="d-flex align-items-center justify-content-between gap-2">
                        <input type="text" class="form-control form-control-sm" name="name" value="<?php echo html((string)$seq['name']); ?>" style="max-width: 380px;">
                        <div class="d-flex align-items-center gap-2 flex-shrink-0">
                            <div class="text-muted small">next&nbsp;<?php echo html((string)$seq['next']); ?></div>
                            <button type="submit" class="btn btn-sm btn-outline-secondary" title="Guardar">
                                <i class="bi bi-save"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger" title="Eliminar" onclick="deleteSequence(<?php echo (int)$seq['id']; ?>, '<?php echo addslashes((string)$seq['name']); ?>')">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>

                    <div class="d-flex align-items-center justify-content-between gap-2 mt-2">
                        <div class="d-flex align-items-center gap-2">
                            <label class="small text-muted">Incrementar:</label>
                            <input type="number" class="form-control form-control-sm" name="increment" value="<?php echo html((string)$seq['increment']); ?>" min="1" style="width:90px;">
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <label class="small text-muted">Carácter de relleno:</label>
                            <input type="number" class="form-control form-control-sm" name="padding" value="<?php echo html((string)$seq['padding']); ?>" min="0" max="20" style="width:90px;">
                        </div>
                    </div>
                </form>
            </div>
        <?php endforeach; ?>
    </div>

<?php else: ?>
    <div class="card settings-card">
        <div class="card-header d-flex align-items-center justify-content-between">
            <strong>Secuencias disponibles</strong>
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createSequenceModal">
                <i class="bi bi-plus-circle"></i> Agregar nueva secuencia
            </button>
        </div>
        <div class="card-body">
            <?php if (empty($sequences)): ?>
                <p class="text-muted">No hay secuencias creadas.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th><i class="bi bi-tag"></i> Nombre</th>
                                <th>Siguiente</th>
                                <th>Incrementar</th>
                                <th>Carácter de relleno</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sequences as $seq): ?>
                                <tr>
                                    <td><span class="fw-semibold small"><?php echo html($seq['name']); ?></span></td>
                                    <td><?php echo html((string)$seq['next']); ?></td>
                                    <td><?php echo html((string)$seq['increment']); ?></td>
                                    <td><?php echo html((string)$seq['padding']); ?></td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                            onclick="editSequence(<?php echo (int)$seq['id']; ?>, '<?php echo addslashes($seq['name']); ?>', <?php echo (int)$seq['next']; ?>, <?php echo (int)$seq['increment']; ?>, <?php echo (int)$seq['padding']; ?>)">
                                            <i class="bi bi-pencil"></i> Editar
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                            onclick="deleteSequence(<?php echo (int)$seq['id']; ?>, '<?php echo addslashes($seq['name']); ?>')">
                                            <i class="bi bi-trash"></i> Eliminar
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<div class="modal fade" id="createSequenceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <?php csrfField(); ?>
                <input type="hidden" name="action" value="create">
                <div class="modal-header">
                    <h5 class="modal-title">Agregar nueva secuencia</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Incrementar</label>
                        <input type="number" class="form-control" name="increment" value="1" min="1" required>
                        <div class="form-text">Cantidad a incrementar cada vez</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Carácter de relleno</label>
                        <input type="number" class="form-control" name="padding" value="0" min="0" max="20">
                        <div class="form-text">Número de dígitos (0 = sin relleno)</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Crear secuencia</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editSequenceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <?php csrfField(); ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header">
                    <h5 class="modal-title">Editar secuencia</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nombre</label>
                        <input type="text" class="form-control" name="name" id="edit_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Siguiente</label>
                        <input type="number" class="form-control" name="next" id="edit_next" min="0" required>
                        <div class="form-text">El próximo número que se generará</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Incrementar</label>
                        <input type="number" class="form-control" name="increment" id="edit_increment" min="1" required>
                        <div class="form-text">Cantidad a incrementar cada vez</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Carácter de relleno</label>
                        <input type="number" class="form-control" name="padding" id="edit_padding" min="0" max="20">
                        <div class="form-text">Número de dígitos (0 = sin relleno)</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteSequenceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <?php csrfField(); ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="delete_id">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmar eliminación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>¿Estás seguro de que deseas eliminar la secuencia <strong id="delete_name"></strong>?</p>
                    <p class="text-danger">Esta acción no se puede deshacer.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Eliminar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editSequence(id, name, next, increment, padding) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_next').value = next;
    document.getElementById('edit_increment').value = increment;
    document.getElementById('edit_padding').value = padding;
    var modal = new bootstrap.Modal(document.getElementById('editSequenceModal'));
    modal.show();
}

function deleteSequence(id, name) {
    document.getElementById('delete_id').value = id;
    document.getElementById('delete_name').textContent = name;
    var modal = new bootstrap.Modal(document.getElementById('deleteSequenceModal'));
    modal.show();
}
</script>
<?php
$content = ob_get_clean();

if ($embed) {
    ?><!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Secuencias</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
        <link rel="stylesheet" href="css/scp.css?v=<?php echo (int)@filemtime(__DIR__ . '/css/scp.css'); ?>">
    </head>
    <body class="p-3" style="background:#fff;">
        <?php echo $content; ?>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html><?php
    exit;
}

require_once 'layout_admin.php';
