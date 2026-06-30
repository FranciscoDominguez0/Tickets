<?php
require_once '../config.php';
require_once '../includes/helpers.php';
require_once '../includes/Auth.php';

if ((string) getAppSetting('system.helpdesk_status', 'online') === 'offline') {
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
        $p1 = (string) ($_POST['password'] ?? '');
        $p2 = (string) ($_POST['password2'] ?? '');

        if ($p1 === '' || $p2 === '') {
            $error = 'Debe ingresar la nueva contraseña.';
        } elseif (strlen($p1) < 8) {
            $error = 'La contraseña debe tener al menos 8 caracteres.';
        } elseif ($p1 !== $p2) {
            $error = 'Las contraseñas no coinciden.';
        } else {
            $uid = (int) $resetRow['user_id'];
            $hash = Auth::hash($p1);

            $stmtU = $mysqli->prepare("UPDATE users SET password = ?, status = 'active', updated = NOW() WHERE id = ?");
            if ($stmtU) {
                $stmtU->bind_param('si', $hash, $uid);
                $stmtU->execute();
            }

            $stmtMark = $mysqli->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = ?");
            if ($stmtMark) {
                $rid = (int) $resetRow['id'];
                $stmtMark->bind_param('i', $rid);
                $stmtMark->execute();
            }

            // Invalidar cualquier otro token pendiente para el mismo usuario
            $stmtInvalidate = $mysqli->prepare("UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL");
            if ($stmtInvalidate) {
                $stmtInvalidate->bind_param('i', $uid);
                $stmtInvalidate->execute();
            }

            $_SESSION['login_failed_attempts'] = 0;
            $_SESSION['flash_success'] = 'Contraseña actualizada. Ya puedes iniciar sesión.';
            $_SESSION['flash_email'] = (string) ($resetRow['email'] ?? '');
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
    <link rel="icon" type="image/x-icon"
        href="<?php echo (defined('APP_URL') ? rtrim((string) APP_URL, '/') : ''); ?>/publico/img/favicon.ico">
    <title>Restablecer contraseña - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="scp/css/vendor/bootstrap-icons-1.11.1.css">
    <link rel="stylesheet"
        href="../publico/css/login.css?v=<?php echo (int) (@filemtime(__DIR__ . '/../publico/css/login.css') ?: time()); ?>">
</head>
<?php
$brandLogo = (string) getCompanyLogoUrl('publico/img/vigitec-logo.webp');
$bgMode = (string) getAppSetting('login.background_mode', 'default');
$loginBg = $bgMode === 'custom' ? (string) getBrandAssetUrl('login.background', '') : '';
$bodyStyle = $loginBg !== ''
    ? ('background-color: #f6f7fb; background-image: linear-gradient(135deg, rgba(240, 244, 248, 0.92) 0%, rgba(226, 232, 240, 0.92) 100%), url(' . html($loginBg) . '); background-size: cover, cover; background-position: center, center; background-repeat: no-repeat, no-repeat;')
    : '';

// Dark Mode logic for Reset (Cookie based since user is not logged in)
$isPortalDarkModeEnabled = (string) getAppSetting('portal.dark_mode_enabled', '1') === '1';
// If enabled, default to dark unless cookie explicitly says '0'
if ($isPortalDarkModeEnabled) {
    $isDarkMode = !isset($_COOKIE['client_dark_mode']) || $_COOKIE['client_dark_mode'] === '1';
} else {
    $isDarkMode = false;
}
?>

<body style="<?php echo $bodyStyle; ?>" class="<?php echo $isDarkMode ? 'dark-mode' : ''; ?>">
    <link rel="stylesheet"
        href="css/client_dark.css?v=<?php echo (int) @filemtime(__DIR__ . '/css/client_dark.css'); ?>">
    <div class="support-center-wrapper">
        <div class="support-header">
            <div class="support-header-left">
                <img src="<?php echo html($brandLogo); ?>" alt="VIGITEC PANAMA" class="vigitec-logo">
            </div>
            <div class="support-header-right d-flex align-items-center gap-3">
                <?php if ($isPortalDarkModeEnabled): ?>
                    <button type="button" id="loginDarkModeBtn" class="btn btn-outline-secondary btn-sm"
                        style="border-radius:999px; width:34px; height:34px; padding:0; display:inline-flex; align-items:center; justify-content:center; border-color: rgba(255,255,255,0.15);"
                        title="Alternar modo oscuro">
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
                <h2 class="welcome-title">Restablecer contraseña</h2>
                <p class="welcome-text">Define una nueva contraseña para tu cuenta.</p>
            </div>

            <div class="login-panel login-panel-split">
                <div class="login-panel-left">
                    <div class="login-form-header">
                        <h2 class="login-form-title">Nueva contraseña</h2>
                        <p class="login-form-subtitle">Define una nueva contraseña para tu cuenta.</p>
                    </div>
                    <form method="post" class="login-form">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo html($error); ?></div>
                        <?php endif; ?>
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo html($success); ?></div>
                        <?php endif; ?>

                        <?php if (!$success && $error === ''): ?>
                            <div class="form-group">
                                <label>Correo</label>
                                <input type="email" value="<?php echo htmlspecialchars($resetRow['email'] ?? ''); ?>"
                                    disabled>
                            </div>

                            <div class="form-group">
                                <label for="password">Nueva contraseña</label>
                                <div style="position: relative;">
                                    <input type="password" id="password" name="password" placeholder="Nueva contraseña"
                                        required style="padding-right: 40px; width: 100%; box-sizing: border-box;">
                                    <button type="button" id="togglePasswordBtn1" tabindex="-1"
                                        style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #64748b; padding: 0; display: flex; align-items: center; justify-content: center;">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
                                            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                            stroke-linejoin="round">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                            <circle cx="12" cy="12" r="3"></circle>
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="password2">Confirmar contraseña</label>
                                <div style="position: relative;">
                                    <input type="password" id="password2" name="password2"
                                        placeholder="Confirmar contraseña" required
                                        style="padding-right: 40px; width: 100%; box-sizing: border-box;">
                                    <button type="button" id="togglePasswordBtn2" tabindex="-1"
                                        style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #64748b; padding: 0; display: flex; align-items: center; justify-content: center;">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
                                            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                            stroke-linejoin="round">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                            <circle cx="12" cy="12" r="3"></circle>
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <input type="hidden" name="csrf_token"
                                value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                            <button type="submit" class="btn-login">Guardar contraseña</button>
                        <?php endif; ?>
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
                        <h2 class="login-welcome-title">Establece <span>tu clave</span></h2>
                        <p class="login-welcome-text">Ingresa tu nueva contraseña para volver a acceder de forma segura.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="support-footer">
            <p class="copyright">
                Derechos de autor &copy; <?php echo date('Y'); ?>
                <?php echo htmlspecialchars(getAppSetting('company.name', 'Vigitec Panama')); ?> -
                <?php echo APP_NAME; ?> - Todos los derechos reservados.
            </p>
        </div>
    </div>

    <script>
        // Prevenir submit duplicado
        var form = document.querySelector('form');
        if (form) {
            form.addEventListener('submit', function (e) {
                const btn = this.querySelector('.btn-login');
                if (btn) {
                    if (btn.disabled) {
                        e.preventDefault();
                        return false;
                    }
                    btn.disabled = true;
                    btn.classList.add('loading');
                    btn.textContent = 'Guardando...';
                }
            });
        }

        // Mostrar/Ocultar contraseña
        function setupTogglePassword(btnId, inputId) {
            var btn = document.getElementById(btnId);
            var input = document.getElementById(inputId);
            if (btn && input) {
                btn.addEventListener('click', function () {
                    var isPassword = input.getAttribute('type') === 'password';
                    input.setAttribute('type', isPassword ? 'text' : 'password');
                    if (isPassword) {
                        this.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>';
                    } else {
                        this.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
                    }
                });
            }
        }
        setupTogglePassword('togglePasswordBtn1', 'password');
        setupTogglePassword('togglePasswordBtn2', 'password2');

        // Dark Mode Toggle
        var loginDarkBtn = document.getElementById('loginDarkModeBtn');
        if (loginDarkBtn) {
            loginDarkBtn.addEventListener('click', function () {
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
                d.setTime(d.getTime() + (30 * 24 * 60 * 60 * 1000));
                document.cookie = "client_dark_mode=" + (nextDark ? "1" : "0") + ";expires=" + d.toUTCString() + ";path=/";
            });
        }
    </script>
</body>

</html>