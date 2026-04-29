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

// --- PAGINACIÓN ---
$limit = 10;
$page = isset($_GET['p']) && is_numeric($_GET['p']) ? (int)$_GET['p'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Contar total para paginación
$totalRes = $mysqli->query("SELECT COUNT(*) as c FROM ticket_deletion_requests WHERE empresa_id = $eid");
$totalCount = $totalRes ? (int)($totalRes->fetch_assoc()['c'] ?? 0) : 0;
$totalPages = ceil($totalCount / $limit);

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
    header('Location: deleted_tickets.php?p=' . $page);
    exit;
}

$sql = "SELECT r.*, CONCAT(s.firstname, ' ', s.lastname) as requester_name, CONCAT(v.firstname, ' ', v.lastname) as resolver_name 
        FROM ticket_deletion_requests r 
        LEFT JOIN staff s ON r.requested_by = s.id 
        LEFT JOIN staff v ON r.resolved_by = v.id 
        WHERE r.empresa_id = $eid 
        ORDER BY r.created_at DESC
        LIMIT $limit OFFSET $offset";
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
            <span class="badge bg-light text-dark" style="font-weight: 700; border: 1px solid #e2e8f0;"><?php echo $totalCount; ?> entradas</span>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light" style="background: #f8fafc;">
                    <tr>
                        <th class="ps-4" style="font-weight: 700; color: #64748b; font-size: 0.75rem; text-transform: uppercase;">Ticket</th>
                        <th style="font-weight: 700; color: #64748b; font-size: 0.75rem; text-transform: uppercase;">Solicitado por</th>
                        <th style="font-weight: 700; color: #64748b; font-size: 0.75rem; text-transform: uppercase;">Motivo</th>
                        <th style="font-weight: 700; color: #64748b; font-size: 0.75rem; text-transform: uppercase;">Estado</th>
                        <th style="font-weight: 700; color: #64748b; font-size: 0.75rem; text-transform: uppercase;">Resolución</th>
                        <th class="pe-4 text-end" style="font-weight: 700; color: #64748b; font-size: 0.75rem; text-transform: uppercase;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($res && $res->num_rows > 0): ?>
                        <?php while ($r = $res->fetch_assoc()): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold" style="color: #2563eb;">#<?php echo html($r['ticket_number']); ?></div>
                                    <div class="small text-muted text-truncate" style="max-width: 180px; font-weight: 600;"><?php echo html($r['ticket_subject']); ?></div>
                                    <div class="x-small text-muted"><?php echo formatDate($r['created_at']); ?></div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="avatar-circle-sm"><?php echo strtoupper(substr($r['requester_name'] ?? '?', 0, 1)); ?></div>
                                        <span class="small fw-bold" style="color: #334155;"><?php echo html($r['requester_name']); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="small text-wrap" style="max-width: 220px; font-weight: 500; color: #475569;"><?php echo html($r['reason']); ?></div>
                                </td>
                                <td>
                                    <?php 
                                    $s = $r['status'];
                                    if ($s === 'pending') echo '<span class="badge" style="background: #fffbeb; color: #92400e; border: 1px solid #fde68a; font-weight: 700;"><i class="bi bi-clock me-1"></i>Pendiente</span>';
                                    elseif ($s === 'approved') echo '<span class="badge" style="background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; font-weight: 700;"><i class="bi bi-check-circle me-1"></i>Aprobado</span>';
                                    else echo '<span class="badge" style="background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; font-weight: 700;"><i class="bi bi-x-circle me-1"></i>Rechazado</span>';
                                    ?>
                                </td>
                                <td>
                                    <?php if ($r['resolved_at']): ?>
                                        <div class="small">
                                            <div class="fw-bold" style="color: #334155;"><?php echo html($r['resolver_name'] ?: 'Admin'); ?></div>
                                            <div class="text-muted x-small"><?php echo formatDate($r['resolved_at']); ?></div>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted small">---</span>
                                    <?php endif; ?>
                                </td>
                                <td class="pe-4 text-end">
                                    <?php if ($s === 'pending'): ?>
                                        <div class="btn-group btn-group-sm shadow-sm">
                                            <button type="button" class="btn btn-success" 
                                                    onclick="openResolveModal('approve_delete', <?php echo (int)$r['id']; ?>, '<?php echo html($r['ticket_number']); ?>')"
                                                    title="Aprobar borrado" style="background: #059669; border: none;">
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-danger" 
                                                    onclick="openResolveModal('reject_delete', <?php echo (int)$r['id']; ?>, '<?php echo html($r['ticket_number']); ?>')"
                                                    title="Rechazar solicitud" style="border-color: #e2e8f0; color: #dc2626;">
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <i class="bi bi-check2-all text-muted opacity-50"></i>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center py-5 text-muted fw-semibold">No hay solicitudes registradas.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="card-footer bg-white py-3 border-top" style="border-top: 1px solid #f1f5f9 !important;">
        <nav aria-label="Page navigation">
            <ul class="pagination pagination-sm justify-content-center mb-0 gap-1">
                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                    <a class="page-link border-0 rounded-3" href="?p=<?php echo $page - 1; ?>" style="background: #f8fafc; color: #64748b;">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                </li>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                        <a class="page-link border-0 rounded-3 fw-bold" href="?p=<?php echo $i; ?>" 
                           style="<?php echo ($i == $page) ? 'background: #2563eb; color: white;' : 'background: #f8fafc; color: #64748b;'; ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                    <a class="page-link border-0 rounded-3" href="?p=<?php echo $page + 1; ?>" style="background: #f8fafc; color: #64748b;">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<!-- Modal de Resolución (Estilo Corporativo Premium) -->
<div class="modal fade" id="resolveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" class="modal-content border-0 shadow-lg overflow-hidden" style="border-radius: 16px; background: #ffffff;">
            <?php csrfField(); ?>
            <input type="hidden" name="id" id="modalId">
            <input type="hidden" name="action" id="modalAction">
            
            <div class="modal-header border-bottom py-3 px-4" style="background: #f8fafc; border-bottom: 1px solid #e2e8f0 !important;">
                <div class="d-flex align-items-center gap-3">
                    <div id="modalIconBox" style="width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; transition: all 0.3s ease;">
                        <i class="bi bi-shield-check" id="modalMainIcon"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="modalTitle" style="color: #0f172a; font-size: 1.1rem; font-weight: 700;">Resolver Solicitud</h5>
                        <div class="text-muted small fw-semibold" id="modalTicketText" style="font-weight: 600;">Ticket #000000</div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-4 pt-4 text-center">
                <h4 class="fw-bold text-dark mb-2" id="modalBodyText" style="font-size: 1.2rem; font-weight: 700;">¿Deseas continuar?</h4>
                <p class="text-secondary px-2 mb-0" style="font-size: 0.92rem; color: #64748b;">Esta acción es definitiva y quedará registrada en el historial de auditoría de la empresa.</p>
                
                <div class="alert mt-4 mb-0 d-none" id="modalWarning" style="background: #fef2f2; border: 1px solid #fee2e2; border-radius: 12px; text-align: left;">
                    <div class="d-flex gap-3 align-items-center">
                        <div class="bg-danger text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; flex-shrink: 0;">
                            <i class="bi bi-exclamation-triangle-fill" style="font-size: 0.85rem;"></i>
                        </div>
                        <div class="small text-danger fw-bold" style="font-weight: 700; line-height: 1.3;">
                            Los datos del ticket y todos sus mensajes serán eliminados permanentemente.
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer border-top-0 p-4 pt-0 justify-content-center pb-5">
                <button type="button" class="btn btn-outline-secondary px-4 py-2" data-bs-dismiss="modal" style="border-radius: 10px; font-size: 0.88rem; font-weight: 700; border-color: #e2e8f0; color: #64748b;">Cancelar</button>
                <button type="submit" class="btn px-4 py-2 shadow-sm text-white" id="modalSubmitBtn" style="border-radius: 10px; font-size: 0.88rem; font-weight: 700; border: none;">Confirmar Acción</button>
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
    const iconBox = document.getElementById('modalIconBox');
    const mainIcon = document.getElementById('modalMainIcon');
    
    if (action === 'approve_delete') {
        iconBox.style.background = 'rgba(16, 185, 129, 0.12)';
        iconBox.style.color = '#059669';
        mainIcon.className = 'bi bi-trash-fill';
        titleEl.textContent = 'Aprobar Borrado';
        bodyEl.textContent = '¿Aprobar eliminación permanente?';
        btnEl.style.background = '#059669';
        btnEl.textContent = 'Aprobar y Borrar';
        warningEl.classList.remove('d-none');
    } else {
        iconBox.style.background = 'rgba(239, 68, 68, 0.12)';
        iconBox.style.color = '#dc2626';
        mainIcon.className = 'bi bi-x-circle-fill';
        titleEl.textContent = 'Rechazar Solicitud';
        bodyEl.textContent = '¿Rechazar petición de borrado?';
        btnEl.style.background = '#dc2626';
        btnEl.textContent = 'Rechazar Solicitud';
        warningEl.classList.add('d-none');
    }
    
    modal.show();
}
</script>

<style>
.modal-backdrop.show { opacity: 0.45; background-color: #0f172a; }
.avatar-circle-sm {
    width: 28px; height: 28px; background: #f1f5f9; border-radius: 8px;
    display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; color: #475569;
}
.x-small { font-size: 0.72rem; }
</style>
<?php
$content = ob_get_clean();
require_once 'layout_admin.php';
exit;
