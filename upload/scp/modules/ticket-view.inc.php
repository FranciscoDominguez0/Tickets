<?php
if (!isset($ticketView) || !is_array($ticketView)) return;
$t = $ticketView;
$tid = (int) $t['id'];
$entries = $t['thread_entries'] ?? [];
$countPublic = count(array_filter($entries, function ($e) { return (int)($e['is_internal'] ?? 0) === 0; }));
?>
<style>
/* Vista de ticket - estilo tipo osTicket */
.ticket-view-wrap { max-width: 1100px; margin: 0 auto; }
.ticket-view-header {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 20px;
    padding-bottom: 16px;
    border-bottom: 2px solid #e2e8f0;
}
.ticket-view-title {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    font-size: 1.75rem;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
}
.ticket-view-title a { color: inherit; text-decoration: none; }
.ticket-view-title a:hover { color: #2563eb; }
.ticket-view-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.ticket-view-actions .btn-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
    background: #fff;
    color: #475569;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    transition: all 0.15s;
}
.ticket-view-actions .btn-icon:hover { background: #f8fafc; color: #1d4ed8; }
.ticket-view-overview {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 20px 24px;
    margin-bottom: 24px;
    padding: 20px 24px;
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 4px 24px rgba(0,0,0,0.06);
    border: 1px solid #f1f5f9;
}
.ticket-view-overview .field { margin-bottom: 12px; }
.ticket-view-overview .field:last-child { margin-bottom: 0; }
.ticket-view-overview .field label {
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #64748b;
    font-weight: 600;
    display: block;
    margin-bottom: 4px;
}
.ticket-view-overview .field .value { font-size: 0.95rem; color: #0f172a; }
.ticket-view-overview .field .value a { color: #2563eb; text-decoration: none; }
.ticket-view-overview .field .value a:hover { text-decoration: underline; }
.ticket-view-overview .badge-status {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.85rem;
}
.ticket-view-tabs {
    display: flex;
    gap: 0;
    padding: 0 24px;
    background: #f8fafc;
    border-radius: 12px 12px 0 0;
    border: 1px solid #e2e8f0;
    border-bottom: none;
}
.ticket-view-tabs .tab {
    padding: 14px 20px;
    font-weight: 600;
    color: #64748b;
    text-decoration: none;
    border-bottom: 3px solid transparent;
    margin-bottom: -1px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 0.95rem;
}
.ticket-view-tabs .tab:hover { color: #2563eb; }
.ticket-view-tabs .tab.active { color: #2563eb; border-bottom-color: #2563eb; background: #fff; border-radius: 8px 8px 0 0; }
.ticket-view-tab-content {
    padding: 24px;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-top: none;
    border-radius: 0 0 12px 12px;
    margin-bottom: 24px;
    box-shadow: 0 4px 24px rgba(0,0,0,0.06);
}
.ticket-view-entry {
    padding: 16px 20px;
    margin-bottom: 16px;
    border-radius: 12px;
    border-left: 4px solid #2563eb;
    background: #f8fafc;
}
.ticket-view-entry.internal { border-left-color: #f59e0b; background: #fffbeb; }
.ticket-view-entry.staff { border-left-color: #059669; background: #ecfdf5; }
.ticket-view-entry .entry-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 10px;
    font-size: 0.85rem;
    color: #475569;
}
.ticket-view-entry .entry-meta .author { font-weight: 600; color: #0f172a; }
.ticket-view-entry .entry-body { color: #334155; white-space: pre-wrap; word-break: break-word; }
.ticket-view-entry .entry-body p { margin: 0 0 0.5em; }
.ticket-view-entry .entry-body p:last-child { margin-bottom: 0; }
.ticket-view-entry .entry-footer { font-size: 0.8rem; color: #94a3b8; margin-top: 10px; }
.ticket-view-reply {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 4px 24px rgba(0,0,0,0.06);
    border: 1px solid #e2e8f0;
    padding: 24px;
}
.ticket-view-reply .reply-buttons { display: flex; gap: 10px; margin-bottom: 16px; flex-wrap: wrap; }
.ticket-view-reply .btn-reply {
    padding: 10px 20px;
    border-radius: 10px;
    font-weight: 600;
    border: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.ticket-view-reply .btn-reply.btn-primary-reply { background: linear-gradient(135deg, #2563eb, #1d4ed8); color: #fff; }
.ticket-view-reply .btn-reply.btn-internal { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
.ticket-view-reply .btn-reply.btn-internal:hover { background: #e2e8f0; color: #334155; }
.ticket-view-reply textarea.form-control { min-height: 180px; border-radius: 12px; resize: vertical; }
.ticket-view-reply .reply-from {
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid #e2e8f0;
    font-size: 0.9rem;
    color: #64748b;
}
.ticket-view-reply .reply-from strong { color: #334155; }
.ticket-view-reply .attach-zone {
    border: 2px dashed #cbd5e1;
    border-radius: 12px;
    padding: 24px;
    text-align: center;
    background: #f8fafc;
    margin-bottom: 16px;
    cursor: pointer;
    transition: border-color 0.2s, background 0.2s;
}
.ticket-view-reply .attach-zone:hover, .ticket-view-reply .attach-zone.dragover { border-color: #2563eb; background: #eff6ff; }
.ticket-view-reply .attach-zone input[type="file"] { display: none; }
.ticket-view-reply .attach-zone .attach-text { color: #64748b; font-size: 0.95rem; }
.ticket-view-reply .attach-zone .attach-text a { color: #2563eb; text-decoration: none; }
.ticket-view-reply .attach-zone .attach-text a:hover { text-decoration: underline; }
.ticket-view-reply .attach-list { margin-top: 8px; font-size: 0.85rem; color: #475569; }
.ticket-view-reply .reply-options { display: flex; flex-wrap: wrap; gap: 24px 32px; margin-bottom: 16px; align-items: flex-start; }
.ticket-view-reply .reply-options .opt-group label { font-weight: 600; font-size: 0.9rem; color: #334155; display: block; margin-bottom: 8px; }
.ticket-view-reply .reply-options .opt-group .form-select { max-width: 280px; border-radius: 10px; }
.ticket-view-reply .reply-options .opt-group .form-radio { margin-right: 12px; }
.ticket-view-reply .btn-reset { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; padding: 10px 20px; border-radius: 10px; font-weight: 600; }
.ticket-view-reply .btn-reset:hover { background: #e2e8f0; color: #334155; }
.ticket-view-reply .btn-publish { background: linear-gradient(135deg, #eab308, #ca8a04); color: #1c1917; border: none; padding: 10px 24px; border-radius: 10px; font-weight: 600; }
.ticket-view-reply .btn-publish:hover { background: linear-gradient(135deg, #ca8a04, #a16207); color: #1c1917; }
</style>

<div class="ticket-view-wrap">
    <header class="ticket-view-header">
        <h1 class="ticket-view-title">
            <a href="tickets.php?id=<?php echo $tid; ?>" title="Recargar">
                <i class="bi bi-arrow-clockwise"></i>
            </a>
            Ticket #<?php echo html($t['ticket_number']); ?>
        </h1>
        <div class="ticket-view-actions">
            <a href="users.php?id=<?php echo (int)$t['user_id']; ?>" class="btn-icon" title="Volver al usuario"><i class="bi bi-arrow-left"></i></a>
            <a href="users.php?id=<?php echo (int)$t['user_id']; ?>" class="btn-icon" title="Guardar"><i class="bi bi-save"></i></a>
            <div class="dropdown d-inline-block">
                <button class="btn-icon dropdown-toggle" type="button" data-bs-toggle="dropdown" title="Estado"><i class="bi bi-flag"></i></button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <?php
                    $st = $mysqli->query("SELECT id, name FROM ticket_status ORDER BY order_by, id");
                    while ($row = $st->fetch_assoc()): ?>
                        <li><a class="dropdown-item <?php echo (int)$row['id'] === (int)$t['status_id'] ? 'active' : ''; ?>" href="#"><?php echo html($row['name']); ?></a></li>
                    <?php endwhile; ?>
                </ul>
            </div>
            <div class="dropdown d-inline-block">
                <button class="btn-icon dropdown-toggle" type="button" data-bs-toggle="dropdown" title="Asignar"><i class="bi bi-person"></i></button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="#">— Sin asignar —</a></li>
                    <?php
                    $st = $mysqli->query("SELECT id, firstname, lastname FROM staff WHERE is_active = 1 ORDER BY firstname, lastname");
                    while ($row = $st->fetch_assoc()): ?>
                        <li><a class="dropdown-item" href="#"><?php echo html(trim($row['firstname'] . ' ' . $row['lastname'])); ?></a></li>
                    <?php endwhile; ?>
                </ul>
            </div>
            <button class="btn-icon" title="Imprimir"><i class="bi bi-printer"></i></button>
            <button class="btn-icon" title="Más"><i class="bi bi-gear"></i></button>
        </div>
    </header>

    <!-- Resumen del ticket -->
    <div class="ticket-view-overview">
        <div>
            <div class="field">
                <label>Estado</label>
                <div class="value">
                    <span class="badge-status" style="background: <?php echo html($t['status_color'] ?? '#e2e8f0'); ?>; color: #0f172a;"><?php echo html($t['status_name']); ?></span>
                </div>
            </div>
            <div class="field">
                <label>Prioridad</label>
                <div class="value"><?php echo html($t['priority_name']); ?></div>
            </div>
            <div class="field">
                <label>Departamento</label>
                <div class="value"><?php echo html($t['dept_name']); ?></div>
            </div>
            <div class="field">
                <label>Creado en</label>
                <div class="value"><?php echo $t['created'] ? date('m/d/y H:i:s', strtotime($t['created'])) : '—'; ?></div>
            </div>
        </div>
        <div>
            <div class="field">
                <label>Usuario</label>
                <div class="value">
                    <a href="users.php?id=<?php echo (int)$t['user_id']; ?>"><?php echo html($t['user_name']); ?> (<?php echo (int)$t['user_id']; ?>)</a>
                </div>
            </div>
            <div class="field">
                <label>Email</label>
                <div class="value"><?php echo html($t['user_email']); ?></div>
            </div>
            <div class="field">
                <label>Fuente</label>
                <div class="value">Web</div>
            </div>
        </div>
        <div>
            <div class="field">
                <label>Asignado a</label>
                <div class="value"><?php echo html($t['staff_name']); ?></div>
            </div>
            <div class="field">
                <label>Último mensaje</label>
                <div class="value"><?php echo $t['last_message'] ? date('m/d/y H:i:s', strtotime($t['last_message'])) : '—'; ?></div>
            </div>
            <div class="field">
                <label>Última respuesta</label>
                <div class="value"><?php echo $t['last_response'] ? date('m/d/y H:i:s', strtotime($t['last_response'])) : '—'; ?></div>
            </div>
        </div>
    </div>

    <!-- Pestañas: Hilo del ticket / Tareas -->
    <ul class="ticket-view-tabs" role="tablist">
        <li><a class="tab active" href="#thread"><i class="bi bi-chat-left-text"></i> Hilo del Ticket (<?php echo $countPublic; ?>)</a></li>
        <li><a class="tab" href="#tasks"><i class="bi bi-check2-square"></i> Tareas</a></li>
    </ul>

    <div class="ticket-view-tab-content" id="thread">
        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'reply_sent'): ?>
            <div class="alert alert-success alert-dismissible fade show">Respuesta publicada correctamente.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($reply_errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo implode(' ', array_map('htmlspecialchars', $reply_errors)); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (empty($entries)): ?>
            <p class="text-muted mb-0">Aún no hay mensajes en el hilo.</p>
        <?php else: ?>
            <?php foreach ($entries as $e): ?>
                <?php
                $isInternal = (int)($e['is_internal'] ?? 0) === 1;
                $isStaff = !empty($e['staff_id']);
                $author = $isStaff
                    ? (trim($e['staff_first'] . ' ' . $e['staff_last']) ?: 'Agente')
                    : (trim($e['user_first'] . ' ' . $e['user_last']) ?: 'Usuario');
                $cssClass = $isInternal ? 'internal' : ($isStaff ? 'staff' : '');
                ?>
                <div class="ticket-view-entry <?php echo $cssClass; ?>">
                    <div class="entry-meta">
                        <span class="author"><?php echo html($author); ?></span>
                        <span><?php echo $e['created'] ? date('m/d/y H:i:s', strtotime($e['created'])) : ''; ?></span>
                    </div>
                    <div class="entry-body"><?php
                        $b = $e['body'];
                        if (strpos($b, '<') !== false) {
                            echo strip_tags($b, '<p><br><strong><em><b><i><u><s><ul><ol><li><a><span>');
                        } else {
                            echo nl2br(html($b));
                        }
                    ?></div>
                    <div class="entry-footer">
                        Creado por <?php echo html($author); ?> <?php echo $e['created'] ? date('m/d/y H:i:s', strtotime($e['created'])) : ''; ?>
                        <?php if ($isInternal): ?> <span class="badge bg-warning text-dark">Nota interna</span><?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Responder -->
    <div class="ticket-view-reply">
        <form method="post" action="tickets.php?id=<?php echo $tid; ?>" enctype="multipart/form-data" id="form-reply">
            <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">
            <div class="mb-3">
                <label class="form-label fw-bold">Respuesta</label>
                <textarea name="body" id="reply_body" class="form-control" placeholder="Empezar escribiendo su respuesta aquí. Usa respuestas predefinidas del menú desplegable de arriba si lo desea."></textarea>
            </div>
            <div class="attach-zone" id="attach-zone" onclick="document.getElementById('attachments').click();">
                <input type="file" name="attachments[]" id="attachments" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.txt">
                <div class="attach-text">Agregar archivos aquí o <a href="#" onclick="document.getElementById('attachments').click(); return false;">elegirlos</a></div>
                <div class="attach-list" id="attach-list"></div>
            </div>
            <div class="reply-options">
                <div class="opt-group">
                    <label>Firma:</label>
                    <label class="me-3"><input type="radio" name="signature" value="none" class="form-radio" checked> Ninguno</label>
                    <label><input type="radio" name="signature" value="dept" class="form-radio"> Firma del Departamento (<?php echo html($t['dept_name'] ?? 'Soporte'); ?>)</label>
                </div>
                <div class="opt-group">
                    <label>Estado del Ticket:</label>
                    <select name="status_id" class="form-select form-control">
                        <?php
                        $statusList = $ticket_status_list ?? [];
                        foreach ($statusList as $st): ?>
                            <option value="<?php echo (int)$st['id']; ?>" <?php echo (int)$st['id'] === (int)$t['status_id'] ? 'selected' : ''; ?>><?php echo html($st['name']); ?><?php echo (int)$st['id'] === (int)$t['status_id'] ? ' (actual)' : ''; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="reply-buttons">
                <button type="submit" name="do" value="reply" class="btn btn-reply btn-publish">
                    <i class="bi bi-send"></i> Publicar Respuesta
                </button>
                <button type="submit" name="do" value="internal" class="btn btn-reply btn-internal">
                    <i class="bi bi-lock"></i> publicar nota interna
                </button>
                <button type="button" class="btn btn-reset" id="btn-reset">Restablecer</button>
            </div>
            <div class="reply-from">
                <strong>De:</strong> <?php echo html($staff['name'] ?? 'Agente'); ?> &lt;<?php echo html($staff['email'] ?? ''); ?>&gt;<br>
                <strong>Destinatarios:</strong> <?php echo html($t['user_name']); ?> &lt;<?php echo html($t['user_email']); ?>&gt;
            </div>
        </form>
    </div>
</div>

<link href="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.css" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/lang/summernote-es-ES.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof jQuery !== 'undefined' && jQuery().summernote) {
        jQuery('#reply_body').summernote({
            height: 260,
            lang: 'es-ES',
            placeholder: 'Empezar escribiendo su respuesta aquí. Usa respuestas predefinidas del menú desplegable de arriba si lo desea.',
            toolbar: [
                ['style', ['style', 'paragraph']],
                ['font', ['bold', 'italic', 'underline', 'strikethrough', 'clear']],
                ['fontname', ['fontname']],
                ['color', ['color']],
                ['fontsize', ['fontsize']],
                ['insert', ['link', 'picture', 'video', 'table', 'hr']],
                ['view', ['codeview', 'fullscreen']],
                ['para', ['ul', 'ol', 'paragraph']]
            ],
            fontNames: ['Arial', 'Arial Black', 'Comic Sans MS', 'Courier New', 'Helvetica', 'Impact', 'Tahoma', 'Times New Roman', 'Verdana'],
            fontSizes: ['8', '9', '10', '11', '12', '14', '16', '18', '24', '36']
        });
    }
    var zone = document.getElementById('attach-zone');
    var input = document.getElementById('attachments');
    var list = document.getElementById('attach-list');
    function updateList() {
        list.innerHTML = '';
        if (input.files.length) {
            for (var i = 0; i < input.files.length; i++) {
                list.innerHTML += '<span class="d-inline-block me-2 mb-1"><i class="bi bi-paperclip"></i> ' + input.files[i].name + '</span> ';
            }
        }
    }
    input.addEventListener('change', updateList);
    zone.addEventListener('dragover', function(e) { e.preventDefault(); zone.classList.add('dragover'); });
    zone.addEventListener('dragleave', function() { zone.classList.remove('dragover'); });
    zone.addEventListener('drop', function(e) {
        e.preventDefault();
        zone.classList.remove('dragover');
        if (e.dataTransfer.files.length) {
            input.files = e.dataTransfer.files;
            updateList();
        }
    });
    document.getElementById('btn-reset').addEventListener('click', function() {
        if (typeof jQuery !== 'undefined' && jQuery('#reply_body').length && jQuery('#reply_body').summernote('code')) {
            jQuery('#reply_body').summernote('reset');
        }
        input.value = '';
        list.innerHTML = '';
    });
});
</script>
