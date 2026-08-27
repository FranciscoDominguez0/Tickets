<?php
if (!defined('APP_URL')) die('Direct access not permitted');

requireRolePermission('requisitions.view');

$eid = empresaId();
$sid = (int)$_SESSION['staff_id'];
$action = $_GET['a'] ?? 'list';
$canManage = roleHasPermission('requisitions.manage');
$isAdmin = roleHasPermission('admin.access');
$flashMsg = '';
$flashError = '';

if (!empty($_SESSION['flash_msg'])) {
    $flashMsg = (string)$_SESSION['flash_msg'];
    unset($_SESSION['flash_msg']);
}
if (!empty($_SESSION['flash_error'])) {
    $flashError = (string)$_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

if ($action === 'ajax_tickets') {
    $page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
    if ($page < 1) $page = 1;
    $limit = 5;
    $offset = ($page - 1) * $limit;
    
    $q = trim((string)($_GET['q'] ?? ''));
    
    $whereStr = "empresa_id = ? AND (closed IS NULL OR closed = 0)";
    $types = 'i';
    $params = [$eid];
    
    if ($q !== '') {
        $whereStr .= " AND (ticket_number LIKE ? OR subject LIKE ?)";
        $likeQ = '%' . $q . '%';
        $types .= 'ss';
        $params[] = $likeQ;
        $params[] = $likeQ;
    } else {
        $whereStr .= " AND MONTH(created) = MONTH(CURRENT_DATE()) AND YEAR(created) = YEAR(CURRENT_DATE())";
    }
    
    $sqlCount = "SELECT COUNT(*) as total FROM tickets WHERE $whereStr";
    $stmtC = $mysqli->prepare($sqlCount);
    if ($stmtC) {
        $stmtC->bind_param($types, ...$params);
        $stmtC->execute();
        $total = (int)$stmtC->get_result()->fetch_assoc()['total'];
    } else {
        $total = 0;
    }
    
    $totalPages = ceil($total / $limit);
    if ($totalPages < 1) $totalPages = 1;
    
    $sqlT = "SELECT id, ticket_number, subject, created FROM tickets WHERE $whereStr ORDER BY id DESC LIMIT ? OFFSET ?";
    $stmtT = $mysqli->prepare($sqlT);
    $types .= 'ii';
    $params[] = $limit;
    $params[] = $offset;
    $stmtT->bind_param($types, ...$params);
    $stmtT->execute();
    $resT = $stmtT->get_result();
    
    $tickets = [];
    while ($row = $resT->fetch_assoc()) {
        $tickets[] = [
            'id' => $row['id'],
            'number' => $row['ticket_number'],
            'subject' => $row['subject'],
            'date' => date('d/m/Y h:i A', strtotime($row['created']))
        ];
    }
    
    header('Content-Type: application/json');
    echo json_encode([
        'tickets' => $tickets,
        'currentPage' => $page,
        'totalPages' => $totalPages,
        'total' => $total
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF()) {
        $flashError = 'Token inválido.';
    } else {
        $do = $_POST['do'] ?? '';
        if ($do === 'create') {
            $ticketId = isset($_POST['ticket_id']) ? (int)$_POST['ticket_id'] : 0;
            $manualClientName = trim((string)($_POST['manual_client_name'] ?? ''));
            $products = $_POST['product_name'] ?? [];
            $quantities = $_POST['quantity'] ?? [];
            if (($ticketId <= 0 && $manualClientName === '') || empty($products)) {
                $flashError = 'Debe seleccionar un ticket o ingresar el nombre del cliente, y añadir al menos un producto.';
            } else {
                $mysqli->begin_transaction();
                try {
                    $clientName = $manualClientName;
                    $dbTicketId = null;
                    if ($ticketId > 0) {
                        $dbTicketId = $ticketId;
                        $clientName = 'Ticket #'.$ticketId;
                        $stmtTkt = $mysqli->prepare("SELECT u.firstname, u.lastname FROM tickets t LEFT JOIN users u ON t.user_id = u.id WHERE t.id = ? AND t.empresa_id = ?");
                        $stmtTkt->bind_param('ii', $ticketId, $eid);
                        $stmtTkt->execute();
                        $tktRes = $stmtTkt->get_result()->fetch_assoc();
                        if ($tktRes) {
                            $clientName = trim($tktRes['firstname'] . ' ' . $tktRes['lastname']);
                            if (empty($clientName)) $clientName = 'Cliente (Sin Nombre)';
                        }
                    }

                    $stmtIns = $mysqli->prepare("INSERT INTO requisitions (empresa_id, ticket_id, agent_id, client_name, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $stmtIns->bind_param('iiis', $eid, $dbTicketId, $sid, $clientName);
                    $stmtIns->execute();
                    $reqId = $mysqli->insert_id;
                    
                    $stmtItem = $mysqli->prepare("INSERT INTO requisition_items (requisition_id, product_name, quantity) VALUES (?, ?, ?)");
                    for ($i = 0; $i < count($products); $i++) {
                        $pName = trim($products[$i]);
                        $qty = (int)($quantities[$i] ?? 1);
                        if ($pName !== '' && $qty > 0) {
                            $stmtItem->bind_param('isi', $reqId, $pName, $qty);
                            $stmtItem->execute();
                        }
                    }
                    $mysqli->commit();
                    
                    $agentName = 'Un agente';
                    $stmtA = $mysqli->prepare("SELECT firstname, lastname FROM staff WHERE id = ?");
                    $stmtA->bind_param('i', $sid);
                    $stmtA->execute();
                    $resA = $stmtA->get_result()->fetch_assoc();
                    if ($resA) $agentName = trim($resA['firstname'] . ' ' . $resA['lastname']);
                    
                    $msg = $mysqli->real_escape_string("El agente {$agentName} ha enviado una nueva Solicitud de Inventario (ID #REQ-" . str_pad($reqId, 5, '0', STR_PAD_LEFT) . ").");
                    $resAdmins = $mysqli->query("SELECT staff_id FROM notification_recipients WHERE empresa_id = $eid");
                    if ($resAdmins) {
                        while ($adm = $resAdmins->fetch_assoc()) {
                            $admId = (int)$adm['staff_id'];
                            if ($admId !== $sid) {
                                $mysqli->query("INSERT INTO notifications (empresa_id, staff_id, message, type, related_id, is_read, created_at) VALUES ($eid, $admId, '$msg', 'requisition', $reqId, 0, NOW())");
                            }
                        }
                    }

                    $_SESSION['flash_msg'] = 'Requisición creada con éxito.';
                    header("Location: requisitions.php?a=view&id={$reqId}");
                    exit;
                } catch (Exception $e) {
                    $mysqli->rollback();
                    $flashError = 'Error al crear la requisición.';
                }
            }
        } elseif ($do === 'update_usage') {
            if (!$isAdmin) {
                $_SESSION['flash_error'] = 'Solo un administrador puede editar el uso real de materiales.';
                header("Location: requisitions.php?a=view&id=" . (int)$_POST['id']);
                exit;
            }
            $reqId = (int)$_POST['id'];
            $used_quantities = $_POST['quantity_used'] ?? [];
            
            $stmtUpd = $mysqli->prepare("UPDATE requisition_items SET quantity_used = ? WHERE id = ? AND requisition_id = ?");
            foreach ($used_quantities as $itemId => $qtyUsed) {
                $qUsed = ($qtyUsed === '') ? null : (int)$qtyUsed;
                $iId = (int)$itemId;
                $stmtUpd->bind_param('iii', $qUsed, $iId, $reqId);
                $stmtUpd->execute();
            }
            $_SESSION['flash_msg'] = 'Uso de materiales actualizado correctamente.';
            header("Location: requisitions.php?a=view&id={$reqId}");
            exit;
        } elseif ($do === 'mark_delivered' && $canManage) {
            $reqId = (int)$_POST['id'];
            
            $stmtAg = $mysqli->prepare("SELECT agent_id FROM requisitions WHERE id = ?");
            $stmtAg->bind_param('i', $reqId);
            $stmtAg->execute();
            $resAg = $stmtAg->get_result()->fetch_assoc();
            $agentIdToNotify = $resAg ? (int)$resAg['agent_id'] : 0;

            $stmtUpd = $mysqli->prepare("UPDATE requisitions SET status = 'delivered', admin_id_delivered = ?, delivered_at = NOW() WHERE id = ? AND empresa_id = ?");
            $stmtUpd->bind_param('iii', $sid, $reqId, $eid);
            $stmtUpd->execute();
            
            if ($agentIdToNotify > 0 && $agentIdToNotify !== $sid) {
                $msg = $mysqli->real_escape_string("Tus materiales de la Requisición #REQ-" . str_pad($reqId, 5, '0', STR_PAD_LEFT) . " han sido entregados. Por favor, ingresa para firmar de recibido.");
                $mysqli->query("INSERT INTO notifications (empresa_id, staff_id, message, type, related_id, is_read, created_at) VALUES ($eid, $agentIdToNotify, '$msg', 'requisition', $reqId, 0, NOW())");
            }

            $_SESSION['flash_msg'] = 'Requisición marcada como entregada. El agente ahora debe firmar para finalizar el proceso.';
            header("Location: requisitions.php?a=view&id={$reqId}");
            exit;
        } elseif ($do === 'sign') {
            $reqId = (int)$_POST['id'];
            $signature = $_POST['signature'] ?? '';
            if ($signature === '') {
                $flashError = 'Debe proporcionar su firma.';
            } else {
                $stmtUpd = $mysqli->prepare("UPDATE requisitions SET agent_signature = ?, signed_at = NOW() WHERE id = ? AND agent_id = ? AND empresa_id = ?");
                $stmtUpd->bind_param('siii', $signature, $reqId, $sid, $eid);
                $stmtUpd->execute();
                if ($stmtUpd->affected_rows > 0) {
                    $_SESSION['flash_msg'] = 'Firma guardada correctamente. Requisición completada.';
                    
                    $agentName = 'Un agente';
                    $stmtA = $mysqli->prepare("SELECT firstname, lastname FROM staff WHERE id = ?");
                    $stmtA->bind_param('i', $sid);
                    $stmtA->execute();
                    $resA = $stmtA->get_result()->fetch_assoc();
                    if ($resA) $agentName = trim($resA['firstname'] . ' ' . $resA['lastname']);
                    
                    $msg = $mysqli->real_escape_string("El agente {$agentName} ha firmado la recepción de la Requisición #REQ-" . str_pad($reqId, 5, '0', STR_PAD_LEFT) . ".");
                    $resAdmins = $mysqli->query("SELECT staff_id FROM notification_recipients WHERE empresa_id = $eid");
                    if ($resAdmins) {
                        while ($adm = $resAdmins->fetch_assoc()) {
                            $admId = (int)$adm['staff_id'];
                            if ($admId !== $sid) {
                                $mysqli->query("INSERT INTO notifications (empresa_id, staff_id, message, type, related_id, is_read, created_at) VALUES ($eid, $admId, '$msg', 'requisition', $reqId, 0, NOW())");
                            }
                        }
                    }
                    header("Location: requisitions.php?a=view&id={$reqId}");
                    exit;
                } else {
                    $_SESSION['flash_error'] = 'No se pudo guardar la firma (Asegúrese de ser el creador de la requisición).';
                    header("Location: requisitions.php?a=view&id={$reqId}");
                    exit;
                }
            }
        }
    }
}
?>

<style>
/* Design System Overrides for Requisitions */
.tickets-header {
    background: radial-gradient(circle at 0% 0%, #ef4444 0%, #1a0000 35%, #000000 100%);
    color: #fff;
    border-radius: 14px;
    padding: 24px 22px;
    margin-bottom: 20px;
    box-shadow: 0 8px 30px rgba(239, 68, 68, 0.2);
}
.tickets-header h1 {
    margin: 0;
    font-size: 1.4rem;
    font-weight: 800;
    letter-spacing: -0.01em;
}
.tickets-header .sub {
    margin-top: 4px;
    opacity: 0.92;
    font-size: 0.9rem;
    font-weight: 500;
}
.section-title {
    font-size: 0.82rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #475569;
    margin-bottom: 18px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.section-title i {
    color: #ef4444;
    font-size: 1rem;
}

/* Dark Mode Enhancements for Requisitions */
body.dark-mode {
    background-color: #000000 !important;
    color: #e5e5e5 !important;
}
body.dark-mode .main-shell,
body.dark-mode .container-main,
body.dark-mode #content {
    background-color: #000000 !important;
}
body.dark-mode .card,
body.dark-mode .list-group-item,
body.dark-mode .table-responsive,
body.dark-mode .table {
    background-color: #000000 !important;
    color: #e4e4e7 !important;
    border-color: #111111 !important;
}
body.dark-mode .card-header,
body.dark-mode .bg-light,
body.dark-mode .table-light,
body.dark-mode .list-group-item.bg-light {
    background-color: #000000 !important;
    color: #888888 !important;
    border-color: #111111 !important;
}
body.dark-mode .text-dark,
body.dark-mode .fw-bold.text-dark {
    color: #e4e4e7 !important;
}
body.dark-mode .text-muted {
    color: #888888 !important;
}
body.dark-mode input,
body.dark-mode select,
body.dark-mode textarea {
    background-color: #000000 !important;
    color: #e5e5e5 !important;
    border-color: #2e2e2e !important;
}
body.dark-mode input:focus,
body.dark-mode select:focus,
body.dark-mode textarea:focus {
    background: #1f1f1f !important;
    border-color: #ef4444 !important;
    box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.15) !important;
    color: #e5e5e5 !important;
}
body.dark-mode .input-group-text.bg-white {
    background-color: #000000 !important;
    border-color: #2e2e2e !important;
    color: #e5e5e5 !important;
}
body.dark-mode .ticket-row {
    background: #000000 !important;
    border-bottom: 1px solid #1f1f1f !important;
}
body.dark-mode .ticket-row:hover>td {
    background: #181818 !important;
}
body.dark-mode .ticket-row a.ticket-title {
    color: #ef4444 !important;
    text-decoration: none !important;
}
body.dark-mode .ticket-row a.ticket-title:hover {
    color: #ef4444 !important;
    text-decoration: none !important;
}
body.dark-mode .ticket-row span[style*="color: #334155"],
body.dark-mode span.text-dark,
body.dark-mode .fw-bold.fs-6.fs-md-5.text-dark {
    color: #e2e8f0 !important;
}
body.dark-mode div[style*="background: #f1f5f9"] {
    background: #181818 !important;
    color: #888888 !important;
}
body.dark-mode span[style*="background: #fffbeb"] {
    background: #78350f !important;
    color: #fef3c7 !important;
    border-color: #92400e !important;
}
body.dark-mode span[style*="background: #f0fdf4"] {
    background: #064e3b !important;
    color: #d1fae5 !important;
    border-color: #047857 !important;
}
body.dark-mode .btn-new[style*="background: #fff"],
body.dark-mode .btn-new[style*="background:#fff"],
body.dark-mode button[style*="background: #fff"],
body.dark-mode button[style*="background:#fff"],
body.dark-mode button[style*="background: #ffffff"],
body.dark-mode button[style*="background:#ffffff"] {
    background: #000000 !important;
    border-color: #2e2e2e !important;
    color: #e5e5e5 !important;
}
body.dark-mode button[onclick="addProductRow()"] {
    background-color: #000000 !important;
    color: #e4e4e7 !important;
    border-color: #2e2e2e !important;
}
.ticket-row a.ticket-title {
    color: #b91c1c !important;
    text-decoration: none !important;
}
.ticket-row a.ticket-title:hover {
    color: #ef4444 !important;
    text-decoration: none !important;
}
/* Modales en modo oscuro */
body.dark-mode .modal-content {
    background-color: #000000 !important;
    border-color: #111111 !important;
    color: #e4e4e7 !important;
}
body.dark-mode .modal-header,
body.dark-mode .modal-footer {
    border-color: #111111 !important;
}
body.dark-mode .badge.bg-light {
    background-color: #000000 !important;
    color: #888888 !important;
    border-color: #111111 !important;
}
body.dark-mode .badge.bg-secondary-subtle {
    background-color: #000000 !important;
    color: #888888 !important;
    border-color: #111111 !important;
}

/* Card list layout on mobile for requisitions */
@media (max-width: 768px) {
    .tickets-table-wrap {
        border: none !important;
        box-shadow: none !important;
        background: transparent !important;
        padding: 0 !important;
    }
    .tickets-table {
        background: transparent !important;
        border: none !important;
        display: block !important;
    }
    .tickets-table thead {
        display: none !important;
    }
    .tickets-table tbody {
        display: flex !important;
        flex-direction: column !important;
        gap: 12px !important;
        width: 100% !important;
    }
    .tickets-table tr.ticket-row {
        display: block !important;
        background: #ffffff !important;
        border: 1px solid #e2e8f0 !important;
        border-radius: 14px !important;
        padding: 16px !important;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02) !important;
        margin-bottom: 0 !important;
        transition: all 0.2s ease !important;
    }
    body.dark-mode .tickets-table tr.ticket-row {
        background: #000000 !important;
        border-color: #111111 !important;
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.4) !important;
    }
    .tickets-table tr.ticket-row:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.04) !important;
    }
    body.dark-mode .tickets-table tr.ticket-row:hover {
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.6) !important;
    }
    .tickets-table tr.ticket-row td {
        display: none !important;
    }
    .tickets-table tr.ticket-row td:first-child {
        display: block !important;
        width: 100% !important;
        padding: 0 !important;
        border: none !important;
        background: transparent !important;
    }
}
</style>


<?php if ($flashError): ?>
    <div class="alert alert-danger auto-dismiss-alert"><i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo html($flashError); ?></div>
<?php endif; ?>
<?php if ($flashMsg): ?>
    <div class="alert alert-success auto-dismiss-alert"><i class="bi bi-check-circle-fill me-2"></i><?php echo html($flashMsg); ?></div>
<?php endif; ?>

<script>
    setTimeout(function() {
        const alerts = document.querySelectorAll('.auto-dismiss-alert');
        alerts.forEach(alert => {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        });
    }, 4000);
</script>

<?php if ($action === 'list'): ?>
    <?php
        $search = trim($_GET['q'] ?? '');
        $page = max(1, (int)($_GET['p'] ?? 1));
        $perPage = 15;

        $where = ["empresa_id = ?"];
        $params = [$eid];
        $types = "i";

        if (!$canManage) {
            $where[] = "agent_id = ?";
            $params[] = $sid;
            $types .= "i";
        }
        
        if ($search !== '') {
            $where[] = "(client_name LIKE ? OR id = ?)";
            $likeSearch = "%{$search}%";
            $idSearch = (int)preg_replace('/[^0-9]/', '', $search);
            $params[] = $likeSearch;
            $params[] = $idSearch;
            $types .= "si";
        }
        
        $whereStr = implode(" AND ", $where);
        
        $sqlCount = "SELECT COUNT(*) as total FROM requisitions WHERE $whereStr";
        $stmtC = $mysqli->prepare($sqlCount);
        $stmtC->bind_param($types, ...$params);
        $stmtC->execute();
        $total = (int)$stmtC->get_result()->fetch_assoc()['total'];
        
        $totalPages = ceil($total / $perPage);
        if ($totalPages < 1) $totalPages = 1;
        if ($page > $totalPages) $page = $totalPages;
        $offset = ($page - 1) * $perPage;
    ?>

    <div class="tickets-header mb-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
            <div>
                <h1 class="mb-1">Requisiciones e Inventario</h1>
                <div class="sub opacity-75">Control y registro de salidas de productos y materiales</div>
            </div>
            <a href="requisitions.php?a=new" class="btn-new" style="background: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.30); color: #fff; padding: 8px 16px; border-radius: 10px; text-decoration: none; font-weight: 700; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 6px;">
                <i class="bi bi-plus-lg"></i> Nueva Salida
            </a>
        </div>
    </div>

    <div class="tickets-panel">
        <div class="tickets-toolbar">
            <div class="tickets-filters">
                <!-- Espacio para futuros filtros (como fecha, estado, etc.) -->
                <?php if ($search !== ''): ?>
                    <a href="requisitions.php" class="btn btn-sm btn-outline-secondary fw-bold px-3">Limpiar Búsqueda</a>
                <?php endif; ?>
            </div>
            <div class="tickets-search">
                <form method="get" action="requisitions.php" class="m-0">
                    <div class="input-group">
                        <span class="input-group-text bg-white" style="border-right: none; border-radius: 10px 0 0 10px;">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" name="q" class="form-control" placeholder="Buscar por cliente o ID (#REQ-...) y presione Enter" value="<?php echo html($search); ?>" style="border-left: none; border-radius: 0 10px 10px 0;">
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="tickets-table-wrap">
        <table class="table table-hover tickets-table mb-0">
            <thead class="table-light" style="border-bottom: 2px solid #e2e8f0; background-color: #f8fafc;">
                <tr>
                    <th style="font-weight: 700; color: #475569; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; padding-left: 20px;">Requisición</th>
                    <th class="d-none d-lg-table-cell" style="font-weight: 700; color: #475569; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em;">Cliente</th>
                    <th class="d-none d-md-table-cell" style="font-weight: 700; color: #475569; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em;">Estado</th>
                    <th class="d-none d-lg-table-cell" style="font-weight: 700; color: #475569; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em;">Fecha Solicitud</th>
                    <th style="width: 80px; text-align: right; font-weight: 700; color: #475569; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em;"></th>
                </tr>
            </thead>
            <tbody>
                <?php
                $sql = "SELECT id, client_name, status, created_at FROM requisitions WHERE $whereStr ORDER BY id DESC LIMIT ? OFFSET ?";
                $stmt = $mysqli->prepare($sql);
                $params[] = $perPage;
                $params[] = $offset;
                $types .= "ii";
                $stmt->bind_param($types, ...$params);
                
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res->num_rows === 0) {
                    echo '<tr><td colspan="5"><div class="empty-state" style="padding: 40px; text-align: center;"><i class="bi bi-box-seam" style="font-size: 2.5rem; opacity: 0.4; color: #dc3545;"></i><div style="margin-top: 15px; color: #475569; font-weight: 500; font-size: 1.1rem;">No hay salidas de inventario registradas.</div></div></td></tr>';
                }
                while ($row = $res->fetch_assoc()):
                ?>
    <tr class="ticket-row" style="background: #fff; cursor: pointer; transition: background 0.2s;" onclick="if(!event.target.closest('a') && !event.target.closest('button')) window.location='requisitions.php?a=view&id=<?php echo $row['id']; ?>';">
    <td style="vertical-align: middle; padding: 18px 12px 18px 20px;">
        <!-- Cabecera en Móvil -->
        <div class="d-flex d-md-none justify-content-between align-items-center mb-2">
            <a class="ticket-title" href="requisitions.php?a=view&id=<?php echo $row['id']; ?>" style="font-weight: 800; font-size: 1.05rem; color: #b91c1c; text-decoration: none;">
                <i class="bi bi-hash" style="opacity: 0.5;"></i>REQ-<?php echo str_pad($row['id'], 5, '0', STR_PAD_LEFT); ?>
            </a>
            <div>
                <?php if ($row['status'] === 'pending'): ?>
                    <span style="background: #fffbeb; color: #b45309; border: 1px solid #fde68a; padding: 3px 8px; font-weight: 700; border-radius: 5px; font-size: 0.7rem; text-transform: uppercase;">PENDIENTE</span>
                <?php else: ?>
                    <span style="background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; padding: 3px 8px; font-weight: 700; border-radius: 5px; font-size: 0.7rem; text-transform: uppercase;">ENTREGADO</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Cabecera en Escritorio -->
        <div class="d-none d-md-flex align-items-center gap-2 mb-1">
            <a class="ticket-title" href="requisitions.php?a=view&id=<?php echo $row['id']; ?>" style="font-weight: 800; font-size: 1.05rem; color: #b91c1c; text-decoration: none;">
                <i class="bi bi-hash" style="opacity: 0.5;"></i>REQ-<?php echo str_pad($row['id'], 5, '0', STR_PAD_LEFT); ?>
            </a>
            <span title="Tipo de Solicitud" style="display:inline-flex; align-items:center; gap:4px; background:#f1f5f9; color:#64748b; border:1px solid #e2e8f0; border-radius:5px; padding:1px 7px; font-size:0.68rem; font-weight:800; letter-spacing:0.04em; line-height:1.6; text-transform:uppercase; white-space:nowrap;">
                <span style="width:5px; height:5px; border-radius:50%; background:#64748b; flex-shrink:0; display:inline-block;"></span>
                INVENTARIO
            </span>
        </div>

        <div class="ticket-subject" style="font-weight: 600; color: #1e293b; font-size: 0.95rem; margin-bottom: 4px; line-height: 1.4; display: block; max-width: 55ch; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; text-transform: none;">
            Solicitud de Materiales
        </div>

        <!-- Detalles Móviles -->
        <div class="d-md-none text-muted mt-2 d-flex flex-column gap-1" style="font-size: 0.8rem;">
            <div><i class="bi bi-person me-1"></i> Solicitante: <strong class="text-dark"><?php echo html($row['client_name']); ?></strong></div>
            <div><i class="bi bi-calendar-event me-1"></i> Fecha: <?php echo date('d M, Y h:i A', strtotime($row['created_at'])); ?></div>
        </div>
    </td>

    <td class="d-none d-lg-table-cell" style="vertical-align: middle;">
        <div style="display:flex; align-items:center; gap:10px;">
            <div style="width: 32px; height: 32px; border-radius: 50%; background: #f1f5f9; color: #64748b; display: flex; align-items: center; justify-content: center; font-size: 1rem; flex-shrink: 0;">
                <i class="bi bi-person-fill"></i>
            </div>
            <div style="display:flex; flex-direction:column;">
                <span style="font-weight: 700; color: #334155; font-size: 0.9rem;"><?php echo html($row['client_name']); ?></span>
            </div>
        </div>
    </td>

    <td class="d-none d-md-table-cell" style="vertical-align: middle;">
        <div style="display:flex; flex-direction:column; gap:6px; align-items: flex-start;">
            <?php if ($row['status'] === 'pending'): ?>
                <span style="background: #fffbeb; color: #b45309; border: 1px solid #fde68a; padding: 5px 10px; font-weight: 700; letter-spacing: 0.03em; border-radius: 6px; font-size: 0.75rem; text-transform: uppercase;">
                    <i class="bi bi-record-circle-fill" style="font-size: 0.6rem; margin-right: 4px; vertical-align: middle;"></i> PENDIENTE
                </span>
            <?php else: ?>
                <span style="background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; padding: 5px 10px; font-weight: 700; letter-spacing: 0.03em; border-radius: 6px; font-size: 0.75rem; text-transform: uppercase;">
                    <i class="bi bi-record-circle-fill" style="font-size: 0.6rem; margin-right: 4px; vertical-align: middle;"></i> ENTREGADO
                </span>
            <?php endif; ?>
        </div>
    </td>

    <td class="d-none d-lg-table-cell" style="vertical-align: middle;">
        <div style="display:flex; flex-direction:column; gap:3px;">
            <span style="color:#64748b; font-size:0.85rem; display:flex; align-items:center; gap:5px;">
                <i class="bi bi-clock" style="opacity:0.6;"></i> <?php echo date('d/m/Y h:i A', strtotime($row['created_at'])); ?>
            </span>
        </div>
    </td>

    <td class="d-none d-md-table-cell" style="vertical-align: middle; text-align: right; padding-right: 20px;">
        <i class="bi bi-chevron-right" style="color: #cbd5e1; font-size: 1.2rem; font-weight: bold;"></i>
    </td>
</tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="tickets-pagination-wrap bg-white border-top py-3 px-4">
        <?php echo renderModernPagination($page, $totalPages, '&q=' . urlencode($search), 'p'); ?>
    </div>
    <?php endif; ?>
    </div>
<?php elseif ($action === 'new'): ?>
    <div class="tickets-header mb-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2">
            <div>
                <h1>Nueva Solicitud</h1>
                <div class="sub">Cree una nueva requisición para el cliente.</div>
            </div>
            <a href="requisitions.php" class="btn-new" style="background: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.30); color: #fff; padding: 8px 16px; border-radius: 10px; text-decoration: none; font-weight: 700; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 6px;">
                <i class="bi bi-arrow-left"></i> Volver al listado
            </a>
        </div>
    </div>
    
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <form method="post" action="requisitions.php" class="m-0">
            <?php csrfField(); ?>
            <input type="hidden" name="do" value="create">
            
            <div class="row g-0">
                <!-- Left Column -->
                <div class="col-md-5 col-lg-4 p-4 border-end-md bg-light">
                    <div class="section-title">
                        <i class="bi bi-ticket-detailed fs-4 me-2"></i> Información del Ticket
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold text-muted small text-uppercase mb-2">Ticket Relacionado o Cliente Manual</label>
                        
                        <?php $preSelectTicketId = isset($_GET['ticket_id']) ? (int)$_GET['ticket_id'] : 0; ?>
                        <!-- Hidden input to store selected ticket ID -->
                        <input type="hidden" name="ticket_id" id="ticket_id_input" value="<?php echo html($preSelectTicketId); ?>">
                        
                        <!-- Selected Ticket Display -->
                        <div id="selected_ticket_container" class="mb-3" style="display: <?php echo $preSelectTicketId ? 'block' : 'none'; ?>;">
                            <div class="d-flex align-items-center justify-content-between p-3 border rounded-3 bg-white shadow-sm border-secondary-subtle">
                                <div>
                                    <div class="fw-bold text-dark fs-5 mb-1" id="selected_ticket_display">
                                        <?php if ($preSelectTicketId): ?>Ticket #<?php echo html($preSelectTicketId); ?><?php endif; ?>
                                    </div>
                                    <div class="small text-muted">Ticket asociado a la requisición</div>
                                </div>
                                <button type="button" class="btn btn-outline-danger btn-sm rounded-pill fw-bold" onclick="clearSelectedTicket()">
                                    <i class="bi bi-x-circle me-1"></i> Quitar
                                </button>
                            </div>
                        </div>

                        <!-- Manual Client Input Container -->
                        <div id="manual_client_container" class="mb-3" style="display: <?php echo $preSelectTicketId ? 'none' : 'block'; ?>;">
                            <input type="text" name="manual_client_name" id="manual_client_name" class="form-control form-control-lg border-secondary-subtle w-100 shadow-sm" placeholder="Escribir nombre del cliente">
                        </div>
                        
                        <!-- Button to Open Modal -->
                        <button type="button" class="btn btn-outline-primary w-100 py-3 fw-bold border-secondary-subtle bg-light shadow-sm" data-bs-toggle="modal" data-bs-target="#ticketSelectionModal" style="border-style: dashed !important; display: <?php echo $preSelectTicketId ? 'none' : 'block'; ?>;" id="btn_open_modal">
                            <i class="bi bi-ticket-detailed me-2"></i> Buscar un Ticket Abierto
                        </button>
                    </div>
                </div>
                
                <!-- Right Column -->
                <div class="col-md-7 col-lg-8 p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div class="section-title">
                            <i class="bi bi-box-seam fs-4 me-2"></i> Productos
                        </div>
                    </div>
                    
                    <div id="products-container">
                        <div class="d-flex align-items-center gap-2 mb-3 product-row p-2 bg-light rounded border border-light-subtle">
                            <div class="flex-grow-1">
                                <input type="text" name="product_name[]" class="form-control border-0 bg-transparent" required placeholder="Nombre del producto">
                            </div>
                            <div style="width: 100px;">
                                <input type="number" name="quantity[]" class="form-control border-0 text-center" required min="1" value="1" placeholder="Cant.">
                            </div>
                            <button type="button" class="btn btn-light text-danger btn-sm border-0" onclick="removeRow(this)" disabled><i class="bi bi-trash fs-5"></i></button>
                        </div>
                    </div>
                    
                    <button type="button" class="btn btn-outline-secondary w-100 py-3 mt-3 fw-bold bg-light border border-secondary-subtle" onclick="addProductRow()" style="border-style: dashed !important;">
                        <i class="bi bi-plus-circle me-2"></i> Añadir otro producto
                    </button>
                </div>
            </div>
            
            <div class="card-footer bg-transparent p-4 border-top text-end">
                <a href="requisitions.php" class="btn btn-light fw-bold px-4 me-2 border">Cancelar</a>
                <button type="submit" class="btn btn-danger fw-bold px-5">Crear Solicitud <i class="bi bi-send ms-2"></i></button>
            </div>
        </form>
    </div>

    <script>
        function addProductRow() {
            const container = document.getElementById('products-container');
            const row = document.createElement('div');
            row.className = 'd-flex align-items-center gap-2 mb-3 product-row p-2 bg-light rounded border border-light-subtle';
            row.innerHTML = `
                <div class="flex-grow-1">
                    <input type="text" name="product_name[]" class="form-control border-0 bg-transparent" required placeholder="Nombre del producto">
                </div>
                <div style="width: 100px;">
                    <input type="number" name="quantity[]" class="form-control border-0 text-center" required min="1" value="1" placeholder="Cant.">
                </div>
                <button type="button" class="btn btn-light text-danger btn-sm border-0" onclick="removeRow(this)"><i class="bi bi-trash fs-5"></i></button>
            `;
            container.appendChild(row);
            updateRemoveButtons();
        }
        function removeRow(btn) {
            btn.closest('.product-row').remove();
            updateRemoveButtons();
        }
        function updateRemoveButtons() {
            const rows = document.querySelectorAll('.product-row');
            const btns = document.querySelectorAll('.product-row .btn-light.text-danger');
            if (rows.length === 1) {
                btns[0].disabled = true;
            } else {
                btns.forEach(b => b.disabled = false);
            }
        }
    </script>

    <!-- Ticket Selection Modal -->
    <div class="modal fade" id="ticketSelectionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-bottom-0 bg-light p-4">
                    <h5 class="modal-title fw-bold"><i class="bi bi-ticket-detailed text-primary me-2"></i>Seleccionar Ticket Abierto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="p-3 border-bottom bg-white sticky-top">
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control border-start-0 bg-light shadow-none" id="tickets-search-input" placeholder="Buscar ticket...">
                            <button class="btn btn-primary px-4 fw-bold" type="button" onclick="loadTickets(1)">Buscar</button>
                        </div>
                    </div>
                    <div id="tickets-loading" class="text-center py-5 text-muted">
                        <div class="spinner-border text-primary" role="status"></div>
                        <div class="mt-2 fw-medium">Cargando tickets...</div>
                    </div>
                    <div id="tickets-list" class="list-group list-group-flush" style="display:none;">
                        <!-- Tickets rendered here -->
                    </div>
                </div>
                <div class="modal-footer border-top-0 bg-light p-3 d-flex justify-content-between align-items-center w-100">
                    <div class="small text-muted fw-bold w-25" id="tickets-total">0 tickets encontrados</div>
                    <nav aria-label="Paginación de tickets" class="w-50 d-flex justify-content-center">
                        <ul class="pagination pagination-sm mb-0" id="tickets-pagination">
                            <!-- Pagination here -->
                        </ul>
                    </nav>
                    <div class="w-25"></div> <!-- Spacer for centering -->
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const modalEl = document.getElementById('ticketSelectionModal');
            let currentPage = 1;

            if (modalEl) {
                modalEl.addEventListener('show.bs.modal', function () {
                    loadTickets(1);
                });
            }
            
            window.clearSelectedTicket = function() {
                document.getElementById('ticket_id_input').value = '';
                document.getElementById('selected_ticket_container').style.display = 'none';
                document.getElementById('manual_client_container').style.display = 'block';
                document.getElementById('btn_open_modal').style.display = 'block';
                document.getElementById('manual_client_name').required = true;
            };

            window.selectTicket = function(id, number, subject) {
                document.getElementById('ticket_id_input').value = id;
                document.getElementById('selected_ticket_display').innerHTML = `Ticket #${id} - ${subject}`;
                
                document.getElementById('selected_ticket_container').style.display = 'block';
                document.getElementById('manual_client_container').style.display = 'none';
                document.getElementById('btn_open_modal').style.display = 'none';
                
                const manualInput = document.getElementById('manual_client_name');
                manualInput.required = false;
                manualInput.value = '';
                
                const bsModal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
                bsModal.hide();
            };

            const searchInput = document.getElementById('tickets-search-input');
            if (searchInput) {
                searchInput.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        loadTickets(1);
                    }
                });
            }

            window.loadTickets = function(page) {
                currentPage = page;
                const searchQ = searchInput ? searchInput.value.trim() : '';
                
                document.getElementById('tickets-loading').style.display = 'block';
                document.getElementById('tickets-list').style.display = 'none';
                
                fetch('requisitions.php?a=ajax_tickets&p=' + page + '&q=' + encodeURIComponent(searchQ))
                    .then(response => response.json())
                    .then(data => {
                        renderTickets(data.tickets);
                        renderPagination(data.currentPage, data.totalPages);
                        document.getElementById('tickets-total').innerText = data.total + ' tickets encontrados';
                        document.getElementById('tickets-loading').style.display = 'none';
                        document.getElementById('tickets-list').style.display = 'block';
                    })
                    .catch(err => {
                        console.error('Error cargando tickets:', err);
                        document.getElementById('tickets-loading').innerHTML = '<div class="text-danger py-5"><i class="bi bi-exclamation-triangle me-2"></i>Error al cargar.</div>';
                    });
            };

            function renderTickets(tickets) {
                const list = document.getElementById('tickets-list');
                list.innerHTML = '';
                if (tickets.length === 0) {
                    list.innerHTML = '<div class="p-4 text-center text-muted fw-medium">No hay tickets abiertos disponibles.</div>';
                    return;
                }
                
                tickets.forEach(t => {
                    const subjectEscaped = t.subject.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
                    const html = `
                        <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center p-3 gap-3">
                            <div>
                                <div class="fw-bold text-dark mb-1">#${t.number} - ${t.subject}</div>
                                <div class="small text-muted"><i class="bi bi-calendar-event me-1"></i>${t.date}</div>
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3 fw-bold flex-shrink-0" onclick="selectTicket(${t.id}, '${t.number}', '${subjectEscaped}')">
                                Elegir
                            </button>
                        </div>
                    `;
                    list.insertAdjacentHTML('beforeend', html);
                });
            }

            function renderPagination(current, total) {
                const ul = document.getElementById('tickets-pagination');
                ul.innerHTML = '';
                if (total <= 1) return;
                
                ul.className = 'pagination pagination-sm justify-content-center mb-0 gap-1 modern-pagination';
                
                let html = `<li class="page-item ${current <= 1 ? 'disabled' : ''}">
                    <button type="button" class="page-link border-0 rounded-3 shadow-sm" ${current > 1 ? `onclick="loadTickets(${current - 1})"` : 'disabled'} title="Anterior"><i class="bi bi-chevron-left"></i></button>
                </li>`;
                
                const range = 2;
                const start = Math.max(1, current - range);
                const end = Math.min(total, current + range);

                if (start > 1) {
                    html += `<li class="page-item"><button type="button" class="page-link border-0 rounded-3 fw-bold shadow-sm" onclick="loadTickets(1)">1</button></li>`;
                    if (start > 2) {
                        html += `<li class="page-item disabled"><span class="page-link border-0 bg-transparent text-muted" style="pointer-events: none;">&hellip;</span></li>`;
                    }
                }

                for (let i = start; i <= end; i++) {
                    if (current === i) {
                        html += `<li class="page-item active"><span class="page-link border-0 rounded-3 fw-bold shadow-sm">${i}</span></li>`;
                    } else {
                        html += `<li class="page-item"><button type="button" class="page-link border-0 rounded-3 fw-bold shadow-sm" onclick="loadTickets(${i})">${i}</button></li>`;
                    }
                }

                if (end < total) {
                    if (end < total - 1) {
                        html += `<li class="page-item disabled"><span class="page-link border-0 bg-transparent text-muted" style="pointer-events: none;">&hellip;</span></li>`;
                    }
                    html += `<li class="page-item"><button type="button" class="page-link border-0 rounded-3 fw-bold shadow-sm" onclick="loadTickets(${total})">${total}</button></li>`;
                }
                
                html += `<li class="page-item ${current >= total ? 'disabled' : ''}">
                    <button type="button" class="page-link border-0 rounded-3 shadow-sm" ${current < total ? `onclick="loadTickets(${current + 1})"` : 'disabled'} title="Siguiente"><i class="bi bi-chevron-right"></i></button>
                </li>`;
                
                ul.innerHTML = html;
            }
            
            // Initialization state for preselected ticket
            const preSelectTicketId = parseInt(document.getElementById('ticket_id_input').value) || 0;
            if (preSelectTicketId <= 0) {
                document.getElementById('manual_client_name').required = true;
            }
        });
    </script>


<?php elseif ($action === 'view'): 
    $reqId = (int)($_GET['id'] ?? 0);
    $stmt = $mysqli->prepare("SELECT * FROM requisitions WHERE id = ? AND empresa_id = ?");
    $stmt->bind_param('ii', $reqId, $eid);
    $stmt->execute();
    $req = $stmt->get_result()->fetch_assoc();
    
    if (!$req) {
        echo '<div class="alert alert-danger">Requisición no encontrada.</div>';
        return;
    }
    
    // Si el usuario no es el autor ni un manager, no puede verla
    if ($req['agent_id'] != $sid && !$canManage) {
        echo '<div class="alert alert-danger">No tienes permisos para ver esta requisición.</div>';
        return;
    }

        $stmtItems = $mysqli->prepare("SELECT * FROM requisition_items WHERE requisition_id = ?");
    $stmtItems->bind_param('i', $reqId);
    $stmtItems->execute();
    $items = $stmtItems->get_result();
?>
    <!-- Context Header -->
    <div class="tickets-header mb-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2">
            <div>
                <h1>Detalles de Requisición</h1>
                <div class="sub">REQ-<?php echo str_pad($req['id'], 5, '0', STR_PAD_LEFT); ?></div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="requisitions.php" class="btn-new" style="background: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.30); color: #fff; padding: 8px 16px; border-radius: 10px; text-decoration: none; font-weight: 700; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 6px;">
                    <i class="bi bi-arrow-left"></i> Volver al listado
                </a>
                <a href="print_requisition.php?id=<?php echo $req['id']; ?>" target="_blank" class="btn-new" style="background: #fff; border: 1px solid #e2e8f0; color: #475569; padding: 8px 16px; border-radius: 10px; text-decoration: none; font-weight: 700; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 6px;">
                    <i class="bi bi-printer"></i> Imprimir
                </a>
                <?php if ($req['status'] === 'delivered' && empty($req['agent_signature']) && $req['agent_id'] == $sid): ?>
                    <button type="button" class="btn-new" data-bs-toggle="modal" data-bs-target="#signatureModal" style="background: #fff; border: none; color: #dc2626; padding: 8px 16px; border-radius: 10px; font-weight: 700; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 6px;">
                        <i class="bi bi-pen"></i> Firmar Recepción
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="row g-4 pb-5">
        <!-- Left Column -->
        <div class="col-lg-8">
            <!-- Customer Summary Card -->
            <div class="card border-0 shadow-sm rounded-4 mb-4 position-relative overflow-hidden">
                <div class="position-absolute top-0 start-0 w-100 bg-danger" style="height: 4px;"></div>
                <div class="card-body p-3 p-md-5">
                    <div class="d-flex justify-content-between align-items-start mb-4">
                        <div class="section-title mb-0">
                            <i class="bi bi-person-fill fs-4 me-2"></i> Información del Solicitante
                        </div>
                        <div>
                            <?php if ($req['status'] === 'pending'): ?>
                                <span class="badge bg-warning text-dark px-3 py-2 shadow-sm rounded-pill"><i class="bi bi-clock me-1"></i>Pendiente</span>
                            <?php else: ?>
                                <span class="badge bg-success px-3 py-2 shadow-sm rounded-pill"><i class="bi bi-check-all me-1"></i>Entregado</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="row g-3 g-md-4">
                        <div class="col-12 col-md-4">
                            <span class="d-block text-muted small fw-bold text-uppercase mb-1"><i class="bi bi-person me-1"></i>Nombre</span>
                            <span class="fw-bold fs-6 fs-md-5 text-dark"><?php echo html($req['client_name']); ?></span>
                        </div>
                        <div class="col-6 col-md-4">
                            <span class="d-block text-muted small fw-bold text-uppercase mb-1"><i class="bi bi-calendar-date me-1"></i>Fecha</span>
                            <span class="fw-bold fs-6 fs-md-5 text-dark"><?php echo date('d/m/Y', strtotime($req['created_at'])); ?></span>
                            <span class="d-block text-muted mt-1 small"><?php echo date('h:i A', strtotime($req['created_at'])); ?></span>
                        </div>
                        <div class="col-6 col-md-4">
                            <span class="d-block text-muted small fw-bold text-uppercase mb-1"><i class="bi bi-hash me-1"></i>Requisición</span>
                            <span class="fw-bold fs-6 fs-md-5 text-dark font-monospace">REQ-<?php echo str_pad($req['id'], 5, '0', STR_PAD_LEFT); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Products Table Card -->
            <form method="post" action="requisitions.php" class="m-0">
                <?php csrfField(); ?>
                <input type="hidden" name="do" value="update_usage">
                <input type="hidden" name="id" value="<?php echo $req['id']; ?>">
                
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-header bg-light border-bottom-0 p-4 d-flex justify-content-between align-items-center">
                        <div class="section-title">
                            <i class="bi bi-box-seam-fill fs-4 me-2"></i> Productos Solicitados y Uso
                        </div>
                        <span class="badge bg-secondary rounded-pill px-3 py-2"><?php echo $items->num_rows; ?> Artículos</span>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item bg-light text-muted small text-uppercase fw-bold d-none d-md-flex px-4 py-3">
                                <div class="flex-grow-1">Producto</div>
                                <div style="width: 120px;" class="text-center">Solicitado</div>
                                <div style="width: 120px;" class="text-center">Utilizado</div>
                            </li>
                            <?php while ($item = $items->fetch_assoc()): ?>
                            <li class="list-group-item px-3 px-md-4 py-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                                <div class="fw-bold text-dark text-break flex-grow-1">
                                    <i class="bi bi-box me-2 text-secondary opacity-50 d-none d-md-inline"></i>
                                    <?php echo html($item['product_name']); ?>
                                </div>
                                <div class="d-flex align-items-center justify-content-between justify-content-md-end gap-3 flex-shrink-0">
                                    <div class="text-center" style="width: 120px;">
                                        <span class="badge bg-secondary-subtle text-secondary rounded-pill px-3 py-2 fs-6 border">
                                            <?php echo html($item['quantity']); ?>
                                        </span>
                                    </div>
                                    <div style="width: 120px;">
                                        <?php if ($req['status'] === 'delivered' && $isAdmin): ?>
                                            <input type="number" name="quantity_used[<?php echo $item['id']; ?>]" class="form-control text-center form-control-sm" placeholder="Uso Real" value="<?php echo html($item['quantity_used'] ?? ''); ?>" min="0">
                                        <?php else: ?>
                                            <span class="badge <?php echo ($item['quantity_used'] !== null) ? 'bg-info-subtle text-info border-info' : 'bg-light text-muted border'; ?> rounded-pill px-3 py-2 fs-6 border d-block text-center">
                                                <?php echo ($item['quantity_used'] !== null) ? html($item['quantity_used']) : '-'; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </li>
                            <?php endwhile; ?>
                        </ul>
                    </div>
                    <?php if ($req['status'] === 'delivered' && $isAdmin): ?>
                    <div class="card-footer bg-transparent border-top p-3 text-end">
                        <button type="submit" class="btn btn-primary fw-bold"><i class="bi bi-save me-2"></i>Guardar Uso Real</button>
                    </div>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Right Column -->
        <div class="col-lg-4">
            <div>
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-body p-4">
                        <div class="section-title">
                            <i class="bi bi-bezier2 fs-4 me-2"></i> Estado del Trámite
                        </div>
                        
                        <div class="position-relative ms-2 mt-2">
                            <!-- Timeline Line -->
                            <div class="position-absolute bg-secondary opacity-25" style="width: 2px; top: 10px; bottom: 30px; left: 7px;"></div>
                            
                            <!-- Step 1 -->
                            <div class="d-flex mb-4 position-relative z-1">
                                <div class="bg-danger rounded-circle border border-white border-3 shadow-sm me-3" style="width: 16px; height: 16px; margin-top: 4px;"></div>
                                <div>
                                    <h6 class="fw-bold mb-0 text-dark">Solicitud Creada</h6>
                                    <p class="small text-muted mb-0"><?php echo date('d M Y, h:i A', strtotime($req['created_at'])); ?></p>
                                </div>
                            </div>
                            
                            <!-- Step 2 -->
                            <div class="d-flex mb-4 position-relative z-1">
                                <?php if ($req['status'] === 'delivered'): ?>
                                    <div class="bg-danger rounded-circle border border-white border-3 shadow-sm me-3" style="width: 16px; height: 16px; margin-top: 4px;"></div>
                                    <div class="w-100">
                                        <h6 class="fw-bold mb-0 text-dark">Entregada</h6>
                                        <p class="small text-muted mb-0"><?php echo date('d M Y, h:i A', strtotime($req['delivered_at'])); ?></p>
                                        <?php
                                            $adminName = 'Admin';
                                            if ($req['admin_id_delivered']) {
                                                $stmtA = $mysqli->prepare("SELECT firstname, lastname FROM staff WHERE id = ?");
                                                $stmtA->bind_param('i', $req['admin_id_delivered']);
                                                $stmtA->execute();
                                                $resA = $stmtA->get_result()->fetch_assoc();
                                                if ($resA) $adminName = trim($resA['firstname'] . ' ' . $resA['lastname']);
                                            }
                                        ?>
                                        <p class="small text-muted mt-1"><i class="bi bi-person-fill"></i> Por: <?php echo html($adminName); ?></p>
                                    </div>
                                <?php else: ?>
                                    <div class="bg-white rounded-circle border border-danger border-2 shadow-sm d-flex align-items-center justify-content-center me-3" style="width: 16px; height: 16px; margin-top: 4px;">
                                        <div class="bg-danger rounded-circle" style="width: 8px; height: 8px;"></div>
                                    </div>
                                    <div class="w-100">
                                        <h6 class="fw-bold text-danger mb-1">Pendiente de Entrega</h6>
                                        <p class="small text-muted mb-2">Esperando confirmación física.</p>
                                        <?php if ($canManage): ?>
                                            <button type="button" class="btn btn-outline-secondary w-100 mt-2 py-2 fw-bold" data-bs-toggle="modal" data-bs-target="#confirmDeliveryModal">
                                                <i class="bi bi-box-seam me-1"></i> Marcar como Entregado
                                            </button>
                                            
                                            <!-- Modal de Confirmación -->
                                            <div class="modal fade" id="confirmDeliveryModal" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered">
                                                    <div class="modal-content border-0 shadow">
                                                        <div class="modal-header border-bottom-0 bg-light">
                                                            <h5 class="modal-title fw-bold"><i class="bi bi-exclamation-circle text-warning me-2"></i>Confirmar Entrega</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body text-center py-4">
                                                            <p class="fs-5 mb-0">¿Estás seguro que deseas marcar estos productos como entregados al agente?</p>
                                                            <p class="text-muted mt-2 small">Esta acción habilitará la firma electrónica para el agente.</p>
                                                        </div>
                                                        <div class="modal-footer border-top-0 d-flex bg-light">
                                                            <form method="post" action="requisitions.php" class="w-100 d-flex gap-2 m-0">
                                                                <?php csrfField(); ?>
                                                                <input type="hidden" name="do" value="mark_delivered">
                                                                <input type="hidden" name="id" value="<?php echo $req['id']; ?>">
                                                                <button type="button" class="btn btn-outline-secondary fw-bold flex-fill" data-bs-dismiss="modal">Cancelar</button>
                                                                <button type="submit" class="btn btn-success fw-bold flex-fill">Sí, Confirmar Entrega</button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Step 3 (Firma) -->
                            <?php if ($req['status'] === 'delivered'): ?>
                                <div class="d-flex position-relative z-1">
                                    <?php if (empty($req['agent_signature'])): ?>
                                        <div class="bg-white rounded-circle border border-danger border-2 shadow-sm d-flex align-items-center justify-content-center me-3" style="width: 16px; height: 16px; margin-top: 4px;">
                                            <div class="bg-danger rounded-circle" style="width: 8px; height: 8px;"></div>
                                        </div>
                                        <div class="w-100">
                                            <h6 class="fw-bold text-danger mb-1">Firma Pendiente</h6>
                                            <p class="small text-muted mb-2">Requiere validación final.</p>
                                            
                                            <?php if ($req['agent_id'] == $sid): ?>
                                                <button type="button" class="btn btn-danger fw-bold w-100 mt-2 py-2" data-bs-toggle="modal" data-bs-target="#signatureModal">
                                                    <i class="bi bi-pen me-2"></i>Firmar Recepción
                                                </button>
                                                
                                                <!-- Modal de Firma -->
                                                <div class="modal fade" id="signatureModal" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog modal-dialog-centered modal-lg">
                                                        <div class="modal-content border-0 shadow">
                                                            <div class="modal-header border-bottom-0 bg-light">
                                                                <h5 class="modal-title fw-bold"><i class="bi bi-pen text-danger me-2"></i>Firmar Recepción de Activos</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body p-4 bg-light">
                                                                <p class="text-center text-muted small mb-3">Dibuja tu firma en el recuadro blanco. <br><strong>Tip:</strong> Puedes girar tu celular en horizontal para mayor comodidad.</p>
                                                                
                                                                <form method="post" action="requisitions.php" id="signature-form">
                                                                    <?php csrfField(); ?>
                                                                    <input type="hidden" name="do" value="sign">
                                                                    <input type="hidden" name="id" value="<?php echo $req['id']; ?>">
                                                                    <input type="hidden" name="signature" id="signature-data">
                                                                    
                                                                    <div class="border border-2 border-secondary rounded bg-white signature-wrapper shadow-sm" style="height: 280px; position: relative;">
                                                                        <canvas id="signature-pad" style="width: 100%; height: 100%; cursor: crosshair; touch-action: none; position: absolute; top:0; left:0;"></canvas>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                            <div class="modal-footer border-top-0 d-flex bg-white gap-2">
                                                                <button type="button" class="btn btn-outline-secondary fw-bold flex-fill" onclick="clearSignature()">Limpiar</button>
                                                                <button type="button" class="btn btn-danger fw-bold flex-fill" onclick="saveSignature()">Confirmar y Guardar</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <script>
                                                    const canvas = document.getElementById('signature-pad');
                                                    const ctx = canvas.getContext('2d');
                                                    let isDrawing = false;
                                                    
                                                    function resizeCanvas() {
                                                        const ratio = Math.max(window.devicePixelRatio || 1, 1);
                                                        canvas.width = canvas.offsetWidth * ratio;
                                                        canvas.height = canvas.offsetHeight * ratio;
                                                        ctx.scale(ratio, ratio);
                                                        ctx.lineCap = 'round';
                                                        ctx.lineJoin = 'round';
                                                        ctx.lineWidth = 3;
                                                        ctx.strokeStyle = '#0f172a';
                                                    }
                                                    
                                                    const sigModal = document.getElementById('signatureModal');
                                                    sigModal.addEventListener('shown.bs.modal', resizeCanvas);
                                                    window.addEventListener('resize', resizeCanvas);
                                                    window.addEventListener('orientationchange', function() {
                                                        setTimeout(resizeCanvas, 200);
                                                    });
                
                                                    function getPos(e) {
                                                        const rect = canvas.getBoundingClientRect();
                                                        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
                                                        const clientY = e.touches ? e.touches[0].clientY : e.clientY;
                                                        return {
                                                            x: clientX - rect.left,
                                                            y: clientY - rect.top
                                                        };
                                                    }
                
                                                    function startPosition(e) {
                                                        isDrawing = true;
                                                        draw(e);
                                                    }
                
                                                    function endPosition() {
                                                        isDrawing = false;
                                                        ctx.beginPath();
                                                    }
                
                                                    function draw(e) {
                                                        if (!isDrawing) return;
                                                        e.preventDefault();
                                                        const pos = getPos(e);
                                                        ctx.lineTo(pos.x, pos.y);
                                                        ctx.stroke();
                                                        ctx.beginPath();
                                                        ctx.moveTo(pos.x, pos.y);
                                                    }
                
                                                    canvas.addEventListener('mousedown', startPosition);
                                                    canvas.addEventListener('mouseup', endPosition);
                                                    canvas.addEventListener('mousemove', draw);
                                                    
                                                    canvas.addEventListener('touchstart', startPosition, {passive: false});
                                                    canvas.addEventListener('touchend', endPosition);
                                                    canvas.addEventListener('touchmove', draw, {passive: false});
                
                                                    function clearSignature() {
                                                        ctx.clearRect(0, 0, canvas.width, canvas.height);
                                                    }
                                                    
                                                    function saveSignature() {
                                                        const blank = document.createElement('canvas');
                                                        blank.width = canvas.width;
                                                        blank.height = canvas.height;
                                                        if (canvas.toDataURL() === blank.toDataURL()) {
                                                            alert("Por favor dibuje su firma primero.");
                                                            return;
                                                        }
                                                        document.getElementById('signature-data').value = canvas.toDataURL('image/png');
                                                        document.getElementById('signature-form').submit();
                                                    }
                                                </script>
                                            <?php else: ?>
                                                <div class="alert alert-warning mb-0 small">
                                                    Esperando la firma del agente solicitante.
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="bg-danger rounded-circle border border-white border-3 shadow-sm me-3" style="width: 16px; height: 16px; margin-top: 4px;"></div>
                                        <div class="w-100">
                                            <h6 class="fw-bold mb-0 text-dark">Firma Confirmada</h6>
                                            <p class="small text-muted mb-2"><?php echo date('d M Y, h:i A', strtotime($req['signed_at'])); ?></p>
                                            <div class="bg-white border rounded-3 p-2 text-center mt-2 shadow-sm">
                                                <img src="<?php echo html($req['agent_signature']); ?>" alt="Firma del agente" style="max-height: 80px; max-width: 100%;">
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="d-flex position-relative z-1">
                                    <div class="bg-light rounded-circle border border-white border-3 me-3" style="width: 16px; height: 16px; margin-top: 4px;"></div>
                                    <div>
                                        <h6 class="fw-bold mb-0 text-muted">Firma Confirmada</h6>
                                        <p class="small text-muted mb-0 opacity-75">Requiere validación final</p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="card border-0 shadow-sm rounded-4 bg-light">
                    <div class="card-body p-4 d-flex align-items-start gap-3">
                        <i class="bi bi-info-circle text-danger fs-4 mt-1"></i>
                        <p class="small text-muted mb-0">Una vez firmada la recepción, los activos se asignarán automáticamente al centro de costos del solicitante.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
