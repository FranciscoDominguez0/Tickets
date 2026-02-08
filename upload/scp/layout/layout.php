<?php
// Layout principal del panel de agente
// Header + sidebar fijos, contenido dinámico en $content
?>
<?php
$notifCount = 0;
$notifItems = [];
if (isset($mysqli) && $mysqli && isset($_SESSION['staff_id'])) {
    $sid = (int) $_SESSION['staff_id'];
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
}

// Lógica para controlar el estado inicial del sidebar (similar al panel de administrador)
$collapseSidebarMenu = false;
if (!isset($_SESSION['agent_sidebar_menu_seen'])) {
    $_SESSION['agent_sidebar_menu_seen'] = 1;
    $collapseSidebarMenu = true;
}
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
<body<?php if (isset($currentRoute) && $currentRoute === 'users'): ?> data-user-active-tab="<?php echo isset($_GET['t']) ? htmlspecialchars($_GET['t'], ENT_QUOTES, 'UTF-8') : 'tickets'; ?>"<?php endif; ?>>
    <!-- NAVBAR -->
    <nav class="navbar navbar-dark">
        <div class="container-fluid">
            <span class="navbar-brand"><?php echo APP_NAME; ?> - Agente</span>
            <div class="d-flex align-items-center gap-3">
                <span style="color: white;">Agente: <strong><?php echo html($staff['name']); ?></strong></span>

                <div class="dropdown">
                    <button class="btn btn-outline-light btn-sm position-relative scp-notif-btn <?php echo $notifCount > 0 ? 'has-new' : ''; ?>" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Notificaciones">
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

                <a href="settings.php?t=pages" class="btn btn-outline-light btn-sm">Panel Administrador</a>
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
                        $isPanelRoute = in_array($currentRoute, ['dashboard','directory']);
                        $expandPanel = ($isPanelRoute && empty($collapseSidebarMenu));
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
                        $expandUsers = ($isUsersRoute && empty($collapseSidebarMenu));
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
    <script src="js/users.js"></script>
    <?php endif; ?>
    <?php if (isset($currentRoute) && $currentRoute === 'dashboard'): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="js/dashboard.js"></script>
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

