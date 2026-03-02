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
<body class="superadmin<?php echo $isDarkMode ? ' superadmin-dark' : ''; ?>">
<nav class="navbar navbar-dark">
    <div class="container-fluid">
        <span class="navbar-brand"><?php echo APP_NAME; ?> - SuperAdmin</span>
        <div class="d-flex align-items-center gap-3">
            <form method="post" action="toggle_dark.php" class="d-inline" style="margin:0">
                <?php csrfField(); ?>
                <input type="hidden" name="dark_mode" value="<?php echo $isDarkMode ? '0' : '1'; ?>">
                <input type="hidden" name="return" value="<?php echo html(basename((string)($_SERVER['PHP_SELF'] ?? 'index.php')) . (!empty($_SERVER['QUERY_STRING']) ? ('?' . (string)$_SERVER['QUERY_STRING']) : '')); ?>">
                <button type="submit" class="btn btn-outline-light btn-sm" title="Modo oscuro">
                    <i class="bi <?php echo $isDarkMode ? 'bi-sun' : 'bi-moon-stars'; ?>"></i>
                </button>
            </form>
            <span style="color: white;">SuperAdmin: <strong><?php echo html($staffName); ?></strong></span>
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
                    <a href="superadmins.php" class="sidebar-link <?php echo $currentRoute === 'superadmins' ? 'active' : ''; ?>">
                        <span class="icon">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M16 21V19A4 4 0 0 0 12 15H7A4 4 0 0 0 3 19V21" stroke="<?php echo $currentRoute === 'superadmins' ? '#ffffff' : '#9ca3af'; ?>" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M9.5 11A4 4 0 1 0 9.5 3A4 4 0 1 0 9.5 11Z" stroke="<?php echo $currentRoute === 'superadmins' ? '#ffffff' : '#9ca3af'; ?>" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M20 8V14" stroke="<?php echo $currentRoute === 'superadmins' ? '#ffffff' : '#9ca3af'; ?>" stroke-width="1.8" stroke-linecap="round"/>
                                <path d="M23 11H17" stroke="<?php echo $currentRoute === 'superadmins' ? '#ffffff' : '#9ca3af'; ?>" stroke-width="1.8" stroke-linecap="round"/>
                            </svg>
                        </span>
                        Superadmins
                    </a>
                </li>
                <li>
                    <a href="empresas.php" class="sidebar-link <?php echo $currentRoute === 'empresas' ? 'active' : ''; ?>">
                        <span class="icon">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect x="4" y="7" width="16" height="13" rx="2" stroke="<?php echo $currentRoute === 'empresas' ? '#ffffff' : '#9ca3af'; ?>" stroke-width="1.8"/>
                                <path d="M8 7V5" stroke="<?php echo $currentRoute === 'empresas' ? '#ffffff' : '#9ca3af'; ?>" stroke-width="1.8" stroke-linecap="round"/>
                                <path d="M16 7V5" stroke="<?php echo $currentRoute === 'empresas' ? '#ffffff' : '#9ca3af'; ?>" stroke-width="1.8" stroke-linecap="round"/>
                            </svg>
                        </span>
                        Empresas
                    </a>
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
                    <a href="notificaciones.php" class="sidebar-link <?php echo $currentRoute === 'notificaciones' ? 'active' : ''; ?>">
                        <span class="icon">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M18 8A6 6 0 0 0 6 8C6 15 4 15 4 15H20C20 15 18 15 18 8Z" stroke="<?php echo $currentRoute === 'notificaciones' ? '#ffffff' : '#9ca3af'; ?>" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M13.73 21A2 2 0 0 1 10.27 21" stroke="<?php echo $currentRoute === 'notificaciones' ? '#ffffff' : '#9ca3af'; ?>" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                        Notificaciones
                    </a>
                </li>
                <li>
                    <a href="configuracion.php" class="sidebar-link <?php echo $currentRoute === 'configuracion' ? 'active' : ''; ?>">
                        <span class="icon"><i class="bi bi-gear"></i></span>
                        Configuración
                    </a>
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

        <div class="sidebar-footer">
            <div>&copy; VigitecPanama</div>
        </div>
    </aside>

    <main class="main-shell">
        <div class="container-main">
            <?php echo $content; ?>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../js/scp.js"></script>
</body>
</html>
