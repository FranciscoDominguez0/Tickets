<?php
/**
 * DEPARTAMENTOS
 * Gestionar departamentos del sistema
 */

require_once '../config.php';
require_once '../includes/helpers.php';
require_once '../includes/Auth.php';

requireLogin('agente');

// Obtener departamentos con estadísticas
$stmt = $mysqli->prepare(
    'SELECT d.id, d.name, d.description, d.is_active,
            COUNT(t.id) as total_tickets,
            SUM(CASE WHEN t.status_id = 1 THEN 1 ELSE 0 END) as open_tickets
     FROM departments d
     LEFT JOIN tickets t ON d.id = t.dept_id
     GROUP BY d.id, d.name, d.description, d.is_active
     ORDER BY d.name'
);
$stmt->execute();
$departments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Departamentos - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <style>
        body {
            background: #f5f7fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
        }
        
        .dept-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            border-left: 5px solid #27ae60;
        }
        
        .dept-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
        }
        
        .dept-description {
            color: #666;
            font-size: 0.95rem;
            margin-bottom: 15px;
        }
        
        .dept-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-top: 10px;
        }
        
        .stat-item {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            text-align: center;
        }
        
        .stat-number {
            font-weight: 700;
            color: #27ae60;
            font-size: 1.3rem;
        }
        
        .stat-label {
            font-size: 0.85rem;
            color: #999;
        }
    </style>
</head>
<body>
    <!-- NAVBAR -->
    <nav class="navbar navbar-dark">
        <div class="container-fluid">
            <span class="navbar-brand">🛠️ <?php echo APP_NAME; ?></span>
            <div class="d-flex align-items-center gap-3">
                <a href="dashboard.php" class="btn btn-sm btn-outline-light">← Volver</a>
                <a href="logout.php" class="btn btn-outline-light btn-sm">Cerrar Sesión</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h2>Departamentos</h2>
        <p class="text-muted">Total: <strong><?php echo count($departments); ?></strong> departamentos</p>

        <div class="row">
            <?php foreach ($departments as $dept): ?>
                <div class="col-md-6">
                    <div class="dept-card">
                        <div class="dept-title">
                            📂 <?php echo html($dept['name']); ?>
                            <span class="badge" style="background: <?php echo $dept['is_active'] ? '#27ae60' : '#e74c3c'; ?>; color: white; float: right;">
                                <?php echo $dept['is_active'] ? 'Activo' : 'Inactivo'; ?>
                            </span>
                        </div>
                        <div class="dept-description">
                            <?php echo html($dept['description'] ?? 'Sin descripción'); ?>
                        </div>
                        <div class="dept-stats">
                            <div class="stat-item">
                                <div class="stat-number"><?php echo $dept['total_tickets'] ?? 0; ?></div>
                                <div class="stat-label">Total</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number"><?php echo $dept['open_tickets'] ?? 0; ?></div>
                                <div class="stat-label">Abiertos</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number">0</div>
                                <div class="stat-label">Agentes</div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
