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
$emailTab = 'banlist';

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

$ensureBanlistTable = function () use ($mysqli) {
    if (!isset($mysqli) || !$mysqli) return false;
    $sql = "CREATE TABLE IF NOT EXISTS banlist (\n"
        . "  id INT PRIMARY KEY AUTO_INCREMENT,\n"
        . "  email VARCHAR(255) NULL,\n"
        . "  domain VARCHAR(255) NULL,\n"
        . "  notes TEXT NULL,\n"
        . "  is_active TINYINT(1) NOT NULL DEFAULT 1,\n"
        . "  created DATETIME DEFAULT CURRENT_TIMESTAMP,\n"
        . "  updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
        . "  KEY idx_email (email),\n"
        . "  KEY idx_domain (domain),\n"
        . "  KEY idx_active (is_active)\n"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    return (bool)$mysqli->query($sql);
};
$ensureBanlistTable();

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF()) {
        $_SESSION['flash_error'] = 'Token CSRF inválido.';
        header('Location: banlist.php');
        exit;
    }

    $do = (string)($_POST['do'] ?? '');

    if ($do === 'create') {
        $email = trim((string)($_POST['email'] ?? ''));
        $domain = trim((string)($_POST['domain'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));

        if ($email === '' && $domain === '') {
            $_SESSION['flash_error'] = 'Debes ingresar un email o un dominio.';
            header('Location: banlist.php');
            exit;
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash_error'] = 'Email inválido.';
            header('Location: banlist.php');
            exit;
        }
        if ($domain !== '' && preg_match('/\s/', $domain)) {
            $_SESSION['flash_error'] = 'Dominio inválido.';
            header('Location: banlist.php');
            exit;
        }

        $emailParam = ($email !== '') ? strtolower($email) : null;
        $domainParam = ($domain !== '') ? strtolower($domain) : null;
        $notesParam = ($notes !== '') ? $notes : null;

        $existingId = 0;
        if ($emailParam !== null) {
            $stmtChk = $mysqli->prepare('SELECT id FROM banlist WHERE email = ? LIMIT 1');
            if ($stmtChk) {
                $stmtChk->bind_param('s', $emailParam);
                $stmtChk->execute();
                $row = $stmtChk->get_result()->fetch_assoc();
                $existingId = (int)($row['id'] ?? 0);
            }
        } elseif ($domainParam !== null) {
            $stmtChk = $mysqli->prepare('SELECT id FROM banlist WHERE domain = ? LIMIT 1');
            if ($stmtChk) {
                $stmtChk->bind_param('s', $domainParam);
                $stmtChk->execute();
                $row = $stmtChk->get_result()->fetch_assoc();
                $existingId = (int)($row['id'] ?? 0);
            }
        }

        $affectedBanId = 0;
        if ($existingId > 0) {
            $stmtU = $mysqli->prepare('UPDATE banlist SET email = ?, domain = ?, notes = ?, is_active = 0, updated = NOW() WHERE id = ?');
            if ($stmtU) {
                $stmtU->bind_param('sssi', $emailParam, $domainParam, $notesParam, $existingId);
                if ($stmtU->execute()) {
                    $_SESSION['flash_msg'] = 'Elemento actualizado en la lista de prohibidos.';
                    $affectedBanId = $existingId;
                } else {
                    $_SESSION['flash_error'] = 'No se pudo actualizar.';
                }
            } else {
                $_SESSION['flash_error'] = 'No se pudo actualizar.';
            }
        } else {
            $stmt = $mysqli->prepare('INSERT INTO banlist (email, domain, notes, is_active, created, updated) VALUES (?, ?, ?, 0, NOW(), NOW())');
            if ($stmt) {
                $stmt->bind_param('sss', $emailParam, $domainParam, $notesParam);
                if ($stmt->execute()) {
                    $_SESSION['flash_msg'] = 'Elemento agregado a la lista de prohibidos.';
                    $affectedBanId = (int)($mysqli->insert_id ?? 0);
                } else {
                    $_SESSION['flash_error'] = 'No se pudo agregar.';
                }
            } else {
                $_SESSION['flash_error'] = 'No se pudo agregar.';
            }
        }

        if ($affectedBanId > 0) {
            $stmtForce = $mysqli->prepare('UPDATE banlist SET is_active = 0 WHERE id = ?');
            if ($stmtForce) {
                $stmtForce->bind_param('i', $affectedBanId);
                $stmtForce->execute();
            }
        }

        if ($emailParam !== null) {
            $stmtBanUser = $mysqli->prepare("UPDATE users SET status = 'banned' WHERE email = ?");
            if ($stmtBanUser) {
                $stmtBanUser->bind_param('s', $emailParam);
                $stmtBanUser->execute();
            }
        }

        header('Location: banlist.php');
        exit;
    }

    if ($do === 'mass_process') {
        $ids = $_POST['ids'] ?? [];
        $action = (string)($_POST['a'] ?? '');

        if (empty($ids) || !is_array($ids)) {
            $_SESSION['flash_error'] = 'Debes seleccionar al menos un elemento.';
            header('Location: banlist.php');
            exit;
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($v) => $v > 0)));
        if (empty($ids)) {
            $_SESSION['flash_error'] = 'Debes seleccionar al menos un elemento.';
            header('Location: banlist.php');
            exit;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));

        if ($action === 'delete') {
            $stmt = $mysqli->prepare("DELETE FROM banlist WHERE id IN ($placeholders)");
            if ($stmt) {
                $stmt->bind_param($types, ...$ids);
                if ($stmt->execute()) {
                    $_SESSION['flash_msg'] = 'Elementos eliminados.';
                } else {
                    $_SESSION['flash_error'] = 'No se pudo eliminar.';
                }
            } else {
                $_SESSION['flash_error'] = 'No se pudo eliminar.';
            }
            header('Location: banlist.php');
            exit;
        }

        if ($action === 'enable' || $action === 'disable') {
            $val = ($action === 'enable') ? 1 : 0;
            $stmt = $mysqli->prepare("UPDATE banlist SET is_active = $val WHERE id IN ($placeholders)");
            if ($stmt) {
                $stmt->bind_param($types, ...$ids);
                if ($stmt->execute()) {
                    $_SESSION['flash_msg'] = 'Lista actualizada.';

                    $stmtE = $mysqli->prepare("SELECT email FROM banlist WHERE id IN ($placeholders) AND email IS NOT NULL AND email <> ''");
                    if ($stmtE) {
                        $stmtE->bind_param($types, ...$ids);
                        $stmtE->execute();
                        $resE = $stmtE->get_result();
                        $emails = [];
                        while ($r = $resE->fetch_assoc()) {
                            $em = strtolower(trim((string)($r['email'] ?? '')));
                            if ($em !== '' && filter_var($em, FILTER_VALIDATE_EMAIL)) $emails[] = $em;
                        }

                        if (!empty($emails)) {
                            $emails = array_values(array_unique($emails));
                            $emPlace = implode(',', array_fill(0, count($emails), '?'));
                            $emTypes = str_repeat('s', count($emails));
                            if ($action === 'disable') {
                                $stmtU = $mysqli->prepare("UPDATE users SET status = 'banned' WHERE email IN ($emPlace)");
                            } else {
                                $stmtU = $mysqli->prepare("UPDATE users SET status = 'active' WHERE email IN ($emPlace)");
                            }
                            if ($stmtU) {
                                $stmtU->bind_param($emTypes, ...$emails);
                                $stmtU->execute();
                            }
                        }
                    }
                } else {
                    $_SESSION['flash_error'] = 'No se pudo actualizar.';
                }
            } else {
                $_SESSION['flash_error'] = 'No se pudo actualizar.';
            }
            header('Location: banlist.php');
            exit;
        }

        $_SESSION['flash_error'] = 'Acción no reconocida.';
        header('Location: banlist.php');
        exit;
    }
}

$items = [];
$res = $mysqli->query("SELECT * FROM banlist WHERE (notes IS NULL OR notes <> 'Sincronizado desde users.status=banned') ORDER BY is_active DESC, id DESC");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $items[] = $r;
    }
}

ob_start();
?>

<div class="settings-hero">
    <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
        <div class="d-flex align-items-center gap-3">
            <span class="settings-hero-icon"><i class="bi bi-envelope"></i></span>
            <div>
                <h1>Correos Electrónicos</h1>
                <p>Lista de correos y dominios prohibidos</p>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addBanModal">
                <i class="bi bi-plus-circle"></i> Agregar
            </button>
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
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong><i class="bi bi-slash-circle"></i> Prohibidos</strong>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-outline-success btn-sm" id="enableBanBtn"><i class="bi bi-check2-circle"></i> Activar</button>
                    <button type="button" class="btn btn-outline-warning btn-sm" id="disableBanBtn"><i class="bi bi-slash-circle"></i> Bloquear</button>
                    <button type="button" class="btn btn-outline-danger btn-sm" id="deleteBanBtn"><i class="bi bi-trash"></i> Eliminar</button>
                </div>
            </div>
            <div class="card-body p-0">
                <form method="post" action="banlist.php" id="banMassForm">
                    <input type="hidden" name="do" value="mass_process">
                    <?php csrfField(); ?>
                    <input type="hidden" name="a" value="" id="banActionInput">

                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th width="30"><input type="checkbox" id="selectAllBan" class="form-check-input"></th>
                                    <th>Email</th>
                                    <th>Dominio</th>
                                    <th>Notas</th>
                                    <th>Estado</th>
                                    <th>Creado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($items)): ?>
                                    <tr><td colspan="6" class="text-center text-muted py-4">Sin registros.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($items as $it): ?>
                                        <tr>
                                            <td><input type="checkbox" class="form-check-input ban-checkbox" name="ids[]" value="<?php echo (int)$it['id']; ?>"></td>
                                            <td><?php echo html((string)($it['email'] ?? '')); ?></td>
                                            <td><?php echo html((string)($it['domain'] ?? '')); ?></td>
                                            <td class="text-muted"><?php echo html((string)($it['notes'] ?? '')); ?></td>
                                            <td>
                                                <?php if ((int)$it['is_active'] === 1): ?>
                                                    <span class="badge bg-success">Activo</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Inactivo</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo html(formatDate($it['created'] ?? null)); ?></td>
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

<div class="modal fade" id="addBanModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post" action="banlist.php">
                <input type="hidden" name="do" value="create">
                <?php csrfField(); ?>
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Agregar a prohibidos</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Email (opcional)</label>
                                <input type="email" class="form-control" name="email" placeholder="usuario@dominio.com">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Dominio (opcional)</label>
                                <input type="text" class="form-control" name="domain" placeholder="dominio.com">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notas</label>
                        <textarea class="form-control" name="notes" rows="3"></textarea>
                    </div>
                    <div class="text-muted small">Puedes bloquear un email específico o un dominio completo.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function(){
    function getChecked(){
        var boxes = document.querySelectorAll('.ban-checkbox:checked');
        var ids = [];
        boxes.forEach(function(b){ ids.push(b.value); });
        return ids;
    }
    var selectAll = document.getElementById('selectAllBan');
    if (selectAll) {
        selectAll.addEventListener('change', function(){
            document.querySelectorAll('.ban-checkbox').forEach(function(b){ b.checked = selectAll.checked; });
        });
    }

    function submitAction(action){
        var ids = getChecked();
        if (ids.length < 1) return;
        if (action === 'delete' && !confirm('¿Deseas eliminar los elementos seleccionados?')) return;
        document.getElementById('banActionInput').value = action;
        document.getElementById('banMassForm').submit();
    }

    var btnEnable = document.getElementById('enableBanBtn');
    if (btnEnable) btnEnable.addEventListener('click', function(){ submitAction('enable'); });
    var btnDisable = document.getElementById('disableBanBtn');
    if (btnDisable) btnDisable.addEventListener('click', function(){ submitAction('disable'); });
    var btnDelete = document.getElementById('deleteBanBtn');
    if (btnDelete) btnDelete.addEventListener('click', function(){ submitAction('delete'); });
})();
</script>

<?php
$content = ob_get_clean();
require_once 'layout_admin.php';
?>
