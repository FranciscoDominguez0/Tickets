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

if (!isset($_SESSION['login_failed_attempts'])) {
    $_SESSION['login_failed_attempts'] = 0;
}

$supportEmail = defined('ADMIN_NOTIFY_EMAIL') ? (string) ADMIN_NOTIFY_EMAIL : 'cuenta9fran@gmail.com';

$isLocked = false;
$lockEmail = trim((string)$prefillEmail);
if ($lockEmail !== '' && filter_var($lockEmail, FILTER_VALIDATE_EMAIL)) {
    $stmtS = $mysqli->prepare("SELECT status FROM users WHERE email = ? LIMIT 1");
    if ($stmtS) {
        $stmtS->bind_param('s', $lockEmail);
        $stmtS->execute();
        $srow = $stmtS->get_result()->fetch_assoc();
        if ($srow && ($srow['status'] ?? '') === 'banned') {
            $isLocked = true;
        }
    }
}

if ($isLocked && $error === '') {
    $error = 'Has alcanzado el máximo de intentos fallidos de inicio de sesión.';
}

if ($_POST) {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $prefillEmail = $email;

        // Recalcular bloqueo según el email enviado
        $isLocked = false;
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $stmtS = $mysqli->prepare("SELECT status FROM users WHERE email = ? LIMIT 1");
            if ($stmtS) {
                $stmtS->bind_param('s', $email);
                $stmtS->execute();
                $srow = $stmtS->get_result()->fetch_assoc();
                if ($srow && ($srow['status'] ?? '') === 'banned') {
                    $isLocked = true;
                }
            }
        }

        if ($isLocked) {
            $_SESSION['flash_error'] = 'Has alcanzado el máximo de intentos fallidos de inicio de sesión.';
            $_SESSION['flash_email'] = $prefillEmail;
            addLog('Cuenta bloqueada: intento de inicio de sesión (usuario)', 'email=' . $prefillEmail, 'user', null, 'user', null);
            header('Location: login.php');
            exit;
        } else {
        // Validar CSRF
        if (!Auth::validateCSRF($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Token de seguridad inválido';
            header('Location: login.php');
            exit;
        } else {
            if (!$isLocked && (empty($email) || empty($password))) {
                $_SESSION['flash_error'] = 'Email y contraseña son requeridos';
                $_SESSION['flash_email'] = $prefillEmail;
                header('Location: login.php');
                exit;
            } elseif (!$isLocked) {
                $user = Auth::loginUser($email, $password);
                if ($user) {
                    $_SESSION['login_failed_attempts'] = 0;
                    $_SESSION['user_login_time'] = time();
                    header('Location: tickets.php');
                    exit;
                } else {
                    $_SESSION['login_failed_attempts'] = (int)($_SESSION['login_failed_attempts'] ?? 0) + 1;
                    addLog('Intento fallido de inicio de sesión (usuario)', 'email=' . $prefillEmail . ' attempts=' . (string)$_SESSION['login_failed_attempts'], 'user', null, 'user', null);
                    if ((int)$_SESSION['login_failed_attempts'] >= 3) {
                        $isLocked = true;
                        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            $stmtLock = $mysqli->prepare("UPDATE users SET status = 'banned', updated = NOW() WHERE email = ?");
                            if ($stmtLock) {
                                $stmtLock->bind_param('s', $email);
                                $stmtLock->execute();
                            }
                            $stmtUid = $mysqli->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
                            $uid = null;
                            if ($stmtUid) {
                                $stmtUid->bind_param('s', $email);
                                $stmtUid->execute();
                                $urow = $stmtUid->get_result()->fetch_assoc();
                                $uid = $urow ? (int)$urow['id'] : null;
                            }
                            addLog('Excesivos intentos de identificación (usuario)', 'email=' . $prefillEmail . ' attempts=' . (string)$_SESSION['login_failed_attempts'], 'user', $uid, 'user', $uid);
                        }
                        $_SESSION['flash_error'] = 'Has alcanzado el máximo de intentos fallidos de inicio de sesión.';
                        $_SESSION['flash_email'] = $prefillEmail;
                        header('Location: login.php');
                        exit;
                    } else {
                        $_SESSION['flash_error'] = 'Email o contraseña incorrectos';
                        $_SESSION['flash_email'] = $prefillEmail;
                        header('Location: login.php');
                        exit;
                    }
                }
            }
        }
        }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../publico/css/login.css">
</head>
<?php
$brandLogo = (string)getBrandAssetUrl('company.logo', 'publico/img/vigitec-logo.png');
$loginBg = (string)getBrandAssetUrl('login.background', '');
$bodyStyle = $loginBg !== '' ? ('background-image:url(' . html($loginBg) . '); background-size:cover; background-position:center; background-repeat:no-repeat;') : '';
?>
<body style="<?php echo $bodyStyle; ?>">
    <div class="support-center-wrapper">
        <!-- HEADER SUPERIOR -->
        <div class="support-header">
            <div class="support-header-left">
                <img src="<?php echo html($brandLogo); ?>" alt="VIGITEC PANAMA" class="vigitec-logo">
            </div>
            <div class="support-header-right">
                <span class="guest-user">Usuario Invitado</span>
                <span class="header-separator">|</span>
                <a href="#" class="header-login-link">Inicia Sesión</a>
            </div>
        </div>

        <!-- NAVEGACIÓN -->
        <div class="support-nav">
            <button class="nav-item active">Inicio Centro de Soporte</button>
        </div>

        <!-- CONTENIDO PRINCIPAL -->
        <div class="support-content">
            <div class="welcome-section">
                <h2 class="welcome-title">Iniciar sesión en <?php echo APP_NAME; ?></h2>
                <p class="welcome-text">Para servirle mejor, recomendamos a nuestros clientes registrarse para una cuenta.</p>
            </div>

            <!-- PANEL DE LOGIN -->
            <div class="login-panel">
                <!-- COLUMNA IZQUIERDA - FORMULARIO -->
                <div class="login-panel-left">
                    <form method="post" class="login-form">
                        <!-- Alertas -->
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
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

                        <!-- CSRF Token -->
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                        <!-- Botón Login -->
                        <button type="submit" class="btn-login">Inicia Sesión</button>

                        <div style="margin-top:12px;">
                            <a href="forgot.php" class="register-link">Olvidé mi contraseña</a>
                        </div>
                    </form>
                </div>

                <!-- COLUMNA DERECHA - ENLACES E ICONO -->
                <div class="login-panel-right">
                    <div class="login-links">
                        <p class="register-text">
                            ¿Aún no está registrado? 
                            <a href="registrar.php" class="register-link">Cree una cuenta</a>
                        </p>
                        <p class="agent-text">
                            Soy un agente — 
                            <a href="scp/login.php" class="agent-link">Acceda aquí</a>
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

        <!-- FOOTER -->
        <div class="support-footer">
            <p class="copyright">
                Derechos de autor © <?php echo date('Y'); ?> Vigitec Panama - <?php echo APP_NAME; ?> - Todos los derechos reservados.
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
    </script>
</body>
</html>
