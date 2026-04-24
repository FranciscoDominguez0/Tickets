<div class="tickets-shell">
    <?php if (isset($_GET['id']) && !$ticketView): ?>
        <div class="alert alert-warning">Ticket no encontrado.</div>
    <?php endif; ?>

    <?php if (!empty($bulk_errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($bulk_errors as $e): ?>
                    <li><?php echo html($e); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <?php if ($bulk_success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php echo html($bulk_success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div id="bulkClientAlert" class="alert alert-warning d-none" role="alert"></div>

    <div id="bulkLoadingOverlay" class="d-none" style="position:fixed; inset:0; background:rgba(15,23,42,0.45); z-index: 3000;">
        <div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); background:#fff; border-radius:14px; padding:16px 18px; border:1px solid #e2e8f0; box-shadow:0 16px 40px rgba(0,0,0,0.25); min-width: 260px; text-align:center;">
            <div class="spinner-border text-primary" role="status" style="width:2.25rem; height:2.25rem;"></div>
            <div id="bulkLoadingText" style="margin-top:10px; font-weight:800; color:#0f172a;">Procesando…</div>
            <div style="margin-top:4px; color:#64748b; font-size:0.9rem;">Por favor espera</div>
        </div>
    </div>

    <div class="tickets-header">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2">
            <div>
                <h1>Tickets</h1>
                <div class="sub">Abiertos: <strong><?php echo $countOpen; ?></strong> · Sin asignar: <strong><?php echo $countUnassigned; ?></strong> · Míos: <strong><?php echo $countMine; ?></strong><?php if ($topicFilterAvailable && $selectedTopicId > 0): ?> · Tema: <strong><?php echo html($selectedTopicName ?: ('#' . (int)$selectedTopicId)); ?></strong> (Total: <strong><?php echo (int)$countSelectedTopic; ?></strong>)<?php endif; ?></div>
            </div>
            <?php if (roleHasPermission('ticket.create')): ?>
                <a href="tickets.php?a=open" class="btn-new"><i class="bi bi-plus-lg me-1"></i> Nuevo</a>
            <?php endif; ?>
        </div>
    </div>

    <form method="post" id="bulkForm">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <input type="hidden" name="do" id="bulk_do" value="">
        <input type="hidden" name="confirm" id="bulk_confirm" value="0">
        <input type="hidden" name="current_filter" value="<?php echo html($filterKey); ?>">
        <input type="hidden" name="current_q" value="<?php echo html($query); ?>">
        <input type="hidden" name="current_topic_id" value="<?php echo (int)$selectedTopicId; ?>">
        <input type="hidden" name="bulk_staff_id" id="bulk_staff_id" value="">
        <input type="hidden" name="bulk_status_id" id="bulk_status_id" value="">
        <input type="hidden" id="bulk_staff_label" value="">
        <input type="hidden" id="bulk_status_label" value="">

        <?php
        $canBulkAssign = roleHasPermission('ticket.assign');
        $canBulkEdit = roleHasPermission('ticket.edit');
        $canBulkClose = roleHasPermission('ticket.close');
        $canBulkDelete = roleHasPermission('ticket.delete');
        $bulkStatusLocked = !$canBulkEdit && !$canBulkClose;
        ?>

        <!-- Nueva barra de acciones contextuales (se muestra al seleccionar tickets) -->
        <div id="selectionActionBar" class="tickets-selection-bar">
            <div class="selection-info">
                <span class="count-badge" id="selectedCountBadge">0</span>
                <span id="selectedCountText">tickets seleccionados</span>
            </div>
            <div class="bulk-actions">
                <div class="btn-group">
                    <button type="button" class="btn btn-bulk" title="<?php echo $canBulkAssign ? 'Asignar a...' : 'Sin permiso para asignar'; ?>" <?php echo $canBulkAssign ? 'data-bs-toggle="dropdown"' : 'disabled'; ?>>
                        <i class="bi bi-person-fill"></i> Asignar
                    </button>
                    <ul class="dropdown-menu" id="bulkAssignMenu">
                        <li id="bulkAssignEmptyItem" class="d-none"><span class="dropdown-item-text text-muted" style="font-weight:700;">Debes seleccionar un ticket</span></li>
                        <li id="bulkAssignUnassignItem"><a class="dropdown-item" href="#" data-action="tickets-bulk-assign" data-staff-id="0" data-staff-label="— Sin asignar —">— Sin asignar —</a></li>
                        <li id="bulkAssignDivider"><hr class="dropdown-divider"></li>
                        <?php foreach ($staffOptions as $s): ?>
                            <?php $sn = trim($s['firstname'] . ' ' . $s['lastname']); ?>
                            <li><a class="dropdown-item bulk-assign-staff-item" href="#" data-action="tickets-bulk-assign" data-staff-id="<?php echo (int) $s['id']; ?>" data-staff-label="<?php echo html($sn); ?>" data-staff-dept-id="<?php echo (int)($s['dept_id'] ?? 0); ?>"><?php echo html($sn); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div class="btn-group">
                    <button type="button" class="btn btn-bulk" title="<?php echo $bulkStatusLocked ? 'Sin permiso para cambiar estado' : 'Cambiar estado a...'; ?>" <?php echo $bulkStatusLocked ? 'disabled' : 'data-bs-toggle="dropdown"'; ?>>
                        <i class="bi bi-flag-fill"></i> Estado
                    </button>
                    <ul class="dropdown-menu">
                        <?php foreach ($statusOptions as $st): ?>
                            <li><a class="dropdown-item" href="#" data-action="tickets-bulk-status" data-status-id="<?php echo (int) $st['id']; ?>" data-status-label="<?php echo html($st['name']); ?>"><?php echo html($st['name']); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <button type="button" class="btn btn-bulk btn-bulk-danger" data-action="tickets-bulk-delete" title="<?php echo $canBulkDelete ? 'Eliminar permanentemente' : 'Sin permiso para eliminar'; ?>" <?php echo $canBulkDelete ? '' : 'disabled'; ?>>
                    <i class="bi bi-trash-fill"></i> Eliminar
                </button>
            </div>
        </div>

        <div class="tickets-panel" data-filter-key="<?php echo html($filterKey); ?>" data-general-dept-id="<?php echo (int)$generalDeptId; ?>">
            <div class="tickets-toolbar">
                <div class="tickets-filters">
                    <div class="dropdown filter-dd">
                        <button class="btn btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="bi bi-funnel"></i>
                            <?php echo html($filters[$filterKey]['label']); ?>
                        </button>
                        <ul class="dropdown-menu">
                            <?php $topicParam = ($topicFilterAvailable && $selectedTopicId > 0) ? ('&topic_id=' . (int)$selectedTopicId) : ''; ?>
                            <li><a class="dropdown-item <?php echo $filterKey === 'open' ? 'active' : ''; ?>" href="tickets.php?filter=open<?php echo $query !== '' ? '&q=' . urlencode($query) : ''; ?><?php echo $topicParam; ?>">Abiertos</a></li>
                            <li><a class="dropdown-item <?php echo $filterKey === 'unassigned' ? 'active' : ''; ?>" href="tickets.php?filter=unassigned<?php echo $query !== '' ? '&q=' . urlencode($query) : ''; ?><?php echo $topicParam; ?>">Sin asignar</a></li>
                            <li><a class="dropdown-item <?php echo $filterKey === 'mine' ? 'active' : ''; ?>" href="tickets.php?filter=mine<?php echo $query !== '' ? '&q=' . urlencode($query) : ''; ?><?php echo $topicParam; ?>">Asignados a mí</a></li>
                            <li><a class="dropdown-item <?php echo $filterKey === 'closed' ? 'active' : ''; ?>" href="tickets.php?filter=closed<?php echo $query !== '' ? '&q=' . urlencode($query) : ''; ?><?php echo $topicParam; ?>">Cerrados</a></li>
                            <li><a class="dropdown-item <?php echo $filterKey === 'all' ? 'active' : ''; ?>" href="tickets.php?filter=all<?php echo $query !== '' ? '&q=' . urlencode($query) : ''; ?><?php echo $topicParam; ?>">Todos</a></li>
                        </ul>
                    </div>

                    <?php if ($topicFilterAvailable): ?>
                        <select class="form-select form-select-sm" id="ticketTopicSelect" aria-label="Filtrar por tema">
                            <option value="0">Todos los temas</option>
                            <?php foreach ($topicOptions as $tp): ?>
                                <?php $tpId = (int)($tp['id'] ?? 0); ?>
                                <option value="<?php echo $tpId; ?>" <?php echo $tpId === (int)$selectedTopicId ? 'selected' : ''; ?>><?php echo html((string)($tp['name'] ?? '')); ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>

                    <div id="ticketDateRange" style="display:inline-flex; align-items:center; gap:6px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:4px 8px; height:32px;">
                        <i class="bi bi-calendar3" style="color:#64748b; font-size:0.8rem; flex-shrink:0;"></i>
                        <input type="date" id="dateFromInput" value="<?php echo html($dateFrom); ?>" title="Desde"
                               style="border:none; background:transparent; font-size:0.82rem; color:#334155; outline:none; width:110px; padding:0;">
                        <span style="color:#cbd5e1; font-size:0.75rem; flex-shrink:0;">→</span>
                        <input type="date" id="dateToInput" value="<?php echo html($dateTo); ?>" title="Hasta"
                               style="border:none; background:transparent; font-size:0.82rem; color:#334155; outline:none; width:110px; padding:0;">
                        <button type="button" id="applyDateRange"
                                style="display:inline-flex; align-items:center; gap:3px; background:#2563eb; color:#fff; border:none; border-radius:6px; padding:2px 10px; font-size:0.78rem; font-weight:600; cursor:pointer; white-space:nowrap;">
                            <i class="bi bi-check-lg"></i> Aplicar
                        </button>
                        <?php if ($dateFrom !== $defaultDateFrom || $dateTo !== $defaultDateTo): ?>
                        <a href="tickets.php?filter=<?php echo html($filterKey); ?><?php echo $query !== '' ? '&q='.urlencode($query) : ''; ?>"
                           title="Restablecer al mes actual" style="color:#94a3b8; font-size:0.9rem; flex-shrink:0; line-height:1;">
                            <i class="bi bi-x-circle"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                    <script>
                    (function(){
                        var btn = document.getElementById('applyDateRange');
                        if (!btn) return;
                        btn.addEventListener('click', function(){
                            var from = document.getElementById('dateFromInput').value;
                            var to   = document.getElementById('dateToInput').value;
                            var url  = new URL(window.location.href);
                            url.searchParams.delete('p');
                            if (from) url.searchParams.set('date_from', from); else url.searchParams.delete('date_from');
                            if (to)   url.searchParams.set('date_to',   to);   else url.searchParams.delete('date_to');
                            window.location.href = url.toString();
                        });
                        ['dateFromInput','dateToInput'].forEach(function(id){
                            var el = document.getElementById(id);
                            if (el) el.addEventListener('keydown', function(e){ if (e.key==='Enter') btn.click(); });
                        });
                    })();
                    </script>
                </div>
                <div class="tickets-search">
                    <div class="input-group">
                        <span class="input-group-text bg-white" style="border-right: none; border-radius: 10px 0 0 10px;"><i class="bi bi-search"></i></span>
                        <input type="text" id="ticketSearchInput" class="form-control" style="border-left: none; border-radius: 0 10px 10px 0;" placeholder="Buscar ticket y presione Enter..." value="<?php echo html($query); ?>">
                    </div>
                </div>
            </div>
        </div>

    <div class="tickets-table-wrap">
        <table class="table table-hover tickets-table mb-0" id="ticketsTable">
            <thead class="table-light" style="border-bottom: 2px solid #e2e8f0; background-color: #f8fafc;">
                <tr>
                    <th class="check-cell" style="width: 44px; text-align: center; vertical-align: middle;"><input type="checkbox" class="form-check-input" id="check_all"></th>
                    <th style="font-weight: 700; color: #475569; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; padding-left: 0;">Asunto del Ticket</th>
                    <th class="d-none d-lg-table-cell" style="font-weight: 700; color: #475569; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em;">Cliente</th>
                    <th class="d-none d-md-table-cell" style="font-weight: 700; color: #475569; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em;">Estado</th>
                    <th class="d-none d-lg-table-cell" style="font-weight: 700; color: #475569; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em;">Última Actividad</th>
                    <th style="width: 80px; text-align: right; font-weight: 700; color: #475569; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em;"></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tickets)): ?>
                    <tr>
                        <td colspan="7">
                            <div class="empty-state">
                                <i class="bi bi-inbox" style="font-size: 2rem; opacity: 0.6;"></i>
                                <div class="mt-2">No hay tickets para esta vista.</div>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($tickets as $t): ?>
                        <?php
                        $clientName = trim($t['user_first'] . ' ' . $t['user_last']) ?: $t['user_email'];
                        $staffName = trim($t['staff_first'] . ' ' . $t['staff_last']);
                        $statusColor = $t['status_color'] ?: '#2563eb';
                        $priorityColor = $t['priority_color'] ?: '#94a3b8';

                        $tidRow = (int)($t['id'] ?? 0);
                        $createdTs = !empty($t['created']) ? @strtotime((string)$t['created']) : 0;
                        $sidNew = (int)($_SESSION['staff_id'] ?? 0);
                        $sinceKey = $sidNew > 0 ? ('tickets_new_since_' . $sidNew) : '';
                        $newSince = ($sinceKey !== '' && isset($_SESSION[$sinceKey]) && is_numeric($_SESSION[$sinceKey])) ? (int)$_SESSION[$sinceKey] : 0;
                        $isAfterSince = ($newSince > 0 && $createdTs > 0 && $createdTs >= $newSince);
                        $isNew = ($tidRow > 0 && $isAfterSince && !isset($seenIds[$tidRow]));
                        ?>
                        <?php
                            $backRel = 'tickets.php';
                            if (!empty($_SERVER['REQUEST_URI'])) {
                                $u = (string)$_SERVER['REQUEST_URI'];
                                $path = (string)parse_url($u, PHP_URL_PATH);
                                $reqQueryStr = (string)parse_url($u, PHP_URL_QUERY);
                                $rel = ltrim($path, '/');
                                $needle = 'upload/scp/';
                                $pos = strpos($rel, $needle);
                                if ($pos !== false) {
                                    $rel = substr($rel, $pos + strlen($needle));
                                }
                                $rel = trim($rel);
                                if ($rel !== '') {
                                    $backRel = $rel . ($reqQueryStr !== '' ? ('?' . $reqQueryStr) : '');
                                }
                            }
                            $ticketHref = 'tickets.php?id=' . (int)$t['id'] . '&back=' . urlencode($backRel);
                        ?>
                        <tr class="ticket-row" style="background: #fff; cursor: pointer; transition: all 0.2s;" data-ticket-id="<?php echo (int)$t['id']; ?>" data-ticket-dept-id="<?php echo (int)($t['dept_id'] ?? 0); ?>" onclick="if(!event.target.closest('.check-cell') && !event.target.closest('a') && !event.target.closest('button')) window.location='<?php echo html($ticketHref); ?>';">
                            <td class="check-cell" style="vertical-align: middle; text-align: center; width: 44px;">
                                <input class="form-check-input ticket-check" type="checkbox" name="ticket_ids[]" value="<?php echo (int) $t['id']; ?>" data-ticket-dept-id="<?php echo (int)($t['dept_id'] ?? 0); ?>" style="cursor: pointer; width: 1.1em; height: 1.1em;">
                            </td>
                            <td style="vertical-align: middle; padding: 18px 12px 18px 0;">
                                <div style="display: flex; align-items: baseline; gap: 8px; margin-bottom: 6px;">
                                    <a class="ticket-title ticket-preview-trigger" href="<?php echo html($ticketHref); ?>" data-ticket-id="<?php echo (int)$t['id']; ?>" style="font-weight: 800; font-size: 1.05rem; color: #2563eb; text-decoration: none;">
                                        <i class="bi bi-hash" style="opacity: 0.5;"></i><?php echo html($t['ticket_number']); ?>
                                    </a>
                                    <?php if ($isNew): ?>
                                        <span class="badge" style="background:#ef4444; color: #fff; font-size: 0.65rem; padding: 4px 6px; letter-spacing: 0.05em; text-transform: uppercase; border-radius: 6px; box-shadow: 0 2px 4px rgba(239, 68, 68, 0.2);">Nuevo</span>
                                    <?php endif; ?>
                                    <div class="d-md-none text-muted ms-auto" style="font-size:0.75rem; font-weight:600;">
                                        <?php echo formatDate($t['updated'] ?: $t['created']); ?>
                                    </div>
                                </div>
                                <div class="ticket-subject" style="font-weight: 600; color: #1e293b; font-size: 0.95rem; margin-bottom: 8px; line-height: 1.4; display: block; max-width: 55ch; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; text-transform: none;">
                                    <?php echo html(function_exists('cleanPlainText') ? cleanPlainText((string)($t['subject'] ?? '')) : (string)($t['subject'] ?? '')); ?>
                                </div>
                                <div style="display: flex; align-items: center; font-size: 0.8rem; color: #64748b;">
                                    <span style="display:inline-flex; align-items:center; gap:5px;">
                                        <i class="bi bi-headset" style="color:#94a3b8;"></i> Asignado a: <strong style="color: #475569; font-weight:600;"><?php echo $staffName ?: 'Sin asignar'; ?></strong>
                                    </span>
                                </div>
                                
                                <!-- Mobile Info -->
                                <div class="d-md-none mt-3" style="display:flex; gap:8px; flex-direction:column;">
                                    <div style="font-size: 0.85rem; color: #475569; display:flex; align-items:center; gap:6px;">
                                        <i class="bi bi-person-fill" style="color:#cbd5e1;"></i> <strong><?php echo html($clientName); ?></strong>
                                    </div>
                                    <div style="display:flex; gap:6px; flex-wrap:wrap; margin-top: 2px;">
                                        <span class="chip chip-status" style="background: <?php echo html($statusColor); ?>15; color: <?php echo html($statusColor); ?>; border: 1px solid <?php echo html($statusColor); ?>33; font-size:0.7rem; border-radius:6px; padding:3px 8px; font-weight:700;">
                                            <?php echo html($t['status_name']); ?>
                                        </span>
                                        <?php if (!empty($t['closed']) && (int)($t['has_report'] ?? 0) === 1): ?>
                                            <?php if (($t['billing_status'] ?? 'pending') === 'confirmed'): ?>
                                                <span class="chip" style="background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; font-size:0.7rem; border-radius:6px; padding:3px 8px; font-weight:700;">
                                                    <i class="bi bi-patch-check-fill"></i> Facturado
                                                </span>
                                            <?php else: ?>
                                                <span class="chip" style="background: #fef9c3; color: #854d0e; border: 1px solid #fef08a; font-size:0.7rem; border-radius:6px; padding:3px 8px; font-weight:700;">
                                                    <i class="bi bi-clock-history"></i> Pendiente Facturación
                                                </span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="d-none d-lg-table-cell" style="vertical-align: middle;">
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <div style="width: 32px; height: 32px; border-radius: 50%; background: #f1f5f9; color: #64748b; display: flex; align-items: center; justify-content: center; font-size: 1rem; flex-shrink: 0;">
                                        <i class="bi bi-person-fill"></i>
                                    </div>
                                    <div style="display:flex; flex-direction:column;">
                                        <span style="font-weight: 700; color: #334155; font-size: 0.9rem;"><?php echo html($clientName); ?></span>
                                    </div>
                                </div>
                            </td>
                            <td class="d-none d-md-table-cell" style="vertical-align: middle;">
                                <div style="display:flex; flex-direction:column; gap:6px; align-items: flex-start;">
                                    <span class="chip chip-status" style="background: <?php echo html($statusColor); ?>15; color: <?php echo html($statusColor); ?>; border: 1px solid <?php echo html($statusColor); ?>33; padding: 6px 14px; font-weight: 700; letter-spacing: 0.03em; border-radius: 8px; font-size: 0.8rem; text-transform: uppercase;">
                                        <i class="bi bi-record-circle-fill" style="font-size: 0.6rem; margin-right: 4px; vertical-align: middle;"></i> <?php echo html($t['status_name']); ?>
                                    </span>
                                    <?php if (!empty($t['closed']) && (int)($t['has_report'] ?? 0) === 1): ?>
                                        <?php if (($t['billing_status'] ?? 'pending') === 'confirmed'): ?>
                                            <span class="chip" style="background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; padding: 4px 10px; font-weight: 700; font-size: 0.75rem; border-radius: 6px; margin-top: 4px;">
                                                <i class="bi bi-patch-check-fill"></i> Facturado
                                            </span>
<?php else: ?>
                                            <span class="chip" style="background: #fef9c3; color: #854d0e; border: 1px solid #fef08a; padding: 4px 10px; font-weight: 700; font-size: 0.75rem; border-radius: 6px; margin-top: 4px;">
                                                <i class="bi bi-clock-history"></i> Pendiente Facturación
                                            </span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="d-none d-lg-table-cell" style="vertical-align: middle;">
                                <div style="color:#64748b; font-size: 0.85rem; font-weight: 600; display:flex; align-items:center; gap:6px;">
                                    <i class="bi bi-clock-history" style="color:#94a3b8; font-size: 1rem;"></i> 
                                    <span><?php echo formatDate($t['updated'] ?: $t['created']); ?></span>
                                </div>
                            </td>
                            <td style="vertical-align: middle; text-align: right; padding-right: 12px;">
                                <button type="button" class="btn btn-sm" style="background: transparent; color: #94a3b8; border: none; font-size: 1.2rem; transition: all 0.2s;" onmouseover="this.style.color='#2563eb'" onmouseout="this.style.color='#94a3b8'">
                                    <i class="bi bi-chevron-right"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php
    $basePageParams = ['filter' => $filterKey];
    if ($query !== '') $basePageParams['q'] = $query;
    if ($topicFilterAvailable && $selectedTopicId > 0) $basePageParams['topic_id'] = (int)$selectedTopicId;
    if ($dateFrom !== '') $basePageParams['date_from'] = $dateFrom;
    if ($dateTo   !== '') $basePageParams['date_to']   = $dateTo;
    $prevUrl = '';
    $nextUrl = '';
    if ($page > 1) {
        $prevParams = $basePageParams;
        $prevParams['p'] = $page - 1;
        $prevUrl = 'tickets.php?' . http_build_query($prevParams);
    }
    if ($page < $totalPages) {
        $nextParams = $basePageParams;
        $nextParams['p'] = $page + 1;
        $nextUrl = 'tickets.php?' . http_build_query($nextParams);
    }
    ?>
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mt-3">
        <div class="text-muted" style="font-size:0.9rem;">
            Página <?php echo $page; ?> de <?php echo $totalPages; ?>
        </div>
        <div class="d-flex gap-2">
            <?php if ($prevUrl !== ''): ?>
                <a class="btn btn-outline-secondary btn-sm" href="<?php echo html($prevUrl); ?>">
                    <i class="bi bi-chevron-left"></i> Anterior
                </a>
            <?php else: ?>
                <button class="btn btn-outline-secondary btn-sm" type="button" disabled>
                    <i class="bi bi-chevron-left"></i> Anterior
                </button>
            <?php endif; ?>

            <?php if ($nextUrl !== ''): ?>
                <a class="btn btn-outline-secondary btn-sm" href="<?php echo html($nextUrl); ?>">
                    Siguiente <i class="bi bi-chevron-right"></i>
                </a>
            <?php else: ?>
                <button class="btn btn-outline-secondary btn-sm" type="button" disabled>
                    Siguiente <i class="bi bi-chevron-right"></i>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="ticket-hover-preview d-none" id="ticketHoverPreview" role="dialog" aria-hidden="true">
        <div class="ticket-hover-preview-inner">
            <div class="ticket-hover-preview-head">
                <div class="num" id="ticketHoverNumber"></div>
                <div style="display:flex; align-items:center; gap:8px;">
                    <a class="open" id="ticketHoverOpen" href="#" title="Abrir ticket"><i class="bi bi-box-arrow-up-right"></i></a>
                    <button type="button" class="close" id="ticketHoverClose" aria-label="Cerrar" title="Cerrar">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>
            <div class="subject" id="ticketHoverSubject"></div>
            <div class="meta" id="ticketHoverMeta"></div>
            <div class="msg" id="ticketHoverMsg"></div>
            <div class="loading d-none" id="ticketHoverLoading">
                <div class="spinner-border text-primary" role="status" style="width:1.5rem; height:1.5rem;"></div>
                <div class="text">Cargando…</div>
            </div>
        </div>
    </div>
    </form>
</div>

<!-- Modal confirmación acción masiva -->
<div class="modal fade" id="bulkConfirmModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmar acción</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0" id="bulkConfirmText"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="bulkConfirmBtn">Confirmar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal informativo (evita popup del navegador con "localhost") -->
<div class="modal fade" id="bulkInfoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-exclamation-circle text-warning me-2"></i>Atención</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0" id="bulkInfoText"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Entendido</button>
            </div>
        </div>
    </div>
</div>
