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
                    echo '<style>html,body{height:100%;margin:0}body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Arial,sans-serif;background:radial-gradient(1200px 600px at 20% 10%,rgba(239,68,68,.12),transparent 60%),#09090b;color:#e5e7eb;display:flex;align-items:center;justify-content:center} .box{width:min(520px,92vw);padding:26px 24px;border-radius:18px;background:rgba(9,9,11,0.65);border:1px solid rgba(239,68,68,0.2);backdrop-filter:blur(14px);box-shadow:0 25px 80px rgba(0,0,0,.75),0 0 40px rgba(239,68,68,0.08)} .t{font-weight:800;letter-spacing:.01em;font-size:18px;margin:0 0 6px} .s{margin:0 0 18px;opacity:.85;font-size:13px} .bar{height:6px;border-radius:999px;background:rgba(255,255,255,.06);overflow:hidden} .bar>i{display:block;height:100%;width:30%;background:linear-gradient(90deg,#b91c1c,#ef4444,#f87171);border-radius:999px;animation:mv 1.05s ease-in-out infinite} @keyframes mv{0%{transform:translateX(-120%)}50%{transform:translateX(140%)}100%{transform:translateX(340%)}} .spin{width:36px;height:36px;border-radius:50%;border:3px solid rgba(255,255,255,.06);border-top-color:#ef4444;animation:sp .85s cubic-bezier(0.4,0,0.2,1) infinite;margin:0 0 16px} @keyframes sp{to{transform:rotate(360deg)}} </style>';
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
    <link rel="icon" type="image/x-icon" href="<?php echo (defined('APP_URL') ? rtrim((string)APP_URL, '/') : ''); ?>/publico/img/favicon.ico">
    <title>Login Agente - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../../publico/css/agent-login.css">
    <?php
        $uploadRootAbs = realpath(__DIR__ . '/..');
        $companyLogoRaw = (string)getCompanyLogoUrl('publico/img/vigitec-logo.webp');
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
        :root {
            --accent-rgb: 239, 68, 68;
            --accent-color: rgb(239, 68, 68);
            --accent-hover: rgb(220, 38, 38);
            --accent-glow: rgba(239, 68, 68, 0.35);
            --accent-glow-soft: rgba(239, 68, 68, 0.12);
        }
        
        /* ── Modern Premium Base Styling ── */
        body.agent-login {
            background-color: #09090b !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            min-height: 100vh;
        }
        .agent-login-brand {
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 0 16px;
        }
        .agent-login-subtext {
            text-align: center;
            margin: -6px 0 24px;
            color: rgba(255, 255, 255, 0.95);
            font-weight: 750;
            letter-spacing: 0.02em;
            font-size: 13px;
            text-shadow: 0 2px 10px rgba(0,0,0,0.4);
        }
        .agent-login-brand img {
            height: 58px;
            width: auto;
            max-width: 100%;
            object-fit: contain;
            display: block;
            filter: drop-shadow(0 10px 30px rgba(0,0,0,0.3));
        }

        /* ── Dynamic Intelligent Accent Theme Mapping ── */
        .agent-login-panel {
            background: rgba(9, 9, 11, 0.45) !important;
            backdrop-filter: blur(20px) saturate(140%) !important;
            -webkit-backdrop-filter: blur(20px) saturate(140%) !important;
            border: 1px solid rgba(255, 255, 255, 0.08) !important;
            overflow: hidden;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.6) !important;
            position: relative;
            padding: 50px 40px !important;
        }
        .agent-login-panel:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 35px 70px rgba(0, 0, 0, 0.7) !important;
            border-color: rgba(255, 255, 255, 0.12) !important;
        }
        .agent-login-panel::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--accent-hover), var(--accent-color));
            z-index: 10;
        }
        .agent-btn-login {
            background: linear-gradient(135deg, var(--accent-hover) 0%, var(--accent-color) 100%) !important;
            box-shadow: 0 6px 20px var(--accent-glow-soft), 0 2px 8px rgba(0,0,0,0.2) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
            -webkit-tap-highlight-color: transparent !important;
        }
        .agent-btn-login:hover:not(.loading) {
            transform: translateY(-2px) scale(1.01) !important;
            box-shadow: 0 10px 30px var(--accent-glow), 0 4px 12px var(--accent-glow-soft) !important;
            background: linear-gradient(135deg, var(--accent-color) 0%, var(--accent-hover) 100%) !important;
        }
        .agent-btn-login.loading {
            background: linear-gradient(135deg, var(--accent-color) 0%, var(--accent-hover) 100%) !important;
            background-image: linear-gradient(135deg, var(--accent-color) 0%, var(--accent-hover) 100%) !important;
            box-shadow: 0 6px 20px var(--accent-glow-soft), 0 2px 8px rgba(0,0,0,0.2) !important;
        }
        .agent-btn-login:active {
            transform: translateY(0) !important;
            box-shadow: 0 0 0 3px var(--accent-glow) !important;
        }
        .agent-btn-login:focus, .agent-btn-login:focus-visible {
            outline: none !important;
            box-shadow: 0 0 0 3px var(--accent-glow) !important;
            -webkit-tap-highlight-color: transparent !important;
        }
        .agent-form-group input {
            background: rgba(255, 255, 255, 0.03) !important;
            border-bottom: 2px solid rgba(255, 255, 255, 0.12) !important;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
            border-radius: 8px 8px 0 0 !important;
            padding: 14px 16px 14px 16px !important;
            color: #ffffff !important;
            font-weight: 500 !important;
        }
        .agent-form-group input:focus {
            background: rgba(255, 255, 255, 0.06) !important;
            border-bottom-color: var(--accent-color) !important;
            box-shadow: 0 4px 15px var(--accent-glow-soft) !important;
            transform: none !important;
        }
        .agent-form-group input:focus + .agent-input-icon {
            color: var(--accent-color) !important;
            opacity: 1 !important;
            transform: translateY(-2px) !important;
        }
        #togglePasswordAgent {
            pointer-events: auto !important;
            transition: all 0.2s !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
        }
        #togglePasswordAgent:hover svg {
            color: var(--accent-color) !important;
            opacity: 1 !important;
            transform: scale(1.1) !important;
        }
        .back-btn {
            background: rgba(9, 9, 11, 0.45) !important;
            backdrop-filter: blur(15px) !important;
            -webkit-backdrop-filter: blur(15px) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            transition: all 0.2s !important;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2) !important;
        }
        .back-btn:hover {
            background: var(--accent-color) !important;
            border-color: var(--accent-color) !important;
            box-shadow: 0 4px 15px var(--accent-glow) !important;
            transform: translateY(-1px) !important;
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
                        style="padding-right: 30px;"
                    >
                    <button type="button" id="togglePasswordAgent" class="agent-input-icon" tabindex="-1" style="background: none; border: none; cursor: pointer; padding: 0; pointer-events: auto;">
                        <svg id="eyeIconAgent" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                    </button>
                </div>

                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                <!-- Botón Login -->
                <button type="submit" class="agent-btn-login">Inicia sesión</button>
            </form>
        </div>
    </div>

    <script src="js/login.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var toggleBtn = document.getElementById('togglePasswordAgent');
            var pwdInput = document.getElementById('password');
            if(toggleBtn && pwdInput) {
                toggleBtn.addEventListener('click', function() {
                    var isPassword = pwdInput.getAttribute('type') === 'password';
                    pwdInput.setAttribute('type', isPassword ? 'text' : 'password');
                    if (isPassword) {
                        this.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>';
                    } else {
                        this.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
                    }
                });
            }
        });

        // ── Extractor Inteligente de Colores de Fondo ──
        (function() {
            var bgUrl = <?php echo json_encode($loginBgRaw !== '' ? toAppAbsoluteUrl($loginBgRaw) : toAppAbsoluteUrl('publico/img/agent-background.webp')); ?>;
            
            function extractColors(url) {
                var img = new Image();
                img.crossOrigin = "Anonymous";
                img.onload = function() {
                    var canvas = document.createElement('canvas');
                    canvas.width = 16;
                    canvas.height = 16;
                    var ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, 16, 16);
                    var data;
                    try {
                        data = ctx.getImageData(0, 0, 16, 16).data;
                    } catch(e) {
                        console.log("CORS o error en lectura de píxeles. Usando rojo por defecto.");
                        applyColor({r: 239, g: 68, b: 68});
                        return;
                    }
                    
                    var bestColor = null;
                    var maxVibrancy = -1;
                    
                    for (var i = 0; i < data.length; i += 4) {
                        var r = data[i];
                        var g = data[i+1];
                        var b = data[i+2];
                        var a = data[i+3];
                        if (a < 220) continue; // ignorar píxeles transparentes
                        
                        var max = Math.max(r, g, b);
                        var min = Math.min(r, g, b);
                        var chroma = max - min;
                        
                        // Ignorar colores excesivamente oscuros, claros o grises lavados
                        if (max < 60 || max > 235) continue;
                        if (chroma < 30) continue;
                        
                        var vibrancy = chroma;
                        if (vibrancy > maxVibrancy) {
                            maxVibrancy = vibrancy;
                            bestColor = {r: r, g: g, b: b};
                        }
                    }
                    
                    if (!bestColor) {
                        var sumR = 0, sumG = 0, sumB = 0, count = 0;
                        for (var i = 0; i < data.length; i += 4) {
                            if (data[i+3] > 200) {
                                sumR += data[i];
                                sumG += data[i+1];
                                sumB += data[i+2];
                                count++;
                            }
                        }
                        if (count > 0) {
                            bestColor = {
                                r: Math.round(sumR / count),
                                g: Math.round(sumG / count),
                                b: Math.round(sumB / count)
                            };
                        } else {
                            bestColor = {r: 239, g: 68, b: 68}; // Default
                        }
                    }
                    
                    applyColor(bestColor);
                };
                img.onerror = function() {
                    applyColor({r: 239, g: 68, b: 68});
                };
                img.src = url;
            }
            
            function applyColor(color) {
                var r = color.r;
                var g = color.g;
                var b = color.b;
                
                // Si el color es excesivamente oscuro, aclararlo un poco para que destaque en el botón
                var brightness = (r * 299 + g * 587 + b * 114) / 1000;
                if (brightness < 80) {
                    r = Math.min(255, r + 40);
                    g = Math.min(255, g + 40);
                    b = Math.min(255, b + 40);
                }
                
                var root = document.documentElement;
                root.style.setProperty('--accent-rgb', r + ', ' + g + ', ' + b);
                root.style.setProperty('--accent-color', 'rgb(' + r + ', ' + g + ', ' + b + ')');
                
                // Generar variante hover más oscura o clara según brillo
                var hoverFactor = brightness > 150 ? 0.82 : 1.18;
                var hr = Math.min(255, Math.max(0, Math.round(r * hoverFactor)));
                var hg = Math.min(255, Math.max(0, Math.round(g * hoverFactor)));
                var hb = Math.min(255, Math.max(0, Math.round(b * hoverFactor)));
                
                root.style.setProperty('--accent-hover', 'rgb(' + hr + ', ' + hg + ', ' + hb + ')');
                root.style.setProperty('--accent-glow', 'rgba(' + r + ', ' + g + ', ' + b + ', 0.35)');
                root.style.setProperty('--accent-glow-soft', 'rgba(' + r + ', ' + g + ', ' + b + ', 0.12)');
                
                document.body.style.opacity = '1';
            }
            
            // Ocultar transición inicial mientras se realiza la extracción rápida
            document.body.style.opacity = '0.01';
            document.body.style.transition = 'opacity 0.35s ease';
            
            extractColors(bgUrl);
        })();
    </script>
</body>
</html>
