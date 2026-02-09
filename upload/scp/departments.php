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
$currentRoute = 'departments';

$flashError = (string)($_SESSION['flash_error'] ?? '');
$flashMsg = (string)($_SESSION['flash_msg'] ?? '');
unset($_SESSION['flash_error'], $_SESSION['flash_msg']);

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF()) {
        $_SESSION['flash_error'] = 'Token CSRF inválido.';
        header('Location: departments.php');
        exit;
    }

    $do = (string)($_POST['do'] ?? '');

    if ($do === 'create') {
        $name = trim((string)($_POST['name'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($name === '') {
            $_SESSION['flash_error'] = 'El nombre del departamento es requerido.';
            header('Location: departments.php');
            exit;
        }

        $stmt = $mysqli->prepare('INSERT INTO departments (name, description, is_active, created) VALUES (?, ?, ?, NOW())');
        if (!$stmt) {
            $_SESSION['flash_error'] = 'No se pudo crear el departamento.';
            header('Location: departments.php');
            exit;
        }
        $descParam = $description !== '' ? $description : null;
        $stmt->bind_param('ssi', $name, $descParam, $isActive);
        if ($stmt->execute()) {
            $_SESSION['flash_msg'] = 'Departamento creado correctamente.';
        } else {
            $_SESSION['flash_error'] = 'No se pudo crear el departamento (puede que ya exista).';
        }
        header('Location: departments.php');
        exit;
    }

    if ($do === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($id <= 0) {
            $_SESSION['flash_error'] = 'ID inválido.';
            header('Location: departments.php');
            exit;
        }
        if ($name === '') {
            $_SESSION['flash_error'] = 'El nombre del departamento es requerido.';
            header('Location: departments.php');
            exit;
        }

        $stmt = $mysqli->prepare('UPDATE departments SET name = ?, description = ?, is_active = ? WHERE id = ?');
        if (!$stmt) {
            $_SESSION['flash_error'] = 'No se pudo actualizar el departamento.';
            header('Location: departments.php');
            exit;
        }
        $descParam = $description !== '' ? $description : null;
        $stmt->bind_param('ssii', $name, $descParam, $isActive, $id);
        if ($stmt->execute()) {
            $_SESSION['flash_msg'] = 'Departamento actualizado correctamente.';
        } else {
            $_SESSION['flash_error'] = 'No se pudo actualizar el departamento.';
        }
        header('Location: departments.php');
        exit;
    }

    if ($do === 'mass_process') {
        $ids = $_POST['ids'] ?? [];
        $action = (string)($_POST['a'] ?? '');

        if (empty($ids) || !is_array($ids)) {
            $_SESSION['flash_error'] = 'Debe seleccionar al menos un departamento.';
            header('Location: departments.php');
            exit;
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($v) => $v > 0)));
        if (empty($ids)) {
            $_SESSION['flash_error'] = 'Debe seleccionar al menos un departamento.';
            header('Location: departments.php');
            exit;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));

        if ($action === 'enable' || $action === 'disable') {
            $enabled = $action === 'enable' ? 1 : 0;
            $stmt = $mysqli->prepare("UPDATE departments SET is_active = ? WHERE id IN ($placeholders)");
            if ($stmt) {
                $stmt->bind_param('i' . $types, $enabled, ...$ids);
                $stmt->execute();
                $_SESSION['flash_msg'] = $enabled ? 'Departamentos habilitados correctamente.' : 'Departamentos deshabilitados correctamente.';
            } else {
                $_SESSION['flash_error'] = 'No se pudo procesar la acción.';
            }
            header('Location: departments.php');
            exit;
        }

        if ($action === 'delete') {
            $stmtCnt = $mysqli->prepare("SELECT COUNT(*) c FROM staff WHERE dept_id IN ($placeholders)");
            if ($stmtCnt) {
                $stmtCnt->bind_param($types, ...$ids);
                $stmtCnt->execute();
                $row = $stmtCnt->get_result()->fetch_assoc();
                if ((int)($row['c'] ?? 0) > 0) {
                    $_SESSION['flash_error'] = 'No se pueden eliminar departamentos que tienen agentes asignados.';
                    header('Location: departments.php');
                    exit;
                }
            }

            $stmtCntT = $mysqli->prepare("SELECT COUNT(*) c FROM tickets WHERE dept_id IN ($placeholders)");
            if ($stmtCntT) {
                $stmtCntT->bind_param($types, ...$ids);
                $stmtCntT->execute();
                $row = $stmtCntT->get_result()->fetch_assoc();
                if ((int)($row['c'] ?? 0) > 0) {
                    $_SESSION['flash_error'] = 'No se pueden eliminar departamentos que tienen tickets asignados.';
                    header('Location: departments.php');
                    exit;
                }
            }

            $stmt = $mysqli->prepare("DELETE FROM departments WHERE id IN ($placeholders)");
            if ($stmt) {
                $stmt->bind_param($types, ...$ids);
                if ($stmt->execute()) {
                    $_SESSION['flash_msg'] = 'Departamentos eliminados correctamente.';
                } else {
                    $_SESSION['flash_error'] = 'No se pudieron eliminar los departamentos.';
                }
            } else {
                $_SESSION['flash_error'] = 'No se pudieron eliminar los departamentos.';
            }
            header('Location: departments.php');
            exit;
        }

        $_SESSION['flash_error'] = 'Acción no reconocida.';
        header('Location: departments.php');
        exit;
    }
}

$departments = [];
$sql = "
    SELECT
        d.id,
        d.name,
        d.description,
        d.is_active,
        ea.id AS email_id,
        ea.email AS dept_email,
        ea.name AS dept_email_name,
        COUNT(DISTINCT s.id) AS staff_total,
        COUNT(DISTINCT t.id) AS ticket_total
    FROM departments d
    LEFT JOIN (
        SELECT dept_id, MIN(id) AS email_id
        FROM email_accounts
        WHERE dept_id IS NOT NULL
        GROUP BY dept_id
    ) eam ON eam.dept_id = d.id
    LEFT JOIN email_accounts ea ON ea.id = eam.email_id
    LEFT JOIN staff s ON s.dept_id = d.id
    LEFT JOIN tickets t ON t.dept_id = d.id
    GROUP BY d.id, d.name, d.description, d.is_active, ea.id, ea.email, ea.name
    ORDER BY d.name
";
$res = $mysqli->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $departments[] = $row;
    }
}

$activeCount = 0;
$inactiveCount = 0;
foreach ($departments as $d) {
    if ((int)($d['is_active'] ?? 0) === 1) $activeCount++;
    else $inactiveCount++;
}

ob_start();
?>

<div class="settings-hero">
    <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
        <div class="d-flex align-items-center gap-3">
            <span class="settings-hero-icon"><i class="bi bi-diagram-3"></i></span>
            <div>
                <h1>Departamentos</h1>
                <p>Gestión de departamentos</p>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="badge bg-success"><?php echo (int)$activeCount; ?> Activos</span>
            <span class="badge bg-secondary"><?php echo (int)$inactiveCount; ?> Inactivos</span>
            <span class="badge bg-info"><?php echo (int)count($departments); ?> Total</span>
        </div>
    </div>
</div>

<?php if ($flashError): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i><?php echo html($flashError); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($flashMsg): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i><?php echo html($flashMsg); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="alert alert-danger alert-dismissible fade show d-none" role="alert" id="deptsClientError" aria-live="polite">
    <i class="bi bi-exclamation-triangle me-2"></i><span id="deptsClientErrorText"></span>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>

<div class="row">
    <div class="col-12">
        <div class="card settings-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong><i class="bi bi-diagram-3"></i> Lista de Departamentos</strong>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addDeptModal">
                        <i class="bi bi-plus-circle"></i> Añadir nuevo Departamento
                    </button>
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" id="deptsMoreDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-gear"></i> Más
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="deptsMoreDropdown">
                            <li><button class="dropdown-item" type="button" data-dept-action="enable"><i class="bi bi-check-circle me-2"></i>Habilitar</button></li>
                            <li><button class="dropdown-item" type="button" data-dept-action="disable"><i class="bi bi-slash-circle me-2"></i>Deshabilitar</button></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><button class="dropdown-item text-danger" type="button" data-dept-action="delete"><i class="bi bi-trash me-2"></i>Eliminar</button></li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <form method="post" action="departments.php" id="deptsMassForm">
                    <input type="hidden" name="do" value="mass_process">
                    <?php csrfField(); ?>
                    <input type="hidden" name="a" value="" id="deptsMassAction">

                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th width="30"><input type="checkbox" id="selectAllDepts" class="form-check-input"></th>
                                    <th>Departamento</th>
                                    <th class="text-center">Estado</th>
                                    <th class="text-center">Agentes</th>
                                    <th>Correo Electrónico</th>
                                    <th class="text-center">Tickets</th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($departments)): ?>
                                    <tr><td colspan="7" class="text-center text-muted py-4">No hay departamentos para mostrar.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($departments as $d): ?>
                                        <?php
                                        $id = (int)($d['id'] ?? 0);
                                        $name = (string)($d['name'] ?? '');
                                        $description = (string)($d['description'] ?? '');
                                        $active = (int)($d['is_active'] ?? 0) === 1;
                                        $emailId = (int)($d['email_id'] ?? 0);
                                        $deptEmail = (string)($d['dept_email'] ?? '');
                                        $deptEmailName = (string)($d['dept_email_name'] ?? '');
                                        $staffTotal = (int)($d['staff_total'] ?? 0);
                                        $ticketTotal = (int)($d['ticket_total'] ?? 0);
                                        ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" name="ids[]" value="<?php echo $id; ?>" class="form-check-input dept-checkbox">
                                            </td>
                                            <td>
                                                <div class="fw-semibold">
                                                    <?php echo html($name); ?>
                                                </div>
                                                <div class="text-muted small">#<?php echo $id; ?><?php if ($description !== ''): ?> · <?php echo html($description); ?><?php endif; ?></div>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($active): ?>
                                                    <span class="badge bg-success">Activo</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Inactivo</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center"><strong><?php echo (int)$staffTotal; ?></strong></td>
                                            <td>
                                                <?php if ($deptEmail !== '' && $emailId > 0): ?>
                                                    <a class="text-decoration-none" href="email.php?id=<?php echo (int)$emailId; ?>">
                                                        <?php if ($deptEmailName !== ''): ?>
                                                            <?php echo html($deptEmailName); ?> &lt;<?php echo html($deptEmail); ?>&gt;
                                                        <?php else: ?>
                                                            <?php echo html($deptEmail); ?>
                                                        <?php endif; ?>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center"><strong><?php echo (int)$ticketTotal; ?></strong></td>
                                            <td class="text-end">
                                                <button type="button" class="btn btn-sm btn-outline-primary dept-edit-btn"
                                                    data-id="<?php echo $id; ?>"
                                                    data-name="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-description="<?php echo htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-is-active="<?php echo $active ? '1' : '0'; ?>"
                                                    data-bs-toggle="modal" data-bs-target="#editDeptModal">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
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

<div class="modal fade" id="addDeptModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="departments.php">
                <input type="hidden" name="do" value="create">
                <?php csrfField(); ?>
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle text-primary"></i> Añadir nuevo Departamento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nombre</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" id="createDeptIsActive" checked>
                        <label class="form-check-label" for="createDeptIsActive">Activo</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle"></i> Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editDeptModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="departments.php">
                <input type="hidden" name="do" value="update">
                <?php csrfField(); ?>
                <input type="hidden" name="id" id="dept_edit_id" value="">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil text-primary"></i> Editar Departamento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nombre</label>
                        <input type="text" name="name" id="dept_edit_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea name="description" id="dept_edit_description" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" id="dept_edit_is_active" checked>
                        <label class="form-check-label" for="dept_edit_is_active">Activo</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle"></i> Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
window.addEventListener('DOMContentLoaded', function(){
    function getCheckedIds(){
        var boxes = document.querySelectorAll('.dept-checkbox:checked');
        var ids = [];
        boxes.forEach(function(b){ ids.push(b.value); });
        return ids;
    }

    function requireAtLeastOneDeptSelected(ids) {
        if (ids.length < 1) {
            var box = document.getElementById('deptsClientError');
            var txt = document.getElementById('deptsClientErrorText');
            if (txt) txt.textContent = 'Debe seleccionar al menos un departamento';
            if (box) {
                box.classList.remove('d-none');
                box.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
            return false;
        }
        return true;
    }

    var selectAll = document.getElementById('selectAllDepts');
    if (selectAll) {
        selectAll.addEventListener('change', function(){
            var boxes = document.querySelectorAll('.dept-checkbox');
            boxes.forEach(function(b){ b.checked = selectAll.checked; });
        });
    }

    var actionButtons = document.querySelectorAll('[data-dept-action]');
    actionButtons.forEach(function(btn){
        btn.addEventListener('click', function(){
            var ids = getCheckedIds();
            if (!requireAtLeastOneDeptSelected(ids)) return;
            var action = btn.getAttribute('data-dept-action') || '';
            if (action === 'delete' && !confirm('¿Deseas eliminar los departamentos seleccionados?')) return;
            var form = document.getElementById('deptsMassForm');
            var act = document.getElementById('deptsMassAction');
            if (!form || !act) return;
            act.value = action;
            form.submit();
        });
    });

    var editBtns = document.querySelectorAll('.dept-edit-btn');
    editBtns.forEach(function(btn){
        btn.addEventListener('click', function(){
            var id = btn.getAttribute('data-id') || '';
            var name = btn.getAttribute('data-name') || '';
            var desc = btn.getAttribute('data-description') || '';
            var active = (btn.getAttribute('data-is-active') || '0') === '1';
            var idEl = document.getElementById('dept_edit_id');
            var nameEl = document.getElementById('dept_edit_name');
            var descEl = document.getElementById('dept_edit_description');
            var actEl = document.getElementById('dept_edit_is_active');
            if (idEl) idEl.value = id;
            if (nameEl) nameEl.value = name;
            if (descEl) descEl.value = desc;
            if (actEl) actEl.checked = active;
        });
    });
});
</script>

<?php
$content = ob_get_clean();

require_once 'layout_admin.php';
?>
