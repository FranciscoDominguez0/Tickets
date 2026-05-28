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
$currentRoute = 'staff';

$eid = empresaId();

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

$deptHasEmpresa = false;
if (isset($mysqli) && $mysqli) {
    $col = $mysqli->query("SHOW COLUMNS FROM departments LIKE 'empresa_id'");
    $deptHasEmpresa = ($col && $col->num_rows > 0);
}

$hasStaffDepartmentsTable = false;
if (isset($mysqli) && $mysqli) {
    try {
        $rt = $mysqli->query("SHOW TABLES LIKE 'staff_departments'");
        $hasStaffDepartmentsTable = ($rt && $rt->num_rows > 0);
    } catch (Throwable $e) {
        $hasStaffDepartmentsTable = false;
    }
}

function normalizeDeptIds($raw): array {
    if (!is_array($raw)) return [];
    $ids = array_map('intval', $raw);
    $ids = array_values(array_unique(array_filter($ids, fn($v) => $v > 0)));
    return $ids;
}

$currentStaffId = (int)($_SESSION['staff_id'] ?? 0);
$currentStaffRole = '';
if ($currentStaffId > 0) {
    $stmtMe = $mysqli->prepare("SELECT role FROM staff WHERE id = ? AND empresa_id = ? LIMIT 1");
    if ($stmtMe) {
        $stmtMe->bind_param('ii', $currentStaffId, $eid);
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
    try {
        $resCol = $mysqli->query("SHOW COLUMNS FROM staff LIKE 'role'");
        if ($resCol && $resCol->num_rows > 0) {
            $colData = $resCol->fetch_assoc();
            if (stripos((string)($colData['Type'] ?? ''), 'enum') !== false) {
                $mysqli->query("ALTER TABLE staff MODIFY COLUMN role VARCHAR(100) COLLATE utf8mb4_unicode_ci DEFAULT 'agent'");
            }
        }
    } catch (Throwable $e) {}
}

$rolesHasEmpresaId = false;
if (isset($mysqli) && $mysqli) {
    try {
        $res = $mysqli->query("SHOW COLUMNS FROM roles LIKE 'empresa_id'");
        $rolesHasEmpresaId = ($res && $res->num_rows > 0);
    } catch (Throwable $e) {
        $rolesHasEmpresaId = false;
    }
}

$enabledRoles = [];
if (isset($mysqli) && $mysqli) {
    $sqlRoles = 'SELECT name FROM roles WHERE is_enabled = 1';
    if ($rolesHasEmpresaId) {
        $sqlRoles .= ' AND empresa_id = ' . (int)$eid;
    }
    $sqlRoles .= ' ORDER BY name';
    $resRoles = $mysqli->query($sqlRoles);
    if ($resRoles) {
        while ($r = $resRoles->fetch_assoc()) {
            $name = trim((string)($r['name'] ?? ''));
            if ($name !== '') $enabledRoles[] = $name;
        }
    }
}

$isValidEnabledRole = function (string $role) use ($mysqli, $eid, $rolesHasEmpresaId) {
    $role = trim($role);
    if ($role === '') return false;
    if (!isset($mysqli) || !$mysqli) return false;
    $sql = 'SELECT is_enabled FROM roles WHERE name = ?';
    if ($rolesHasEmpresaId) {
        $sql .= ' AND empresa_id = ?';
    }
    $sql .= ' LIMIT 1';

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) return false;
    if ($rolesHasEmpresaId) {
        $stmt->bind_param('si', $role, $eid);
    } else {
        $stmt->bind_param('s', $role);
    }
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

    if ($action === 'delete') {
        $id = isset($_POST['id']) && is_numeric($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id <= 0) {
            $_SESSION['flash_error'] = 'Agente inválido.';
            header('Location: staff.php');
            exit;
        }

        if ($id === $currentStaffId) {
            $_SESSION['flash_error'] = 'No puedes eliminar tu propio usuario.';
            header('Location: staff.php');
            exit;
        }

        $stmtChk = $mysqli->prepare('SELECT id FROM staff WHERE id = ? AND empresa_id = ? LIMIT 1');
        if (!$stmtChk) {
            $_SESSION['flash_error'] = 'No se pudo procesar la solicitud.';
            header('Location: staff.php');
            exit;
        }
        $stmtChk->bind_param('ii', $id, $eid);
        $stmtChk->execute();
        $row = $stmtChk->get_result()->fetch_assoc();
        if (!$row) {
            $_SESSION['flash_error'] = 'Agente no encontrado.';
            header('Location: staff.php');
            exit;
        }

        // Si existen tareas asignadas a este agente, bloquear con mensaje claro.
        $hasTasks = false;
        $rt = @$mysqli->query("SHOW TABLES LIKE 'tasks'");
        if ($rt && $rt->num_rows > 0) $hasTasks = true;
        if ($hasTasks) {
            $stmtCntT = $mysqli->prepare('SELECT COUNT(*) c FROM tasks WHERE assigned_to = ? AND empresa_id = ?');
            if ($stmtCntT) {
                $stmtCntT->bind_param('ii', $id, $eid);
                $stmtCntT->execute();
                $cntRow = $stmtCntT->get_result()->fetch_assoc();
                if ((int)($cntRow['c'] ?? 0) > 0) {
                    $_SESSION['flash_error'] = 'No se puede eliminar este agente porque tiene tareas asignadas. Reasigna o elimina esas tareas antes de eliminar el agente.';
                    header('Location: staff.php');
                    exit;
                }
            }
        }

        $stmtDel = $mysqli->prepare('DELETE FROM staff WHERE id = ? AND empresa_id = ?');
        if (!$stmtDel) {
            $_SESSION['flash_error'] = 'No se pudo eliminar el agente.';
            header('Location: staff.php');
            exit;
        }
        $stmtDel->bind_param('ii', $id, $eid);
        try {
            if ($stmtDel->execute()) {
                $_SESSION['flash_msg'] = 'Agente eliminado correctamente.';
            } else {
                $_SESSION['flash_error'] = 'No se pudo eliminar el agente.';
            }
        } catch (mysqli_sql_exception $e) {
            // 1451: Cannot delete or update a parent row (FK)
            if ((int)$e->getCode() === 1451) {
                $_SESSION['flash_error'] = 'No se puede eliminar el agente porque está siendo usado por otros registros (por ejemplo: tareas asignadas). Reasigna o elimina esas referencias antes de eliminar.';
            } else {
                $_SESSION['flash_error'] = 'No se pudo eliminar el agente.';
            }
        }
        header('Location: staff.php');
        exit;
    }

    if ($action === 'create') {
        $firstname = trim((string)($_POST['firstname'] ?? ''));
        $lastname = trim((string)($_POST['lastname'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $username = trim((string)($_POST['username'] ?? ''));
        $role = trim((string)($_POST['role'] ?? 'agent'));
        $deptIds = normalizeDeptIds($_POST['dept_ids'] ?? []);
        $deptId = isset($_POST['dept_id']) && is_numeric($_POST['dept_id']) ? (int)$_POST['dept_id'] : 0;
        if (!empty($deptIds)) {
            $deptId = (int)$deptIds[0];
        }
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

        $stmtDu = $mysqli->prepare('SELECT id FROM staff WHERE empresa_id = ? AND (username = ? OR email = ?) LIMIT 1');
        if ($stmtDu) {
            $stmtDu->bind_param('iss', $eid, $username, $email);
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

        $stmtIns = $mysqli->prepare('INSERT INTO staff (empresa_id, username, email, firstname, lastname, password, dept_id, role, is_active, created) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
        if (!$stmtIns) {
            $_SESSION['flash_error'] = 'No se pudo crear el agente.';
            header('Location: staff.php');
            exit;
        }
        $stmtIns->bind_param('isssssisi', $eid, $username, $email, $firstname, $lastname, $hash, $deptId, $role, $isActive);
        if (!$stmtIns->execute()) {
            $_SESSION['flash_error'] = 'No se pudo crear el agente.';
            header('Location: staff.php');
            exit;
        }

        $newId = (int)$mysqli->insert_id;

        if ($newId > 0 && $hasStaffDepartmentsTable) {
            if (!empty($deptIds)) {
                $stmtSd = $mysqli->prepare('INSERT IGNORE INTO staff_departments (staff_id, dept_id) VALUES (?, ?)');
                if ($stmtSd) {
                    foreach ($deptIds as $did) {
                        $stmtSd->bind_param('ii', $newId, $did);
                        $stmtSd->execute();
                    }
                }
            }
        }

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
        $deptIds = normalizeDeptIds($_POST['dept_ids'] ?? []);
        $deptId = isset($_POST['dept_id']) && is_numeric($_POST['dept_id']) ? (int)$_POST['dept_id'] : 0;
        if (!empty($deptIds)) {
            $deptId = (int)$deptIds[0];
        }
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

        $stmtDu = $mysqli->prepare('SELECT id FROM staff WHERE empresa_id = ? AND (username = ? OR email = ?) AND id <> ? LIMIT 1');
        if ($stmtDu) {
            $stmtDu->bind_param('issi', $eid, $username, $email, $id);
            $stmtDu->execute();
            $dup = $stmtDu->get_result()->fetch_assoc();
            if ($dup) {
                $_SESSION['flash_error'] = 'Ya existe otro agente con ese usuario o correo.';
                header('Location: staff.php');
                exit;
            }
        }

        $stmtUp = $mysqli->prepare('UPDATE staff SET username = ?, email = ?, firstname = ?, lastname = ?, dept_id = ?, role = ?, is_active = ?, updated = NOW() WHERE id = ? AND empresa_id = ?');
        if (!$stmtUp) {
            $_SESSION['flash_error'] = 'No se pudo actualizar el agente.';
            header('Location: staff.php');
            exit;
        }
        $stmtUp->bind_param('ssssissii', $username, $email, $firstname, $lastname, $deptId, $role, $isActive, $id, $eid);
        $stmtUp->execute();

        if ($hasStaffDepartmentsTable) {
            $stmtDelSd = $mysqli->prepare('DELETE FROM staff_departments WHERE staff_id = ?');
            if ($stmtDelSd) {
                $stmtDelSd->bind_param('i', $id);
                $stmtDelSd->execute();
            }

            if (!empty($deptIds)) {
                $stmtInsSd = $mysqli->prepare('INSERT IGNORE INTO staff_departments (staff_id, dept_id) VALUES (?, ?)');
                if ($stmtInsSd) {
                    foreach ($deptIds as $did) {
                        $stmtInsSd->bind_param('ii', $id, $did);
                        $stmtInsSd->execute();
                    }
                }
            }
        }

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

        $stmtS = $mysqli->prepare('SELECT id, email, username, firstname, lastname FROM staff WHERE id = ? AND empresa_id = ? LIMIT 1');
        if (!$stmtS) {
            $_SESSION['flash_error'] = 'No se pudo procesar la solicitud.';
            header('Location: staff.php');
            exit;
        }
        $stmtS->bind_param('ii', $id, $eid);
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
$status = (string)($_GET['status'] ?? '');

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
        d.name AS dept_name";
if ($hasStaffDepartmentsTable) {
    $sql .= ",\n        GROUP_CONCAT(DISTINCT d2.name ORDER BY d2.name SEPARATOR ', ') AS dept_names,\n        GROUP_CONCAT(DISTINCT d2.id ORDER BY d2.id SEPARATOR ',') AS dept_ids\n";
} else {
    $sql .= ",\n        NULL AS dept_names,\n        NULL AS dept_ids\n";
}
$sql .= "
    FROM staff s
    LEFT JOIN departments d ON s.dept_id = d.id
";
if ($hasStaffDepartmentsTable) {
    $sql .= "    LEFT JOIN staff_departments sd ON sd.staff_id = s.id\n";
    $sql .= "    LEFT JOIN departments d2 ON d2.id = sd.dept_id\n";
}
$sql .= "
    WHERE 1=1
        AND s.empresa_id = ?
";
$params = [];
$types = 'i';
$params[] = $eid;

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
    if ($hasStaffDepartmentsTable) {
        $sql .= " AND EXISTS (SELECT 1 FROM staff_departments sd2 WHERE sd2.staff_id = s.id AND sd2.dept_id = ?)";
        $params[] = $deptFilter;
        $types .= 'i';
    } else {
        $sql .= " AND s.dept_id = ?";
        $params[] = $deptFilter;
        $types .= 'i';
    }
}
if (isset($_GET['status']) && ($_GET['status'] === 'active' || $_GET['status'] === 'inactive')) {
    $sql .= " AND s.is_active = ?";
    $params[] = ($_GET['status'] === 'active') ? 1 : 0;
    $types .= 'i';
}

$sql .= " GROUP BY s.id ORDER BY s.firstname, s.lastname";

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
$deptSql = "SELECT id, name FROM departments WHERE is_active = 1";
if ($deptHasEmpresa) {
    $deptSql .= " AND empresa_id = ?";
}
$deptSql .= " ORDER BY name";
$deptStmt = $mysqli->prepare($deptSql);
if ($deptStmt) {
    if ($deptHasEmpresa) {
        $deptStmt->bind_param('i', $eid);
    }
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

<style>
/* ── Premium Agent Roles Styling (Celeste en Modo Oscuro Solucionado) ── */
.badge-agent-role {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 4px 10px;
    border-radius: 8px;
    font-weight: 700;
    font-size: 0.78rem;
    line-height: 1;
    border: 1px solid transparent;
}
/* Administrador: rojo corporativo premium */
.badge-agent-role.role-admin {
    background: rgba(239, 68, 68, 0.1) !important;
    color: #ef4444 !important;
    border-color: rgba(239, 68, 68, 0.2) !important;
}
/* Agentes u otros: azul/pizarra premium */
.badge-agent-role.role-other {
    background: rgba(59, 130, 246, 0.1) !important;
    color: #3b82f6 !important;
    border-color: rgba(59, 130, 246, 0.2) !important;
}

/* Modo oscuro */
body.dark-mode .badge-agent-role.role-admin {
    background: rgba(239, 68, 68, 0.18) !important;
    color: #f87171 !important;
    border-color: rgba(239, 68, 68, 0.3) !important;
}
body.dark-mode .badge-agent-role.role-other {
    background: rgba(59, 130, 246, 0.18) !important;
    color: #60a5fa !important;
    border-color: rgba(59, 130, 246, 0.3) !important;
}

/* Tarjetas móviles y bordes */
.agent-mobile-card {
    padding: 16px;
    background: #ffffff;
    position: relative;
    border-radius: 12px;
}
body.dark-mode .agent-mobile-card {
    background: #0f0f11 !important;
    color: #f1f5f9 !important;
}
body.dark-mode .agent-mobile-card .agent-card-title {
    color: #fff !important;
}
body.dark-mode .agent-mobile-card .agent-card-username {
    color: #94a3b8 !important;
}
body.dark-mode .agent-mobile-card .agent-card-meta-text {
    color: #cbd5e1 !important;
}
body.dark-mode .agent-mobile-card .agent-card-divider {
    border-top-color: #222 !important;
}

/* ── Premium Status Badges ── */
.badge-status {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 5px 12px;
    border-radius: 20px;
    font-weight: 700;
    font-size: 0.75rem;
    line-height: 1;
    border: 1px solid transparent;
}
.badge-status.active {
    background: rgba(16, 185, 129, 0.1) !important;
    color: #10b981 !important;
    border-color: rgba(16, 185, 129, 0.2) !important;
}
.badge-status.inactive {
    background: rgba(100, 116, 139, 0.1) !important;
    color: #64748b !important;
    border-color: rgba(100, 116, 139, 0.2) !important;
}
body.dark-mode .badge-status.active {
    background: rgba(16, 185, 129, 0.18) !important;
    color: #34d399 !important;
    border-color: rgba(16, 185, 129, 0.3) !important;
}
body.dark-mode .badge-status.inactive {
    background: rgba(148, 163, 184, 0.18) !important;
    color: #94a3b8 !important;
    border-color: rgba(148, 163, 184, 0.3) !important;
}

/* ── Premium Action Buttons ── */
.btn-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 8px;
    border: 1px solid #e2e8f0 !important;
    background: transparent !important;
    color: #64748b !important;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.2s ease;
}
.btn-action.edit:hover {
    background: rgba(37, 99, 235, 0.06) !important;
    color: #2563eb !important;
    border-color: rgba(37, 99, 235, 0.2) !important;
    transform: translateY(-1px);
}
.btn-action.reset:hover {
    background: rgba(8, 145, 178, 0.06) !important;
    color: #0891b2 !important;
    border-color: rgba(8, 145, 178, 0.2) !important;
    transform: translateY(-1px);
}
.btn-action.delete:hover {
    background: rgba(239, 68, 68, 0.06) !important;
    color: #ef4444 !important;
    border-color: rgba(239, 68, 68, 0.2) !important;
    transform: translateY(-1px);
}

/* Dark mode for Action Buttons */
body.dark-mode .btn-action {
    border-color: #27272a !important;
    color: #a1a1aa !important;
}
body.dark-mode .btn-action.edit:hover {
    background: rgba(59, 130, 246, 0.12) !important;
    color: #60a5fa !important;
    border-color: rgba(59, 130, 246, 0.3) !important;
}
body.dark-mode .btn-action.reset:hover {
    background: rgba(6, 182, 212, 0.12) !important;
    color: #22d3ee !important;
    border-color: rgba(6, 182, 212, 0.3) !important;
}
body.dark-mode .btn-action.delete:hover {
    background: rgba(239, 68, 68, 0.12) !important;
    color: #f87171 !important;
    border-color: rgba(239, 68, 68, 0.3) !important;
}

/* Dark mode for desktop table text */
body.dark-mode .agent-desktop-name {
    color: #f1f5f9 !important;
}
body.dark-mode .agent-desktop-username {
    color: #94a3b8 !important;
}
body.dark-mode .agent-desktop-meta {
    color: #94a3b8 !important;
}
body.dark-mode .agent-desktop-meta a {
    color: #94a3b8 !important;
}
body.dark-mode .agent-desktop-meta a:hover {
    color: #60a5fa !important;
}
body.dark-mode .agent-desktop-dept {
    color: #cbd5e1 !important;
}
body.dark-mode .table th {
    background: #18181b !important;
    border-color: #27272a !important;
    color: #a1a1aa !important;
}
body.dark-mode .table td {
    border-color: #27272a !important;
}

/* ── Premium Search Card ── */
.search-card {
    background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
    border-radius: 16px;
    padding: 20px 24px;
    box-shadow: 0 4px 20px rgba(30, 58, 138, 0.06);
    border: 1px solid rgba(30, 64, 175, 0.08);
    margin-bottom: 24px;
}
.search-card .search-wrap {
    display: flex;
    align-items: center;
    gap: 12px;
}
.search-card input.form-control {
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    padding: 12px 16px 12px 44px;
    font-size: 0.95rem;
    height: 48px;
    transition: all 0.2s ease;
}
.search-card input.form-control:focus {
    border-color: #ef4444;
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.15);
}
.search-card .search-icon-wrap {
    position: relative;
    flex: 1;
}
.search-card .search-icon-wrap::before {
    content: "";
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    width: 20px;
    height: 20px;
    background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2394a3b8' stroke-width='2'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z'/%3E%3C/svg%3E") no-repeat center;
    pointer-events: none;
    z-index: 5;
}
.search-card .btn-search {
    border-radius: 12px;
    padding: 0 24px;
    height: 48px;
    background: linear-gradient(135deg, #dc2626, #ef4444);
    color: #fff !important;
    border: none;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s ease;
    box-shadow: 0 4px 12px rgba(220, 38, 38, 0.2);
}
.search-card .btn-search:hover {
    opacity: 0.95;
    box-shadow: 0 6px 16px rgba(220, 38, 38, 0.3);
    transform: translateY(-1px);
}
.search-card .btn-filter {
    border-radius: 12px;
    padding: 0 20px;
    height: 48px;
    background: #f1f5f9;
    color: #475569;
    border: 1px solid #e2e8f0;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s ease;
}
.search-card .btn-filter:hover {
    background: #e2e8f0;
    color: #334155;
}
.search-card .btn-clear {
    border-radius: 12px;
    padding: 0 20px;
    height: 48px;
    background: #fef2f2;
    color: #dc2626;
    border: 1px solid #fecaca;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s ease;
    text-decoration: none;
}
.search-card .btn-clear:hover {
    background: #fee2e2;
    color: #b91c1c;
}

/* Modo Oscuro para Search Card */
body.dark-mode .search-card {
    background: linear-gradient(145deg, #18181b 0%, #09090b 100%);
    border-color: rgba(239, 68, 68, 0.15);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
}
body.dark-mode .search-card input.form-control {
    background: #09090b !important;
    border-color: #27272a !important;
    color: #f1f5f9 !important;
}
body.dark-mode .search-card input.form-control:focus {
    border-color: #ef4444 !important;
}
body.dark-mode .search-card .btn-filter {
    background: #27272a;
    color: #e4e4e7;
    border-color: #3f3f46;
}
body.dark-mode .search-card .btn-filter:hover {
    background: #3f3f46;
    color: #fff;
}
body.dark-mode .search-card .btn-clear {
    background: rgba(239, 68, 68, 0.15);
    color: #f87171;
    border-color: rgba(239, 68, 68, 0.25);
}
body.dark-mode .search-card .btn-clear:hover {
    background: rgba(239, 68, 68, 0.25);
    color: #fca5a5;
}
body.dark-mode .advanced-filters-panel {
    background: #09090b !important;
    border: 1px solid #27272a !important;
}
body.dark-mode .advanced-filters-panel label {
    color: #a1a1aa !important;
}
body.dark-mode .advanced-filters-panel select {
    background-color: #09090b !important;
    border-color: #27272a !important;
    color: #cbd5e1 !important;
}
</style>

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

<div class="search-card">
    <form method="GET" action="staff.php" class="search-form">
        <!-- Fila Principal (Estilo users.php) -->
        <div class="search-wrap d-flex align-items-center gap-2 flex-wrap">
            <div class="search-icon-wrap flex-grow-1">
                <input type="text" name="q" class="form-control" placeholder="Buscar agentes por nombre, email o usuario..." value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            
            <button type="submit" class="btn btn-search">
                <i class="bi bi-search"></i> Buscar
            </button>
            
            <!-- Botón para alternar los filtros ocultos -->
            <button type="button" class="btn btn-filter" data-bs-toggle="collapse" data-bs-target="#advancedFilters" aria-expanded="<?php echo ($deptFilter > 0 || $status !== '') ? 'true' : 'false'; ?>" aria-controls="advancedFilters">
                <i class="bi bi-funnel"></i> Filtros
            </button>

            <?php if ($search !== '' || $deptFilter > 0 || $status !== ''): ?>
                <a class="btn btn-clear" href="staff.php">
                    <i class="bi bi-x-circle"></i> Limpiar
                </a>
            <?php endif; ?>
        </div>

        <!-- Panel de Filtros Oculto/Colapsable -->
        <div class="collapse <?php echo ($deptFilter > 0 || $status !== '') ? 'show' : ''; ?> mt-3" id="advancedFilters">
            <div class="card card-body bg-light border-0 p-3 advanced-filters-panel" style="border-radius:12px;">
                <div class="row g-3">
                    <!-- Dropdown de Departamento -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-secondary">Departamento</label>
                        <select name="did" class="form-select" style="border-radius:10px;">
                            <option value="0">Todos</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?php echo (int)$d['id']; ?>" <?php echo $deptFilter === (int)$d['id'] ? 'selected' : ''; ?>><?php echo html($d['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <!-- Dropdown de Estado -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-secondary">Estado</label>
                        <select name="status" class="form-select" style="border-radius:10px;">
                            <option value="" <?php echo $status === '' ? 'selected' : ''; ?>>Todos</option>
                            <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Activos</option>
                            <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactivos</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<div class="card settings-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong><i class="bi bi-person-badge"></i> Lista de Agentes</strong>
        <div class="d-flex gap-2">
            <?php if (roleHasPermission('admin.access')): ?>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#agentCreateModal">
                    <i class="bi bi-plus-circle"></i> Nuevo Agente
                </button>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light" style="border-bottom: 2px solid #e2e8f0; background-color: #f8fafc;">
                    <tr>
                        <th style="font-weight: 700; color: #475569; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; padding-left: 20px;">Agente</th>
                        <th style="font-weight: 700; color: #475569; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em;">Departamento</th>
                        <th style="font-weight: 700; color: #475569; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em;">Rol</th>
                        <th class="text-center" style="font-weight: 700; color: #475569; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em;">Estado</th>
                        <th style="font-weight: 700; color: #475569; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em;">Último acceso</th>
                        <th class="text-end" style="font-weight: 700; color: #475569; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; padding-right: 20px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($agents)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">No hay resultados.</td></tr>
                <?php else: ?>
                    <?php foreach ($agents as $a): ?>
                        <?php
                        $name = trim((string)($a['firstname'] ?? '') . ' ' . (string)($a['lastname'] ?? ''));
                        if ($name === '') $name = (string)($a['username'] ?? '');
                        // Mostrar solo el departamento principal del agente.
                        $dept = trim((string)($a['dept_name'] ?? ''));
                        if ($dept === '') {
                            $allDeptNames = array_values(array_filter(array_map('trim', explode(',', (string)($a['dept_names'] ?? '')))));
                            if (!empty($allDeptNames)) {
                                $dept = (string)$allDeptNames[0];
                            }
                        }

                        $role = (string)($a['role'] ?? '');
                        $active = (int)($a['is_active'] ?? 0) === 1;
                        $last = $a['last_login'] ?? null;

                        // Generar iniciales y colores de avatar para un look consistente
                        $parts = preg_split('/\s+/', trim($name));
                        $i1 = strtoupper((string)($parts[0][0] ?? ''));
                        $i2 = '';
                        if (count($parts) > 1) {
                            $i2 = strtoupper((string)($parts[1][0] ?? ''));
                        } elseif (strlen($name) > 1) {
                            $i2 = strtoupper(substr($name, 1, 1));
                        }
                        $initials = trim($i1 . $i2);
                        if ($initials === '') $initials = 'A';

                        $avatarColors = ['#ef4444', '#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899'];
                        $avatarColor = $avatarColors[($a['id'] ?? 0) % count($avatarColors)];
                        ?>
                        <tr>
                            <!-- VISTA MÓVIL (Tarjeta Premium) -->
                            <td class="d-md-none p-0">
                                <div class="agent-mobile-card">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div class="d-flex align-items-center gap-3">
                                            <?php if ($active): ?>
                                            <span style="background: #f0fdf4; color: #16a34a; padding: 4px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: 800; border: 1px solid #bbf7d0;"><i class="bi bi-check-circle-fill me-1"></i>Activo</span>
                                            <?php else: ?>
                                            <span style="background: #f1f5f9; color: #64748b; padding: 4px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: 800; border: 1px solid #e2e8f0;"><i class="bi bi-pause-circle-fill me-1"></i>Inactivo</span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (roleHasPermission('admin.access')): ?>
                                        <div class="dropdown">
                                            <button type="button" class="btn btn-sm btn-light border-0" data-bs-toggle="dropdown" aria-expanded="false" style="width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; background: #f8fafc;">
                                                <i class="bi bi-three-dots-vertical text-secondary"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="border-radius: 12px; border: 1px solid #e2e8f0; border-top-right-radius: 0;">
                                                <li>
                                                    <a class="dropdown-item py-2 fw-semibold agent-edit-btn" href="#"
                                                        data-id="<?php echo (int)$a['id']; ?>"
                                                        data-firstname="<?php echo htmlspecialchars((string)($a['firstname'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-lastname="<?php echo htmlspecialchars((string)($a['lastname'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-email="<?php echo htmlspecialchars((string)($a['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-username="<?php echo htmlspecialchars((string)($a['username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-role="<?php echo htmlspecialchars((string)($a['role'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-dept-ids="<?php echo htmlspecialchars((string)($a['dept_ids'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-is-active="<?php echo $active ? '1' : '0'; ?>"
                                                        data-bs-toggle="modal" data-bs-target="#agentEditModal">
                                                        <i class="bi bi-pencil me-2 text-primary"></i> Editar Agente
                                                    </a>
                                                </li>
                                                <li>
                                                    <form method="post" action="staff.php" class="d-inline w-100 m-0 p-0">
                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                        <input type="hidden" name="do" value="send_reset">
                                                        <input type="hidden" name="id" value="<?php echo (int)$a['id']; ?>">
                                                        <button type="submit" class="dropdown-item py-2 fw-semibold">
                                                            <i class="bi bi-envelope me-2 text-info"></i> Enviar reseteo
                                                        </button>
                                                    </form>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <a class="dropdown-item py-2 text-danger fw-bold agent-delete-btn" href="#"
                                                        data-id="<?php echo (int)$a['id']; ?>"
                                                        data-name="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-bs-toggle="modal" data-bs-target="#agentDeleteModal">
                                                        <i class="bi bi-trash me-2"></i> Eliminar
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="agent-card-title" style="font-size: 1.1rem; font-weight: 800; color: #0f172a; margin-bottom: 2px; line-height: 1.2;">
                                        <?php echo html($name); ?>
                                    </div>
                                    <div class="agent-card-username" style="font-size: 0.85rem; color: #64748b; margin-bottom: 8px; font-weight: 600;">
                                        @<?php echo html((string)($a['username'] ?? '')); ?>
                                    </div>
                                    
                                    <div style="font-size: 0.9rem; color: #475569; margin-bottom: 12px; font-weight: 500;">
                                        <a class="text-decoration-none" href="mailto:<?php echo html((string)($a['email'] ?? '')); ?>"><?php echo html((string)($a['email'] ?? '')); ?></a>
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                                        <div class="agent-card-meta-text" style="font-size: 0.8rem; color: #334155; font-weight: 600; display: inline-flex; align-items: center; gap: 4px;">
                                            <i class="bi bi-shield-lock text-muted"></i> Rol:
                                            <?php if ($role === 'admin'): ?>
                                                <span class="badge-agent-role role-admin" style="padding: 2px 6px; font-size: 0.7rem;">Administrador</span>
                                            <?php elseif ($role !== ''): ?>
                                                <span class="badge-agent-role role-other" style="padding: 2px 6px; font-size: 0.7rem;"><?php echo html(ucfirst($role)); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted" style="font-size: 0.75rem;">—</span>
                                            <?php endif; ?>
                                        </div>
                                        <div style="font-size: 0.75rem; color: #64748b; font-weight: 600;">
                                            <i class="bi bi-clock-history me-1 text-muted"></i> <?php echo $last ? date('d M, Y', strtotime($last)) : 'Nunca'; ?>
                                        </div>
                                    </div>

                                    <div class="d-flex align-items-center mt-2 pt-3 agent-card-divider" style="border-top: 1px dashed #e2e8f0;">
                                        <div style="font-size: 0.75rem; color: #64748b; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; margin-right: 8px;">
                                            Dpto:
                                        </div>
                                        <?php if ($dept !== ''): ?>
                                        <span style="background: rgba(37,99,235,0.08); color: #2563eb; padding: 4px 10px; border-radius: 8px; font-weight: 800; font-size: 0.75rem;">
                                            <i class="bi bi-building me-1"></i><?php echo html($dept); ?>
                                        </span>
                                        <?php else: ?>
                                        <span style="background: #f1f5f9; color: #64748b; padding: 4px 10px; border-radius: 8px; font-weight: 700; font-size: 0.75rem;">
                                            Sin asignar
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>

                            <!-- VISTA ESCRITORIO -->
                            <td class="d-none d-md-table-cell" style="vertical-align: middle; padding-left: 20px;">
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <div style="width: 36px; height: 36px; border-radius: 50%; background: <?php echo html($avatarColor); ?>; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: 700; flex-shrink: 0; letter-spacing: 0.02em;">
                                        <?php echo html($initials); ?>
                                    </div>
                                    <div style="min-width: 0; flex: 1;">
                                        <div style="font-weight: 700; font-size: 0.95rem; color: #1e293b;" class="agent-desktop-name"><?php echo html($name); ?></div>
                                        <div style="font-size: 0.8rem; color: #64748b;" class="agent-desktop-meta">@<?php echo html((string)($a['username'] ?? '')); ?> · <a class="text-decoration-none" href="mailto:<?php echo html((string)($a['email'] ?? '')); ?>" style="color: inherit;"><?php echo html((string)($a['email'] ?? '')); ?></a></div>
                                    </div>
                                </div>
                            </td>
                            <td class="d-none d-md-table-cell" style="vertical-align: middle;">
                                <?php if ($dept !== ''): ?>
                                    <span class="agent-desktop-dept" style="color: #475569; font-weight: 600; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 6px;">
                                        <i class="bi bi-building text-muted" style="font-size: 0.85rem;"></i> <?php echo html($dept); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted" style="font-size:0.8rem;">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="d-none d-md-table-cell" style="vertical-align: middle;">
                                <?php if ($role !== ''): ?>
                                    <?php if ($role === 'admin'): ?>
                                        <span class="badge-agent-role role-admin">Administrador</span>
                                    <?php else: ?>
                                        <span class="badge-agent-role role-other"><?php echo html(ucfirst($role)); ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center d-none d-md-table-cell" style="vertical-align: middle;">
                                <?php if ($active): ?>
                                    <span style="color: #10b981; font-weight: 600; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 6px; justify-content: center; width: 100%;">
                                        <span style="width: 8px; height: 8px; border-radius: 50%; background-color: #10b981; display: inline-block;"></span> Activo
                                    </span>
                                <?php else: ?>
                                    <span style="color: #64748b; font-weight: 600; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 6px; justify-content: center; width: 100%;">
                                        <span style="width: 8px; height: 8px; border-radius: 50%; background-color: #94a3b8; display: inline-block;"></span> Inactivo
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="d-none d-md-table-cell" style="vertical-align: middle; font-weight: 500; font-size: 0.85rem; color:#475569;">
                                <?php if ($last): ?>
                                    <i class="bi bi-clock me-1 text-muted" style="font-size:0.8rem;"></i> <?php echo html(formatDate($last)); ?>
                                <?php else: ?>
                                    <span class="text-muted">Nunca</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end d-none d-md-table-cell" style="vertical-align: middle; padding-right: 20px;">
                                <?php if (roleHasPermission('admin.access')): ?>
                                    <div class="d-flex align-items-center justify-content-end gap-2">
                                        <button type="button" class="btn-action edit agent-edit-btn"
                                            data-id="<?php echo (int)$a['id']; ?>"
                                            data-firstname="<?php echo htmlspecialchars((string)($a['firstname'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                            data-lastname="<?php echo htmlspecialchars((string)($a['lastname'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                            data-email="<?php echo htmlspecialchars((string)($a['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                            data-username="<?php echo htmlspecialchars((string)($a['username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                            data-role="<?php echo htmlspecialchars((string)($a['role'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                            data-dept-ids="<?php echo htmlspecialchars((string)($a['dept_ids'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                            data-is-active="<?php echo $active ? '1' : '0'; ?>"
                                            data-bs-toggle="modal" data-bs-target="#agentEditModal"
                                            title="Editar Agente">
                                            <i class="bi bi-pencil-fill"></i>
                                        </button>

                                        <form method="post" action="staff.php" class="m-0 p-0 d-inline">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                            <input type="hidden" name="do" value="send_reset">
                                            <input type="hidden" name="id" value="<?php echo (int)$a['id']; ?>">
                                            <button type="submit" class="btn-action reset" title="Enviar reseteo de contraseña">
                                                <i class="bi bi-envelope-fill"></i>
                                            </button>
                                        </form>

                                        <button type="button" class="btn-action delete agent-delete-btn"
                                            data-id="<?php echo (int)$a['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>"
                                            data-bs-toggle="modal" data-bs-target="#agentDeleteModal"
                                            title="Eliminar Agente">
                                            <i class="bi bi-trash-fill"></i>
                                        </button>
                                    </div>
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

<style>
/* Responsive Table -> Cards for Mobile */
@media (max-width: 768px) {
    .settings-card { background: transparent !important; box-shadow: none !important; }
    .settings-card .card-header { border-radius: 12px; margin-bottom: 12px; }
    .settings-card .table-responsive { border: none !important; overflow: visible !important; }
    .settings-card .table { background: transparent !important; }
    .settings-card .table thead { display: none !important; }
    .settings-card .table tbody tr {
        display: block !important;
        margin-bottom: 1rem !important;
        background: #fff !important;
        border: 1px solid #e2e8f0 !important;
        border-radius: 16px !important;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05) !important;
        overflow: hidden !important;
    }
    .settings-card .table tbody td.d-md-none {
        display: block !important;
        width: 100% !important;
        padding: 0 !important;
        border: none !important;
    }
    .settings-card .table tbody td.d-none {
        display: none !important;
    }
}
</style>

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
                            <label class="form-label d-flex justify-content-between align-items-center mb-2">
                                <span>Departamento(s)</span>
                                <span class="badge bg-secondary rounded-pill dept-count-badge" id="createDeptBadge">0 seleccionados</span>
                            </label>
                            
                            <!-- Botón elegante para expandir/colapsar -->
                            <button type="button" class="btn btn-outline-secondary btn-sm w-100 d-flex justify-content-between align-items-center py-2 px-3 dept-toggle-btn" data-target="createDeptContainer">
                                <span><i class="bi bi-building me-1"></i> Gestionar departamentos asociados...</span>
                                <i class="bi bi-chevron-down toggle-arrow"></i>
                            </button>
                            
                            <!-- Contenedor expandible -->
                            <div id="createDeptContainer" class="dept-expandable-container mt-2" style="display: none;">
                                <!-- Barra de búsqueda y acciones rápidas -->
                                <div class="d-flex gap-2 mb-2">
                                    <div class="position-relative flex-grow-1">
                                        <input type="text" class="form-control form-control-sm dept-search-input" placeholder="Buscar departamento..." style="border-radius: 8px; padding-left: 36px !important;">
                                    </div>
                                    <button type="button" class="btn btn-light btn-sm dept-select-all" style="border-radius: 8px; font-weight: 700; font-size: 0.78rem; border: 1px solid rgba(15, 23, 42, 0.1);">Mostrar todos</button>
                                    <button type="button" class="btn btn-light btn-sm dept-clear-all" style="border-radius: 8px; font-weight: 700; font-size: 0.78rem; border: 1px solid rgba(15, 23, 42, 0.1);">Ocultar</button>
                                </div>
                                
                                <!-- Checklist con scroll -->
                                <div class="border rounded-3 p-2 dept-checkbox-list" style="max-height: 160px; overflow-y: auto;">
                                    <div class="dept-placeholder text-muted text-center py-3 small"><i class="bi bi-search me-1"></i> Escribe para buscar o haz clic en "Todos"</div>
                                    <?php foreach ($departments as $d): ?>
                                        <div class="form-check dept-check-item" style="display: none;">
                                            <input class="form-check-input dept-checkbox" type="checkbox" name="dept_ids[]" id="createDept<?php echo (int)$d['id']; ?>" value="<?php echo (int)$d['id']; ?>">
                                            <label class="form-check-label" for="createDept<?php echo (int)$d['id']; ?>"><?php echo html($d['name']); ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="form-text mt-1">Si marcas varios, el primero guardado será el principal (compatibilidad).</div>
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
                            <label class="form-label d-flex justify-content-between align-items-center mb-2">
                                <span>Departamento(s)</span>
                                <span class="badge bg-secondary rounded-pill dept-count-badge" id="editDeptBadge">0 seleccionados</span>
                            </label>
                            
                            <!-- Botón elegante para expandir/colapsar -->
                            <button type="button" class="btn btn-outline-secondary btn-sm w-100 d-flex justify-content-between align-items-center py-2 px-3 dept-toggle-btn" data-target="editDeptContainer">
                                <span><i class="bi bi-building me-1"></i> Gestionar departamentos asociados...</span>
                                <i class="bi bi-chevron-down toggle-arrow"></i>
                            </button>
                            
                            <!-- Contenedor expandible -->
                            <div id="editDeptContainer" class="dept-expandable-container mt-2" style="display: none;">
                                <!-- Barra de búsqueda y acciones rápidas -->
                                <div class="d-flex gap-2 mb-2">
                                    <div class="position-relative flex-grow-1">
                                        <input type="text" class="form-control form-control-sm dept-search-input" placeholder="Buscar departamento..." style="border-radius: 8px; padding-left: 36px !important;">
                                    </div>
                                    <button type="button" class="btn btn-light btn-sm dept-select-all" style="border-radius: 8px; font-weight: 700; font-size: 0.78rem; border: 1px solid rgba(15, 23, 42, 0.1);">Mostrar todos</button>
                                    <button type="button" class="btn btn-light btn-sm dept-clear-all" style="border-radius: 8px; font-weight: 700; font-size: 0.78rem; border: 1px solid rgba(15, 23, 42, 0.1);">Ocultar</button>
                                </div>
                                
                                <!-- Checklist con scroll -->
                                <div class="border rounded-3 p-2 dept-checkbox-list" style="max-height: 160px; overflow-y: auto;" id="edit_dept_box">
                                    <div class="dept-placeholder text-muted text-center py-3 small"><i class="bi bi-search me-1"></i> Escribe para buscar o haz clic en "Todos"</div>
                                    <?php foreach ($departments as $d): ?>
                                        <div class="form-check dept-check-item" style="display: none;">
                                            <input class="form-check-input dept-checkbox" type="checkbox" name="dept_ids[]" id="editDept<?php echo (int)$d['id']; ?>" value="<?php echo (int)$d['id']; ?>">
                                            <label class="form-check-label" for="editDept<?php echo (int)$d['id']; ?>"><?php echo html($d['name']); ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="form-text mt-1">Si marcas varios, el primero guardado será el principal (compatibilidad).</div>
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

<div class="modal fade" id="agentDeleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="staff.php">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-trash text-danger"></i> Eliminar Agente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="do" value="delete">
                    <input type="hidden" name="id" id="delete_id" value="">
                    ¿Deseas eliminar el agente seleccionado?
                    <div class="text-muted small mt-2">Agente: <strong><span id="delete_agent_name">—</span></strong></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger"><i class="bi bi-trash"></i> Eliminar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    (function () {
        // --- Funciones del Widget de Departamentos ---
        function initDeptWidgets() {
            var toggleBtns = document.querySelectorAll('.dept-toggle-btn');
            toggleBtns.forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    var targetId = btn.getAttribute('data-target');
                    var container = document.getElementById(targetId);
                    if (!container) return;
                    
                    var isVisible = container.style.display !== 'none';
                    if (isVisible) {
                        container.style.display = 'none';
                        btn.querySelector('.toggle-arrow').className = 'bi bi-chevron-down toggle-arrow';
                    } else {
                        container.style.display = 'block';
                        btn.querySelector('.toggle-arrow').className = 'bi bi-chevron-up toggle-arrow';
                        var search = container.querySelector('.dept-search-input');
                        if (search) search.focus();
                    }
                });
            });

            function updateBadge(containerId, badgeId) {
                var container = document.getElementById(containerId);
                var badge = document.getElementById(badgeId);
                if (!container || !badge) return;
                var checkedCount = container.querySelectorAll('.dept-checkbox:checked').length;
                badge.textContent = checkedCount + ' seleccionado' + (checkedCount !== 1 ? 's' : '');
                if (checkedCount > 0) {
                    badge.className = 'badge bg-danger rounded-pill dept-count-badge';
                } else {
                    badge.className = 'badge bg-secondary rounded-pill dept-count-badge';
                }
            }

            // Escuchar cambios en los checkboxes
            document.querySelectorAll('.dept-checkbox').forEach(function (cb) {
                cb.addEventListener('change', function () {
                    updateBadge('createDeptContainer', 'createDeptBadge');
                    updateBadge('editDeptContainer', 'editDeptBadge');
                });
            });

            // Exponer globalmente para actualizar badges cuando cargue la edición y sincronizar visibilidad
            window.updateDeptBadges = function () {
                updateBadge('createDeptContainer', 'createDeptBadge');
                updateBadge('editDeptContainer', 'editDeptBadge');

                // Ajustar visibilidad inicial para crear y editar (ocultar unselected por defecto)
                ['createDeptContainer', 'editDeptContainer'].forEach(function (containerId) {
                    var container = document.getElementById(containerId);
                    if (!container) return;
                    
                    var items = container.querySelectorAll('.dept-check-item');
                    var placeholder = container.querySelector('.dept-placeholder');
                    var visibleCount = 0;
                    
                    items.forEach(function (item) {
                        var cb = item.querySelector('.dept-checkbox');
                        if (cb && cb.checked) {
                            item.style.display = 'block';
                            visibleCount++;
                        } else {
                            item.style.display = 'none';
                        }
                    });
                    
                    if (placeholder) {
                        placeholder.style.display = (visibleCount === 0) ? 'block' : 'none';
                    }
                });
            };

            // Filtrado en vivo de búsqueda
            document.querySelectorAll('.dept-search-input').forEach(function (input) {
                input.addEventListener('input', function () {
                    var q = input.value.toLowerCase().trim();
                    var container = input.closest('.dept-expandable-container');
                    if (!container) return;
                    
                    var placeholder = container.querySelector('.dept-placeholder');
                    var items = container.querySelectorAll('.dept-check-item');
                    var visibleCount = 0;
                    
                    items.forEach(function (item) {
                        var text = (item.querySelector('.form-check-label').textContent || '').toLowerCase();
                        var cb = item.querySelector('.dept-checkbox');
                        
                        if (q === '') {
                            // Si la búsqueda está vacía, solo mostrar los que estén seleccionados
                            if (cb && cb.checked) {
                                item.style.display = 'block';
                                visibleCount++;
                            } else {
                                item.style.display = 'none';
                            }
                        } else {
                            // Si hay búsqueda, mostrar los que coincidan
                            if (text.indexOf(q) !== -1) {
                                item.style.display = 'block';
                                visibleCount++;
                            } else {
                                item.style.display = 'none';
                            }
                        }
                    });
                    
                    if (placeholder) {
                        placeholder.style.display = (visibleCount === 0) ? 'block' : 'none';
                    }
                });
            });

            // Botones Mostrar Todos / Ocultar
            document.querySelectorAll('.dept-select-all').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var container = btn.closest('.dept-expandable-container');
                    if (!container) return;
                    
                    var items = container.querySelectorAll('.dept-check-item');
                    var placeholder = container.querySelector('.dept-placeholder');
                    
                    items.forEach(function (item) {
                        item.style.display = 'block';
                        // No seleccionamos automáticamente, solo mostramos
                    });
                    
                    if (placeholder) {
                        placeholder.style.display = 'none';
                    }
                    
                    updateBadge('createDeptContainer', 'createDeptBadge');
                    updateBadge('editDeptContainer', 'editDeptBadge');
                });
            });

            document.querySelectorAll('.dept-clear-all').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var container = btn.closest('.dept-expandable-container');
                    if (!container) return;
                    
                    // Limpiar el input de búsqueda
                    var search = container.querySelector('.dept-search-input');
                    if (search) search.value = '';

                    // Ocultar los ítems no seleccionados y mantener visibles los seleccionados
                    var items = container.querySelectorAll('.dept-check-item');
                    var visibleCount = 0;
                    
                    items.forEach(function (item) {
                        var cb = item.querySelector('.dept-checkbox');
                        if (cb && cb.checked) {
                            item.style.display = 'block';
                            visibleCount++;
                        } else {
                            item.style.display = 'none';
                        }
                    });
                    
                    var placeholder = container.querySelector('.dept-placeholder');
                    if (placeholder) {
                        placeholder.style.display = (visibleCount === 0) ? 'block' : 'none';
                    }
                    
                    updateBadge('createDeptContainer', 'createDeptBadge');
                    updateBadge('editDeptContainer', 'editDeptBadge');
                });
            });
        }

        // Inicializar widgets al cargar la página
        initDeptWidgets();

        // --- Carga de Datos en modal de Edición ---
        var btns = document.querySelectorAll('.agent-edit-btn');
        if (btns && btns.length) {
            btns.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var id = this.getAttribute('data-id') || '';
                    document.getElementById('edit_id').value = id;
                    document.getElementById('edit_firstname').value = this.getAttribute('data-firstname') || '';
                    document.getElementById('edit_lastname').value = this.getAttribute('data-lastname') || '';
                    document.getElementById('edit_email').value = this.getAttribute('data-email') || '';
                    document.getElementById('edit_username').value = this.getAttribute('data-username') || '';
                    document.getElementById('edit_role').value = this.getAttribute('data-role') || 'agent';
                    
                    var raw = (this.getAttribute('data-dept-ids') || '').toString();
                    var ids = raw.split(',').map(function (s) { return parseInt((s || '').trim(), 10) || 0; }).filter(function (v) { return v > 0; });
                    var box = document.getElementById('edit_dept_box');
                    if (box) {
                        box.querySelectorAll('input[type="checkbox"]').forEach(function (cb) {
                            var v = parseInt(cb.value, 10) || 0;
                            cb.checked = ids.indexOf(v) !== -1;
                        });
                    }
                    
                    // Actualizar el conteo del badge de departamentos
                    if (window.updateDeptBadges) {
                        window.updateDeptBadges();
                    }
                    
                    document.getElementById('editIsActive').checked = (this.getAttribute('data-is-active') || '0') === '1';
                });
            });
        }

        // --- Carga de Datos en modal de Eliminación ---
        var delBtns = document.querySelectorAll('.agent-delete-btn');
        if (delBtns && delBtns.length) {
            delBtns.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var id = this.getAttribute('data-id') || '';
                    var name = this.getAttribute('data-name') || '';
                    var idEl = document.getElementById('delete_id');
                    var nameEl = document.getElementById('delete_agent_name');
                    if (idEl) idEl.value = id;
                    if (nameEl) nameEl.textContent = name || '—';
                });
            });
        }
    })();
</script>
<?php endif; ?>

<?php
$content = ob_get_clean();

require_once 'layout_admin.php';