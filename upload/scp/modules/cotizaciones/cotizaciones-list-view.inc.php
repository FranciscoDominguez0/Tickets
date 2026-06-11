<style>
    /* Dark mode overrides for cotizaciones list */
    body.dark-mode .tickets-table thead.table-light {
        background-color: #1e293b !important;
        border-bottom-color: #334155 !important;
    }
    body.dark-mode .tickets-table thead.table-light th {
        color: #cbd5e1 !important;
    }
    body.dark-mode .ticket-row {
        background: #111111 !important;
        border-bottom: 1px solid #333 !important;
    }
    body.dark-mode .ticket-row:hover {
        background: #1a1a1a !important;
    }
    body.dark-mode .ticket-title {
        color: #60a5fa !important;
        background: rgba(96, 165, 250, 0.15) !important;
    }
    body.dark-mode .ticket-subject {
        color: #f8fafc !important;
    }
    body.dark-mode .ticket-row td span[style*="#334155"],
    body.dark-mode .ticket-row td strong[style*="#475569"] {
        color: #e2e8f0 !important;
    }
    body.dark-mode .ticket-row td div[style*="#64748b"],
    body.dark-mode .ticket-row td i[style*="#94a3b8"] {
        color: #94a3b8 !important;
    }
    body.dark-mode .ticket-row td div[style*="#f1f5f9"] {
        background: #334155 !important;
        color: #cbd5e1 !important;
    }
    body.dark-mode .pagination-container {
        background: #111111 !important;
        border-color: #333 !important;
    }
    body.dark-mode .pagination-container .text-muted {
        color: #94a3b8 !important;
    }
    body.dark-mode .pagination-container .page-link {
        background: transparent !important;
        color: #94a3b8 !important;
    }
    body.dark-mode .pagination-container .page-item.active .page-link {
        background: #3b82f6 !important;
        border-color: #3b82f6 !important;
        color: #fff !important;
    }
</style>
<div class="tickets-shell">
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
                    <input type="text" name="q" id="ticketSearchInput" class="form-control" style="border-left: none; border-radius: 0 10px 10px 0;" placeholder="Buscar cotización..." value="<?php echo html($searchQuery); ?>">
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
                    <th style="font-weight: 700; color: #475569; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; padding-left: 20px;">Cotización</th>
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
                                <i class="bi bi-inbox fs-1 text-muted" style="opacity: 0.6;"></i>
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
                        <tr class="ticket-row" style="background: #fff; cursor: pointer; transition: all 0.2s;" onclick="window.location='cotizaciones.php?id=<?php echo $q['id']; ?>'">
                            <td style="vertical-align: middle; padding: 18px 12px 18px 20px;">
                                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                                    <a class="ticket-title" href="cotizaciones.php?id=<?php echo $q['id']; ?>" style="font-weight: 800; font-size: 1.05rem; color: #60a5fa; text-decoration: none;">
                                        <i class="bi bi-hash" style="opacity: 0.5;"></i><?php echo $q['id']; ?>
                                    </a>
                                    <div class="d-md-none text-muted ms-auto" style="font-size:0.75rem; font-weight:600;">
                                        <?php echo date('d/m/Y', strtotime($q['created_at'])); ?>
                                    </div>
                                </div>
                                <div class="ticket-subject" style="font-weight: 600; color: #1e293b; font-size: 0.95rem; margin-bottom: 8px; line-height: 1.4; display: block; max-width: 55ch; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; text-transform: none;">
                                    <?php echo html($q['title']); ?>
                                </div>
                                <div style="display: flex; align-items: center; font-size: 0.8rem; color: #64748b;">
                                    <span style="display:inline-flex; align-items:center; gap:5px;">
                                        <i class="bi bi-headset" style="color:#94a3b8;"></i> Agente: <strong style="color: #475569; font-weight:600;"><?php echo html($q['staff_name'] ?: 'Sin asignar'); ?></strong>
                                    </span>
                                </div>

                                <!-- Mobile Info -->
                                <div class="d-md-none mt-3" style="display:flex; gap:8px; flex-direction:column;">
                                    <div style="font-size: 0.85rem; color: #475569; display:flex; align-items:center; gap:6px;">
                                        <i class="bi bi-building" style="color:#cbd5e1;"></i> <strong><?php echo html($q['org_name'] ?: 'N/A'); ?></strong>
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
                                    <div style="display:flex; flex-direction:column;">
                                        <span style="font-weight: 700; color: #334155; font-size: 0.9rem;"><?php echo html($q['org_name'] ?: 'N/A'); ?></span>
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
                                <a href="cotizaciones.php?id=<?php echo $q['id']; ?>" class="btn btn-sm" style="background: transparent; color: #94a3b8; border: none; font-size: 1.2rem; transition: all 0.2s; display: inline-flex; align-items: center;" onmouseover="this.style.color='#60a5fa'" onmouseout="this.style.color='#94a3b8'">
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if (isset($totalPages) && $totalPages > 1): ?>
    <div class="d-flex justify-content-between align-items-center p-3 mt-2 pagination-container" style="background: #fff; border-radius: 12px; border: 1px solid #e2e8f0;">
        <div class="text-muted" style="font-size: 0.85rem; font-weight: 600;">
            Página <?php echo $page; ?> de <?php echo $totalPages; ?> (Total: <?php echo $totalQuotes; ?>)
        </div>
        <ul class="pagination pagination-sm m-0" style="gap: 4px;">
            <?php 
            $qs = $_GET;
            if ($page > 1): 
                $qs['p'] = $page - 1;
            ?>
                <li class="page-item"><a class="page-link rounded-2" style="border: none; color: #64748b; font-weight: 700;" href="?<?php echo http_build_query($qs); ?>"><i class="bi bi-chevron-left"></i></a></li>
            <?php else: ?>
                <li class="page-item disabled"><span class="page-link rounded-2" style="border: none; background: transparent;"><i class="bi bi-chevron-left"></i></span></li>
            <?php endif; ?>
            
            <?php for ($i = 1; $i <= $totalPages; $i++): 
                $qs['p'] = $i;
            ?>
                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                    <a class="page-link rounded-2" style="<?php echo $i === $page ? 'background: #3b82f6; border-color: #3b82f6; font-weight: 700;' : 'border: none; color: #64748b; font-weight: 600;'; ?>" href="?<?php echo http_build_query($qs); ?>"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>

            <?php 
            if ($page < $totalPages): 
                $qs['p'] = $page + 1;
            ?>
                <li class="page-item"><a class="page-link rounded-2" style="border: none; color: #64748b; font-weight: 700;" href="?<?php echo http_build_query($qs); ?>"><i class="bi bi-chevron-right"></i></a></li>
            <?php else: ?>
                <li class="page-item disabled"><span class="page-link rounded-2" style="border: none; background: transparent;"><i class="bi bi-chevron-right"></i></span></li>
            <?php endif; ?>
        </ul>
    </div>
    <?php endif; ?>
</div>
