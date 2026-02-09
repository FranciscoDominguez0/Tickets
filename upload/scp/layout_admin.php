<?php
// Layout para panel administrador
// Similar al layout de agentes pero con sidebar de administración

$staffIdForMenu = (int)($_SESSION['staff_id'] ?? 0);
if ((string)($_SESSION['sidebar_panel_mode'] ?? '') !== 'admin') {
    unset($_SESSION['admin_sidebar_menu_seen_' . $staffIdForMenu]);
    unset($_SESSION['admin_settings_menu_seen_' . $staffIdForMenu]);
    $_SESSION['sidebar_panel_mode'] = 'admin';
}

if (!isset($collapseSettingsMenu)) {
    $collapseSettingsMenu = false;
    $menuKey = 'admin_sidebar_menu_seen_' . $staffIdForMenu;
    if (!isset($_SESSION[$menuKey])) {
        $_SESSION[$menuKey] = 1;
        $collapseSettingsMenu = true;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Administrador - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/scp.css?v=<?php echo (int)@filemtime(__DIR__ . '/css/scp.css'); ?>">
</head>
<body style="padding-top: 64px;">
    <!-- NAVBAR ADMINISTRADOR -->
    <nav class="navbar navbar-dark" style="position: fixed; top: 0; left: 0; width: 100%; z-index: 1001;">
        <div class="container-fluid">
            <span class="navbar-brand"><?php echo APP_NAME; ?> - Panel Administrador</span>
            <div class="d-flex align-items-center gap-3">
                <span style="color: white;">Agente: <strong><?php echo html($staff['name']); ?></strong></span>
                <a href="index.php" class="btn btn-outline-light btn-sm">Volver a Agentes</a>
                <a href="logout.php" class="btn btn-outline-light btn-sm">Cerrar Sesión</a>
            </div>
        </div>
    </nav>

    <!-- LAYOUT ADMINISTRADOR -->
    <div class="layout">
        <!-- SIDEBAR ADMINISTRACIÓN -->
        <aside class="sidebar">
            <div class="sidebar-logo">
                <div class="sidebar-brand-logo">
                    <?php $brandLogo = (string)getCompanyLogoUrl('publico/img/vigitec-logo.png'); ?>
                    <img src="<?php echo html($brandLogo); ?>" alt="Vigitec Panama" />
                </div>
            </div>

            <div class="sidebar-section">
                <div class="sidebar-section-title">Panel Admin</div>
                <ul class="sidebar-nav">
                    <li class="sidebar-group">
                        <?php $settingsTab = (string)($_GET['t'] ?? ''); $isSettingsRoute = ($currentRoute === 'settings'); ?>
                        <?php $expandSettings = ($isSettingsRoute && empty($collapseSettingsMenu)); ?>
                        <button type="button" class="sidebar-toggle <?php echo $expandSettings ? 'active expanded' : ''; ?>" data-subnav="settings-subnav" aria-controls="settings-subnav" aria-expanded="<?php echo $expandSettings ? 'true' : 'false'; ?>">
                            <span class="icon"><i class="bi bi-gear"></i></span>
                            Configuración
                            <span class="arrow"><i class="bi bi-chevron-right"></i></span>
                        </button>
                        <ul id="settings-subnav" class="sidebar-subnav <?php echo $expandSettings ? 'open' : ''; ?>">
                            <li>
                                <a href="settings.php?t=pages" class="sidebar-link <?php echo ($isSettingsRoute && $settingsTab === 'pages') ? 'active' : ''; ?>">
                                    <span class="icon"><i class="bi bi-building"></i></span>
                                    Perfil de la empresa
                                </a>
                            </li>
                            <li>
                                <a href="settings.php?t=system" class="sidebar-link <?php echo ($isSettingsRoute && $settingsTab === 'system') ? 'active' : ''; ?>">
                                    <span class="icon"><i class="bi bi-sliders"></i></span>
                                    Sistema
                                </a>
                            </li>
                            <li>
                                <a href="settings.php?t=tickets" class="sidebar-link <?php echo ($isSettingsRoute && $settingsTab === 'tickets') ? 'active' : ''; ?>">
                                    <span class="icon"><i class="bi bi-ticket-perforated"></i></span>
                                    Solicitudes
                                </a>
                            </li>
                            <li>
                                <a href="settings.php?t=tasks#settings" class="sidebar-link <?php echo ($isSettingsRoute && $settingsTab === 'tasks') ? 'active' : ''; ?>">
                                    <span class="icon"><i class="bi bi-check2-square"></i></span>
                                    Tareas
                                </a>
                            </li>
                            <li>
                                <a href="settings.php?t=agents" class="sidebar-link <?php echo ($isSettingsRoute && $settingsTab === 'agents') ? 'active' : ''; ?>">
                                    <span class="icon"><i class="bi bi-person-badge"></i></span>
                                    Agentes
                                </a>
                            </li>
                            <li>
                                <a href="settings.php?t=users" class="sidebar-link <?php echo ($isSettingsRoute && $settingsTab === 'users') ? 'active' : ''; ?>">
                                    <span class="icon"><i class="bi bi-people"></i></span>
                                    Usuarios, osTicket
                                </a>
                            </li>
                        </ul>
                        <a href="logs.php" class="sidebar-link <?php echo ($currentRoute === 'logs') ? 'active' : ''; ?>">
                            <span class="icon"><i class="bi bi-graph-up"></i></span>
                            Panel de Control
                        </a>
                        <a href="helptopics.php" class="sidebar-link <?php echo ($currentRoute === 'helptopics') ? 'active' : ''; ?>">
                            <span class="icon"><i class="bi bi-list-check"></i></span>
                            Administrar
                        </a>
                        <?php
                        $emailTab = isset($emailTab) ? (string)$emailTab : '';
                        $isEmailRoute = ($currentRoute === 'emails');
                        $expandEmail = ($isEmailRoute && empty($collapseSettingsMenu));
                        ?>
                        <button type="button" class="sidebar-toggle <?php echo $expandEmail ? 'active expanded' : ''; ?>" data-subnav="emails-subnav" aria-controls="emails-subnav" aria-expanded="<?php echo $expandEmail ? 'true' : 'false'; ?>">
                            <span class="icon"><i class="bi bi-envelope"></i></span>
                            Correos Electrónicos
                            <span class="arrow"><i class="bi bi-chevron-right"></i></span>
                        </button>
                        <ul id="emails-subnav" class="sidebar-subnav <?php echo $expandEmail ? 'open' : ''; ?>">
                            <li>
                                <a href="emails.php" class="sidebar-link <?php echo ($isEmailRoute && $emailTab === 'emails') ? 'active' : ''; ?>">
                                    <span class="icon"><i class="bi bi-inbox"></i></span>
                                    Correos
                                </a>
                            </li>
                            <li>
                                <a href="emailsettings.php" class="sidebar-link <?php echo ($isEmailRoute && $emailTab === 'settings') ? 'active' : ''; ?>">
                                    <span class="icon"><i class="bi bi-gear"></i></span>
                                    Configuración
                                </a>
                            </li>
                            <li>
                                <a href="banlist.php" class="sidebar-link <?php echo ($isEmailRoute && $emailTab === 'banlist') ? 'active' : ''; ?>">
                                    <span class="icon"><i class="bi bi-slash-circle"></i></span>
                                    Lista de prohibidos
                                </a>
                            </li>
                            <li>
                                <a href="emailtest.php" class="sidebar-link <?php echo ($isEmailRoute && $emailTab === 'test') ? 'active' : ''; ?>">
                                    <span class="icon"><i class="bi bi-activity"></i></span>
                                    Diagnóstico
                                </a>
                            </li>
                        </ul>
                        <?php
                        $isAgentsRoute = in_array($currentRoute, ['staff', 'roles', 'departments']);
                        $expandAgents = ($isAgentsRoute && empty($collapseSettingsMenu));
                        ?>
                        <button type="button" class="sidebar-toggle <?php echo $expandAgents ? 'active expanded' : ''; ?>" data-subnav="agents-subnav" aria-controls="agents-subnav" aria-expanded="<?php echo $expandAgents ? 'true' : 'false'; ?>">
                            <span class="icon"><i class="bi bi-people"></i></span>
                            Agentes
                            <span class="arrow"><i class="bi bi-chevron-right"></i></span>
                        </button>
                        <ul id="agents-subnav" class="sidebar-subnav <?php echo $expandAgents ? 'open' : ''; ?>">
                            <li>
                                <a href="staff.php" class="sidebar-link <?php echo ($currentRoute === 'staff') ? 'active' : ''; ?>">
                                    <span class="icon"><i class="bi bi-person-badge"></i></span>
                                    Agentes
                                </a>
                            </li>
                            <li>
                                <a href="roles.php" class="sidebar-link <?php echo ($currentRoute === 'roles') ? 'active' : ''; ?>">
                                    <span class="icon"><i class="bi bi-shield-lock"></i></span>
                                    Roles
                                </a>
                            </li>
                            <li>
                                <a href="departments.php" class="sidebar-link <?php echo ($currentRoute === 'departments') ? 'active' : ''; ?>">
                                    <span class="icon"><i class="bi bi-diagram-3"></i></span>
                                    Departamentos
                                </a>
                            </li>
                        </ul>
                    </li>

                </ul>
            </div>
        </aside>

        <!-- CONTENIDO PRINCIPAL -->
        <main class="main-shell">
            <div class="container-main">
                <?php echo $content; ?>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/scp.js"></script>
    <?php if (isset($currentRoute) && $currentRoute === 'logs'): ?>
    <script src="js/logs.js"></script>
    <?php endif; ?>
</body>
</html>