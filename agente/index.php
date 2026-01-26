<?php
/**
 * HOME AGENTE
 * Dashboard principal del agente
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
    <title>Dashboard Agente - <?php echo APP_NAME; ?></title>
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
        
        .navbar-brand {
            font-weight: 700;
            font-size: 1.4rem;
        }
        
        .container-main {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .welcome-card {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
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
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 5px solid #27ae60;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #27ae60;
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
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
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
    </style>
</head>
<body>
    <!-- NAVBAR -->
    <nav class="navbar navbar-dark">
        <div class="container-fluid">
            <span class="navbar-brand">🛠️ <?php echo APP_NAME; ?> - Agente</span>
            <div class="d-flex align-items-center gap-3">
                <span style="color: white;">Agente: <strong><?php echo html($staff['name']); ?></strong></span>
                <a href="logout.php" class="btn btn-outline-light btn-sm">Cerrar Sesión</a>
            </div>
        </div>
    </nav>

    <!-- CONTENIDO PRINCIPAL -->
    <div class="container-main">
        <!-- BIENVENIDA -->
        <div class="welcome-card">
            <h1>👋 ¡Bienvenido, <?php echo html(explode(' ', $staff['name'])[0]); ?>!</h1>
            <p>Panel de control para gestionar y resolver tickets de clientes.</p>
        </div>

        <!-- ESTADÍSTICAS -->
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

        <!-- TARJETAS DE ACCIÓN -->
        <div class="action-cards">
            <!-- Ver Tickets -->
            <div class="card">
                <div class="card-body">
                    <div class="card-icon">📂</div>
                    <h5>Mis Tickets</h5>
                    <p>Visualiza los tickets asignados a ti y gestiona su estado.</p>
                    <a href="mis-tickets.php" class="btn-action">Ver Tickets</a>
                </div>
            </div>

            <!-- Todos los Tickets -->
            <div class="card">
                <div class="card-body">
                    <div class="card-icon">📋</div>
                    <h5>Todos los Tickets</h5>
                    <p>Consulta todos los tickets del sistema y asignatarios.</p>
                    <a href="todos-tickets.php" class="btn-action">Ver Todos</a>
                </div>
            </div>

            <!-- Mi Perfil -->
            <div class="card">
                <div class="card-body">
                    <div class="card-icon">👤</div>
                    <h5>Mi Perfil</h5>
                    <p>Edita tu información personal y preferencias de trabajo.</p>
                    <a href="perfil.php" class="btn-action">Editar Perfil</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
