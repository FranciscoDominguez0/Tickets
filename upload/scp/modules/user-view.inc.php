<?php
if (!isset($viewUser) || !is_array($viewUser)) return;
$uid = (int) $viewUser['id'];
$statusKey = $viewUser['status'] ?? 'active';
$statusLabel = $statusLabels[$statusKey] ?? ucfirst($statusKey);
?>
<style>
/* Vista de usuario - diseño creativo */
.user-view-wrap { max-width: 960px; margin: 0 auto; }
.user-view-header {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 28px;
    padding-bottom: 20px;
    border-bottom: 2px solid #e2e8f0;
}
.user-view-title {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    font-size: 1.75rem;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
}
.user-view-title a {
    color: inherit;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    transition: color .15s;
}
.user-view-title a:hover { color: #2563eb; }
.user-view-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.user-view-actions .btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    border-radius: 10px;
    font-weight: 600;
    border: 1px solid #e2e8f0;
    background: #fff;
    color: #475569;
    text-decoration: none;
    font-size: 0.9rem;
}
.user-view-actions .btn:hover { background: #f8fafc; color: #1e293b; }
.user-view-actions .btn.btn-register { background: linear-gradient(135deg, #10b981, #059669); color: #fff; border: none; }
.user-view-actions .btn.btn-register:hover { opacity: 0.95; color: #fff; }
.user-view-actions .btn.btn-danger { background: #fef2f2; color: #dc2626; border-color: #fecaca; }
.user-view-actions .btn.btn-danger:hover { background: #fee2e2; color: #b91c1c; }
.user-view-card {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 4px 24px rgba(0,0,0,0.06);
    border: 1px solid #f1f5f9;
    overflow: hidden;
    margin-bottom: 24px;
}
.user-view-profile {
    display: grid;
    grid-template-columns: 100px 1fr;
    gap: 28px;
    padding: 28px 32px;
    align-items: start;
}
.user-view-avatar {
    width: 100px;
    height: 100px;
    border-radius: 16px;
    background: linear-gradient(145deg, #e2e8f0 0%, #cbd5e1 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #64748b;
    font-size: 2.5rem;
    flex-shrink: 0;
}
.user-view-details { display: grid; grid-template-columns: 1fr 1fr; gap: 24px 32px; }
.user-view-detail { margin-bottom: 0; }
.user-view-detail label { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; font-weight: 600; display: block; margin-bottom: 4px; }
.user-view-detail .value { font-size: 1rem; color: #0f172a; }
.user-view-detail .value a { color: #2563eb; text-decoration: none; }
.user-view-detail .value a:hover { text-decoration: underline; }
.user-view-detail .value .edit-icon { font-size: 0.85rem; margin-left: 6px; opacity: 0.7; }
.user-view-status-badge {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.9rem;
}
.user-view-status-badge.active { background: #dcfce7; color: #166534; }
.user-view-status-badge.inactive { background: #f1f5f9; color: #475569; }
.user-view-status-badge.banned { background: #fee2e2; color: #991b1b; }
.user-view-tabs {
    display: flex;
    gap: 0;
    padding: 0 32px;
    border-bottom: 2px solid #e2e8f0;
    background: #fafafa;
}
.user-view-tabs .tab {
    padding: 14px 20px;
    font-weight: 600;
    color: #64748b;
    text-decoration: none;
    border-bottom: 3px solid transparent;
    margin-bottom: -2px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 0.95rem;
}
.user-view-tabs .tab:hover { color: #2563eb; }
.user-view-tabs .tab.active { color: #2563eb; border-bottom-color: #2563eb; background: #fff; }
.user-view-tab-content { padding: 24px 32px; min-height: 120px; }
.user-view-tab-content .empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #64748b;
}
.user-view-tab-content .empty-state .icon { font-size: 3rem; opacity: 0.4; margin-bottom: 12px; }
.user-view-tab-content .btn-create { margin-top: 16px; }
.user-view-tickets-table { width: 100%; border-collapse: collapse; }
.user-view-tickets-table th, .user-view-tickets-table td { padding: 12px 16px; text-align: left; border-bottom: 1px solid #f1f5f9; }
.user-view-tickets-table th { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; font-weight: 600; }
.user-view-tickets-table td a { color: #2563eb; text-decoration: none; }
.user-view-tickets-table td a:hover { text-decoration: underline; }
</style>

<div class="user-view-wrap">
    <header class="user-view-header">
        <h1 class="user-view-title">
            <a href="users.php?id=<?php echo $uid; ?>" title="Recargar">
                <i class="bi bi-arrow-clockwise"></i>
                <?php echo html($viewUserName); ?>
            </a>
        </h1>
        <div class="user-view-actions">
            <a href="#" class="btn btn-register"><i class="bi bi-person-check"></i> Registrarse</a>
            <a href="users.php?do=delete&id=<?php echo $uid; ?>" class="btn btn-danger" onclick="return confirm('¿Eliminar este usuario?');"><i class="bi bi-trash"></i> Eliminar usuario</a>
            <div class="dropdown">
                <button class="btn dropdown-toggle" type="button" data-bs-toggle="dropdown"><i class="bi bi-gear"></i> Más <i class="bi bi-chevron-down" style="font-size:0.7rem;"></i></button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="#"><i class="bi bi-envelope"></i> Enviar restablecer contraseña</a></li>
                    <li><a class="dropdown-item" href="#"><i class="bi bi-pencil"></i> Editar perfil</a></li>
                </ul>
            </div>
        </div>
    </header>

    <div class="user-view-card">
        <div class="user-view-profile">
            <div class="user-view-avatar">
                <i class="bi bi-person-fill"></i>
            </div>
            <div class="user-view-details">
                <div class="user-view-detail">
                    <label>Nombre</label>
                    <div class="value">
                        <a href="#"><?php echo html($viewUserName); ?></a>
                        <i class="bi bi-pencil edit-icon" title="Editar nombre"></i>
                    </div>
                </div>
                <div class="user-view-detail">
                    <label>Email</label>
                    <div class="value"><?php echo html($viewUser['email']); ?></div>
                </div>
                <div class="user-view-detail">
                    <label>Organización</label>
                    <div class="value"><a href="#">Agregar organización</a></div>
                </div>
                <div class="user-view-detail">
                    <label>Estado</label>
                    <div class="value">
                        <span class="user-view-status-badge <?php echo html($statusKey); ?>"><?php echo html($statusLabel); ?></span>
                    </div>
                </div>
                <div class="user-view-detail">
                    <label>Creado</label>
                    <div class="value"><?php echo $viewUser['created'] ? date('d/m/y H:i:s', strtotime($viewUser['created'])) : '—'; ?></div>
                </div>
                <div class="user-view-detail">
                    <label>Actualizado</label>
                    <div class="value"><?php echo $viewUser['updated'] ? date('d/m/y H:i:s', strtotime($viewUser['updated'])) : '—'; ?></div>
                </div>
            </div>
        </div>

<?php $activeTab = $_GET['t'] ?? 'tickets'; ?>
        <ul class="user-view-tabs" role="tablist">
            <li><a class="tab <?php echo $activeTab === 'tickets' ? 'active' : ''; ?>" href="users.php?id=<?php echo $uid; ?>&t=tickets"><i class="bi bi-ticket-perforated"></i> Tickets</a></li>
            <li><a class="tab <?php echo $activeTab === 'notes' ? 'active' : ''; ?>" href="users.php?id=<?php echo $uid; ?>&t=notes"><i class="bi bi-pin-angle"></i> Notas</a></li>
        </ul>

        <div class="user-view-tab-content" id="tab-tickets" style="display:<?php echo $activeTab === 'tickets' ? 'block' : 'none'; ?>">
            <?php if (empty($userTickets)): ?>
                <div class="empty-state">
                    <div class="icon"><i class="bi bi-inbox"></i></div>
                    <p class="mb-0">Usuario no tiene ningún Ticket</p>
                    <a href="open.php?user_id=<?php echo $uid; ?>" class="btn btn-primary btn-create"><i class="bi bi-plus-lg"></i> Crear un nuevo Ticket</a>
                </div>
            <?php else: ?>
                <table class="user-view-tickets-table">
                    <thead>
                        <tr>
                            <th>Número</th>
                            <th>Asunto</th>
                            <th>Estado</th>
                            <th>Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($userTickets as $t): ?>
                            <tr>
                                <td><a href="tickets.php?id=<?php echo (int)$t['id']; ?>"><?php echo html($t['ticket_number']); ?></a></td>
                                <td><a href="tickets.php?id=<?php echo (int)$t['id']; ?>"><?php echo html($t['subject']); ?></a></td>
                                <td><?php echo html($t['status_name'] ?? '—'); ?></td>
                                <td><?php echo $t['created'] ? date('d/m/y H:i', strtotime($t['created'])) : '—'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p class="mt-3 mb-0">
                    <a href="open.php?user_id=<?php echo $uid; ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> Crear un nuevo Ticket</a>
                </p>
            <?php endif; ?>
        </div>

        <div class="user-view-tab-content" id="tab-notes" style="display:<?php echo $activeTab === 'notes' ? 'block' : 'none'; ?>">
            <div class="empty-state">
                <div class="icon"><i class="bi bi-pin-angle"></i></div>
                <p class="mb-0">No hay notas para este usuario</p>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    var tab = '<?php echo html($activeTab); ?>';
    document.querySelectorAll('.user-view-tabs .tab').forEach(function(el) {
        var href = (el.getAttribute('href') || '').toString();
        if (href.indexOf('t=' + tab) !== -1) el.classList.add('active');
        else el.classList.remove('active');
    });
})();
</script>
