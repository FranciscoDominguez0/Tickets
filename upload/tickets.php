<?php
/**
 * VER TICKETS (USUARIO)
 * Lista de tickets del usuario
 */

require_once '../config.php';
require_once '../includes/helpers.php';

// Validar que sea cliente
requireLogin('cliente');

$user = getCurrentUser();

// AJAX: check for new staff replies while user is on tickets.php
if (isset($_GET['action']) && $_GET['action'] === 'check_staff_replies') {
    header('Content-Type: application/json; charset=utf-8');

    $uidAjax = (int)($_SESSION['user_id'] ?? 0);
    $eidAjax = (int)($_SESSION['empresa_id'] ?? 0);
    if ($eidAjax <= 0) $eidAjax = 1;
    $sinceId = isset($_GET['since_id']) && is_numeric($_GET['since_id']) ? (int)$_GET['since_id'] : 0;
    if ($sinceId < 0) $sinceId = 0;

    if (!isset($mysqli) || !$mysqli || $uidAjax <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
        exit;
    }

    $hasStaffIdCol = false;
    try {
        $col = $mysqli->query("SHOW COLUMNS FROM thread_entries LIKE 'staff_id'");
        $hasStaffIdCol = ($col && $col->num_rows > 0);
    } catch (Throwable $e) {
        $hasStaffIdCol = false;
    }

    if (!$hasStaffIdCol) {
        echo json_encode(['ok' => true, 'items' => [], 'max_id' => $sinceId]);
        exit;
    }

    $items = [];
    $maxId = $sinceId;

    $stmtN = $mysqli->prepare(
        "SELECT te.id, te.created, tk.id AS ticket_id, tk.ticket_number, tk.subject\n"
        . "FROM tickets tk\n"
        . "JOIN threads th ON th.ticket_id = tk.id\n"
        . "JOIN thread_entries te ON te.thread_id = th.id\n"
        . "WHERE tk.user_id = ? AND tk.empresa_id = ? AND te.staff_id IS NOT NULL AND te.id > ?\n"
        . "ORDER BY te.id ASC\n"
        . "LIMIT 5"
    );
    if ($stmtN) {
        $stmtN->bind_param('iii', $uidAjax, $eidAjax, $sinceId);
        if ($stmtN->execute()) {
            $rs = $stmtN->get_result();
            while ($rs && ($r = $rs->fetch_assoc())) {
                $id = (int)($r['id'] ?? 0);
                if ($id > $maxId) $maxId = $id;
                $items[] = [
                    'id' => $id,
                    'created' => (string)($r['created'] ?? ''),
                    'ticket_id' => (int)($r['ticket_id'] ?? 0),
                    'ticket_number' => (string)($r['ticket_number'] ?? ''),
                    'subject' => (string)($r['subject'] ?? ''),
                ];
            }
        }
    }

    echo json_encode([
        'ok' => true,
        'items' => $items,
        'max_id' => $maxId,
    ]);
    exit;
}

// AJAX: user notifications (DB)
if (isset($_GET['action']) && in_array((string)$_GET['action'], ['user_notifs_count', 'user_notifs_list', 'user_notifs_mark_read'], true)) {
    header('Content-Type: application/json; charset=utf-8');

    $uidAjax = (int)($_SESSION['user_id'] ?? 0);
    $eidAjax = (int)($_SESSION['empresa_id'] ?? 0);
    if ($eidAjax <= 0) $eidAjax = 1;
    if (!isset($mysqli) || !$mysqli || $uidAjax <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
        exit;
    }

    $hasUserNotifs = false;
    try {
        $chkT = @$mysqli->query("SHOW TABLES LIKE 'user_notifications'");
        $hasUserNotifs = ($chkT && $chkT->num_rows > 0);
    } catch (Throwable $e) {
        $hasUserNotifs = false;
    }

    if (!$hasUserNotifs) {
        echo json_encode(['ok' => true, 'has_table' => false, 'count' => 0, 'items' => []]);
        exit;
    }

    $action = (string)$_GET['action'];

    if ($action === 'user_notifs_mark_read') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
            exit;
        }
        $id = isset($_POST['id']) && is_numeric($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id <= 0) {
            echo json_encode(['ok' => false, 'error' => 'Invalid id']);
            exit;
        }
        $stmtU = $mysqli->prepare('UPDATE user_notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND empresa_id = ? AND user_id = ?');
        if ($stmtU) {
            $stmtU->bind_param('iii', $id, $eidAjax, $uidAjax);
            $stmtU->execute();
        }
        $stmtD = $mysqli->prepare('DELETE FROM user_notifications WHERE id = ? AND empresa_id = ? AND user_id = ?');
        if ($stmtD) {
            $stmtD->bind_param('iii', $id, $eidAjax, $uidAjax);
            $stmtD->execute();
        }
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'user_notifs_count') {
        $cnt = 0;
        $stmt = $mysqli->prepare('SELECT COUNT(*) c FROM user_notifications WHERE empresa_id = ? AND user_id = ? AND is_read = 0');
        if ($stmt) {
            $stmt->bind_param('ii', $eidAjax, $uidAjax);
            if ($stmt->execute()) {
                $cnt = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
            }
        }
        echo json_encode(['ok' => true, 'has_table' => true, 'count' => $cnt]);
        exit;
    }

    // user_notifs_list
    $items = [];
    $stmt = $mysqli->prepare('SELECT id, type, message, ticket_id, thread_entry_id, created_at FROM user_notifications WHERE empresa_id = ? AND user_id = ? AND is_read = 0 ORDER BY id DESC LIMIT 10');
    if ($stmt) {
        $stmt->bind_param('ii', $eidAjax, $uidAjax);
        if ($stmt->execute()) {
            $rs = $stmt->get_result();
            while ($rs && ($r = $rs->fetch_assoc())) {
                $items[] = [
                    'id' => (int)($r['id'] ?? 0),
                    'type' => (string)($r['type'] ?? ''),
                    'message' => (string)($r['message'] ?? ''),
                    'ticket_id' => (int)($r['ticket_id'] ?? 0),
                    'thread_entry_id' => (int)($r['thread_entry_id'] ?? 0),
                    'created_at' => (string)($r['created_at'] ?? ''),
                ];
            }
        }
    }
    echo json_encode(['ok' => true, 'has_table' => true, 'items' => $items]);
    exit;
}

$eid = (int)($_SESSION['empresa_id'] ?? 0);
if ($eid <= 0) $eid = 1;

$shouldProcessMailQueue = (!empty($_SESSION['pending_mail_queue_needs_process']) && (int)$_SESSION['pending_mail_queue_needs_process'] === 1);

$flashMsg = '';
if (!empty($_SESSION['flash_msg'])) {
    $flashMsg = (string)$_SESSION['flash_msg'];
    unset($_SESSION['flash_msg']);
}

$preventOpenBack = !empty($_SESSION['prevent_open_back']);
if ($preventOpenBack) {
    unset($_SESSION['prevent_open_back']);
}

$newTicketId = 0;
if (!empty($_SESSION['new_ticket_id'])) {
    $newTicketId = (int)$_SESSION['new_ticket_id'];
    unset($_SESSION['new_ticket_id']);
}

$filter = $_GET['filter'] ?? 'open';
if (!in_array($filter, ['open', 'closed', 'all'], true)) $filter = 'open';
$q = trim($_GET['q'] ?? '');
$where = 't.user_id = ? AND t.empresa_id = ?';
if ($filter === 'open') {
    $where .= ' AND t.closed IS NULL';
} elseif ($filter === 'closed') {
    $where .= ' AND t.closed IS NOT NULL';
}

// Obtener tickets del usuario
$tickets = [];
$sql = '
    SELECT t.id, t.ticket_number, t.subject, t.created, t.closed,
           ts.name as status_name, ts.color as status_color,
           p.name as priority_name, p.color as priority_color
    FROM tickets t
    LEFT JOIN ticket_status ts ON t.status_id = ts.id
    LEFT JOIN priorities p ON t.priority_id = p.id
    WHERE ' . $where;
if ($q !== '') {
    $sql .= ' AND (t.ticket_number LIKE ? OR t.subject LIKE ?)';
}
$sql .= ' ORDER BY COALESCE(t.updated, t.created) DESC, t.created DESC';

$stmt = $mysqli->prepare($sql);
$uid = (int) ($_SESSION['user_id'] ?? 0);
if ($q !== '') {
    $like = '%' . $q . '%';
    $stmt->bind_param('iiss', $uid, $eid, $like, $like);
} else {
    $stmt->bind_param('ii', $uid, $eid);
}
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $tickets[] = $row;
}

$countOpen = 0;
$countClosed = 0;
$stmtC = $mysqli->prepare('SELECT SUM(closed IS NULL) AS c_open, SUM(closed IS NOT NULL) AS c_closed FROM tickets WHERE user_id = ? AND empresa_id = ?');
$stmtC->bind_param('ii', $uid, $eid);
$stmtC->execute();
if ($r = $stmtC->get_result()->fetch_assoc()) {
    $countOpen = (int) ($r['c_open'] ?? 0);
    $countClosed = (int) ($r['c_closed'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Tickets - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
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
                radial-gradient(900px circle at 88% 10%, rgba(99, 102, 241, 0.10), transparent 55%),
                repeating-linear-gradient(135deg, rgba(15, 23, 42, 0.02) 0px, rgba(15, 23, 42, 0.02) 1px, transparent 1px, transparent 14px);
            z-index: -1;
        }

        .topbar {
            background: linear-gradient(135deg, #0b1220, #111827);
            border-bottom: 1px solid rgba(255, 255, 255, 0.12);
        }
        .topbar.navbar {
            padding-top: 0 !important;
            padding-bottom: 0 !important;
        }
        .topbar .container-fluid {
            padding-top: 2px;
            padding-bottom: 2px;
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
            justify-content: center;
            box-shadow: none;
            display: inline-flex;
            align-items: center;
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
        .agent-login-brand img {
            height: 54px;
            width: auto;
            max-width: 100%;
            object-fit: contain;
            display: block;
            filter: drop-shadow(0 10px 30px rgba(0,0,0,0.22));
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
            font-weight: 1000;
            letter-spacing: 0.08em;
            flex: 0 0 auto;
        }
        .topbar .profile-brand .avatar {
            width: 36px;
            height: 36px;
            border-radius: 14px;
            background: rgba(255,255,255,0.92);
            color: #0b1220;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 1000;
            letter-spacing: 0.08em;
            flex: 0 0 auto;
        }
        .topbar .profile-brand .name {
            font-weight: 900;
            font-size: 0.98rem;
            line-height: 1.1;
        }
        .topbar .btn { border-radius: 999px; font-weight: 700; }

        .container-main {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .shell {
            max-width: 980px;
            margin: 0 auto;
        }

        .panel-soft {
            background: rgba(255, 255, 255, 0.82);
            backdrop-filter: blur(10px);
            border-radius: 22px;
            border: 1px solid rgba(226, 232, 240, 0.9);
            box-shadow: 0 22px 60px rgba(15, 23, 42, 0.08);
            overflow: hidden;
        }

        .panel-soft {
            background-image:
                radial-gradient(900px circle at 0% 0%, rgba(37, 99, 235, 0.06), transparent 52%),
                radial-gradient(700px circle at 100% 0%, rgba(245, 158, 11, 0.06), transparent 55%);
        }

        .page-header {
            background: linear-gradient(180deg, #ffffff, #f8fafc);
            padding: 22px 22px;
            border-radius: 16px;
            margin-bottom: 18px;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
            color: #0f172a;
            border: 1px solid #e2e8f0;
            border-left: 6px solid #2563eb;
        }
        .page-header .sub { color: #64748b; font-weight: 700; }

        .panel {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.06);
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }

        .panel {
            transition: box-shadow .15s ease, border-color .15s ease;
        }
        .panel:hover {
            box-shadow: 0 14px 36px rgba(15, 23, 42, 0.10);
            border-color: #cbd5e1;
        }

        .tabs {
            display: flex;
            gap: 0;
            padding: 0 18px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }
        .tabs a {
            padding: 14px 16px;
            text-decoration: none;
            font-weight: 700;
            color: #64748b;
            border-bottom: 3px solid transparent;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .tabs a:hover { color: #0f172a; background: rgba(15,23,42,0.03); }
        .tabs a.active { color: #2563eb; border-bottom-color: #2563eb; background: #fff; border-radius: 10px 10px 0 0; }
        .tabs .count { background: #e2e8f0; color: #0f172a; padding: 2px 8px; border-radius: 999px; font-size: 0.8rem; }

        .panel-head {
            padding: 16px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }
        .search {
            min-width: 260px;
            max-width: 420px;
            width: 100%;
        }

        .tickets-table { padding: 0 18px 18px; overflow-x: auto; }
        .tickets-table .table { min-width: 720px; }
        .tickets-table .table { margin-bottom: 0; }
        .tickets-table .table thead th { font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.05em; color: #475569; background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
        .tickets-table .table tbody tr:hover { background: #f8fafc; }
        .tickets-table .table tbody tr { transition: background .12s ease; }
        .ticket-new-highlight { background: rgba(37, 99, 235, 0.08) !important; box-shadow: inset 0 0 0 2px rgba(37, 99, 235, 0.25); }
        .ticket-new-badge { display: inline-flex; align-items: center; gap: 6px; padding: 2px 10px; border-radius: 999px; font-size: 0.72rem; font-weight: 900; letter-spacing: 0.04em; text-transform: uppercase; background: rgba(16, 185, 129, 0.14); color: #065f46; border: 1px solid rgba(16, 185, 129, 0.25); margin-left: 10px; }
        .badge-soft { display: inline-block; padding: 6px 10px; border-radius: 10px; font-weight: 700; font-size: 0.85rem; }
        .mono { font-variant-numeric: tabular-nums; }
        .dropdown-menu .notif-item:hover { background: #f1f5f9; }

        @import url('https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&display=swap');

        /* ── Grid ── */
        .ticket-cards {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
            padding: 22px 22px 26px;
        }
        @media (max-width: 992px) { .ticket-cards { grid-template-columns: repeat(2,1fr); } }
        @media (max-width: 576px)  { .ticket-cards { grid-template-columns: 1fr; padding: 14px; gap: 14px; } }

        /* ── Card shell ── */
        .ticket-card {
            position: relative;
            display: flex;
            flex-direction: column;
            border-radius: 18px;
            border: 1px solid #e8edf5;
            background: #fff;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(15,23,42,.04), 0 8px 20px rgba(15,23,42,.06);
            transition:
                transform .24s cubic-bezier(.22,1,.36,1),
                box-shadow .24s cubic-bezier(.22,1,.36,1),
                border-color .18s ease;
        }
        .ticket-card:hover {
            transform: translateY(-6px);
            border-color: #c5d5f0;
            box-shadow: 0 0 0 4px rgba(37,99,235,.06), 0 24px 52px rgba(15,23,42,.14);
        }

        /* ── Top tinted header band ── */
        .ticket-card-head {
            position: relative;
            padding: 18px 20px 16px 20px;
            background: linear-gradient(135deg,
                color-mix(in srgb, var(--tc-status-color, #2563eb) 8%, #fff) 0%,
                #fff 80%);
            border-bottom: 1px solid rgba(0,0,0,.05);
        }
        @supports not (background: color-mix(in srgb, red 8%, white)) {
            .ticket-card-head { background: #f8fafc; }
        }

        /* glowing dot indicator (top-right) */
        .ticket-card-head::after {
            content: '';
            position: absolute; top: 18px; right: 20px;
            width: 9px; height: 9px; border-radius: 50%;
            background: var(--tc-status-color, #2563eb);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--tc-status-color, #2563eb) 22%, transparent);
        }
        @supports not (background: color-mix(in srgb, red 8%, white)) {
            .ticket-card-head::after { box-shadow: none; }
        }

        /* ── Number + badge row ── */
        .ticket-card-top {
            display: flex; align-items: center;
            justify-content: space-between; gap: 8px;
            margin-bottom: 10px;
        }
        .ticket-card-number {
            font-family: 'DM Mono', monospace;
            font-size: .74rem; font-weight: 500;
            letter-spacing: .06em; color: #64748b;
        }
        .ticket-card-number a {
            color: inherit; text-decoration: none;
            padding: 3px 10px; border-radius: 7px;
            background: rgba(255,255,255,.85); border: 1px solid rgba(0,0,0,.10);
            transition: background .14s, color .14s, border-color .14s;
        }
        .ticket-card-number a:hover { background: #fff; color: #2563eb; border-color: #bfdbfe; }

        .ticket-new-badge {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 3px 8px; border-radius: 999px;
            font-size: .66rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase;
            background: #ecfdf5; color: #065f46; border: 1px solid #6ee7b7;
            animation: pulseBadge 2.5s ease infinite;
        }
        @keyframes pulseBadge {
            0%,100% { box-shadow: 0 0 0 0 rgba(16,185,129,.4); }
            55%      { box-shadow: 0 0 0 6px rgba(16,185,129,.0); }
        }

        /* ── Subject ── */
        .ticket-card-subject {
            font-size: 1rem; font-weight: 700; line-height: 1.38;
            color: #0f172a; margin: 0; letter-spacing: -.01em;
            display: -webkit-box;
            -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
        }

        /* ── Body section ── */
        .ticket-card-body {
            display: flex; flex-direction: column; flex: 1;
            padding: 14px 20px 18px; gap: 12px;
        }

        /* ── Badges ── */
        .ticket-card-meta { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
        .ticket-card .badge-soft {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 5px 11px; border-radius: 999px;
            font-size: .72rem; font-weight: 700; letter-spacing: .02em; line-height: 1;
            border: 1px solid transparent;
        }

        /* ── Footer ── */
        .ticket-card-foot {
            display: flex; align-items: center;
            justify-content: space-between; gap: 10px;
            padding-top: 12px; border-top: 1px solid #f1f5f9; margin-top: auto;
        }
        .ticket-card-dates { display: flex; flex-direction: column; gap: 3px; }
        .ticket-card-date-row {
            display: flex; align-items: center; gap: 5px;
            font-size: .75rem; color: #94a3b8; line-height: 1.25;
        }
        .ticket-card-date-row i { font-size: .67rem; }
        .ticket-card-date-row.is-closed-date { color: #10b981; }

        /* ── CTA ── */
        .ticket-card-btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 18px; border-radius: 999px;
            font-size: .81rem; font-weight: 700;
            background: #2563eb; color: #fff; border: none;
            text-decoration: none; white-space: nowrap; flex-shrink: 0;
            box-shadow: 0 2px 8px rgba(37,99,235,.30);
            transition: background .15s, box-shadow .15s, transform .15s;
        }
        .ticket-card-btn i { transition: transform .2s cubic-bezier(.22,1,.36,1); }
        .ticket-card-btn:hover { background: #1d4ed8; color: #fff; box-shadow: 0 4px 18px rgba(37,99,235,.42); transform: scale(1.06); }
        .ticket-card-btn:hover i { transform: translateX(3px); }
        .ticket-card.is-closed .ticket-card-btn { background: #64748b; box-shadow: 0 2px 8px rgba(100,116,139,.22); }
        .ticket-card.is-closed .ticket-card-btn:hover { background: #475569; }

        /* ── New highlight ── */
        .ticket-card.ticket-new-highlight {
            border-color: #bfdbfe;
            box-shadow: 0 0 0 3px rgba(37,99,235,.10), 0 8px 24px rgba(37,99,235,.12);
        }
        .ticket-card.ticket-new-highlight .ticket-card-head::after {
            animation: dotPulse 1.8s ease infinite;
        }
        @keyframes dotPulse {
            0%,100% { box-shadow: 0 0 0 0 rgba(37,99,235,.5); }
            55%      { box-shadow: 0 0 0 7px rgba(37,99,235,.0); }
        }

        .notif-dd {
            border-radius: 18px;
            border: 1px solid rgba(226,232,240,0.95);
            overflow: hidden;
            box-shadow: 0 22px 55px rgba(15, 23, 42, 0.22);
        }
        .notif-dd-head {
            background: radial-gradient(900px circle at 0% 0%, rgba(255,255,255,0.35), transparent 55%),
                        linear-gradient(135deg, #2563eb, #0ea5e9);
            color: #fff;
        }
        .notif-dd-title {
            font-weight: 900;
            letter-spacing: 0.02em;
        }
        .notif-dd-sub {
            opacity: .85;
            font-weight: 700;
            font-size: .85rem;
        }
        .notif-dd-count {
            background: rgba(255,255,255,0.22);
            border: 1px solid rgba(255,255,255,0.28);
            color: #fff;
            padding: 3px 10px;
            border-radius: 999px;
            font-weight: 900;
            font-size: .78rem;
        }
        .notif-empty {
            border: 1px dashed rgba(148, 163, 184, 0.6);
            background: rgba(248, 250, 252, 0.7);
            border-radius: 16px;
        }
        .notif-item {
            border: 1px solid rgba(226,232,240,0.95);
            background: #fff;
            transition: transform .12s ease, box-shadow .12s ease, background .12s ease;
        }
        .notif-item:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 26px rgba(15, 23, 42, 0.10);
        }
        .notif-item + .notif-item { margin-top: 10px; }

        @media (max-width: 576px) {
            .container-main { padding: 0 12px; margin: 18px auto; }
            .shell { max-width: 100%; }
            .tabs { overflow-x: auto; -webkit-overflow-scrolling: touch; }
            .tabs a { white-space: nowrap; }
            .tickets-table { padding: 0 12px 12px; }
        }
    </style>
    <?php if ($shouldProcessMailQueue): ?>
    <script>
        (function(){
            try {
                var fd = new FormData();
                fd.append('csrf_token', <?php echo json_encode((string)($_SESSION['csrf_token'] ?? '')); ?>);
                fetch('process_mail_queue.php', {
                    method: 'POST',
                    body: fd,
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                }).catch(function(){});
            } catch (e) {}
        })();
    </script>
    <?php endif; ?>

    <?php if ($preventOpenBack || $flashMsg !== ''): ?>
    <script>
        (function(){
            try {
                if (window.history && history.replaceState) {
                    history.replaceState(null, document.title, 'tickets.php');
                    history.pushState(null, document.title, 'tickets.php');
                    window.addEventListener('popstate', function(){
                        try {
                            history.pushState(null, document.title, 'tickets.php');
                            window.location.replace('tickets.php');
                        } catch (e) {}
                    });
                }
            } catch (e) {}
        })();
    </script>
    <?php endif; ?>

</head>
<body>
    <?php
        $navUserName = trim((string)($user['name'] ?? ''));
        $companyName = trim((string)getAppSetting('company.name', ''));
        $companyLogoUrlRaw = (string)getCompanyLogoUrl('publico/img/vigitec-logo.png');
        $companyLogoV = 1;
        try {
            $pLogo = parse_url($companyLogoUrlRaw, PHP_URL_PATH);
            if (is_string($pLogo) && $pLogo !== '') {
                $pos = strpos($pLogo, '/upload/');
                if ($pos !== false) {
                    $rel = substr($pLogo, $pos + 8);
                    $fs = rtrim((string)__DIR__, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($rel, '/'));
                    if (is_file($fs)) {
                        $companyLogoV = (int)@filemtime($fs);
                        if ($companyLogoV <= 0) $companyLogoV = 1;
                    }
                } else {
                    $pos2 = strpos($pLogo, '/publico/');
                    if ($pos2 !== false) {
                        $rel2 = substr($pLogo, $pos2 + 9);
                        $fs2 = rtrim((string)realpath(__DIR__ . '/..'), '/\\') . DIRECTORY_SEPARATOR . 'publico' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($rel2, '/'));
                        if (is_file($fs2)) {
                            $companyLogoV = (int)@filemtime($fs2);
                            if ($companyLogoV <= 0) $companyLogoV = 1;
                        }
                    }
                }
            }
        } catch (Throwable $e) {
        }
        $companyLogoUrl = $companyLogoUrlRaw . (strpos($companyLogoUrlRaw, '?') !== false ? '&' : '?') . 'v=' . (string)$companyLogoV;
        $navInitials = '';
        $parts = preg_split('/\s+/', trim($navUserName));
        if (!empty($parts[0])) $navInitials .= (function_exists('mb_substr') ? mb_substr($parts[0], 0, 1) : substr($parts[0], 0, 1));
        if (!empty($parts[1])) $navInitials .= (function_exists('mb_substr') ? mb_substr($parts[1], 0, 1) : substr($parts[1], 0, 1));
        $navInitials = strtoupper($navInitials ?: 'U');
        if ($navUserName === '') $navUserName = 'Mi Perfil';
    ?>
    <nav class="navbar navbar-dark topbar" style="position: fixed; top: 0; left: 0; width: 100%; z-index: 1030;">
        <div class="container-fluid">
            <a class="navbar-brand profile-brand" href="tickets.php">
                <span class="brand-logo-wrap" aria-hidden="true">
                    <img class="brand-logo" src="<?php echo html($companyLogoUrl); ?>" alt="<?php echo html($companyName !== '' ? $companyName : 'Logo'); ?>">
                </span>
            </a>
            <div class="d-flex align-items-center gap-2">
                <div class="dropdown">
                    <button class="btn btn-outline-light btn-sm user-menu-btn" type="button" id="notifBellBtn" data-bs-toggle="dropdown" aria-expanded="false" title="Notificaciones">
                        <i class="bi bi-bell"></i>
                        <span id="notifBellBadge" class="badge bg-danger ms-1" style="display:none; font-size:.7rem;">0</span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end p-0 notif-dd" style="min-width: 380px;" aria-labelledby="notifBellBtn">
                        <div class="p-3 notif-dd-head">
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
                        <div id="notifBellList" class="p-3" style="max-height: 360px; overflow:auto;">
                            <div class="notif-empty text-center text-muted py-3" style="font-size:.92rem">
                                <div class="mb-1" style="font-weight:900;color:#0f172a;">Todo al día</div>
                                <div style="color:#64748b;">Cuando el equipo responda, te aparecerá aquí.</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="dropdown">
                    <button class="btn btn-outline-light btn-sm dropdown-toggle user-menu-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="uavatar" aria-hidden="true"><?php echo html($navInitials); ?></span>
                        <span class="d-none d-sm-inline"><?php echo html($navUserName); ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="tickets.php"><i class="bi bi-inboxes"></i> Mis Tickets</a></li>
                        <li><a class="dropdown-item" href="open.php"><i class="bi bi-plus-circle"></i> Crear Ticket</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person"></i> Mi perfil</a></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right"></i> Cerrar sesión</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-main">
        <div class="shell">
            <main class="panel-soft" style="padding: 18px;">
                <div class="page-header" style="margin-top: 0;">
                    <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
                        <div>
                            <h2 class="mb-1">Mis Tickets</h2>
                            <div class="sub">Gestiona tus solicitudes y revisa respuestas del equipo.</div>
                        </div>
                        <div>
                            <a href="open.php" class="btn btn-light btn-sm" style="border-radius: 999px; font-weight: 800;"><i class="bi bi-plus-circle"></i> Abrir ticket</a>
                        </div>
                    </div>
                </div>

                <?php if ($flashMsg !== ''): ?>
                    <div class="alert alert-success" role="alert" id="tickets-flash-success"><?php echo html($flashMsg); ?></div>
                    <script>
                        (function(){
                            try {
                                var el = document.getElementById('tickets-flash-success');
                                if (!el) return;
                                window.setTimeout(function(){
                                    try {
                                        el.style.transition = 'opacity 220ms ease, max-height 260ms ease, margin 260ms ease, padding 260ms ease';
                                        el.style.opacity = '0';
                                        el.style.maxHeight = '0';
                                        el.style.margin = '0';
                                        el.style.paddingTop = '0';
                                        el.style.paddingBottom = '0';
                                        window.setTimeout(function(){
                                            if (el && el.parentNode) el.parentNode.removeChild(el);
                                        }, 320);
                                    } catch (e) {}
                                }, 3500);
                            } catch (e) {}
                        })();
                    </script>
                <?php endif; ?>

                <div class="panel">
                    <div class="tabs">
                        <a class="<?php echo $filter === 'open' ? 'active' : ''; ?>" href="tickets.php?filter=open<?php echo $q !== '' ? '&q=' . urlencode($q) : ''; ?>">
                            <i class="bi bi-folder2-open"></i> Abiertos <span class="count"><?php echo (int)$countOpen; ?></span>
                        </a>
                        <a class="<?php echo $filter === 'closed' ? 'active' : ''; ?>" href="tickets.php?filter=closed<?php echo $q !== '' ? '&q=' . urlencode($q) : ''; ?>">
                            <i class="bi bi-check2-circle"></i> Cerrados <span class="count"><?php echo (int)$countClosed; ?></span>
                        </a>
                        <a class="<?php echo $filter === 'all' ? 'active' : ''; ?>" href="tickets.php?filter=all<?php echo $q !== '' ? '&q=' . urlencode($q) : ''; ?>">
                            <i class="bi bi-inboxes"></i> Todos <span class="count"><?php echo (int)($countOpen + $countClosed); ?></span>
                        </a>
                    </div>

                    <div class="panel-head">
                        <div class="text-muted">Filtros y búsqueda</div>
                        <form method="get" class="search">
                            <input type="hidden" name="filter" value="<?php echo html($filter); ?>">
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control" name="q" value="<?php echo html($q); ?>" placeholder="Buscar por número o asunto">
                                <button class="btn btn-primary" type="submit">Buscar</button>
                            </div>
                        </form>
                    </div>

                    <?php if (empty($tickets)): ?>
                        <div class="text-center py-5">
                            <div class="text-muted mb-3">No hay tickets para este filtro.</div>
                            <a href="open.php" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Abrir ticket</a>
                        </div>
                    <?php else: ?>
                        <div class="ticket-cards">
                            <?php foreach ($tickets as $ticket): ?>
                                <?php
                                    $isNew    = ($newTicketId > 0 && (int)$ticket['id'] === (int)$newTicketId);
                                    $isClosed = !empty($ticket['closed']);

                                    $statusColor = (string)($ticket['status_color'] ?? '');
                                    if (!preg_match('~^#([0-9a-f]{3}|[0-9a-f]{6})$~i', $statusColor)) $statusColor = '#2563eb';

                                    $priorityColor = (string)($ticket['priority_color'] ?? '');
                                    if ($priorityColor === '' || !preg_match('~^#([0-9a-f]{3}|[0-9a-f]{6})$~i', $priorityColor)) $priorityColor = '#64748b';

                                    $pName = strtolower((string)($ticket['priority_name'] ?? ''));
                                    $priorityIcon = 'bi-flag';
                                    if (str_contains($pName,'alta')||str_contains($pName,'high')||str_contains($pName,'urgent')) $priorityIcon = 'bi-flag-fill';
                                    elseif (str_contains($pName,'media')||str_contains($pName,'medium')) $priorityIcon = 'bi-flag-fill';

                                    $cardClass = 'ticket-card';
                                    if ($isNew)    $cardClass .= ' ticket-new-highlight';
                                    if ($isClosed) $cardClass .= ' is-closed';

                                    $createdFmt = date('d M Y · H:i', strtotime($ticket['created']));
                                    $closedFmt  = !empty($ticket['closed']) ? date('d M Y · H:i', strtotime($ticket['closed'])) : '';
                                ?>
                                <div id="ticket-row-<?php echo (int)$ticket['id']; ?>"
                                     class="<?php echo $cardClass; ?>"
                                     style="--tc-status-color:<?php echo html($statusColor); ?>;">

                                    <!-- HEADER BAND: número + asunto con fondo tintado -->
                                    <div class="ticket-card-head">
                                        <div class="ticket-card-top">
                                            <span class="ticket-card-number mono">
                                                <a href="view-ticket.php?id=<?php echo (int)$ticket['id']; ?>">
                                                    <?php echo html($ticket['ticket_number']); ?>
                                                </a>
                                            </span>
                                            <?php if ($isNew): ?>
                                                <span class="ticket-new-badge">
                                                    <i class="bi bi-lightning-charge-fill"></i> Nuevo
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <p class="ticket-card-subject"><?php echo html($ticket['subject']); ?></p>
                                    </div>

                                    <!-- BODY: badges + fechas + botón -->
                                    <div class="ticket-card-body">
                                        <div class="ticket-card-meta">
                                            <?php if (!empty($ticket['status_name'])): ?>
                                                <span class="badge-soft"
                                                      style="background:<?php echo html($statusColor); ?>18;
                                                             color:<?php echo html($statusColor); ?>;
                                                             border-color:<?php echo html($statusColor); ?>35;">
                                                    <i class="bi bi-circle-fill" style="font-size:.38rem;"></i>
                                                    <?php echo html($ticket['status_name']); ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if (!empty($ticket['priority_name'])): ?>
                                                <span class="badge-soft"
                                                      style="background:<?php echo html($priorityColor); ?>18;
                                                             color:<?php echo html($priorityColor); ?>;
                                                             border-color:<?php echo html($priorityColor); ?>35;">
                                                    <i class="bi <?php echo $priorityIcon; ?>" style="font-size:.60rem;"></i>
                                                    <?php echo html($ticket['priority_name']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <div class="ticket-card-foot">
                                            <div class="ticket-card-dates">
                                                <div class="ticket-card-date-row">
                                                    <i class="bi bi-calendar3"></i>
                                                    <?php echo $createdFmt; ?>
                                                </div>
                                                <?php if ($closedFmt !== ''): ?>
                                                    <div class="ticket-card-date-row is-closed-date">
                                                        <i class="bi bi-check-circle-fill"></i>
                                                        <?php echo $closedFmt; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <a href="view-ticket.php?id=<?php echo (int)$ticket['id']; ?>"
                                               class="ticket-card-btn">
                                                Ver <i class="bi bi-arrow-right"></i>
                                            </a>
                                        </div>
                                    </div>

                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
    <footer style="text-align: center; padding: 20px 0; background-color: #f8f9fa; border-top: 1px solid #dee2e6; margin-top: 40px; color: #6c757d; font-size: 12px;">
        <p style="margin: 0;">
            Derechos de autor &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(getAppSetting('company.name', 'Vigitec Panama')); ?> - Sistema de Tickets - Todos los derechos reservados.
        </p>
    </footer>

    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 2000;">
        <div id="staffReplyToast" class="toast align-items-center text-bg-primary border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <div class="fw-bold">Nueva respuesta en tu ticket</div>
                    <div id="staffReplyToastText" style="font-size:.9rem">Tienes una nueva actualización del equipo.</div>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (function(){
            var POLL_MS = 12000;

            function showToast(msg) {
                try {
                    var toastEl = document.getElementById('staffReplyToast');
                    var textEl = document.getElementById('staffReplyToastText');
                    if (!toastEl || !textEl) return;
                    textEl.textContent = msg;
                    bootstrap.Toast.getOrCreateInstance(toastEl, { delay: 6500 }).show();
                } catch (e) {}
            }

            function formatNotifMessage(raw) {
                try {
                    var s = (raw || '').toString().trim();
                    if (!s) return '';
                    var m = s.match(/ticket\s*(#?\d+)/i);
                    if (m && m[1]) {
                        return 'Respuesta nueva · Ticket #' + String(m[1]).replace('#','');
                    }
                    return s;
                } catch (e) {
                    return (raw || '').toString();
                }
            }

            function formatNotifWhen(raw) {
                try {
                    var s = (raw || '').toString().trim();
                    if (!s) return '';
                    var d = new Date(s.replace(' ', 'T'));
                    if (!isFinite(d.getTime())) return s;
                    return d.toLocaleString('es-PA', {
                        day: '2-digit',
                        month: 'short',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                } catch (e) {
                    return (raw || '').toString();
                }
            }

            function tryBrowserNotify(title, body, url) {
                try {
                    if (!('Notification' in window)) return;
                    if (Notification.permission !== 'granted') return;
                    var n = new Notification(title, { body: body });
                    n.onclick = function(){
                        try { window.focus(); } catch (e) {}
                        if (url) window.location.href = url;
                        try { n.close(); } catch (e) {}
                    };
                } catch (e) {}
            }

            function renderBell(items) {
                try {
                    var list = document.getElementById('notifBellList');
                    if (!list) return;
                    if (!items || !items.length) {
                        list.innerHTML = '<div class="text-center text-muted py-3" style="font-size:.9rem">Sin notificaciones</div>';
                        return;
                    }
                    var html = '';
                    items.forEach(function(it){
                        var msg = formatNotifMessage(it.message || '');
                        var when = formatNotifWhen(it.created_at || '');
                        var href = it.ticket_id ? ('view-ticket.php?id=' + String(it.ticket_id)) : 'tickets.php';
                        html += ''
                            + '<div class="notif-item rounded-3 px-2 py-2" style="cursor:pointer;">'
                            +   '<div class="d-flex align-items-start gap-2">'
                            +     '<div class="flex-shrink-0" style="width:34px;height:34px;border-radius:12px;background:rgba(37,99,235,.12);display:flex;align-items:center;justify-content:center;color:#2563eb;">'
                            +       '<i class="bi bi-chat-dots"></i>'
                            +     '</div>'
                            +     '<div class="flex-grow-1">'
                            +       '<div class="text-dark" style="font-weight:800;font-size:.92rem;line-height:1.15;">' + msg.replace(/</g,'&lt;') + '</div>'
                            +       '<div class="text-muted" style="font-size:.78rem;">' + when.replace(/</g,'&lt;') + '</div>'
                            +     '</div>'
                            +     '<div class="flex-shrink-0">'
                            +       '<button class="btn btn-sm btn-outline-primary" data-mark-read="' + String(it.id) + '" data-href="' + href + '" style="border-radius:999px;">Ver</button>'
                            +     '</div>'
                            +   '</div>'
                            + '</div>';
                    });
                    list.innerHTML = html;
                } catch (e) {}
            }

            function setBellCount(n) {
                try {
                    var badge = document.getElementById('notifBellBadge');
                    var pill = document.getElementById('notifBellCountPill');
                    if (!badge) return;
                    var v = parseInt(n || 0, 10) || 0;
                    badge.textContent = String(v);
                    badge.style.display = v > 0 ? '' : 'none';
                    if (pill) {
                        pill.textContent = String(v) + ' nuevas';
                        pill.style.display = v > 0 ? '' : 'none';
                    }
                } catch (e) {}
            }

            function poll(firstRun) {
                fetch('tickets.php?action=user_notifs_count', { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                    .then(function(r){ return r.json(); })
                    .then(function(data){
                        if (!data || !data.ok) return;
                        setBellCount(data.count || 0);
                        var cnt = (parseInt(data.count || 0, 10) || 0);
                        if (cnt <= 0) {
                            renderBell([]);
                            return;
                        }
                        return fetch('tickets.php?action=user_notifs_list', { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                            .then(function(r){ return r.json(); })
                            .then(function(d2){
                                if (!d2 || !d2.ok) return;
                                var items = Array.isArray(d2.items) ? d2.items : [];
                                renderBell(items);
                                if (!items.length) return;
                                var last = items[0] || {};
                                var lastId = parseInt(last.id || 0, 10) || 0;
                                var seenId = 0;
                                try { seenId = parseInt(localStorage.getItem('tickets_last_notif_id') || '0', 10) || 0; } catch (e) { seenId = 0; }
                                if (firstRun) {
                                    try { if (lastId > 0) localStorage.setItem('tickets_last_notif_id', String(lastId)); } catch (e) {}
                                    return;
                                }
                                if (lastId <= 0 || lastId <= seenId) return;
                                try { localStorage.setItem('tickets_last_notif_id', String(lastId)); } catch (e) {}
                                var msg = formatNotifMessage(last.message || '');
                                if (!msg) msg = 'Respuesta nueva · Revisa tu ticket';
                                showToast(msg);
                                tryBrowserNotify('Nueva respuesta', msg, last.ticket_id ? ('view-ticket.php?id=' + String(last.ticket_id)) : 'tickets.php');
                            });
                    })
                    .catch(function(){});
            }

            document.addEventListener('click', function(ev){
                try {
                    var btn = ev.target && ev.target.getAttribute ? ev.target.getAttribute('data-mark-read') : null;
                    if (!btn) return;
                    ev.preventDefault();
                    var id = parseInt(btn, 10) || 0;
                    if (!id) return;
                    var href = ev.target.getAttribute('data-href') || 'tickets.php';
                    var fd = new FormData();
                    fd.append('id', String(id));
                    fetch('tickets.php?action=user_notifs_mark_read', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                        .then(function(){ window.location.href = href; })
                        .catch(function(){ window.location.href = href; });
                } catch (e) {}
            });

            poll(true);
            window.setInterval(function(){ poll(false); }, POLL_MS);
        })();
    </script>
</body>
</html>