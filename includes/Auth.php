<?php
/**
 * CLASE DE AUTENTICACIÓN
 * Maneja login, logout y verificación de contraseñas
 */

class Auth {
    /**
     * Hash de contraseña con bcrypt
     */
    public static function hash($password) {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
    }

    /**
     * Verificar contraseña
     */
    public static function verify($password, $hash) {
        return password_verify($password, $hash);
    }

    /**
     * LOGIN USUARIO (CLIENTE)
     * 
     * SQL: SELECT id, email, firstname, lastname, password FROM users WHERE email = ?
     */
    public static function loginUser($email, $password) {
        global $mysqli;
        
        $stmt = $mysqli->prepare('SELECT id, email, firstname, lastname, password FROM users WHERE email = ? AND status = "active"');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if (!$user || !self::verify($password, $user['password'])) {
            return false;
        }

        // Actualizar último login
        $update = $mysqli->prepare('UPDATE users SET last_login = NOW() WHERE id = ?');
        $update->bind_param('i', $user['id']);
        $update->execute();

        // Crear sesión
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_type'] = 'cliente';
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_name'] = $user['firstname'] . ' ' . $user['lastname'];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        return $user;
    }

    /**
     * LOGIN AGENTE (STAFF)
     * 
     * SQL: SELECT id, username, email, firstname, lastname, password FROM staff WHERE username = ?
     */
    public static function loginStaff($username, $password) {
        global $mysqli;
        
        $stmt = $mysqli->prepare('SELECT id, username, email, firstname, lastname, password FROM staff WHERE username = ? AND is_active = 1');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $staff = $result->fetch_assoc();

        if (!$staff || !self::verify($password, $staff['password'])) {
            return false;
        }

        // Actualizar último login
        $update = $mysqli->prepare('UPDATE staff SET last_login = NOW() WHERE id = ?');
        $update->bind_param('i', $staff['id']);
        $update->execute();

        // Crear sesión
        $_SESSION['staff_id'] = $staff['id'];
        $_SESSION['user_type'] = 'agente';
        $_SESSION['staff_email'] = $staff['email'];
        $_SESSION['staff_name'] = $staff['firstname'] . ' ' . $staff['lastname'];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        return $staff;
    }

    /**
     * Verificar si está logueado
     */
    public static function isLoggedIn($type = null) {
        if ($type === 'cliente') return isset($_SESSION['user_id']);
        if ($type === 'agente') return isset($_SESSION['staff_id']);
        return isset($_SESSION['user_id']) || isset($_SESSION['staff_id']);
    }

    /**
     * Logout
     */
    public static function logout() {
        session_destroy();
        header('Location: login.php');
        exit;
    }

    /**
     * Validar CSRF token
     */
    public static function validateCSRF($token) {
        return isset($_SESSION['csrf_token']) && 
               hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Obtener usuario actual
     */
    public static function getCurrentUser() {
        return [
            'id' => $_SESSION['user_id'] ?? $_SESSION['staff_id'] ?? null,
            'type' => $_SESSION['user_type'] ?? null,
            'name' => $_SESSION['user_name'] ?? $_SESSION['staff_name'] ?? null,
            'email' => $_SESSION['user_email'] ?? $_SESSION['staff_email'] ?? null
        ];
    }
}
?>
