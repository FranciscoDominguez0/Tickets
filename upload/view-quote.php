<?php
/**
 * VER COTIZACIÓN (USUARIO)
 * Detalle de cotización con hilo y adjuntos
 */

require_once '../config.php';
require_once '../includes/helpers.php';

requireLogin('cliente');
$uid = (int)$_SESSION['user_id'];
$eid = (int)empresaId();

// Cargar info del usuario
$user = getCurrentUser();

if (!isset($_SESSION['client_dark_mode'])) {
    $_SESSION['client_dark_mode'] = 0;
}
$isDarkMode = (isset($_SESSION['client_dark_mode']) && (int)$_SESSION['client_dark_mode'] === 1);
$qid = isset($_GET['id']) && is_numeric($_GET['id']) ? (int) $_GET['id'] : 0;

if ($qid <= 0) {
    die("DEBUG: qid is less than or equal to 0. GET array: " . print_r($_GET, true));
}

// Cargar cotización
$stmt = $mysqli->prepare(
    "SELECT q.*, 
            o.name as org_name, o.website as org_website,
            CONCAT(s.firstname, ' ', s.lastname) as staff_name 
     FROM quotes q 
     LEFT JOIN organizations o ON q.org_id = o.id 
     LEFT JOIN staff s ON q.staff_id = s.id 
     WHERE q.id = ? AND q.empresa_id = ?"
);
$stmt->bind_param('ii', $qid, $eid);
$stmt->execute();
$quote = $stmt->get_result()->fetch_assoc();

if (!$quote) {
    die("DEBUG: Quote not found in DB! qid=$qid, eid=$eid, query error: " . $mysqli->error);
}

// Check access: user must belong to the org of the quote and have org_tickets_view
$hasAccess = true;

// Procesar acciones POST
$reply_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCSRF($_POST['csrf_token'] ?? '')) {
        $reply_error = 'Token de seguridad inválido';
    } else {
        $action = $_POST['action_type'] ?? '';
        
        if ($action === 'request_quote' && $quote['status'] === 'pending') {
            $upd = $mysqli->prepare("UPDATE quotes SET status = 'requested' WHERE id = ?");
            $upd->bind_param('i', $qid);
            $upd->execute();
            $_SESSION['flash_msg'] = 'Cotización solicitada exitosamente.';
            header("Location: view-quote.php?id=$qid");
            exit;
        } elseif ($action === 'accept_quote' && $quote['status'] === 'answered') {
            $upd = $mysqli->prepare("UPDATE quotes SET status = 'accepted' WHERE id = ?");
            $upd->bind_param('i', $qid);
            $upd->execute();
            $_SESSION['flash_msg'] = 'Cotización aceptada exitosamente.';
            header("Location: view-quote.php?id=$qid");
            exit;
        } elseif ($action === 'reject_quote' && $quote['status'] === 'answered') {
            $upd = $mysqli->prepare("UPDATE quotes SET status = 'rejected' WHERE id = ?");
            $upd->bind_param('i', $qid);
            $upd->execute();
            $_SESSION['flash_msg'] = 'Cotización rechazada.';
            header("Location: view-quote.php?id=$qid");
            exit;
        } elseif ($action === 'reply_message') {
            $msg = trim($_POST['message'] ?? '');
            if ($msg === '') {
                $reply_error = 'El mensaje no puede estar vacío.';
            } else {
                $ins = $mysqli->prepare("INSERT INTO quote_messages (quote_id, user_id, message, created_at) VALUES (?, ?, ?, NOW())");
                $ins->bind_param('iis', $qid, $uid, $msg);
                if ($ins->execute()) {
                    // Si el cliente responde, y el estado era answered, lo pasamos a requested para que el agente lo vuelva a ver
                    if ($quote['status'] === 'answered') {
                        $mysqli->query("UPDATE quotes SET status = 'requested' WHERE id = $qid");
                    }
                    header("Location: view-quote.php?id=$qid");
                    exit;
                } else {
                    $reply_error = 'Error al enviar el mensaje.';
                }
            }
        }
    }
}

// Cargar mensajes
$messages = [];
$stmtMsg = $mysqli->prepare("SELECT m.*, 
    CONCAT(s.firstname, ' ', s.lastname) as staff_name,
    CONCAT(u.firstname, ' ', u.lastname) as user_name
    FROM quote_messages m
    LEFT JOIN staff s ON m.staff_id = s.id
    LEFT JOIN users u ON m.user_id = u.id
    WHERE m.quote_id = ?
    ORDER BY m.created_at ASC");
if ($stmtMsg) {
    $stmtMsg->bind_param('i', $qid);
    $stmtMsg->execute();
    $msgResult = $stmtMsg->get_result();
    while ($row = $msgResult->fetch_assoc()) {
        $messages[] = $row;
    }
}

$statusColors = [
    'draft'    => ['bg' => '#f1f5f9', 'color' => '#475569', 'icon' => 'bi-pencil-square',   'label' => 'Borrador'],
    'pending'  => ['bg' => '#f1f5f9', 'color' => '#475569', 'icon' => 'bi-clock-fill',       'label' => 'Pendiente de Solicitud'],
    'requested'=> ['bg' => '#fef9c3', 'color' => '#854d0e', 'icon' => 'bi-send-exclamation', 'label' => 'Solicitada'],
    'answered' => ['bg' => '#dbeafe', 'color' => '#1e40af', 'icon' => 'bi-reply-all-fill',   'label' => 'Esperando Aprobación'],
    'accepted' => ['bg' => '#dcfce7', 'color' => '#166534', 'icon' => 'bi-check-circle-fill', 'label' => 'Aceptada'],
    'rejected' => ['bg' => '#fee2e2', 'color' => '#991b1b', 'icon' => 'bi-x-circle-fill',    'label' => 'Rechazada']
];
$stInfo = $statusColors[$quote['status']] ?? $statusColors['draft'];
$stBg = $stInfo['bg'];
$stCol = $stInfo['color'];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cotización #<?php echo $qid; ?> - <?php echo APP_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="<?php echo html(rtrim(defined('APP_URL') ? APP_URL : '', '/')); ?>/publico/img/favicon.ico">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/client_dark.css?v=<?php echo (int)@filemtime(__DIR__ . '/css/client_dark.css'); ?>">
    <link rel="stylesheet" href="css/client-ticket-view.css?v=<?php echo (int)@filemtime(__DIR__ . '/css/client-ticket-view.css'); ?>">
    <style>
        body {
            background: #f6f7fb;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding-top: 62px;
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            pointer-events: none;
            background:
                radial-gradient(700px circle at 12% 0%, rgba(245, 158, 11, 0.08), transparent 52%),
                radial-gradient(900px circle at 88% 10%, rgba(239, 68, 68, 0.10), transparent 55%),
                repeating-linear-gradient(135deg, rgba(15, 23, 42, 0.02) 0px, rgba(15, 23, 42, 0.02) 1px, transparent 1px, transparent 14px);
            z-index: -1;
        }
        .topbar {
            background: linear-gradient(135deg, #0b1220, #111827);
            border-bottom: 1px solid rgba(255, 255, 255, 0.12);
        }
        .topbar .navbar-brand { font-weight: 900; letter-spacing: 0.02em; }
        .topbar .profile-brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: #fff;
            text-decoration: none;
        }
        
        .container-main { max-width: 1200px; margin: 40px auto; padding: 0 20px; }
        .center-wrap { max-width: 980px; margin: 0 auto; }
        .panel-soft {
            background: rgba(255, 255, 255, 0.82);
            backdrop-filter: blur(10px);
            border-radius: 22px;
            border: 1px solid rgba(226, 232, 240, 0.9);
            box-shadow: 0 22px 60px rgba(15, 23, 42, 0.08);
            overflow: hidden;
            padding: 18px;
        }
        .card-soft { background: #fff; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; overflow: hidden; }
        .card-soft .head { padding: 20px 22px; border-bottom: 1px solid #e2e8f0; }
        .card-soft .body { padding: 24px; }
        
        .ticket-view-entry { margin-bottom: 24px; font-family: 'Inter', system-ui, sans-serif; }
        .ticket-view-entry .entry-row { display: flex; align-items: flex-start; gap: 16px; }
        .ticket-view-entry.user .entry-row { flex-direction: row-reverse; }
        .ticket-view-entry .entry-avatar { width: 40px; height: 40px; border-radius: 50%; background: #e2e8f0; display: flex; align-items: center; justify-content: center; flex: 0 0 auto; }
        .ticket-view-entry.staff .entry-avatar { background: #0f62fe; color: #ffffff; }
        .ticket-view-entry.user .entry-avatar { background: #fef2f2; color: #1e3a8a; }
        .ticket-view-entry .entry-content { flex: 1; min-width: 0; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 18px 24px; box-shadow: 0 4px 12px rgba(15, 23, 42, 0.03); position: relative; }
        .ticket-view-entry.user .entry-content { background: #f8fafc; border-color: #f1f5f9; }
        .ticket-view-entry .entry-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid #f1f5f9; }
        .ticket-view-entry .entry-author { font-weight: 700; color: #0f172a; font-size: 0.95rem; }
        .ticket-view-entry .entry-date { color: #64748b; font-size: 0.85rem; font-weight: 500; }
        
        .btn-action-primary {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: #fff; border: none; padding: 10px 24px; border-radius: 50rem; font-weight: 700;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.25);
            transition: transform 0.2s;
        }
        .btn-action-primary:hover { transform: translateY(-2px); color: #fff; }
        .btn-action-success { background: #10b981; color: #fff; border: none; padding: 10px 24px; border-radius: 50rem; font-weight: 700; }
        .btn-action-danger { background: #ef4444; color: #fff; border: none; padding: 10px 24px; border-radius: 50rem; font-weight: 700; }
        
    </style>
</head>
<body class="<?php echo $isDarkMode ? 'dark-mode' : ''; ?>">
<nav class="navbar navbar-dark topbar" style="position: fixed; top: 0; left: 0; width: 100%; z-index: 1030;">
    <div class="container-fluid">
        <a class="navbar-brand profile-brand" href="tickets.php?view=org">
            <?php echo APP_NAME; ?>
        </a>
        <div class="d-flex align-items-center gap-2">
            <a href="tickets.php?view=org" class="btn btn-sm btn-outline-light rounded-pill">Volver a la organización</a>
        </div>
    </div>
</nav>

<div class="container-main">
    <div class="center-wrap">
        <div class="panel-soft">
            <?php if (!empty($_SESSION['flash_msg'])): ?>
                <div class="alert alert-success d-flex align-items-center mb-4" role="alert">
                    <i class="bi bi-check-circle-fill fs-4 me-3"></i>
                    <div><?php echo html($_SESSION['flash_msg']); unset($_SESSION['flash_msg']); ?></div>
                </div>
            <?php endif; ?>
            
            <?php if ($reply_error): ?>
                <div class="alert alert-danger mb-4"><?php echo html($reply_error); ?></div>
            <?php endif; ?>

            <div class="client-ticket-hero" style="background: #fff; padding: 24px; border-radius: 16px; border: 1px solid #e2e8f0; margin-bottom: 24px;">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <span class="badge mb-2" style="background-color: <?php echo $stBg; ?>; color: <?php echo $stCol; ?>; border: 1px solid <?php echo $stCol; ?>33; padding: 6px 12px; border-radius: 6px;">
                            <i class="<?php echo $stInfo['icon']; ?>"></i> <?php echo $stInfo['label']; ?>
                        </span>
                        <h1 class="h3 fw-bold mb-1">Cotización #<?php echo $qid; ?>: <?php echo html($quote['title']); ?></h1>
                        <div class="text-muted small">
                            <i class="bi bi-calendar"></i> Creada el <?php echo date('d/m/Y', strtotime($quote['created_at'])); ?> 
                            | <i class="bi bi-building"></i> <?php echo html($quote['org_name']); ?>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <?php if (!empty($quote['file_path'])): ?>
                            <a href="<?php echo html($quote['file_path']); ?>" target="_blank" class="btn btn-outline-secondary rounded-pill fw-bold">
                                <i class="bi bi-file-earmark-pdf"></i> Descargar PDF
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($quote['status'] === 'pending'): ?>
                            <form method="POST" style="margin:0;">
                                <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">
                                <input type="hidden" name="action_type" value="request_quote">
                                <button type="submit" class="btn btn-action-primary">
                                    <i class="bi bi-send"></i> Solicitar Cotización
                                </button>
                            </form>
                        <?php elseif ($quote['status'] === 'answered'): ?>
                            <form method="POST" style="margin:0;" class="d-flex gap-2">
                                <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">
                                <input type="hidden" name="action_type" value="accept_quote">
                                <button type="submit" class="btn btn-action-success">
                                    <i class="bi bi-check-lg"></i> Aceptar
                                </button>
                            </form>
                            <form method="POST" style="margin:0;">
                                <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">
                                <input type="hidden" name="action_type" value="reject_quote">
                                <button type="submit" class="btn btn-action-danger">
                                    <i class="bi bi-x-lg"></i> Rechazar
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (!empty($quote['description'])): ?>
                    <hr>
                    <div class="text-secondary" style="white-space: pre-wrap;"><?php echo html($quote['description']); ?></div>
                <?php endif; ?>
            </div>

            <!-- Thread -->
            <div class="thread">
                <?php foreach ($messages as $m): 
                    $isStaff = !empty($m['staff_id']);
                    $authorName = $isStaff ? $m['staff_name'] : $m['user_name'];
                    $avInitials = strtoupper(substr($authorName, 0, 1) . substr(strrchr($authorName, ' ') ?: '', 1, 1));
                    $avInitials = trim($avInitials) ?: 'U';
                ?>
                    <div class="ticket-view-entry <?php echo $isStaff ? 'staff' : 'user'; ?>">
                        <div class="entry-row">
                            <div class="entry-avatar">
                                <span class="entry-avatar-inner"><?php echo html($avInitials); ?></span>
                            </div>
                            <div class="entry-content">
                                <div class="entry-header">
                                    <div class="entry-author"><?php echo html($authorName); ?></div>
                                    <div class="entry-date"><?php echo date('d/m/Y h:i A', strtotime($m['created_at'])); ?></div>
                                </div>
                                <div class="entry-body" style="white-space: pre-wrap;"><?php echo html($m['message']); ?></div>
                                
                                <?php if (!empty($m['file_path'])): ?>
                                    <div class="mt-3 p-2 border rounded bg-light" style="max-width: 300px; display:flex; align-items:center; gap: 10px;">
                                        <i class="bi bi-file-earmark-pdf fs-3 text-danger"></i>
                                        <div style="flex:1; min-width:0;">
                                            <div class="text-truncate fw-bold small">Documento_Cotizacion.pdf</div>
                                            <a href="<?php echo html($m['file_path']); ?>" target="_blank" class="small text-decoration-none">Descargar</a>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (!in_array($quote['status'], ['accepted', 'rejected'])): ?>
            <!-- Reply box -->
            <div class="card-soft mt-4">
                <div class="head">
                    <h5 class="m-0 fw-bold"><i class="bi bi-chat-dots"></i> Escribir un mensaje</h5>
                </div>
                <div class="body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">
                        <input type="hidden" name="action_type" value="reply_message">
                        <textarea name="message" class="form-control mb-3" rows="4" placeholder="Escribe tu mensaje o consulta aquí..." required></textarea>
                        <div class="text-end">
                            <button type="submit" class="btn btn-action-primary">
                                <i class="bi bi-send"></i> Enviar Mensaje
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
