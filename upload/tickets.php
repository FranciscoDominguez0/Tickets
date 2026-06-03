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
if (isset($_GET['action']) && in_array((string)$_GET['action'], ['user_notifs_count', 'user_notifs_list', 'user_notifs_mark_read', 'user_notifs_mark_all_read'], true)) {
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

    if ($action === 'user_notifs_mark_all_read') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
            exit;
        }
        // Marcar todas como leídas
        $stmtU = $mysqli->prepare('UPDATE user_notifications SET is_read = 1, read_at = NOW() WHERE empresa_id = ? AND user_id = ? AND is_read = 0');
        if ($stmtU) {
            $stmtU->bind_param('ii', $eidAjax, $uidAjax);
            $stmtU->execute();
        }
        // Opcional: eliminar todas las notificaciones (descomentar si se prefiere borrar en lugar de marcar)
        // $stmtD = $mysqli->prepare('DELETE FROM user_notifications WHERE empresa_id = ? AND user_id = ?');
        // if ($stmtD) {
        //     $stmtD->bind_param('ii', $eidAjax, $uidAjax);
        //     $stmtD->execute();
        // }
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
$uid = (int)($_SESSION['user_id'] ?? 0);

$canOrgTicketsView = userOrgTicketsViewEnabled($mysqli, $uid, $eid);
$isOrgExplorer = $canOrgTicketsView && (($_GET['view'] ?? '') === 'org');
$orgExplorerOrgId = isset($_GET['org_id']) && is_numeric($_GET['org_id']) ? (int)$_GET['org_id'] : 0;
$orgExplorerMemberId = isset($_GET['member_id']) && is_numeric($_GET['member_id']) ? (int)$_GET['member_id'] : 0;
$orgExplorerOrgs = [];
$orgExplorerMembers = [];
$orgExplorerTickets = [];
$orgExplorerOrgName = '';
$orgExplorerMemberName = '';
$orgListPerPage = 10;
$orgUsersPage = max(1, (int)($_GET['oup'] ?? 1));
$orgTicketsPage = max(1, (int)($_GET['otp'] ?? 1));
$orgUsersTotal = 0;
$orgUsersTotalPages = 1;
$orgTicketsTotal = 0;
$orgTicketsTotalPages = 1;
$orgExplorerListMode = (isset($_GET['list']) && (string)$_GET['list'] === 'all') ? 'all' : 'users';
$orgAllTicketsPage = max(1, (int)($_GET['oat'] ?? 1));
$orgAllTicketsTotal = 0;
$orgAllTicketsTotalPages = 1;
$orgExplorerAllTickets = [];
$ticketMonthFilter = null;
$ticketMonthOptions = [];
$ticketMonthSql = '';
$ticketMonthTypes = '';
$ticketMonthParams = [];
$ticketMonthQuery = '';

if ($isOrgExplorer) {
    $ticketMonthFilter = parseTicketMonthFilter($_GET['month'] ?? null);
    $ticketMonthOptions = listTicketMonthFilterOptions(36);
    $ticketMonthSql = ticketMonthFilterSqlClause($ticketMonthFilter);
    $ticketMonthTypes = $ticketMonthSql !== '' ? 'ss' : '';
    $ticketMonthParams = $ticketMonthSql !== '' ? [$ticketMonthFilter['start'], $ticketMonthFilter['end']] : [];
    $ticketMonthQuery = ticketMonthFilterQueryString($ticketMonthFilter);

    if (!$canOrgTicketsView) {
        header('Location: tickets.php');
        exit;
    }
    $orgExplorerOrgs = getPortalOrganizationsForUser($mysqli, $uid, $eid);
    if ($orgExplorerOrgId > 0) {
        foreach ($orgExplorerOrgs as $oRow) {
            if ((int)($oRow['organization_id'] ?? 0) === $orgExplorerOrgId) {
                $orgExplorerOrgName = (string)($oRow['name'] ?? '');
                break;
            }
        }
        if ($orgExplorerOrgName === '') {
            $orgExplorerOrgId = 0;
        } else {
            $orgUsersTotal = countOrganizationUsers($mysqli, $eid, $orgExplorerOrgId, $orgExplorerOrgName);
            $orgUsersTotalPages = $orgUsersTotal > 0 ? (int)ceil($orgUsersTotal / $orgListPerPage) : 1;
            $orgUsersPage = min($orgUsersPage, max(1, $orgUsersTotalPages));
            $orgUsersOffset = ($orgUsersPage - 1) * $orgListPerPage;

            if ($orgExplorerMemberId <= 0 && $orgExplorerListMode === 'all') {
                $orgAllTicketsTotal = countPortalOrganizationTickets($mysqli, $eid, $orgExplorerOrgId, $orgExplorerOrgName, $ticketMonthFilter);
                $orgAllTicketsTotalPages = $orgAllTicketsTotal > 0 ? (int)ceil($orgAllTicketsTotal / $orgListPerPage) : 1;
                $orgAllTicketsPage = min($orgAllTicketsPage, max(1, $orgAllTicketsTotalPages));
                $orgAllTicketsOffset = ($orgAllTicketsPage - 1) * $orgListPerPage;
                $orgExplorerAllTickets = fetchPortalOrganizationTickets(
                    $mysqli,
                    $eid,
                    $orgExplorerOrgId,
                    $orgExplorerOrgName,
                    $orgListPerPage,
                    $orgAllTicketsOffset,
                    $ticketMonthFilter
                );
            } elseif ($orgExplorerMemberId <= 0) {
                $orgExplorerMembers = fetchOrganizationUsers(
                    $mysqli,
                    $eid,
                    $orgExplorerOrgId,
                    $orgExplorerOrgName,
                    $orgListPerPage,
                    $orgUsersOffset
                );
            } else {
                $memberRow = null;
                if (organizationMembershipEnabled($mysqli)) {
                    $stmtM = $mysqli->prepare(
                        "SELECT u.id, u.firstname, u.lastname, u.email FROM users u
                         WHERE u.id = ? AND u.empresa_id = ?
                         AND (
                            EXISTS (
                                SELECT 1 FROM user_organizations uo
                                WHERE uo.user_id = u.id AND uo.organization_id = ? AND uo.empresa_id = ?
                            )
                            OR (
                                TRIM(COALESCE(u.company, '')) <> '' AND u.company = ?
                                AND NOT EXISTS (
                                    SELECT 1 FROM user_organizations uo2
                                    WHERE uo2.user_id = u.id AND uo2.empresa_id = ?
                                )
                            )
                         ) LIMIT 1"
                    );
                    if ($stmtM) {
                        $stmtM->bind_param('iiiisi', $orgExplorerMemberId, $eid, $orgExplorerOrgId, $eid, $orgExplorerOrgName, $eid);
                        if ($stmtM->execute()) {
                            $memberRow = $stmtM->get_result()->fetch_assoc();
                        }
                    }
                } else {
                    $stmtM = $mysqli->prepare(
                        'SELECT id, firstname, lastname, email FROM users
                         WHERE id = ? AND empresa_id = ? AND company = ? LIMIT 1'
                    );
                    if ($stmtM) {
                        $stmtM->bind_param('iis', $orgExplorerMemberId, $eid, $orgExplorerOrgName);
                        if ($stmtM->execute()) {
                            $memberRow = $stmtM->get_result()->fetch_assoc();
                        }
                    }
                }

                if (!$memberRow) {
                    $orgExplorerMemberId = 0;
                    $orgExplorerMembers = fetchOrganizationUsers(
                        $mysqli,
                        $eid,
                        $orgExplorerOrgId,
                        $orgExplorerOrgName,
                        $orgListPerPage,
                        $orgUsersOffset
                    );
                } else {
                    $orgExplorerMemberName = trim((string)($memberRow['firstname'] ?? '') . ' ' . (string)($memberRow['lastname'] ?? ''));
                    if ($orgExplorerMemberName === '') {
                        $orgExplorerMemberName = (string)($memberRow['email'] ?? 'Usuario');
                    }

                    $stmtTc = $mysqli->prepare(
                        'SELECT COUNT(*) AS c FROM tickets t WHERE t.user_id = ? AND t.empresa_id = ?' . $ticketMonthSql
                    );
                    if ($stmtTc) {
                        mysqliBindParams(
                            $stmtTc,
                            'ii' . $ticketMonthTypes,
                            [$orgExplorerMemberId, $eid],
                            $ticketMonthParams
                        );
                        if ($stmtTc->execute()) {
                            $orgTicketsTotal = (int)($stmtTc->get_result()->fetch_assoc()['c'] ?? 0);
                        }
                    }
                    $orgTicketsTotalPages = $orgTicketsTotal > 0 ? (int)ceil($orgTicketsTotal / $orgListPerPage) : 1;
                    $orgTicketsPage = min($orgTicketsPage, max(1, $orgTicketsTotalPages));
                    $orgTicketsOffset = ($orgTicketsPage - 1) * $orgListPerPage;

                    $stmtOt = $mysqli->prepare(
                        'SELECT t.id, t.ticket_number, t.subject, t.created, t.closed,
                                ts.name AS status_name, ts.color AS status_color,
                                (SELECT status FROM ticket_approvals WHERE ticket_id = t.id ORDER BY id DESC LIMIT 1) AS approval_status
                         FROM tickets t
                         LEFT JOIN ticket_status ts ON t.status_id = ts.id
                         WHERE t.user_id = ? AND t.empresa_id = ?' . $ticketMonthSql . '
                         ORDER BY COALESCE(t.updated, t.created) DESC
                         LIMIT ? OFFSET ?'
                    );
                    if ($stmtOt) {
                        mysqliBindParams(
                            $stmtOt,
                            'ii' . $ticketMonthTypes . 'ii',
                            [$orgExplorerMemberId, $eid],
                            array_merge($ticketMonthParams, [$orgListPerPage, $orgTicketsOffset])
                        );
                        if ($stmtOt->execute()) {
                            $orgExplorerTickets = $stmtOt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
                        }
                    }
                }
            }
        }
    }
}

if (!isset($_SESSION['client_dark_mode'])) {
    $_SESSION['client_dark_mode'] = 0;
    if (isset($mysqli) && $mysqli && !empty($_SESSION['user_id'])) {
        $uidT = (int)$_SESSION['user_id'];
        try {
            $colRes = $mysqli->query("SHOW COLUMNS FROM users LIKE 'dark_mode'");
            if ($colRes && $colRes->num_rows > 0) {
                $rs = $mysqli->query("SELECT dark_mode FROM users WHERE id = $uidT");
                if ($rs && $r = $rs->fetch_assoc()) {
                    $_SESSION['client_dark_mode'] = (int)$r['dark_mode'];
                }
            }
        } catch (Throwable $e) {}
    }
}
$isDarkMode = (isset($_SESSION['client_dark_mode']) && (int)$_SESSION['client_dark_mode'] === 1);

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

if (isset($_GET['msg']) && $_GET['msg'] === 'signed') {
    $flashMsg = 'Ticket firmado y cerrado correctamente. ¡Gracias!';
}

$filter = $_GET['filter'] ?? 'open';
if (!in_array($filter, ['open', 'closed', 'all', 'signatures'], true)) $filter = 'open';
$q = trim($_GET['q'] ?? '');

// Firmas pendientes (si existe soporte en esquema)
$pendingSignCount = 0;
$pendingSignTickets = [];
try {
    $hasSigReqCol = dbColumnExists('tickets', 'signature_requested');
    $hasSigTokenCol = dbColumnExists('tickets', 'signature_token');
    if ($hasSigReqCol && $hasSigTokenCol) {
        $stmtPS = $mysqli->prepare(
            'SELECT id, ticket_number, subject, created
             FROM tickets
             WHERE user_id = ? AND empresa_id = ? AND closed IS NULL AND signature_requested = 1 AND signature_token IS NOT NULL
             ORDER BY COALESCE(updated, created) DESC
             LIMIT 5'
        );
        $uidPS = (int)($_SESSION['user_id'] ?? 0);
        $stmtPS->bind_param('ii', $uidPS, $eid);
        if ($stmtPS->execute()) {
            $rsPS = $stmtPS->get_result();
            while ($rsPS && ($r = $rsPS->fetch_assoc())) {
                $pendingSignTickets[] = $r;
            }
        }

        $stmtPSC = $mysqli->prepare(
            'SELECT COUNT(*) c
             FROM tickets
             WHERE user_id = ? AND empresa_id = ? AND closed IS NULL AND signature_requested = 1 AND signature_token IS NOT NULL'
        );
        $stmtPSC->bind_param('ii', $uidPS, $eid);
        if ($stmtPSC->execute()) {
            $pendingSignCount = (int)($stmtPSC->get_result()->fetch_assoc()['c'] ?? 0);
        }
    }

    $hasClientSigCol = dbColumnExists('tickets', 'client_signature');
    $countSignatures = 0;
    $sqlSig = 'SELECT COUNT(*) AS c FROM tickets WHERE user_id = ? AND empresa_id = ? AND (';
    $sigConditions = [];
    if ($hasSigReqCol) $sigConditions[] = '(signature_requested = 1 AND signature_token IS NOT NULL)';
    if ($hasClientSigCol) $sigConditions[] = 'client_signature IS NOT NULL';
    
    if (!empty($sigConditions)) {
        $sqlSig .= implode(' OR ', $sigConditions) . ')';
        $stmtSig = $mysqli->prepare($sqlSig);
        $stmtSig->bind_param('ii', $uidPS, $eid);
        if ($stmtSig->execute()) {
            $countSignatures = (int)($stmtSig->get_result()->fetch_assoc()['c'] ?? 0);
        }
    }
} catch (Throwable $e) {
    $pendingSignCount = 0;
    $pendingSignTickets = [];
}

$blockNewIfSignaturePending = ((string)getAppSetting('tickets.block_new_if_signature_pending', '0') === '1');
$sigBlockPortal = ($blockNewIfSignaturePending && $pendingSignCount > 0);

// Aprobaciones pendientes (si es jefe)
$pendingApprovalCount = 0;
$pendingApprovalFirstOrgId = 0;
if ($canOrgTicketsView) {
    $orgs = getPortalOrganizationsForUser($mysqli, $uid, $eid);
    if (!empty($orgs)) {
        $orgConds = [];
        if (organizationMembershipEnabled($mysqli)) {
            $orgConds[] = "EXISTS (
                SELECT 1 FROM user_organizations uo1 
                INNER JOIN user_organizations uo2 ON uo1.organization_id = uo2.organization_id 
                WHERE uo1.user_id = t.user_id AND uo2.user_id = ? AND uo1.empresa_id = t.empresa_id
            )";
        }
        $orgConds[] = "(TRIM(COALESCE(u.company, '')) <> '' AND u.company = (SELECT company FROM users WHERE id = ?))";
        
        $sqlApprCount = "SELECT COUNT(DISTINCT t.id) as c FROM tickets t 
                    INNER JOIN users u ON t.user_id = u.id AND u.empresa_id = t.empresa_id
                    INNER JOIN ticket_approvals ta ON ta.ticket_id = t.id
                    WHERE t.empresa_id = ? AND ta.status = 'pending' AND t.closed IS NULL AND (" . implode(" OR ", $orgConds) . ")";
        $stmtApprCount = $mysqli->prepare($sqlApprCount);
        if ($stmtApprCount) {
            if (organizationMembershipEnabled($mysqli)) {
                $stmtApprCount->bind_param('iii', $eid, $uid, $uid);
            } else {
                $stmtApprCount->bind_param('ii', $eid, $uid);
            }
            if ($stmtApprCount->execute()) {
                $pendingApprovalCount = (int)($stmtApprCount->get_result()->fetch_assoc()['c'] ?? 0);
            }
        }

        // Buscar la organización que realmente tiene tickets pendientes de aprobación
        if ($pendingApprovalCount > 0 && organizationMembershipEnabled($mysqli)) {
            $sqlFirstOrg = "SELECT uo1.organization_id FROM tickets t
                INNER JOIN users u ON t.user_id = u.id AND u.empresa_id = t.empresa_id
                INNER JOIN ticket_approvals ta ON ta.ticket_id = t.id
                INNER JOIN user_organizations uo1 ON uo1.user_id = t.user_id AND uo1.empresa_id = t.empresa_id
                INNER JOIN user_organizations uo2 ON uo2.organization_id = uo1.organization_id AND uo2.user_id = ?
                WHERE t.empresa_id = ? AND ta.status = 'pending' AND t.closed IS NULL
                ORDER BY ta.created_at DESC LIMIT 1";
            $stmtFirstOrg = $mysqli->prepare($sqlFirstOrg);
            if ($stmtFirstOrg) {
                $stmtFirstOrg->bind_param('ii', $uid, $eid);
                if ($stmtFirstOrg->execute()) {
                    $rowFirstOrg = $stmtFirstOrg->get_result()->fetch_assoc();
                    if ($rowFirstOrg) {
                        $pendingApprovalFirstOrgId = (int)$rowFirstOrg['organization_id'];
                    }
                }
            }
        }
        if ($pendingApprovalFirstOrgId <= 0 && !empty($orgs)) {
            $pendingApprovalFirstOrgId = (int)($orgs[0]['organization_id'] ?? 0);
        }
    }
}

// Paginación fija: 10 tickets por página (mejor rendimiento)
$perPage = 10;
$tickets = [];
$countOpen = 0;
$countClosed = 0;
$totalFiltered = 0;
$totalPages = 1;
$page = isset($_GET['p']) && is_numeric($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$offset = ($page - 1) * $perPage;

if ($isOrgExplorer) {
    // El explorador por organización usa su propia vista (partials/client-org-tickets.inc.php)
} else {
$where = 't.user_id = ? AND t.empresa_id = ?';
if ($filter === 'open') {
    $where .= ' AND t.closed IS NULL';
} elseif ($filter === 'closed') {
    $where .= ' AND t.closed IS NOT NULL';
} elseif ($filter === 'signatures') {
    $where .= ' AND (';
    $whereSigs = [];
    $hasSigReqCol = dbColumnExists('tickets', 'signature_requested');
    $hasClientSigCol = dbColumnExists('tickets', 'client_signature');
    if ($hasSigReqCol) $whereSigs[] = '(t.signature_requested = 1 AND t.signature_token IS NOT NULL)';
    if ($hasClientSigCol) $whereSigs[] = 't.client_signature IS NOT NULL';
    if (empty($whereSigs)) $whereSigs[] = '0=1';
    $where .= implode(' OR ', $whereSigs) . ')';
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
$sql .= ' LIMIT ? OFFSET ?';

$stmt = $mysqli->prepare($sql);
if ($q !== '') {
    $like = '%' . $q . '%';
    $stmt->bind_param('iissii', $uid, $eid, $like, $like, $perPage, $offset);
} else {
    $stmt->bind_param('iiii', $uid, $eid, $perPage, $offset);
}
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $tickets[] = $row;
}

// Total real para paginación
$totalFiltered = 0;
$sqlCount = 'SELECT COUNT(*) c FROM tickets t WHERE ' . $where;
if ($q !== '') $sqlCount .= ' AND (t.ticket_number LIKE ? OR t.subject LIKE ?)';
$stmtCount = $mysqli->prepare($sqlCount);
if ($q !== '') {
    $like2 = '%' . $q . '%';
    $stmtCount->bind_param('iiss', $uid, $eid, $like2, $like2);
} else {
    $stmtCount->bind_param('ii', $uid, $eid);
}
$stmtCount->execute();
$totalFiltered = (int)($stmtCount->get_result()->fetch_assoc()['c'] ?? 0);
$totalPages = max(1, (int)ceil($totalFiltered / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$stmtC = $mysqli->prepare('SELECT SUM(closed IS NULL) AS c_open, SUM(closed IS NOT NULL) AS c_closed FROM tickets WHERE user_id = ? AND empresa_id = ?');
$stmtC->bind_param('ii', $uid, $eid);
$stmtC->execute();
if ($r = $stmtC->get_result()->fetch_assoc()) {
    $countOpen = (int) ($r['c_open'] ?? 0);
    $countClosed = (int) ($r['c_closed'] ?? 0);
}
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Tickets - <?php echo APP_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="<?php echo html(rtrim(defined('APP_URL') ? APP_URL : '', '/')); ?>/publico/img/favicon.ico">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            background: #f6f7fb;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding-top: 62px;
        }

        /* Dynamic badge styles */
        [style*="--badge-bg-light"] {
            background-color: var(--badge-bg-light) !important;
            color: var(--badge-color-light) !important;
            border: 1px solid transparent !important;
            transition: background-color 0.3s, color 0.3s, border-color 0.3s;
        }
        body.dark-mode [style*="--badge-bg-light"] {
            background-color: var(--badge-bg-dark) !important;
            color: var(--badge-color-dark) !important;
            border-color: var(--badge-border-dark) !important;
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
        .topbar.navbar {
            padding-top: 0 !important;
            padding-bottom: 0 !important;
        }
        .topbar .container-fluid {
            padding-top: 2px;
            padding-bottom: 2px;
            flex-wrap: nowrap !important;
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
                radial-gradient(900px circle at 0% 0%, rgba(239, 68, 68, 0.06), transparent 52%),
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
            border-left: 6px solid #ef4444;
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
        .tabs a.active { color: #ef4444; border-bottom-color: #ef4444; background: #fff; border-radius: 10px 10px 0 0; }
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
        .ticket-new-highlight { background: rgba(239, 68, 68, 0.08) !important; box-shadow: inset 0 0 0 2px rgba(239, 68, 68, 0.25); }
        .ticket-new-badge { 
            display: inline-flex; 
            align-items: center; 
            gap: 6px; 
            padding: 2px 10px; 
            border-radius: 999px; 
            font-size: 0.72rem; 
            font-weight: 900; 
            letter-spacing: 0.04em; 
            text-transform: uppercase; 
            background: rgba(239, 68, 68, 0.1); 
            color: #b91c1c; 
            border: 1px solid rgba(239, 68, 68, 0.2); 
            margin-left: 10px; 
        }

        .badge-soft { display: inline-block; padding: 6px 10px; border-radius: 10px; font-weight: 700; font-size: 0.85rem; border: 1px solid transparent; }
        .mono { font-variant-numeric: tabular-nums; }
        .dropdown-menu .notif-item:hover { background: #f1f5f9; }

        .ticket-cards {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
            padding: 0 18px 18px;
        }

        @media (max-width: 992px) {
            .ticket-cards { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }

        @media (max-width: 576px) {
            .ticket-cards { grid-template-columns: 1fr; padding: 0 12px 12px; }
        }

        .ticket-card {
            border-radius: 16px;
            border: 1px solid rgba(226, 232, 240, 0.95);
            background: #fff;
            padding: 16px 16px;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
            transition: transform .12s ease, box-shadow .12s ease, border-color .12s ease;
        }

        .ticket-card:hover {
            transform: translateY(-1px);
            box-shadow: 0 14px 32px rgba(15, 23, 42, 0.10);
            border-color: rgba(203, 213, 225, 1);
        }

        .ticket-card.ticket-new-highlight {
            border-color: rgba(239, 68, 68, 0.35);
            box-shadow: 0 14px 34px rgba(239, 68, 68, 0.16);
        }

        .ticket-card-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 12px;
        }

        .ticket-card-number {
            font-weight: 900;
            letter-spacing: 0.02em;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .ticket-card-number a {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 999px;
            background: #f8fafc;
            border: 1px solid rgba(226, 232, 240, 1);
            color: #0f172a;
        }

        .ticket-card-subject {
            font-weight: 800;
            color: #0f172a;
            line-height: 1.25;
            margin: 0 0 10px;
            font-size: 1.02rem;
        }

        .ticket-card-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
            margin-bottom: 14px;
        }

        .ticket-card .badge-soft {
            border-radius: 999px;
            padding: 6px 12px;
            font-size: 0.82rem;
            font-weight: 800;
            letter-spacing: 0.01em;
            box-shadow: none;
        }

        .ticket-card-foot {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding-top: 12px;
            border-top: 1px solid rgba(226, 232, 240, 0.85);
        }

        .ticket-card-dates {
            font-size: 0.85rem;
            color: #64748b;
            line-height: 1.25;
        }

        .notif-dd {
            border-radius: 18px;
            border: 1px solid rgba(226,232,240,0.95);
            overflow: hidden;
            box-shadow: 0 22px 55px rgba(15, 23, 42, 0.22);
        }
        @media (max-width: 576px) {
            .notif-dd {
                position: fixed !important;
                top: 60px !important;
                left: 50% !important;
                right: auto !important;
                transform: translateX(-50%) !important;
                width: 320px !important;
                min-width: unset !important;
                max-width: 90vw !important;
                margin-top: 0 !important;
            }
        }
        .notif-dd-head {
            background: radial-gradient(900px circle at 0% 0%, rgba(255,255,255,0.35), transparent 55%),
                        linear-gradient(135deg, #ef4444, #f87171);
            color: #fff;
        }
        .notif-dd-flex.show {
            display: flex !important;
            flex-direction: column;
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

        /* ── Pagination ── */
        .pagination-bar {
            display: flex; align-items: center; justify-content: space-between;
            gap: 12px; flex-wrap: wrap;
            padding: 14px 22px 20px;
            border-top: 1px solid #f1f5f9;
        }
        .pagination-info {
            font-size: .80rem; color: #94a3b8; font-weight: 600;
        }
        .pagination-nav {
            display: flex; align-items: center; gap: 4px;
        }
        .pg-btn {
            display: inline-flex; align-items: center; justify-content: center;
            min-width: 34px; height: 34px; padding: 0 6px;
            border-radius: 9px; border: 1px solid #e2e8f0;
            background: #fff; color: #334155;
            font-size: .83rem; font-weight: 700; text-decoration: none;
            transition: background .14s, border-color .14s, color .14s, box-shadow .14s;
            cursor: pointer;
        }
        .pg-btn:hover {
            background: #fef2f2; border-color: #fecaca; color: #ef4444;
        }
        .pg-btn.pg-current {
            background: #ef4444; border-color: #ef4444; color: #fff;
            box-shadow: 0 2px 8px rgba(239,68,68,.28);
            cursor: default;
        }
        .pg-btn.pg-disabled {
            opacity: .35; cursor: not-allowed; pointer-events: none;
        }
        .pg-arrow { font-size: .78rem; }
        .pg-dots {
            display: inline-flex; align-items: center; justify-content: center;
            min-width: 28px; height: 34px;
            font-size: .83rem; color: #94a3b8; letter-spacing: .05em;
        }
        @media (max-width: 576px) {
            .pagination-bar { flex-direction: column; align-items: flex-start; gap: 8px; padding: 12px 14px 16px; }
        }

        /* ── Limit selector ── */
        .panel-head {
            padding: 12px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            border-bottom: 1px solid #f1f5f9;
            background: #fafbfc;
        }
        .panel-head .search {
            min-width: 220px;
            max-width: 360px;
            width: 100%;
        }
        @media (max-width: 640px) {
            .panel-head { flex-direction: column; align-items: stretch; }
            .panel-head .search { max-width: 100%; }
        }
        .limit-selector {
            display: inline-flex; align-items: center; gap: 4px;
            background: #f1f5f9; border-radius: 10px; padding: 4px;
        }
        @keyframes pulseHighlight {
            0% { box-shadow: 0 4px 12px rgba(217, 119, 6, 0.05); }
            50% { box-shadow: 0 0 25px rgba(245, 158, 11, 0.5); transform: scale(1.005); }
            100% { box-shadow: 0 4px 12px rgba(217, 119, 6, 0.05); }
        }
        .pulse-highlight { animation: pulseHighlight 0.6s ease-in-out 3; }
        .limit-label {
            font-size: .76rem; font-weight: 700; color: #94a3b8;
            padding: 0 8px; white-space: nowrap;
            display: flex; align-items: center; gap: 5px;
        }
        .limit-btn {
            display: inline-flex; align-items: center; justify-content: center;
            min-width: 36px; height: 28px; padding: 0 8px;
            border-radius: 7px; font-size: .78rem; font-weight: 700;
            color: #64748b; text-decoration: none;
            transition: background .13s, color .13s, box-shadow .13s;
            white-space: nowrap;
        }
        .limit-btn:hover { background: #fff; color: #ef4444; }
        .limit-btn.active {
            background: #fff; color: #ef4444;
            box-shadow: 0 1px 4px rgba(15,23,42,.10);
        }

        @media (max-width: 576px) {
            .limit-selector { flex-wrap: wrap; }
            .limit-label { width: 100%; padding: 0 4px; margin-bottom: 2px; }
        }

        @media (max-width: 576px) {
            body .container-main { padding: 0 10px !important; margin: 20px auto !important; }
            body .shell { max-width: 100% !important; }
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
            .tabs { 
                padding: 0 4px; 
                display: flex; 
                justify-content: space-between; 
                overflow: hidden;
            }
            .tabs a { 
                padding: 12px 2px; 
                flex: 1; 
                justify-content: center; 
                font-size: 0.82rem; 
                gap: 4px; 
                white-space: nowrap; 
            }
            .tabs .count { padding: 2px 5px; font-size: 0.72rem; }
            .tickets-table { padding: 0 12px 12px; }
        }

        /* Botones primarios (Rojo) */
        .btn-primary { background-color: #ef4444; border-color: #ef4444; color: #fff; }
        .btn-primary:hover, .btn-primary:focus, .btn-primary:active { background-color: #dc2626; border-color: #dc2626; color: #fff; }
        .btn-outline-primary { color: #ef4444; border-color: #ef4444; }
        .btn-outline-primary:hover { background-color: #ef4444; color: #fff; border-color: #ef4444; }
        .text-primary { color: #ef4444 !important; }
    </style>
    <link rel="stylesheet" href="css/client_dark.css?v=<?php echo (int)@filemtime(__DIR__ . '/css/client_dark.css'); ?>">
    <?php if (!empty($canOrgTicketsView)): ?>
    <link rel="stylesheet" href="css/client-org-explorer.css?v=<?php echo (int)@filemtime(__DIR__ . '/css/client-org-explorer.css'); ?>">
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
<body class="<?php echo $isDarkMode ? 'dark-mode' : ''; ?>">
    <?php
        $navUserName = trim((string)($user['name'] ?? ''));
        $companyName = trim((string)getAppSetting('company.name', ''));
        $companyLogoUrlRaw = (string)getCompanyLogoUrl('publico/img/vigitec-logo.webp');
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
                <?php if (true): // Siempre disponible para usuarios logueados ?>
                <form method="post" action="toggle_user_dark.php" class="d-inline" style="margin:0" id="clientDarkModeForm">
                    <?php csrfField(); ?>
                    <input type="hidden" name="dark_mode" value="<?php echo $isDarkMode ? '0' : '1'; ?>">
                    <input type="hidden" name="return" value="<?php echo html(basename((string)($_SERVER['PHP_SELF'] ?? 'tickets.php')) . (!empty($_SERVER['QUERY_STRING']) ? ('?' . (string)$_SERVER['QUERY_STRING']) : '')); ?>">
                    <button type="submit" class="btn btn-outline-light btn-sm user-theme-toggle" id="clientDarkModeBtn" title="Modo oscuro" style="border-radius:999px; font-weight:700; width:34px; height:34px; padding:0; display:inline-flex; align-items:center; justify-content:center;">
                        <i class="bi <?php echo $isDarkMode ? 'bi-sun' : 'bi-moon-stars'; ?> user-theme-toggle-icon" style="font-size:16px;"></i>
                    </button>
                </form>
                <?php endif; ?>
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
                        <?php if (!empty($canOrgTicketsView)): ?>
                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-3 profile-dd-item" href="tickets.php?view=org">
                                <div class="profile-dd-icon profile-dd-icon-default"><i class="bi bi-diagram-3"></i></div> Por organización
                            </a>
                        </li>
                        <?php endif; ?>
                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-3 profile-dd-item" href="open.php" <?php if (!empty($sigBlockPortal)): ?> onclick="window.showSigToast && window.showSigToast(); return false;" <?php endif; ?>>
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
        <div class="shell">
            <?php if (!empty($canOrgTicketsView) && $pendingApprovalCount > 0 && empty($isOrgExplorer)): ?>
            <style>
                .exec-review-alert {
                    display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;
                    padding: 16px 20px; border-radius: 16px; margin-bottom: 24px;
                    background: linear-gradient(135deg, #ffffff 0%, #fef2f2 100%);
                    border: 1px solid #fca5a5;
                    box-shadow: 0 4px 15px rgba(185, 28, 28, 0.08);
                    transition: all 0.3s ease;
                }
                .exec-review-alert__main { display: flex; align-items: center; gap: 16px; }
                .exec-review-alert__icon {
                    display: flex; align-items: center; justify-content: center;
                    width: 44px; height: 44px; border-radius: 12px;
                    background: #b91c1c; color: #ffffff; font-size: 1.3rem;
                    box-shadow: 0 4px 12px rgba(185, 28, 28, 0.25);
                }
                .exec-review-alert__text { color: #262626; font-size: 1rem; line-height: 1.4; margin: 0; }
                .exec-review-alert__text strong { color: #000000; font-weight: 700; }
                .exec-review-alert__btn {
                    background: #000000; color: #ffffff; border: 1px solid #000000;
                    font-weight: 600; padding: 8px 20px; border-radius: 999px;
                    text-decoration: none; transition: all 0.2s ease;
                    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
                    display: inline-flex; align-items: center; gap: 6px;
                }
                .exec-review-alert__btn:hover { background: #b91c1c; color: #ffffff; border-color: #b91c1c; transform: translateY(-1px); box-shadow: 0 4px 10px rgba(185, 28, 28, 0.3); }

                body.dark-mode .exec-review-alert {
                    background: linear-gradient(135deg, #171717 0%, #262626 100%);
                    border-color: rgba(185, 28, 28, 0.4);
                    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
                }
                body.dark-mode .exec-review-alert__icon {
                    background: rgba(185, 28, 28, 0.2); color: #fca5a5;
                    box-shadow: none; border: 1px solid rgba(185, 28, 28, 0.4);
                }
                body.dark-mode .exec-review-alert__text { color: #d4d4d4; }
                body.dark-mode .exec-review-alert__text strong { color: #ffffff; }
                body.dark-mode .exec-review-alert__btn {
                    background: rgba(185, 28, 28, 0.15); color: #fca5a5; border-color: rgba(185, 28, 28, 0.5);
                    box-shadow: none;
                }
                body.dark-mode .exec-review-alert__btn:hover {
                    background: rgba(185, 28, 28, 0.3); color: #ffffff; border-color: rgba(185, 28, 28, 0.8);
                }

                @media (max-width: 767.98px) {
                    .exec-review-alert {
                        flex-direction: column; align-items: stretch; gap: 12px;
                        padding: 14px 16px; border-radius: 14px; margin-bottom: 16px;
                    }
                    .exec-review-alert__main { gap: 12px; }
                    .exec-review-alert__icon {
                        width: 38px; height: 38px; border-radius: 10px; font-size: 1.1rem;
                        flex-shrink: 0;
                    }
                    .exec-review-alert__text { font-size: 0.88rem; line-height: 1.4; }
                    .exec-review-alert__btn {
                        width: 100%; justify-content: center;
                        padding: 10px 16px; font-size: 0.88rem; border-radius: 10px;
                    }
                }
            </style>
            <div class="exec-review-alert">
                <div class="exec-review-alert__main">
                    <div class="exec-review-alert__icon">
                        <i class="bi bi-shield-lock-fill"></i>
                    </div>
                    <div>
                        <p class="exec-review-alert__text">
                            <strong>Revisión Ejecutiva:</strong> Tienes <strong><?php echo $pendingApprovalCount; ?></strong> <?php echo $pendingApprovalCount === 1 ? 'ticket pendiente' : 'tickets pendientes'; ?> de aprobación.
                        </p>
                    </div>
                </div>
                <a href="tickets.php?view=org<?php echo $pendingApprovalFirstOrgId > 0 ? '&amp;org_id=' . $pendingApprovalFirstOrgId . '&amp;list=all' : ''; ?>" class="exec-review-alert__btn">
                    Ver tickets <i class="bi bi-arrow-right"></i>
                </a>
            </div>
            <?php endif; ?>
            <main class="panel-soft" style="padding: 18px;">
                <?php if (!empty($isOrgExplorer)): ?>
                    <?php require __DIR__ . '/partials/client-org-tickets.inc.php'; ?>
                <?php else: ?>
                <div class="page-header" style="margin-top: 0;">
                    <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
                        <div>
                            <h2 class="mb-1">Mis Tickets</h2>
                            <div class="sub">Gestiona tus solicitudes y revisa respuestas del equipo.</div>
                        </div>
                        <div class="d-flex gap-2 flex-wrap">
                            <?php if (!empty($canOrgTicketsView)): ?>
                            <a href="tickets.php?view=org" class="btn-org-ghost">
                                <i class="bi bi-diagram-3"></i> Por organización
                            </a>
                            <?php endif; ?>
                            <a href="open.php" class="btn btn-primary btn-sm" style="border-radius: 999px; font-weight: 800; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);" <?php if ($sigBlockPortal): ?> onclick="window.showSigToast && window.showSigToast(); return false;" <?php endif; ?>><i class="bi bi-plus-circle"></i> Abrir ticket</a>
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
                    <?php if ($pendingSignCount > 0): ?>
                        <div class="alert alert-warning d-flex flex-wrap align-items-center mb-4 p-3" style="border-radius: 16px; border: 1px solid #fde68a; background: #fffbeb; box-shadow: 0 4px 12px rgba(217, 119, 6, 0.05); gap: 12px;">
                            <div class="flex-shrink-0" style="width: 38px; height: 38px; border-radius: 12px; background: #fef3c7; display: flex; align-items: center; justify-content: center; color: #d97706;">
                                <i class="bi bi-exclamation-triangle-fill" style="font-size: 1.2rem;"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-0" style="font-weight: 800; color: #92400e;">Firmas pendientes</h6>
                                <p class="mb-0 d-none d-md-block" style="font-size: 0.88rem; color: #b45309; line-height: 1.4;">
                                    Tienes <strong><?php echo (int)$pendingSignCount; ?></strong> ticket(s) que requieren tu firma. Debes firmar para poder abrir nuevos reportes.
                                </p>
                                <p class="mb-0 d-md-none" style="font-size: 0.82rem; color: #b45309; line-height: 1.2;">
                                    Tienes <strong><?php echo (int)$pendingSignCount; ?></strong> firma(s) pendiente(s).
                                </p>
                            </div>
                            <div class="ms-md-auto d-flex gap-2">
                                <a href="view-ticket.php?id=<?php echo (int)$pendingSignTickets[0]['id']; ?>&sign=1" class="btn btn-warning btn-sm" style="border-radius: 10px; font-weight: 700; white-space: nowrap;">Firmar</a>
                                <a href="view-ticket.php?id=<?php echo (int)$pendingSignTickets[0]['id']; ?>" class="btn btn-outline-warning btn-sm" style="border-radius: 10px; font-weight: 700; white-space: nowrap;">Detalles</a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="tabs">
                        <a class="<?php echo $filter === 'open' ? 'active' : ''; ?>" href="tickets.php?filter=open<?php echo $q !== '' ? '&q=' . urlencode($q) : ''; ?>">
                            Abiertos <span class="count"><?php echo (int)$countOpen; ?></span>
                        </a>
                        <a class="<?php echo $filter === 'closed' ? 'active' : ''; ?>" href="tickets.php?filter=closed<?php echo $q !== '' ? '&q=' . urlencode($q) : ''; ?>">
                            Cerrados <span class="count"><?php echo (int)$countClosed; ?></span>
                        </a>
                        <a class="<?php echo $filter === 'all' ? 'active' : ''; ?>" href="tickets.php?filter=all<?php echo $q !== '' ? '&q=' . urlencode($q) : ''; ?>">
                            Todos <span class="count"><?php echo (int)($countOpen + $countClosed); ?></span>
                        </a>
                    </div>

                    <div class="panel-head">
                        <div>
                            <span style="font-size:.78rem;font-weight:600;color:#94a3b8;">
                                <i class="bi bi-layout-three-columns me-1"></i> Mostrando 10 por página
                            </span>
                        </div>
                        <form method="get" class="search">
                            <input type="hidden" name="filter" value="<?php echo html($filter); ?>">
                            <input type="hidden" name="p" value="1">
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control" name="q" value="<?php echo html($q); ?>" placeholder="Buscar por número o asunto…">
                                <button class="btn btn-primary" type="submit">Buscar</button>
                            </div>
                        </form>
                    </div>

                    <?php if (empty($tickets)): ?>
                        <div class="text-center py-5">
                            <div class="text-muted mb-3">No hay tickets para este filtro.</div>
                            <a href="open.php" 
                               class="btn btn-primary" 
                               style="border-radius: 999px; font-weight: 800; padding: 10px 24px;"
                               <?php if ($sigBlockPortal): ?>
                               onclick="window.showSigToast && window.showSigToast(); return false;"
                               <?php endif; ?>>
                                <i class="bi bi-plus-circle"></i> Abrir ticket
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="ticket-cards">
                            <?php foreach ($tickets as $ticket): ?>
                                <?php $isNew = ($newTicketId > 0 && (int)$ticket['id'] === (int)$newTicketId); ?>
                                <?php
                                    $statusColor = normalizeTicketHexColor((string)($ticket['status_color'] ?? ''), '#ef4444');
                                    $priorityColor = normalizeTicketHexColor((string)($ticket['priority_color'] ?? ''), '#64748b');
                                    $statusBadgeStyle = clientTicketBadgeStyle($statusColor, $isDarkMode);
                                    $priorityBadgeStyle = clientTicketBadgeStyle($priorityColor, $isDarkMode);
                                ?>
                                <div id="ticket-row-<?php echo (int)$ticket['id']; ?>" class="ticket-card <?php echo $isNew ? 'ticket-new-highlight' : ''; ?>">
                                    <div class="ticket-card-top">
                                        <div>
                                            <div class="ticket-card-number mono">
                                                <a href="view-ticket.php?id=<?php echo (int)$ticket['id']; ?>" class="text-decoration-none text-dark">
                                                    <?php echo html($ticket['ticket_number']); ?>
                                                </a>
                                            </div>
                                        </div>
                                        <div>
                                            <?php if ($isNew): ?>
                                                <span class="ticket-new-badge">Nuevo</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <p class="ticket-card-subject"><?php echo html($ticket['subject']); ?></p>

                                    <div class="ticket-card-meta">
                                        <span class="badge-soft" style="<?php echo html($statusBadgeStyle); ?>">
                                            <?php echo html($ticket['status_name']); ?>
                                        </span>
                                        <span class="badge-soft" style="<?php echo html($priorityBadgeStyle); ?>">
                                            <?php echo html($ticket['priority_name']); ?>
                                        </span>
                                    </div>

                                    <div class="ticket-card-foot">
                                        <div class="ticket-card-dates">
                                            <div>Creado: <?php echo date('d/m/Y h:i A', strtotime($ticket['created'])); ?></div>
                                            <?php if (!empty($ticket['closed'])): ?>
                                                <div>Cerrado: <?php echo date('d/m/Y h:i A', strtotime($ticket['closed'])); ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <a href="view-ticket.php?id=<?php echo (int)$ticket['id']; ?>" class="btn btn-sm btn-primary" style="border-radius: 999px;"><i class="bi bi-eye"></i> Ver</a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if ($perPage > 0 && $totalPages > 1): ?>
                        <div class="pagination-bar">
                            <?php
                                $baseUrl = 'tickets.php?filter=' . urlencode($filter) . ($q !== '' ? '&q=' . urlencode($q) : '');
                                $showWindow = 2; // páginas a cada lado de la actual
                            ?>
                            <div class="pagination-info">
                                Mostrando <?php echo ((int)$totalFiltered > 0) ? ((int)$offset + 1) : 0; ?>-<?php echo min((int)$offset + (int)count($tickets), (int)$totalFiltered); ?> de <?php echo (int)$totalFiltered; ?> tickets
                            </div>
                            <nav class="pagination-nav" aria-label="Paginación de tickets">
                                <!-- Anterior -->
                                <?php if ($page > 1): ?>
                                    <a href="<?php echo $baseUrl . '&p=' . ($page - 1); ?>" class="pg-btn pg-arrow" title="Anterior">
                                        <i class="bi bi-chevron-left"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="pg-btn pg-arrow pg-disabled"><i class="bi bi-chevron-left"></i></span>
                                <?php endif; ?>

                                <!-- Páginas -->
                                <?php for ($p = 1; $p <= $totalPages; $p++):
                                    $far = ($p > 1 + $showWindow && $p < $page - $showWindow) || ($p < $totalPages - $showWindow && $p > $page + $showWindow);
                                    if ($far) { if ($p === 2 || $p === $totalPages - 1) { echo '<span class="pg-dots">…</span>'; } continue; }
                                ?>
                                    <?php if ($p === $page): ?>
                                        <span class="pg-btn pg-current"><?php echo $p; ?></span>
                                    <?php else: ?>
                                        <a href="<?php echo $baseUrl . '&p=' . $p; ?>" class="pg-btn"><?php echo $p; ?></a>
                                    <?php endif; ?>
                                <?php endfor; ?>

                                <!-- Siguiente -->
                                <?php if ($page < $totalPages): ?>
                                    <a href="<?php echo $baseUrl . '&p=' . ($page + 1); ?>" class="pg-btn pg-arrow" title="Siguiente">
                                        <i class="bi bi-chevron-right"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="pg-btn pg-arrow pg-disabled"><i class="bi bi-chevron-right"></i></span>
                                <?php endif; ?>
                            </nav>
                        </div>
                        <?php endif; ?>

                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
    <footer style="text-align: center; padding: 20px 0; background-color: #f8f9fa; border-top: 1px solid #dee2e6; margin-top: 40px; color: #6c757d; font-size: 12px;">
        <p style="margin: 0;">
            Derechos de autor &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(getAppSetting('company.name', 'Vigitec Panama')); ?> - Sistema de Tickets - Todos los derechos reservados.
        </p>
    </footer>

    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 2000;">
        <div id="sigWarningToast" class="toast align-items-center text-bg-warning border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <div class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill"></i> Acción bloqueada</div>
                    <div style="font-size: .88rem; line-height: 1.3;">
                        Tienes <strong><?php echo (int)$pendingSignCount; ?></strong> firma(s) pendiente(s). 
                        Debes firmar tus tickets cerrados antes de abrir uno nuevo.
                    </div>
                    <?php if (!empty($pendingSignTickets)): ?>
                        <div class="mt-2">
                            <a href="view-ticket.php?id=<?php echo (int)$pendingSignTickets[0]['id']; ?>&sign=1" class="btn btn-dark btn-sm" style="font-weight: 800; border-radius: 8px;">Ir a firmar</a>
                        </div>
                    <?php endif; ?>
                </div>
                <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>

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
                        minute: '2-digit',
                        hour12: true
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
                            +     '<div class="flex-shrink-0" style="width:34px;height:34px;border-radius:12px;background:rgba(239,68,68,.12);display:flex;align-items:center;justify-content:center;color:#ef4444;">'
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

            // Botón "Marcar todas como leídas"
            (function(){
                var markAllBtn = document.getElementById('notifMarkAllRead');
                if (!markAllBtn) return;
                markAllBtn.addEventListener('click', function(ev){
                    ev.preventDefault();
                    ev.stopPropagation();
                    fetch('tickets.php?action=user_notifs_mark_all_read', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                        .then(function(r){ return r.json(); })
                        .then(function(data){
                            if (data && data.ok) {
                                // Limpiar la UI
                                setBellCount(0);
                                renderBell([]);
                                // Mostrar mensaje
                                var list = document.getElementById('notifBellList');
                                if (list) {
                                    list.innerHTML = '<div class="notif-empty text-center text-muted py-3" style="font-size:.92rem"><div class="mb-1" style="font-weight:900;color:#0f172a;">Todo al día</div><div style="color:#64748b;">Todas las notificaciones fueron marcadas como leídas.</div></div>';
                                }
                            }
                        })
                        .catch(function(){});
                });
            })();

            // Función global para mostrar el aviso de firma
            window.showSigToast = function() {
                try {
                    var toastEl = document.getElementById('sigWarningToast');
                    if (toastEl && typeof bootstrap !== 'undefined' && bootstrap.Toast) {
                        var toast = bootstrap.Toast.getOrCreateInstance(toastEl, { delay: 8000 });
                        toast.show();
                    }
                    // Intentar resaltar el banner amarillo si existe
                    var banner = document.querySelector('.alert-warning');
                    if (banner) {
                        banner.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        banner.classList.add('pulse-highlight');
                        setTimeout(function(){ banner.classList.remove('pulse-highlight'); }, 2000);
                    }
                } catch (e) {
                    console.log('Error showing signature toast:', e);
                }
            };

            // Dark Mode Toggle
            (function(){
                var form = document.getElementById('clientDarkModeForm');
                if (!form) return;
                var btn = document.getElementById('clientDarkModeBtn');
                var body = document.body;
                var input = form.querySelector('input[name="dark_mode"]');
                var icon = form.querySelector('.user-theme-toggle-icon');

                function setUi(isDark) {
                    if (isDark) body.classList.add('dark-mode');
                    else body.classList.remove('dark-mode');
                    if (icon) {
                        icon.classList.remove('bi-sun', 'bi-moon-stars');
                        icon.classList.add(isDark ? 'bi-sun' : 'bi-moon-stars');
                    }
                    if (input) input.value = isDark ? '0' : '1';
                }

                form.addEventListener('submit', function(e){
                    e.preventDefault();
                    var isDark = body.classList.contains('dark-mode');
                    var nextDark = !isDark;
                    setUi(nextDark);
                    try {
                        if (btn) btn.disabled = true;
                        var fd = new FormData(form);
                        fd.set('dark_mode', nextDark ? '1' : '0');
                        fetch(form.getAttribute('action') || 'toggle_user_dark.php', {
                            method: 'POST',
                            body: fd,
                            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                        }).then(function(r){ return r.json().catch(function(){ return null; }); })
                          .then(function(data){
                              if (data && typeof data.dark_mode !== 'undefined') {
                                  setUi(String(data.dark_mode) === '1' || data.dark_mode === 1);
                              }
                          })
                          .catch(function(){
                              setUi(isDark);
                          })
                          .finally(function(){
                              if (btn) btn.disabled = false;
                          });
                    } catch (err) {
                        setUi(isDark);
                        if (btn) btn.disabled = false;
                    }
                });
            })();

            poll(true);
            window.setInterval(function(){ poll(false); }, POLL_MS);
        })();
    </script>
</body>
</html>