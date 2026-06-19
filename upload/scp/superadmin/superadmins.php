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
        $password_confirm = (string)($_POST['password_confirm'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if ($username === '' || $email === '' || $firstname === '' || $lastname === '' || $password === '') {
            $_SESSION['flash_error'] = 'Todos los campos son obligatorios.';
            $redirectSelf();
        }
        if ($password !== $password_confirm) {
            $_SESSION['flash_error'] = 'Las contraseñas no coinciden.';
            $redirectSelf();
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash_error'] = 'Correo electrónico inválido.';
            $redirectSelf();
        }

        $stmtDu = $mysqli->prepare('SELECT id FROM super_admins WHERE username = ? OR email = ? LIMIT 1');
        if ($stmtDu) {
            $stmtDu->bind_param('ss', $username, $email);
            $stmtDu->execute();
            if ($stmtDu->get_result()->fetch_assoc()) {
                $_SESSION['flash_error'] = 'Ya existe un usuario con ese username o email.';
                $redirectSelf();
            }
        }

        $hash = Auth::hash($password);

        $stmt = $mysqli->prepare('INSERT INTO super_admins (username, email, firstname, lastname, password, is_active, created, updated) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())');
        if (!$stmt) {
            $_SESSION['flash_error'] = 'No se pudo crear el superadmin.';
            $redirectSelf();
        }
        $stmt->bind_param('sssssi', $username, $email, $firstname, $lastname, $hash, $is_active);

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
        $password_confirm = (string)($_POST['password_confirm'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if ($id <= 0) {
            $_SESSION['flash_error'] = 'ID inválido.';
            $redirectSelf();
        }
        if ($username === '' || $email === '' || $firstname === '' || $lastname === '') {
            $_SESSION['flash_error'] = 'Todos los campos (excepto contraseña) son obligatorios.';
            $redirectSelf();
        }
        if ($password !== '' && $password !== $password_confirm) {
            $_SESSION['flash_error'] = 'Las contraseñas no coinciden.';
            $redirectSelf();
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash_error'] = 'Correo electrónico inválido.';
            $redirectSelf();
        }

        $stmtDu = $mysqli->prepare('SELECT id FROM super_admins WHERE (username = ? OR email = ?) AND id <> ? LIMIT 1');
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
            $stmt = $mysqli->prepare('UPDATE super_admins SET username = ?, email = ?, firstname = ?, lastname = ?, password = ?, is_active = ?, updated = NOW() WHERE id = ?');
            if (!$stmt) {
                $_SESSION['flash_error'] = 'No se pudo actualizar.';
                $redirectSelf();
            }
            $stmt->bind_param('sssssii', $username, $email, $firstname, $lastname, $hash, $is_active, $id);
        } else {
            $stmt = $mysqli->prepare('UPDATE super_admins SET username = ?, email = ?, firstname = ?, lastname = ?, is_active = ?, updated = NOW() WHERE id = ?');
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

        $stmt = $mysqli->prepare("SELECT id FROM super_admins WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            if (!$stmt->get_result()->fetch_assoc()) {
                $_SESSION['flash_error'] = 'Superadmin no encontrado.';
                $redirectSelf();
            }
        }

        $stmtDel = $mysqli->prepare('DELETE FROM super_admins WHERE id = ?');
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
    $sql = "SELECT id, username, email, firstname, lastname, is_active, last_login FROM super_admins ORDER BY (id = " . (int)$meId . ") DESC, id ASC";
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
<style>
    /* Diseño premium tipo tarjeta para la tabla */
    .pro-table {
        border-collapse: separate !important;
        border-spacing: 0 8px !important;
        background: transparent !important;
    }
    .pro-table thead th {
        border-bottom: none !important;
        padding-bottom: 4px !important;
    }
    .pro-table tbody tr {
        background: #ffffff;
        box-shadow: 0 2px 5px rgba(0,0,0,0.02);
        transition: all 0.2s ease-in-out;
    }
    .pro-table tbody td {
        border: none !important;
        padding: 1rem 1.25rem !important;
    }
    /* Bordes redondeados en las esquinas de cada fila */
    .pro-table tbody tr td:first-child {
        border-top-left-radius: 10px;
        border-bottom-left-radius: 10px;
    }
    .pro-table tbody tr td:last-child {
        border-top-right-radius: 10px;
        border-bottom-right-radius: 10px;
    }
    /* Efecto hover premium */
    .pro-table tbody tr:hover {
        background: #f8fafc !important;
        transform: translateY(-2px);
        box-shadow: 0 6px 15px rgba(0,0,0,0.05);
    }
    
    /* Adaptación premium para el Modo Oscuro */
    body.superadmin-dark .pro-table tbody tr {
        background: #000000 !important;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
    }
    body.superadmin-dark .pro-table tbody tr:hover {
        background: #000000 !important;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.4);
    }
    body.superadmin-dark .pro-table tbody td {
        color: #e4e4e7 !important;
    }
    body.superadmin-dark .pro-table tbody a {
        color: #3b82f6 !important;
    }
    body.superadmin-dark .pro-table tbody a:hover {
        color: #60a5fa !important;
    }
</style>
<div class="settings-hero mb-3">
    <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
        <div class="d-flex align-items-center gap-3">
            <span class="settings-hero-icon" style="background: rgba(255, 255, 255, 0.15) !important; border: 1px solid rgba(255, 255, 255, 0.2) !important; color: #fff !important; box-shadow: none !important;">
                <i class="bi bi-shield-lock text-white"></i>
            </span>
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

<script>
    (function () {
        function autoCloseAlerts() {
            var alerts = document.querySelectorAll('.alert.alert-dismissible');
            if (!alerts || alerts.length === 0) return;
            window.setTimeout(function () {
                alerts.forEach(function (el) {
                    try {
                        if (window.bootstrap && bootstrap.Alert) {
                            var inst = bootstrap.Alert.getOrCreateInstance(el);
                            inst.close();
                        } else {
                            el.classList.remove('show');
                            window.setTimeout(function () { if (el && el.parentNode) el.parentNode.removeChild(el); }, 200);
                        }
                    } catch (e) {}
                });
            }, 4500);
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', autoCloseAlerts);
        } else {
            autoCloseAlerts();
        }
    })();
</script>

<div class="card pro-card mt-3">
    <div class="card-header">
        <span class="card-title-sm"><i class="bi bi-people me-1"></i>Listado de Superadmins</span>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25"
                  style="font-size:.67rem"><?php echo count($rows); ?> registros</span>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table pro-table table-hover mb-0">
                <thead>
                <tr>
                    <th>Usuario</th>
                    <th>Nombre completo</th>
                    <th>Email</th>
                    <th>Estado</th>
                    <th>Último acceso</th>
                    <th class="text-end" style="width: 120px;">Acciones</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4"><i class="bi bi-inbox fs-3 d-block mb-2 opacity-50"></i>No hay superadmins registrados.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td class="fw-semibold">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="avatar-sm text-white rounded-circle d-flex align-items-center justify-content-center" style="width:32px; height:32px; font-size:.85rem; background-color: #ef4444 !important; box-shadow: 0 2px 6px rgba(239, 68, 68, 0.3);">
                                        <i class="bi bi-shield-lock-fill text-white"></i>
                                    </div>
                                    <span><?php echo html((string)($r['username'] ?? '')); ?></span>
                                </div>
                            </td>
                            <td><?php echo html(trim((string)($r['firstname'] ?? '') . ' ' . (string)($r['lastname'] ?? ''))); ?></td>
                            <td>
                                <a href="mailto:<?php echo html((string)($r['email'] ?? '')); ?>" class="text-decoration-none">
                                    <i class="bi bi-envelope me-1 opacity-50"></i><?php echo html((string)($r['email'] ?? '')); ?>
                                </a>
                            </td>
                            <td>
                                <?php if ((int)($r['is_active'] ?? 0) === 1): ?>
                                    <span class="badge-pill badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Activo</span>
                                <?php else: ?>
                                    <span class="badge-pill badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">Inactivo</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($r['last_login'])): ?>
                                    <i class="bi bi-clock-history me-1 opacity-50"></i><?php echo html((string)($r['last_login'] ?? '')); ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <div class="d-flex gap-1 justify-content-end btn-action-group">
                                    <button
                                        type="button"
                                        class="btn btn-outline-secondary btn-sm btnEditSuperadmin"
                                        data-bs-toggle="modal"
                                        data-bs-target="#superadminModal"
                                        data-id="<?php echo (int)($r['id'] ?? 0); ?>"
                                        data-username="<?php echo htmlspecialchars((string)($r['username'] ?? ''), ENT_QUOTES); ?>"
                                        data-email="<?php echo htmlspecialchars((string)($r['email'] ?? ''), ENT_QUOTES); ?>"
                                        data-firstname="<?php echo htmlspecialchars((string)($r['firstname'] ?? ''), ENT_QUOTES); ?>"
                                        data-lastname="<?php echo htmlspecialchars((string)($r['lastname'] ?? ''), ENT_QUOTES); ?>"
                                        data-active="<?php echo ((int)($r['is_active'] ?? 0) === 1) ? '1' : '0'; ?>"
                                        title="Editar"
                                    >
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <?php if ((int)($r['id'] ?? 0) !== $meId): ?>
                                        <button
                                            type="button"
                                            class="btn btn-outline-danger btn-sm btnDeleteSuperadmin"
                                            data-bs-toggle="modal"
                                            data-bs-target="#deleteSuperadminModal"
                                            data-id="<?php echo (int)($r['id'] ?? 0); ?>"
                                            data-username="<?php echo htmlspecialchars((string)($r['username'] ?? ''), ENT_QUOTES); ?>"
                                            data-email="<?php echo htmlspecialchars((string)($r['email'] ?? ''), ENT_QUOTES); ?>"
                                            title="Eliminar"
                                        >
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    <?php else: ?>
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 d-flex align-items-center" style="padding:0 0.5rem; font-size:.75rem;" title="No puedes eliminarte a ti mismo">Tú</span>
                                    <?php endif; ?>
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

<div class="modal fade" id="deleteSuperadminModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" id="deleteSuperadminForm">
                <div class="modal-header">
                    <h5 class="modal-title">Eliminar superadmin</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php csrfField(); ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="delSaId" value="">
                    <div class="alert alert-warning mb-0">
                        <div class="fw-semibold mb-1">Esta acción no se puede deshacer.</div>
                        <div>
                            Vas a eliminar:
                            <div class="mt-2">
                                <div><strong>Usuario:</strong> <span id="delSaUsername"></span></div>
                                <div><strong>Email:</strong> <span id="delSaEmail"></span></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Eliminar</button>
                </div>
            </form>
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
                        <div class="col-md-6">
                            <label class="form-label" id="saPasswordConfirmLabel">Confirmar Contraseña</label>
                            <input type="password" class="form-control" name="password_confirm" id="saPasswordConfirm">
                            <div class="invalid-feedback" id="saPasswordError">Las contraseñas no coinciden. Por favor, verifica e inténtalo de nuevo.</div>
                        </div>
                        <div class="col-md-12 d-flex align-items-end">
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
        var passwordConfirmEl = document.getElementById('saPasswordConfirm');
        var passwordLabelEl = document.getElementById('saPasswordLabel');
        var passwordHelpEl = document.getElementById('saPasswordHelp');
        var activeEl = document.getElementById('saActive');
        var submitEl = document.getElementById('saSubmit');
        var btnCreate = document.getElementById('btnOpenCreate');
        var formEl = document.getElementById('superadminForm');

        var delIdEl = document.getElementById('delSaId');
        var delUsernameEl = document.getElementById('delSaUsername');
        var delEmailEl = document.getElementById('delSaEmail');

        function setCreate() {
            titleEl.textContent = 'Nuevo superadmin';
            actionEl.value = 'create';
            idEl.value = '';
            usernameEl.value = '';
            emailEl.value = '';
            firstnameEl.value = '';
            lastnameEl.value = '';
            passwordEl.value = '';
            passwordConfirmEl.value = '';
            passwordEl.required = true;
            passwordConfirmEl.required = true;
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
            passwordConfirmEl.value = '';
            passwordEl.required = false;
            passwordConfirmEl.required = false;
            passwordLabelEl.textContent = 'Nueva contraseña (opcional)';
            passwordHelpEl.style.display = 'block';
            activeEl.checked = (btn.getAttribute('data-active') === '1');
            submitEl.textContent = 'Guardar cambios';
        }

        if (formEl) {
            formEl.addEventListener('submit', function (e) {
                if (passwordEl.value !== passwordConfirmEl.value) {
                    e.preventDefault();
                    passwordConfirmEl.classList.add('is-invalid');
                } else {
                    passwordConfirmEl.classList.remove('is-invalid');
                }
            });

            passwordConfirmEl.addEventListener('input', function() {
                passwordConfirmEl.classList.remove('is-invalid');
            });
            passwordEl.addEventListener('input', function() {
                passwordConfirmEl.classList.remove('is-invalid');
            });
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

        document.querySelectorAll('.btnDeleteSuperadmin').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (delIdEl) delIdEl.value = btn.getAttribute('data-id') || '';
                if (delUsernameEl) delUsernameEl.textContent = btn.getAttribute('data-username') || '';
                if (delEmailEl) delEmailEl.textContent = btn.getAttribute('data-email') || '';
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
