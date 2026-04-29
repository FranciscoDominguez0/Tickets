<?php
require_once '../../config.php';
require_once '../../includes/helpers.php';
require_once '../../includes/Auth.php';

if (!isset($_SESSION['staff_id'])) {
    header('Location: login.php');
    exit;
}

requireLogin('agente');
$staff = getCurrentUser();
$currentRoute = 'deleted_tickets';

// --- LÓGICA DE NEGOCIO ---
requireRolePermission('ticket.delete', 'index.php');

$eid = empresaId();
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id > 0) {
    if (!isset($_POST['csrf_token']) || !Auth::validateCSRF($_POST['csrf_token'])) {
        $_SESSION['flash_error'] = 'Token de seguridad inválido.';
    } else {
        $resReq = $mysqli->query("SELECT * FROM ticket_deletion_requests WHERE id = $id AND empresa_id = $eid");
        $request = $resReq ? $resReq->fetch_assoc() : null;

        if (!$request) {
            $_SESSION['flash_error'] = 'Solicitud no encontrada.';
        } elseif ($request['status'] !== 'pending') {
            $_SESSION['flash_error'] = 'Esta solicitud ya ha sido resuelta.';
        } else {
            if ($action === 'approve_delete') {
                $tid = (int)$request['ticket_id'];
                try {
                    if (method_exists($mysqli, 'begin_transaction')) $mysqli->begin_transaction();
                    $mysqli->query("DELETE te FROM thread_entries te JOIN threads th ON th.id = te.thread_id WHERE th.ticket_id = $tid");
                    $mysqli->query("DELETE FROM threads WHERE ticket_id = $tid");
                    $mysqli->query("DELETE FROM tickets WHERE id = $tid AND empresa_id = $eid");
                    $stmtUpd = $mysqli->prepare("UPDATE ticket_deletion_requests SET status = 'approved', resolved_at = NOW(), resolved_by = ? WHERE id = ?");
                    $stmtUpd->bind_param('ii', $_SESSION['staff_id'], $id);
                    $stmtUpd->execute();
                    if (method_exists($mysqli, 'commit')) $mysqli->commit();
                    $_SESSION['flash_msg'] = 'Ticket borrado permanentemente.';
                } catch (Throwable $e) {
                    if (method_exists($mysqli, 'rollback')) $mysqli->rollback();
                    $_SESSION['flash_error'] = 'Error al ejecutar el borrado.';
                }
            } elseif ($action === 'reject_delete') {
                $stmtUpd = $mysqli->prepare("UPDATE ticket_deletion_requests SET status = 'rejected', resolved_at = NOW(), resolved_by = ? WHERE id = ?");
                $stmtUpd->bind_param('ii', $_SESSION['staff_id'], $id);
                if ($stmtUpd->execute()) {
                    $_SESSION['flash_msg'] = 'Solicitud de borrado rechazada.';
                }
            }
        }
    }
    header('Location: deleted_tickets.php');
    exit;
}

$sql = "SELECT r.*, CONCAT(s.firstname, ' ', s.lastname) as requester_name, CONCAT(v.firstname, ' ', v.lastname) as resolver_name 
        FROM ticket_deletion_requests r 
        LEFT JOIN staff s ON r.requested_by = s.id 
        LEFT JOIN staff v ON r.resolved_by = v.id 
        WHERE r.empresa_id = $eid 
        ORDER BY r.created_at DESC";
$res = $mysqli->query($sql);

ob_start();
?>
<!-- CABECERA ESTILO ADMIN (HERO) -->
<div class="settings-hero">
    <div class="d-flex align-items-center gap-3">
        <span class="settings-hero-icon" style="background: #f1f5f9; color: #1e293b;"><i class="bi bi-trash"></i></span>
        <div>
            <h1>Historial de Tickets Borrados</h1>
            <p>Supervisión y aprobación de solicitudes de eliminación de registros.</p>
        </div>
    </div>
</div>

<!-- CONTENIDO PRINCIPAL -->
<div class="card settings-card border-0 shadow-sm mt-4">
    <div class="card-header bg-white py-3 border-bottom">
        <div class="d-flex justify-content-between align-items-center">
            <strong><i class="bi bi-list-ul me-2"></i>Registros de eliminación</strong>
            <span class="badge bg-light text-dark"><?php echo $res->num_rows; ?> entradas</span>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Ticket</th>
                        <th>Solicitado por</th>
                        <th>Motivo</th>
                        <th>Estado</th>
                        <th>Resolución</th>
                        <th class="pe-4 text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($res && $res->num_rows > 0): ?>
                        <?php while ($r = $res->fetch_assoc()): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-primary">#<?php echo html($r['ticket_number']); ?></div>
                                    <div class="small text-muted text-truncate" style="max-width: 200px;"><?php echo html($r['ticket_subject']); ?></div>
                                    <div class="x-small text-muted"><?php echo formatDate($r['created_at']); ?></div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="avatar-circle-sm"><?php echo strtoupper(substr($r['requester_name'] ?? '?', 0, 1)); ?></div>
                                        <span class="small fw-semibold"><?php echo html($r['requester_name']); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="small text-wrap" style="max-width: 250px;"><?php echo html($r['reason']); ?></div>
                                </td>
                                <td>
                                    <?php 
                                    $s = $r['status'];
                                    if ($s === 'pending') echo '<span class="badge bg-warning text-dark"><i class="bi bi-clock me-1"></i>Pendiente</span>';
                                    elseif ($s === 'approved') echo '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Aprobado</span>';
                                    else echo '<span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Rechazado</span>';
                                    ?>
                                </td>
                                <td>
                                    <?php if ($r['resolved_at']): ?>
                                        <div class="small">
                                            <div class="fw-bold"><?php echo html($r['resolver_name'] ?: 'Admin'); ?></div>
                                            <div class="text-muted"><?php echo formatDate($r['resolved_at']); ?></div>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted small">---</span>
                                    <?php endif; ?>
                                                                <td class="pe-4 text-end">
                                    <?php if ($s === 'pending'): ?>
                                        <div class="btn-group btn-group-sm shadow-sm">
                                            <button type="button" class="btn btn-success" 
                                                    onclick="openResolveModal('approve_delete', <?php echo (int)$r['id']; ?>, '<?php echo html($r['ticket_number']); ?>')"
                                                    title="Aprobar borrado">
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-danger" 
                                                    onclick="openResolveModal('reject_delete', <?php echo (int)$r['id']; ?>, '<?php echo html($r['ticket_number']); ?>')"
                                                    title="Rechazar solicitud">
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <i class="bi bi-check2-all text-muted"></i>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center py-5 text-muted">No hay solicitudes registradas.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal de Resolución -->
<div class="modal fade" id="resolveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" class="modal-content border-0 shadow-lg">
            <?php csrfField(); ?>
            <input type="hidden" name="id" id="modalId">
            <input type="hidden" name="action" id="modalAction">
            
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold" id="modalTitle">Resolver Solicitud</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4">
                <div class="text-center mb-4 d-none" id="modalIconApprove">
                    <div class="display-1 text-success"><i class="bi bi-check-circle-fill"></i></div>
                </div>
                <div class="text-center mb-4 d-none" id="modalIconReject">
                    <div class="display-1 text-danger"><i class="bi bi-x-circle-fill"></i></div>
                </div>
                
                <p class="text-center fs-5 mb-0" id="modalBodyText"></p>
                <p class="text-center text-muted small mt-2" id="modalTicketText"></p>
                
                <div class="alert alert-danger mt-4 mb-0 d-none" id="modalWarning">
                    <div class="d-flex gap-2">
                        <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                        <div>
                            <strong>¡Atención!</strong> Esta acción eliminará el ticket y todos sus mensajes permanentemente de la base de datos.
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top-0 pt-0 justify-content-center pb-4">
                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn px-4 fw-bold" id="modalSubmitBtn">Confirmar Acción</button>
            </div>
        </form>
    </div>
</div>

<script>
function openResolveModal(action, id, ticketNum) {
    const modalEl = document.getElementById('resolveModal');
    const modal = new bootstrap.Modal(modalEl);
    
    document.getElementById('modalId').value = id;
    document.getElementById('modalAction').value = action;
    document.getElementById('modalTicketText').textContent = 'Ticket #' + ticketNum;
    
    const titleEl = document.getElementById('modalTitle');
    const bodyEl = document.getElementById('modalBodyText');
    const btnEl = document.getElementById('modalSubmitBtn');
    const warningEl = document.getElementById('modalWarning');
    const iconApprove = document.getElementById('modalIconApprove');
    const iconReject = document.getElementById('modalIconReject');
    
    // Resetear
    iconApprove.classList.add('d-none');
    iconReject.classList.add('d-none');
    warningEl.classList.add('d-none');
    
    if (action === 'approve_delete') {
        titleEl.textContent = 'Aprobar Eliminación';
        bodyEl.textContent = '¿Estás seguro de aprobar el borrado definitivo?';
        btnEl.className = 'btn btn-success px-4 fw-bold';
        btnEl.textContent = 'Aprobar y Borrar';
        warningEl.classList.remove('d-none');
        iconApprove.classList.remove('d-none');
    } else {
        titleEl.textContent = 'Rechazar Solicitud';
        bodyEl.textContent = '¿Deseas rechazar esta solicitud de borrado?';
        btnEl.className = 'btn btn-danger px-4 fw-bold';
        btnEl.textContent = 'Rechazar Solicitud';
        iconReject.classList.remove('d-none');
    }
    
    modal.show();
}
</script>

<style>
.avatar-circle-sm {
    width: 24px; height: 24px; background: #e2e8f0; border-radius: 50%;
    display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: bold; color: #475569;
}
.x-small { font-size: 0.7rem; }
.display-1 { font-size: 4.5rem; line-height: 1; }
</style>
</style>
<?php
$content = ob_get_clean();
require_once 'layout_admin.php';
exit;
