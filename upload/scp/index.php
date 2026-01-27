<?php
/**
 * PANEL AGENTE/ADMIN
 * Dashboard principal del agente
 */

require_once '../../config.php';
require_once '../../includes/helpers.php';
require_once '../../includes/Auth.php';

// Si no está logueado, redirigir al login
if (!isset($_SESSION['staff_id'])) {
    header('Location: login.php');
    exit;
}

requireLogin('agente');
$staff = getCurrentUser();

// Obtener estadísticas básicas para tarjetas
$stats = [
    'total_tickets'   => 0,
    'open_tickets'    => 0,
    'assigned_to_me'  => 0
];

// Tickets abiertos
$stmt = $mysqli->prepare('SELECT COUNT(*) as count FROM tickets WHERE status_id = 1');
$stmt->execute();
$result = $stmt->get_result();
$stats['open_tickets'] = (int)$result->fetch_assoc()['count'];

// Tickets asignados al agente
$stmt = $mysqli->prepare('SELECT COUNT(*) as count FROM tickets WHERE staff_id = ?');
$stmt->bind_param('i', $_SESSION['staff_id']);
$stmt->execute();
$result = $stmt->get_result();
$stats['assigned_to_me'] = (int)$result->fetch_assoc()['count'];

// Total de tickets
$stmt = $mysqli->prepare('SELECT COUNT(*) as count FROM tickets');
$stmt->execute();
$result = $stmt->get_result();
$stats['total_tickets'] = (int)$result->fetch_assoc()['count'];

// Listado de tickets abiertos (como la pestaña Open de osTicket)
$sqlTickets = "
    SELECT t.*, 
           u.firstname, u.lastname, u.email,
           IFNULL(CONCAT(s.firstname, ' ', s.lastname), 'Sin asignar') AS staff_name,
           ts.name AS status_name,
           p.name  AS priority_name
    FROM tickets t
    JOIN users u        ON t.user_id = u.id
    LEFT JOIN staff s   ON t.staff_id = s.id
    JOIN ticket_status ts ON t.status_id = ts.id
    JOIN priorities p   ON t.priority_id = p.id
    WHERE t.status_id = 1
    ORDER BY t.created DESC
";

$result = $mysqli->query($sqlTickets);
$tickets = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
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
        
        .action-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            overflow: hidden;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .card-icon {
            font-size: 3rem;
            margin-bottom: 15px;
        }
        
        .card h5 {
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
        }
        
        .card p {
            color: #666;
            font-size: 0.9rem;
        }
        
        .btn-action {
            background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 50%, #0ea5e9 100%);
            border: none;
            color: white;
            padding: 10px 25px;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            margin-top: 15px;
        }
        
        .btn-action:hover {
            transform: scale(1.05);
            color: white;
            text-decoration: none;
        }
        
        .card-body {
            padding: 25px;
            text-align: center;
        }

        /* Barra de pestañas tipo osTicket */
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
                    <li>
                        <a href="index.php" class="sidebar-link active">
                            <span class="icon">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M4 12L11 5L18 12V19H4V12Z" stroke="#e5e7eb" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>
                            Panel de control
                        </a>
                    </li>
                    <li>
                        <a href="../../agente/tickets.php" class="sidebar-link">
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
                        <a href="../../agente/usuarios.php" class="sidebar-link">
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
                        <a href="#" class="sidebar-link">
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
                        <a href="#" class="sidebar-link">
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
                        <a href="#" class="sidebar-link">
                            <span class="icon">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <circle cx="12" cy="12" r="3" stroke="#9ca3af" stroke-width="1.6"/>
                                    <path d="M4 12H6M18 12H20M12 4V6M12 18V20M6.5 6.5L7.9 7.9M16.1 16.1L17.5 17.5M6.5 17.5L7.9 16.1M16.1 7.9L17.5 6.5" stroke="#9ca3af" stroke-width="1.4" stroke-linecap="round"/>
                                </svg>
                            </span>
                            Configuración
                        </a>
                    </li>
                    <li>
                        <a href="../../agente/perfil.php" class="sidebar-link">
                            <span class="icon">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <circle cx="12" cy="8" r="3" stroke="#9ca3af" stroke-width="1.6"/>
                                    <path d="M5 20C5.6 16.5 8.3 15 12 15C15.7 15 18.4 16.5 19 20" stroke="#9ca3af" stroke-width="1.6" stroke-linecap="round"/>
                                </svg>
                            </span>
                            Mi perfil
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

        <!-- ZONA PRINCIPAL -->
        <main class="main-shell">
            <div class="container-main">
                <!-- BIENVENIDA -->
                <div class="welcome-card">
                    <h1>¡Bienvenido, <?php echo html(explode(' ', $staff['name'])[0]); ?>!</h1>
                    <p>Panel de control para gestionar y resolver tickets de clientes.</p>
                </div>

                <!-- PESTAÑAS SUPERIORES (como Panel de Control / Solicitudes / etc.) -->
                <div class="top-tabs">
                    <div class="top-tabs-nav">
                        <a href="index.php" class="top-tab-link active">Solicitudes</a>
                        <a href="#" class="top-tab-link">Panel de control</a>
                        <a href="../../agente/usuarios.php" class="top-tab-link">Usuarios</a>
                        <a href="#" class="top-tab-link">Tareas</a>
                        <a href="#" class="top-tab-link">Base de conocimientos</a>
                    </div>
                    <div>
                        <a href="../open.php" class="btn btn-sm btn-success">Nuevo Ticket</a>
                    </div>
                </div>

                <!-- ESTADÍSTICAS RÁPIDAS -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['total_tickets']; ?></div>
                        <div class="stat-label">Total de Tickets</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['open_tickets']; ?></div>
                        <div class="stat-label">Tickets Abiertos</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['assigned_to_me']; ?></div>
                        <div class="stat-label">Asignados a Mí</div>
                    </div>
                </div>

                <!-- LISTADO DE TICKETS (similar a la pestaña Open de osTicket) -->
                <div style="background: #ffffff; padding: 20px; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.06);">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="mb-0">Open tickets</h5>
                        <small class="text-muted">Mostrando <?php echo count($tickets); ?> ticket(s) abiertos</small>
                    </div>

                    <!-- Filtros secundarios -->
                    <div class="ticket-filters">
                        <button class="btn btn-sm btn-outline-secondary active">Open</button>
                        <a href="../../agente/tickets.php" class="btn btn-sm btn-outline-secondary">My Tickets</a>
                        <button class="btn btn-sm btn-outline-secondary" disabled>Closed</button>
                        <button class="btn btn-sm btn-outline-secondary" disabled>Buscar</button>
                    </div>

                    <?php if (empty($tickets)): ?>
                        <div class="alert alert-info mt-3 mb-0">No hay tickets abiertos actualmente.</div>
                    <?php else: ?>
                        <div class="table-responsive mt-2">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 40px;"><input type="checkbox" /></th>
                                        <th>Ticket</th>
                                        <th>Última actualización</th>
                                        <th>Asunto</th>
                                        <th>De</th>
                                        <th>Prioridad</th>
                                        <th>Asignado a</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tickets as $ticket): ?>
                                        <tr>
                                            <td><input type="checkbox" /></td>
                                            <td>
                                                <strong><?php echo html($ticket['ticket_number']); ?></strong>
                                            </td>
                                            <td>
                                                <small><?php echo formatDate($ticket['created']); ?></small>
                                            </td>
                                            <td>
                                                <a href="../../agente/ver-ticket.php?id=<?php echo (int)$ticket['id']; ?>">
                                                    <?php echo html(substr($ticket['subject'], 0, 60)); ?>
                                                </a>
                                            </td>
                                            <td>
                                                <small><?php echo html($ticket['firstname'] . ' ' . $ticket['lastname']); ?></small>
                                            </td>
                                            <td>
                                                <?php
                                                    $priorityColor = '#f39c12';
                                                    if ($ticket['priority_name'] === 'Urgente') {
                                                        $priorityColor = '#e74c3c';
                                                    } elseif ($ticket['priority_name'] === 'Alta') {
                                                        $priorityColor = '#e67e22';
                                                    }
                                                ?>
                                                <span class="badge-priority" style="background: <?php echo $priorityColor; ?>">
                                                    <?php echo html($ticket['priority_name']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small><?php echo html($ticket['staff_name']); ?></small>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
