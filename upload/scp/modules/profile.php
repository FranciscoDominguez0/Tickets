<?php
/**
 * Módulo: Mi perfil
 * Solo lógica: carga datos, procesa POST (actualizar perfil / cambiar contraseña).
 * La vista está en modules/profile-view.inc.php
 */

$profile_errors = [];
$profile_success = '';
$profile_staff = null;
$profile_password_errors = false; // para reabrir el modal si hubo errores al cambiar contraseña
$staff_id = (int) ($_SESSION['staff_id'] ?? 0);
$eid = empresaId();

if ($staff_id <= 0) {
    $profile_errors[] = 'Sesión inválida.';
} else {
    // Cargar datos del agente (todas las columnas disponibles)
    $stmt = $mysqli->prepare('SELECT * FROM staff WHERE id = ? AND empresa_id = ? LIMIT 1');
    $stmt->bind_param('ii', $staff_id, $eid);
    $stmt->execute();
    $result = $stmt->get_result();
    $profile_staff = $result->fetch_assoc();
    if (!$profile_staff) {
        $profile_errors[] = 'Agente no encontrado.';
    }
}

$has_dark_mode_column  = $profile_staff && array_key_exists('dark_mode', $profile_staff);
$has_phone_column      = false;
if ($profile_staff) {
    $chkPhone = $mysqli->query("SHOW COLUMNS FROM staff LIKE 'phone'");
    $has_phone_column = ($chkPhone && $chkPhone->num_rows > 0);
}

// Cargar departamento y rol del agente (solo lectura)
$profile_dept_name = '';
$profile_role_name = '';
if ($profile_staff) {
    $dept_id = (int)($profile_staff['dept_id'] ?? 0);
    if ($dept_id > 0) {
        $stmtD = $mysqli->prepare('SELECT name FROM departments WHERE id = ? LIMIT 1');
        if ($stmtD) {
            $stmtD->bind_param('i', $dept_id);
            $stmtD->execute();
            $dRow = $stmtD->get_result()->fetch_assoc();
            $profile_dept_name = (string)($dRow['name'] ?? '');
        }
    }
    // Buscar nombre amigable del rol desde tabla roles si existe
    $roleRaw = (string)($profile_staff['role'] ?? '');
    $profile_role_name = $roleRaw;
    $resRole = $mysqli->query("SHOW TABLES LIKE 'roles'");
    if ($resRole && $resRole->num_rows > 0) {
        $stmtR = $mysqli->prepare('SELECT name FROM roles WHERE name = ? LIMIT 1');
        if ($stmtR) {
            $stmtR->bind_param('s', $roleRaw);
            $stmtR->execute();
            $rRow = $stmtR->get_result()->fetch_assoc();
            if ($rRow) $profile_role_name = (string)($rRow['name'] ?? $roleRaw);
        }
    }

    // Estadísticas de tickets para el agente
    $profile_total_tickets = 0;
    $profile_open_tickets = 0;
    $stmtT = $mysqli->prepare('SELECT COUNT(*) AS total FROM tickets WHERE staff_id = ? AND empresa_id = ?');
    if ($stmtT) {
        $stmtT->bind_param('ii', $staff_id, $eid);
        $stmtT->execute();
        $resT = $stmtT->get_result()->fetch_assoc();
        $profile_total_tickets = (int)($resT['total'] ?? 0);
    }
    $stmtO = $mysqli->prepare("SELECT COUNT(*) AS open_cnt FROM tickets t INNER JOIN ticket_status ts ON t.status_id = ts.id WHERE t.staff_id = ? AND t.empresa_id = ? AND ts.name IN ('Abierto', 'En Progreso', 'Esperando Usuario')");
    if ($stmtO) {
        $stmtO->bind_param('ii', $staff_id, $eid);
        $stmtO->execute();
        $resO = $stmtO->get_result()->fetch_assoc();
        $profile_open_tickets = (int)($resO['open_cnt'] ?? 0);
    }
}

// ----- Procesar cambio de contraseña (POST desde modal)
if ($profile_staff && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    if (!Auth::validateCSRF($_POST['csrf_token'] ?? '')) {
        $profile_errors[] = 'Token de seguridad inválido.';
        $profile_password_errors = true;
    } else {
        $current = $_POST['current_password'] ?? '';
        $new_pass = $_POST['new_password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';
        if (empty($current) || empty($new_pass) || empty($confirm)) {
            $profile_errors[] = 'Completa todos los campos de contraseña.';
            $profile_password_errors = true;
        } elseif ($new_pass !== $confirm) {
            $profile_errors[] = 'La nueva contraseña y la confirmación no coinciden.';
            $profile_password_errors = true;
        } elseif (strlen($new_pass) < 6) {
            $profile_errors[] = 'La nueva contraseña debe tener al menos 6 caracteres.';
            $profile_password_errors = true;
        } elseif (!Auth::verify($current, $profile_staff['password'])) {
            $profile_errors[] = 'Contraseña actual incorrecta.';
            $profile_password_errors = true;
        } else {
            $hash = Auth::hash($new_pass);
            $up = $mysqli->prepare('UPDATE staff SET password = ?, updated = NOW() WHERE id = ? AND empresa_id = ?');
            $up->bind_param('sii', $hash, $staff_id, $eid);
            if ($up->execute()) {
                $profile_success = 'Contraseña actualizada correctamente.';
                $profile_staff['password'] = $hash;
            } else {
                $profile_errors[] = 'No se pudo actualizar la contraseña.';
                $profile_password_errors = true;
            }
        }
    }
}

// ----- Procesar actualización de perfil (POST del formulario principal)
if ($profile_staff && $_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['action']) || $_POST['action'] !== 'change_password')) {
    if (!Auth::validateCSRF($_POST['csrf_token'] ?? '')) {
        $profile_errors[] = 'Token de seguridad inválido.';
    } else {
        $firstname = trim($_POST['firstname'] ?? '');
        $lastname  = trim($_POST['lastname'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $phone     = isset($_POST['phone']) ? trim($_POST['phone']) : '';
        $dark_mode = isset($_POST['dark_mode']) ? (int)$_POST['dark_mode'] : 0;

        if (empty($firstname)) {
            $profile_errors['firstname'] = 'El nombre es obligatorio.';
        }
        if (empty($lastname)) {
            $profile_errors['lastname'] = 'El apellido es obligatorio.';
        }
        if (empty($email)) {
            $profile_errors['email'] = 'El correo electrónico es obligatorio.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $profile_errors['email'] = 'Correo electrónico no válido.';
        } else {
            $check = $mysqli->prepare('SELECT id FROM staff WHERE email = ? AND id != ? AND empresa_id = ?');
            $check->bind_param('sii', $email, $staff_id, $eid);
            $check->execute();
            if ($check->get_result()->fetch_assoc()) {
                $profile_errors['email'] = 'Ese correo ya está en uso por otro agente.';
            }
        }

        if (empty($profile_errors)) {
            // Construir UPDATE dinámicamente según columnas disponibles
            $setClauses = 'firstname = ?, lastname = ?, email = ?';
            $bindTypes  = 'sss';
            $bindValues = [&$firstname, &$lastname, &$email];

            if ($has_phone_column) {
                $setClauses .= ', phone = ?';
                $bindTypes  .= 's';
                $bindValues[] = &$phone;
            }
            if ($has_dark_mode_column) {
                $setClauses .= ', dark_mode = ?';
                $bindTypes  .= 'i';
                $bindValues[] = &$dark_mode;
            }
            $setClauses .= ', updated = NOW()';
            $bindTypes  .= 'ii';
            $bindValues[] = &$staff_id;
            $bindValues[] = &$eid;

            $stmt = $mysqli->prepare("UPDATE staff SET $setClauses WHERE id = ? AND empresa_id = ?");
            if ($stmt) {
                $stmt->bind_param($bindTypes, ...$bindValues);
                if ($stmt->execute()) {
                    $profile_success = 'Perfil actualizado correctamente.';
                    $profile_staff['firstname'] = $firstname;
                    $profile_staff['lastname']  = $lastname;
                    $profile_staff['email']     = $email;
                    if ($has_phone_column) {
                        $profile_staff['phone'] = $phone;
                    }
                    if ($has_dark_mode_column) {
                        $profile_staff['dark_mode'] = $dark_mode;
                        $_SESSION['scp_dark_mode'] = (string)$dark_mode;
                    }
                    $_SESSION['staff_name']  = $firstname . ' ' . $lastname;
                    $_SESSION['staff_email'] = $email;
                } else {
                    $profile_errors[] = 'No se pudo guardar el perfil.';
                }
            } else {
                $profile_errors[] = 'No se pudo preparar la consulta.';
            }
        }
    }
}// Asegurar que profile_errors sea array (puede tener claves firstname, lastname, email)
if (!is_array($profile_errors)) {
    $profile_errors = $profile_errors ? (array) $profile_errors : [];
}require __DIR__ . '/profile-view.inc.php';
