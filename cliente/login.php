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
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

if ($_POST) {
        // Validar CSRF
        if (!Auth::validateCSRF($_POST['csrf_token'] ?? '')) {
            $error = 'Token de seguridad inválido';
        } else {
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($email) || empty($password)) {
                $error = 'Email y contraseña son requeridos';
            } else {
                $user = Auth::loginUser($email, $password);
                if ($user) {
                    $_SESSION['user_login_time'] = time();
                    $success = 'Login exitoso, redirigiendo...';
                    echo '<script>
                        setTimeout(function() {
                            window.location.href = "index.php";
                        }, 1500);
                    </script>';
                } else {
                    $error = 'Email o contraseña incorrectos';
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
$brandLogo = (string)getCompanyLogoUrl('publico/img/vigitec-logo.png');
$bgMode = (string)getAppSetting('login.background_mode', 'default');
$loginBg = $bgMode === 'custom' ? (string)getBrandAssetUrl('login.background', '') : '';
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
                                value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
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
                            <a href="../agente/login.php" class="agent-link">Acceda aquí</a>
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

            <!-- INFORMACIÓN ADICIONAL -->
            <div class="info-section">
                <p class="info-text">
                    Si es la primera vez que se pone en contacto con nosotros o perdió el número de Ticket, 
                    por favor <a href="#" class="info-link">abra un nuevo Ticket</a>.
                </p>
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
