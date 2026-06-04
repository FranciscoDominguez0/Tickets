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
                    // Invalidar tokens pendientes anteriores (dejar un solo enlace activo)
                    $stmtInv = $mysqli->prepare("UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL");
                    if ($stmtInv) {
                        $uid = (int) $u['id'];
                        $stmtInv->bind_param('i', $uid);
                        $stmtInv->execute();
                    }

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

                    $subject = 'Restablecer contraseña - ' . APP_NAME;
                    $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
                    $safeUrl = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8');

                    $bodyHtml = ''
                        . '<div style="font-family:Segoe UI, Tahoma, Arial, sans-serif; background:#f7f8fc; padding:26px;">
                            <div style="max-width:680px; margin:0 auto;">
                                <div style="background:linear-gradient(135deg,#0b1220,#111827); color:#ffffff; border-radius:18px; padding:18px 20px; border:1px solid rgba(255,255,255,.12);">
                                    <div style="font-size:12px; font-weight:900; letter-spacing:.08em; opacity:.92; text-transform:uppercase;">' . htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') . '</div>
                                    <div style="font-size:22px; font-weight:1000; margin-top:4px;">Restablecer contraseña</div>
                                </div>
                                <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:18px; padding:18px 20px; margin-top:12px; box-shadow:0 18px 60px rgba(15,23,42,.10);">
                                    <p style="margin:0 0 10px 0; color:#0f172a; font-size:14px;">Hola <strong>' . $safeName . '</strong>,</p>
                                    <p style="margin:0 0 10px 0; color:#334155; font-size:14px; line-height:1.55;">Recibimos una solicitud para restablecer la contraseña de tu cuenta. Para continuar, haz clic en el siguiente botón:</p>
                                    <p style="margin:14px 0 12px 0;">
                                        <a href="' . $safeUrl . '" style="display:inline-block; background:#111827; color:#ffffff; text-decoration:none; padding:12px 16px; border-radius:999px; font-weight:900;">Restablecer contraseña</a>
                                    </p>
                                    <div style="margin:0 0 10px 0; color:#64748b; font-size:12px; line-height:1.5;">
                                        Este enlace vence en <strong>1 hora</strong> por seguridad.
                                    </div>
                                    <div style="margin:0; color:#64748b; font-size:12px; line-height:1.5;">
                                        Si no solicitaste este cambio, puedes ignorar este correo. Tu contraseña no se modificará.
                                    </div>
                                    <hr style="border:0; border-top:1px solid #e2e8f0; margin:14px 0;">
                                    <div style="color:#94a3b8; font-size:11px; line-height:1.5;">
                                        Si el botón no funciona, copia y pega este enlace en tu navegador:<br>
                                        <span style="word-break:break-all;">' . $safeUrl . '</span>
                                    </div>
                                </div>
                                <div style="text-align:center; color:#94a3b8; font-size:11px; margin-top:10px;">
                                    © ' . date('Y') . ' ' . htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') . '
                                </div>
                            </div>
                        </div>';

                    $bodyText = "Hola $name,\n\n" .
                        "Recibimos una solicitud para restablecer la contraseña de tu cuenta.\n\n" .
                        "Enlace para restablecer contraseña (vence en 1 hora):\n$resetUrl\n\n" .
                        "Si no solicitaste este cambio, puedes ignorar este correo.";

                    if (function_exists('enqueueEmailJob')) {
                        $uidQueue = (int)$u['id'];
                        $currentEmpresaId = function_exists('empresaId') ? empresaId() : 1;
                        enqueueEmailJob($u['email'], $subject, $bodyHtml, $bodyText, ['empresa_id' => $currentEmpresaId, 'context_type' => 'forgot_reset', 'context_id' => $uidQueue]);
                        if (function_exists('triggerEmailQueueWorkerAsync')) {
                            triggerEmailQueueWorkerAsync();
                        }
                    } else {
                        Mailer::send($u['email'], $subject, $bodyHtml, $bodyText);
                    }
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
    <link rel="icon" type="image/x-icon" href="<?php echo (defined('APP_URL') ? rtrim((string)APP_URL, '/') : ''); ?>/publico/img/favicon.ico">
    <title>Recuperar contraseña - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../publico/css/login.css?v=<?php echo (int)(@filemtime(__DIR__ . '/../publico/css/login.css') ?: time()); ?>">
</head>
<?php
$brandLogo = (string)getCompanyLogoUrl('publico/img/vigitec-logo.png');
$bgMode = (string)getAppSetting('login.background_mode', 'default');
$loginBg = $bgMode === 'custom' ? (string)getBrandAssetUrl('login.background', '') : '';
$bodyStyle = $loginBg !== ''
    ? ('background-image: linear-gradient(135deg, rgba(240, 244, 248, 0.92) 0%, rgba(226, 232, 240, 0.92) 100%), url(' . html($loginBg) . '); background-size: cover, cover; background-position: center, center; background-repeat: no-repeat, no-repeat;')
    : '';

// Dark Mode logic for Forgot Password (Cookie based since user is not logged in)
$isPortalDarkModeEnabled = (string)getAppSetting('portal.dark_mode_enabled', '1') === '1';
// If enabled, default to dark unless cookie explicitly says '0'
if ($isPortalDarkModeEnabled) {
    $isDarkMode = !isset($_COOKIE['client_dark_mode']) || $_COOKIE['client_dark_mode'] === '1';
} else {
    $isDarkMode = false;
}
?>
<body style="<?php echo $bodyStyle; ?>" class="<?php echo $isDarkMode ? 'dark-mode' : ''; ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/client_dark.css?v=<?php echo (int)@filemtime(__DIR__ . '/css/client_dark.css'); ?>">
    <div class="support-center-wrapper">
        <div class="support-header">
            <div class="support-header-left">
                <img src="<?php echo html($brandLogo); ?>" alt="VIGITEC PANAMA" class="vigitec-logo">
            </div>
            <div class="support-header-right d-flex align-items-center gap-3">
                <?php if ($isPortalDarkModeEnabled): ?>
                <button type="button" id="loginDarkModeBtn" class="btn btn-outline-secondary btn-sm" style="border-radius:999px; width:34px; height:34px; padding:0; display:inline-flex; align-items:center; justify-content:center; border-color: rgba(255,255,255,0.15);" title="Alternar modo oscuro">
                    <i class="bi <?php echo $isDarkMode ? 'bi-sun' : 'bi-moon-stars'; ?>" style="font-size:16px;"></i>
                </button>
                <?php endif; ?>
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

            <div class="login-panel login-panel-split">
                <div class="login-panel-left">
                    <div class="login-form-header">
                        <h2 class="login-form-title">Recuperar contraseña</h2>
                        <p class="login-form-subtitle">Ingresa tu correo y te enviaremos un enlace para restablecer tu contraseña.</p>
                    </div>
                    <form method="post" class="login-form">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo html($error); ?></div>
                        <?php endif; ?>
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo html($success); ?></div>
                        <?php endif; ?>

                        <div class="form-group">
                            <label for="email">Correo electrónico</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                        </div>

                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                        <button type="submit" class="btn-login">Enviar enlace</button>

                        <div class="login-side-links">
                            <p class="register-text">
                                ¿Recordaste tu contraseña?
                                <a href="login.php" class="register-link">Iniciar sesión</a>
                            </p>
                        </div>
                    </form>
                </div>

                <div class="login-panel-right login-panel-right-center">
                    <div class="login-corner-mark" aria-hidden="true">
                        <span class="login-corner-dot"></span>
                        <span class="login-corner-text">
                            <span class="login-corner-text-top">SISTEMA</span>
                            <span class="login-corner-text-bottom">TICKETS</span>
                        </span>
                    </div>
                    <div class="login-welcome">
                        <h2 class="login-welcome-title">Estás a un paso <span>de volver</span></h2>
                        <p class="login-welcome-text">Sigue las instrucciones y en minutos tendrás acceso otra vez.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="support-footer">
            <p class="copyright">
                Derechos de autor &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(getAppSetting('company.name', 'Vigitec Panama')); ?> - <?php echo APP_NAME; ?> - Todos los derechos reservados.
            </p>
        </div>
    </div>
    <script>
        // Dark Mode Toggle for Forgot
        var loginDarkBtn = document.getElementById('loginDarkModeBtn');
        if (loginDarkBtn) {
            loginDarkBtn.addEventListener('click', function() {
                var isDark = document.body.classList.contains('dark-mode');
                var nextDark = !isDark;
                
                if (nextDark) document.body.classList.add('dark-mode');
                else document.body.classList.remove('dark-mode');
                
                var icon = this.querySelector('i');
                if (icon) {
                    icon.classList.remove('bi-sun', 'bi-moon-stars');
                    icon.classList.add(nextDark ? 'bi-sun' : 'bi-moon-stars');
                }
                
                // Save in cookie (30 days)
                var d = new Date();
                d.setTime(d.getTime() + (30*24*60*60*1000));
                document.cookie = "client_dark_mode=" + (nextDark ? "1" : "0") + ";expires=" + d.toUTCString() + ";path=/";
            });
        }
    </script>
</body>
</html>
