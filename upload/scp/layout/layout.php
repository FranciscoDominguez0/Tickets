<?php
// Layout principal del panel de agente
// Header + sidebar fijos, contenido dinámico en $content
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Agente - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/scp.css?v=<?php echo (int)@filemtime(__DIR__ . '/../css/scp.css'); ?>">
    <?php if (isset($currentRoute) && $currentRoute === 'profile'): ?>
    <link rel="stylesheet" href="css/profile.css">
    <?php endif; ?>
    <?php if (isset($currentRoute) && $currentRoute === 'users'): ?>
    <link rel="stylesheet" href="css/users.css">
    <?php endif; ?>
    <?php if (isset($currentRoute) && $currentRoute === 'tickets'): ?>
    <link rel="stylesheet" href="css/tickets.css">
    <?php endif; ?>
    <?php if (isset($currentRoute) && $currentRoute === 'orgs'): ?>
    <link rel="stylesheet" href="css/orgs.css">
    <?php endif; ?>
</head>
<body>
    <!-- NAVBAR -->
    <nav class="navbar navbar-dark">
        <div class="container-fluid">
            <span class="navbar-brand"><?php echo APP_NAME; ?> - Agente</span>
            <div class="d-flex align-items-center gap-3">
                <span style="color: white;">Agente: <strong><?php echo html($staff['name']); ?></strong></span>
                <a href="settings.php" class="btn btn-outline-light btn-sm">Panel Administrador</a>
                <a href="logout.php" class="btn btn-outline-light btn-sm">Cerrar Sesión</a>
            </div>
        </div>
    </nav>

    <!-- LAYOUT PRINCIPAL -->
    <div class="layout">
        <!-- SIDEBAR LATERAL -->
        <aside class="sidebar">
            <div class="sidebar-logo">
                <span class="icon sidebar-brand-logo">
                    <?php $brandLogo = (string)getCompanyLogoUrl('publico/img/vigitec-logo.png'); ?>
                    <img src="<?php echo html($brandLogo); ?>" alt="Vigitec Panama" />
                </span>
            </div>

            <div class="sidebar-section">
                <div class="sidebar-section-title">Principal</div>
                <ul class="sidebar-nav">
                    <li class="sidebar-group">
                        <?php 
                        $isPanelRoute = in_array($currentRoute, ['dashboard','directory','profile']);
                        ?>
                        <button type="button"
                                class="sidebar-link sidebar-toggle <?php echo $isPanelRoute ? 'active' : ''; ?> <?php echo $isPanelRoute ? 'expanded' : ''; ?>"
                                data-subnav="panel-subnav">
                            <span class="icon">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M4 12L11 5L18 12V19H4V12Z" stroke="<?php echo $isPanelRoute ? '#ffffff' : '#9ca3af'; ?>" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>
                            Panel de control
                            <span class="arrow">
                                <svg width="12" height="12" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M7 5L12 10L7 15" stroke="<?php echo $isPanelRoute ? '#ffffff' : '#9ca3af'; ?>" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>
                        </button>
                        <ul id="panel-subnav" class="sidebar-subnav <?php echo $isPanelRoute ? 'open' : ''; ?>">
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
                            <li>
                                <a href="profile.php" class="sidebar-link <?php echo $currentRoute === 'profile' ? 'active' : ''; ?>">
                                    <span class="icon">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <circle cx="12" cy="8" r="3" stroke="<?php echo $currentRoute === 'profile' ? '#ffffff' : '#64748b'; ?>" stroke-width="1.6"/>
                                            <path d="M6 19C6.6 16.5 8.8 15 12 15C15.2 15 17.4 16.5 18 19" stroke="<?php echo $currentRoute === 'profile' ? '#ffffff' : '#64748b'; ?>" stroke-width="1.6" stroke-linecap="round"/>
                                        </svg>
                                    </span>
                                    Mi perfil
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li>
                        <a href="tickets.php" class="sidebar-link <?php echo $currentRoute === 'tickets' ? 'active' : ''; ?>">
                            <span class="icon">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <rect x="4" y="4" width="16" height="16" rx="2" stroke="<?php echo $currentRoute === 'tickets' ? '#ffffff' : '#9ca3af'; ?>" stroke-width="1.8"/>
                                    <path d="M8 9H16" stroke="<?php echo $currentRoute === 'tickets' ? '#ffffff' : '#9ca3af'; ?>" stroke-width="1.8" stroke-linecap="round"/>
                                    <path d="M8 13H13" stroke="<?php echo $currentRoute === 'tickets' ? '#ffffff' : '#9ca3af'; ?>" stroke-width="1.8" stroke-linecap="round"/>
                                </svg>
                            </span>
                            Solicitudes
                        </a>
                    </li>
                    <li class="sidebar-group">
                        <?php
                        $isUsersRoute = in_array($currentRoute, ['users', 'orgs']);
                        ?>
                        <button type="button"
                                class="sidebar-link sidebar-toggle <?php echo $isUsersRoute ? 'active' : ''; ?> <?php echo $isUsersRoute ? 'expanded' : ''; ?>"
                                data-subnav="users-subnav">
                            <span class="icon">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <circle cx="9" cy="8" r="3" stroke="<?php echo $isUsersRoute ? '#ffffff' : '#9ca3af'; ?>" stroke-width="1.8"/>
                                    <path d="M4 19C4.6 16 6.5 14.5 9 14.5C11.5 14.5 13.4 16 14 19" stroke="<?php echo $isUsersRoute ? '#ffffff' : '#9ca3af'; ?>" stroke-width="1.8" stroke-linecap="round"/>
                                    <circle cx="17" cy="8" r="2.5" stroke="<?php echo $isUsersRoute ? '#ffffff' : '#9ca3af'; ?>" stroke-width="1.6"/>
                                </svg>
                            </span>
                            Usuarios
                            <span class="arrow">
                                <svg width="12" height="12" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M7 5L12 10L7 15" stroke="<?php echo $isUsersRoute ? '#ffffff' : '#9ca3af'; ?>" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>
                        </button>
                        <ul id="users-subnav" class="sidebar-subnav <?php echo $isUsersRoute ? 'open' : ''; ?>">
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
                        <a href="canned.php" class="sidebar-link <?php echo $currentRoute === 'canned' ? 'active' : ''; ?>">
                            <span class="icon">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M5 4H14L19 9V20H5V4Z" stroke="<?php echo $currentRoute === 'canned' ? '#ffffff' : '#9ca3af'; ?>" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M9 12H15" stroke="<?php echo $currentRoute === 'canned' ? '#ffffff' : '#9ca3af'; ?>" stroke-width="1.8" stroke-linecap="round"/>
                                </svg>
                            </span>
                            Base de conocimientos
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
            <div class="sidebar-footer">
                <div>Sesión iniciada como<br><strong><?php echo html($staff['name']); ?></strong></div>
            </div>
        </aside>

        <!-- ZONA PRINCIPAL (contenido dinámico) -->
        <main class="main-shell">
            <div class="container-main">
                <?php echo $content; ?>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/scp.js"></script>
    <?php if (isset($currentRoute) && $currentRoute === 'profile'): ?>
    <script src="js/profile.js"></script>
    <?php endif; ?>
    <?php if (isset($currentRoute) && $currentRoute === 'users'): ?>
    <script>
      var USER_ACTIVE_TAB = '<?php echo isset($_GET['t']) ? htmlspecialchars($_GET['t'], ENT_QUOTES, 'UTF-8') : 'tickets'; ?>';
    </script>
    <script src="js/users.js"></script>
    <?php endif; ?>
    <?php if (isset($currentRoute) && $currentRoute === 'tickets'): ?>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.css">
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/lang/summernote-es-ES.min.js"></script>
    <script src="js/tickets.js"></script>
    <?php endif; ?>
    <?php if (isset($currentRoute) && $currentRoute === 'orgs'): ?>
    <script src="js/orgs.js"></script>
    <?php endif; ?>
</body>
</html>

