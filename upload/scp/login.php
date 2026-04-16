<?php
/**
 * LOGIN AGENTE
 * Formulario de autenticación para agentes/staff
 * 
 * SQL: SELECT id, username, email, firstname, lastname, password FROM staff WHERE username = ? AND is_active = 1
 */

require_once '../../config.php';
require_once '../../includes/helpers.php';

// Generar CSRF token si no existe
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Si ya está logueado, redirigir
if (isset($_SESSION['staff_id'])) {
    if ((string)($_SESSION['staff_role'] ?? '') === 'superadmin') {
        header('Location: superadmin/index.php');
    } else {
        header('Location: index.php');
    }
    exit;
}

$error = '';
$success = '';

$loginMsg = (string)($_GET['msg'] ?? '');
if ($loginMsg === 'timeout') {
    $error = 'Tu sesión expiró por inactividad';
} elseif ($loginMsg === 'ip') {
    $error = 'Tu sesión se cerró por cambio de IP. Inicia sesión nuevamente.';
}

if (!empty($_SESSION['flash_error'])) {
    $error = (string)$_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}
if (!empty($_SESSION['flash_success'])) {
    $success = (string)$_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

$prefillUser = '';
if (!empty($_SESSION['flash_username'])) {
    $prefillUser = (string)$_SESSION['flash_username'];
    unset($_SESSION['flash_username']);
}

if ($_POST) {
        // Validar CSRF
        if (!Auth::validateCSRF($_POST['csrf_token'] ?? '')) {
            $error = 'Token de seguridad inválido';
        } else {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($username) || empty($password)) {
                $error = 'Usuario y contraseña son requeridos';
            } else {
                $staff = Auth::loginStaff($username, $password);
                if ($staff) {
                    $_SESSION['user_login_time'] = time();
                    $_SESSION['staff_last_activity'] = time();
                    $_SESSION['staff_login_ip'] = (string)($_SERVER['REMOTE_ADDR'] ?? '');
                    $_SESSION['show_agent_loading_overlay'] = 1;
                    $redirectTo = ((string)($_SESSION['staff_role'] ?? '') === 'superadmin')
                        ? 'superadmin/index.php'
                        : 'index.php';
                    header('Content-Type: text/html; charset=utf-8');
                    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Ingresando...</title>';
                    echo '<style>html,body{height:100%;margin:0}body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Arial,sans-serif;background:radial-gradient(1200px 600px at 20% 10%,rgba(99,102,241,.25),transparent 60%),radial-gradient(900px 500px at 90% 80%,rgba(34,197,94,.18),transparent 55%),#0b1220;color:#e5e7eb;display:flex;align-items:center;justify-content:center} .box{width:min(520px,92vw);padding:26px 22px;border-radius:18px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);backdrop-filter:blur(14px);box-shadow:0 20px 70px rgba(0,0,0,.45)} .t{font-weight:800;letter-spacing:.01em;font-size:18px;margin:0 0 6px} .s{margin:0 0 16px;opacity:.85;font-size:13px} .bar{height:10px;border-radius:999px;background:rgba(255,255,255,.10);overflow:hidden} .bar>i{display:block;height:100%;width:30%;background:linear-gradient(90deg,#60a5fa,#a78bfa,#34d399);border-radius:999px;animation:mv 1.05s ease-in-out infinite} @keyframes mv{0%{transform:translateX(-120%)}50%{transform:translateX(140%)}100%{transform:translateX(340%)}} .spin{width:34px;height:34px;border-radius:50%;border:3px solid rgba(255,255,255,.18);border-top-color:rgba(255,255,255,.82);animation:sp .85s linear infinite;margin:0 0 16px} @keyframes sp{to{transform:rotate(360deg)}} </style>';
                    echo '</head><body><div class="box"><div class="spin"></div><p class="t">Ingresando...</p><p class="s">Verificando acceso y cargando tu panel</p><div class="bar"><i></i></div></div>';
                    echo '<script>(function(){var u=' . json_encode($redirectTo) . ';setTimeout(function(){try{window.location.replace(u);}catch(e){window.location.href=u;}},250);})();</script>';
                    echo '</body></html>';
                    exit;
                } else {
                    $error = (string)(Auth::$lastError ?: 'Usuario o contraseña incorrectos');
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
    <link rel="icon" type="image/png" href="/sistema-tickets/publico/img/vigitec-topbar-mark.png">
    <title>Login Agente - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../../publico/css/agent-login.css">
    <?php
        $uploadRootAbs = realpath(__DIR__ . '/..');
        $companyLogoRaw = (string)getCompanyLogoUrl('publico/img/vigitec-logo.png');
        $companyLogoV = 1;
        $pLogo = parse_url($companyLogoRaw, PHP_URL_PATH);
        if (is_string($pLogo) && $pLogo !== '' && is_string($uploadRootAbs) && $uploadRootAbs !== '') {
            $pos = strpos($pLogo, '/upload/');
            if ($pos !== false) {
                $rel = substr($pLogo, $pos + 8);
                $fs = rtrim($uploadRootAbs, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($rel, '/'));
                if (is_file($fs)) {
                    $companyLogoV = (int)@filemtime($fs);
                    if ($companyLogoV <= 0) $companyLogoV = 1;
                }
            }
        }
        $companyLogo = $companyLogoRaw . (strpos($companyLogoRaw, '?') !== false ? '&' : '?') . 'v=' . (string)$companyLogoV;

        $bgMode = (string)getAppSetting('login.background_mode', 'default');
        $loginBgRaw = $bgMode === 'custom' ? (string)getBrandAssetUrl('login.background', '') : '';
        $loginBgV = 1;
        $pBg = parse_url($loginBgRaw, PHP_URL_PATH);
        if (is_string($pBg) && $pBg !== '' && is_string($uploadRootAbs) && $uploadRootAbs !== '') {
            $pos = strpos($pBg, '/upload/');
            if ($pos !== false) {
                $rel = substr($pBg, $pos + 8);
                $fs = rtrim($uploadRootAbs, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($rel, '/'));
                if (is_file($fs)) {
                    $loginBgV = (int)@filemtime($fs);
                    if ($loginBgV <= 0) $loginBgV = 1;
                }
            }
        }
        $loginBg = $loginBgRaw !== '' ? ($loginBgRaw . (strpos($loginBgRaw, '?') !== false ? '&' : '?') . 'v=' . (string)$loginBgV) : '';
    ?>
    <?php if ($companyLogo !== ''): ?>
        <link rel="preload" as="image" href="<?php echo html($companyLogo); ?>">
    <?php endif; ?>
    <?php if ($loginBg !== ''): ?>
        <link rel="preload" as="image" href="<?php echo html($loginBg); ?>">
    <?php endif; ?>
    <style>
        .agent-login-brand {
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 0 16px;
        }
        .agent-login-subtext {
            text-align: center;
            margin: -6px 0 18px;
            color: rgba(255, 255, 255, 0.92);
            font-weight: 650;
            letter-spacing: 0.02em;
            font-size: 13px;
            text-shadow: 0 2px 10px rgba(0,0,0,0.35);
        }
        .agent-login-brand img {
            height: 54px;
            width: auto;
            max-width: 100%;
            object-fit: contain;
            display: block;
            filter: drop-shadow(0 10px 30px rgba(0,0,0,0.22));
        }
    </style>
</head>
<?php
$bodyStyle = $loginBg !== '' ? ('background-image:url(' . html($loginBg) . ');') : '';
?>
<body class="agent-login" style="<?php echo $bodyStyle; ?>">
    <div class="back-to-user-login" style="position:fixed;top:10px;left:10px;z-index:9999;">
        <a href="../login.php" class="back-btn" style="display:inline-flex;align-items:center;gap:8px;padding:10px 14px;background:rgba(255,255,255,0.15);backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,0.2);border-radius:10px;color:#fff;text-decoration:none;font-weight:600;font-size:14px;line-height:1;">
            <svg width="16" height="16" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:block;flex:0 0 auto;">
                <path d="M19 12H5M12 19l-7-7 7-7"/>
            </svg>
            <span>Login de Cliente</span>
        </a>
    </div>

    <!-- CONTENEDOR PRINCIPAL -->
    <div class="agent-login-container">
        <!-- PANEL DE LOGIN (GLASSMORPHISM) -->
        <div class="agent-login-panel">
            <?php if ($companyLogo !== ''): ?>
                <div class="agent-login-brand">
                    <img src="<?php echo html($companyLogo); ?>" alt="<?php echo html((string)getAppSetting('company.name', APP_NAME)); ?>" loading="eager" fetchpriority="high" decoding="async">
                </div>
                <div class="agent-login-subtext">Acceso de Agentes</div>
            <?php endif; ?>
            <form method="post" class="agent-login-form">
                <!-- Alertas -->
                <?php if ($error): ?>
                    <div class="agent-alert agent-alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="agent-alert agent-alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <!-- Usuario -->
                <div class="agent-form-group">
                    <label for="username">Usuario</label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        placeholder="Usuario"
                        value="<?php echo htmlspecialchars($_POST['username'] ?? ($prefillUser ?: '')); ?>"
                        required
                    >
                    <svg class="agent-input-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                </div>

                <!-- Contraseña -->
                <div class="agent-form-group">
                    <label for="password">Contraseña</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        placeholder="Contraseña"
                        required
                    >
                    <svg class="agent-input-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                </div>

                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                <!-- Botón Login -->
                <button type="submit" class="agent-btn-login">Inicia sesión</button>
            </form>
        </div>
    </div>

    <script src="js/login.js"></script>
</body>
</html>
