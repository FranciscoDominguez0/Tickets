<?php
require_once '../../../config.php';
require_once '../../../includes/helpers.php';
require_once '../../../includes/Auth.php';

requireLogin('agente');

if ((string)($_SESSION['staff_role'] ?? '') !== 'superadmin') {
    header('Location: ../index.php');
    exit;
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$currentRoute = 'superadmins';

$flashMsg = '';
$flashError = '';
if (!empty($_SESSION['flash_msg'])) {
    $flashMsg = (string)$_SESSION['flash_msg'];
    unset($_SESSION['flash_msg']);
}
if (!empty($_SESSION['flash_error'])) {
    $flashError = (string)$_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

$staffHasEmpresa = false;
$staffHasRole = false;
if (isset($mysqli) && $mysqli) {
    $col = $mysqli->query("SHOW COLUMNS FROM staff LIKE 'empresa_id'");
    $staffHasEmpresa = ($col && $col->num_rows > 0);
    $col = $mysqli->query("SHOW COLUMNS FROM staff LIKE 'role'");
    $staffHasRole = ($col && $col->num_rows > 0);
}

$meId = (int)($_SESSION['staff_id'] ?? 0);

$redirectSelf = function (string $qs = '') {
    $to = 'superadmins.php';
    if ($qs !== '') $to .= '?' . ltrim($qs, '?');
    header('Location: ' . $to);
    exit;
};

$normalizeUsername = function ($v) {
    $v = trim((string)$v);
    $v = preg_replace('/\s+/', '', $v);
    return $v;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCSRF($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Token de seguridad inválido.';
        $redirectSelf();
    }

    $action = (string)($_POST['action'] ?? '');

    if (!isset($mysqli) || !$mysqli) {
        $_SESSION['flash_error'] = 'DB no disponible.';
        $redirectSelf();
    }

    if ($action === 'create') {
        $username = $normalizeUsername($_POST['username'] ?? '');
        $email = trim((string)($_POST['email'] ?? ''));
        $firstname = trim((string)($_POST['firstname'] ?? ''));
        $lastname = trim((string)($_POST['lastname'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if ($username === '' || $email === '' || $firstname === '' || $lastname === '' || $password === '') {
            $_SESSION['flash_error'] = 'Todos los campos son obligatorios.';
            $redirectSelf();
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash_error'] = 'Correo electrónico inválido.';
            $redirectSelf();
        }

        $stmtDu = $mysqli->prepare('SELECT id FROM staff WHERE username = ? OR email = ? LIMIT 1');
        if ($stmtDu) {
            $stmtDu->bind_param('ss', $username, $email);
            $stmtDu->execute();
            if ($stmtDu->get_result()->fetch_assoc()) {
                $_SESSION['flash_error'] = 'Ya existe un usuario con ese username o email.';
                $redirectSelf();
            }
        }

        $hash = Auth::hash($password);

        if ($staffHasRole && $staffHasEmpresa) {
            $empresa_id = 1;
            $role = 'superadmin';
            $stmt = $mysqli->prepare('INSERT INTO staff (empresa_id, role, username, email, firstname, lastname, password, is_active, created, updated) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
            if (!$stmt) {
                $_SESSION['flash_error'] = 'No se pudo crear el superadmin.';
                $redirectSelf();
            }
            $stmt->bind_param('issssssi', $empresa_id, $role, $username, $email, $firstname, $lastname, $hash, $is_active);
        } elseif ($staffHasRole) {
            $role = 'superadmin';
            $stmt = $mysqli->prepare('INSERT INTO staff (role, username, email, firstname, lastname, password, is_active, created, updated) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
            if (!$stmt) {
                $_SESSION['flash_error'] = 'No se pudo crear el superadmin.';
                $redirectSelf();
            }
            $stmt->bind_param('ssssssi', $role, $username, $email, $firstname, $lastname, $hash, $is_active);
        } else {
            $stmt = $mysqli->prepare('INSERT INTO staff (username, email, firstname, lastname, password, is_active, created, updated) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())');
            if (!$stmt) {
                $_SESSION['flash_error'] = 'No se pudo crear el superadmin.';
                $redirectSelf();
            }
            $stmt->bind_param('sssssi', $username, $email, $firstname, $lastname, $hash, $is_active);
        }

        if ($stmt->execute()) {
            $_SESSION['flash_msg'] = 'Superadmin creado correctamente.';
            $redirectSelf();
        }

        $_SESSION['flash_error'] = 'No se pudo crear el superadmin.';
        $redirectSelf();
    }

    if ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $username = $normalizeUsername($_POST['username'] ?? '');
        $email = trim((string)($_POST['email'] ?? ''));
        $firstname = trim((string)($_POST['firstname'] ?? ''));
        $lastname = trim((string)($_POST['lastname'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if ($id <= 0) {
            $_SESSION['flash_error'] = 'ID inválido.';
            $redirectSelf();
        }
        if ($username === '' || $email === '' || $firstname === '' || $lastname === '') {
            $_SESSION['flash_error'] = 'Todos los campos (excepto contraseña) son obligatorios.';
            $redirectSelf();
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash_error'] = 'Correo electrónico inválido.';
            $redirectSelf();
        }

        $stmtDu = $mysqli->prepare('SELECT id FROM staff WHERE (username = ? OR email = ?) AND id <> ? LIMIT 1');
        if ($stmtDu) {
            $stmtDu->bind_param('ssi', $username, $email, $id);
            $stmtDu->execute();
            if ($stmtDu->get_result()->fetch_assoc()) {
                $_SESSION['flash_error'] = 'Ya existe otro usuario con ese username o email.';
                $redirectSelf();
            }
        }

        if ($password !== '') {
            $hash = Auth::hash($password);
            $stmt = $mysqli->prepare('UPDATE staff SET username = ?, email = ?, firstname = ?, lastname = ?, password = ?, is_active = ?, updated = NOW() WHERE id = ?');
            if (!$stmt) {
                $_SESSION['flash_error'] = 'No se pudo actualizar.';
                $redirectSelf();
            }
            $stmt->bind_param('sssssii', $username, $email, $firstname, $lastname, $hash, $is_active, $id);
        } else {
            $stmt = $mysqli->prepare('UPDATE staff SET username = ?, email = ?, firstname = ?, lastname = ?, is_active = ?, updated = NOW() WHERE id = ?');
            if (!$stmt) {
                $_SESSION['flash_error'] = 'No se pudo actualizar.';
                $redirectSelf();
            }
            $stmt->bind_param('ssssii', $username, $email, $firstname, $lastname, $is_active, $id);
        }

        if ($stmt->execute()) {
            $_SESSION['flash_msg'] = 'Superadmin actualizado correctamente.';
            $redirectSelf();
        }

        $_SESSION['flash_error'] = 'No se pudo actualizar.';
        $redirectSelf();
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {
            $_SESSION['flash_error'] = 'ID inválido.';
            $redirectSelf();
        }
        if ($id === $meId) {
            $_SESSION['flash_error'] = 'No puedes eliminar tu propia cuenta.';
            $redirectSelf();
        }

        $stmt = $mysqli->prepare("SELECT id FROM staff WHERE id = ?" . ($staffHasRole ? " AND role = 'superadmin'" : "") . " LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            if (!$stmt->get_result()->fetch_assoc()) {
                $_SESSION['flash_error'] = 'Superadmin no encontrado.';
                $redirectSelf();
            }
        }

        $stmtDel = $mysqli->prepare('DELETE FROM staff WHERE id = ?');
        if (!$stmtDel) {
            $_SESSION['flash_error'] = 'No se pudo eliminar.';
            $redirectSelf();
        }
        $stmtDel->bind_param('i', $id);
        if ($stmtDel->execute()) {
            $_SESSION['flash_msg'] = 'Superadmin eliminado.';
            $redirectSelf();
        }

        $_SESSION['flash_error'] = 'No se pudo eliminar.';
        $redirectSelf();
    }

    $_SESSION['flash_error'] = 'Acción inválida.';
    $redirectSelf();
}

$rows = [];
if (isset($mysqli) && $mysqli) {
    $sql = "SELECT id, username, email, firstname, lastname, is_active, last_login";
    if ($staffHasRole) $sql .= ", role";
    $sql .= " FROM staff";
    if ($staffHasRole) {
        $sql .= " WHERE role = 'superadmin'";
    }
    $sql .= " ORDER BY id ASC";
    $res = $mysqli->query($sql);
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
    }
}

ob_start();
?>
<link rel="stylesheet" href="css/empresas.css">

<div class="emp-hero mb-1">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="hero-icon" style="background:#0d6efd;">
                <i class="bi bi-shield-lock"></i>
            </div>
            <div>
                <h1>Superadmins</h1>
                <p>Gestiona las cuentas con acceso al panel SuperAdmin</p>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <button type="button" class="btn btn-primary" id="btnOpenCreate" data-bs-toggle="modal" data-bs-target="#superadminModal">
                <i class="bi bi-plus-lg me-1"></i>Nuevo superadmin
            </button>
        </div>
    </div>
</div>

<?php if ($flashMsg): ?>
    <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2 mt-3" role="alert">
        <i class="bi bi-check-circle-fill flex-shrink-0"></i>
        <div><?php echo html($flashMsg); ?></div>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if ($flashError): ?>
    <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2 mt-3" role="alert">
        <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
        <div><?php echo html($flashError); ?></div>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card pro-card mt-3">
    <div class="card-header">
        <span class="card-title-sm"><i class="bi bi-people me-1"></i>Listado</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped mb-0">
                <thead>
                <tr>
                    <th>Usuario</th>
                    <th>Email</th>
                    <th>Nombre</th>
                    <th>Activo</th>
                    <th>Último login</th>
                    <th style="width: 170px;">Acciones</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No hay superadmins.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><?php echo html((string)($r['username'] ?? '')); ?></td>
                            <td><?php echo html((string)($r['email'] ?? '')); ?></td>
                            <td><?php echo html(trim((string)($r['firstname'] ?? '') . ' ' . (string)($r['lastname'] ?? ''))); ?></td>
                            <td>
                                <?php if ((int)($r['is_active'] ?? 0) === 1): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Sí</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">No</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo html((string)($r['last_login'] ?? '')); ?></td>
                            <td>
                                <div class="d-flex gap-2">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-primary btnEditSuperadmin"
                                        data-bs-toggle="modal"
                                        data-bs-target="#superadminModal"
                                        data-id="<?php echo (int)($r['id'] ?? 0); ?>"
                                        data-username="<?php echo htmlspecialchars((string)($r['username'] ?? ''), ENT_QUOTES); ?>"
                                        data-email="<?php echo htmlspecialchars((string)($r['email'] ?? ''), ENT_QUOTES); ?>"
                                        data-firstname="<?php echo htmlspecialchars((string)($r['firstname'] ?? ''), ENT_QUOTES); ?>"
                                        data-lastname="<?php echo htmlspecialchars((string)($r['lastname'] ?? ''), ENT_QUOTES); ?>"
                                        data-active="<?php echo ((int)($r['is_active'] ?? 0) === 1) ? '1' : '0'; ?>"
                                    >
                                        Editar
                                    </button>
                                    <form method="post" onsubmit="return confirm('¿Eliminar este superadmin?');">
                                        <?php csrfField(); ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo (int)($r['id'] ?? 0); ?>">
                                        <button class="btn btn-sm btn-outline-danger" type="submit" <?php echo ((int)($r['id'] ?? 0) === $meId) ? 'disabled' : ''; ?>>Eliminar</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="superadminModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="post" autocomplete="off" id="superadminForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="superadminModalTitle">Nuevo superadmin</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php csrfField(); ?>
                    <input type="hidden" name="action" id="saAction" value="create">
                    <input type="hidden" name="id" id="saId" value="">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Usuario</label>
                            <input type="text" class="form-control" name="username" id="saUsername" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" id="saEmail" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nombre</label>
                            <input type="text" class="form-control" name="firstname" id="saFirstname" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Apellido</label>
                            <input type="text" class="form-control" name="lastname" id="saLastname" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" id="saPasswordLabel">Contraseña</label>
                            <input type="password" class="form-control" name="password" id="saPassword">
                            <div class="form-text" id="saPasswordHelp" style="display:none;">Deja en blanco para mantener la contraseña actual.</div>
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" id="saActive" checked>
                                <label class="form-check-label" for="saActive">Activo</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="saSubmit">Crear</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    (function () {
        var modalEl = document.getElementById('superadminModal');
        var titleEl = document.getElementById('superadminModalTitle');
        var actionEl = document.getElementById('saAction');
        var idEl = document.getElementById('saId');
        var usernameEl = document.getElementById('saUsername');
        var emailEl = document.getElementById('saEmail');
        var firstnameEl = document.getElementById('saFirstname');
        var lastnameEl = document.getElementById('saLastname');
        var passwordEl = document.getElementById('saPassword');
        var passwordLabelEl = document.getElementById('saPasswordLabel');
        var passwordHelpEl = document.getElementById('saPasswordHelp');
        var activeEl = document.getElementById('saActive');
        var submitEl = document.getElementById('saSubmit');
        var btnCreate = document.getElementById('btnOpenCreate');

        function setCreate() {
            titleEl.textContent = 'Nuevo superadmin';
            actionEl.value = 'create';
            idEl.value = '';
            usernameEl.value = '';
            emailEl.value = '';
            firstnameEl.value = '';
            lastnameEl.value = '';
            passwordEl.value = '';
            passwordEl.required = true;
            passwordLabelEl.textContent = 'Contraseña';
            passwordHelpEl.style.display = 'none';
            activeEl.checked = true;
            submitEl.textContent = 'Crear';
        }

        function setEdit(btn) {
            titleEl.textContent = 'Editar superadmin';
            actionEl.value = 'update';
            idEl.value = btn.getAttribute('data-id') || '';
            usernameEl.value = btn.getAttribute('data-username') || '';
            emailEl.value = btn.getAttribute('data-email') || '';
            firstnameEl.value = btn.getAttribute('data-firstname') || '';
            lastnameEl.value = btn.getAttribute('data-lastname') || '';
            passwordEl.value = '';
            passwordEl.required = false;
            passwordLabelEl.textContent = 'Nueva contraseña (opcional)';
            passwordHelpEl.style.display = 'block';
            activeEl.checked = (btn.getAttribute('data-active') === '1');
            submitEl.textContent = 'Guardar cambios';
        }

        if (btnCreate) {
            btnCreate.addEventListener('click', function () {
                setCreate();
            });
        }

        document.querySelectorAll('.btnEditSuperadmin').forEach(function (btn) {
            btn.addEventListener('click', function () {
                setEdit(btn);
            });
        });

        if (modalEl) {
            modalEl.addEventListener('hidden.bs.modal', function () {
                setCreate();
            });
        }

        setCreate();
    })();
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
