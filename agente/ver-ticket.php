<?php
/**
 * VER TICKET - AGENTE
 * Vista detallada de un ticket con conversación, cierre con firma y reapertura
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
    die('Ticket no encontrado');
}

$isClosed = ((int)$ticket['status_id'] === 5);

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
if ($_POST && !isset($_POST['action'])) {
    if (!validateCSRF()) {
        $error = 'Token de seguridad invalido';
    } else {
        $body = trim($_POST['reply'] ?? '');
        $new_status = $_POST['status_id'] ?? $ticket['status_id'];

        if (!$body) {
            $error = 'El mensaje no puede estar vacio';
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

        .closed-banner {
            background: linear-gradient(135deg, #fee2e2 0%, #fef3c7 100%);
            border: 1px solid #f59e0b;
            border-radius: 8px;
            padding: 15px 20px;
            margin-top: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .closed-banner .icon {
            font-size: 1.5rem;
        }

        .signature-box {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .signature-box img {
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            background: #fff;
        }

        #signatureCanvas {
            border: 2px solid #d1d5db;
            border-radius: 8px;
            cursor: crosshair;
            background: #fff;
            touch-action: none;
        }

        .btn-close-ticket {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            border: none;
            font-weight: 600;
        }

        .btn-close-ticket:hover {
            background: linear-gradient(135deg, #c0392b 0%, #a93226 100%);
            color: white;
        }

        .btn-reopen-ticket {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            border: none;
            font-weight: 600;
        }

        .btn-reopen-ticket:hover {
            background: linear-gradient(135deg, #2980b9 0%, #1f6fa5 100%);
            color: white;
        }

        .action-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15,23,42,0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        .action-overlay.active {
            display: flex;
        }
    </style>
</head>
<body>
    <!-- OVERLAY DE CARGA -->
    <div class="action-overlay" id="actionOverlay">
        <div style="background:#fff; border-radius:14px; padding:24px 32px; border:1px solid #e2e8f0; box-shadow:0 16px 40px rgba(0,0,0,0.25); min-width: 240px; text-align:center;">
            <div class="spinner-border text-primary" role="status" style="width:2.5rem; height:2.5rem;"></div>
            <div style="margin-top:12px; font-weight:800; color:#0f172a;" id="actionOverlayText">Procesando...</div>
        </div>
    </div>

    <!-- NAVBAR -->
    <nav class="navbar navbar-dark">
        <div class="container-fluid">
            <span class="navbar-brand"><?php echo APP_NAME; ?></span>
            <div class="d-flex align-items-center gap-3">
                <a href="tickets.php" class="btn btn-sm btn-outline-light">Volver</a>
                <a href="logout.php" class="btn btn-outline-light btn-sm">Cerrar Sesion</a>
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
                            <span class="badge" style="background: <?php echo $isClosed ? '#e74c3c' : '#3498db'; ?>; color: white; font-size: 0.95rem;">
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

            <!-- BOTONES DE ACCION -->
            <div class="mt-3 d-flex gap-2">
                <?php if ($isClosed): ?>
                    <button type="button" class="btn btn-reopen-ticket" id="btnReopenTicket">
                        Reabrir Ticket
                    </button>
                <?php else: ?>
                    <button type="button" class="btn btn-close-ticket" data-bs-toggle="modal" data-bs-target="#modalCloseChoice">
                        Cerrar Ticket
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($isClosed): ?>
            <!-- BANNER CERRADO -->
            <div class="closed-banner">
                <div class="icon">&#128274;</div>
                <div>
                    <strong>Ticket cerrado</strong>
                    <?php if (!empty($ticket['close_message'])): ?>
                        <br><small><?php echo nl2br(html($ticket['close_message'])); ?></small>
                    <?php endif; ?>
                    <?php if (!empty($ticket['closed_at'])): ?>
                        <br><small class="text-muted">Cerrado el: <?php echo formatDate($ticket['closed_at']); ?></small>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($ticket['client_signature'])): ?>
                <!-- FIRMA DEL CLIENTE -->
                <div class="signature-box">
                    <h5>Firma del Cliente</h5>
                    <img src="../<?php echo html($ticket['client_signature']); ?>?v=<?php echo time(); ?>" 
                         width="300" alt="Firma del cliente">
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- MENSAJES -->
        <div style="background: white; padding: 20px; border-radius: 8px; margin-top: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
            <h5 style="margin-bottom: 20px;">Conversacion</h5>

            <?php if (isset($msg) && $msg == 'reply_sent'): ?>
                <div class="alert alert-success">Respuesta enviada correctamente</div>
            <?php endif; ?>

            <?php if (empty($messages)): ?>
                <div class="alert alert-info">Sin mensajes</div>
            <?php else: ?>
                <?php foreach ($messages as $msg): ?>
                    <div class="message <?php echo $msg['staff_id'] ? 'staff' : 'user'; ?>">
                        <div class="message-author">
                            <?php if ($msg['staff_id']): ?>
                                <?php echo html($msg['staff_first'] . ' ' . $msg['staff_last']); ?> (Agente)
                            <?php else: ?>
                                <?php echo html($msg['user_first'] . ' ' . $msg['user_last']); ?> (Usuario)
                            <?php endif; ?>
                        </div>
                        <div><?php echo nl2br(html($msg['body'])); ?></div>
                        <div class="message-time"><?php echo formatDate($msg['created']); ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if (!$isClosed): ?>
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
        <?php endif; ?>
    </div>

    <!-- MODAL ELECCION: CERRAR CON O SIN FIRMA -->
    <div class="modal fade" id="modalCloseChoice" tabindex="-1" aria-labelledby="modalCloseChoiceLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #e74c3c, #c0392b); color: white;">
                    <h5 class="modal-title" id="modalCloseChoiceLabel">Cerrar Ticket #<?php echo html($ticket['ticket_number']); ?></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body text-center" style="padding: 30px;">
                    <p style="font-size: 1.05rem; font-weight: 600; color: #0f172a; margin-bottom: 20px;">
                        Como deseas cerrar este ticket?
                    </p>
                    <div class="d-grid gap-3">
                        <button type="button" class="btn btn-lg btn-success" id="btnCloseWithSignature" style="font-weight: 600; padding: 14px 20px;">
                            Con Firma del Cliente
                        </button>
                        <button type="button" class="btn btn-lg btn-outline-danger" id="btnCloseWithoutSignature" style="font-weight: 600; padding: 14px 20px;">
                            Sin Firma
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL CERRAR SIN FIRMA -->
    <div class="modal fade" id="modalCloseNoSignature" tabindex="-1" aria-labelledby="modalCloseNoSignatureLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #e74c3c, #c0392b); color: white;">
                    <h5 class="modal-title" id="modalCloseNoSignatureLabel">Cerrar sin firma - Ticket #<?php echo html($ticket['ticket_number']); ?></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Motivo de cierre</label>
                        <textarea id="closeMessageNoSig" class="form-control" rows="3" placeholder="Describe el motivo del cierre..."></textarea>
                    </div>
                    <div class="alert alert-warning mb-0" style="font-size: 0.9rem;">
                        El ticket se cerrara sin firma del cliente. Se enviara una notificacion interna a los agentes configurados.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-close-ticket" id="btnConfirmCloseNoSig">
                        Cerrar Ticket
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL CERRAR TICKET CON FIRMA -->
    <div class="modal fade" id="modalCloseTicket" tabindex="-1" aria-labelledby="modalCloseTicketLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #e74c3c, #c0392b); color: white;">
                    <h5 class="modal-title" id="modalCloseTicketLabel">Cerrar Ticket #<?php echo html($ticket['ticket_number']); ?></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Motivo de cierre</label>
                        <textarea id="closeMessage" class="form-control" rows="3" placeholder="Describe el motivo del cierre..."></textarea>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-bold">Firma del cliente</label>
                        <div class="d-flex gap-2 mb-2">
                            <button type="button" class="btn btn-sm btn-outline-danger" id="btnClearSignature">Limpiar firma</button>
                        </div>
                        <canvas id="signatureCanvas" width="700" height="200"></canvas>
                        <div class="form-text">Dibuja la firma del cliente en el recuadro. Si no hay firma, el ticket se cerrara sin ella.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-close-ticket" id="btnConfirmClose">
                        Cerrar Ticket
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var ticketId = <?php echo (int)$ticket_id; ?>;
        var csrfToken = <?php echo json_encode($_SESSION['csrf_token'] ?? ''); ?>;
        var overlay = document.getElementById('actionOverlay');
        var overlayText = document.getElementById('actionOverlayText');

        // ============================
        // SIGNATURE CANVAS
        // ============================
        var canvas = document.getElementById('signatureCanvas');
        var ctx = canvas ? canvas.getContext('2d') : null;
        var drawing = false;
        var hasDrawn = false;
        var lastX = 0, lastY = 0;

        function getPos(e) {
            var rect = canvas.getBoundingClientRect();
            var scaleX = canvas.width / rect.width;
            var scaleY = canvas.height / rect.height;
            if (e.touches && e.touches.length > 0) {
                return {
                    x: (e.touches[0].clientX - rect.left) * scaleX,
                    y: (e.touches[0].clientY - rect.top) * scaleY
                };
            }
            return {
                x: (e.clientX - rect.left) * scaleX,
                y: (e.clientY - rect.top) * scaleY
            };
        }

        function startDraw(e) {
            drawing = true;
            var pos = getPos(e);
            lastX = pos.x;
            lastY = pos.y;
            e.preventDefault();
        }

        function draw(e) {
            if (!drawing) return;
            var pos = getPos(e);
            ctx.beginPath();
            ctx.moveTo(lastX, lastY);
            ctx.lineTo(pos.x, pos.y);
            ctx.strokeStyle = '#1a1a2e';
            ctx.lineWidth = 2.5;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            ctx.stroke();
            lastX = pos.x;
            lastY = pos.y;
            hasDrawn = true;
            e.preventDefault();
        }

        function stopDraw(e) {
            drawing = false;
            e.preventDefault();
        }

        if (canvas && ctx) {
            canvas.addEventListener('mousedown', startDraw);
            canvas.addEventListener('mousemove', draw);
            canvas.addEventListener('mouseup', stopDraw);
            canvas.addEventListener('mouseleave', stopDraw);
            canvas.addEventListener('touchstart', startDraw, {passive: false});
            canvas.addEventListener('touchmove', draw, {passive: false});
            canvas.addEventListener('touchend', stopDraw, {passive: false});

            document.getElementById('btnClearSignature').addEventListener('click', function() {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                hasDrawn = false;
            });
        }

        // ============================
        // MODAL DE ELECCION
        // ============================
        var modalChoice = document.getElementById('modalCloseChoice');
        var modalChoiceInstance = modalChoice ? bootstrap.Modal.getOrCreateInstance(modalChoice) : null;
        var modalNoSig = document.getElementById('modalCloseNoSignature');
        var modalNoSigInstance = modalNoSig ? bootstrap.Modal.getOrCreateInstance(modalNoSig) : null;
        var modalWithSig = document.getElementById('modalCloseTicket');
        var modalWithSigInstance = modalWithSig ? bootstrap.Modal.getOrCreateInstance(modalWithSig) : null;

        var btnWithSig = document.getElementById('btnCloseWithSignature');
        if (btnWithSig) {
            btnWithSig.addEventListener('click', function() {
                if (modalChoiceInstance) modalChoiceInstance.hide();
                if (canvas && ctx) {
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    hasDrawn = false;
                }
                if (modalWithSigInstance) modalWithSigInstance.show();
            });
        }

        var btnWithoutSig = document.getElementById('btnCloseWithoutSignature');
        if (btnWithoutSig) {
            btnWithoutSig.addEventListener('click', function() {
                if (modalChoiceInstance) modalChoiceInstance.hide();
                if (modalNoSigInstance) modalNoSigInstance.show();
            });
        }

        // ============================
        // CERRAR CON FIRMA
        // ============================
        var btnConfirmClose = document.getElementById('btnConfirmClose');
        if (btnConfirmClose) {
            btnConfirmClose.addEventListener('click', function() {
                if (!hasDrawn) {
                    alert('Por favor dibuje la firma del cliente antes de cerrar.');
                    return;
                }

                btnConfirmClose.disabled = true;
                overlayText.textContent = 'Cerrando ticket con firma...';
                overlay.classList.add('active');

                var closeMessage = document.getElementById('closeMessage').value.trim();
                var signatureData = '';
                if (hasDrawn && canvas) {
                    signatureData = canvas.toDataURL('image/png');
                }

                var formData = new FormData();
                formData.append('ticket_id', ticketId);
                formData.append('close_message', closeMessage);
                formData.append('signature_data', signatureData);
                formData.append('csrf_token', csrfToken);

                fetch('close-ticket.php', {
                    method: 'POST',
                    body: formData
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        location.reload();
                    } else {
                        overlay.classList.remove('active');
                        btnConfirmClose.disabled = false;
                        alert('Error: ' + (data.error || 'No se pudo cerrar el ticket'));
                    }
                })
                .catch(function(err) {
                    overlay.classList.remove('active');
                    btnConfirmClose.disabled = false;
                    alert('Error de conexion');
                });
            });
        }

        // ============================
        // CERRAR SIN FIRMA
        // ============================
        var btnConfirmCloseNoSig = document.getElementById('btnConfirmCloseNoSig');
        if (btnConfirmCloseNoSig) {
            btnConfirmCloseNoSig.addEventListener('click', function() {
                btnConfirmCloseNoSig.disabled = true;
                overlayText.textContent = 'Cerrando ticket sin firma...';
                overlay.classList.add('active');

                var closeMessage = document.getElementById('closeMessageNoSig').value.trim();

                var formData = new FormData();
                formData.append('ticket_id', ticketId);
                formData.append('close_message', closeMessage);
                formData.append('signature_data', '');
                formData.append('csrf_token', csrfToken);

                fetch('close-ticket.php', {
                    method: 'POST',
                    body: formData
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        location.reload();
                    } else {
                        overlay.classList.remove('active');
                        btnConfirmCloseNoSig.disabled = false;
                        alert('Error: ' + (data.error || 'No se pudo cerrar el ticket'));
                    }
                })
                .catch(function(err) {
                    overlay.classList.remove('active');
                    btnConfirmCloseNoSig.disabled = false;
                    alert('Error de conexion');
                });
            });
        }

        // ============================
        // REABRIR TICKET
        // ============================
        var btnReopen = document.getElementById('btnReopenTicket');
        if (btnReopen) {
            btnReopen.addEventListener('click', function() {
                if (!confirm('Seguro que deseas reabrir este ticket? La firma del cliente sera eliminada.')) return;

                btnReopen.disabled = true;
                overlayText.textContent = 'Reabriendo ticket...';
                overlay.classList.add('active');

                var formData = new FormData();
                formData.append('ticket_id', ticketId);
                formData.append('csrf_token', csrfToken);

                fetch('reopen-ticket.php', {
                    method: 'POST',
                    body: formData
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        location.reload();
                    } else {
                        overlay.classList.remove('active');
                        btnReopen.disabled = false;
                        alert('Error: ' + (data.error || 'No se pudo reabrir el ticket'));
                    }
                })
                .catch(function(err) {
                    overlay.classList.remove('active');
                    btnReopen.disabled = false;
                    alert('Error de conexion');
                });
            });
        }
    });
    </script>
</body>
</html>
