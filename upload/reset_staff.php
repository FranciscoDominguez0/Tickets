<?php
require_once '../config.php';
require_once '../includes/helpers.php';
require_once '../includes/Auth.php';

if ((string)getAppSetting('system.helpdesk_status', 'online') === 'offline') {
    header('Location: scp/login.php');
    exit;
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$success = '';
$token = trim($_GET['token'] ?? '');
$linkInvalid = false;

global $mysqli;

$mysqli->query(
    "CREATE TABLE IF NOT EXISTS staff_password_resets (\n"
    . "  id INT PRIMARY KEY AUTO_INCREMENT,\n"
    . "  staff_id INT NOT NULL,\n"
    . "  token_hash CHAR(64) NOT NULL,\n"
    . "  expires_at DATETIME NOT NULL,\n"
    . "  used_at DATETIME NULL,\n"
    . "  created DATETIME DEFAULT CURRENT_TIMESTAMP,\n"
    . "  KEY idx_staff_id (staff_id),\n"
    . "  KEY idx_token_hash (token_hash),\n"
    . "  KEY idx_expires (expires_at),\n"
    . "  FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE\n"
    . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    $error = 'Enlace inválido o incompleto.';
    $linkInvalid = true;
}

$tokenHash = $token !== '' ? hash('sha256', $token) : '';

$resetRow = null;
if ($error === '') {
    $stmt = $mysqli->prepare(
        "SELECT pr.id, pr.staff_id, pr.expires_at, pr.used_at, s.email, s.username, s.firstname, s.lastname, s.is_active\n"
        . "FROM staff_password_resets pr\n"
        . "JOIN staff s ON pr.staff_id = s.id\n"
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
        $linkInvalid = true;
    } elseif (!empty($resetRow['used_at'])) {
        $error = 'Este enlace ya fue utilizado.';
        $linkInvalid = true;
    } elseif (strtotime($resetRow['expires_at']) < time()) {
        $error = 'Este enlace ha expirado. Solicita uno nuevo.';
        $linkInvalid = true;
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
            $sid = (int)$resetRow['staff_id'];
            $hash = Auth::hash($p1);

            $stmtU = $mysqli->prepare("UPDATE staff SET password = ?, is_active = 1, updated = NOW() WHERE id = ?");
            if ($stmtU) {
                $stmtU->bind_param('si', $hash, $sid);
                $stmtU->execute();
            }

            $stmtMark = $mysqli->prepare("UPDATE staff_password_resets SET used_at = NOW() WHERE id = ?");
            if ($stmtMark) {
                $rid = (int)$resetRow['id'];
                $stmtMark->bind_param('i', $rid);
                $stmtMark->execute();
            }

            // Invalidar cualquier otro token pendiente para el mismo agente
            $stmtInvalidate = $mysqli->prepare("UPDATE staff_password_resets SET used_at = NOW() WHERE staff_id = ? AND used_at IS NULL");
            if ($stmtInvalidate) {
                $stmtInvalidate->bind_param('i', $sid);
                $stmtInvalidate->execute();
            }

            $_SESSION['flash_success'] = 'Contraseña actualizada. Ya puedes iniciar sesión.';
            $_SESSION['flash_username'] = (string)($resetRow['username'] ?? '');
            header('Location: scp/login.php');
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
    <link rel="icon" type="image/x-icon" href="<?php echo (defined('APP_URL') ? rtrim((string)APP_URL, '/') : ''); ?>/publico/img/favicon.ico">
    <title>Restablecer contraseña (Agente) - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../publico/css/agent-login.css">
    <?php
        $uploadRootAbs = __DIR__;
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
            background-color: #000000 !important;
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
        .agent-input-icon-btn {
            pointer-events: auto !important;
            transition: all 0.2s !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            background: none;
            border: none;
            cursor: pointer;
            padding: 0;
        }
        .agent-input-icon-btn:hover svg {
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
        <a href="login.php" class="back-btn" style="display:inline-flex;align-items:center;gap:8px;padding:10px 14px;background:rgba(255,255,255,0.15);backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,0.2);border-radius:10px;color:#fff;text-decoration:none;font-weight:600;font-size:14px;line-height:1;">
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
                <div class="agent-login-subtext">Restablecer Contraseña (Agente)</div>
            <?php endif; ?>
            
            <form method="post" class="agent-login-form">
                <!-- Alertas -->
                <?php if ($error): ?>
                    <div class="agent-alert agent-alert-danger"><?php echo html($error); ?></div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="agent-alert agent-alert-success"><?php echo html($success); ?></div>
                <?php endif; ?>

                <?php if (!$linkInvalid): ?>
                    <!-- Agente -->
                    <div class="agent-form-group">
                        <label>Agente</label>
                        <input type="text" value="<?php echo htmlspecialchars(($resetRow['firstname'] ?? '') . ' ' . ($resetRow['lastname'] ?? '')); ?>" disabled>
                        <svg class="agent-input-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                    </div>

                    <!-- Nueva contraseña -->
                    <div class="agent-form-group">
                        <label for="password">Nueva contraseña</label>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            placeholder="Nueva contraseña" 
                            required
                            style="padding-right: 40px;"
                        >
                        <button type="button" id="togglePasswordAgent1" class="agent-input-icon agent-input-icon-btn" tabindex="-1">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>

                    <!-- Confirmar contraseña -->
                    <div class="agent-form-group">
                        <label for="password2">Confirmar contraseña</label>
                        <input 
                            type="password" 
                            id="password2" 
                            name="password2" 
                            placeholder="Confirmar contraseña" 
                            required
                            style="padding-right: 40px;"
                        >
                        <button type="button" id="togglePasswordAgent2" class="agent-input-icon agent-input-icon-btn" tabindex="-1">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>

                    <!-- CSRF Token -->
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                    <!-- Botón -->
                    <button type="submit" class="agent-btn-login">Guardar contraseña</button>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Mostrar/Ocultar contraseña
            function setupTogglePassword(btnId, inputId) {
                var btn = document.getElementById(btnId);
                var input = document.getElementById(inputId);
                if (btn && input) {
                    btn.addEventListener('click', function() {
                        var isPassword = input.getAttribute('type') === 'password';
                        input.setAttribute('type', isPassword ? 'text' : 'password');
                        if (isPassword) {
                            this.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>';
                        } else {
                            this.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
                        }
                    });
                }
            }
            setupTogglePassword('togglePasswordAgent1', 'password');
            setupTogglePassword('togglePasswordAgent2', 'password2');

            // Prevenir submit duplicado
            var form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const btn = this.querySelector('.agent-btn-login');
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
