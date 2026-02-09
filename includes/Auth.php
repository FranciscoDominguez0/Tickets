<?php
/**
 * CLASE DE AUTENTICACIÓN
 * Maneja login, logout y verificación de contraseñas
 */

class Auth {
    public static $lastError = '';
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
        self::$lastError = '';

        $ensureAttemptsTable = function () use ($mysqli) {
            if (!isset($mysqli) || !$mysqli) return false;
            $sql = "CREATE TABLE IF NOT EXISTS user_login_attempts (\n"
                . "  id INT AUTO_INCREMENT PRIMARY KEY,\n"
                . "  email VARCHAR(255) NOT NULL,\n"
                . "  ip VARCHAR(45) NULL,\n"
                . "  attempts INT NOT NULL DEFAULT 0,\n"
                . "  locked_until DATETIME NULL,\n"
                . "  updated DATETIME NULL,\n"
                . "  UNIQUE KEY uq_user_login_attempts (email, ip)\n"
                . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
            return (bool)$mysqli->query($sql);
        };

        $ensureAttemptsTable();

        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        $maxAttempts = (int)getAppSetting('users.max_login_attempts', '10');
        if ($maxAttempts <= 0) $maxAttempts = 10;
        $lockoutMin = (int)getAppSetting('users.lockout_minutes', '1');
        if ($lockoutMin < 0) $lockoutMin = 0;

        $isLocked = false;
        $remainingSec = 0;
        if (isset($mysqli) && $mysqli && $email !== '') {
            $stmtL = $mysqli->prepare('SELECT locked_until, TIMESTAMPDIFF(SECOND, NOW(), locked_until) AS remaining_sec FROM user_login_attempts WHERE email = ? AND ip = ? LIMIT 1');
            if ($stmtL) {
                $stmtL->bind_param('ss', $email, $ip);
                $stmtL->execute();
                $rowL = $stmtL->get_result()->fetch_assoc();
                if ($rowL && !empty($rowL['locked_until'])) {
                    $remainingSec = (int)($rowL['remaining_sec'] ?? 0);
                    if ($remainingSec <= 0) {
                        $stmtClear = $mysqli->prepare('DELETE FROM user_login_attempts WHERE email = ? AND ip = ?');
                        if ($stmtClear) {
                            $stmtClear->bind_param('ss', $email, $ip);
                            $stmtClear->execute();
                        }
                    } else {
                        $isLocked = true;
                    }
                }
            }
        }

        if ($isLocked) {
            $mins = (int)ceil($remainingSec / 60);
            if ($mins < 1) $mins = 1;
            self::$lastError = 'Cuenta bloqueada por intentos fallidos. Intenta de nuevo en ' . $mins . ' minuto(s).';
            return false;
        }
        
        $stmt = $mysqli->prepare('SELECT id, email, firstname, lastname, password FROM users WHERE email = ? AND status = "active"');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if (!$user || !self::verify($password, $user['password'])) {
            if (isset($mysqli) && $mysqli && $email !== '') {
                $stmtU = $mysqli->prepare('INSERT INTO user_login_attempts (email, ip, attempts, locked_until, updated) VALUES (?, ?, 1, NULL, NOW()) ON DUPLICATE KEY UPDATE attempts = attempts + 1, updated = NOW()');
                if ($stmtU) {
                    $stmtU->bind_param('ss', $email, $ip);
                    $stmtU->execute();
                }

                $stmtG = $mysqli->prepare('SELECT attempts FROM user_login_attempts WHERE email = ? AND ip = ? LIMIT 1');
                if ($stmtG) {
                    $stmtG->bind_param('ss', $email, $ip);
                    $stmtG->execute();
                    $rowG = $stmtG->get_result()->fetch_assoc();
                    $attemptsNow = (int)($rowG['attempts'] ?? 0);
                    if ($lockoutMin > 0 && $attemptsNow >= $maxAttempts) {
                        $stmtLock = $mysqli->prepare('UPDATE user_login_attempts SET locked_until = DATE_ADD(NOW(), INTERVAL ? MINUTE), attempts = 0, updated = NOW() WHERE email = ? AND ip = ?');
                        if ($stmtLock) {
                            $stmtLock->bind_param('iss', $lockoutMin, $email, $ip);
                            $stmtLock->execute();
                        }
                        self::$lastError = 'Cuenta bloqueada por intentos fallidos. Intenta de nuevo en ' . (string)$lockoutMin . ' minuto(s).';
                    }
                }
            }
            return false;
        }

        if (isset($mysqli) && $mysqli && $email !== '') {
            $stmtC = $mysqli->prepare('DELETE FROM user_login_attempts WHERE email = ? AND ip = ?');
            if ($stmtC) {
                $stmtC->bind_param('ss', $email, $ip);
                $stmtC->execute();
            }
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

        self::$lastError = '';

        $ensureAttemptsTable = function () use ($mysqli) {
            if (!isset($mysqli) || !$mysqli) return false;
            $sql = "CREATE TABLE IF NOT EXISTS staff_login_attempts (\n"
                . "  id INT AUTO_INCREMENT PRIMARY KEY,\n"
                . "  username VARCHAR(255) NOT NULL,\n"
                . "  ip VARCHAR(45) NULL,\n"
                . "  attempts INT NOT NULL DEFAULT 0,\n"
                . "  locked_until DATETIME NULL,\n"
                . "  updated DATETIME NULL,\n"
                . "  UNIQUE KEY uq_staff_login_attempts (username, ip)\n"
                . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
            return (bool)$mysqli->query($sql);
        };

        $ensureAttemptsTable();

        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        $maxAttempts = (int)getAppSetting('agents.max_login_attempts', '4');
        if ($maxAttempts <= 0) $maxAttempts = 4;
        $lockoutMin = (int)getAppSetting('agents.lockout_minutes', '2');
        if ($lockoutMin < 0) $lockoutMin = 0;

        $isLocked = false;
        $rowL = null;
        $remainingSec = 0;
        if (isset($mysqli) && $mysqli) {
            $stmtL = $mysqli->prepare('SELECT attempts, locked_until, TIMESTAMPDIFF(SECOND, NOW(), locked_until) AS remaining_sec FROM staff_login_attempts WHERE username = ? AND ip = ? LIMIT 1');
            if ($stmtL) {
                $stmtL->bind_param('ss', $username, $ip);
                $stmtL->execute();
                $rowL = $stmtL->get_result()->fetch_assoc();

                if ($rowL && !empty($rowL['locked_until'])) {
                    $remainingSec = (int)($rowL['remaining_sec'] ?? 0);

                    // Si ya expiró el bloqueo, limpiar y reactivar si fue un lock automático
                    if ($remainingSec <= 0) {
                        $stmtClear = $mysqli->prepare('DELETE FROM staff_login_attempts WHERE username = ? AND ip = ?');
                        if ($stmtClear) {
                            $stmtClear->bind_param('ss', $username, $ip);
                            $stmtClear->execute();
                        }
                        $stmtReact = $mysqli->prepare('UPDATE staff SET is_active = 1 WHERE username = ? AND is_active = 0');
                        if ($stmtReact) {
                            $stmtReact->bind_param('s', $username);
                            $stmtReact->execute();
                        }
                        $rowL = null;
                        $remainingSec = 0;
                    } else {
                        $isLocked = true;
                    }
                }
            }
        }

        if ($isLocked) {
            $mins = (int)ceil($remainingSec / 60);
            if ($mins < 1) $mins = 1;
            self::$lastError = 'Cuenta bloqueada por intentos fallidos. Intenta de nuevo en ' . $mins . ' minuto(s).';
            return false;
        }
        
        $stmt = $mysqli->prepare('SELECT id, username, email, firstname, lastname, password FROM staff WHERE username = ? AND is_active = 1');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $staff = $result->fetch_assoc();

        if (!$staff || !self::verify($password, $staff['password'])) {
            if (isset($mysqli) && $mysqli) {
                $stmtU = $mysqli->prepare('INSERT INTO staff_login_attempts (username, ip, attempts, locked_until, updated) VALUES (?, ?, 1, NULL, NOW()) ON DUPLICATE KEY UPDATE attempts = attempts + 1, updated = NOW()');
                if ($stmtU) {
                    $stmtU->bind_param('ss', $username, $ip);
                    $stmtU->execute();
                }

                $stmtG = $mysqli->prepare('SELECT attempts FROM staff_login_attempts WHERE username = ? AND ip = ? LIMIT 1');
                if ($stmtG) {
                    $stmtG->bind_param('ss', $username, $ip);
                    $stmtG->execute();
                    $rowG = $stmtG->get_result()->fetch_assoc();
                    $attemptsNow = (int)($rowG['attempts'] ?? 0);
                    if ($lockoutMin > 0 && $attemptsNow >= $maxAttempts) {
                        $stmtLock = $mysqli->prepare('UPDATE staff_login_attempts SET locked_until = DATE_ADD(NOW(), INTERVAL ? MINUTE), attempts = 0, updated = NOW() WHERE username = ? AND ip = ?');
                        if ($stmtLock) {
                            $stmtLock->bind_param('iss', $lockoutMin, $username, $ip);
                            $stmtLock->execute();
                        }

                        // Reflejar bloqueo en staff
                        $stmtDeact = $mysqli->prepare('UPDATE staff SET is_active = 0 WHERE username = ?');
                        if ($stmtDeact) {
                            $stmtDeact->bind_param('s', $username);
                            $stmtDeact->execute();
                        }
                    }
                }
            }
            return false;
        }

        if (isset($mysqli) && $mysqli) {
            $stmtC = $mysqli->prepare('DELETE FROM staff_login_attempts WHERE username = ? AND ip = ?');
            if ($stmtC) {
                $stmtC->bind_param('ss', $username, $ip);
                $stmtC->execute();
            }
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
