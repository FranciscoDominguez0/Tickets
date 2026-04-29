<?php
/**
 * Módulo de Gestión de Tickets Borrados y Solicitudes de Borrado
 */

requireRolePermission('ticket.delete', 'index.php');

$eid = empresaId();
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);

// --- PROCESAR ACCIONES ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id > 0) {
    if (!isset($_POST['csrf_token']) || !Auth::validateCSRF($_POST['csrf_token'])) {
        $_SESSION['flash_error'] = 'Token de seguridad inválido.';
    } else {
        // Obtener datos de la solicitud
        $resReq = $mysqli->query("SELECT * FROM ticket_deletion_requests WHERE id = $id AND empresa_id = $eid");
        $request = $resReq ? $resReq->fetch_assoc() : null;

        if (!$request) {
            $_SESSION['flash_error'] = 'Solicitud no encontrada.';
        } elseif ($request['status'] !== 'pending') {
            $_SESSION['flash_error'] = 'Esta solicitud ya ha sido resuelta.';
        } else {
            if ($action === 'approve_delete') {
                $tid = (int)$request['ticket_id'];
                
                // 1. Borrar el ticket real (misma lógica que el controlador de tickets)
                try {
                    if (method_exists($mysqli, 'begin_transaction')) $mysqli->begin_transaction();

                    // Limpiar threads y entries
                    $mysqli->query("DELETE te FROM thread_entries te JOIN threads th ON th.id = te.thread_id WHERE th.ticket_id = $tid");
                    $mysqli->query("DELETE FROM threads WHERE ticket_id = $tid");
                    
                    // Borrar el ticket
                    $mysqli->query("DELETE FROM tickets WHERE id = $tid AND empresa_id = $eid");

                    // 2. Marcar solicitud como aprobada
                    $stmtUpd = $mysqli->prepare("UPDATE ticket_deletion_requests SET status = 'approved', resolved_at = NOW(), resolved_by = ? WHERE id = ?");
                    $stmtUpd->bind_param('ii', $_SESSION['staff_id'], $id);
                    $stmtUpd->execute();

                    if (method_exists($mysqli, 'commit')) $mysqli->commit();
                    $_SESSION['flash_msg'] = 'Ticket borrado permanentemente y solicitud aprobada.';
                } catch (Throwable $e) {
                    if (method_exists($mysqli, 'rollback')) $mysqli->rollback();
                    $_SESSION['flash_error'] = 'Error al ejecutar el borrado: ' . $e->getMessage();
                }

            } elseif ($action === 'reject_delete') {
                // Simplemente marcar como rechazada
                $stmtUpd = $mysqli->prepare("UPDATE ticket_deletion_requests SET status = 'rejected', resolved_at = NOW(), resolved_by = ? WHERE id = ?");
                $stmtUpd->bind_param('ii', $_SESSION['staff_id'], $id);
                if ($stmtUpd->execute()) {
                    $_SESSION['flash_msg'] = 'Solicitud de borrado rechazada. El ticket se mantiene intacto.';
                } else {
                    $_SESSION['flash_error'] = 'Error al rechazar la solicitud.';
                }
            }
        }
    }
    header('Location: index.php?page=deleted_tickets');
    exit;
}

// --- VISTA ---
// Consultar historial
$sql = "SELECT r.*, CONCAT(s.firstname, ' ', s.lastname) as requester_name, CONCAT(v.firstname, ' ', v.lastname) as resolver_name 
        FROM ticket_deletion_requests r 
        LEFT JOIN staff s ON r.requested_by = s.id 
        LEFT JOIN staff v ON r.resolved_by = v.id 
        WHERE r.empresa_id = $eid 
        ORDER BY r.created_at DESC";
$res = $mysqli->query($sql);
?>

<div class="page-header d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0" style="font-weight: 800; color: #1e293b;">Historial de Borrados</h1>
        <p class="text-muted mb-0">Gestión de solicitudes y registro de tickets eliminados.</p>
    </div>
</div>

<div class="card border-0 shadow-sm" style="border-radius: 16px; overflow: hidden;">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" style="min-width: 900px;">
            <thead style="background: #f8fafc; border-bottom: 2px solid #f1f5f9;">
                <tr>
                    <th class="ps-4 py-3" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b;">Ticket</th>
                    <th class="py-3" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b;">Solicitado por</th>
                    <th class="py-3" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b;">Motivo</th>
                    <th class="py-3" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b;">Estado</th>
                    <th class="py-3" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b;">Resolución</th>
                    <th class="pe-4 py-3 text-end" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($res && $res->num_rows > 0): ?>
                    <?php while ($r = $res->fetch_assoc()): ?>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td class="ps-4 py-4">
                                <div class="d-flex flex-column">
                                    <span style="font-weight: 700; color: #334155; font-size: 0.9rem;">#<?php echo html($r['ticket_number']); ?></span>
                                    <span class="text-muted" style="font-size: 0.8rem; max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                        <?php echo html($r['ticket_subject']); ?>
                                    </span>
                                    <small class="text-muted" style="font-size: 0.7rem;"><?php echo formatDate($r['created_at']); ?></small>
                                </div>
                            </td>
                            <td class="py-4">
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <div style="width:28px; height:28px; border-radius:50%; background:#e2e8f0; display:flex; align-items:center; justify-content:center; font-size:0.7rem; font-weight:700; color:#475569;">
                                        <?php echo strtoupper(substr($r['requester_name'] ?? '?', 0, 1)); ?>
                                    </div>
                                    <span style="font-size: 0.85rem; font-weight: 600; color: #475569;"><?php echo html($r['requester_name'] ?: 'Desconocido'); ?></span>
                                </div>
                            </td>
                            <td class="py-4">
                                <div style="max-width: 300px; font-size: 0.85rem; color: #64748b; line-height: 1.4;">
                                    <?php echo html($r['reason']); ?>
                                </div>
                            </td>
                            <td class="py-4">
                                <?php 
                                $status = $r['status'];
                                $badgeClass = 'bg-secondary';
                                $statusText = 'Pendiente';
                                if ($status === 'approved') { $badgeClass = 'bg-success'; $statusText = 'Borrado'; }
                                if ($status === 'rejected') { $badgeClass = 'bg-danger'; $statusText = 'Rechazado'; }
                                ?>
                                <span class="badge <?php echo $badgeClass; ?> rounded-pill px-3 py-2" style="font-size: 0.7rem; font-weight: 700; letter-spacing: 0.02em;">
                                    <?php echo $statusText; ?>
                                </span>
                            </td>
                            <td class="py-4">
                                <?php if ($r['resolved_at']): ?>
                                    <div class="d-flex flex-column">
                                        <span style="font-size: 0.8rem; font-weight: 600; color: #475569;">Por: <?php echo html($r['resolver_name'] ?: 'Admin'); ?></span>
                                        <small class="text-muted" style="font-size: 0.7rem;"><?php echo formatDate($r['resolved_at']); ?></small>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted italic" style="font-size: 0.8rem; opacity: 0.6;">Sin resolver</span>
                                <?php endif; ?>
                            </td>
                            <td class="pe-4 py-4 text-end">
                                <?php if ($status === 'pending'): ?>
                                    <div class="d-flex justify-content-end gap-2">
                                        <form method="post" onsubmit="return confirm('¿Aprobar y BORRAR el ticket permanentemente?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">
                                            <input type="hidden" name="action" value="approve_delete">
                                            <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-success" title="Aprobar Borrado">
                                                <i class="bi bi-check-lg"></i> Aprobar
                                            </button>
                                        </form>
                                        <form method="post" onsubmit="return confirm('¿Rechazar esta solicitud de borrado?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">
                                            <input type="hidden" name="action" value="reject_delete">
                                            <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Rechazar">
                                                <i class="bi bi-x-lg"></i> Rechazar
                                            </button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted"><i class="bi bi-lock-fill"></i></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="bi bi-trash" style="font-size: 2rem; opacity: 0.2; display: block; margin-bottom: 10px;"></i>
                            No hay solicitudes ni historial de borrados.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
