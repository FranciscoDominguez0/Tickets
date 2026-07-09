<style>
    /* Distinct structural styling for Cotizaciones (Quotes) */
    .quotes-shell .ticket-row {
        position: relative;
    }
    .quotes-shell .ticket-row:hover {
        background: #f8fafc !important;
    }
    .quotes-shell .quote-icon-wrapper {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 44px;
        height: 44px;
        background: #eff6ff;
        color: #3b82f6;
        border-radius: 12px;
        font-size: 1.2rem;
        flex-shrink: 0;
    }
    
    body.dark-mode .quotes-shell .ticket-row:hover {
        background: #1e293b !important;
    }
    body.dark-mode .quotes-shell .quote-icon-wrapper {
        background: rgba(96, 165, 250, 0.1);
        color: #60a5fa;
    }

    /* Dark mode overrides for cotizaciones list */
    body.dark-mode .quotes-shell .tickets-table thead.table-light {
        background-color: #1e293b !important;
        border-bottom-color: #334155 !important;
    }
    body.dark-mode .quotes-shell .tickets-table thead.table-light th {
        color: #cbd5e1 !important;
    }
    body.dark-mode .quotes-shell .ticket-row {
        background: #111111 !important;
        border-bottom: 1px solid #333 !important;
    }
    body.dark-mode .quotes-shell .ticket-title {
        background: #334155 !important;
        color: #f8fafc !important;
    }
    body.dark-mode .quotes-shell .ticket-title i {
        color: #94a3b8 !important;
    }
    body.dark-mode .quotes-shell .ticket-subject {
        color: #f8fafc !important;
    }
    body.dark-mode .quotes-shell .ticket-row td span[style*="#334155"],
    body.dark-mode .quotes-shell .ticket-row td strong[style*="#475569"] {
        color: #e2e8f0 !important;
    }
    body.dark-mode .quotes-shell .ticket-row td div[style*="#64748b"],
    body.dark-mode .quotes-shell .ticket-row td i[style*="#94a3b8"] {
        color: #94a3b8 !important;
    }
    body.dark-mode .quotes-shell .ticket-row td div[style*="#f1f5f9"] {
        background: #334155 !important;
        color: #cbd5e1 !important;
    }
</style>
<div class="tickets-shell quotes-shell">
    <div class="tickets-header">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2">
            <div>
                <h1>Cotizaciones</h1>
                <div class="sub">
                    <?php
                    $statusNames = [
                        '' => 'Todas',
                        'draft' => 'Borradores',
                        'pending' => 'Pendientes',
                        'accepted' => 'Aceptadas',
                        'rejected' => 'Rechazadas'
                    ];
                    echo html($statusNames[$statusFilter] ?? 'Listado');
                    ?>
                </div>
            </div>
            <a href="cotizaciones.php?a=open" class="btn-new">
                <i class="bi bi-plus-lg me-1"></i> Nueva
            </a>
        </div>
    </div>

    <!-- Toolbar -->
    <div class="tickets-panel mb-3" data-filter-key="<?php echo html($statusFilter); ?>">
        <div class="tickets-toolbar">
            <div class="tickets-filters">
                <div class="dropdown filter-dd">
                    <button class="btn btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-funnel"></i>
                        <?php echo html($statusNames[$statusFilter] ?? 'Filtro'); ?>
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item <?php echo $statusFilter === '' ? 'active' : ''; ?>" href="cotizaciones.php<?php echo $searchQuery !== '' ? '?q=' . urlencode($searchQuery) : ''; ?>">Todas</a></li>
                        <li><a class="dropdown-item <?php echo $statusFilter === 'draft' ? 'active' : ''; ?>" href="cotizaciones.php?status=draft<?php echo $searchQuery !== '' ? '&q=' . urlencode($searchQuery) : ''; ?>">Borradores</a></li>
                        <li><a class="dropdown-item <?php echo $statusFilter === 'pending' ? 'active' : ''; ?>" href="cotizaciones.php?status=pending<?php echo $searchQuery !== '' ? '&q=' . urlencode($searchQuery) : ''; ?>">Pendientes</a></li>
                        <li><a class="dropdown-item <?php echo $statusFilter === 'accepted' ? 'active' : ''; ?>" href="cotizaciones.php?status=accepted<?php echo $searchQuery !== '' ? '&q=' . urlencode($searchQuery) : ''; ?>">Aceptadas</a></li>
                        <li><a class="dropdown-item <?php echo $statusFilter === 'rejected' ? 'active' : ''; ?>" href="cotizaciones.php?status=rejected<?php echo $searchQuery !== '' ? '&q=' . urlencode($searchQuery) : ''; ?>">Rechazadas</a></li>
                    </ul>
                </div>
            </div>
            <div class="tickets-search">
                <form method="GET" action="cotizaciones.php" class="input-group">
                    <?php if ($statusFilter !== ''): ?>
                        <input type="hidden" name="status" value="<?php echo html($statusFilter); ?>">
                    <?php endif; ?>
                    <span class="input-group-text bg-white" style="border-right: none; border-radius: 10px 0 0 10px;"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" id="ticketSearchInput" class="form-control" style="border-left: none; border-radius: 0 10px 10px 0;" placeholder="Buscar reporte..." value="<?php echo html($searchQuery); ?>">
                    <?php if ($searchQuery !== ''): ?>
                        <a href="cotizaciones.php<?php echo $statusFilter !== '' ? '?status='.html($statusFilter) : ''; ?>" class="btn btn-outline-secondary" style="border-left: none; border-radius: 0 10px 10px 0; background: #fff; border-color: #dee2e6;">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <!-- Tabla -->
    <div class="tickets-table-wrap">
        <table class="table table-hover tickets-table no-checkbox mb-0" id="ticketsTable">
            <thead class="table-light" style="border-bottom: 2px solid #e2e8f0; background-color: #f8fafc;">
                <tr>
                    <th style="font-weight: 700; color: #475569; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; padding-left: 20px;">Reporte</th>
                    <th class="d-none d-lg-table-cell" style="font-weight: 700; color: #475569; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em;">Organización</th>
                    <th class="d-none d-md-table-cell" style="font-weight: 700; color: #475569; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em;">Estado</th>
                    <th class="d-none d-lg-table-cell" style="font-weight: 700; color: #475569; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em;">Fecha</th>
                    <th style="width: 80px; text-align: right; font-weight: 700; color: #475569; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em;"></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($quotes)): ?>
                    <tr>
                        <td colspan="5">
                            <div class="empty-state text-center p-4">
                                <i class="bi bi-file-earmark-text fs-1 text-muted" style="opacity: 0.6;"></i>
                                <div class="mt-2 text-muted">No se encontraron cotizaciones.</div>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($quotes as $q): ?>
                        <?php
                        $statusColors = [
                            'draft'    => ['color' => '#94a3b8', 'icon' => 'bi-pencil-square',   'label' => 'Borrador'],
                            'pending'  => ['color' => '#64748b', 'icon' => 'bi-clock-fill',       'label' => 'Pendiente'],
                            'requested'=> ['color' => '#eab308', 'icon' => 'bi-send-exclamation', 'label' => 'Solicitada'],
                            'answered' => ['color' => '#3b82f6', 'icon' => 'bi-reply-all-fill',   'label' => 'Esperando Aprobación'],
                            'accepted' => ['color' => '#22c55e', 'icon' => 'bi-check-circle-fill', 'label' => 'Aceptada'],
                            'rejected' => ['color' => '#ef4444', 'icon' => 'bi-x-circle-fill',    'label' => 'Rechazada']
                        ];
                        $st = $statusColors[$q['status']] ?? $statusColors['draft'];
                        ?>
                        <tr class="ticket-row" style="background: #fff; cursor: pointer;" onclick="window.location='cotizaciones.php?id=<?php echo $q['id']; ?>'">
                            <td style="vertical-align: middle; padding: 18px 12px 18px 20px;">
                                <div style="display: flex; gap: 14px; align-items: flex-start;">
                                    <div class="quote-icon-wrapper d-none d-sm-flex">
                                        <i class="bi bi-file-earmark-ruled"></i>
                                    </div>
                                    <div style="flex: 1;">
                                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                                            <a class="ticket-title" href="cotizaciones.php?id=<?php echo $q['id']; ?>" style="background: #f1f5f9; color: #0f172a; padding: 2px 8px; border-radius: 6px; font-weight: 800; font-size: 0.95rem; text-decoration: none; display: inline-flex; align-items: center; gap: 2px; border: 1px solid #e2e8f0;">
                                                <i class="bi bi-hash" style="color: #64748b; font-size: 0.85rem;"></i><?php echo $q['id']; ?>
                                            </a>
                                            <div class="d-md-none text-muted ms-auto" style="font-size:0.75rem; font-weight:600;">
                                                <?php echo date('d/m/Y', strtotime($q['created_at'])); ?>
                                            </div>
                                        </div>
                                        <div class="ticket-subject" style="font-weight: 600; color: #1e293b; font-size: 1rem; margin-bottom: 8px; line-height: 1.3; display: block; max-width: 55ch; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; text-transform: none;">
                                            <?php echo html($q['title']); ?>
                                        </div>
                                        <div style="display: flex; align-items: center; font-size: 0.8rem; color: #64748b;">
                                            <span style="display:inline-flex; align-items:center; gap:5px;">
                                                <i class="bi bi-headset" style="color:#94a3b8;"></i> Agente: <strong style="color: #475569; font-weight:600;"><?php echo html($q['staff_name'] ?: 'Sin asignar'); ?></strong>
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Mobile Info -->
                                <div class="d-md-none mt-3" style="display:flex; gap:8px; flex-direction:column;">
                                    <div style="font-size: 0.85rem; color: #475569; display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                                        <i class="bi bi-building" style="color:#cbd5e1;"></i> <strong><?php echo html($q['org_name'] ?: 'N/A'); ?></strong>
                                        <?php if (!empty($q['sucursal'])): ?>
                                        <span style="font-size:0.72rem; color:#94a3b8; font-weight:500; display:inline-flex; align-items:center; gap:2px;">
                                            <i class="bi bi-geo-alt" style="font-size:0.68rem;"></i><?php echo html($q['sucursal']); ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="display:flex; gap:6px; flex-wrap:wrap; margin-top: 2px;">
                                        <span class="chip chip-status" style="background: <?php echo $st['color']; ?>15; color: <?php echo $st['color']; ?>; border: 1px solid <?php echo $st['color']; ?>33; font-size:0.7rem; border-radius:6px; padding:3px 8px; font-weight:700; text-transform: uppercase;">
                                            <i class="bi <?php echo $st['icon']; ?>" style="font-size: 0.7rem; margin-right: 4px; vertical-align: middle;"></i> <?php echo $st['label']; ?>
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td class="d-none d-lg-table-cell" style="vertical-align: middle;">
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <div style="width: 32px; height: 32px; border-radius: 50%; background: #f1f5f9; color: #64748b; display: flex; align-items: center; justify-content: center; font-size: 1rem; flex-shrink: 0;">
                                        <i class="bi bi-building"></i>
                                    </div>
                                    <div style="display:flex; flex-direction:column; gap:2px;">
                                        <span style="font-weight: 700; color: #334155; font-size: 0.9rem;"><?php echo html($q['org_name'] ?: 'N/A'); ?></span>
                                        <?php if (!empty($q['sucursal'])): ?>
                                        <span style="font-size: 0.72rem; color: #94a3b8; font-weight: 500; display:inline-flex; align-items:center; gap:3px;">
                                            <i class="bi bi-geo-alt" style="font-size:0.68rem;"></i><?php echo html($q['sucursal']); ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="d-none d-md-table-cell" style="vertical-align: middle;">
                                <div style="display:flex; flex-direction:column; gap:6px; align-items: flex-start;">
                                    <span class="chip chip-status" style="background: <?php echo $st['color']; ?>15; color: <?php echo $st['color']; ?>; border: 1px solid <?php echo $st['color']; ?>33; padding: 6px 14px; font-weight: 700; letter-spacing: 0.03em; border-radius: 8px; font-size: 0.8rem; text-transform: uppercase;">
                                        <i class="bi <?php echo $st['icon']; ?>" style="font-size: 0.7rem; margin-right: 4px; vertical-align: middle;"></i> <?php echo $st['label']; ?>
                                    </span>
                                </div>
                            </td>
                            <td class="d-none d-lg-table-cell" style="vertical-align: middle;">
                                <div style="color:#64748b; font-size: 0.85rem; font-weight: 600; display:flex; align-items:center; gap:6px;">
                                    <i class="bi bi-clock-history" style="color:#94a3b8; font-size: 1rem;"></i> 
                                    <span><?php echo date('d/m/Y', strtotime($q['created_at'])); ?></span>
                                </div>
                            </td>
                            <td style="vertical-align: middle; text-align: right; padding-right: 20px;">
                                <div class="d-flex justify-content-end gap-2 align-items-center">
                                    <button type="button" class="btn btn-sm" style="background: transparent; color: #f87171; border: none; font-size: 1.1rem; transition: all 0.2s; padding: 4px;" onmouseover="this.style.color='#dc2626'" onmouseout="this.style.color='#f87171'" title="Eliminar Reporte" data-bs-toggle="modal" data-bs-target="#deleteQuoteModal" data-id="<?php echo $q['id']; ?>" onclick="event.stopPropagation();">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                    <a href="cotizaciones.php?id=<?php echo $q['id']; ?>" class="btn btn-sm" style="background: transparent; color: #94a3b8; border: none; font-size: 1.2rem; transition: all 0.2s; display: inline-flex; align-items: center; padding: 4px;" onmouseover="this.style.color='#60a5fa'" onmouseout="this.style.color='#94a3b8'">
                                        <i class="bi bi-chevron-right"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if (isset($totalPages) && $totalPages > 1): ?>
    <div class="mt-4 mb-2">
        <?php 
        $urlParams = '';
        foreach ($_GET as $k => $v) {
            if ($k !== 'p' && $k !== 'page' && !is_array($v)) {
                $urlParams .= '&' . urlencode($k) . '=' . urlencode((string)$v);
            }
        }
        echo renderModernPagination($page, $totalPages, $urlParams, 'p'); 
        ?>
    </div>
    <?php endif; ?>
</div>

<!-- Modal Confirmar Eliminación -->
<div class="modal fade" id="deleteQuoteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 16px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
            <div class="modal-header border-0 pb-0">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center pt-0 pb-4 px-4">
                <div style="width: 70px; height: 70px; border-radius: 50%; background: #fef2f2; color: #ef4444; display: inline-flex; align-items: center; justify-content: center; font-size: 2rem; margin-bottom: 20px;">
                    <i class="bi bi-trash3-fill"></i>
                </div>
                <h4 class="fw-bold mb-2" style="color: #0f172a;">¿Eliminar cotización?</h4>
                <p class="text-muted mb-4" style="font-size: 0.95rem;">Esta acción no se puede deshacer. La cotización será borrada permanentemente del sistema.</p>
                <form method="POST" action="cotizaciones.php" id="deleteQuoteForm">
                    <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="deleteQuoteId" value="">
                    
                    <div class="d-flex justify-content-center gap-3">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal" style="border-radius: 999px; padding: 10px 24px; font-weight: 700; color: #475569; background: #f1f5f9; border: none;">Cancelar</button>
                        <button type="submit" class="btn btn-danger" style="border-radius: 999px; padding: 10px 24px; font-weight: 700; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);">Sí, eliminar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var deleteQuoteModal = document.getElementById('deleteQuoteModal');
    if (deleteQuoteModal) {
        deleteQuoteModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var quoteId = button.getAttribute('data-id');
            var inputId = document.getElementById('deleteQuoteId');
            if (inputId) {
                inputId.value = quoteId;
            }
        });
    }
});
</script>

<style>
    body.dark-mode #deleteQuoteModal .modal-content { background: #1e293b; }
    body.dark-mode #deleteQuoteModal h4 { color: #f8fafc !important; }
    body.dark-mode #deleteQuoteModal .text-muted { color: #94a3b8 !important; }
    body.dark-mode #deleteQuoteModal .btn-close { filter: invert(1) grayscale(100%) brightness(200%); }
    body.dark-mode #deleteQuoteModal .btn-light { background: #334155 !important; color: #cbd5e1 !important; }
    body.dark-mode #deleteQuoteModal .btn-light:hover { background: #475569 !important; }
    body.dark-mode #deleteQuoteModal .bi-trash3-fill { color: #f87171; }
    body.dark-mode #deleteQuoteModal div[style*="background: #fef2f2"] { background: rgba(239, 68, 68, 0.1) !important; }
</style>
