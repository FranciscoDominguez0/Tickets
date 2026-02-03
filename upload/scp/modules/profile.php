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

if ($staff_id <= 0) {
    $profile_errors[] = 'Sesión inválida.';
} else {
    // Cargar datos del agente (todas las columnas disponibles)
    $stmt = $mysqli->prepare('SELECT * FROM staff WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $staff_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $profile_staff = $result->fetch_assoc();
    if (!$profile_staff) {
        $profile_errors[] = 'Agente no encontrado.';
    }
}

$has_signature_column = $profile_staff && array_key_exists('signature', $profile_staff);

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
            $up = $mysqli->prepare('UPDATE staff SET password = ?, updated = NOW() WHERE id = ?');
            $up->bind_param('si', $hash, $staff_id);
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
        $signature = isset($_POST['signature']) ? trim($_POST['signature']) : null;

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
            $check = $mysqli->prepare('SELECT id FROM staff WHERE email = ? AND id != ?');
            $check->bind_param('si', $email, $staff_id);
            $check->execute();
            if ($check->get_result()->fetch_assoc()) {
                $profile_errors['email'] = 'Ese correo ya está en uso por otro agente.';
            }
        }

        if (empty($profile_errors)) {
            if ($has_signature_column) {
                $stmt = $mysqli->prepare('UPDATE staff SET firstname = ?, lastname = ?, email = ?, signature = ?, updated = NOW() WHERE id = ?');
                $stmt->bind_param('ssssi', $firstname, $lastname, $email, $signature, $staff_id);
            } else {
                $stmt = $mysqli->prepare('UPDATE staff SET firstname = ?, lastname = ?, email = ?, updated = NOW() WHERE id = ?');
                $stmt->bind_param('sssi', $firstname, $lastname, $email, $staff_id);
            }
            if ($stmt->execute()) {
                $profile_success = 'Perfil actualizado correctamente.';
                $profile_staff['firstname'] = $firstname;
                $profile_staff['lastname']  = $lastname;
                $profile_staff['email']     = $email;
                if ($has_signature_column) {
                    $profile_staff['signature'] = $signature;
                }
                $_SESSION['staff_name']  = $firstname . ' ' . $lastname;
                $_SESSION['staff_email'] = $email;
            } else {
                $profile_errors[] = 'No se pudo guardar el perfil.';
            }
        }
    }
}// Asegurar que profile_errors sea array (puede tener claves firstname, lastname, email)
if (!is_array($profile_errors)) {
    $profile_errors = $profile_errors ? (array) $profile_errors : [];
}require __DIR__ . '/profile-view.inc.php';
