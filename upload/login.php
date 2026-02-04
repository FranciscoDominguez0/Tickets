<?php
/**
 * LOGIN CLIENTE
 * Formulario de autenticación para usuarios
 * 
 * SQL: SELECT id, email, firstname, lastname, password FROM users WHERE email = ? AND status = "active"
 */

require_once '../config.php';

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
$isLocked = (int)($_SESSION['login_failed_attempts'] ?? 0) >= 3;

if ($_POST) {
        if ($isLocked) {
            $_SESSION['flash_error'] = 'Demasiados intentos fallidos. Contacte con soporte: ' . $supportEmail;
            header('Location: login.php');
            exit;
        } else {
        // Validar CSRF
        if (!Auth::validateCSRF($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Token de seguridad inválido';
            header('Location: login.php');
            exit;
        } else {
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $prefillEmail = $email;

            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $stmtS = $mysqli->prepare("SELECT status FROM users WHERE email = ? LIMIT 1");
                if ($stmtS) {
                    $stmtS->bind_param('s', $email);
                    $stmtS->execute();
                    $srow = $stmtS->get_result()->fetch_assoc();
                    if ($srow && ($srow['status'] ?? '') === 'banned') {
                        $_SESSION['login_failed_attempts'] = 3;
                        $isLocked = true;
                        $_SESSION['flash_error'] = 'Cuenta bloqueada. Contacte con soporte: ' . $supportEmail;
                        $_SESSION['flash_email'] = $prefillEmail;
                        header('Location: login.php');
                        exit;
                    }
                }
            }

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
                    if ((int)$_SESSION['login_failed_attempts'] >= 3) {
                        $isLocked = true;
                        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            $stmtLock = $mysqli->prepare("UPDATE users SET status = 'banned', updated = NOW() WHERE email = ?");
                            if ($stmtLock) {
                                $stmtLock->bind_param('s', $email);
                                $stmtLock->execute();
                            }
                        }
                        $_SESSION['flash_error'] = 'Demasiados intentos fallidos. Contacte con soporte: ' . $supportEmail;
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
<body>
    <div class="support-center-wrapper">
        <!-- HEADER SUPERIOR -->
        <div class="support-header">
            <div class="support-header-left">
                <img src="../publico/img/vigitec-logo.png" alt="VIGITEC PANAMA" class="vigitec-logo">
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
                        <?php if ($isLocked): ?>
                            <!-- Email (deshabilitado) -->
                            <div class="form-group">
                                <label for="email">Correo electrónico o nombre de usuario</label>
                                <input 
                                    type="email" 
                                    id="email" 
                                    name="email" 
                                    placeholder="Correo electrónico o nombre de usuario"
                                    value="<?php echo htmlspecialchars($prefillEmail); ?>"
                                    disabled
                                >
                            </div>

                            <!-- Contraseña (deshabilitada) -->
                            <div class="form-group">
                                <label for="password">Contraseña</label>
                                <input 
                                    type="password" 
                                    id="password" 
                                    name="password" 
                                    placeholder="Contraseña"
                                    disabled
                                >
                            </div>

                            <!-- Botón Login (deshabilitado) -->
                            <button type="button" class="btn-login" disabled>Inicia Sesión</button>

                            <div style="margin-top:10px; color:#64748b; font-size:0.92rem;">
                                Pídele al administrador que reactive tu usuario desde el panel.
                            </div>

                            <div style="margin-top:10px; color:#334155; font-size:0.92rem;">
                                Soporte: <a href="https://mail.google.com/mail/?view=cm&amp;fs=1&amp;to=<?php echo urlencode($supportEmail); ?>" target="_blank" rel="noopener" style="font-weight:800;"><?php echo htmlspecialchars($supportEmail); ?></a>
                            </div>
                        <?php else: ?>
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
                        <?php endif; ?>
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

            <!-- INFORMACIÓN ADICIONAL -->
            <div class="info-section">
                <p class="info-text">
                    Si es la primera vez que se pone en contacto con nosotros o perdió el número de Ticket, 
                    por favor <a href="open.php" class="info-link">abra un nuevo Ticket</a>.
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

    <?php if ($isLocked): ?>
        <div id="lockout-overlay" style="position:fixed; inset:0; background:rgba(15,23,42,0.55); z-index: 9999; display:flex; align-items:center; justify-content:center; padding: 18px;">
            <div role="dialog" aria-modal="true" style="width: min(560px, 100%); background:#fff; border-radius: 14px; box-shadow: 0 20px 60px rgba(0,0,0,0.35); border: 1px solid rgba(148,163,184,0.5); overflow:hidden;">
                <div style="display:flex; align-items:center; justify-content:space-between; padding: 14px 16px; border-bottom: 1px solid #e2e8f0;">
                    <div style="font-weight: 900; color:#0f172a;">Aviso de seguridad</div>
                    <button type="button" id="lockout-close-x" aria-label="Cerrar" style="appearance:none; border:0; background:transparent; font-size: 20px; line-height: 1; cursor:pointer; padding: 6px 10px; color:#334155;">×</button>
                </div>
                <div style="padding: 16px;">
                    <div style="display:flex; gap: 12px; align-items:flex-start;">
                        <div style="flex:0 0 auto; width: 42px; height: 42px; border-radius: 12px; background:#fef3c7; color:#92400e; display:flex; align-items:center; justify-content:center; font-weight: 900;">!</div>
                        <div style="flex: 1 1 auto;">
                            <div style="font-weight: 900; color:#0f172a; font-size: 1.05rem;">No puedes iniciar sesión por el momento</div>
                            <div style="margin-top:6px; color:#475569; line-height: 1.4;">Por seguridad se detuvieron los intentos después de varios errores. Si necesitas ayuda, contacta a soporte.</div>
                            <div style="margin-top:10px;">
                                <a href="https://mail.google.com/mail/?view=cm&amp;fs=1&amp;to=<?php echo urlencode($supportEmail); ?>" target="_blank" rel="noopener" style="display:inline-block; padding:10px 12px; border-radius: 10px; background:#2563eb; color:#fff; text-decoration:none; font-weight: 800;">Contactar soporte</a>
                                <span style="margin-left:10px; color:#64748b; font-weight: 700;"><?php echo htmlspecialchars($supportEmail); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <div style="padding: 12px 16px; border-top: 1px solid #e2e8f0; display:flex; justify-content:flex-end; gap: 10px;">
                    <button type="button" id="lockout-close" style="padding:10px 14px; border-radius: 10px; border: 1px solid #cbd5e1; background:#fff; color:#0f172a; font-weight: 800; cursor:pointer;">Entendido</button>
                </div>
            </div>
        </div>
    <?php endif; ?>

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

        (function () {
            var ov = document.getElementById('lockout-overlay');
            if (!ov) return;
            function closeOverlay() {
                ov.style.display = 'none';
            }
            var btn1 = document.getElementById('lockout-close');
            var btn2 = document.getElementById('lockout-close-x');
            if (btn1) btn1.addEventListener('click', closeOverlay);
            if (btn2) btn2.addEventListener('click', closeOverlay);
            ov.addEventListener('click', function (e) {
                if (e.target === ov) closeOverlay();
            });
        })();
    </script>
</body>
</html>
