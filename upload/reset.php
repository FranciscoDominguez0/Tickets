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
$token = trim($_GET['token'] ?? '');

if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    $error = 'Enlace inválido o incompleto.';
}

$tokenHash = $token !== '' ? hash('sha256', $token) : '';

$resetRow = null;
if ($error === '') {
    $stmt = $mysqli->prepare(
        "SELECT pr.id, pr.user_id, pr.expires_at, pr.used_at, u.email, u.firstname, u.lastname, u.status\n"
        . "FROM password_resets pr\n"
        . "JOIN users u ON pr.user_id = u.id\n"
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
            $uid = (int)$resetRow['user_id'];
            $hash = Auth::hash($p1);

            $stmtU = $mysqli->prepare("UPDATE users SET password = ?, status = 'active', updated = NOW() WHERE id = ?");
            if ($stmtU) {
                $stmtU->bind_param('si', $hash, $uid);
                $stmtU->execute();
            }

            $stmtMark = $mysqli->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = ?");
            if ($stmtMark) {
                $rid = (int)$resetRow['id'];
                $stmtMark->bind_param('i', $rid);
                $stmtMark->execute();
            }

            $_SESSION['login_failed_attempts'] = 0;
            $_SESSION['flash_success'] = 'Contraseña actualizada. Ya puedes iniciar sesión.';
            $_SESSION['flash_email'] = (string)($resetRow['email'] ?? '');
            header('Location: login.php');
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
    <title>Restablecer contraseña - <?php echo APP_NAME; ?></title>
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
                <h2 class="welcome-title">Restablecer contraseña</h2>
                <p class="welcome-text">Define una nueva contraseña para tu cuenta.</p>
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

                        <?php if (!$success && $error === ''): ?>
                            <div class="form-group">
                                <label>Correo</label>
                                <input type="email" value="<?php echo htmlspecialchars($resetRow['email'] ?? ''); ?>" disabled>
                            </div>

                            <div class="form-group">
                                <label for="password">Nueva contraseña</label>
                                <input type="password" id="password" name="password" placeholder="Nueva contraseña" required>
                            </div>

                            <div class="form-group">
                                <label for="password2">Confirmar contraseña</label>
                                <input type="password" id="password2" name="password2" placeholder="Confirmar contraseña" required>
                            </div>

                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                            <button type="submit" class="btn-login">Guardar contraseña</button>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="login-panel-right">
                    <div class="login-links">
                        <p class="register-text">
                            Estás actualizando tu contraseña mediante un enlace seguro.
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
