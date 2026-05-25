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

// --- AJAX: VER HILO DEL TICKET ---
if ($action === 'view_thread' && $id > 0) {
    $stmtT = $mysqli->prepare("
        SELECT te.body, te.created, te.is_internal, 
               CONCAT(s.firstname, ' ', s.lastname) as staff_name, 
               CONCAT(u.firstname, ' ', u.lastname) as user_name, te.staff_id
        FROM thread_entries te
        JOIN threads t ON te.thread_id = t.id
        LEFT JOIN staff s ON te.staff_id = s.id
        LEFT JOIN users u ON te.user_id = u.id
        WHERE t.ticket_id = (SELECT ticket_id FROM ticket_deletion_requests WHERE id = ? AND empresa_id = ?)
        ORDER BY te.created ASC
    ");
    $stmtT->bind_param('ii', $id, $eid);
    $stmtT->execute();
    $resT = $stmtT->get_result();
    
    if ($resT && $resT->num_rows > 0) {
        while ($e = $resT->fetch_assoc()) {
            $isStaff = (int)$e['staff_id'] > 0;
            $isInternal = (int)$e['is_internal'] === 1;
            $name = trim($isStaff ? $e['staff_name'] : $e['user_name']) ?: 'Sistema';
            
            $typeLabel = $isInternal ? 'Nota Interna' : ($isStaff ? 'Respuesta' : 'Mensaje');
            $icon = $isInternal ? 'bi-journal-text text-warning' : ($isStaff ? 'bi-person-badge text-primary' : 'bi-person text-success');
            $typeClass = $isInternal ? 'internal' : ($isStaff ? 'staff' : 'user');
            
            echo "
            <div class='thread-entry-card thread-entry-$typeClass shadow-sm'>
                <div class='thread-header d-flex justify-content-between align-items-center'>
                    <span class='thread-name'><i class='bi $icon me-1'></i>" . html($name) . "</span>
                    <span class='thread-meta'>$typeLabel &bull; " . formatDate($e['created']) . "</span>
                </div>
                <div class='thread-body'>" . (function_exists('sanitizeRichText') ? sanitizeRichText($e['body']) : $e['body']) . "</div>
            </div>";
        }
    } else {
        echo "<div class='text-center py-4 text-muted'><i class='bi bi-chat-left-dots fs-2 d-block mb-2 opacity-50'></i>No hay mensajes registrados en este ticket.</div>";
    }
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
                                <!-- VISTA MÓVIL (Tarjeta Premium) -->
                                <td class="d-md-none p-0">
                                    <div class="mobile-deletion-card">
                                        <!-- Header -->
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="mobile-deletion-icon-box">
                                                    <i class="bi bi-trash3"></i>
                                                </div>
                                                <div style="line-height: 1.2;">
                                                    <a href="javascript:void(0)" onclick="viewTicketThread(<?php echo $r['id']; ?>, '<?php echo html($r['ticket_number']); ?>')" class="mobile-deletion-ticket-link">
                                                        #<?php echo html($r['ticket_number']); ?>
                                                    </a>
                                                    <span style="font-size: 0.68rem; color: var(--text-muted, #64748b); font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">
                                                        <?php echo date('d M Y, H:i', strtotime($r['created_at'])); ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div>
                                                <?php 
                                                $s = $r['status'];
                                                if ($s === 'pending') echo '<span class="badge-del-status pending"><i class="bi bi-clock me-1"></i>Pendiente</span>';
                                                elseif ($s === 'approved') echo '<span class="badge-del-status approved"><i class="bi bi-check-circle-fill me-1"></i>Aprobado</span>';
                                                else echo '<span class="badge-del-status rejected"><i class="bi bi-x-circle-fill me-1"></i>Rechazado</span>';
                                                ?>
                                            </div>
                                        </div>

                                        <!-- Asunto -->
                                        <div class="mobile-deletion-subject">
                                            <?php echo html($r['ticket_subject']); ?>
                                        </div>

                                        <!-- Bloque Solicitante y Motivo -->
                                        <div class="mobile-deletion-meta-block">
                                            <div class="mobile-deletion-meta-header d-flex align-items-center gap-2 mb-3 pb-2">
                                                <div class="avatar-circle-sm mobile-deletion-avatar">
                                                    <?php echo strtoupper(substr($r['requester_name'] ?? '?', 0, 1)); ?>
                                                </div>
                                                <span class="mobile-deletion-requester-text">
                                                    Solicitado por <span class="mobile-deletion-requester-name"><?php echo html($r['requester_name']); ?></span>
                                                </span>
                                            </div>
                                            <div class="mobile-deletion-reason-text">
                                                <div class="mobile-deletion-reason-label">
                                                    Motivo de Eliminación
                                                </div>
                                                <?php echo html($r['reason']); ?>
                                            </div>
                                        </div>

                                        <!-- Footer: Resolución y Botones -->
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div style="display: flex; flex-direction: column;">
                                                <span class="mobile-deletion-resolver-label">
                                                    Resuelto por
                                                </span>
                                                <?php if ($r['resolved_at']): ?>
                                                    <span class="mobile-deletion-resolver-name-val">
                                                        <i class="bi bi-shield-check text-success me-1"></i><?php echo html($r['resolver_name'] ?: 'Admin'); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="mobile-deletion-resolver-pending">
                                                        Pendiente de acción
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div>
                                                <?php if ($s === 'pending'): ?>
                                                    <div class="d-flex gap-2">
                                                        <button type="button" onclick="openResolveModal('reject_delete', <?php echo (int)$r['id']; ?>, '<?php echo html($r['ticket_number']); ?>')" class="mobile-btn-reject">
                                                            <i class="bi bi-x-lg" style="font-size: 1.1rem; font-weight: bold;"></i>
                                                        </button>
                                                        <button type="button" onclick="openResolveModal('approve_delete', <?php echo (int)$r['id']; ?>, '<?php echo html($r['ticket_number']); ?>')" class="mobile-btn-approve">
                                                            <i class="bi bi-check-lg" style="font-size: 1.4rem; font-weight: bold;"></i>
                                                        </button>
                                                    </div>
                                                <?php else: ?>
                                                    <div style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; color: var(--text-muted, #cbd5e1);">
                                                        <i class="bi bi-check2-all" style="font-size: 1.5rem;"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <!-- VISTA ESCRITORIO (Tabla normal) -->
                                <td class="ps-4 d-none d-md-table-cell">
                                    <a href="javascript:void(0)" onclick="viewTicketThread(<?php echo $r['id']; ?>, '<?php echo html($r['ticket_number']); ?>')" class="ticket-link d-block">
                                        <div class="fw-bold" style="color: #2563eb;">#<?php echo html($r['ticket_number']); ?></div>
                                    </a>
                                    <div class="small text-muted text-truncate" style="max-width: 180px; font-weight: 600;"><?php echo html($r['ticket_subject']); ?></div>
                                    <div class="x-small text-muted"><?php echo formatDate($r['created_at']); ?></div>
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="avatar-circle-sm"><?php echo strtoupper(substr($r['requester_name'] ?? '?', 0, 1)); ?></div>
                                        <span class="small fw-bold"><?php echo html($r['requester_name']); ?></span>
                                    </div>
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <div class="small text-wrap" style="max-width: 100%; font-weight: 500;"><?php echo html($r['reason']); ?></div>
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <?php 
                                    if ($s === 'pending') echo '<span class="badge-del-status pending"><i class="bi bi-clock me-1"></i>Pendiente</span>';
                                    elseif ($s === 'approved') echo '<span class="badge-del-status approved"><i class="bi bi-check-circle-fill me-1"></i>Aprobado</span>';
                                    else echo '<span class="badge-del-status rejected"><i class="bi bi-x-circle-fill me-1"></i>Rechazado</span>';
                                    ?>
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <?php if ($r['resolved_at']): ?>
                                        <div class="small">
                                            <div class="fw-bold"><?php echo html($r['resolver_name'] ?: 'Admin'); ?></div>
                                            <div class="text-muted x-small"><?php echo formatDate($r['resolved_at']); ?></div>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted small">---</span>
                                    <?php endif; ?>
                                </td>
                                <td class="pe-4 text-end d-none d-md-table-cell">
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
        <form method="post" class="modal-content border-0 shadow-lg overflow-hidden" style="border-radius: 16px;">
            <?php csrfField(); ?>
            <input type="hidden" name="id" id="modalId">
            <input type="hidden" name="action" id="modalAction">
            
            <div class="modal-header py-3 px-4">
                <div class="d-flex align-items-center gap-3">
                    <div id="modalIconBox" style="width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; transition: all 0.3s ease;">
                        <i class="bi bi-shield-check" id="modalMainIcon"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="modalTitle" style="font-size: 1.1rem; font-weight: 700;">Resolver Solicitud</h5>
                        <div class="text-muted small fw-semibold" id="modalTicketText" style="font-weight: 600;">Ticket #000000</div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-4 pt-4 text-center">
                <h4 class="fw-bold mb-2" id="modalBodyText" style="font-size: 1.2rem; font-weight: 700;">¿Deseas continuar?</h4>
                <p class="px-2 mb-0" style="font-size: 0.92rem;">Esta acción es definitiva y quedará registrada en el historial de auditoría de la empresa.</p>
                
                <div class="alert mt-4 mb-0 d-none" id="modalWarning" style="border-radius: 12px; text-align: left;">
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
                <button type="button" class="btn btn-outline-secondary px-4 py-2" data-bs-dismiss="modal" style="border-radius: 10px; font-size: 0.88rem; font-weight: 700;">Cancelar</button>
                <button type="submit" class="btn px-4 py-2 shadow-sm text-white" id="modalSubmitBtn" style="border-radius: 10px; font-size: 0.88rem; font-weight: 700; border: none;">Confirmar Acción</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal de Hilo del Ticket -->
<div class="modal fade" id="threadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px; overflow: hidden;">
            <div class="modal-header py-3 px-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-2 rounded-3" style="background: #eff6ff; color: #2563eb; width: 42px; height: 42px; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-chat-dots-fill"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold" style="font-weight: 700; margin-bottom: 0;">Conversación del Ticket</h5>
                        <p class="text-muted small mb-0" style="font-weight: 600;">Auditoría previa &bull; #<span id="threadTicketNum"></span></p>
                    </div>
                </div>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4 py-4">
                <div id="threadBodyWrap">
                    <div id="threadContent">
                        <div class="text-center py-5">
                            <div class="spinner-border text-primary" role="status" style="width: 2rem; height: 2rem;"></div>
                            <p class="mt-2 text-muted small fw-bold">Recuperando mensajes...</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer py-3 px-4">
                <button type="button" class="btn btn-outline-secondary fw-bold" data-bs-dismiss="modal" style="border-radius: 10px; padding: 8px 24px; font-size: 0.9rem;">Cerrar Vista</button>
            </div>
        </div>
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

function viewTicketThread(id, ticketNum) {
    const modalEl = document.getElementById('threadModal');
    const modal = new bootstrap.Modal(modalEl);
    document.getElementById('threadTicketNum').textContent = ticketNum;
    document.getElementById('threadContent').innerHTML = `
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="mt-2 text-muted small fw-bold">Cargando conversación...</p>
        </div>`;
    
    modal.show();
    
    fetch('deleted_tickets.php?action=view_thread&id=' + id)
        .then(response => response.text())
        .then(html => {
            document.getElementById('threadContent').innerHTML = html;
        })
        .catch(err => {
            document.getElementById('threadContent').innerHTML = '<div class="alert alert-danger">Error al cargar el hilo de conversación.</div>';
        });
}
</script>

<style>
/* ── Variables & Default Light System ── */
:root {
    --del-bg-main: #f8fafc;
    --del-card-bg: #ffffff;
    --del-card-border: #e2e8f0;
    --del-text-main: #0f172a;
    --del-text-muted: #64748b;
    --del-table-header: #f8fafc;
    --del-row-hover: #f8fafc;
    
    --modal-content-bg: #ffffff;
    --modal-content-border: rgba(0, 0, 0, 0.1);
    --modal-header-bg: #f8fafc;
    --modal-body-bg: #f8fafc;
    --modal-footer-bg: #ffffff;
    
    --thread-internal-bg: #fffbeb;
    --thread-internal-border: #fde68a;
    --thread-staff-bg: #f8fafc;
    --thread-staff-border: #e2e8f0;
    --thread-user-bg: #ffffff;
    --thread-user-border: #e2e8f0;
    --thread-name-color: #1e293b;
    --thread-body-color: #334155;
    --thread-border-color: rgba(0, 0, 0, 0.05);
}

/* ── Dark Mode Variable Overrides ── */
body.dark-mode {
    --del-bg-main: #0a0a0a;
    --del-card-bg: #111111;
    --del-card-border: #2a2a2a;
    --del-text-main: #e5e5e5;
    --del-text-muted: #888888;
    --del-table-header: #161616;
    --del-row-hover: #1a1a1a;
    
    --modal-content-bg: #111111;
    --modal-content-border: #2a2a2a;
    --modal-header-bg: #111111;
    --modal-body-bg: #161616;
    --modal-footer-bg: #111111;
    
    --thread-internal-bg: #201a15;
    --thread-internal-border: #3b2f1f;
    --thread-staff-bg: #161616;
    --thread-staff-border: #2a2a2a;
    --thread-user-bg: #111111;
    --thread-user-border: #2a2a2a;
    --thread-name-color: #f1f5f9;
    --thread-body-color: #cbd5e1;
    --thread-border-color: rgba(255, 255, 255, 0.05);
}

/* ── Global Styles ── */
.modal-backdrop.show { opacity: 0.45; background-color: #0f172a; }
.avatar-circle-sm {
    width: 28px; height: 28px; background: var(--del-row-hover); border-radius: 8px;
    display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; color: var(--del-text-main);
}
.x-small { font-size: 0.72rem; }

.ticket-link { text-decoration: none; transition: all 0.2s ease; border-radius: 6px; padding: 2px 4px; margin-left: -4px; display: inline-block; }
.ticket-link:hover { background: #eff6ff; color: #1e40af !important; text-decoration: none !important; }
body.dark-mode .ticket-link:hover { background: #1e3a8a; color: #3b82f6 !important; }

#threadBodyWrap { max-height: 550px; overflow-y: auto; padding-right: 12px; }
#threadBodyWrap::-webkit-scrollbar { width: 6px; }
#threadBodyWrap::-webkit-scrollbar-thumb { background: var(--del-card-border); border-radius: 10px; }
#threadBodyWrap::-webkit-scrollbar-thumb:hover { background: var(--del-text-muted); }

/* ── Badges ── */
.badge-del-status {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 800;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    border: 1px solid transparent;
}
.badge-del-status.pending {
    background: #fffbeb;
    color: #b45309;
    border-color: #fde68a;
}
.badge-del-status.approved {
    background: #f0fdf4;
    color: #15803d;
    border-color: #bbf7d0;
}
.badge-del-status.rejected {
    background: #fef2f2;
    color: #b91c1c;
    border-color: #fee2e2;
}

body.dark-mode .badge-del-status.pending {
    background: rgba(217, 119, 6, 0.15);
    color: #fbbf24;
    border-color: rgba(217, 119, 6, 0.3);
}
body.dark-mode .badge-del-status.approved {
    background: rgba(16, 185, 129, 0.15);
    color: #34d399;
    border-color: rgba(16, 185, 129, 0.3);
}
body.dark-mode .badge-del-status.rejected {
    background: rgba(239, 68, 68, 0.15);
    color: #f87171;
    border-color: rgba(239, 68, 68, 0.3);
}

/* ── Thread entries ── */
.thread-entry-card {
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 16px;
    border: 1px solid var(--del-card-border);
}
.thread-entry-card.thread-entry-internal {
    background: var(--thread-internal-bg);
    border-color: var(--thread-internal-border) !important;
}
.thread-entry-card.thread-entry-staff {
    background: var(--thread-staff-bg);
    border-color: var(--thread-staff-border) !important;
}
.thread-entry-card.thread-entry-user {
    background: var(--thread-user-bg);
    border-color: var(--thread-user-border) !important;
}
.thread-entry-card .thread-header {
    border-bottom: 1px solid var(--thread-border-color);
    padding-bottom: 8px;
    margin-bottom: 8px;
}
.thread-entry-card .thread-name {
    font-weight: 700;
    color: var(--thread-name-color);
}
.thread-entry-card .thread-meta {
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--del-text-muted);
}
.thread-entry-card .thread-body {
    font-size: 0.88rem;
    color: var(--thread-body-color);
    line-height: 1.5;
}

/* ── Modern Layout Custom Styles (Dark Mode Support) ── */
.settings-card {
    background: var(--del-card-bg) !important;
    border: 1px solid var(--del-card-border) !important;
}
.settings-card .card-header {
    background: var(--del-card-bg) !important;
    border-bottom: 1px solid var(--del-card-border) !important;
    color: var(--del-text-main);
}
.settings-card .card-header strong {
    color: var(--del-text-main);
}
.table {
    color: var(--del-text-main) !important;
}
.table thead.table-light,
.table thead {
    background: var(--del-table-header) !important;
}
.table thead th {
    background: var(--del-table-header) !important;
    color: var(--del-text-muted) !important;
    border-bottom: 1px solid var(--del-card-border) !important;
}
.table tbody tr:hover td {
    background: var(--del-row-hover) !important;
}
.table td {
    border-bottom: 1px solid var(--del-card-border) !important;
    color: var(--del-text-main) !important;
}
.table td a .fw-bold {
    color: #2563eb;
}
body.dark-mode .table td a .fw-bold {
    color: #3b82f6;
}

.pagination .page-link {
    background: var(--del-card-bg) !important;
    color: var(--del-text-muted) !important;
    border-color: var(--del-card-border) !important;
}
.pagination .page-item.active .page-link {
    background: #2563eb !important;
    color: #ffffff !important;
}

/* ── Modal Dark Mode ── */
.modal-content {
    background: var(--modal-content-bg) !important;
    border: 1px solid var(--modal-content-border) !important;
}
.modal-header {
    background: var(--modal-header-bg) !important;
    border-bottom: 1px solid var(--del-card-border) !important;
}
.modal-header .modal-title {
    color: var(--del-text-main) !important;
}
.modal-body {
    background: var(--modal-body-bg) !important;
    color: var(--del-text-main) !important;
}
.modal-body p {
    color: var(--del-text-muted) !important;
}
.modal-footer {
    background: var(--modal-footer-bg) !important;
    border-top: 1px solid var(--del-card-border) !important;
}
.modal-footer button.btn-outline-secondary {
    border-color: var(--del-card-border) !important;
    color: var(--del-text-muted) !important;
}
.modal-footer button.btn-outline-secondary:hover {
    background: var(--del-row-hover) !important;
}
body.dark-mode .modal-header .btn-close {
    filter: invert(1);
}
body.dark-mode #modalWarning {
    background: rgba(239, 68, 68, 0.1) !important;
    border-color: rgba(239, 68, 68, 0.2) !important;
}
body.dark-mode #modalWarning .text-danger {
    color: #f87171 !important;
}
body.dark-mode .settings-hero-icon {
    background: #1e1e1e !important;
    color: #e5e5e5 !important;
}

/* ── Mobile Layout CSS & Dark Mode Support ── */
.mobile-deletion-card {
    padding: 16px;
    background: var(--del-card-bg);
}
.mobile-deletion-icon-box {
    background: rgba(37,99,235,0.08); 
    color: #2563eb; 
    width: 40px; 
    height: 40px; 
    border-radius: 12px; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    font-size: 1.2rem;
}
body.dark-mode .mobile-deletion-icon-box {
    background: rgba(59, 130, 246, 0.15);
    color: #3b82f6;
}
.mobile-deletion-ticket-link {
    text-decoration: none;
    font-weight: 800; 
    color: var(--del-text-main); 
    font-size: 1.1rem; 
    display: block;
}
.mobile-deletion-ticket-link:hover {
    color: #2563eb;
}
.mobile-deletion-subject {
    font-size: 0.95rem; 
    font-weight: 700; 
    color: var(--del-text-main); 
    margin-bottom: 14px; 
    line-height: 1.4;
}
.mobile-deletion-meta-block {
    background: var(--del-bg-main); 
    border: 1px solid var(--del-card-border); 
    border-radius: 14px; 
    padding: 14px; 
    margin-bottom: 16px;
}
.mobile-deletion-meta-header {
    border-bottom: 1px dashed var(--del-card-border);
}
.mobile-deletion-avatar {
    width: 22px; 
    height: 22px; 
    font-size: 10px; 
    background: var(--del-card-border); 
    color: var(--del-text-main);
}
.mobile-deletion-requester-text {
    font-size: 0.8rem; 
    font-weight: 600; 
    color: var(--del-text-muted);
}
.mobile-deletion-requester-name {
    color: var(--del-text-main); 
    font-weight: 700;
}
.mobile-deletion-reason-text {
    font-size: 0.85rem; 
    color: var(--del-text-main); 
    font-weight: 500; 
    line-height: 1.5;
}
.mobile-deletion-reason-label {
    color: var(--del-text-muted); 
    font-weight: 800; 
    font-size: 0.68rem; 
    text-transform: uppercase; 
    margin-bottom: 4px; 
    letter-spacing: 0.5px;
}
.mobile-deletion-resolver-label {
    font-size: 0.68rem; 
    color: var(--del-text-muted); 
    font-weight: 800; 
    text-transform: uppercase; 
    letter-spacing: 0.5px; 
    margin-bottom: 2px;
}
.mobile-deletion-resolver-name-val {
    font-size: 0.85rem; 
    font-weight: 800; 
    color: var(--del-text-main);
}
.mobile-deletion-resolver-pending {
    font-size: 0.85rem; 
    font-weight: 700; 
    color: var(--del-text-muted);
}
.mobile-btn-reject {
    background: var(--del-card-bg); 
    border: 1px solid rgba(239, 68, 68, 0.4); 
    color: #dc2626; 
    border-radius: 10px; 
    width: 40px; 
    height: 40px; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    cursor: pointer; 
    transition: all 0.2s; 
    box-shadow: 0 2px 4px rgba(220,38,38,0.1);
}
.mobile-btn-reject:hover {
    background: rgba(239, 68, 68, 0.1);
}
.mobile-btn-approve {
    background: #10b981; 
    border: none; 
    color: #ffffff; 
    border-radius: 10px; 
    width: 40px; 
    height: 40px; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    cursor: pointer; 
    transition: all 0.2s; 
    box-shadow: 0 4px 10px rgba(16,185,129,0.3);
}
.mobile-btn-approve:hover {
    background: #059669;
}

@media (max-width: 768px) {
    .settings-card { background: transparent !important; box-shadow: none !important; border: none !important; }
    .settings-card .card-header { border-radius: 12px; margin-bottom: 12px; border: 1px solid var(--del-card-border) !important; }
    .table-responsive { border: none !important; overflow: visible; }
    .table { background: transparent; }
    .table thead { display: none; }
    .table tbody tr {
        display: block;
        margin-bottom: 1rem;
        background: var(--del-card-bg);
        border: 1px solid var(--del-card-border);
        border-radius: 16px;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        overflow: hidden;
    }
    .table tbody td {
        display: block;
        border: none !important;
        padding: 0 !important;
    }
}
</style>
<?php
$content = ob_get_clean();
require_once 'layout_admin.php';
exit;
