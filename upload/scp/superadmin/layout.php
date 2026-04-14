<?php
require_once '../../../config.php';
require_once '../../../includes/helpers.php';

requireLogin('agente');

if ((string)($_SESSION['staff_role'] ?? '') !== 'superadmin') {
    header('Location: ../index.php');
    exit;
}

$staff = getCurrentUser();
$staffName = (string)($_SESSION['staff_name'] ?? ($staff['name'] ?? ''));
$currentRoute = (string)($currentRoute ?? 'dashboard');
$content = (string)($content ?? '');
$isDarkMode = (int)($_SESSION['superadmin_dark_mode'] ?? 0) === 1;
$sidebarCookieState = isset($_COOKIE['scp_sidebar_collapsed']) ? (string)$_COOKIE['scp_sidebar_collapsed'] : '';
$sidebarDefaultCollapsed = ($sidebarCookieState === 'collapsed');
$allowExpandedGroups = !$sidebarDefaultCollapsed;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SuperAdmin - <?php echo htmlspecialchars(APP_NAME); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/scp.css?v=<?php echo (int)@filemtime(__DIR__ . '/../css/scp.css'); ?>">
</head>
<body class="superadmin scp-panel<?php echo $isDarkMode ? ' superadmin-dark' : ''; ?><?php echo $sidebarDefaultCollapsed ? ' sidebar-collapsed' : ''; ?>" data-sidebar-default="<?php echo $sidebarDefaultCollapsed ? 'collapsed' : 'expanded'; ?>">
<nav class="navbar navbar-dark">
    <div class="container-fluid">
        <div class="d-flex align-items-center gap-2">
            <span class="navbar-brand scp-brand-title">Sistema de Tickets</span>
            <button class="btn scp-menu-toggle" id="scpSidebarToggle" type="button" aria-label="Alternar menú lateral" aria-expanded="<?php echo $sidebarDefaultCollapsed ? 'false' : 'true'; ?>">
                <i class="bi bi-list"></i>
            </button>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span style="color: white;">SuperAdmin: <strong><?php echo html($staffName); ?></strong></span>
            <form method="post" action="toggle_dark.php" class="d-inline" style="margin:0" data-superadmin-dark-toggle-form>
                <?php csrfField(); ?>
                <input type="hidden" name="dark_mode" value="<?php echo $isDarkMode ? '0' : '1'; ?>">
                <input type="hidden" name="return" value="<?php echo html(basename((string)($_SERVER['PHP_SELF'] ?? 'index.php')) . (!empty($_SERVER['QUERY_STRING']) ? ('?' . (string)$_SERVER['QUERY_STRING']) : '')); ?>">
                <button type="submit" class="btn btn-outline-light btn-sm superadmin-theme-toggle" title="Modo oscuro" data-superadmin-dark-toggle-btn>
                    <span class="superadmin-theme-toggle-track" aria-hidden="true">
                        <span class="superadmin-theme-toggle-thumb"></span>
                    </span>
                    <i class="bi <?php echo $isDarkMode ? 'bi-sun' : 'bi-moon-stars'; ?> superadmin-theme-toggle-icon"></i>
                </button>
            </form>
            <a href="../logout.php" class="btn btn-outline-light btn-sm">Cerrar Sesión</a>
        </div>
    </div>
</nav>

<div class="layout">
    <aside class="sidebar">
        <div class="sidebar-logo">
            <span class="icon sidebar-brand-logo">
                <?php $brandLogo = (string)getCompanyLogoUrl('publico/img/vigitec-logo.png'); ?>
                <img src="<?php echo html($brandLogo); ?>" alt="Vigitec Panama" />
            </span>
            <span class="sidebar-brand-collapsed-mark" aria-hidden="true">//</span>
        </div>

        <div class="sidebar-section">
            <div class="sidebar-section-title">SuperAdmin</div>
            <ul class="sidebar-nav">
                <li>
                    <a href="index.php" class="sidebar-link <?php echo $currentRoute === 'dashboard' ? 'active' : ''; ?>">
                        <span class="icon">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M5 19V10" stroke="<?php echo $currentRoute === 'dashboard' ? '#ffffff' : '#9ca3af'; ?>" stroke-width="1.8" stroke-linecap="round"/>
                                <path d="M10 19V5" stroke="<?php echo $currentRoute === 'dashboard' ? '#ffffff' : '#9ca3af'; ?>" stroke-width="1.8" stroke-linecap="round"/>
                                <path d="M15 19V13" stroke="<?php echo $currentRoute === 'dashboard' ? '#ffffff' : '#9ca3af'; ?>" stroke-width="1.8" stroke-linecap="round"/>
                                <path d="M20 19V8" stroke="<?php echo $currentRoute === 'dashboard' ? '#ffffff' : '#9ca3af'; ?>" stroke-width="1.8" stroke-linecap="round"/>
                            </svg>
                        </span>
                        Panel general
                    </a>
                </li>
                <li>
                    <?php $agentsGroupActive = ($currentRoute === 'superadmins'); ?>
                    <?php $expandAgents = ($agentsGroupActive && $allowExpandedGroups); ?>
                    <button type="button" class="sidebar-toggle <?php echo $expandAgents ? 'active expanded' : ''; ?>" data-subnav="superadmin-agents-subnav" aria-controls="superadmin-agents-subnav" aria-expanded="<?php echo $expandAgents ? 'true' : 'false'; ?>">
                        <span class="icon">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M16 21V19A4 4 0 0 0 12 15H7A4 4 0 0 0 3 19V21" stroke="<?php echo $expandAgents ? '#ffffff' : '#9ca3af'; ?>" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M9.5 11A4 4 0 1 0 9.5 3A4 4 0 1 0 9.5 11Z" stroke="<?php echo $expandAgents ? '#ffffff' : '#9ca3af'; ?>" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M20 8V14" stroke="<?php echo $expandAgents ? '#ffffff' : '#9ca3af'; ?>" stroke-width="1.8" stroke-linecap="round"/>
                                <path d="M23 11H17" stroke="<?php echo $expandAgents ? '#ffffff' : '#9ca3af'; ?>" stroke-width="1.8" stroke-linecap="round"/>
                            </svg>
                        </span>
                        Agentes
                        <span class="arrow" aria-hidden="true"><i class="bi bi-chevron-right"></i></span>
                    </button>
                    <ul id="superadmin-agents-subnav" class="sidebar-subnav <?php echo $expandAgents ? 'open' : ''; ?>">
                        <li>
                            <a href="superadmins.php" class="sidebar-link <?php echo $currentRoute === 'superadmins' ? 'active' : ''; ?>">
                                <span class="icon"><i class="bi bi-person-badge"></i></span>
                                Superadmins
                            </a>
                        </li>
                    </ul>
                </li>
                <li>
                    <?php $empresasGroupActive = ($currentRoute === 'empresas' || $currentRoute === 'empresas_actividad'); ?>
                    <?php $expandEmpresas = ($empresasGroupActive && $allowExpandedGroups); ?>
                    <button type="button" class="sidebar-toggle <?php echo $expandEmpresas ? 'active expanded' : ''; ?>" data-subnav="superadmin-empresas-subnav" aria-controls="superadmin-empresas-subnav" aria-expanded="<?php echo $expandEmpresas ? 'true' : 'false'; ?>">
                        <span class="icon">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect x="4" y="7" width="16" height="13" rx="2" stroke="<?php echo $expandEmpresas ? '#ffffff' : '#9ca3af'; ?>" stroke-width="1.8"/>
                                <path d="M8 7V5" stroke="<?php echo $expandEmpresas ? '#ffffff' : '#9ca3af'; ?>" stroke-width="1.8" stroke-linecap="round"/>
                                <path d="M16 7V5" stroke="<?php echo $expandEmpresas ? '#ffffff' : '#9ca3af'; ?>" stroke-width="1.8" stroke-linecap="round"/>
                            </svg>
                        </span>
                        Empresa
                        <span class="arrow" aria-hidden="true"><i class="bi bi-chevron-right"></i></span>
                    </button>
                    <ul id="superadmin-empresas-subnav" class="sidebar-subnav <?php echo $expandEmpresas ? 'open' : ''; ?>">
                        <li>
                            <a href="empresas.php" class="sidebar-link <?php echo $currentRoute === 'empresas' ? 'active' : ''; ?>">
                                <span class="icon"><i class="bi bi-buildings"></i></span>
                                Empresas
                            </a>
                        </li>
                        <li>
                            <a href="empresas_actividad.php" class="sidebar-link <?php echo $currentRoute === 'empresas_actividad' ? 'active' : ''; ?>">
                                <span class="icon"><i class="bi bi-activity"></i></span>
                                Actividad de tickets
                            </a>
                        </li>
                    </ul>
                </li>
                <li>
                    <a href="pagos.php" class="sidebar-link <?php echo $currentRoute === 'pagos' ? 'active' : ''; ?>">
                        <span class="icon">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect x="4" y="6" width="16" height="12" rx="2" stroke="<?php echo $currentRoute === 'pagos' ? '#ffffff' : '#9ca3af'; ?>" stroke-width="1.8"/>
                                <path d="M4 10H20" stroke="<?php echo $currentRoute === 'pagos' ? '#ffffff' : '#9ca3af'; ?>" stroke-width="1.8" stroke-linecap="round"/>
                            </svg>
                        </span>
                        Facturación
                    </a>
                </li>
                <li>
                    <?php $configGroupActive = ($currentRoute === 'configuracion' || $currentRoute === 'notificaciones'); ?>
                    <?php $expandConfig = ($configGroupActive && $allowExpandedGroups); ?>
                    <button type="button" class="sidebar-toggle <?php echo $expandConfig ? 'active expanded' : ''; ?>" data-subnav="superadmin-config-subnav" aria-controls="superadmin-config-subnav" aria-expanded="<?php echo $expandConfig ? 'true' : 'false'; ?>">
                        <span class="icon"><i class="bi bi-gear"></i></span>
                        Configuración
                        <span class="arrow" aria-hidden="true"><i class="bi bi-chevron-right"></i></span>
                    </button>
                    <ul id="superadmin-config-subnav" class="sidebar-subnav <?php echo $expandConfig ? 'open' : ''; ?>">
                        <li>
                            <a href="configuracion.php" class="sidebar-link <?php echo $currentRoute === 'configuracion' ? 'active' : ''; ?>">
                                <span class="icon"><i class="bi bi-gear"></i></span>
                                Configuración
                            </a>
                        </li>
                        <li>
                            <a href="notificaciones.php" class="sidebar-link <?php echo $currentRoute === 'notificaciones' ? 'active' : ''; ?>">
                                <span class="icon"><i class="bi bi-bell"></i></span>
                                Notificaciones
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>

        <div class="sidebar-section">
            <div class="sidebar-section-title">Configuración</div>
            <ul class="sidebar-nav">
                <li>
                    <a href="../logout.php" class="sidebar-link">
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

    <main class="main-shell">
        <div class="container-main">
            <?php echo $content; ?>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../js/scp.js"></script>
<script>
    (function(){
        var form = document.querySelector('[data-superadmin-dark-toggle-form]');
        if (!form) return;
        var btn = form.querySelector('[data-superadmin-dark-toggle-btn]');
        var body = document.body;
        var input = form.querySelector('input[name="dark_mode"]');
        var icon = form.querySelector('.superadmin-theme-toggle-icon');

        function setUi(isDark) {
            if (isDark) body.classList.add('superadmin-dark');
            else body.classList.remove('superadmin-dark');
            if (icon) {
                icon.classList.remove('bi-sun');
                icon.classList.remove('bi-moon-stars');
                icon.classList.add(isDark ? 'bi-sun' : 'bi-moon-stars');
            }
            if (input) input.value = isDark ? '0' : '1';
        }

        form.addEventListener('submit', function(e){
            e.preventDefault();
            var isDark = body.classList.contains('superadmin-dark');
            var nextDark = !isDark;
            setUi(nextDark);
            try {
                if (btn) btn.disabled = true;
                var fd = new FormData(form);
                fd.set('dark_mode', nextDark ? '1' : '0');
                fetch(form.getAttribute('action') || 'toggle_dark.php', {
                    method: 'POST',
                    body: fd,
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                }).then(function(r){ return r.json().catch(function(){ return null; }); })
                  .then(function(data){
                      if (data && typeof data.dark_mode !== 'undefined') {
                          setUi(String(data.dark_mode) === '1' || data.dark_mode === 1);
                      }
                  })
                  .catch(function(){
                      setUi(isDark);
                  })
                  .finally(function(){
                      if (btn) btn.disabled = false;
                  });
            } catch (err) {
                setUi(isDark);
                if (btn) btn.disabled = false;
            }
        });
    })();
</script>
</body>
</html>
