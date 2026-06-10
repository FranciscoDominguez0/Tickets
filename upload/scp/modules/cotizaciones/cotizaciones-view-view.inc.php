<?php
$statusColors = [
    'draft'    => ['bg' => '#f1f5f9', 'color' => '#475569', 'icon' => 'bi-pencil-square',   'label' => 'Borrador'],
    'pending'  => ['bg' => '#f1f5f9', 'color' => '#475569', 'icon' => 'bi-clock-fill',       'label' => 'Pendiente de Solicitud'],
    'requested'=> ['bg' => '#fef9c3', 'color' => '#854d0e', 'icon' => 'bi-send-exclamation', 'label' => 'Solicitada'],
    'answered' => ['bg' => '#dbeafe', 'color' => '#1e40af', 'icon' => 'bi-reply-all-fill',   'label' => 'Esperando Aprobación'],
    'accepted' => ['bg' => '#dcfce7', 'color' => '#166534', 'icon' => 'bi-check-circle-fill', 'label' => 'Aceptada'],
    'rejected' => ['bg' => '#fee2e2', 'color' => '#991b1b', 'icon' => 'bi-x-circle-fill',    'label' => 'Rechazada']
];
$stInfo = $statusColors[$quote['status']] ?? $statusColors['draft'];
$staffInitials = '';
$sn = trim($quote['staff_name'] ?? '');
if ($sn !== '') {
    $parts = explode(' ', $sn);
    $staffInitials = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
}
?>

<div class="ticket-view-wrap">
    <!-- Header -->
    <header class="ticket-view-header">
        <h1 class="ticket-view-title">
            <a href="cotizaciones.php?id=<?php echo $quote['id']; ?>" title="Recargar"><i class="bi bi-arrow-clockwise"></i></a>
            <span>Cotización #<?php echo $quote['id']; ?></span>

        </h1>
        <div class="ticket-view-actions">
            <a href="cotizaciones.php" class="btn-icon" title="Volver"><i class="bi bi-arrow-left"></i></a>
            <?php if (!empty($quote['file_path'])): ?>
                <a href="../../<?php echo html($quote['file_path']); ?>" target="_blank" class="btn-icon" title="Descargar PDF"><i class="bi bi-file-earmark-arrow-down"></i></a>
            <?php endif; ?>
        </div>
    </header>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-3">
            <?php foreach ($errors as $e) echo '<div>' . html($e) . '</div>'; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['flash_msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show mb-3" id="quoteFlashAlert">
            <i class="bi bi-check-circle-fill me-2"></i><?php echo html($_SESSION['flash_msg']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['flash_msg']); ?>
        <script>setTimeout(function(){ var el=document.getElementById('quoteFlashAlert'); if(el){var a=new bootstrap.Alert(el);a.close();}},5000);</script>
    <?php endif; ?>

    <!-- Overview Panel – 2 columnas limpias -->
    <div class="ticket-view-overview">
        <div class="ticket-view-overview-desktop">
            <!-- Col 1: Cotización -->
            <div>
                <div class="field mb-4">
                    <label><i class="bi bi-file-earmark-text"></i> COTIZACIÓN</label>
                    <div class="value title"><?php echo html($quote['title']); ?></div>
                </div>
                <div class="field mb-4">
                    <label><i class="bi bi-flag"></i> ESTADO</label>
                    <div class="value">
                        <span class="badge-status" style="font-weight: 800; font-size: 0.8rem; background: <?php echo $stInfo['bg']; ?>; color: <?php echo $stInfo['color']; ?>; padding: 6px 12px; border-radius: 8px; display: inline-flex; align-items: center; gap: 6px; border: 1px solid <?php echo $stInfo['color']; ?>33;">
                            <i class="bi <?php echo $stInfo['icon']; ?>"></i> <?php echo $stInfo['label']; ?>
                        </span>
                    </div>
                </div>

                <?php if ($quote['ticket_id']): ?>
                <div class="field mb-4">
                    <label><i class="bi bi-ticket-perforated"></i> TICKET ORIGEN</label>
                    <div class="value"><a href="tickets.php?id=<?php echo $quote['ticket_id']; ?>" style="color: #ef4444; font-weight: 700; text-decoration: none;">#<?php echo $quote['ticket_id']; ?></a></div>
                </div>
                <?php endif; ?>
            </div>
            <!-- Col 2: Organización & Agente -->
            <div>
                <div class="field mb-4">
                    <label><i class="bi bi-building"></i> ORGANIZACIÓN</label>
                    <div class="value" style="font-weight: 600;"><?php echo html($quote['org_name'] ?: 'N/A'); ?></div>
                </div>
                <div class="field mb-4">
                    <label><i class="bi bi-headset"></i> AGENTE A CARGO</label>
                    <div class="value" style="display: flex; align-items: center; gap: 8px;">
                        <span style="width: 26px; height: 26px; background: #0f62fe; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; color: #fff; font-size: 0.65rem; font-weight: 800; flex-shrink: 0;"><?php echo $staffInitials; ?></span>
                        <?php echo html($quote['staff_name'] ?: 'Sin asignar'); ?>
                    </div>
                </div>
            </div>
            <!-- Col 3: Estado & Fechas -->
            <div>

                <div class="field mb-4">
                    <label><i class="bi bi-calendar-event"></i> CREADA</label>
                    <div class="value"><?php echo date('d/m/Y h:i A', strtotime($quote['created_at'])); ?></div>
                </div>
                <?php if (!empty($quote['file_path'])): ?>
                <div class="field mb-4">
                    <label><i class="bi bi-file-earmark-pdf"></i> DOCUMENTO</label>
                    <div class="value">
                        <a href="../../<?php echo html($quote['file_path']); ?>" target="_blank" class="btn-waze-premium"><i class="bi bi-download"></i> Descargar PDF</a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <ul class="ticket-view-tabs">
        <li><a class="tab active" href="#"><i class="bi bi-chat-left-text"></i> Hilo <span class="badge bg-secondary" style="font-size: 0.7rem; border-radius: 50rem; margin-left: 4px;"><?php echo count($messages); ?></span></a></li>
    </ul>

    <!-- Thread -->
    <div class="ticket-view-tab-content">
        <?php if (empty($messages)): ?>
            <div class="empty-state">
                <i class="bi bi-chat-square-text" style="font-size: 2.5rem; color: #cbd5e1;"></i>
                <div style="margin-top: 12px; color: #64748b; font-weight: 600;">No hay mensajes aún.</div>
                <div style="color: #94a3b8; font-size: 0.88rem; margin-top: 4px;">Envía el primer mensaje para que el jefe de la organización pueda revisar la cotización.</div>
            </div>
        <?php else: ?>
            <?php foreach ($messages as $m):
                $isStaff = !empty($m['staff_id']);
                $authorName = trim($isStaff ? ($m['staff_name'] ?? '') : ($m['user_name'] ?? ''));
                if ($authorName === '') $authorName = $isStaff ? 'Agente' : 'Cliente';
                $parts = explode(' ', $authorName);
                $initials = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
                $entryClass = $isStaff ? 'staff' : 'user';
            ?>
            <div class="ticket-view-entry <?php echo $entryClass; ?>">
                <div class="entry-row">
                    <div class="entry-avatar"><span class="entry-avatar-inner"><?php echo $initials; ?></span></div>
                    <div class="entry-bubble-wrapper">
                        <div class="entry-header">
                            <span class="author-name"><?php echo html($authorName); ?></span>
                            <span class="author-role"><?php echo $isStaff ? 'Agente' : 'Cliente'; ?></span>
                        </div>
                        <div class="entry-content">

                            <div class="entry-body" style="white-space: pre-wrap;"><?php echo html($m['message']); ?></div>
                            <?php if (!empty($m['file_path'])):
                                $fileName = basename($m['file_path']);
                                $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                                $iconClass = $ext === 'pdf' ? 'bi-file-earmark-pdf-fill' : (in_array($ext, ['doc','docx']) ? 'bi-file-earmark-word-fill' : 'bi-file-earmark');
                                $iconColor = $ext === 'pdf' ? '#dc2626' : '#3b82f6';
                            ?>
                            <div class="chat-att-list">
                                <div class="chat-att-item">
                                    <span class="chat-att-icon" style="color: <?php echo $iconColor; ?>;"><i class="bi <?php echo $iconClass; ?>"></i></span>
                                    <div class="chat-att-info">
                                        <a href="../../<?php echo html($m['file_path']); ?>" target="_blank" class="att-filename"><?php echo html($fileName); ?></a>
                                    </div>
                                    <a href="../../<?php echo html($m['file_path']); ?>" target="_blank" class="chat-att-download" title="Descargar"><i class="bi bi-download"></i></a>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="entry-footer"><i class="bi bi-clock"></i> <?php echo date('d/m/Y h:i A', strtotime($m['created_at'])); ?></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Reply -->
    <div class="ticket-view-reply" style="margin-top: 24px;">
        <form method="POST" action="cotizaciones.php?id=<?php echo $id; ?>" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">
            <input type="hidden" name="action_type" value="post_message">

            <textarea name="message" class="form-control" style="min-height: 140px; border-radius: 12px; resize: vertical;" placeholder="Escribe tu respuesta aquí..." required></textarea>

            <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; margin-top: 16px; padding-top: 16px; border-top: 1px solid #e2e8f0;">
                <div>
                    <label style="font-weight: 600; font-size: 0.85rem; color: #64748b; display: flex; align-items: center; gap: 6px; margin-bottom: 4px;">
                        <i class="bi bi-paperclip"></i> Adjuntar documento
                    </label>
                    <input type="file" name="quote_file" class="form-control form-control-sm" style="border-radius: 10px; max-width: 320px;" accept=".pdf,.doc,.docx">
                </div>
                <button type="submit" class="btn-reply text-white" style="background: linear-gradient(135deg, #ef4444, #dc2626); box-shadow: 0 4px 12px rgba(239, 68, 68, 0.25); border-radius: 50rem; cursor: pointer; border: none; font-weight: 700;">
                    <i class="bi bi-send-fill"></i> Enviar
                </button>
            </div>
        </form>
    </div>
</div>
