<?php
/**
 * CREAR TICKET
 * Formulario para que usuarios creen nuevos tickets
 */

require_once '../config.php';
require_once '../includes/helpers.php';

// Validar que sea cliente
requireLogin('cliente');

$user = getCurrentUser();
$error = '';
$success = '';

if ($_POST) {
    if (!validateCSRF()) {
        $error = 'Token de seguridad inválido';
    } else {
        $subject = trim($_POST['subject'] ?? '');
        $body = trim($_POST['body'] ?? '');
        $priority_id = intval($_POST['priority_id'] ?? 2);
        $dept_id = intval($_POST['dept_id'] ?? 1);

        if (empty($subject) || empty($body)) {
            $error = 'Asunto y descripción son requeridos';
        } else {
            // Generar número de ticket
            $ticket_number = 'TKT-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            // Insertar ticket
            $stmt = $mysqli->prepare(
                'INSERT INTO tickets (ticket_number, user_id, dept_id, status_id, priority_id, subject, created)
                 VALUES (?, ?, ?, 1, ?, ?, NOW())'
            );
            $stmt->bind_param('siiis', $ticket_number, $_SESSION['user_id'], $dept_id, $priority_id, $subject);
            
            if ($stmt->execute()) {
                $ticket_id = $mysqli->insert_id;
                
                // Crear thread y primer mensaje
                $stmt2 = $mysqli->prepare('INSERT INTO threads (ticket_id, created) VALUES (?, NOW())');
                $stmt2->bind_param('i', $ticket_id);
                $stmt2->execute();
                $thread_id = $mysqli->insert_id;
                
                $stmt3 = $mysqli->prepare(
                    'INSERT INTO thread_entries (thread_id, user_id, body, created)
                     VALUES (?, ?, ?, NOW())'
                );
                $stmt3->bind_param('iis', $thread_id, $_SESSION['user_id'], $body);
                $stmt3->execute();
                
                $success = 'Ticket creado exitosamente! Número: ' . $ticket_number;
                echo '<script>
                    setTimeout(function() {
                        window.location.href = "tickets.php";
                    }, 2000);
                </script>';
            } else {
                $error = 'Error al crear el ticket: ' . $mysqli->error;
            }
        }
    }
}

// Obtener departamentos y prioridades
$departments = [];
$stmt = $mysqli->query('SELECT id, name FROM departments WHERE is_active = 1');
while ($row = $stmt->fetch_assoc()) {
    $departments[] = $row;
}

$priorities = [];
$stmt = $mysqli->query('SELECT id, name, level FROM priorities ORDER BY level ASC');
while ($row = $stmt->fetch_assoc()) {
    $priorities[] = $row;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Ticket - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body {
            background: #f5f7fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .container-main {
            max-width: 900px;
            margin: 40px auto;
            padding: 0 20px;
        }
        .form-card {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <span class="navbar-brand"><?php echo APP_NAME; ?></span>
            <div>
                <a href="tickets.php" class="btn btn-outline-light btn-sm">Mis Tickets</a>
                <a href="logout.php" class="btn btn-outline-light btn-sm">Cerrar Sesión</a>
            </div>
        </div>
    </nav>

    <div class="container-main">
        <div class="form-card">
            <h2 class="mb-4">Crear Nuevo Ticket</h2>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <form method="post">
                <div class="mb-3">
                    <label for="subject" class="form-label">Asunto</label>
                    <input type="text" class="form-control" id="subject" name="subject" required>
                </div>

                <div class="mb-3">
                    <label for="dept_id" class="form-label">Departamento</label>
                    <select class="form-select" id="dept_id" name="dept_id" required>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['id']; ?>"><?php echo html($dept['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="priority_id" class="form-label">Prioridad</label>
                    <select class="form-select" id="priority_id" name="priority_id" required>
                        <?php foreach ($priorities as $priority): ?>
                            <option value="<?php echo $priority['id']; ?>"><?php echo html($priority['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="body" class="form-label">Descripción</label>
                    <textarea class="form-control" id="body" name="body" rows="8" required></textarea>
                </div>

                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                <button type="submit" class="btn btn-primary">Crear Ticket</button>
                <a href="tickets.php" class="btn btn-secondary">Cancelar</a>
            </form>
        </div>
    </div>
</body>
</html>
