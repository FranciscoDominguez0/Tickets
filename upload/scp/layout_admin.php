<?php
// Layout para panel administrador
// Similar al layout de agentes pero con sidebar de administración

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
                                <a href="settings.php?t=billing" class="sidebar-link <?php echo ($isSettingsRoute && $settingsTab === 'billing') ? 'active' : ''; ?>">
                                    <span class="icon"><i class="bi bi-receipt"></i></span>
                                    Facturación
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
                                    Usuarios
                                </a>
                            </li>
                        </ul>
                        <a href="logs.php" class="sidebar-link <?php echo ($currentRoute === 'logs') ? 'active' : ''; ?>">
                            <span class="icon"><i class="bi bi-graph-up"></i></span>
                            Panel de Control
                        </a>
                        <a href="notifications_admin.php" class="sidebar-link <?php echo ($currentRoute === 'notifications_admin') ? 'active' : ''; ?>">
                            <span class="icon"><i class="bi bi-bell"></i></span>
                            Notificaciones
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

                        <a href="logout.php" class="sidebar-link">
                            <span class="icon"><i class="bi bi-box-arrow-right" style="color: #f87171 !important;"></i></span>
                            Salir
                        </a>
                    </li>

                </ul>
            </div>
        </aside>

        <!-- CONTENIDO PRINCIPAL -->
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

    <?php if (isset($currentRoute) && $currentRoute === 'staff'): ?>
        <style>
            #staff-loading-overlay {
                position: fixed;
                inset: 0;
                background: rgba(15, 23, 42, 0.55);
                backdrop-filter: blur(2px);
                display: none;
                align-items: center;
                justify-content: center;
                z-index: 2000;
                padding: 20px;
            }

            #staff-loading-overlay .staff-loading-card {
                width: 100%;
                max-width: 360px;
                background: #fff;
                border-radius: 14px;
                box-shadow: 0 20px 50px rgba(0, 0, 0, 0.25);
                padding: 18px 16px;
                text-align: center;
            }

            #staff-loading-overlay .staff-spinner {
                width: 42px;
                height: 42px;
                border-radius: 999px;
                border: 4px solid #e2e8f0;
                border-top-color: #0d6efd;
                margin: 4px auto 10px;
                animation: staffSpin 0.9s linear infinite;
            }

            #staff-loading-overlay .staff-loading-title {
                font-weight: 700;
                margin-bottom: 4px;
            }

            #staff-loading-overlay .staff-loading-sub {
                color: #64748b;
                font-size: 0.9rem;
                margin: 0;
            }

            @keyframes staffSpin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
        </style>

        <div id="staff-loading-overlay" aria-hidden="true">
            <div class="staff-loading-card">
                <div class="staff-spinner" aria-hidden="true"></div>
                <div class="staff-loading-title" id="staff-loading-title">Procesando...</div>
                <p class="staff-loading-sub" id="staff-loading-sub">Por favor espera un momento</p>
            </div>
        </div>
    <?php endif; ?>

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
    <?php if (isset($currentRoute) && $currentRoute === 'staff'): ?>
    <script>
        (function () {
            var overlay = document.getElementById('staff-loading-overlay');
            var titleEl = document.getElementById('staff-loading-title');
            var subEl = document.getElementById('staff-loading-sub');
            var isBusy = false;

            function showOverlay(title, sub) {
                if (!overlay) return;
                if (titleEl) titleEl.textContent = title || 'Procesando...';
                if (subEl) subEl.textContent = sub || 'Por favor espera un momento';
                overlay.style.display = 'flex';
                overlay.setAttribute('aria-hidden', 'false');
            }

            function hookForm(form, title, sub) {
                if (!form) return;
                form.addEventListener('submit', function () {
                    if (isBusy) return false;
                    isBusy = true;

                    var submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.setAttribute('aria-disabled', 'true');
                    }

                    showOverlay(title, sub);
                    return true;
                });
            }

            document.querySelectorAll('form[action="staff.php"] input[name="do"][value="send_reset"]').forEach(function (input) {
                hookForm(input.closest('form'), 'Enviando correo...', 'Se está enviando el reseteo de contraseña');
            });

            var createDo = document.querySelector('#agentCreateModal form[action="staff.php"] input[name="do"][value="create"]');
            if (createDo) {
                hookForm(createDo.closest('form'), 'Creando agente...', 'Guardando y enviando correo si aplica');
            }
        })();
    </script>
    <?php endif; ?>
    <?php if (isset($currentRoute) && $currentRoute === 'logs'): ?>
    <script src="js/logs.js"></script>
    <?php endif; ?>
</body>
</html>