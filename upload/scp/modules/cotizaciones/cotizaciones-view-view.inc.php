<?php
$statusColors = [
    'draft'    => ['bg' => '#f1f5f9', 'color' => '#475569', 'icon' => 'bi-pencil-square',   'label' => 'Borrador'],
    'pending'  => ['bg' => '#f1f5f9', 'color' => '#475569', 'icon' => 'bi-clock-fill',       'label' => 'Pendiente de Solicitud'],
    'requested'=> ['bg' => '#fef9c3', 'color' => '#854d0e', 'icon' => 'bi-send-exclamation', 'label' => 'Solicitada'],
    'answered' => ['bg' => '#dbeafe', 'color' => '#1e40af', 'icon' => 'bi-reply-all-fill',   'label' => 'Esperando Aprobación'],
    'waiting_oc'=> ['bg' => '#fef3c7', 'color' => '#b45309', 'icon' => 'bi-file-earmark-text-fill',  'label' => 'En espera O/C'],
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
            <?php if ($quote['status'] !== 'waiting_oc'): ?>
            <button type="button" class="btn-icon" style="color: #dc2626;" title="Poner en espera O/C manualmente" data-bs-toggle="modal" data-bs-target="#modalWaitingOC">
                <i class="bi bi-file-earmark-text-fill"></i>
            </button>
            <?php endif; ?>
            <a href="print_cotizacion.php?id=<?php echo $quote['id']; ?>" target="_blank" class="btn-icon" title="Imprimir Cotización"><i class="bi bi-printer"></i></a>
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
        <!-- DISEÑO MÓVIL (Visible solo en pantallas pequeñas) -->
        <div class="d-md-none">
            <!-- Header: Estado -->
            <div class="mobile-header">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div class="mobile-badge" style="background: <?php echo $stInfo['bg']; ?>; color: <?php echo $stInfo['color']; ?>; border: 1px solid <?php echo $stInfo['color']; ?>33;">
                        <span class="dot" style="background: <?php echo $stInfo['color']; ?>;"></span>
                        <?php echo $stInfo['label']; ?>
                    </div>
                </div>
            </div>

            <!-- Sección Organización y Usuario -->
            <div class="mobile-user-section">
                <?php
                    $mobileOrgName = trim($quote['org_name'] ?: 'N/A');
                    $mobileInitialsOrg = strtoupper(substr($mobileOrgName, 0, 2));
                    if(strlen($mobileOrgName) > 0 && strpos($mobileOrgName, ' ') !== false) {
                        $parts = explode(' ', $mobileOrgName);
                        $mobileInitialsOrg = strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
                    }
                ?>
                <div class="mobile-avatar" style="font-size: 1rem; font-weight: 900; letter-spacing: 0.04em; background: #eff6ff; color: #2563eb;">
                    <?php echo html($mobileInitialsOrg); ?>
                </div>
                <div class="mobile-user-info">
                    <div class="name"><?php echo html($mobileOrgName); ?></div>
                    <div class="sub">
                        <i class="bi bi-shop"></i> Sucursal: <?php echo html($quote['sucursal'] ?? 'Principal'); ?>
                    </div>
                    <?php if (!empty($quote['org_boss_name'])): ?>
                    <div class="sub mt-1">
                        <i class="bi bi-person-badge"></i> Encargado: <span style="font-weight:600;"><a href="users.php?id=<?php echo $quote['org_boss_id']; ?>" style="color: inherit; text-decoration: none;"><?php echo html($quote['org_boss_name']); ?></a></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Grilla Inferior -->
            <div class="mobile-grid">
                <div class="mobile-grid-item" style="grid-column: 1 / -1;">
                    <label><i class="bi bi-file-earmark-text"></i> COTIZACIÓN</label>
                    <div class="val"><?php echo html($quote['title']); ?></div>
                </div>
                <div class="mobile-grid-item">
                    <label><i class="bi bi-calendar-event"></i> CREADA</label>
                    <div class="val"><?php echo date('d/m/y h:i A', strtotime($quote['created_at'])); ?></div>
                </div>
                <?php if ($quote['ticket_id']): ?>
                <div class="mobile-grid-item">
                    <label><i class="bi bi-ticket-perforated"></i> TICKET ORIGEN</label>
                    <div class="val"><a href="tickets.php?id=<?php echo $quote['ticket_id']; ?>" style="color: #ef4444; font-weight: 700; text-decoration: none;">#<?php echo $quote['ticket_id']; ?></a></div>
                </div>
                <?php endif; ?>
                <?php if (!empty($quote['file_path'])): ?>
                <div class="mobile-grid-item">
                    <label><i class="bi bi-file-earmark-pdf"></i> DOCUMENTO</label>
                    <div class="val"><a href="../../<?php echo html($quote['file_path']); ?>" target="_blank" style="color: #3b82f6; font-weight: 700; text-decoration: none;"><i class="bi bi-download"></i> Descargar PDF</a></div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- DISEÑO DESKTOP (Visible en pantallas medianas y grandes) -->
        <div class="d-none d-md-grid ticket-view-overview-desktop">
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
            <!-- Col 2: Organización & Sucursal -->
            <div>
                <div class="field mb-4">
                    <label><i class="bi bi-building"></i> ORGANIZACIÓN</label>
                    <div class="value" style="font-weight: 600;"><?php echo html($quote['org_name'] ?: 'N/A'); ?></div>
                </div>
                <div class="field mb-4">
                    <label><i class="bi bi-shop"></i> SUCURSAL</label>
                    <div class="value" style="font-weight: 600;"><?php echo html($quote['sucursal'] ?? 'Principal'); ?></div>
                </div>
            </div>
            <!-- Col 3: Estado & Fechas -->
            <div>

                <div class="field mb-4">
                    <label><i class="bi bi-calendar-event"></i> CREADA</label>
                    <div class="value"><?php echo date('d/m/Y h:i A', strtotime($quote['created_at'])); ?></div>
                </div>
                <?php if (!empty($quote['org_boss_name'])): ?>
                <div class="field mb-4">
                    <label><i class="bi bi-person-badge"></i> ENCARGADO DE ORG.</label>
                    <div class="value" style="font-weight: 600;"><a href="users.php?id=<?php echo $quote['org_boss_id']; ?>" style="color: inherit; text-decoration: none;"><?php echo html($quote['org_boss_name']); ?></a></div>
                </div>
                <?php endif; ?>
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
                                
                                $previewType = '';
                                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) $previewType = 'image';
                                elseif ($ext === 'pdf') $previewType = 'pdf';
                                elseif ($ext === 'docx') $previewType = 'docx';
                                elseif (in_array($ext, ['mp4', 'webm', 'ogg'])) $previewType = 'video';
                                
                                $previewUrl = "../../" . $m['file_path'];
                            ?>
                            <div class="chat-att-list">
                                <div class="chat-att-item">
                                    <span class="chat-att-icon" style="color: <?php echo $iconColor; ?>;"><i class="bi <?php echo $iconClass; ?>"></i></span>
                                    <div class="chat-att-info">
                                        <a href="../../<?php echo html($m['file_path']); ?>" target="_blank" 
                                           class="att-filename <?php echo $previewType ? 'att-preview-trigger' : ''; ?>" 
                                           data-preview-url="<?php echo html($previewUrl); ?>" 
                                           data-preview-type="<?php echo $previewType; ?>">
                                            <?php echo html($fileName); ?>
                                        </a>
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

    <!-- Modal Poner en espera O/C -->
    <?php if ($quote['status'] !== 'waiting_oc'): ?>
    <div class="modal fade" id="modalWaitingOC" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
                <div class="modal-header bg-light border-bottom-0" style="border-radius: 16px 16px 0 0;">
                    <h5 class="modal-title fw-bold" style="color: #dc2626;">
                        <i class="bi bi-file-earmark-text-fill me-2"></i> Poner en espera O/C
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 text-center">
                    <div class="mb-3">
                        <i class="bi bi-file-earmark-text-fill" style="font-size: 3rem; color: #dc2626;"></i>
                    </div>
                    <p class="mb-0 text-muted" style="font-size: 1.05rem;">
                        ¿Estás seguro que deseas marcar esta cotización manualmente como <br><strong>En espera de Orden de Compra</strong>?
                    </p>
                </div>
                <div class="modal-footer border-top-0 d-flex justify-content-between p-3 bg-light" style="border-radius: 0 0 16px 16px;">
                    <button type="button" class="btn btn-light border" style="border-radius: 8px; font-weight: 600;" data-bs-dismiss="modal">Cancelar</button>
                    <form method="POST" action="cotizaciones.php?id=<?php echo $quote['id']; ?>" style="margin:0;">
                        <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">
                        <input type="hidden" name="action_type" value="set_waiting_oc">
                        <button type="submit" class="btn text-white" style="background-color: #dc2626; border-radius: 8px; font-weight: 600;">Sí, poner en espera</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Reply -->
    <div class="ticket-view-reply" style="margin-top: 24px;">
        <?php
        // Generar idempotency key si no existe
        if (empty($_SESSION['quote_idem_key_' . $id])) {
            $_SESSION['quote_idem_key_' . $id] = bin2hex(random_bytes(16));
        }
        $idemKey = $_SESSION['quote_idem_key_' . $id];
        ?>
        <form method="POST" action="cotizaciones.php?id=<?php echo $id; ?>" enctype="multipart/form-data" id="quoteReplyForm">
            <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">
            <input type="hidden" name="action_type" value="post_message">
            <input type="hidden" name="idem_key" id="idemKeyInput" value="<?php echo html($idemKey); ?>">

            <textarea name="message" class="form-control" style="min-height: 140px; border-radius: 12px; resize: vertical;" placeholder="Escribe tu respuesta aquí..." required></textarea>

            <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; margin-top: 16px; padding-top: 16px; border-top: 1px solid #e2e8f0;">
                <div style="flex: 1; min-width: 250px;">
                    <div class="attach-zone" id="attach-zone" data-action="attachments-browse">
                        <input type="file" name="quote_file" id="attachments" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" <?php echo $quote['status'] === 'requested' ? 'required' : ''; ?>>
                        <div class="dz-icon"><i class="bi bi-paperclip"></i></div>
                        <div class="attach-text">
                            Arrastra o <a href="#" data-action="attachments-browse">selecciona un archivo</a>
                            <?php if ($quote['status'] === 'requested'): ?>
                                <span class="text-danger fw-bold">* (Obligatorio)</span>
                            <?php endif; ?>
                        </div>
                        <div class="attach-hint">PDF, DOC, JPG, PNG (Máx. 10MB)</div>
                        <div class="attach-list" id="attach-list"></div>
                    </div>
                </div>
                <button type="submit" id="quoteSubmitBtn" class="btn-reply text-white" style="background: linear-gradient(135deg, #ef4444, #dc2626); box-shadow: 0 4px 12px rgba(239, 68, 68, 0.25); border-radius: 50rem; cursor: pointer; border: none; font-weight: 700; height: max-content; padding: 10px 24px;">
                    <i class="bi bi-send-fill" id="quoteSubmitIcon"></i>
                    <span id="quoteSubmitText"> Enviar</span>
                    <span id="quoteSubmitSpinner" class="spinner-border spinner-border-sm ms-1" style="display:none;"></span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Bloqueo de formulario al submit (previene doble envío)
(function() {
    var form = document.getElementById('quoteReplyForm');
    var btn  = document.getElementById('quoteSubmitBtn');
    var icon = document.getElementById('quoteSubmitIcon');
    var txt  = document.getElementById('quoteSubmitText');
    var spin = document.getElementById('quoteSubmitSpinner');
    if (!form || !btn) return;

    form.addEventListener('submit', function() {
        btn.disabled = true;
        btn.style.opacity = '0.7';
        btn.style.cursor  = 'not-allowed';
        if (icon) icon.style.display = 'none';
        if (txt)  txt.textContent  = ' Enviando...';
        if (spin) spin.style.display = 'inline-block';
    });
})();
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var zone = document.getElementById('attach-zone');
    var input = document.getElementById('attachments');
    var list = document.getElementById('attach-list');

    if (zone && input) {
        zone.addEventListener('click', function (e) {
            var t = e.target;
            if (t && t.tagName && t.tagName.toLowerCase() === 'a') e.preventDefault();
            if (t.closest('.dz-preview-remove')) return;
            try {
                if (typeof input.showPicker === 'function') input.showPicker();
                else input.click();
            } catch (err) { input.click(); }
        });

        zone.addEventListener('dragover', function(e) { e.preventDefault(); zone.classList.add('dragover'); });
        zone.addEventListener('dragleave', function(e) { e.preventDefault(); zone.classList.remove('dragover'); });
        zone.addEventListener('drop', function(e) {
            e.preventDefault();
            zone.classList.remove('dragover');
            if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) {
                input.files = e.dataTransfer.files;
                updateList();
            }
        });
        input.addEventListener('change', function() { updateList(); });
    }

    function humanSize(bytes) {
        if (!bytes) return '0 B';
        var units = ['B', 'KB', 'MB', 'GB'];
        var i = Math.floor(Math.log(bytes) / Math.log(1024));
        i = Math.min(i, units.length - 1);
        return (bytes / Math.pow(1024, i)).toFixed(i === 0 ? 0 : 1) + ' ' + units[i];
    }

    function updateList() {
        if (list) list.innerHTML = '';
        if (input && input.files.length) {
            var file = input.files[0];
            var ext = file.name.split('.').pop().toLowerCase();
            var iconHtml = '<i class="bi bi-file-earmark-text"></i>';
            if (['pdf'].includes(ext)) { iconHtml = '<i class="bi bi-file-earmark-pdf-fill" style="color: #ef4444;"></i>'; } 
            else if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) { iconHtml = '<i class="bi bi-file-earmark-image" style="color: #3b82f6;"></i>'; } 
            else if (['doc', 'docx'].includes(ext)) { iconHtml = '<i class="bi bi-file-earmark-word-fill" style="color: #0ea5e9;"></i>'; }

            var card = document.createElement('div');
            card.className = 'dz-preview-card';
            card.innerHTML = 
                '<div class="dz-preview-icon" id="preview-icon-0">' + iconHtml + '</div>' +
                '<div class="dz-preview-info">' +
                    '<div class="dz-preview-name" title="'+file.name+'">' + file.name + '</div>' +
                    '<div class="dz-preview-size">' + humanSize(file.size) + '</div>' +
                '</div>' +
                '<button type="button" class="dz-preview-remove" data-remove-index="0">Quitar</button>';
            
            list.appendChild(card);

            if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    var iconDiv = document.getElementById('preview-icon-0');
                    if (iconDiv) { iconDiv.innerHTML = '<img src="' + e.target.result + '" alt="preview">'; }
                };
                reader.readAsDataURL(file);
            }
        }
    }

    if (list) {
        list.addEventListener('click', function(e) {
            var btn = e.target.closest('.dz-preview-remove');
            if (btn) {
                e.preventDefault();
                e.stopPropagation();
                var dt = new DataTransfer();
                input.files = dt.files;
                updateList();
            }
        });
    }
});
</script>

<style>
/* Image Preview Styles */
.att-image-preview-container {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    z-index: 10000;
    pointer-events: auto;
    display: none;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
    padding: 8px;
    max-width: min(90vw, 520px);
    animation: attFadeIn 0.2s ease-out forwards;
}
@media (max-width: 768px) {
    .att-image-preview-container { margin-top: -20px; }
}
.att-image-preview-container img {
    max-width: 100%;
    max-height: 350px;
    display: block;
    border-radius: 8px;
    object-fit: contain;
}
@keyframes attFadeIn {
    from { opacity: 0; transform: translate(-50%, -50%) scale(0.95); }
    to { opacity: 1; transform: translate(-50%, -50%) scale(1); }
}
.att-image-preview-container .preview-content-docx {
    padding: 15px;
    font-size: 13px;
    line-height: 1.5;
    color: #334155;
    background: #fdfdfd;
    max-height: 400px;
    overflow-y: auto;
}
.att-image-preview-container .preview-loading {
    padding: 20px;
    text-align: center;
    color: #64748b;
    font-size: 12px;
}
.att-image-preview-container .preview-error {
    padding: 15px;
    color: #ef4444;
    font-size: 12px;
    text-align: center;
}
.att-preview-close {
    position: absolute;
    top: -12px;
    right: -12px;
    width: 30px;
    height: 30px;
    background: #1e293b;
    color: #fff;
    border: 2px solid #fff;
    border-radius: 50%;
    display: none;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    z-index: 10001;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    padding: 0;
    line-height: 1;
}
.att-preview-close i { font-size: 16px; pointer-events: none; }
@media (max-width: 768px) {
    .att-preview-close { display: flex; }
    .att-image-preview-container {
        width: 94vw !important;
        max-width: 94vw !important;
        padding: 6px;
    }
    .att-image-preview-container img {
        max-height: 70vh;
    }
}
</style>
<script src="https://cdnjs.cloudflare.com/ajax/libs/mammoth/1.6.0/mammoth.browser.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Image Preview Logic
    (function() {
        var previewContainer = document.createElement('div');
        previewContainer.className = 'att-image-preview-container';
        
        var closeBtn = document.createElement('button');
        closeBtn.className = 'att-preview-close';
        closeBtn.innerHTML = '<i class="bi bi-x-lg"></i>';
        closeBtn.type = 'button';
        closeBtn.onclick = function(e) {
            e.stopPropagation();
            previewContainer.style.display = 'none';
            activeUrl = '';
        };
        previewContainer.appendChild(closeBtn);

        document.body.appendChild(previewContainer);

        var triggers = document.querySelectorAll('.att-preview-trigger');
        var hideTimeout = null;
        var activeUrl = '';
        var isMobile = window.innerWidth <= 768;

        function showPreview(el, e) {
            clearTimeout(hideTimeout);
            var url = el.getAttribute('data-preview-url');
            var type = el.getAttribute('data-preview-type');
            if (!url) return;
            
            if (activeUrl === url && previewContainer.style.display === 'block') return;

            activeUrl = url;
            previewContainer.innerHTML = '';
            previewContainer.appendChild(closeBtn);
            previewContainer.style.display = 'block';
            
            if (type === 'image') {
                var img = document.createElement('img');
                img.src = url;
                previewContainer.appendChild(img);
            } else if (type === 'pdf') {
                var iframe = document.createElement('iframe');
                iframe.src = url + '#toolbar=0&navpanes=0&scrollbar=1';
                iframe.style.width = isMobile ? '100%' : '500px';
                iframe.style.height = isMobile ? '60vh' : '380px';
                iframe.style.border = 'none';
                iframe.style.borderRadius = '8px';
                if (!isMobile) previewContainer.style.maxWidth = '520px';
                previewContainer.appendChild(iframe);
            } else if (type === 'docx') {
                var loader = document.createElement('div');
                loader.className = 'preview-loading';
                loader.innerHTML = '<div class="spinner-border spinner-border-sm text-primary me-2"></div> Cargando documento...';
                previewContainer.appendChild(loader);
                previewContainer.style.width = isMobile ? '100%' : '380px';

                fetch(url)
                    .then(function(r) { return r.arrayBuffer(); })
                    .then(function(arrayBuffer) {
                        if (activeUrl !== url) return;
                        return mammoth.convertToHtml({arrayBuffer: arrayBuffer});
                    })
                    .then(function(result) {
                        if (activeUrl !== url || !result) return;
                        previewContainer.innerHTML = '';
                        previewContainer.appendChild(closeBtn);
                        var content = document.createElement('div');
                        content.className = 'preview-content-docx';
                        content.innerHTML = result.value;
                        previewContainer.appendChild(content);
                    })
                    .catch(function(err) {
                        if (activeUrl !== url) return;
                        previewContainer.innerHTML = '';
                        previewContainer.appendChild(closeBtn);
                        var error = document.createElement('div');
                        error.className = 'preview-error';
                        error.innerHTML = '<i class="bi bi-exclamation-triangle"></i> No se pudo previsualizar el documento Word.';
                        previewContainer.appendChild(error);
                    });
            } else if (type === 'video') {
                var video = document.createElement('video');
                video.src = url;
                video.controls = true;
                video.autoplay = true;
                video.style.width = '100%';
                video.style.maxHeight = isMobile ? '60vh' : '400px';
                video.style.borderRadius = '8px';
                previewContainer.appendChild(video);
                if (!isMobile) previewContainer.style.maxWidth = '600px';
            }
        }

        function hidePreview() {
            if (isMobile) return;
            hideTimeout = setTimeout(function() {
                previewContainer.style.display = 'none';
                previewContainer.innerHTML = '';
                previewContainer.appendChild(closeBtn);
                previewContainer.style.maxWidth = '400px';
                previewContainer.style.width = '';
                activeUrl = '';
            }, 250);
        }

        triggers.forEach(function(el) {
            el.addEventListener('mouseenter', function(e) {
                if (!isMobile) showPreview(el, e);
            });

            el.addEventListener('mouseleave', function() {
                if (!isMobile) hidePreview();
            });

            var pressTimer;
            el.addEventListener('touchstart', function(e) {
                pressTimer = window.setTimeout(function() {
                    showPreview(el, e);
                    if (navigator.vibrate) navigator.vibrate(40);
                }, 600);
            }, {passive: true});

            el.addEventListener('touchend', function() {
                clearTimeout(pressTimer);
            }, {passive: true});

            el.addEventListener('touchmove', function() {
                clearTimeout(pressTimer);
            }, {passive: true});
        });

        previewContainer.addEventListener('mouseenter', function() {
            if (!isMobile) clearTimeout(hideTimeout);
        });

        previewContainer.addEventListener('mouseleave', function() {
            if (!isMobile) hidePreview();
        });

    })();
});
</script>
