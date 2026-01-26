<?php
/**
 * HOME CLIENTE
 * Dashboard principal del cliente - VERSIÓN MEJORADA
 */

require_once '../config.php';
require_once '../includes/helpers.php';
require_once '../includes/Auth.php';

// Validar que sea cliente
requireLogin('cliente');

$user = getCurrentUser();

// Simulación de datos (reemplazar con consultas a BD reales)
$stats = [
    'tickets_total' => 5,
    'tickets_abiertos' => 2,
    'tickets_resueltos' => 3,
    'tiempo_promedio' => '2.5 horas'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: linear-gradient(135deg, #ecf0f1 0%, #e8eef5 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #2c3e50;
        }
        
        /* NAVBAR */
        .navbar-custom {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.15);
            padding: 1rem 0;
            border-bottom: 3px solid #3498db;
        }
        
        .navbar-brand {
            font-weight: 800;
            font-size: 1.4rem;
            color: white !important;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        
        .nav-user {
            color: rgba(255,255,255,0.85);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .nav-user strong {
            color: white;
        }
        
        .btn-logout {
            background: rgba(52, 152, 219, 0.8) !important;
            border: 1px solid rgba(52, 152, 219, 1) !important;
            color: white !important;
            padding: 7px 18px !important;
            border-radius: 5px !important;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }
        
        .btn-logout:hover {
            background: #3498db !important;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
        }
        
        /* CONTENIDO PRINCIPAL */
        .container-main {
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        
        /* TARJETA DE BIENVENIDA */
        .welcome-section {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            padding: 45px 40px;
            border-radius: 10px;
            margin-bottom: 50px;
            box-shadow: 0 8px 25px rgba(52, 152, 219, 0.25);
            position: relative;
            overflow: hidden;
        }
        
        .welcome-section::before {
            content: '';
            position: absolute;
            top: -40%;
            right: -5%;
            width: 300px;
            height: 300px;
            background: rgba(255,255,255,0.08);
            border-radius: 50%;
        }
        
        .welcome-section h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
        }
        
        .welcome-section p {
            font-size: 1rem;
            opacity: 0.9;
            position: relative;
            z-index: 1;
            margin: 0;
        }
        
        /* ESTADÍSTICAS */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 50px;
        }
        
        .stat-card {
            background: white;
            padding: 30px 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            text-align: center;
            transition: all 0.3s ease;
            border-top: 4px solid #3498db;
        }
        
        .stat-card:nth-child(2) {
            border-top-color: #e74c3c;
        }
        
        .stat-card:nth-child(3) {
            border-top-color: #27ae60;
        }
        
        .stat-card:nth-child(4) {
            border-top-color: #f39c12;
        }
        
        .stat-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 8px 20px rgba(52, 152, 219, 0.15);
        }
        
        .stat-icon {
            font-size: 2.2rem;
            margin-bottom: 12px;
            color: #3498db;
        }
        
        .stat-card:nth-child(2) .stat-icon {
            color: #e74c3c;
        }
        
        .stat-card:nth-child(3) .stat-icon {
            color: #27ae60;
        }
        
        .stat-card:nth-child(4) .stat-icon {
            color: #f39c12;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #7f8c8d;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        /* TARJETAS DE ACCIONES */
        .actions-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 50px;
        }
        
        .action-card {
            background: white;
            border: none;
            border-radius: 8px;
            padding: 0;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .action-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 30px rgba(52, 152, 219, 0.2);
        }
        
        .action-card-header {
            background: linear-gradient(135deg, #34495e 0%, #2c3e50 100%);
            padding: 35px 20px;
            text-align: center;
            color: white;
        }
        
        .action-card:nth-child(1) .action-card-header {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
        }
        
        .action-card:nth-child(2) .action-card-header {
            background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);
        }
        
        .action-card:nth-child(3) .action-card-header {
            background: linear-gradient(135deg, #1abc9c 0%, #16a085 100%);
        }
        
        .action-card-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }
        
        .action-card-body {
            padding: 25px;
            text-align: center;
        }
        
        .action-card h5 {
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 12px;
            font-size: 1.15rem;
        }
        
        .action-card p {
            color: #7f8c8d;
            font-size: 0.95rem;
            margin-bottom: 20px;
            line-height: 1.5;
        }
        
        .btn-action {
            background: linear-gradient(135deg, #34495e 0%, #2c3e50 100%);
            border: none;
            color: white;
            padding: 11px 28px;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            font-weight: 600;
            font-size: 0.95rem;
        }
        
        .action-card:nth-child(1) .btn-action {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
        }
        
        .action-card:nth-child(2) .btn-action {
            background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);
        }
        
        .action-card:nth-child(3) .btn-action {
            background: linear-gradient(135deg, #1abc9c 0%, #16a085 100%);
        }
        
        .btn-action:hover {
            transform: scale(1.05);
            color: white;
            text-decoration: none;
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        }
        
        /* INFORMACIÓN DE CUENTA */
        .info-section {
            background: white;
            padding: 35px 40px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 40px;
        }
        
        .info-section h3 {
            color: #2c3e50;
            font-weight: 700;
            margin-bottom: 25px;
            font-size: 1.3rem;
            border-bottom: 3px solid #3498db;
            padding-bottom: 15px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 30px;
        }
        
        .info-item {
            padding: 0;
        }
        
        .info-label {
            color: #7f8c8d;
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 10px;
            letter-spacing: 0.5px;
        }
        
        .info-value {
            color: #2c3e50;
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        .status-active {
            color: #27ae60;
            font-weight: 700;
        }
        
        /* FOOTER */
        .footer-custom {
            background: rgba(44, 62, 80, 0.05);
            padding: 25px;
            text-align: center;
            color: #7f8c8d;
            margin-top: 60px;
            border-top: 1px solid #ddd;
            font-size: 0.9rem;
        }
        
        /* RESPONSIVE */
        @media (max-width: 768px) {
            .welcome-section {
                padding: 30px 20px;
            }
            
            .welcome-section h1 {
                font-size: 1.8rem;
            }
            
            .container-main {
                padding: 20px 15px;
            }
            
            .nav-user {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <!-- NAVBAR -->
    <nav class="navbar navbar-dark navbar-custom">
        <div class="container-fluid">
            <span class="navbar-brand">
                <i class="fas fa-ticket-alt"></i> SUPPORT CENTER
            </span>
            <div class="nav-user">
                <span>
                    Usuario: <strong><?php echo html($user['name']); ?></strong>
            </div>
        </div>
    </nav>

    <!-- CONTENIDO PRINCIPAL -->
    <div class="container-main">
        <!-- SECCIÓN DE BIENVENIDA -->
        <div class="welcome-section">
            <h1>
                Bienvenido, <?php echo html(explode(' ', $user['name'])[0]); ?>
            </h1>
            <p>Tu panel de control de tickets. Crea, gestiona y sigue el estado de tus solicitudes.</p>
        </div>

        <!-- ESTADÍSTICAS -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-list"></i>
                </div>
                <div class="stat-number"><?php echo $stats['tickets_total']; ?></div>
                <div class="stat-label">Total de Tickets</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-number"><?php echo $stats['tickets_abiertos']; ?></div>
                <div class="stat-label">Pendientes</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-check"></i>
                </div>
                <div class="stat-number"><?php echo $stats['tickets_resueltos']; ?></div>
                <div class="stat-label">Resueltos</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-tachometer-alt"></i>
                </div>
                <div class="stat-number"><?php echo $stats['tiempo_promedio']; ?></div>
                <div class="stat-label">Tiempo Respuesta</div>
            </div>
        </div>

        <!-- TARJETAS DE ACCIONES -->
        <div class="actions-container">
            <!-- Crear Ticket -->
            <div class="action-card">
                <div class="action-card-header">
                    <div class="action-card-icon">
                        <i class="fas fa-plus"></i>
                    </div>
                </div>
                <div class="action-card-body">
                    <h5>Abrir nuevo Ticket</h5>
                    <p>Crea una nueva solicitud para reportar un problema o pedir ayuda al equipo de soporte.</p>
                    <a href="crear-ticket.php" class="btn-action">
                        Crear Ticket
                    </a>
                </div>
            </div>

            <!-- Mis Tickets -->
            <div class="action-card">
                <div class="action-card-header">
                    <div class="action-card-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                </div>
                <div class="action-card-body">
                    <h5>Ver Estado de Tickets</h5>
                    <p>Accede al historial completo de tus solicitudes y sigue el progreso en tiempo real.</p>
                    <a href="mis-tickets.php" class="btn-action">
                        Ver Mis Tickets
                    </a>
                </div>
            </div>

            <!-- Mi Perfil -->
            <div class="action-card">
                <div class="action-card-header">
                    <div class="action-card-icon">
                        <i class="fas fa-user"></i>
                    </div>
                </div>
                <div class="action-card-body">
                    <h5>Configurar Perfil</h5>
                    <p>Edita tu información personal, contraseña y opciones de notificación.</p>
                    <a href="perfil.php" class="btn-action">
                        Mi Perfil
                    </a>
                </div>
            </div>
        </div>

        <!-- INFORMACIÓN DE CUENTA -->
        <div class="info-section">
            <h3>
                Información de tu Cuenta
            </h3>
            <div class="info-grid">
                <div>
                    <div class="info-label">Correo Electrónico</div>
                    <div class="info-value"><?php echo html($user['email']); ?></div>
                </div>
                <div>
                    <div class="info-label">Miembro desde</div>
                    <div class="info-value">Enero 2025</div>
                </div>
                <div>
                    <div class="info-label">Estado de Cuenta</div>
                    <div class="info-value"><span class="status-active">Activo</span></div>
                </div>
            </div>
        </div>
    </div>

    <!-- FOOTER -->
    <div class="footer-custom">
        <p>&copy; 2025 <?php echo APP_NAME; ?> - Todos los derechos reservados</p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Animación suave al cargar la página
        window.addEventListener('load', () => {
            document.querySelectorAll('.stat-card, .action-card').forEach((el, index) => {
                el.style.opacity = '0';
                el.style.animation = `fadeInUp 0.6s ease-out ${index * 0.1}s forwards`;
            });
        });
    </script>
    <style>
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</body>
</html>
