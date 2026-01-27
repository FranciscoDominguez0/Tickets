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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #f5f7fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .navbar {
            background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 50%, #0ea5e9 100%);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.4rem;
        }

        .layout {
            display: flex;
            min-height: calc(100vh - 64px);
        }

        .sidebar {
            width: 240px;
            background: #020617;
            color: #e5e7eb;
            padding: 24px 16px;
        }

        .sidebar-logo {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #e5e7eb;
        }

        .sidebar-section-title {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #4b5563;
            margin: 16px 0 8px;
        }

        .sidebar-nav {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border-radius: 8px;
            color: #9ca3af;
            text-decoration: none;
            font-size: 0.9rem;
            margin-bottom: 4px;
            transition: background .15s, color .15s, transform .1s;
        }

        .sidebar-link span.icon {
            display: inline-flex;
            width: 20px;
            justify-content: center;
        }

        .sidebar-link:hover {
            background: #0b1120;
            color: #e5e7eb;
            transform: translateX(1px);
        }

        .sidebar-toggle {
            width: 100%;
            background: none;
            border: none;
            text-align: left;
            padding: 0;
        }

        .sidebar-subnav {
            list-style: none;
            margin: 6px 0 10px 0;
            padding-left: 32px;
            border-left: 2px solid rgba(37, 99, 235, 0.6);
            max-height: 0;
            overflow: hidden;
            transition: max-height .18s ease-out, opacity .15s ease-out;
            opacity: 0;
        }

        .sidebar-subnav.open {
            max-height: 400px;
            opacity: 1;
        }

        .sidebar-subnav .sidebar-link {
            padding: 7px 8px;
            font-size: 0.84rem;
            color: #9ca3af;
        }

        .sidebar-subnav .sidebar-link.active {
            color: #e5e7eb;
        }

        .sidebar-toggle .arrow {
            margin-left: auto;
            display: inline-flex;
            transition: transform .15s ease-out;
        }

        .sidebar-toggle.expanded .arrow {
            transform: rotate(90deg);
        }

        .sidebar-link.active {
            background: linear-gradient(135deg, #1d4ed8, #0ea5e9);
            color: #f9fafb;
        }

        .sidebar-footer {
            margin-top: 24px;
            font-size: 0.8rem;
            color: #4b5563;
        }

        .main-shell {
            flex: 1;
            padding: 24px 24px 40px;
        }

        .container-main {
            max-width: 1200px;
            margin: 0 auto;
        }

        .welcome-card {
            background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 40%, #0ea5e9 100%);
            color: white;
            padding: 40px;
            border-radius: 10px;
            margin-bottom: 40px;
            box-shadow: 0 5px 20px rgba(39, 174, 96, 0.3);
        }

        .welcome-card h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }

        .welcome-card p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: #ffffff;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 5px solid #1d4ed8;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #1d4ed8;
        }

        .stat-label {
            color: #666;
            margin-top: 10px;
        }

        .top-tabs {
            margin-bottom: 25px;
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .top-tabs-nav {
            display: flex;
            gap: 20px;
        }

        .top-tab-link {
            padding: 14px 0;
            font-size: 0.95rem;
            font-weight: 500;
            color: #555;
            text-decoration: none;
            border-bottom: 3px solid transparent;
        }

        .top-tab-link.active {
            color: #1d4ed8;
            border-bottom-color: #1d4ed8;
        }

        .ticket-filters {
            margin-top: 10px;
            margin-bottom: 10px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .badge-priority {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            color: #fff;
        }

        .badge-status {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            background: #2563eb;
            color: #fff;
        }
    </style>
</head>
<body>
    <!-- NAVBAR -->
    <nav class="navbar navbar-dark">
        <div class="container-fluid">
            <span class="navbar-brand"><?php echo APP_NAME; ?> - Agente</span>
            <div class="d-flex align-items-center gap-3">
                <span style="color: white;">Agente: <strong><?php echo html($staff['name']); ?></strong></span>
                <a href="logout.php" class="btn btn-outline-light btn-sm">Cerrar Sesión</a>
            </div>
        </div>
    </nav>

    <!-- LAYOUT PRINCIPAL -->
    <div class="layout">
        <!-- SIDEBAR LATERAL -->
        <aside class="sidebar">
            <div class="sidebar-logo">
                <span class="icon">
                    <img src="../../publico/img/vigitec-logo.png" alt="Vigitec Panama" style="height:34px; width:auto; display:block;" />
                </span>
            </div>

            <div class="sidebar-section">
                <div class="sidebar-section-title">Principal</div>
                <ul class="sidebar-nav">
                    <li class="sidebar-group">
                        <button type="button"
                                class="sidebar-link sidebar-toggle <?php echo in_array($currentRoute, ['dashboard','directory','profile']) ? 'active expanded' : ''; ?>"
                                data-subnav="panel-subnav">
                            <span class="icon">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M4 12L11 5L18 12V19H4V12Z" stroke="#e5e7eb" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>
                            Panel de control
                            <span class="arrow">
                                <svg width="12" height="12" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M7 5L12 10L7 15" stroke="#9ca3af" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>
                        </button>
                        <ul id="panel-subnav" class="sidebar-subnav <?php echo in_array($currentRoute, ['dashboard','directory','profile']) ? 'open' : ''; ?>">
                            <li>
                                <a href="dashboard.php" class="sidebar-link <?php echo $currentRoute === 'dashboard' ? 'active' : ''; ?>">
                                    <span class="icon">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <rect x="4" y="4" width="16" height="16" rx="3" stroke="#64748b" stroke-width="1.5"/>
                                            <path d="M9 12L11 14L15 10" stroke="#22c55e" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </span>
                                    Resumen
                                </a>
                            </li>
                            <li>
                                <a href="directory.php" class="sidebar-link <?php echo $currentRoute === 'directory' ? 'active' : ''; ?>">
                                    <span class="icon">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M5 5H14L19 10V19H5V5Z" stroke="#64748b" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                            <path d="M9 13H15" stroke="#64748b" stroke-width="1.5" stroke-linecap="round"/>
                                        </svg>
                                    </span>
                                    Directorio del agente
                                </a>
                            </li>
                            <li>
                                <a href="profile.php" class="sidebar-link <?php echo $currentRoute === 'profile' ? 'active' : ''; ?>">
                                    <span class="icon">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <circle cx="12" cy="8" r="3" stroke="#64748b" stroke-width="1.5"/>
                                            <path d="M6 19C6.6 16.5 8.8 15 12 15C15.2 15 17.4 16.5 18 19" stroke="#64748b" stroke-width="1.5" stroke-linecap="round"/>
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
                                    <rect x="4" y="4" width="16" height="16" rx="2" stroke="#9ca3af" stroke-width="1.6"/>
                                    <path d="M8 9H16" stroke="#9ca3af" stroke-width="1.6" stroke-linecap="round"/>
                                    <path d="M8 13H13" stroke="#9ca3af" stroke-width="1.6" stroke-linecap="round"/>
                                </svg>
                            </span>
                            Solicitudes
                        </a>
                    </li>
                    <li>
                        <a href="users.php" class="sidebar-link <?php echo $currentRoute === 'users' ? 'active' : ''; ?>">
                            <span class="icon">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <circle cx="9" cy="8" r="3" stroke="#9ca3af" stroke-width="1.6"/>
                                    <path d="M4 19C4.6 16 6.5 14.5 9 14.5C11.5 14.5 13.4 16 14 19" stroke="#9ca3af" stroke-width="1.6" stroke-linecap="round"/>
                                    <circle cx="17" cy="8" r="2.5" stroke="#9ca3af" stroke-width="1.4"/>
                                </svg>
                            </span>
                            Usuarios
                        </a>
                    </li>
                    <li>
                        <a href="tasks.php" class="sidebar-link <?php echo $currentRoute === 'tasks' ? 'active' : ''; ?>">
                            <span class="icon">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <rect x="4" y="5" width="16" height="14" rx="2" stroke="#9ca3af" stroke-width="1.6"/>
                                    <path d="M4 9H20" stroke="#9ca3af" stroke-width="1.6" stroke-linecap="round"/>
                                </svg>
                            </span>
                            Tareas
                        </a>
                    </li>
                    <li>
                        <a href="canned.php" class="sidebar-link <?php echo $currentRoute === 'canned' ? 'active' : ''; ?>">
                            <span class="icon">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M5 4H14L19 9V20H5V4Z" stroke="#9ca3af" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M9 12H15" stroke="#9ca3af" stroke-width="1.6" stroke-linecap="round"/>
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
                        <a href="orgs.php" class="sidebar-link <?php echo $currentRoute === 'orgs' ? 'active' : ''; ?>">
                            <span class="icon">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <rect x="4" y="8" width="16" height="10" rx="2" stroke="#9ca3af" stroke-width="1.6"/>
                                    <path d="M9 8V6C9 4.89543 9.89543 4 11 4H13C14.1046 4 15 4.89543 15 6V8" stroke="#9ca3af" stroke-width="1.6" stroke-linecap="round"/>
                                </svg>
                            </span>
                            Organizaciones
                        </a>
                    </li>
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
    <script>
        // Toggle del submenú de Panel de control
        document.addEventListener('DOMContentLoaded', function () {
            var toggles = document.querySelectorAll('.sidebar-toggle');
            toggles.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var targetId = btn.getAttribute('data-subnav');
                    if (!targetId) return;
                    var subnav = document.getElementById(targetId);
                    if (!subnav) return;
                    var isOpen = subnav.classList.toggle('open');
                    btn.classList.toggle('expanded', isOpen);
                });
            });
        });
    </script>
</body>
</html>

