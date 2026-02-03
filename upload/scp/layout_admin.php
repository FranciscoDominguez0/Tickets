<?php
// Layout para panel administrador
// Similar al layout de agentes pero con sidebar de administración
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Administrador - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/scp.css">
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
                <span class="icon">
                    <img src="../../publico/img/vigitec-logo.png" alt="Vigitec Panama" style="height:34px; width:auto; display:block;" />
                </span>
            </div>

            <div class="sidebar-section">
                <div class="sidebar-section-title">Panel Admin</div>
                <ul class="sidebar-nav">
                    <li class="sidebar-group">
                        <a href="settings.php" class="sidebar-link <?php echo ($currentRoute === 'settings') ? 'active' : ''; ?>">
                            <span class="icon"><i class="bi bi-gear"></i></span>
                            Configuración
                        </a>
                        <a href="logs.php" class="sidebar-link <?php echo ($currentRoute === 'logs') ? 'active' : ''; ?>">
                            <span class="icon"><i class="bi bi-graph-up"></i></span>
                            Panel de Control
                        </a>
                        <a href="helptopics.php" class="sidebar-link <?php echo ($currentRoute === 'helptopics') ? 'active' : ''; ?>">
                            <span class="icon"><i class="bi bi-list-check"></i></span>
                            Administrar
                        </a>
                        <a href="emails.php" class="sidebar-link <?php echo ($currentRoute === 'emails') ? 'active' : ''; ?>">
                            <span class="icon"><i class="bi bi-envelope"></i></span>
                            Correos Electrónicos
                        </a>
                        <a href="staff.php" class="sidebar-link <?php echo ($currentRoute === 'staff') ? 'active' : ''; ?>">
                            <span class="icon"><i class="bi bi-people"></i></span>
                            Agentes
                        </a>
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
</body>
</html>