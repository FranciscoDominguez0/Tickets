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

<div class="settings-hero mb-4 p-3 p-md-4">
    <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3">
        <div class="d-flex align-items-center gap-3">
            <span class="settings-hero-icon"><i class="bi bi-box-seam"></i></span>
            <div>
                <h1 class="mb-0">Requisiciones e Inventario</h1>
                <p class="mb-0" style="opacity: 0.85;">Control de salidas de productos y materiales</p>
            </div>
        </div>
        <?php if ($action !== 'new'): ?>
            <div class="mt-3 mt-md-0">
                <a href="requisitions.php?a=new" class="btn btn-light fw-bold text-danger"><i class="bi bi-plus-lg me-1"></i> Nueva Salida</a>
            </div>
        <?php endif; ?>
    </div>
</div>

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

    <div class="card border-0 shadow-sm rounded-4 mb-4 p-3 bg-white">
        <form method="get" action="requisitions.php" class="d-flex flex-column flex-md-row gap-2 mb-0">
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                <input type="text" name="q" value="<?php echo html($search); ?>" class="form-control border-start-0 ps-0" placeholder="Buscar por nombre de cliente o ID (#REQ-...)">
            </div>
            <div class="d-flex flex-column flex-sm-row gap-2 mt-2 mt-md-0">
                <button type="submit" class="btn btn-primary fw-bold px-4">Buscar</button>
                <?php if ($search !== ''): ?>
                    <a href="requisitions.php" class="btn btn-outline-secondary fw-bold px-3 text-center">Limpiar</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">ID</th>
                            <th>Cliente</th>
                            <th>Estado</th>
                            <th>Fecha Solicitud</th>
                            <th class="text-end pe-4">Acciones</th>
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
                            <tr>
                                <td class="ps-4 fw-bold">#REQ-<?php echo str_pad($row['id'], 5, '0', STR_PAD_LEFT); ?></td>
                                <td><?php echo html($row['client_name']); ?></td>
                                <td>
                                    <?php if ($row['status'] === 'pending'): ?>
                                        <span class="badge bg-warning text-dark"><i class="bi bi-clock me-1"></i>Pendiente</span>
                                    <?php else: ?>
                                        <span class="badge bg-success"><i class="bi bi-check-all me-1"></i>Entregado</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d/m/Y h:i A', strtotime($row['created_at'])); ?></td>
                                <td class="text-end pe-4">
                                    <a href="requisitions.php?a=view&id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-primary fw-bold">Ver Detalles</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if ($totalPages > 1): ?>
        <div class="card-footer bg-white border-top py-3 rounded-bottom-4">
            <nav aria-label="Navegación de páginas">
                <ul class="pagination justify-content-center mb-0">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="requisitions.php?q=<?php echo urlencode($search); ?>&p=<?php echo $page - 1; ?>">Anterior</a>
                    </li>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="requisitions.php?q=<?php echo urlencode($search); ?>&p=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="requisitions.php?q=<?php echo urlencode($search); ?>&p=<?php echo $page + 1; ?>">Siguiente</a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
<?php elseif ($action === 'new'): ?>
    <div class="card border-0 shadow-sm rounded-4 max-w-700 mx-auto" style="max-width: 800px;">
        <div class="card-header bg-white border-bottom-0 pt-4 pb-0 px-4">
            <h5 class="fw-bold mb-0">Solicitar Materiales al Inventario</h5>
        </div>
        <div class="card-body p-4">
            <form method="post" action="requisitions.php">
                <?php csrfField(); ?>
                <input type="hidden" name="do" value="create">
                
                <div class="mb-4">
                    <label class="form-label fw-bold">Nombre del Cliente</label>
                    <input type="text" name="client_name" class="form-control form-control-lg bg-light" required placeholder="Ej. Juan Pérez o Empresa XYZ">
                    <div class="form-text">Para quién o qué servicio son estos materiales.</div>
                </div>

                <div class="mb-3 d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-end gap-2">
                    <label class="form-label fw-bold mb-0">Productos Solicitados</label>
                    <button type="button" class="btn btn-sm btn-outline-secondary fw-bold mt-2 mt-sm-0" onclick="addProductRow()"><i class="bi bi-plus-lg me-1"></i> Añadir Producto</button>
                </div>
                
                <div id="products-container">
                    <div class="row g-2 mb-2 product-row">
                        <div class="col-12 col-sm-8 mb-1 mb-sm-0">
                            <input type="text" name="product_name[]" class="form-control" required placeholder="Nombre del producto / material">
                        </div>
                        <div class="col-9 col-sm-3">
                            <input type="number" name="quantity[]" class="form-control" required min="1" value="1" placeholder="Cant.">
                        </div>
                        <div class="col-3 col-sm-1 text-end">
                            <button type="button" class="btn btn-outline-danger w-100" onclick="removeRow(this)" disabled><i class="bi bi-trash"></i></button>
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-top d-flex flex-column flex-sm-row justify-content-end gap-2">
                    <a href="requisitions.php" class="btn btn-light fw-bold text-center">Cancelar</a>
                    <button type="submit" class="btn btn-primary fw-bold px-4">Enviar Solicitud <i class="bi bi-send ms-1"></i></button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function addProductRow() {
            const container = document.getElementById('products-container');
            const row = document.createElement('div');
            row.className = 'row g-2 mb-2 product-row';
            row.innerHTML = `
                <div class="col-12 col-sm-8 mb-1 mb-sm-0">
                    <input type="text" name="product_name[]" class="form-control" required placeholder="Nombre del producto / material">
                </div>
                <div class="col-9 col-sm-3">
                    <input type="number" name="quantity[]" class="form-control" required min="1" value="1" placeholder="Cant.">
                </div>
                <div class="col-3 col-sm-1 text-end">
                    <button type="button" class="btn btn-outline-danger w-100" onclick="removeRow(this)"><i class="bi bi-trash"></i></button>
                </div>
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
            const btns = document.querySelectorAll('.product-row .btn-outline-danger');
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
    <div class="row g-4">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-bottom-0 pt-4 px-4 d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2">
                    <h5 class="fw-bold mb-0">Detalles de Requisición #REQ-<?php echo str_pad($req['id'], 5, '0', STR_PAD_LEFT); ?></h5>
                    <?php if ($req['status'] === 'pending'): ?>
                        <span class="badge bg-warning text-dark"><i class="bi bi-clock me-1"></i>Pendiente de Entrega</span>
                    <?php else: ?>
                        <span class="badge bg-success"><i class="bi bi-check-all me-1"></i>Entregado</span>
                    <?php endif; ?>
                </div>
                <div class="card-body px-4">
                    <div class="mb-4 p-3 bg-light rounded-3">
                        <div class="row">
                            <div class="col-sm-6 mb-2 mb-sm-0">
                                <span class="d-block text-muted small fw-bold text-uppercase">Cliente asignado</span>
                                <span class="fw-bold fs-5"><?php echo html($req['client_name']); ?></span>
                            </div>
                            <div class="col-sm-6">
                                <span class="d-block text-muted small fw-bold text-uppercase">Fecha Solicitud</span>
                                <span class="fw-bold"><?php echo date('d/m/Y h:i A', strtotime($req['created_at'])); ?></span>
                            </div>
                        </div>
                    </div>

                    <h6 class="fw-bold text-muted text-uppercase small mb-3">Productos Solicitados</h6>
                    <div class="table-responsive border rounded-3">
                        <table class="table table-borderless table-striped align-middle mb-0">
                            <thead class="table-light border-bottom">
                                <tr>
                                    <th class="ps-3">Producto / Descripción</th>
                                    <th class="text-center" style="width: 100px;">Cantidad</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($item = $items->fetch_assoc()): ?>
                                <tr>
                                    <td class="ps-3 fw-bold"><?php echo html($item['product_name']); ?></td>
                                    <td class="text-center"><span class="badge bg-secondary fs-6 rounded-pill px-3"><?php echo html($item['quantity']); ?></span></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4 d-flex flex-column">
                    <h5 class="fw-bold mb-4 border-bottom pb-2">Estado y Acciones</h5>
                    
                    <?php if ($req['status'] === 'pending'): ?>
                        <div class="text-center mb-4 flex-grow-1 d-flex flex-column justify-content-center">
                            <div class="display-1 text-warning mb-3"><i class="bi bi-box-seam"></i></div>
                            <h6 class="fw-bold">Esperando Entrega</h6>
                            <p class="text-muted small">El administrador o encargado de inventario debe preparar estos productos y marcar como entregado.</p>
                        </div>
                        <?php if ($canManage): ?>
                            <button type="button" class="btn btn-success fw-bold w-100 py-3" data-bs-toggle="modal" data-bs-target="#confirmDeliveryModal">
                                <i class="bi bi-check-circle me-2"></i>Marcar como Entregado
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
                    
                    <?php elseif ($req['status'] === 'delivered'): ?>
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
                        <div class="mb-4">
                            <div class="d-flex align-items-center mb-2">
                                <i class="bi bi-person-check-fill text-success fs-4 me-2"></i>
                                <div>
                                    <span class="d-block small text-muted">Entregado por</span>
                                    <span class="fw-bold"><?php echo html($adminName); ?></span>
                                </div>
                            </div>
                            <div class="d-flex align-items-center">
                                <i class="bi bi-calendar-check text-muted fs-4 me-2"></i>
                                <div>
                                    <span class="d-block small text-muted">Fecha de entrega</span>
                                    <span class="fw-bold"><?php echo date('d/m/Y h:i A', strtotime($req['delivered_at'])); ?></span>
                                </div>
                            </div>
                        </div>

                        <?php if (empty($req['agent_signature'])): ?>
                            <?php if ($req['agent_id'] == $sid): ?>
                                <div class="bg-light p-3 rounded-3 mb-3 text-center border border-warning">
                                    <h6 class="fw-bold text-danger mb-2"><i class="bi bi-pen me-2"></i>Firma Requerida</h6>
                                    <p class="small text-muted mb-3">Confirma que recibiste los productos firmando a continuación.</p>
                                    <button type="button" class="btn btn-primary fw-bold w-100 py-3" data-bs-toggle="modal" data-bs-target="#signatureModal">
                                        <i class="bi bi-pen me-2"></i>Firmar Recepción
                                    </button>
                                </div>
                                
                                <!-- Modal de Firma -->
                                <div class="modal fade" id="signatureModal" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered modal-lg">
                                        <div class="modal-content border-0 shadow">
                                            <div class="modal-header border-bottom-0 bg-light">
                                                <h5 class="modal-title fw-bold"><i class="bi bi-pen text-primary me-2"></i>Firma de Recepción</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body p-4 bg-light">
                                                <p class="text-center text-muted small mb-3">Dibuja tu firma en el recuadro blanco. <br><strong>Tip:</strong> Puedes girar tu celular en horizontal para mayor comodidad.</p>
                                                
                                                <form method="post" action="requisitions.php" id="signature-form">
                                                    <?php csrfField(); ?>
                                                    <input type="hidden" name="do" value="sign">
                                                    <input type="hidden" name="id" value="<?php echo $req['id']; ?>">
                                                    <input type="hidden" name="signature" id="signature-data">
                                                    
                                                    <div class="border border-2 border-secondary rounded bg-white signature-wrapper" style="height: 280px; position: relative;">
                                                        <canvas id="signature-pad" style="width: 100%; height: 100%; cursor: crosshair; touch-action: none; position: absolute; top:0; left:0;"></canvas>
                                                    </div>
                                                </form>
                                            </div>
                                            <div class="modal-footer border-top-0 d-flex bg-white gap-2">
                                                <button type="button" class="btn btn-outline-secondary fw-bold flex-fill" onclick="clearSignature()">Limpiar</button>
                                                <button type="button" class="btn btn-primary fw-bold flex-fill" onclick="saveSignature()">Guardar Firma</button>
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
                                    
                                    // Ajustar tamaño al abrir modal o girar pantalla
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
                                <div class="alert alert-warning mb-0 text-center">
                                    <i class="bi bi-clock me-2"></i>Esperando la firma del agente solicitante.
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="bg-white border rounded-3 p-3 text-center mb-4">
                                <h6 class="fw-bold text-success mb-2"><i class="bi bi-check-circle-fill me-2"></i>Firma Registrada</h6>
                                <img src="<?php echo html($req['agent_signature']); ?>" alt="Firma del agente" style="max-height: 80px; max-width: 100%;">
                                <div class="small text-muted mt-2">Firmado el <?php echo date('d/m/Y h:i A', strtotime($req['signed_at'])); ?></div>
                            </div>
                            
                            <a href="print_requisition.php?id=<?php echo $req['id']; ?>" target="_blank" class="btn btn-outline-primary fw-bold w-100 py-2 mt-auto">
                                <i class="bi bi-printer me-2"></i>Imprimir Requisición
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
