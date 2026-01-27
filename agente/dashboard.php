<?php
/**
 * DASHBOARD AGENTE
 * Panel principal de gestión de tickets
 */

require_once '../config.php';
require_once '../includes/helpers.php';
require_once '../includes/Auth.php';

// Validar que sea agente
requireLogin('agente');

$staff = getCurrentUser();

// Obtener estadísticas
$stats = [
    'total_tickets' => 0,
    'open_tickets' => 0,
    'assigned_to_me' => 0
];

$stmt = $mysqli->prepare('SELECT COUNT(*) as count FROM tickets WHERE status_id = 1');
$stmt->execute();
$result = $stmt->get_result();
$stats['open_tickets'] = $result->fetch_assoc()['count'];

$stmt = $mysqli->prepare('SELECT COUNT(*) as count FROM tickets WHERE staff_id = ?');
$stmt->bind_param('i', $_SESSION['staff_id']);
$stmt->execute();
$result = $stmt->get_result();
$stats['assigned_to_me'] = $result->fetch_assoc()['count'];

$stmt = $mysqli->prepare('SELECT COUNT(*) as count FROM tickets');
$stmt->execute();
$result = $stmt->get_result();
$stats['total_tickets'] = $result->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo APP_NAME; ?></title>
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
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .sidebar {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-right: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            height: fit-content;
        }
        
        .nav-link {
            color: #333;
            padding: 10px 15px;
            border-left: 3px solid transparent;
            margin-bottom: 8px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: block;
        }
        
        .nav-link:hover,
        .nav-link.active {
            color: #27ae60;
            background: #f0f0f0;
            border-left-color: #27ae60;
        }
        
        .main-content {
            padding: 20px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 5px solid #27ae60;
            margin-bottom: 20px;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #27ae60;
        }
        
        .stat-label {
            color: #666;
            margin-top: 10px;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <!-- NAVBAR -->
    <nav class="navbar navbar-dark">
        <div class="container-fluid">
            <span class="navbar-brand">🛠️ <?php echo APP_NAME; ?></span>
            <div class="d-flex align-items-center gap-3">
                <span style="color: white;">Agente: <strong><?php echo html($staff['name']); ?></strong></span>
                <a href="logout.php" class="btn btn-outline-light btn-sm">Cerrar Sesión</a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="row">
            <!-- SIDEBAR -->
            <div class="col-md-3">
                <div class="sidebar">
                    <h6 class="mb-3" style="color: #27ae60; font-weight: 600;">MENÚ</h6>
                    <a href="dashboard.php" class="nav-link active">📊 Dashboard</a>
                    <a href="tickets.php" class="nav-link">📂 Mis Tickets</a>
                    <a href="todos-tickets.php" class="nav-link">📋 Todos los Tickets</a>
                    <a href="usuarios.php" class="nav-link">👥 Usuarios</a>
                    <a href="departamentos.php" class="nav-link">🏢 Departamentos</a>
                    <a href="configuracion.php" class="nav-link">⚙️ Configuración</a>
                    <a href="perfil.php" class="nav-link">👤 Mi Perfil</a>
                    <hr>
                    <a href="logout.php" class="nav-link" style="color: #e74c3c;">🚪 Cerrar Sesión</a>
                </div>
            </div>

            <!-- MAIN CONTENT -->
            <div class="col-md-9">
                <div class="main-content">
                    <h2 style="margin-bottom: 30px;">Dashboard</h2>

                    <!-- ESTADÍSTICAS -->
                    <div class="row">
                        <div class="col-md-4">
                            <div class="stat-card">
                                <div class="stat-number"><?php echo $stats['total_tickets']; ?></div>
                                <div class="stat-label">Total de Tickets</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-card">
                                <div class="stat-number"><?php echo $stats['open_tickets']; ?></div>
                                <div class="stat-label">Tickets Abiertos</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-card">
                                <div class="stat-number"><?php echo $stats['assigned_to_me']; ?></div>
                                <div class="stat-label">Asignados a Mí</div>
                            </div>
                        </div>
                    </div>

                    <!-- ACCIONES RÁPIDAS -->
                    <div style="background: white; padding: 20px; border-radius: 8px; margin-top: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                        <h5 style="margin-bottom: 15px; color: #333;">Acciones Rápidas</h5>
                        <a href="tickets.php" class="btn btn-primary me-2">Ver Mis Tickets</a>
                        <a href="crear-ticket.php" class="btn btn-success me-2">Crear Ticket</a>
                        <a href="usuarios.php" class="btn btn-info me-2">Gestionar Usuarios</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
