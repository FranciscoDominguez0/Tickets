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
$currentRoute = 'staff';

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

$ensureRolesTable = function () use ($mysqli) {
    if (!isset($mysqli) || !$mysqli) return false;
    $sql = "CREATE TABLE IF NOT EXISTS roles (\n"
        . "  id INT PRIMARY KEY AUTO_INCREMENT,\n"
        . "  name VARCHAR(100) NOT NULL,\n"
        . "  is_enabled TINYINT(1) NOT NULL DEFAULT 1,\n"
        . "  created DATETIME DEFAULT CURRENT_TIMESTAMP,\n"
        . "  updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
        . "  UNIQUE KEY uq_roles_name (name)\n"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    return (bool)$mysqli->query($sql);
};

$ensureRolesTable();

if (isset($mysqli) && $mysqli) {
    $mysqli->query("INSERT IGNORE INTO roles (name, is_enabled, created, updated) VALUES ('admin', 1, NOW(), NOW()), ('supervisor', 1, NOW(), NOW()), ('agent', 1, NOW(), NOW())");
    $resSeed = $mysqli->query("SELECT DISTINCT role FROM staff WHERE role IS NOT NULL AND TRIM(role) <> ''");
    if ($resSeed) {
        $stmtIns = $mysqli->prepare('INSERT IGNORE INTO roles (name, is_enabled, created, updated) VALUES (?, 1, NOW(), NOW())');
        if ($stmtIns) {
            while ($r = $resSeed->fetch_assoc()) {
                $name = trim((string)($r['role'] ?? ''));
                if ($name === '') continue;
                $stmtIns->bind_param('s', $name);
                $stmtIns->execute();
            }
            $stmtIns->close();
        }
    }
}

$enabledRoles = [];
if (isset($mysqli) && $mysqli) {
    $resRoles = $mysqli->query('SELECT name FROM roles WHERE is_enabled = 1 ORDER BY name');
    if ($resRoles) {
        while ($r = $resRoles->fetch_assoc()) {
            $name = trim((string)($r['name'] ?? ''));
            if ($name !== '') $enabledRoles[] = $name;
        }
    }
}

$isValidEnabledRole = function (string $role) use ($mysqli) {
    $role = trim($role);
    if ($role === '') return false;
    if (!isset($mysqli) || !$mysqli) return false;
    $stmt = $mysqli->prepare('SELECT is_enabled FROM roles WHERE name = ? LIMIT 1');
    if (!$stmt) return false;
    $stmt->bind_param('s', $role);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row && (int)($row['is_enabled'] ?? 0) === 1;
};

function ensureStaffPasswordResetsTableExists($mysqli) {
    if (!$mysqli) return false;
    $sql = "CREATE TABLE IF NOT EXISTS staff_password_resets (\n"
        . "  id INT PRIMARY KEY AUTO_INCREMENT,\n"
        . "  staff_id INT NOT NULL,\n"
        . "  token_hash CHAR(64) NOT NULL,\n"
        . "  expires_at DATETIME NOT NULL,\n"
        . "  used_at DATETIME NULL,\n"
        . "  created DATETIME DEFAULT CURRENT_TIMESTAMP,\n"
        . "  KEY idx_staff_id (staff_id),\n"
        . "  KEY idx_token_hash (token_hash),\n"
        . "  KEY idx_expires (expires_at),\n"
        . "  FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE\n"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    return (bool)$mysqli->query($sql);
}

function randomPassword($length = 12) {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789@#$%';
    $max = strlen($alphabet) - 1;
    $out = '';
    for ($i = 0; $i < $length; $i++) {
        $out .= $alphabet[random_int(0, $max)];
    }
    return $out;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCSRF($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Token de seguridad inválido.';
        header('Location: staff.php');
        exit;
    }

    if ($currentStaffRole !== 'admin') {
        $_SESSION['flash_error'] = 'No tienes permisos para administrar agentes.';
        header('Location: staff.php');
        exit;
    }

    $action = (string)($_POST['do'] ?? '');

    if ($action === 'create') {
        $firstname = trim((string)($_POST['firstname'] ?? ''));
        $lastname = trim((string)($_POST['lastname'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $username = trim((string)($_POST['username'] ?? ''));
        $role = trim((string)($_POST['role'] ?? 'agent'));
        $deptId = isset($_POST['dept_id']) && is_numeric($_POST['dept_id']) ? (int)$_POST['dept_id'] : 0;
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $sendReset = isset($_POST['send_reset']) ? 1 : 0;

        if ($firstname === '' || $lastname === '') {
            $_SESSION['flash_error'] = 'Nombre y apellido son requeridos.';
            header('Location: staff.php');
            exit;
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash_error'] = 'Correo electrónico inválido.';
            header('Location: staff.php');
            exit;
        }
        if ($username === '') {
            $_SESSION['flash_error'] = 'El usuario es requerido.';
            header('Location: staff.php');
            exit;
        }
        if (!$isValidEnabledRole($role)) {
            $_SESSION['flash_error'] = 'Rol inválido o deshabilitado.';
            header('Location: staff.php');
            exit;
        }

        $stmtDu = $mysqli->prepare('SELECT id FROM staff WHERE username = ? OR email = ? LIMIT 1');
        if ($stmtDu) {
            $stmtDu->bind_param('ss', $username, $email);
            $stmtDu->execute();
            $dup = $stmtDu->get_result()->fetch_assoc();
            if ($dup) {
                $_SESSION['flash_error'] = 'Ya existe un agente con ese usuario o correo.';
                header('Location: staff.php');
                exit;
            }
        }

        $tempPass = randomPassword(14);
        $hash = Auth::hash($tempPass);

        $stmtIns = $mysqli->prepare('INSERT INTO staff (username, email, firstname, lastname, password, dept_id, role, is_active, created) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())');
        if (!$stmtIns) {
            $_SESSION['flash_error'] = 'No se pudo crear el agente.';
            header('Location: staff.php');
            exit;
        }
        $stmtIns->bind_param('sssssisi', $username, $email, $firstname, $lastname, $hash, $deptId, $role, $isActive);
        if (!$stmtIns->execute()) {
            $_SESSION['flash_error'] = 'No se pudo crear el agente.';
            header('Location: staff.php');
            exit;
        }

        $newId = (int)$mysqli->insert_id;

        if ($sendReset && $newId > 0) {
            if (ensureStaffPasswordResetsTableExists($mysqli)) {
                $token = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $token);
                $expiresAt = date('Y-m-d H:i:s', time() + 3600);

                $stmtR = $mysqli->prepare('INSERT INTO staff_password_resets (staff_id, token_hash, expires_at) VALUES (?, ?, ?)');
                if ($stmtR) {
                    $stmtR->bind_param('iss', $newId, $tokenHash, $expiresAt);
                    $stmtR->execute();

                    $resetUrl = rtrim(APP_URL, '/') . '/upload/reset_staff.php?token=' . urlencode($token);
                    $name = trim($firstname . ' ' . $lastname);
                    if ($name === '') $name = $email;
                    $subject = 'Restablecer contraseña (Agente) - ' . APP_NAME;

                    $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
                    $safeUrl = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8');
                    $bodyHtml = ''
                        . '<div style="font-family:Segoe UI, Tahoma, Arial, sans-serif; background:#f1f5f9; padding:24px;">'
                        . '  <div style="max-width:640px; margin:0 auto;">'
                        . '    <div style="background:linear-gradient(135deg,#0f172a,#1d4ed8); color:#ffffff; border-radius:16px; padding:18px 20px;">'
                        . '      <div style="font-size:14px; font-weight:800; letter-spacing:.02em; opacity:.95;">' . htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') . '</div>'
                        . '      <div style="font-size:22px; font-weight:900; margin-top:4px;">Configurar contraseña</div>'
                        . '    </div>'
                        . '    <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:16px; padding:18px 20px; margin-top:12px;">'
                        . '      <p style="margin:0 0 10px 0; color:#0f172a; font-size:14px;">Hola <strong>' . $safeName . '</strong>,</p>'
                        . '      <p style="margin:0 0 10px 0; color:#334155; font-size:14px; line-height:1.5;">Un administrador te envió un enlace para configurar tu contraseña de acceso como agente.</p>'
                        . '      <p style="margin:14px 0 12px 0;">'
                        . '        <a href="' . $safeUrl . '" style="display:inline-block; background:#2563eb; color:#ffffff; text-decoration:none; padding:11px 16px; border-radius:12px; font-weight:800;">Configurar contraseña</a>'
                        . '      </p>'
                        . '      <div style="margin:0 0 10px 0; color:#64748b; font-size:12px; line-height:1.5;">Este enlace vence en <strong>1 hora</strong> por seguridad.</div>'
                        . '      <hr style="border:0; border-top:1px solid #e2e8f0; margin:14px 0;">'
                        . '      <div style="color:#94a3b8; font-size:11px; line-height:1.5;">Si el botón no funciona, copia y pega este enlace:<br><span style="word-break:break-all;">' . $safeUrl . '</span></div>'
                        . '    </div>'
                        . '    <div style="text-align:center; color:#94a3b8; font-size:11px; margin-top:10px;">&copy; ' . date('Y') . ' ' . htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') . '</div>'
                        . '  </div>'
                        . '</div>';

                    $bodyText = "Hola $name\n\nUn administrador te envió un enlace para configurar tu contraseña de agente.\n\nEnlace (vence en 1 hora):\n$resetUrl\n";

                    Mailer::send($email, $subject, $bodyHtml, $bodyText);
                }
            }
        }

        $_SESSION['flash_msg'] = 'Agente creado correctamente.';
        header('Location: staff.php');
        exit;
    }

    if ($action === 'update') {
        $id = isset($_POST['id']) && is_numeric($_POST['id']) ? (int)$_POST['id'] : 0;
        $firstname = trim((string)($_POST['firstname'] ?? ''));
        $lastname = trim((string)($_POST['lastname'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $username = trim((string)($_POST['username'] ?? ''));
        $role = trim((string)($_POST['role'] ?? 'agent'));
        $deptId = isset($_POST['dept_id']) && is_numeric($_POST['dept_id']) ? (int)$_POST['dept_id'] : 0;
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($id <= 0) {
            $_SESSION['flash_error'] = 'Agente inválido.';
            header('Location: staff.php');
            exit;
        }
        if ($firstname === '' || $lastname === '') {
            $_SESSION['flash_error'] = 'Nombre y apellido son requeridos.';
            header('Location: staff.php');
            exit;
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash_error'] = 'Correo electrónico inválido.';
            header('Location: staff.php');
            exit;
        }
        if ($username === '') {
            $_SESSION['flash_error'] = 'El usuario es requerido.';
            header('Location: staff.php');
            exit;
        }
        if (!$isValidEnabledRole($role)) {
            $_SESSION['flash_error'] = 'Rol inválido o deshabilitado.';
            header('Location: staff.php');
            exit;
        }

        $stmtDu = $mysqli->prepare('SELECT id FROM staff WHERE (username = ? OR email = ?) AND id <> ? LIMIT 1');
        if ($stmtDu) {
            $stmtDu->bind_param('ssi', $username, $email, $id);
            $stmtDu->execute();
            $dup = $stmtDu->get_result()->fetch_assoc();
            if ($dup) {
                $_SESSION['flash_error'] = 'Ya existe otro agente con ese usuario o correo.';
                header('Location: staff.php');
                exit;
            }
        }

        $stmtUp = $mysqli->prepare('UPDATE staff SET username = ?, email = ?, firstname = ?, lastname = ?, dept_id = ?, role = ?, is_active = ?, updated = NOW() WHERE id = ?');
        if (!$stmtUp) {
            $_SESSION['flash_error'] = 'No se pudo actualizar el agente.';
            header('Location: staff.php');
            exit;
        }
        $stmtUp->bind_param('ssssissi', $username, $email, $firstname, $lastname, $deptId, $role, $isActive, $id);
        $stmtUp->execute();

        $_SESSION['flash_msg'] = 'Agente actualizado correctamente.';
        header('Location: staff.php');
        exit;
    }

    if ($action === 'send_reset') {
        $id = isset($_POST['id']) && is_numeric($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id <= 0) {
            $_SESSION['flash_error'] = 'Agente inválido.';
            header('Location: staff.php');
            exit;
        }

        $stmtS = $mysqli->prepare('SELECT id, email, username, firstname, lastname FROM staff WHERE id = ? LIMIT 1');
        if (!$stmtS) {
            $_SESSION['flash_error'] = 'No se pudo procesar la solicitud.';
            header('Location: staff.php');
            exit;
        }
        $stmtS->bind_param('i', $id);
        $stmtS->execute();
        $s = $stmtS->get_result()->fetch_assoc();
        if (!$s || empty($s['email'])) {
            $_SESSION['flash_error'] = 'No se encontró el correo del agente.';
            header('Location: staff.php');
            exit;
        }

        if (!ensureStaffPasswordResetsTableExists($mysqli)) {
            $_SESSION['flash_error'] = 'No se pudo preparar el reseteo.';
            header('Location: staff.php');
            exit;
        }

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + 3600);

        $stmtR = $mysqli->prepare('INSERT INTO staff_password_resets (staff_id, token_hash, expires_at) VALUES (?, ?, ?)');
        if ($stmtR) {
            $sid = (int)$s['id'];
            $stmtR->bind_param('iss', $sid, $tokenHash, $expiresAt);
            $stmtR->execute();
        }

        $resetUrl = rtrim(APP_URL, '/') . '/upload/reset_staff.php?token=' . urlencode($token);
        $name = trim(((string)($s['firstname'] ?? '')) . ' ' . ((string)($s['lastname'] ?? '')));
        if ($name === '') $name = (string)($s['email'] ?? '');
        $subject = 'Restablecer contraseña (Agente) - ' . APP_NAME;

        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $safeUrl = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8');
        $bodyHtml = ''
            . '<div style="font-family:Segoe UI, Tahoma, Arial, sans-serif; background:#f1f5f9; padding:24px;">'
            . '  <div style="max-width:640px; margin:0 auto;">'
            . '    <div style="background:linear-gradient(135deg,#0f172a,#1d4ed8); color:#ffffff; border-radius:16px; padding:18px 20px;">'
            . '      <div style="font-size:14px; font-weight:800; letter-spacing:.02em; opacity:.95;">' . htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') . '</div>'
            . '      <div style="font-size:22px; font-weight:900; margin-top:4px;">Restablecer contraseña</div>'
            . '    </div>'
            . '    <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:16px; padding:18px 20px; margin-top:12px;">'
            . '      <p style="margin:0 0 10px 0; color:#0f172a; font-size:14px;">Hola <strong>' . $safeName . '</strong>,</p>'
            . '      <p style="margin:0 0 10px 0; color:#334155; font-size:14px; line-height:1.5;">Recibimos una solicitud para restablecer tu contraseña de agente. Para continuar, haz clic en el siguiente botón:</p>'
            . '      <p style="margin:14px 0 12px 0;">'
            . '        <a href="' . $safeUrl . '" style="display:inline-block; background:#2563eb; color:#ffffff; text-decoration:none; padding:11px 16px; border-radius:12px; font-weight:800;">Restablecer contraseña</a>'
            . '      </p>'
            . '      <div style="margin:0 0 10px 0; color:#64748b; font-size:12px; line-height:1.5;">Este enlace vence en <strong>1 hora</strong> por seguridad.</div>'
            . '      <hr style="border:0; border-top:1px solid #e2e8f0; margin:14px 0;">'
            . '      <div style="color:#94a3b8; font-size:11px; line-height:1.5;">Si el botón no funciona, copia y pega este enlace:<br><span style="word-break:break-all;">' . $safeUrl . '</span></div>'
            . '    </div>'
            . '    <div style="text-align:center; color:#94a3b8; font-size:11px; margin-top:10px;">&copy; ' . date('Y') . ' ' . htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') . '</div>'
            . '  </div>'
            . '</div>';

        $bodyText = "Hola $name\n\nEnlace para restablecer contraseña (vence en 1 hora):\n$resetUrl\n";
        Mailer::send((string)$s['email'], $subject, $bodyHtml, $bodyText);

        $_SESSION['flash_msg'] = 'Correo de reseteo enviado.';
        header('Location: staff.php');
        exit;
    }
}

$search = trim((string)($_GET['q'] ?? ''));
$deptFilter = isset($_GET['did']) && is_numeric($_GET['did']) ? (int)$_GET['did'] : 0;

$sql = "
    SELECT
        s.id,
        s.username,
        s.email,
        s.firstname,
        s.lastname,
        s.role,
        s.is_active,
        s.created,
        s.last_login,
        d.id AS dept_id,
        d.name AS dept_name
    FROM staff s
    LEFT JOIN departments d ON s.dept_id = d.id
    WHERE 1=1
";
$params = [];
$types = '';

if ($search !== '') {
    $sql .= " AND (s.firstname LIKE ? OR s.lastname LIKE ? OR s.email LIKE ? OR s.username LIKE ?)";
    $term = '%' . $search . '%';
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $types .= 'ssss';
}
if ($deptFilter > 0) {
    $sql .= " AND s.dept_id = ?";
    $params[] = $deptFilter;
    $types .= 'i';
}
if (isset($_GET['status']) && ($_GET['status'] === 'active' || $_GET['status'] === 'inactive')) {
    $sql .= " AND s.is_active = ?";
    $params[] = ($_GET['status'] === 'active') ? 1 : 0;
    $types .= 'i';
}

$sql .= " ORDER BY s.firstname, s.lastname";

$agents = [];
$stmt = $mysqli->prepare($sql);
if ($stmt) {
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($res && ($row = $res->fetch_assoc())) {
        $agents[] = $row;
    }
}

$departments = [];
$deptStmt = $mysqli->prepare("SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name");
if ($deptStmt) {
    $deptStmt->execute();
    $deptRes = $deptStmt->get_result();
    while ($deptRes && ($d = $deptRes->fetch_assoc())) {
        $departments[] = $d;
    }
}

$activeCount = 0;
$inactiveCount = 0;
foreach ($agents as $a) {
    if ((int)($a['is_active'] ?? 0) === 1) $activeCount++;
    else $inactiveCount++;
}

ob_start();
?>

<div class="settings-hero">
    <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
        <div class="d-flex align-items-center gap-3">
            <span class="settings-hero-icon"><i class="bi bi-people"></i></span>
            <div>
                <h1>Agentes</h1>
                <p>Gestión de agentes y permisos</p>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="badge bg-success"><?php echo (int)$activeCount; ?> Activos</span>
            <span class="badge bg-secondary"><?php echo (int)$inactiveCount; ?> Inactivos</span>
            <span class="badge bg-info"><?php echo (int)count($agents); ?> Total</span>
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

<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-12 col-md-5">
                <label class="form-label">Buscar</label>
                <input type="text" name="q" class="form-control" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Nombre, email o usuario">
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label">Departamento</label>
                <select name="did" class="form-select">
                    <option value="0">Todos</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?php echo (int)$d['id']; ?>" <?php echo $deptFilter === (int)$d['id'] ? 'selected' : ''; ?>><?php echo html($d['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label">Estado</label>
                <?php $status = (string)($_GET['status'] ?? ''); ?>
                <select name="status" class="form-select">
                    <option value="" <?php echo $status === '' ? 'selected' : ''; ?>>Todos</option>
                    <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Activos</option>
                    <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactivos</option>
                </select>
            </div>
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Buscar</button>
                <a href="staff.php" class="btn btn-outline-secondary">Limpiar</a>
            </div>
        </form>
    </div>
</div>

<div class="card settings-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong><i class="bi bi-person-badge"></i> Lista de Agentes</strong>
        <div class="d-flex gap-2">
            <?php if ($currentStaffRole === 'admin'): ?>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#agentCreateModal">
                    <i class="bi bi-plus-circle"></i> Nuevo Agente
                </button>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Departamento</th>
                        <th>Rol</th>
                        <th class="text-center">Estado</th>
                        <th>Último acceso</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($agents)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No hay resultados.</td></tr>
                <?php else: ?>
                    <?php foreach ($agents as $a): ?>
                        <?php
                        $name = trim((string)($a['firstname'] ?? '') . ' ' . (string)($a['lastname'] ?? ''));
                        if ($name === '') $name = (string)($a['username'] ?? '');
                        $dept = (string)($a['dept_name'] ?? '');
                        $role = (string)($a['role'] ?? '');
                        $active = (int)($a['is_active'] ?? 0) === 1;
                        $last = $a['last_login'] ?? null;
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo html($name); ?></strong><br>
                                <small class="text-muted">@<?php echo html((string)($a['username'] ?? '')); ?></small>
                            </td>
                            <td>
                                <a class="text-decoration-none" href="mailto:<?php echo html((string)($a['email'] ?? '')); ?>"><?php echo html((string)($a['email'] ?? '')); ?></a>
                            </td>
                            <td>
                                <?php if ($dept !== ''): ?>
                                    <span class="badge bg-secondary"><?php echo html($dept); ?></span>
                                <?php else: ?>
                                    <span class="text-muted">Sin asignar</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($role !== ''): ?>
                                    <span class="badge bg-info text-dark"><?php echo html($role); ?></span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($active): ?>
                                    <span class="badge bg-success">Activo</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactivo</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($last): ?>
                                    <?php echo html(formatDate($last)); ?>
                                <?php else: ?>
                                    <span class="text-muted">Nunca</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php if ($currentStaffRole === 'admin'): ?>
                                    <button type="button" class="btn btn-sm btn-outline-primary agent-edit-btn"
                                        data-id="<?php echo (int)$a['id']; ?>"
                                        data-firstname="<?php echo htmlspecialchars((string)($a['firstname'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                        data-lastname="<?php echo htmlspecialchars((string)($a['lastname'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                        data-email="<?php echo htmlspecialchars((string)($a['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                        data-username="<?php echo htmlspecialchars((string)($a['username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                        data-role="<?php echo htmlspecialchars((string)($a['role'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                        data-dept-id="<?php echo (int)($a['dept_id'] ?? 0); ?>"
                                        data-is-active="<?php echo $active ? '1' : '0'; ?>"
                                        data-bs-toggle="modal" data-bs-target="#agentEditModal">
                                        <i class="bi bi-pencil"></i>
                                    </button>

                                    <form method="post" action="staff.php" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <input type="hidden" name="do" value="send_reset">
                                        <input type="hidden" name="id" value="<?php echo (int)$a['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-secondary" title="Enviar reseteo">
                                            <i class="bi bi-envelope"></i>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($currentStaffRole === 'admin'): ?>
<div class="modal fade" id="agentCreateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="staff.php">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle text-primary"></i> Nuevo Agente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="do" value="create">

                    <div class="row g-2">
                        <div class="col-12 col-md-6">
                            <label class="form-label">Nombre</label>
                            <input type="text" name="firstname" class="form-control" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Apellido</label>
                            <input type="text" name="lastname" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Correo electrónico</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Usuario</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Rol</label>
                            <select name="role" class="form-select" required>
                                <?php foreach ($enabledRoles as $r): ?>
                                    <option value="<?php echo html($r); ?>"><?php echo html($r); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Departamento</label>
                            <select name="dept_id" class="form-select">
                                <option value="0">Sin asignar</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?php echo (int)$d['id']; ?>"><?php echo html($d['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" id="createIsActive" checked>
                                <label class="form-check-label" for="createIsActive">Activo</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="send_reset" id="createSendReset" checked>
                                <label class="form-check-label" for="createSendReset">Enviar al agente un correo de reseteo de contraseña</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle"></i> Crear</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="agentEditModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="staff.php">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil text-primary"></i> Editar Agente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="do" value="update">
                    <input type="hidden" name="id" id="edit_id" value="">

                    <div class="row g-2">
                        <div class="col-12 col-md-6">
                            <label class="form-label">Nombre</label>
                            <input type="text" name="firstname" id="edit_firstname" class="form-control" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Apellido</label>
                            <input type="text" name="lastname" id="edit_lastname" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Correo electrónico</label>
                            <input type="email" name="email" id="edit_email" class="form-control" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Usuario</label>
                            <input type="text" name="username" id="edit_username" class="form-control" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Rol</label>
                            <select name="role" id="edit_role" class="form-select" required>
                                <?php foreach ($enabledRoles as $r): ?>
                                    <option value="<?php echo html($r); ?>"><?php echo html($r); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Departamento</label>
                            <select name="dept_id" id="edit_dept_id" class="form-select">
                                <option value="0">Sin asignar</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?php echo (int)$d['id']; ?>"><?php echo html($d['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" id="editIsActive" checked>
                                <label class="form-check-label" for="editIsActive">Activo</label>
                            </div>
                        </div>
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
    (function () {
        var btns = document.querySelectorAll('.agent-edit-btn');
        if (!btns || !btns.length) return;
        btns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = this.getAttribute('data-id') || '';
                document.getElementById('edit_id').value = id;
                document.getElementById('edit_firstname').value = this.getAttribute('data-firstname') || '';
                document.getElementById('edit_lastname').value = this.getAttribute('data-lastname') || '';
                document.getElementById('edit_email').value = this.getAttribute('data-email') || '';
                document.getElementById('edit_username').value = this.getAttribute('data-username') || '';
                document.getElementById('edit_role').value = this.getAttribute('data-role') || 'agent';
                document.getElementById('edit_dept_id').value = this.getAttribute('data-dept-id') || '0';
                document.getElementById('editIsActive').checked = (this.getAttribute('data-is-active') || '0') === '1';
            });
        });
    })();
</script>
<?php endif; ?>

<?php
$content = ob_get_clean();

require_once 'layout_admin.php';
?>