<?php
if (!defined('APP_URL')) die('Direct access not permitted');

requireRolePermission('requisitions.view');

$eid = empresaId();
$sid = (int)$_SESSION['staff_id'];
$action = $_GET['a'] ?? 'list';
$canManage = roleHasPermission('requisitions.manage');
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF()) {
        $flashError = 'Token inválido.';
    } else {
        $do = $_POST['do'] ?? '';
        if ($do === 'create') {
            $clientName = trim($_POST['client_name'] ?? '');
            $products = $_POST['product_name'] ?? [];
            $quantities = $_POST['quantity'] ?? [];
            if ($clientName === '' || empty($products)) {
                $flashError = 'El nombre del cliente y al menos un producto son requeridos.';
            } else {
                $mysqli->begin_transaction();
                try {
                    $stmtIns = $mysqli->prepare("INSERT INTO requisitions (empresa_id, agent_id, client_name, created_at) VALUES (?, ?, ?, NOW())");
                    $stmtIns->bind_param('iis', $eid, $sid, $clientName);
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
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2">
            <div>
                <h1>Requisiciones e Inventario</h1>
                <div class="sub">Control de salidas de productos y materiales</div>
            </div>
            <a href="requisitions.php?a=new" class="btn-new" style="background: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.30); color: #fff; padding: 8px 16px; border-radius: 10px; text-decoration: none; font-weight: 700; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 6px;">
                <i class="bi bi-plus-lg"></i> Nueva Salida
            </a>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white overflow-hidden">
        <div class="card-header bg-white border-bottom p-3">
            <form method="get" action="requisitions.php" class="d-flex flex-column flex-md-row gap-3 align-items-center justify-content-between m-0">
                <div class="input-group flex-grow-1">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" value="<?php echo html($search); ?>" class="form-control border-start-0 ps-0" placeholder="Buscar por nombre de cliente o ID (#REQ-...)">
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-danger fw-bold px-4">Buscar</button>
                    <?php if ($search !== ''): ?>
                        <a href="requisitions.php" class="btn btn-outline-secondary fw-bold px-3 text-center">Limpiar</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-muted small text-uppercase">
                        <tr class="d-none d-md-table-row">
                            <th class="ps-4 py-3">ID</th>
                            <th class="py-3">Cliente</th>
                            <th class="py-3">Estado</th>
                            <th class="py-3">Fecha Solicitud</th>
                            <th class="text-end pe-4 py-3">Acciones</th>
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
                            echo '<tr><td colspan="5" class="text-center text-muted py-5"><i class="bi bi-inbox fs-2 d-block mb-2"></i> No hay requisiciones registradas.</td></tr>';
                        }
                        while ($row = $res->fetch_assoc()):
                        ?>
                            <!-- Desktop Row -->
                            <tr class="d-none d-md-table-row">
                                <td class="ps-4 py-3 fw-bold text-dark">#REQ-<?php echo str_pad($row['id'], 5, '0', STR_PAD_LEFT); ?></td>
                                <td class="py-3 fw-medium"><?php echo html($row['client_name']); ?></td>
                                <td class="py-3">
                                    <?php if ($row['status'] === 'pending'): ?>
                                        <span class="badge bg-warning text-dark px-2 py-1"><i class="bi bi-clock me-1"></i>Pendiente</span>
                                    <?php else: ?>
                                        <span class="badge bg-success px-2 py-1"><i class="bi bi-check-all me-1"></i>Entregado</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 text-muted"><?php echo date('d M, Y', strtotime($row['created_at'])); ?></td>
                                <td class="text-end pe-4 py-3">
                                    <a href="requisitions.php?a=view&id=<?php echo $row['id']; ?>" class="btn btn-sm btn-light fw-bold text-danger border shadow-sm">
                                        <i class="bi bi-eye"></i> Detalles
                                    </a>
                                </td>
                            </tr>
                            <!-- Mobile Card Row -->
                            <tr class="d-md-none border-bottom d-block w-100">
                                <td colspan="5" class="p-0 border-0 d-block w-100">
                                    <div class="px-3 py-3 position-relative w-100">
                                        <!-- Status Badge absolutely positioned top-right -->
                                        <div class="position-absolute top-0 end-0 mt-3 me-3">
                                            <?php if ($row['status'] === 'pending'): ?>
                                                <span class="badge bg-warning text-dark px-2 py-1"><i class="bi bi-clock me-1"></i>Pendiente</span>
                                            <?php else: ?>
                                                <span class="badge bg-success px-2 py-1"><i class="bi bi-check-all me-1"></i>Entregado</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="fw-bold text-dark fs-5 mb-2 pe-5">#REQ-<?php echo str_pad($row['id'], 5, '0', STR_PAD_LEFT); ?></div>
                                        <div class="mb-2 fw-medium text-dark"><i class="bi bi-person me-2 text-muted"></i><?php echo html($row['client_name']); ?></div>
                                        <div class="mb-3 text-muted small"><i class="bi bi-calendar me-2"></i><?php echo date('d M, Y, h:i A', strtotime($row['created_at'])); ?></div>
                                        <a href="requisitions.php?a=view&id=<?php echo $row['id']; ?>" class="btn btn-light fw-bold text-danger border w-100 shadow-sm py-2">
                                            <i class="bi bi-eye me-1"></i> Ver Detalles
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if ($totalPages > 1): ?>
        <div class="card-footer bg-light border-top py-3">
            <nav aria-label="Navegación de páginas">
                <ul class="pagination justify-content-center mb-0">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link border-0 bg-transparent text-secondary" href="requisitions.php?q=<?php echo urlencode($search); ?>&p=<?php echo $page - 1; ?>"><i class="bi bi-chevron-left"></i></a>
                    </li>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link <?php echo $i === $page ? 'bg-danger border-danger rounded-3 text-white shadow-sm' : 'border-0 bg-transparent text-dark'; ?>" href="requisitions.php?q=<?php echo urlencode($search); ?>&p=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                        <a class="page-link border-0 bg-transparent text-secondary" href="requisitions.php?q=<?php echo urlencode($search); ?>&p=<?php echo $page + 1; ?>"><i class="bi bi-chevron-right"></i></a>
                    </li>
                </ul>
            </nav>
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
                        <i class="bi bi-person-badge fs-4 me-2"></i> Información del Cliente
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold text-muted small text-uppercase">Nombre del Cliente</label>
                        <input type="text" name="client_name" class="form-control form-control-lg border-secondary-subtle" required placeholder="Ej. Juan Pérez">
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
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-light border-bottom-0 p-4 d-flex justify-content-between align-items-center">
                    <div class="section-title">
                        <i class="bi bi-box-seam-fill fs-4 me-2"></i> Productos Solicitados
                    </div>
                    <span class="badge bg-secondary rounded-pill px-3 py-2"><?php echo $items->num_rows; ?> Artículos</span>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item bg-light text-muted small text-uppercase fw-bold d-none d-md-flex px-4 py-3">
                            <div class="flex-grow-1">Producto</div>
                            <div style="width: 100px;" class="text-end">Cantidad</div>
                        </li>
                        <?php while ($item = $items->fetch_assoc()): ?>
                        <li class="list-group-item px-3 px-md-4 py-3 d-flex justify-content-between align-items-center">
                            <div class="fw-bold text-dark text-break pe-3">
                                <i class="bi bi-box me-2 text-secondary opacity-50 d-none d-md-inline"></i>
                                <?php echo html($item['product_name']); ?>
                            </div>
                            <div class="text-end flex-shrink-0">
                                <span class="badge bg-secondary-subtle text-secondary rounded-pill px-3 py-2 fs-6 border">
                                    Cant: <?php echo html($item['quantity']); ?>
                                </span>
                            </div>
                        </li>
                        <?php endwhile; ?>
                    </ul>
                </div>
            </div>
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
