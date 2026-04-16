<?php
// Layout principal del panel de agente
// Header + sidebar fijos, contenido dinámico en $content
?>
<?php
$notifCount = 0;
$notifItems = [];
if (isset($mysqli) && $mysqli && isset($_SESSION['staff_id'])) {
    $sid = (int) $_SESSION['staff_id'];

    $cacheKey = 'notif_cache_' . $sid;
    $cacheTsKey = 'notif_cache_ts_' . $sid;
    $cacheTs = (int)($_SESSION[$cacheTsKey] ?? 0);
    if ($cacheTs > 0 && (time() - $cacheTs) < 10 && isset($_SESSION[$cacheKey]) && is_array($_SESSION[$cacheKey])) {
        $payload = $_SESSION[$cacheKey];
        $notifCount = (int)($payload['count'] ?? 0);
        $notifItems = is_array(($payload['items'] ?? null)) ? $payload['items'] : [];
    } else {
        $stmtN = $mysqli->prepare('SELECT COUNT(*) c FROM notifications WHERE staff_id = ? AND is_read = 0');
        if ($stmtN) {
            $stmtN->bind_param('i', $sid);
            if ($stmtN->execute()) {
                $notifCount = (int) (($stmtN->get_result()->fetch_assoc()['c'] ?? 0));
            }
        }

        $stmtL = $mysqli->prepare('SELECT id, message, type, related_id, created_at FROM notifications WHERE staff_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 8');
        if ($stmtL) {
            $stmtL->bind_param('i', $sid);
            if ($stmtL->execute()) {
                $res = $stmtL->get_result();
                while ($row = $res->fetch_assoc()) {
                    $notifItems[] = $row;
                }
            }
        }

        $_SESSION[$cacheKey] = ['count' => $notifCount, 'items' => $notifItems];
        $_SESSION[$cacheTsKey] = time();
    }
}

// Estado inicial del sidebar: persistido por cookie, sin auto-toggle al cargar páginas.
$sidebarCookieState = isset($_COOKIE['scp_sidebar_collapsed']) ? (string)$_COOKIE['scp_sidebar_collapsed'] : '';
$sidebarDefaultCollapsed = ($sidebarCookieState === 'collapsed');

$collapseSidebarMenu = false;
$menuKey = 'agent_sidebar_menu_seen_' . (int)($_SESSION['staff_id'] ?? 0);
if ((string)($_SESSION['sidebar_panel_mode'] ?? '') !== 'agent') {
    unset($_SESSION[$menuKey]);
    $_SESSION['sidebar_panel_mode'] = 'agent';
}
if (!isset($_SESSION[$menuKey])) {
    $_SESSION[$menuKey] = 1;
    $collapseSidebarMenu = true;
}

$allowExpandedGroups = (!$sidebarDefaultCollapsed && !$collapseSidebarMenu);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="/sistema-tickets/publico/img/favicon.ico">
    <title>Panel Agente - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/scp.css?v=<?php echo (int)@filemtime(__DIR__ . '/../css/scp.css'); ?>">
    <?php if (isset($currentRoute) && $currentRoute === 'dashboard'): ?>
    <link rel="stylesheet" href="css/dashboard.css?v=<?php echo (int)@filemtime(__DIR__ . '/../css/dashboard.css'); ?>">
    <?php endif; ?>
    <?php if (isset($currentRoute) && $currentRoute === 'profile'): ?>
    <link rel="stylesheet" href="css/profile.css?v=<?php echo (int)@filemtime(__DIR__ . '/../css/profile.css'); ?>">
    <?php endif; ?>
    <?php if (isset($currentRoute) && $currentRoute === 'users'): ?>
    <link rel="stylesheet" href="css/users.css?v=<?php echo (int)@filemtime(__DIR__ . '/../css/users.css'); ?>">
    <?php endif; ?>
    <?php if (isset($currentRoute) && $currentRoute === 'tickets'): ?>
    <link rel="stylesheet" href="css/tickets.css?v=<?php echo (int)@filemtime(__DIR__ . '/../css/tickets.css'); ?>">
    <?php endif; ?>
    <?php if (isset($currentRoute) && $currentRoute === 'orgs'): ?>
    <link rel="stylesheet" href="css/orgs.css?v=<?php echo (int)@filemtime(__DIR__ . '/../css/orgs.css'); ?>">
    <?php endif; ?>
    <?php if (isset($currentRoute) && $currentRoute === 'tasks'): ?>
    <link rel="stylesheet" href="css/tasks.css?v=<?php echo (int)@filemtime(__DIR__ . '/../css/tasks.css'); ?>">
    <?php endif; ?>
</head>
<?php $userActiveTab = (isset($currentRoute) && $currentRoute === 'users') ? (isset($_GET['t']) ? htmlspecialchars($_GET['t'], ENT_QUOTES, 'UTF-8') : 'tickets') : ''; ?>
<body class="scp-panel<?php echo $sidebarDefaultCollapsed ? ' sidebar-collapsed' : ''; ?>" data-sidebar-default="<?php echo $sidebarDefaultCollapsed ? 'collapsed' : 'expanded'; ?>"<?php if ($userActiveTab !== ''): ?> data-user-active-tab="<?php echo $userActiveTab; ?>"<?php endif; ?>>
    <?php $showOverlay = !empty($_SESSION['show_agent_loading_overlay']); ?>
    <?php if ($showOverlay): ?>
        <style>
            #scp-agent-loading {
                position: fixed;
                inset: 0;
                background: rgba(11, 18, 32, 0.78);
                backdrop-filter: blur(6px);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 2500;
                padding: 18px;
            }
            #scp-agent-loading .box {
                width: min(520px, 92vw);
                padding: 22px 20px;
                border-radius: 18px;
                background: rgba(255,255,255,.06);
                border: 1px solid rgba(255,255,255,.12);
                box-shadow: 0 20px 70px rgba(0,0,0,.45);
                color: #e5e7eb;
                font-family: system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Arial,sans-serif;
            }
            #scp-agent-loading .spin {
                width: 34px;
                height: 34px;
                border-radius: 50%;
                border: 3px solid rgba(255,255,255,.18);
                border-top-color: rgba(255,255,255,.82);
                animation: scpAgentSpin .85s linear infinite;
                margin: 0 0 14px;
            }
            #scp-agent-loading .t {
                font-weight: 800;
                letter-spacing: .01em;
                font-size: 18px;
                margin: 0 0 6px;
            }
            #scp-agent-loading .s {
                margin: 0 0 16px;
                opacity: .85;
                font-size: 13px;
            }
            #scp-agent-loading .bar {
                height: 10px;
                border-radius: 999px;
                background: rgba(255,255,255,.10);
                overflow: hidden;
            }
            #scp-agent-loading .bar>i {
                display: block;
                height: 100%;
                width: 30%;
                background: linear-gradient(90deg,#60a5fa,#a78bfa,#34d399);
                border-radius: 999px;
                animation: scpAgentMv 1.05s ease-in-out infinite;
            }
            @keyframes scpAgentSpin { to { transform: rotate(360deg); } }
            @keyframes scpAgentMv { 0%{transform:translateX(-120%)}50%{transform:translateX(140%)}100%{transform:translateX(340%)} }
        </style>
        <div id="scp-agent-loading" aria-hidden="false">
            <div class="box">
                <div class="spin" aria-hidden="true"></div>
                <p class="t">Cargando panel...</p>
                <p class="s">Espera un momento, estamos preparando todo</p>
                <div class="bar" aria-hidden="true"><i></i></div>
            </div>
        </div>
        <script>
            (function(){
                function hide(){
                    var el = document.getElementById('scp-agent-loading');
                    if (!el) return;
                    el.style.opacity = '0';
                    el.style.transition = 'opacity 180ms ease';
                    window.setTimeout(function(){
                        if (el && el.parentNode) el.parentNode.removeChild(el);
                    }, 220);
                }
                window.addEventListener('load', function(){ hide(); }, { once: true });
                window.setTimeout(function(){ hide(); }, 15000);
            })();
        </script>
        <?php unset($_SESSION['show_agent_loading_overlay']); ?>
    <?php endif; ?>
    <!-- NAVBAR -->
    <nav class="navbar navbar-dark" style="position: fixed; top: 0; left: 0; width: 100%; z-index: 1001; flex-direction: column; align-items: stretch; padding: 0;">
        <div class="container-fluid d-flex flex-nowrap w-100 justify-content-between" style="padding-top: 8px; padding-bottom: 8px;">
            <div class="d-flex align-items-center gap-2">
                <button class="btn scp-menu-toggle px-1" id="scpSidebarToggle" type="button" aria-label="Alternar menú lateral" aria-expanded="<?php echo $sidebarDefaultCollapsed ? 'false' : 'true'; ?>" style="color: rgba(255,255,255,.9);">
                    <i class="bi bi-list" style="font-size: 1.4rem;"></i>
                </button>
                <span class="navbar-brand scp-brand-title m-0">Sistema de Tickets</span>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="dropdown">
                    <button class="btn position-relative scp-notif-btn scp-notif-toggle <?php echo $notifCount > 0 ? 'has-new' : ''; ?>" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Notificaciones">
                        <i class="bi bi-bell"></i>
                        <?php if ($notifCount > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                <?php echo (int) $notifCount; ?>
                            </span>
                        <?php endif; ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end scp-notif-menu">
                        <li>
                            <div class="scp-notif-head">
                                <div class="scp-notif-title">Notificaciones</div>
                                <div class="scp-notif-sub"><?php echo $notifCount > 0 ? ((int)$notifCount . ' nueva(s)') : 'Sin nuevas'; ?></div>
                            </div>
                        </li>
                        <?php if (empty($notifItems)): ?>
                            <li><div class="scp-notif-empty">No tienes notificaciones nuevas.</div></li>
                        <?php else: ?>
                            <?php foreach ($notifItems as $n): ?>
                                <?php
                                $t = (string)($n['type'] ?? 'general');
                                $icon = 'bi-info-circle';
                                $accent = 'general';
                                if ($t === 'ticket_assigned') {
                                    $icon = 'bi-ticket-perforated';
                                    $accent = 'ticket';
                                } elseif ($t === 'task_assigned') {
                                    $icon = 'bi-check2-square';
                                    $accent = 'task';
                                }
                                ?>
                                <li>
                                    <a class="dropdown-item scp-notif-item" href="notification_read.php?id=<?php echo (int) $n['id']; ?>">
                                        <div class="scp-notif-icon <?php echo html($accent); ?>">
                                            <i class="bi <?php echo html($icon); ?>"></i>
                                        </div>
                                        <div class="scp-notif-body">
                                            <div class="scp-notif-msg"><?php echo html((string)($n['message'] ?? 'Notificación')); ?></div>
                                            <div class="scp-notif-time"><?php echo html(formatDate($n['created_at'] ?? null)); ?></div>
                                        </div>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>

                <?php $roleName = function_exists('getCurrentStaffRoleName') ? (string)getCurrentStaffRoleName() : (string)($staff['role'] ?? ''); ?>
                <?php if (in_array($roleName, ['admin', 'supervisor'], true)): ?>
                    <a href="settings.php?t=pages" class="scp-admin-pill scp-admin-pill-lg d-none d-md-inline-flex">Administrador</a>
                <?php endif; ?>
                <div class="dropdown">
                    <?php
                    $staffName = (string)($staff['name'] ?? '');
                    $parts = preg_split('/\s+/', trim($staffName));
                    $i1 = strtoupper((string)($parts[0][0] ?? ''));
                    $i2 = '';
                    if (count($parts) > 1) {
                        $i2 = strtoupper((string)($parts[1][0] ?? ''));
                    } elseif (strlen($staffName) > 1) {
                        $i2 = strtoupper(substr($staffName, 1, 1));
                    }
                    $initials = trim($i1 . $i2);
                    if ($initials === '') {
                        $initials = 'U';
                    }
                    ?>
                    <button class="dropdown-toggle scp-profile-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="scp-profile-avatar" aria-hidden="true"><?php echo html($initials); ?></span>
                        <span class="scp-profile-name"><?php echo html($staffName !== '' ? $staffName : 'Perfil'); ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end scp-profile-menu">
                        <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person"></i>Mi perfil</a></li>
                        <?php if (in_array($roleName, ['admin', 'supervisor'], true)): ?>
                            <li class="d-md-none">
                                <a class="dropdown-item" href="settings.php?t=pages"><i class="bi bi-gear"></i>Administrador</a>
                            </li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right"></i>Desconectar</a></li>
                    </ul>
                </div>
            </div>
        </div>
        
        <?php $roleName = function_exists('getCurrentStaffRoleName') ? (string)getCurrentStaffRoleName() : (string)($staff['role'] ?? ''); ?>
        <div class="scp-mobile-status-container d-flex d-md-none px-3 py-2 w-100 align-items-center" style="border-top: 1px solid rgba(255,255,255,0.15); background: rgba(0,0,0,0.06); margin-top: 2px;">
            <div style="width: 7px; height: 7px; background-color: #10b981; border-radius: 50%; margin-right: 6px; box-shadow: 0 0 5px rgba(16,185,129,0.5);"></div>
            <span style="font-size: 0.8rem; color: rgba(255,255,255,0.95); font-weight: 500;">
                <?php echo html(ucfirst($roleName !== '' ? $roleName : 'Administrador')); ?> · En línea
            </span>
        </div>
    </nav>

    <!-- LAYOUT PRINCIPAL -->
    <div class="layout">
        <!-- SIDEBAR LATERAL -->
        <aside class="sidebar">
            <div class="sidebar-logo">
                <span class="icon sidebar-brand-logo">
                    <?php $brandLogo = (string)getCompanyLogoUrl('publico/img/vigitec-logo.webp'); ?>
                    <img src="<?php echo html($brandLogo); ?>" alt="Vigitec Panama" />
                </span>
                <span class="sidebar-brand-collapsed-mark" aria-hidden="true">//</span>
            </div>

            <div class="sidebar-section">
                <div class="sidebar-section-title">Principal</div>
                <ul class="sidebar-nav">
                    <li class="sidebar-group">
                        <?php 
                        $isPanelRoute = in_array($currentRoute, ['dashboard','directory']);
                        $expandPanel = ($isPanelRoute && $allowExpandedGroups);
                        ?>
                        <button type="button"
                                class="sidebar-link sidebar-toggle <?php echo $expandPanel ? 'active expanded' : ''; ?>"
                                data-subnav="panel-subnav" aria-controls="panel-subnav" aria-expanded="<?php echo $expandPanel ? 'true' : 'false'; ?>">
                            <span class="icon">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M4 12L11 5L18 12V19H4V12Z" stroke="<?php echo $expandPanel ? '#ffffff' : '#9ca3af'; ?>" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>
                            Panel de control
                            <span class="arrow">
                                <svg width="12" height="12" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M7 5L12 10L7 15" stroke="<?php echo $expandPanel ? '#ffffff' : '#9ca3af'; ?>" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>
                        </button>
                        <ul id="panel-subnav" class="sidebar-subnav <?php echo $expandPanel ? 'open' : ''; ?>">
                            <li>
                                <a href="dashboard.php" class="sidebar-link <?php echo $currentRoute === 'dashboard' ? 'active' : ''; ?>">
                                    <span class="icon">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <rect x="4" y="4" width="16" height="16" rx="3" stroke="<?php echo $currentRoute === 'dashboard' ? '#ffffff' : '#64748b'; ?>" stroke-width="1.6"/>
                                            <path d="M9 12L11 14L15 10" stroke="<?php echo $currentRoute === 'dashboard' ? '#ffffff' : '#64748b'; ?>" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </span>
                                    Resumen
                                </a>
                            </li>
                            <li>
                                <a href="directory.php" class="sidebar-link <?php echo $currentRoute === 'directory' ? 'active' : ''; ?>">
                                    <span class="icon">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M5 5H14L19 10V19H5V5Z" stroke="<?php echo $currentRoute === 'directory' ? '#ffffff' : '#64748b'; ?>" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                                            <path d="M9 13H15" stroke="<?php echo $currentRoute === 'directory' ? '#ffffff' : '#64748b'; ?>" stroke-width="1.6" stroke-linecap="round"/>
                                        </svg>
                                    </span>
                                    Directorio del agente
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li>
                        <a href="tickets.php" class="sidebar-link <?php echo $currentRoute === 'tickets' ? 'active' : ''; ?>">
                            <span class="icon">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <rect x="4" y="4" width="16" height="16" rx="2" stroke="<?php echo $currentRoute === 'tickets' ? '#ffffff' : '#9ca3af'; ?>" stroke-width="1.8"/>
                                    <path d="M8 8H16" stroke="<?php echo $currentRoute === 'tickets' ? '#ffffff' : '#9ca3af'; ?>" stroke-width="1.8" stroke-linecap="round"/>
                                    <path d="M8 13H13" stroke="<?php echo $currentRoute === 'tickets' ? '#ffffff' : '#9ca3af'; ?>" stroke-width="1.8" stroke-linecap="round"/>
                                </svg>
                            </span>
                            Tickets
                        </a>
                    </li>
                    <li class="sidebar-group">
                        <?php
                        $isUsersRoute = in_array($currentRoute, ['users', 'orgs']);
                        $expandUsers = ($isUsersRoute && $allowExpandedGroups);
                        ?>
                        <button type="button"
                                class="sidebar-link sidebar-toggle <?php echo $expandUsers ? 'active expanded' : ''; ?>"
                                data-subnav="users-subnav" aria-controls="users-subnav" aria-expanded="<?php echo $expandUsers ? 'true' : 'false'; ?>">
                            <span class="icon">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <circle cx="9" cy="8" r="3" stroke="<?php echo $expandUsers ? '#ffffff' : '#9ca3af'; ?>" stroke-width="1.8"/>
                                    <path d="M4 19C4.6 16 6.5 14.5 9 14.5C11.5 14.5 13.4 16 14 19" stroke="<?php echo $expandUsers ? '#ffffff' : '#9ca3af'; ?>" stroke-width="1.8" stroke-linecap="round"/>
                                    <circle cx="17" cy="8" r="2.5" stroke="<?php echo $expandUsers ? '#ffffff' : '#9ca3af'; ?>" stroke-width="1.6"/>
                                </svg>
                            </span>
                            Usuarios
                            <span class="arrow">
                                <svg width="12" height="12" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M7 5L12 10L7 15" stroke="<?php echo $expandUsers ? '#ffffff' : '#9ca3af'; ?>" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>
                        </button>
                        <ul id="users-subnav" class="sidebar-subnav <?php echo $expandUsers ? 'open' : ''; ?>">
                            <li>
                                <a href="users.php" class="sidebar-link <?php echo $currentRoute === 'users' ? 'active' : ''; ?>">
                                    <span class="icon">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <circle cx="9" cy="8" r="2.5" stroke="<?php echo $currentRoute === 'users' ? '#ffffff' : '#64748b'; ?>" stroke-width="1.6"/>
                                            <path d="M4 19C4.6 16 6.5 14.5 9 14.5C11.5 14.5 13.4 16 14 19" stroke="<?php echo $currentRoute === 'users' ? '#ffffff' : '#64748b'; ?>" stroke-width="1.6" stroke-linecap="round"/>
                                            <circle cx="17" cy="8" r="2" stroke="<?php echo $currentRoute === 'users' ? '#ffffff' : '#64748b'; ?>" stroke-width="1.6"/>
                                        </svg>
                                    </span>
                                    Directorio usuarios
                                </a>
                            </li>
                            <li>
                                <a href="orgs.php" class="sidebar-link <?php echo $currentRoute === 'orgs' ? 'active' : ''; ?>">
                                    <span class="icon">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <rect x="4" y="8" width="16" height="10" rx="2" stroke="<?php echo $currentRoute === 'orgs' ? '#ffffff' : '#64748b'; ?>" stroke-width="1.6"/>
                                            <path d="M9 8V6C9 4.89543 9.89543 4 11 4H13C14.1046 4 15 4.89543 15 6V8" stroke="<?php echo $currentRoute === 'orgs' ? '#ffffff' : '#64748b'; ?>" stroke-width="1.6" stroke-linecap="round"/>
                                        </svg>
                                    </span>
                                    Organizaciones
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li>
                        <a href="tasks.php" class="sidebar-link <?php echo $currentRoute === 'tasks' ? 'active' : ''; ?>">
                            <span class="icon">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <rect x="4" y="5" width="16" height="14" rx="2" stroke="<?php echo $currentRoute === 'tasks' ? '#ffffff' : '#9ca3af'; ?>" stroke-width="1.8"/>
                                    <path d="M4 9H20" stroke="<?php echo $currentRoute === 'tasks' ? '#ffffff' : '#9ca3af'; ?>" stroke-width="1.8" stroke-linecap="round"/>
                                </svg>
                            </span>
                            Tareas
                        </a>
                    </li>
                    <li>
                        <a href="profile.php" class="sidebar-link <?php echo $currentRoute === 'profile' ? 'active' : ''; ?>">
                            <span class="icon">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <circle cx="12" cy="8" r="3" stroke="<?php echo $currentRoute === 'profile' ? '#ffffff' : '#9ca3af'; ?>" stroke-width="1.8"/>
                                    <path d="M6 19C6.6 16.5 8.8 15 12 15C15.2 15 17.4 16.5 18 19" stroke="<?php echo $currentRoute === 'profile' ? '#ffffff' : '#9ca3af'; ?>" stroke-width="1.8" stroke-linecap="round"/>
                                </svg>
                            </span>
                            Mi perfil
                        </a>
                    </li>
                    <li>
                        <a href="statics.php" class="sidebar-link <?php echo $currentRoute === 'statistics' ? 'active' : ''; ?>">
                            <span class="icon">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M5 19V10" stroke="<?php echo $currentRoute === 'statistics' ? '#ffffff' : '#9ca3af'; ?>" stroke-width="1.8" stroke-linecap="round"/>
                                    <path d="M10 19V5" stroke="<?php echo $currentRoute === 'statistics' ? '#ffffff' : '#9ca3af'; ?>" stroke-width="1.8" stroke-linecap="round"/>
                                    <path d="M15 19V13" stroke="<?php echo $currentRoute === 'statistics' ? '#ffffff' : '#9ca3af'; ?>" stroke-width="1.8" stroke-linecap="round"/>
                                    <path d="M20 19V8" stroke="<?php echo $currentRoute === 'statistics' ? '#ffffff' : '#9ca3af'; ?>" stroke-width="1.8" stroke-linecap="round"/>
                                </svg>
                            </span>
                            Estadísticas
                        </a>
                    </li>
                </ul>
            </div>

            <div class="sidebar-section">
                <div class="sidebar-section-title">Configuración</div>
                <ul class="sidebar-nav">
                    <li>
                        <a href="logout.php" class="sidebar-link">
                            <span class="icon">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M10 5H5V19H10" stroke="#f87171" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M15 9L19 12L15 15" stroke="#f87171" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M19 12H9" stroke="#f87171" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>
                            Salir
                        </a>
                    </li>
                </ul>
            </div>
        </aside>
        <div id="scpSidebarFlyout" class="sidebar-flyout" aria-hidden="true"></div>

        <!-- ZONA PRINCIPAL (contenido dinámico) -->
        <main class="main-shell">
            <div class="container-main">
                <?php if ((int)($_SESSION['read_only'] ?? 0) === 1): ?>
                    <?php $roMsg = (string)($_SESSION['read_only_reason'] ?? 'Pago vencido. Comuníquese con Vigitec Panamá.'); ?>
                    <div class="alert alert-warning" role="alert" data-alert-static="1">
                        <i class="bi bi-exclamation-triangle me-2"></i><strong>Modo lectura:</strong> <?php echo html($roMsg); ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($_SESSION['flash_error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle me-2"></i><?php echo html((string)$_SESSION['flash_error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['flash_error']); ?>
                <?php endif; ?>
                <?php if (!empty($_SESSION['flash_msg'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle me-2"></i><?php echo html((string)$_SESSION['flash_msg']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['flash_msg']); ?>
                <?php endif; ?>
                <?php echo $content; ?>
            </div>
        </main>
    </div>

    <div class="text-muted scp-footer-brand" style="font-size: 0.85rem; padding: 14px 10px; text-align: center; width: 100%; display: block;">
        &copy; VigitecPanama
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/scp.js"></script>
    <script>
        window.addEventListener('DOMContentLoaded', function(){
            var alerts = document.querySelectorAll('.alert.alert-dismissible.fade.show:not(.d-none)');
            if (!alerts || !alerts.length) return;
            alerts.forEach(function(el){
                var isPermanent = el.hasAttribute('data-alert-static') || el.getAttribute('data-static') === '1';
                var id = (el.getAttribute('id') || '');
                if (id && /ClientError$/i.test(id)) return;
                if (isPermanent) return;
                window.setTimeout(function(){
                    try {
                        if (typeof bootstrap !== 'undefined' && bootstrap.Alert) {
                            bootstrap.Alert.getOrCreateInstance(el).close();
                        } else {
                            el.classList.remove('show');
                            window.setTimeout(function(){ if (el && el.parentNode) el.parentNode.removeChild(el); }, 250);
                        }
                    } catch (e) {}
                }, 3500);
            });
        });
    </script>
    <?php if (isset($currentRoute) && $currentRoute === 'profile'): ?>
    <script src="js/profile.js"></script>
    <?php endif; ?>
    <?php if (isset($currentRoute) && $currentRoute === 'users'): ?>
    <script src="js/users.js"></script>
    <?php endif; ?>
    <?php if (isset($currentRoute) && $currentRoute === 'dashboard'): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="js/dashboard.js?v=<?php echo (int)@filemtime(__DIR__ . '/../js/dashboard.js'); ?>"></script>
    <?php endif; ?>
    <?php if (isset($currentRoute) && $currentRoute === 'tickets'): ?>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.css">
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/lang/summernote-es-ES.min.js"></script>
    <script src="js/tickets.js"></script>
    <?php endif; ?>
    <?php if (isset($currentRoute) && $currentRoute === 'tasks'): ?>
    <script src="js/tasks.js"></script>
    <?php endif; ?>
    <?php if (isset($currentRoute) && $currentRoute === 'orgs'): ?>
    <script src="js/orgs.js"></script>
    <?php endif; ?>
</body>
</html>

