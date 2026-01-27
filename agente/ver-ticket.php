<?php
/**
 * VER TICKET - AGENTE
 * Vista detallada de un ticket con conversación
 */

require_once '../config.php';
require_once '../includes/helpers.php';
require_once '../includes/Auth.php';

requireLogin('agente');

$ticket_id = getQuery('id');
if (!$ticket_id) {
    redirect('tickets.php');
}

// Obtener ticket
$stmt = $mysqli->prepare(
    'SELECT t.*, u.firstname, u.lastname, u.email, 
            IFNULL(CONCAT(s.firstname, " ", s.lastname), "Sin asignar") as staff_name,
            d.name as dept_name, ts.name as status_name, p.name as priority_name
     FROM tickets t
     JOIN users u ON t.user_id = u.id
     LEFT JOIN staff s ON t.staff_id = s.id
     JOIN departments d ON t.dept_id = d.id
     JOIN ticket_status ts ON t.status_id = ts.id
     JOIN priorities p ON t.priority_id = p.id
     WHERE t.id = ?'
);
$stmt->bind_param('i', $ticket_id);
$stmt->execute();
$ticket = $stmt->get_result()->fetch_assoc();

if (!$ticket) {
    die('❌ Ticket no encontrado');
}

// Obtener conversación
$stmt = $mysqli->prepare(
    'SELECT te.*, 
            u.firstname as user_first, u.lastname as user_last,
            s.firstname as staff_first, s.lastname as staff_last
     FROM thread_entries te
     LEFT JOIN users u ON te.user_id = u.id
     LEFT JOIN staff s ON te.staff_id = s.id
     WHERE te.thread_id = (SELECT id FROM threads WHERE ticket_id = ?)
     ORDER BY te.created ASC'
);
$stmt->bind_param('i', $ticket_id);
$stmt->execute();
$messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Procesar respuesta
$error = '';
if ($_POST) {
    if (!validateCSRF()) {
        $error = '❌ Token de seguridad inválido';
    } else {
        $body = trim($_POST['reply'] ?? '');
        $new_status = $_POST['status_id'] ?? $ticket['status_id'];

        if (!$body) {
            $error = '❌ El mensaje no puede estar vacío';
        } else {
            // Obtener thread_id
            $stmt = $mysqli->prepare('SELECT id FROM threads WHERE ticket_id = ?');
            $stmt->bind_param('i', $ticket_id);
            $stmt->execute();
            $thread = $stmt->get_result()->fetch_assoc();

            // Insertar mensaje
            $stmt = $mysqli->prepare(
                'INSERT INTO thread_entries (thread_id, staff_id, body, created)
                 VALUES (?, ?, ?, NOW())'
            );
            $stmt->bind_param('iis', $thread['id'], $_SESSION['staff_id'], $body);
            $stmt->execute();

            // Actualizar estado y agente asignado
            $stmt = $mysqli->prepare(
                'UPDATE tickets SET status_id = ?, staff_id = ?, updated = NOW()
                 WHERE id = ?'
            );
            $stmt->bind_param('iii', $new_status, $_SESSION['staff_id'], $ticket_id);
            $stmt->execute();

            redirect('ver-ticket.php?id=' . $ticket_id . '&msg=reply_sent');
        }
    }
}

$msg = getQuery('msg');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket <?php echo html($ticket['ticket_number']); ?> - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <style>
        body {
            background: #f5f7fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
        }
        
        .ticket-header {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-top: 20px;
        }
        
        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 4px solid #3498db;
        }
        
        .message.staff {
            background: #e8f5e9;
            border-left-color: #27ae60;
        }
        
        .message.user {
            background: #f3f3f3;
            border-left-color: #3498db;
        }
        
        .message-author {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        
        .message-time {
            font-size: 0.85rem;
            color: #999;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <!-- NAVBAR -->
    <nav class="navbar navbar-dark">
        <div class="container-fluid">
            <span class="navbar-brand">🛠️ <?php echo APP_NAME; ?></span>
            <div class="d-flex align-items-center gap-3">
                <a href="tickets.php" class="btn btn-sm btn-outline-light">← Volver</a>
                <a href="logout.php" class="btn btn-outline-light btn-sm">Cerrar Sesión</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- ENCABEZADO -->
        <div class="ticket-header">
            <div class="row">
                <div class="col-md-8">
                    <h2><?php echo html($ticket['ticket_number']); ?> - <?php echo html($ticket['subject']); ?></h2>
                    <p class="text-muted mb-3">Creado por: <strong><?php echo html($ticket['firstname'] . ' ' . $ticket['lastname']); ?></strong></p>
                </div>
                <div class="col-md-4 text-end">
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                        <div class="mb-2">
                            <small style="color: #999;">Estado</small><br>
                            <span class="badge" style="background: #3498db; color: white; font-size: 0.95rem;">
                                <?php echo html($ticket['status_name']); ?>
                            </span>
                        </div>
                        <div class="mb-2">
                            <small style="color: #999;">Prioridad</small><br>
                            <span class="badge" style="background: #f39c12; color: white; font-size: 0.95rem;">
                                <?php echo html($ticket['priority_name']); ?>
                            </span>
                        </div>
                        <div>
                            <small style="color: #999;">Asignado a</small><br>
                            <small><?php echo html($ticket['staff_name']); ?></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- MENSAJES -->
        <div style="background: white; padding: 20px; border-radius: 8px; margin-top: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
            <h5 style="margin-bottom: 20px;">Conversación</h5>

            <?php if (isset($msg) && $msg == 'reply_sent'): ?>
                <div class="alert alert-success">✅ Respuesta enviada correctamente</div>
            <?php endif; ?>

            <?php if (empty($messages)): ?>
                <div class="alert alert-info">Sin mensajes</div>
            <?php else: ?>
                <?php foreach ($messages as $msg): ?>
                    <div class="message <?php echo $msg['staff_id'] ? 'staff' : 'user'; ?>">
                        <div class="message-author">
                            <?php if ($msg['staff_id']): ?>
                                🛠️ <?php echo html($msg['staff_first'] . ' ' . $msg['staff_last']); ?> (Agente)
                            <?php else: ?>
                                👤 <?php echo html($msg['user_first'] . ' ' . $msg['user_last']); ?> (Usuario)
                            <?php endif; ?>
                        </div>
                        <div><?php echo nl2br(html($msg['body'])); ?></div>
                        <div class="message-time"><?php echo formatDate($msg['created']); ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- RESPONDER -->
        <div style="background: white; padding: 20px; border-radius: 8px; margin-top: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
            <h5 style="margin-bottom: 15px;">Tu Respuesta</h5>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="post">
                <div class="mb-3">
                    <label class="form-label">Nuevo Estado</label>
                    <select name="status_id" class="form-control">
                        <option value="1" <?php echo $ticket['status_id'] == 1 ? 'selected' : ''; ?>>Abierto</option>
                        <option value="2" <?php echo $ticket['status_id'] == 2 ? 'selected' : ''; ?>>En Progreso</option>
                        <option value="3" <?php echo $ticket['status_id'] == 3 ? 'selected' : ''; ?>>Esperando Usuario</option>
                        <option value="4" <?php echo $ticket['status_id'] == 4 ? 'selected' : ''; ?>>Resuelto</option>
                        <option value="5" <?php echo $ticket['status_id'] == 5 ? 'selected' : ''; ?>>Cerrado</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Mensaje</label>
                    <textarea name="reply" class="form-control" rows="5" placeholder="Escribe tu respuesta..." required></textarea>
                </div>

                <?php csrfField(); ?>

                <button type="submit" class="btn btn-success">Enviar Respuesta</button>
                <a href="tickets.php" class="btn btn-secondary">Cancelar</a>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
