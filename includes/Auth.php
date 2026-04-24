<?php
/**
 * CLASE DE AUTENTICACIÓN
 * Maneja login, logout y verificación de contraseñas
 */

if (!function_exists('addLog')) {
    require_once __DIR__ . '/helpers.php';
}

class Auth {
    public static $lastError = '';

    private static function tableHasColumn($table, $column) {
        global $mysqli;
        $table = (string)$table;
        $column = (string)$column;
        if ($table === '' || $column === '') return false;
        if (!isset($mysqli) || !$mysqli) return false;

        static $cache = [];
        if (isset($cache[$table]) && array_key_exists($column, $cache[$table])) {
            return (bool)$cache[$table][$column];
        }

        $tableEsc = $mysqli->real_escape_string($table);
        $colEsc = $mysqli->real_escape_string($column);
        $sql = "SHOW COLUMNS FROM `{$tableEsc}` LIKE '{$colEsc}'";
        $res = $mysqli->query($sql);
        $ok = ($res && $res->num_rows > 0);
        if (!isset($cache[$table])) $cache[$table] = [];
        $cache[$table][$column] = $ok;
        return $ok;
    }

    private static function sessionIpPrefix($ip) {
        $ip = (string)$ip;
        if ($ip === '') return '';
        if (strpos($ip, ':') !== false) {
            $parts = explode(':', $ip);
            return strtolower(implode(':', array_slice($parts, 0, 4)));
        }
        $parts = explode('.', $ip);
        return implode('.', array_slice($parts, 0, 3));
    }

    private static function sessionFingerprint($userType) {
        $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        
        $bindIp = (string)getAppSetting(($userType === 'agente' ? 'agents' : 'users') . '.bind_session_ip', '0') === '1';
        $ipPrefix = $bindIp ? self::sessionIpPrefix($ip) : 'no-ip';
        
        return hash('sha256', (string)$userType . '|' . $ua . '|' . $ipPrefix);
    }

    private static function sessionFingerprintRelaxed($userType) {
        $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
        $browser = 'unknown';
        if (preg_match('~edg/(\d+)~i', $ua, $m)) {
            $browser = 'edge-' . (string)$m[1];
        } elseif (preg_match('~chrome/(\d+)~i', $ua, $m)) {
            $browser = 'chrome-' . (string)$m[1];
        } elseif (preg_match('~firefox/(\d+)~i', $ua, $m)) {
            $browser = 'firefox-' . (string)$m[1];
        } elseif (preg_match('~version/(\d+).+safari~i', $ua, $m)) {
            $browser = 'safari-' . (string)$m[1];
        } elseif (preg_match('~safari/(\d+)~i', $ua, $m)) {
            $browser = 'safari-' . (string)$m[1];
        }
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        
        $bindIp = (string)getAppSetting(($userType === 'agente' ? 'agents' : 'users') . '.bind_session_ip', '0') === '1';
        $ipPrefix = $bindIp ? self::sessionIpPrefix($ip) : 'no-ip';
        
        return hash('sha256', (string)$userType . '|' . $browser . '|' . $ipPrefix);
    }
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
            static $done = null;
            if ($done !== null) return (bool)$done;
            $sql = "CREATE TABLE IF NOT EXISTS user_login_attempts (\n"
                . "  id INT AUTO_INCREMENT PRIMARY KEY,\n"
                . "  email VARCHAR(255) NOT NULL,\n"
                . "  ip VARCHAR(45) NULL,\n"
                . "  attempts INT NOT NULL DEFAULT 0,\n"
                . "  locked_until DATETIME NULL,\n"
                . "  updated DATETIME NULL,\n"
                . "  UNIQUE KEY uq_user_login_attempts (email, ip)\n"
                . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
            $done = (bool)$mysqli->query($sql);
            return (bool)$done;
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
            self::$lastError = "Cuenta bloqueada.\nIntenta de nuevo en " . $mins . ' minuto(s).';
            if (function_exists('addLog')) {
                addLog(
                    'user_login_locked',
                    'Intento de login durante bloqueo para ' . (string)$email,
                    'auth',
                    null,
                    'cliente',
                    null
                );
            }
            return false;
        }
        
        $hasEmpresaId = self::tableHasColumn('users', 'empresa_id');
        $sql = $hasEmpresaId
            ? 'SELECT id, email, firstname, lastname, password, COALESCE(empresa_id, 1) AS empresa_id FROM users WHERE email = ? AND status = "active"'
            : 'SELECT id, email, firstname, lastname, password FROM users WHERE email = ? AND status = "active"';
        $stmt = $mysqli->prepare($sql);
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
                        self::$lastError = "Cuenta bloqueada.\nIntenta de nuevo en " . (string)$lockoutMin . ' minuto(s).';
                        if (function_exists('addLog')) {
                            addLog(
                                'user_login_lockout',
                                'Cuenta bloqueada por intentos fallidos para ' . (string)$email,
                                'auth',
                                null,
                                'cliente',
                                null
                            );
                        }
                    }
                }
            }
            if (function_exists('addLog')) {
                addLog(
                    'user_login_failed',
                    'Credenciales inválidas para ' . (string)$email,
                    'auth',
                    null,
                    'cliente',
                    null
                );
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
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_regenerate_id(true);
        }
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_type'] = 'cliente';
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_name'] = $user['firstname'] . ' ' . $user['lastname'];
        $_SESSION['empresa_id'] = (int)($user['empresa_id'] ?? 1);
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['session_fp'] = self::sessionFingerprint('cliente');
        $_SESSION['session_fp_relaxed'] = self::sessionFingerprintRelaxed('cliente');

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
            static $done = null;
            if ($done !== null) return (bool)$done;
            $sql = "CREATE TABLE IF NOT EXISTS staff_login_attempts (\n"
                . "  id INT AUTO_INCREMENT PRIMARY KEY,\n"
                . "  username VARCHAR(255) NOT NULL,\n"
                . "  ip VARCHAR(45) NULL,\n"
                . "  attempts INT NOT NULL DEFAULT 0,\n"
                . "  locked_until DATETIME NULL,\n"
                . "  updated DATETIME NULL,\n"
                . "  UNIQUE KEY uq_staff_login_attempts (username, ip)\n"
                . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
            $done = (bool)$mysqli->query($sql);
            return (bool)$done;
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
            self::$lastError = "Cuenta bloqueada.\nIntenta de nuevo en " . $mins . ' minuto(s).';
            if (function_exists('addLog')) {
                addLog(
                    'staff_login_locked',
                    'Intento de login durante bloqueo para ' . (string)$username,
                    'auth',
                    null,
                    'staff',
                    null
                );
            }
            return false;
        }
        
        $hasEmpresaId = self::tableHasColumn('staff', 'empresa_id');
        $hasRole = self::tableHasColumn('staff', 'role');
        if ($hasEmpresaId && $hasRole) {
            $sql = 'SELECT id, username, email, firstname, lastname, password, role, COALESCE(empresa_id, 1) AS empresa_id FROM staff WHERE username = ? AND is_active = 1';
        } elseif ($hasRole) {
            $sql = 'SELECT id, username, email, firstname, lastname, password, role FROM staff WHERE username = ? AND is_active = 1';
        } else {
            $sql = 'SELECT id, username, email, firstname, lastname, password FROM staff WHERE username = ? AND is_active = 1';
        }
        $stmt = $mysqli->prepare($sql);
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

                        if (function_exists('addLog')) {
                            addLog(
                                'staff_login_lockout',
                                'Cuenta bloqueada por intentos fallidos para ' . (string)$username,
                                'auth',
                                null,
                                'staff',
                                null
                            );
                        }
                    }
                }
            }

            if (function_exists('addLog')) {
                addLog(
                    'staff_login_failed',
                    'Credenciales inválidas para ' . (string)$username,
                    'auth',
                    null,
                    'staff',
                    null
                );
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
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_regenerate_id(true);
        }
        $_SESSION['staff_id'] = $staff['id'];
        $_SESSION['user_type'] = 'agente';
        $_SESSION['staff_email'] = $staff['email'];
        $_SESSION['staff_name'] = $staff['firstname'] . ' ' . $staff['lastname'];
        $_SESSION['staff_role'] = (string)($staff['role'] ?? 'agent');
        $_SESSION['empresa_id'] = (int)($staff['empresa_id'] ?? 1);
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['session_fp'] = self::sessionFingerprint('agente');
        $_SESSION['session_fp_relaxed'] = self::sessionFingerprintRelaxed('agente');

        $sid = (int)$staff['id'];
        if ($sid > 0) {
            $since = time();
            if (isset($mysqli) && $mysqli) {
                $q = @$mysqli->query('SELECT UNIX_TIMESTAMP(NOW()) ts');
                if ($q && ($r = $q->fetch_assoc()) && is_numeric($r['ts'] ?? null)) {
                    $since = (int)$r['ts'];
                }
            }
            $_SESSION['tickets_new_since_' . $sid] = $since;
        }

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
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'] ?? '/',
                $params['domain'] ?? '',
                (bool)($params['secure'] ?? false),
                (bool)($params['httponly'] ?? true)
            );
            session_destroy();
        }
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
