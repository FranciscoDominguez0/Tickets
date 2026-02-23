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
<body>
    <nav class="navbar navbar-dark">
        <div class="container-fluid">
            <span class="navbar-brand"><?php echo APP_NAME; ?> - SuperAdmin</span>
            <div class="d-flex align-items-center gap-3">
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
                        <a href="index.php" class="sidebar-link active">
                            <span class="icon">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <rect x="4" y="4" width="16" height="16" rx="3" stroke="#ffffff" stroke-width="1.8"/>
                                    <path d="M9 12L11 14L15 10" stroke="#ffffff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>
                            Panel
                        </a>
                    </li>
                    <li>
                        <a href="#" class="sidebar-link">
                            <span class="icon">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M4 7H20" stroke="#9ca3af" stroke-width="1.8" stroke-linecap="round"/>
                                    <path d="M4 12H20" stroke="#9ca3af" stroke-width="1.8" stroke-linecap="round"/>
                                    <path d="M4 17H20" stroke="#9ca3af" stroke-width="1.8" stroke-linecap="round"/>
                                </svg>
                            </span>
                            Empresas
                        </a>
                    </li>
                    <li>
                        <a href="#" class="sidebar-link">
                            <span class="icon">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <circle cx="9" cy="8" r="3" stroke="#9ca3af" stroke-width="1.8"/>
                                    <path d="M4 19C4.6 16 6.5 14.5 9 14.5C11.5 14.5 13.4 16 14 19" stroke="#9ca3af" stroke-width="1.8" stroke-linecap="round"/>
                                    <path d="M17 14V19" stroke="#9ca3af" stroke-width="1.8" stroke-linecap="round"/>
                                    <path d="M19.5 16.5H14.5" stroke="#9ca3af" stroke-width="1.8" stroke-linecap="round"/>
                                </svg>
                            </span>
                            Administradores
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
                <div>Sesión iniciada como<br><strong><?php echo html($staffName); ?></strong></div>
            </div>
        </aside>

        <main class="main-shell">
            <div class="container-main">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <h1 class="h3 m-0">SuperAdmin</h1>
                    <a href="../logout.php" class="btn btn-outline-danger btn-sm">Salir</a>
                </div>

                <div class="card mt-3">
                    <div class="card-body">
                        <div class="fw-semibold mb-2">Empresas</div>
                        <div class="text-muted">Panel inicial. Aquí irá la administración de empresas (crear/activar/desactivar) y usuarios administradores por empresa.</div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/scp.js"></script>
</body>
</html>
