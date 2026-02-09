<?php
require_once '../config.php';
require_once '../includes/helpers.php';
require_once '../includes/Auth.php';

if ((string)getAppSetting('system.helpdesk_status', 'online') === 'offline') {
    header('Location: scp/login.php');
    exit;
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$success = '';
$token = trim($_GET['token'] ?? '');

global $mysqli;

$mysqli->query(
    "CREATE TABLE IF NOT EXISTS staff_password_resets (\n"
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
    . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    $error = 'Enlace inválido o incompleto.';
}

$tokenHash = $token !== '' ? hash('sha256', $token) : '';

$resetRow = null;
if ($error === '') {
    $stmt = $mysqli->prepare(
        "SELECT pr.id, pr.staff_id, pr.expires_at, pr.used_at, s.email, s.username, s.firstname, s.lastname, s.is_active\n"
        . "FROM staff_password_resets pr\n"
        . "JOIN staff s ON pr.staff_id = s.id\n"
        . "WHERE pr.token_hash = ?\n"
        . "ORDER BY pr.id DESC\n"
        . "LIMIT 1"
    );
    if ($stmt) {
        $stmt->bind_param('s', $tokenHash);
        $stmt->execute();
        $resetRow = $stmt->get_result()->fetch_assoc();
    }

    if (!$resetRow) {
        $error = 'Este enlace no es válido o ya fue utilizado.';
    } elseif (!empty($resetRow['used_at'])) {
        $error = 'Este enlace ya fue utilizado.';
    } elseif (strtotime($resetRow['expires_at']) < time()) {
        $error = 'Este enlace ha expirado. Solicita uno nuevo.';
    }
}

if ($_POST && $error === '') {
    if (!Auth::validateCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido.';
    } else {
        $p1 = (string)($_POST['password'] ?? '');
        $p2 = (string)($_POST['password2'] ?? '');

        if ($p1 === '' || $p2 === '') {
            $error = 'Debe ingresar la nueva contraseña.';
        } elseif (strlen($p1) < 8) {
            $error = 'La contraseña debe tener al menos 8 caracteres.';
        } elseif ($p1 !== $p2) {
            $error = 'Las contraseñas no coinciden.';
        } else {
            $sid = (int)$resetRow['staff_id'];
            $hash = Auth::hash($p1);

            $stmtU = $mysqli->prepare("UPDATE staff SET password = ?, is_active = 1, updated = NOW() WHERE id = ?");
            if ($stmtU) {
                $stmtU->bind_param('si', $hash, $sid);
                $stmtU->execute();
            }

            $stmtMark = $mysqli->prepare("UPDATE staff_password_resets SET used_at = NOW() WHERE id = ?");
            if ($stmtMark) {
                $rid = (int)$resetRow['id'];
                $stmtMark->bind_param('i', $rid);
                $stmtMark->execute();
            }

            $_SESSION['flash_success'] = 'Contraseña actualizada. Ya puedes iniciar sesión.';
            $_SESSION['flash_username'] = (string)($resetRow['username'] ?? '');
            header('Location: scp/login.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer contraseña (Agente) - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../publico/css/agent-login.css">
</head>
<?php
$bgMode = (string)getAppSetting('login.background_mode', 'default');
$loginBg = $bgMode === 'custom' ? (string)getBrandAssetUrl('login.background', '') : '';
$bodyStyle = $loginBg !== '' ? ('background-image:url(' . html($loginBg) . ');') : '';
?>
<body class="agent-login" style="<?php echo $bodyStyle; ?>">
    <div class="agent-login-container">
        <div class="agent-login-panel">
            <h1 class="agent-login-title">RESTABLECER</h1>

            <form method="post" class="agent-login-form">
                <?php if ($error): ?>
                    <div class="agent-alert agent-alert-danger"><?php echo html($error); ?></div>
                <?php endif; ?>

                <?php if (!$error): ?>
                    <div class="agent-form-group">
                        <label>Agente</label>
                        <input type="text" value="<?php echo htmlspecialchars(($resetRow['firstname'] ?? '') . ' ' . ($resetRow['lastname'] ?? '')); ?>" disabled>
                    </div>

                    <div class="agent-form-group">
                        <label for="password">Nueva contraseña</label>
                        <input type="password" id="password" name="password" placeholder="Nueva contraseña" required>
                    </div>

                    <div class="agent-form-group">
                        <label for="password2">Confirmar contraseña</label>
                        <input type="password" id="password2" name="password2" placeholder="Confirmar contraseña" required>
                    </div>

                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <button type="submit" class="agent-btn-login">Guardar contraseña</button>
                <?php endif; ?>
            </form>
        </div>
    </div>
</body>
</html>
