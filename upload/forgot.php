<?php
require_once '../config.php';
require_once '../includes/helpers.php';
require_once '../includes/Auth.php';

if ((string)getAppSetting('system.helpdesk_status', 'online') === 'offline') {
    header('Location: login.php?msg=offline');
    exit;
}

// Generar CSRF token si no existe
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$success = '';
$email = '';

// Tabla de tokens (si no existe)
$mysqli->query(
    "CREATE TABLE IF NOT EXISTS password_resets (\n"
    . "  id INT PRIMARY KEY AUTO_INCREMENT,\n"
    . "  user_id INT NOT NULL,\n"
    . "  token_hash CHAR(64) NOT NULL,\n"
    . "  expires_at DATETIME NOT NULL,\n"
    . "  used_at DATETIME NULL,\n"
    . "  created DATETIME DEFAULT CURRENT_TIMESTAMP,\n"
    . "  KEY idx_user_id (user_id),\n"
    . "  KEY idx_token_hash (token_hash),\n"
    . "  KEY idx_expires (expires_at),\n"
    . "  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE\n"
    . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

if ($_POST) {
    if (!Auth::validateCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido';
    } else {
        $email = trim($_POST['email'] ?? '');
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Ingrese un correo válido';
        } else {
            $stmt = $mysqli->prepare("SELECT id, email, firstname, lastname, status FROM users WHERE email = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $u = $stmt->get_result()->fetch_assoc();

                if ($u) {
                    $token = bin2hex(random_bytes(32));
                    $tokenHash = hash('sha256', $token);
                    $expiresAt = date('Y-m-d H:i:s', time() + 3600);

                    $stmtIns = $mysqli->prepare("INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)");
                    if ($stmtIns) {
                        $uid = (int) $u['id'];
                        $stmtIns->bind_param('iss', $uid, $tokenHash, $expiresAt);
                        $stmtIns->execute();
                    }

                    $resetUrl = rtrim(APP_URL, '/') . '/upload/reset.php?token=' . urlencode($token);
                    $name = trim(($u['firstname'] ?? '') . ' ' . ($u['lastname'] ?? ''));
                    if ($name === '') $name = $u['email'];

                    $subject = 'Recuperar contraseña - ' . APP_NAME;
                    $bodyHtml = '<p>Hola ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ',</p>'
                        . '<p>Recibimos una solicitud para restablecer tu contraseña.</p>'
                        . '<p><a href="' . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . '">Restablecer contraseña</a></p>'
                        . '<p>Este enlace vence en 1 hora.</p>'
                        . '<p>Si no solicitaste este cambio, puedes ignorar este correo.</p>';

                    Mailer::send($u['email'], $subject, $bodyHtml);
                }
            }

            $success = 'Si el correo existe en el sistema, te enviaremos un enlace para restablecer tu contraseña.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar contraseña - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../publico/css/login.css">
</head>
<?php
$brandLogo = (string)getCompanyLogoUrl('publico/img/vigitec-logo.png');
$bgMode = (string)getAppSetting('login.background_mode', 'default');
$loginBg = $bgMode === 'custom' ? (string)getBrandAssetUrl('login.background', '') : '';
$bodyStyle = $loginBg !== ''
    ? ('background-image: linear-gradient(135deg, rgba(240, 244, 248, 0.92) 0%, rgba(226, 232, 240, 0.92) 100%), url(' . html($loginBg) . '); background-size: cover, cover; background-position: center, center; background-repeat: no-repeat, no-repeat;')
    : '';
?>
<body style="<?php echo $bodyStyle; ?>">
    <div class="support-center-wrapper">
        <div class="support-header">
            <div class="support-header-left">
                <img src="<?php echo html($brandLogo); ?>" alt="VIGITEC PANAMA" class="vigitec-logo">
            </div>
            <div class="support-header-right">
                <span class="guest-user">Usuario Invitado</span>
                <span class="header-separator">|</span>
                <a href="login.php" class="header-login-link">Inicia Sesión</a>
            </div>
        </div>

        <div class="support-nav">
            <button class="nav-item active">Inicio Centro de Soporte</button>
        </div>

        <div class="support-content">
            <div class="welcome-section">
                <h2 class="welcome-title">Recuperar contraseña</h2>
                <p class="welcome-text">Ingresa tu correo y te enviaremos un enlace para restablecer tu contraseña.</p>
            </div>

            <div class="login-panel">
                <div class="login-panel-left">
                    <form method="post" class="login-form">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>

                        <div class="form-group">
                            <label for="email">Correo electrónico</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                        </div>

                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                        <button type="submit" class="btn-login">Enviar enlace</button>
                    </form>
                </div>

                <div class="login-panel-right">
                    <div class="login-links">
                        <p class="register-text">
                            Recupera el acceso a tu cuenta mediante el enlace enviado a tu correo.
                        </p>
                    </div>
                    <div class="lock-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <div class="support-footer">
            <p class="copyright">
                Derechos de autor © <?php echo date('Y'); ?> Vigitec Panama - <?php echo APP_NAME; ?> - Todos los derechos reservados.
            </p>
        </div>
    </div>
</body>
</html>
