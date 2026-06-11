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
        
        $notifyAgent = function($msgStr) use ($mysqli, $quote, $qid) {
            $staffId = (int)($quote['staff_id'] ?? 0);
            $eid = (int)($quote['empresa_id'] ?? 1);
            $escMsg = $mysqli->real_escape_string("Cotización #$qid: $msgStr");
            
            if ($staffId > 0) {
                $mysqli->query("INSERT INTO notifications (empresa_id, staff_id, message, type, related_id, is_read, created_at) VALUES ($eid, $staffId, '$escMsg', 'quote', $qid, 0, NOW())");
            } else {
                $res = $mysqli->query("SELECT staff_id FROM notification_recipients WHERE empresa_id = $eid");
                if ($res) {
                    while ($row = $res->fetch_assoc()) {
                        $sid = (int)$row['staff_id'];
                        $mysqli->query("INSERT INTO notifications (empresa_id, staff_id, message, type, related_id, is_read, created_at) VALUES ($eid, $sid, '$escMsg', 'quote', $qid, 0, NOW())");
                    }
                }
            }
        };
        
        if ($action === 'request_quote' && $quote['status'] === 'pending') {
            $upd = $mysqli->prepare("UPDATE quotes SET status = 'requested' WHERE id = ?");
            $upd->bind_param('i', $qid);
            $upd->execute();
            $mysqli->query("INSERT INTO quote_messages (quote_id, user_id, message, created_at) VALUES ($qid, $uid, 'El cliente ha solicitado la cotización formal.', NOW())");
            if (!empty($quote['ticket_id']) && function_exists('notifyApprovalToAdminRecipients')) {
                notifyApprovalToAdminRecipients($quote['ticket_id'], 'Cotización Solicitada');
            }
            $notifyAgent('El cliente ha solicitado la cotización formal.');
            $_SESSION['flash_msg'] = 'Cotización solicitada exitosamente.';
            header("Location: view-quote.php?id=$qid");
            exit;
        } elseif ($action === 'accept_quote' && $quote['status'] === 'answered') {
            $upd = $mysqli->prepare("UPDATE quotes SET status = 'accepted' WHERE id = ?");
            $upd->bind_param('i', $qid);
            $upd->execute();
            $mysqli->query("INSERT INTO quote_messages (quote_id, user_id, message, created_at) VALUES ($qid, $uid, 'El cliente ha ACEPTADO la cotización.', NOW())");
            if (!empty($quote['ticket_id']) && function_exists('notifyApprovalToAdminRecipients')) {
                notifyApprovalToAdminRecipients($quote['ticket_id'], 'Cotización Aceptada');
            }
            $notifyAgent('El cliente ha ACEPTADO la cotización.');
            $_SESSION['flash_msg'] = 'Cotización aceptada exitosamente.';
            header("Location: view-quote.php?id=$qid");
            exit;
        } elseif ($action === 'reject_quote' && ($quote['status'] === 'answered' || $quote['status'] === 'pending')) {
            $upd = $mysqli->prepare("UPDATE quotes SET status = 'rejected' WHERE id = ?");
            $upd->bind_param('i', $qid);
            $upd->execute();
            $mysqli->query("INSERT INTO quote_messages (quote_id, user_id, message, created_at) VALUES ($qid, $uid, 'El cliente ha RECHAZADO la cotización.', NOW())");
            if (!empty($quote['ticket_id']) && function_exists('notifyApprovalToAdminRecipients')) {
                notifyApprovalToAdminRecipients($quote['ticket_id'], 'Cotización Rechazada');
            }
            $notifyAgent('El cliente ha RECHAZADO la cotización.');
            $_SESSION['flash_msg'] = 'Cotización rechazada.';
            header("Location: view-quote.php?id=$qid");
            exit;
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
        .topbar .profile-brand .brand-logo-wrap {
            height: 46px;
            padding: 0;
            border-radius: 0;
            background: transparent;
            border: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: none;
        }
        .topbar .profile-brand .brand-logo {
            height: 28px;
            width: auto;
            max-height: 28px;
            max-width: 160px;
            object-fit: contain;
            display: block;
            filter: drop-shadow(0 10px 22px rgba(0,0,0,0.22));
        }
        .topbar .user-menu-btn {
            border-radius: 999px;
            font-weight: 800;
        }
        .topbar .user-menu-btn .uavatar {
            width: 30px;
            height: 30px;
            border-radius: 12px;
            background: rgba(255,255,255,0.92);
            color: #0b1220;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-right: 8px;
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
        
        .ticket-view-entry {
            margin-bottom: 24px;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }

        .ticket-view-entry .entry-row {
            display: flex;
            align-items: flex-start;
            gap: 16px;
        }

        .ticket-view-entry.user .entry-row {
            flex-direction: row-reverse;
        }

        .ticket-view-entry .entry-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
        }

        .ticket-view-entry .entry-avatar-inner {
            font-weight: 800;
            font-size: 0.95rem;
            letter-spacing: 0.05em;
        }

        .ticket-view-entry.staff .entry-avatar {
            background: #0f62fe;
            color: #ffffff;
        }

        .ticket-view-entry.user .entry-avatar {
            background: #fef2f2;
            color: #1e3a8a;
        }

        .ticket-view-entry .entry-bubble-wrapper {
            display: flex;
            flex-direction: column;
            max-width: 800px;
            width: 100%;
            min-width: 0;
        }

        .ticket-view-entry.user .entry-bubble-wrapper {
            align-items: flex-end;
        }

        .ticket-view-entry .entry-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 6px;
            padding-left: 2px;
        }

        .ticket-view-entry .author-name {
            font-weight: 800;
            color: #0f172a;
            font-size: 1rem;
        }

        .ticket-view-entry .author-role {
            font-size: 0.7rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: #ffffff;
            padding: 3px 8px;
            border-radius: 6px;
            box-shadow: 0 2px 6px rgba(239, 68, 68, 0.25);
            margin-left: 4px;
        }

        .ticket-view-entry .entry-content {
            border-radius: 16px;
            padding: 16px 20px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
            width: 100%;
            max-width: 100%;
        }

        .ticket-view-entry.user .entry-content {
            background: #f1f5f9;
            border-color: #e2e8f0;
        }

        .ticket-view-entry.staff .entry-content {
            background: #fef2f2;
            border-color: #dbeafe;
        }

        .ticket-view-entry .entry-meta-top {
            font-size: 0.8rem;
            color: #94a3b8;
            margin-bottom: 10px;
            font-weight: 500;
        }

        .ticket-view-entry .entry-body {
            color: #1e293b;
            white-space: pre-wrap;
            word-break: break-word;
            font-size: 0.95rem;
            line-height: 1.5;
        }

        body.dark-mode .ticket-view-entry .entry-content {
            background: #18181b;
            border-color: #27272a;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }
        
        body.dark-mode .ticket-view-entry.user .entry-content {
            background: #1e1e20;
            border-color: #27272a;
        }

        body.dark-mode .ticket-view-entry.staff .entry-content {
            background: #111827;
            border-color: #1f2937;
        }

        body.dark-mode .ticket-view-entry .author-name {
            color: #f4f4f5;
        }

        body.dark-mode .ticket-view-entry .author-role {
            background: linear-gradient(135deg, #f87171, #ef4444);
            color: #ffffff;
            box-shadow: 0 2px 6px rgba(239, 68, 68, 0.4);
        }

        body.dark-mode .ticket-view-entry .entry-meta-top {
            color: #a1a1aa;
        }

        body.dark-mode .ticket-view-entry .entry-avatar {
            background: #27272a;
            color: #f4f4f5;
        }

        body.dark-mode .ticket-view-entry.staff .entry-avatar {
            background: #1d4ed8;
            color: #ffffff;
        }

        body.dark-mode .ticket-view-entry.user .entry-avatar {
            background: #3f3f46;
            color: #e4e4e7;
        }

        body.dark-mode .ticket-view-entry .entry-body {
            color: #d4d4d8;
        }
        
        .btn-action-primary {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: #fff; border: none; padding: 10px 26px; border-radius: 50rem; font-weight: 700;
            box-shadow: 0 4px 14px rgba(239, 68, 68, 0.25);
            transition: all 0.2s;
        }
        .btn-action-primary:hover { transform: translateY(-2px); color: #fff; box-shadow: 0 6px 20px rgba(239, 68, 68, 0.35); }
        
        .btn-action-dark {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            color: #fff; border: none; padding: 10px 26px; border-radius: 50rem; font-weight: 700;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.2);
            transition: all 0.2s;
        }
        .btn-action-dark:hover { transform: translateY(-2px); color: #fff; box-shadow: 0 6px 20px rgba(15, 23, 42, 0.35); }

        .btn-action-outline {
            background: transparent;
            color: #64748b; border: 2px solid #e2e8f0; padding: 8px 24px; border-radius: 50rem; font-weight: 700;
            transition: all 0.2s;
        }
        .btn-action-outline:hover { background: #f8fafc; color: #0f172a; border-color: #cbd5e1; }
        
        body.dark-mode .btn-action-outline { color: #a1a1aa; border-color: #3f3f46; }
        body.dark-mode .btn-action-outline:hover { background: #27272a; color: #fafafa; border-color: #52525b; }
        
        @media (max-width: 576px) {
            body .container-main {
                padding: 0 10px !important;
                margin: 20px auto !important;
            }
            body .center-wrap {
                max-width: 100% !important;
            }
            body .panel-soft {
                padding: 0 !important;
                background: transparent !important;
                border: none !important;
                box-shadow: none !important;
                backdrop-filter: none !important;
            }
            body.dark-mode .panel-soft {
                background: transparent !important;
                border: none !important;
                box-shadow: none !important;
                backdrop-filter: none !important;
            }
        }
    </style>
</head>
<body class="<?php echo $isDarkMode ? 'dark-mode' : ''; ?>">
<nav class="navbar navbar-dark topbar" style="position: fixed; top: 0; left: 0; width: 100%; z-index: 1030;">
    <div class="container-fluid">
        <?php
            $navUserName = trim((string)($user['name'] ?? ''));
            $companyName = trim((string)getAppSetting('company.name', ''));
            $companyLogoUrl = (string)getCompanyLogoUrl('publico/img/vigitec-logo.png');
            $navInitials = '';
            $parts = preg_split('/\s+/', trim($navUserName));
            if (!empty($parts[0])) $navInitials .= (function_exists('mb_substr') ? mb_substr($parts[0], 0, 1) : substr($parts[0], 0, 1));
            if (!empty($parts[1])) $navInitials .= (function_exists('mb_substr') ? mb_substr($parts[1], 0, 1) : substr($parts[1], 0, 1));
            $navInitials = strtoupper($navInitials ?: 'U');
            if ($navUserName === '') $navUserName = 'Mi Perfil';
        ?>
        <a class="navbar-brand profile-brand" href="tickets.php">
            <span class="brand-logo-wrap" aria-hidden="true">
                <img class="brand-logo" src="<?php echo html($companyLogoUrl); ?>" alt="<?php echo html($companyName !== '' ? $companyName : 'Logo'); ?>">
            </span>
        </a>
        <div class="d-flex align-items-center gap-2">
            <div class="dropdown">
                <button class="btn btn-outline-light btn-sm user-menu-btn position-relative" type="button" id="notifBellBtn" data-bs-toggle="dropdown" aria-expanded="false" title="Notificaciones" style="width: 34px; height: 34px; padding: 0; display: inline-flex; align-items: center; justify-content: center;">
                    <i class="bi bi-bell" style="font-size: 15px;"></i>
                    <span id="notifBellBadge" class="badge bg-danger position-absolute" style="display:none; font-size:.65rem; top: -2px; right: -2px; padding: 3px 5px; border-radius: 50px;">0</span>
                </button>
                <div class="dropdown-menu dropdown-menu-end p-0 notif-dd notif-dd-flex" style="min-width: 380px; max-height: 420px;" aria-labelledby="notifBellBtn">
                    <div class="p-3 notif-dd-head" style="flex-shrink: 0;">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-2">
                                <div style="width:36px;height:36px;border-radius:14px;background:rgba(255,255,255,0.18);display:flex;align-items:center;justify-content:center;border:1px solid rgba(255,255,255,0.22);">
                                    <i class="bi bi-bell" style="font-size:1.05rem;"></i>
                                </div>
                                <div>
                                    <div class="notif-dd-title">Notificaciones</div>
                                    <div class="notif-dd-sub" id="notifBellSub">Respuestas a tus tickets</div>
                                </div>
                            </div>
                            <div id="notifBellCountPill" class="notif-dd-count" style="display:none;">0 nuevas</div>
                        </div>
                    </div>
                    <div id="notifBellList" class="p-3" style="flex: 1; overflow-y: auto; min-height: 0;">
                        <div class="notif-empty text-center text-muted py-3" style="font-size:.92rem">
                            <div class="mb-1" style="font-weight:900;color:#0f172a;">Todo al día</div>
                            <div style="color:#64748b;">Cuando el equipo responda, te aparecerá aquí.</div>
                        </div>
                    </div>
                    <div class="p-2 border-top" style="background:#f8f9fa; flex-shrink: 0;">
                        <button id="notifMarkAllRead" class="btn btn-sm btn-outline-secondary w-100" type="button" style="font-size:.85rem;">
                            <i class="bi bi-check-all"></i> Marcar todas como leídas
                        </button>
                    </div>
                </div>
            </div>
            <form method="post" action="toggle_user_dark.php" class="d-inline" style="margin:0" id="clientDarkModeForm">
                <?php csrfField(); ?>
                <input type="hidden" name="dark_mode" value="<?php echo $isDarkMode ? '0' : '1'; ?>">
                <input type="hidden" name="return" value="<?php echo html(basename((string)($_SERVER['PHP_SELF'] ?? 'view-quote.php')) . (!empty($_SERVER['QUERY_STRING']) ? ('?' . (string)$_SERVER['QUERY_STRING']) : '')); ?>">
                <button type="submit" class="btn btn-outline-light btn-sm user-theme-toggle" id="clientDarkModeBtn" title="Modo oscuro" style="border-radius:999px; font-weight:700; width:34px; height:34px; padding:0; display:inline-flex; align-items:center; justify-content:center;">
                    <i class="bi <?php echo $isDarkMode ? 'bi-sun' : 'bi-moon-stars'; ?> user-theme-toggle-icon" style="font-size:16px;"></i>
                </button>
            </form>
            <div class="dropdown">
                <button class="btn btn-outline-light btn-sm dropdown-toggle user-menu-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="uavatar" aria-hidden="true"><?php echo html($navInitials); ?></span>
                    <span class="d-none d-sm-inline"><?php echo html($navUserName); ?></span>
                </button>
                <style>
                    .profile-dropdown {
                        width: 230px; border-radius: 16px; border: 1px solid rgba(226,232,240,0.95); box-shadow: 0 12px 34px rgba(15, 23, 42, 0.12); padding: 8px; background: #fff;
                    }
                    .profile-dd-item {
                        border-radius: 10px; padding: 8px 12px; font-weight: 600; color: #334155; margin-bottom: 2px; transition: all .15s ease;
                    }
                    .profile-dd-item:hover { background: #f8fafc; color: #0f172a; }
                    .profile-dd-icon {
                        width: 32px; height: 32px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 1.1rem;
                    }
                    .profile-dd-icon-default { background: #f1f5f9; color: #64748b; }
                    .profile-dd-icon-success { background: rgba(16, 185, 129, 0.12); color: #10b981; }
                    .profile-dd-danger { color: #ef4444; }
                    .profile-dd-danger:hover { background: rgba(239, 68, 68, 0.08); color: #ef4444; }
                    .profile-dd-icon-danger { background: rgba(239, 68, 68, 0.12); color: #ef4444; }
                    .profile-dd-divider { border-color: #f1f5f9; opacity: 1; margin: 8px 0; }
                    
                    body.dark-mode .profile-dropdown { background: #1a1a1a; border-color: #2a2a2a; box-shadow: 0 12px 34px rgba(0, 0, 0, 0.5); }
                    body.dark-mode .profile-dd-item { color: #cbd5e1; }
                    body.dark-mode .profile-dd-item:hover { background: #252525; color: #f8fafc; }
                    body.dark-mode .profile-dd-icon-default { background: rgba(255, 255, 255, 0.08); color: #94a3b8; }
                    body.dark-mode .profile-dd-icon-success { background: rgba(16, 185, 129, 0.15); color: #10b981; }
                    body.dark-mode .profile-dd-danger { color: #ef4444; }
                    body.dark-mode .profile-dd-danger:hover { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
                    body.dark-mode .profile-dd-icon-danger { background: rgba(239, 68, 68, 0.15); }
                    body.dark-mode .profile-dd-divider { border-color: #2a2a2a; }
                </style>
                <ul class="dropdown-menu dropdown-menu-end profile-dropdown">
                    <li>
                        <a class="dropdown-item d-flex align-items-center gap-3 profile-dd-item" href="tickets.php">
                            <div class="profile-dd-icon profile-dd-icon-default"><i class="bi bi-inboxes"></i></div> Mis Tickets
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item d-flex align-items-center gap-3 profile-dd-item" href="open.php">
                            <div class="profile-dd-icon profile-dd-icon-success"><i class="bi bi-plus-circle"></i></div> Crear Ticket
                        </a>
                    </li>
                    <li><hr class="dropdown-divider profile-dd-divider"></li>
                    <li>
                        <a class="dropdown-item d-flex align-items-center gap-3 profile-dd-item" href="profile.php">
                            <div class="profile-dd-icon profile-dd-icon-default"><i class="bi bi-person"></i></div> Mi perfil
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item d-flex align-items-center gap-3 profile-dd-item profile-dd-danger" href="logout.php">
                            <div class="profile-dd-icon profile-dd-icon-danger"><i class="bi bi-box-arrow-right"></i></div> Cerrar sesión
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<div class="container-main">
    <div class="center-wrap">
        <div class="panel-soft">
            <?php if (!empty($_SESSION['flash_msg'])): ?>
                <div class="alert alert-success d-flex align-items-center mb-4 auto-dismiss-alert" role="alert">
                    <i class="bi bi-check-circle-fill fs-4 me-3"></i>
                    <div><?php echo html($_SESSION['flash_msg']); unset($_SESSION['flash_msg']); ?></div>
                </div>
            <?php endif; ?>
            
            <?php if ($reply_error): ?>
                <div class="alert alert-danger mb-4 auto-dismiss-alert"><?php echo html($reply_error); ?></div>
            <?php endif; ?>
            <div class="client-ticket-hero">
                <div class="client-ticket-hero__ticket-card">
                    <div class="client-ticket-hero__ticket-accent" aria-hidden="true"></div>
                    <div class="client-ticket-hero__ticket-body">
                        <div class="client-ticket-hero__main">
                            <div class="client-ticket-hero__headline">
                                <span class="client-ticket-hero__number" aria-label="Cotización <?php echo html($qid); ?>" style="font-size: 0.9em;">
                                    <span class="client-ticket-hero__number-mark" style="font-size: 0.85em; margin-right: 4px;">Cotización #</span><span class="client-ticket-hero__number-val"><?php echo html($qid); ?></span>
                                </span>
                                <h1 class="client-ticket-hero__title"><?php echo html($quote['title'] ?: 'Sin título'); ?></h1>
                            </div>
                        </div>
                        <div class="client-ticket-hero__actions">
                            <a href="tickets.php?view=org&org_id=<?php echo html($quote['org_id']); ?>&list=quotes" class="client-ticket-hero__back me-2">
                                <i class="bi bi-arrow-left"></i> Volver a la org.
                            </a>
                            <?php if (!empty($quote['file_path'])): ?>
                                <a href="../<?php echo html($quote['file_path']); ?>" target="_blank" class="btn btn-outline-secondary rounded-pill fw-bold">
                                    <i class="bi bi-file-earmark-pdf"></i> Descargar PDF
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="client-ticket-overview">
                <div class="client-ticket-overview__grid d-none d-md-grid">
                    <div class="client-ticket-overview__grid-item">
                        <div class="client-ticket-field__label"><i class="bi bi-info-circle"></i> Estado</div>
                        <div class="client-ticket-field__value">
                            <span class="badge" style="background-color: <?php echo $stBg; ?>; color: <?php echo $stCol; ?>; border: 1px solid <?php echo $stCol; ?>33; padding: 6px 12px; border-radius: 6px;">
                                <i class="<?php echo $stInfo['icon']; ?>"></i> <?php echo $stInfo['label']; ?>
                            </span>
                        </div>
                    </div>
                    <div class="client-ticket-overview__grid-item">
                        <div class="client-ticket-field__label"><i class="bi bi-calendar"></i> Fecha de Creación</div>
                        <div class="client-ticket-field__value"><?php echo date('d/m/Y', strtotime($quote['created_at'])); ?></div>
                    </div>
                    <div class="client-ticket-overview__grid-item">
                        <div class="client-ticket-field__label"><i class="bi bi-building"></i> Organización</div>
                        <div class="client-ticket-field__value"><?php echo html($quote['org_name'] ?: 'N/A'); ?></div>
                    </div>
                </div>
            </div>

            <!-- Thread -->
            <div class="card-soft mt-4">
                <div class="client-ticket-thread-head head">
                    <h5 class="m-0 fw-bold"><i class="bi bi-chat-left-text-fill me-2"></i> Hilo de la cotización</h5>
                </div>
                <div class="body p-3 p-md-4 client-ticket-thread-body">
                    <div class="thread mt-0">
                        <?php foreach ($messages as $m): 
                            $isStaff = !empty($m['staff_id']);
                            $authorName = $isStaff ? $m['staff_name'] : $m['user_name'];
                            $avInitials = strtoupper(substr($authorName, 0, 1) . substr(strrchr($authorName, ' ') ?: '', 1, 1));
                            $avInitials = trim($avInitials) ?: 'U';
                        ?>
                            <div class="ticket-view-entry <?php echo $isStaff ? 'staff' : 'user'; ?>">
                                <div class="entry-row">
                                    <div class="entry-avatar" aria-hidden="true">
                                        <span class="entry-avatar-inner"><?php echo html($avInitials); ?></span>
                                    </div>
                                    <div class="entry-bubble-wrapper">
                                        <div class="entry-header d-flex align-items-center justify-content-between">
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="author-name"><?php echo html($authorName); ?></span>
                                                <?php if ($isStaff): ?>
                                                    <span class="author-role">Técnico</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="entry-content">
                                            <div class="entry-meta-top">
                                                <?php echo date('d/m/Y h:i A', strtotime($m['created_at'])); ?>
                                            </div>
                                            <div class="entry-body" style="white-space: pre-wrap;"><?php echo html($m['message']); ?></div>
                                            
                                            <?php if (!empty($m['file_path'])): ?>
                                                <div class="mt-3 p-2 border rounded" style="max-width: 300px; display:flex; align-items:center; gap: 10px;">
                                                    <i class="bi bi-file-earmark-pdf fs-3 text-danger"></i>
                                                    <div style="flex:1; min-width:0;">
                                                        <div class="text-truncate fw-bold small">Documento_Cotizacion.pdf</div>
                                                        <a href="../<?php echo html($m['file_path']); ?>" target="_blank" class="small text-decoration-none">Descargar</a>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <?php if ($quote['status'] === 'pending' || $quote['status'] === 'answered'): ?>
            <!-- Quote Actions -->
            <style>
                .quote-action-panel { background: #f8fafc; border: 1px dashed #cbd5e1; }
                body.dark-mode .quote-action-panel { background: #1e293b; border-color: #334155; }
                body.dark-mode .quote-action-panel h5 { color: #f8fafc; }
                body.dark-mode .quote-action-panel p { color: #94a3b8 !important; }
            </style>
            <div class="card-soft mt-4 quote-action-panel">
                <div class="body p-4 text-center">
                    <?php if ($quote['status'] === 'pending'): ?>
                        <h5 class="fw-bold mb-3">Acción Requerida</h5>
                        <p class="text-muted mb-4">Se ha registrado la solicitud de cotización. Por favor, apruébala para continuar con la generación formal del documento.</p>
                        <div class="d-flex justify-content-center gap-3 flex-wrap">
                            <form method="POST" class="d-inline-block m-0">
                                <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">
                                <input type="hidden" name="action_type" value="request_quote">
                                <button type="submit" class="btn btn-action-primary">
                                    <i class="bi bi-check2-circle"></i> Aprobar Cotización
                                </button>
                            </form>
                            <form method="POST" class="d-inline-block m-0">
                                <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">
                                <input type="hidden" name="action_type" value="reject_quote">
                                <button type="submit" class="btn btn-action-outline">
                                    <i class="bi bi-x-circle"></i> Rechazar Cotización
                                </button>
                            </form>
                        </div>
                    <?php elseif ($quote['status'] === 'answered'): ?>
                        <h5 class="fw-bold mb-3">Resolución de Cotización</h5>
                        <p class="text-muted mb-4">Revisa la información proporcionada y el documento adjunto. Puedes aceptar o rechazar esta cotización.</p>
                        <div class="d-flex justify-content-center gap-3 flex-wrap">
                            <form method="POST" style="margin:0;" class="d-inline-block">
                                <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">
                                <input type="hidden" name="action_type" value="accept_quote">
                                <button type="submit" class="btn btn-action-primary">
                                    <i class="bi bi-check-lg"></i> Aceptar Cotización
                                </button>
                            </form>
                            <form method="POST" style="margin:0;" class="d-inline-block">
                                <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">
                                <input type="hidden" name="action_type" value="reject_quote">
                                <button type="submit" class="btn btn-action-outline">
                                    <i class="bi bi-x-lg"></i> Rechazar Cotización
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>



        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const alerts = document.querySelectorAll('.auto-dismiss-alert');
        alerts.forEach(alert => {
            // Ocultar mensaje después de 5 segundos (5000 ms)
            setTimeout(() => {
                alert.style.transition = 'opacity 0.5s ease, margin 0.5s ease, padding 0.5s ease, height 0.5s ease';
                alert.style.opacity = '0';
                alert.style.margin = '0';
                alert.style.padding = '0';
                alert.style.height = '0';
                alert.style.overflow = 'hidden';
                setTimeout(() => alert.remove(), 500);
            }, 5000);
        });
    });
</script>
</body>
</html>
