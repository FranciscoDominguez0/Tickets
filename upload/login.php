<?php
/**
 * LOGIN CLIENTE
 * Formulario de autenticación para usuarios
 * 
 * SQL: SELECT id, email, firstname, lastname, password FROM users WHERE email = ? AND status = "active"
 */

require_once '../config.php';
require_once '../includes/helpers.php';

// Generar CSRF token si no existe
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$helpdeskStatus = (string)getAppSetting('system.helpdesk_status', 'online');
$offlineNotice = '';
if ($helpdeskStatus === 'offline' || (string)($_GET['msg'] ?? '') === 'offline') {
    $offlineNotice = 'Sistema en mantenimiento.';
}

$loginMsg = (string)($_GET['msg'] ?? '');
if ($loginMsg === 'timeout') {
    $_SESSION['flash_error'] = 'Tu sesión expiró por inactividad. Inicia sesión nuevamente.';
}

// Si ya está logueado, redirigir
if (isset($_SESSION['user_id'])) {
    header('Location: tickets.php');
    exit;
}

$error = '';
$success = '';
$prefillEmail = '';

if (isset($_SESSION['flash_error']) || isset($_SESSION['flash_success']) || isset($_SESSION['flash_email'])) {
    $error = (string)($_SESSION['flash_error'] ?? '');
    $success = (string)($_SESSION['flash_success'] ?? '');
    $prefillEmail = (string)($_SESSION['flash_email'] ?? '');
    unset($_SESSION['flash_error'], $_SESSION['flash_success'], $_SESSION['flash_email']);
}

$supportEmail = (string)getAppSetting('mail.admin_notify_email', defined('ADMIN_NOTIFY_EMAIL') ? (string)ADMIN_NOTIFY_EMAIL : 'cuenta9fran@gmail.com');

if ($_POST) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $prefillEmail = $email;

    if ($helpdeskStatus === 'offline') {
        $_SESSION['flash_email'] = $prefillEmail;
        header('Location: login.php?msg=offline');
        exit;
    }

        if (empty($email) || empty($password)) {
            $_SESSION['flash_error'] = 'Email y contraseña son requeridos';
            $_SESSION['flash_email'] = $prefillEmail;
            header('Location: login.php');
            exit;
        }

        $user = Auth::loginUser($email, $password);
        if ($user) {
            $_SESSION['user_login_time'] = time();
            $_SESSION['user_last_activity'] = time();
            $_SESSION['user_login_ip'] = (string)($_SERVER['REMOTE_ADDR'] ?? '');
            header('Location: tickets.php');
            exit;
        }

        $_SESSION['flash_error'] = (string)(Auth::$lastError ?: 'Email o contraseña incorrectos');
        $_SESSION['flash_email'] = $prefillEmail;
        header('Location: login.php');
        exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="/sistema-tickets/publico/img/vigitec-topbar-mark.png">
    <title>Login - <?php echo APP_NAME; ?></title>
    <?php $loginCssV = (int)(@filemtime(__DIR__ . '/../publico/css/login.css') ?: 1); ?>
    <link rel="stylesheet" href="../publico/css/login.css?v=<?php echo $loginCssV; ?>">
</head>
<?php
$brandLogo = (string)getCompanyLogoUrl('publico/img/vigitec-logo.png');
$bgMode = (string)getAppSetting('login.background_mode', 'default');
$loginBg = $bgMode === 'custom' ? (string)getBrandAssetUrl('login.background', '') : '';
$bodyStyle = $loginBg !== ''
    ? ('background-color: #f6f7fb; background-image: linear-gradient(135deg, rgba(240, 244, 248, 0.92) 0%, rgba(226, 232, 240, 0.92) 100%), url(' . html($loginBg) . '); background-size: cover, cover; background-position: center, center; background-repeat: no-repeat, no-repeat;')
    : '';
?>
<body style="<?php echo $bodyStyle; ?>">
    <div class="support-center-wrapper">
        <!-- HEADER SUPERIOR -->
        <div class="support-header">
            <div class="support-header-left">
                <img src="<?php echo html($brandLogo); ?>" alt="VIGITEC PANAMA" class="vigitec-logo">
            </div>
            <div class="support-header-right">
                <a href="#" class="header-login-link">Acceder</a>
            </div>
        </div>

        <!-- NAVEGACIÓN -->
        <div class="support-nav">
            <button class="nav-item active">Centro de soporte</button>
        </div>

        <!-- CONTENIDO PRINCIPAL -->
        <div class="support-content">
            <div class="welcome-section">
                <h2 class="welcome-title">Iniciar sesión en <?php echo APP_NAME; ?></h2>
                <p class="welcome-text">Para servirle mejor, recomendamos a nuestros clientes registrarse para una cuenta.</p>
            </div>

            <!-- PANEL DE LOGIN -->
            <div class="login-panel login-panel-split">
                <!-- COLUMNA IZQUIERDA - FORMULARIO -->
                <div class="login-panel-left">
                    <div class="login-form-header">
                        <h2 class="login-form-title">Inicia sesión</h2>
                        <p class="login-form-subtitle">Accede a tu cuenta para gestionar tus solicitudes.</p>
                    </div>
                    <form method="post" class="login-form">
                        <!-- Alertas -->
                        <?php if ($offlineNotice): ?>
                            <div class="alert alert-warning" data-alert-static="1"><?php echo html($offlineNotice); ?></div>
                        <?php endif; ?>
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo html($error); ?></div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo html($success); ?></div>
                        <?php endif; ?>

                        <!-- Email -->
                        <div class="form-group">
                            <label for="email">Correo electrónico o nombre de usuario</label>
                            <input 
                                type="email" 
                                id="email" 
                                name="email" 
                                placeholder="Correo electrónico o nombre de usuario"
                                value="<?php echo htmlspecialchars($prefillEmail); ?>"
                                required
                            >
                        </div>

                        <!-- Contraseña -->
                        <div class="form-group">
                            <label for="password">Contraseña</label>
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                placeholder="Contraseña"
                                required
                            >
                        </div>

                        <div class="login-forgot">
                            <a href="forgot.php" class="register-link">Olvidé mi contraseña</a>
                        </div>

                        <!-- CSRF Token -->
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                        <!-- Botón Login -->
                        <button type="submit" class="btn-login">Inicia Sesión</button>

                        <div class="login-side-links">
                            <p class="register-text">
                                ¿Sin cuenta?
                                <a href="registrar.php" class="register-link">Crear cuenta</a>
                            </p>
                            <p class="agent-text">
                                ¿Eres agente?
                                <a href="scp/login.php" class="agent-link">Entrar</a>
                            </p>
                        </div>
                    </form>
                </div>

                <!-- COLUMNA DERECHA - ENLACES E ICONO -->
                <div class="login-panel-right login-panel-right-center">
                    <div class="login-corner-mark" aria-hidden="true">
                        <span class="login-corner-dot"></span>
                        <span class="login-corner-text">
                            <span class="login-corner-text-top">SISTEMA</span>
                            <span class="login-corner-text-bottom">TICKETS</span>
                        </span>
                    </div>
                    <div class="login-welcome">
                        <h2 class="login-welcome-title">Hola, <span>¡bienvenido!</span></h2>
                        <p class="login-welcome-text">Inicia sesión para crear y dar seguimiento a tus solicitudes. Estamos aquí para ayudarte.</p>
                    </div>
                    <div class="lock-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M8 11V8.6C8 6.1 9.9 4.2 12.4 4.2c2.5 0 4.4 1.9 4.4 4.4V11" />
                            <path d="M7.2 11h9.6c1 0 1.9.8 1.9 1.9v5.1c0 1-.8 1.9-1.9 1.9H7.2c-1 0-1.9-.8-1.9-1.9v-5.1c0-1 .8-1.9 1.9-1.9Z" />
                            <path d="M12 14.1v2.8" />
                            <path d="M10.9 14.1a1.1 1.1 0 1 0 2.2 0a1.1 1.1 0 0 0-2.2 0Z" />
                        </svg>
                    </div>
                </div>
            </div>

        </div>

        <!-- FOOTER -->
        <div class="support-footer">
            <p class="copyright">
                Derechos de autor &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(getAppSetting('company.name', 'Vigitec Panama')); ?> - <?php echo APP_NAME; ?> - Todos los derechos reservados.
            </p>
        </div>
    </div>

    <script>
        // Prevenir submit duplicado
        document.querySelector('form').addEventListener('submit', function(e) {
            const btn = this.querySelector('.btn-login');
            if (btn.disabled) {
                e.preventDefault();
                return false;
            }
            btn.disabled = true;
            btn.classList.add('loading');
            btn.textContent = 'Verificando...';
        });

        // Auto-ocultar alertas
        (function () {
            try {
                var alerts = document.querySelectorAll('.login-form .alert:not([data-alert-static="1"])');
                if (!alerts || !alerts.length) return;
                setTimeout(function () {
                    try {
                        alerts.forEach(function (el) {
                            try {
                                el.style.transition = 'opacity 300ms ease';
                                el.style.opacity = '0';
                                setTimeout(function () {
                                    try { el.remove(); } catch (e2) { if (el && el.parentNode) el.parentNode.removeChild(el); }
                                }, 320);
                            } catch (e1) {}
                        });
                    } catch (e3) {}
                }, 4500);
            } catch (e4) {}
        })();
    </script>
</body>
</html>
