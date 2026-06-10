<?php
/** Explorador: organizaciones → usuarios → tickets (portal cliente). */
if (!isset($orgExplorerOrgs) || !is_array($orgExplorerOrgs)) {
    $orgExplorerOrgs = [];
}
$orgExplorerOrgId = (int)($orgExplorerOrgId ?? 0);
$orgExplorerMemberId = (int)($orgExplorerMemberId ?? 0);
$orgExplorerOrgName = (string)($orgExplorerOrgName ?? '');
$orgExplorerMemberName = (string)($orgExplorerMemberName ?? '');
$orgExplorerMembers = isset($orgExplorerMembers) && is_array($orgExplorerMembers) ? $orgExplorerMembers : [];
$orgExplorerTickets = isset($orgExplorerTickets) && is_array($orgExplorerTickets) ? $orgExplorerTickets : [];
$orgBackParams = '';
if ($orgExplorerOrgId > 0) {
    $orgBackParams = '&org_id=' . $orgExplorerOrgId;
}
$orgUsersTotal = (int)($orgUsersTotal ?? 0);
$orgUsersPage = max(1, (int)($orgUsersPage ?? 1));
$orgUsersTotalPages = max(1, (int)($orgUsersTotalPages ?? 1));
$orgTicketsTotal = (int)($orgTicketsTotal ?? 0);
$orgTicketsPage = max(1, (int)($orgTicketsPage ?? 1));
$orgTicketsTotalPages = max(1, (int)($orgTicketsTotalPages ?? 1));
$orgListPerPage = max(1, (int)($orgListPerPage ?? 10));
$orgUsersPaginationParams = '&view=org&org_id=' . (int)$orgExplorerOrgId;
$orgTicketsPaginationParams = '&view=org&org_id=' . (int)$orgExplorerOrgId . '&member_id=' . (int)$orgExplorerMemberId;
$orgExplorerListMode = (isset($orgExplorerListMode) && in_array((string)$orgExplorerListMode, ['all', 'quotes'], true)) ? (string)$orgExplorerListMode : 'users';
$orgAllTicketsTotal = (int)($orgAllTicketsTotal ?? 0);
$orgAllTicketsPage = max(1, (int)($orgAllTicketsPage ?? 1));
$orgAllTicketsTotalPages = max(1, (int)($orgAllTicketsTotalPages ?? 1));
$orgExplorerAllTickets = isset($orgExplorerAllTickets) && is_array($orgExplorerAllTickets) ? $orgExplorerAllTickets : [];
$orgMonthQuery = isset($ticketMonthQuery) ? (string)$ticketMonthQuery : '';
$orgAllTicketsPaginationParams = '&view=org&org_id=' . (int)$orgExplorerOrgId . '&list=all' . $orgMonthQuery;
$orgTicketsPaginationParams .= $orgMonthQuery;
$orgOrgBaseUrl = 'tickets.php?view=org&amp;org_id=' . (int)$orgExplorerOrgId;
$orgOrgBaseUrlAll = $orgOrgBaseUrl . '&amp;list=all' . str_replace('&', '&amp;', $orgMonthQuery);
$orgCssV = (int)@filemtime(__DIR__ . '/../css/client-org-explorer.css');
if ($orgCssV <= 0) {
    $orgCssV = 1;
}
$orgLoggedUserId = (int)($orgLoggedUserId ?? ($_SESSION['user_id'] ?? 0));
?>
<link rel="stylesheet" href="css/client-org-explorer.css?v=<?php echo $orgCssV; ?>">
<style>
    /* CSS para soportar el cambio dinámico de modo oscuro en la vista de cotizaciones */
    .quote-title { color: #212529; }
    .quote-date { color: #6c757d; }
    .quote-icon { color: #6c757d; }
    
    body.dark-mode .quote-title { color: #f8f9fa !important; }
    body.dark-mode .quote-date { color: #adb5bd !important; }
    body.dark-mode .quote-icon { color: #f8f9fa !important; }
    body.dark-mode .org-explorer-row.unread-item { background: #212529 !important; border-color: #0d6efd !important; }
    .org-explorer-row.unread-item { background: #f8f9fa; border-color: #0d6efd; }
</style>

<div class="org-explorer-wrap">
    <div class="page-header org-explorer-header" style="margin-top: 0;">
        <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
            <div>
                <h2 class="mb-1 org-explorer-title">Tickets por organización</h2>
                <div class="sub org-explorer-sub">Consulta tickets de usuarios en tus organizaciones (solo lectura).</div>
            </div>
            <div class="org-explorer-actions">
                <a href="tickets.php" class="btn-org-ghost"><i class="bi bi-inbox"></i> Mis tickets</a>
            </div>
        </div>
    </div>

    <nav aria-label="breadcrumb" class="org-breadcrumb-bar mb-3">
        <ol class="breadcrumb mb-0 org-breadcrumb-inner">
            <li class="breadcrumb-item">
                <a href="tickets.php?view=org">Organizaciones</a>
            </li>
            <?php if ($orgExplorerOrgId > 0 && $orgExplorerOrgName !== ''): ?>
            <li class="breadcrumb-item <?php echo ($orgExplorerMemberId <= 0 && $orgExplorerListMode !== 'all') ? 'active' : ''; ?>">
                <?php if ($orgExplorerMemberId > 0 || $orgExplorerListMode === 'all'): ?>
                    <a href="<?php echo $orgOrgBaseUrl; ?>"><?php echo html($orgExplorerOrgName); ?></a>
                <?php else: ?>
                    <?php echo html($orgExplorerOrgName); ?>
                <?php endif; ?>
            </li>
            <?php endif; ?>
            <?php if ($orgExplorerOrgId > 0 && $orgExplorerMemberId <= 0 && $orgExplorerListMode === 'all'): ?>
            <li class="breadcrumb-item active">Todos los tickets</li>
            <?php endif; ?>
            <?php if ($orgExplorerMemberId > 0 && $orgExplorerMemberName !== ''): ?>
            <li class="breadcrumb-item active"><?php echo html($orgExplorerMemberName); ?></li>
            <?php endif; ?>
        </ol>
    </nav>

    <div class="panel org-panel">
        <?php if ($orgExplorerOrgId <= 0): ?>
            <?php if (empty($orgExplorerOrgs)): ?>
                <div class="org-empty">
                    <i class="bi bi-building-x" aria-hidden="true"></i>
                    <p class="text-muted mb-0">No tienes organizaciones asignadas. Contacta al soporte.</p>
                </div>
            <?php else: ?>
                <div class="org-list-section">
                    <div class="org-panel-head">
                        <h3><i class="bi bi-building text-danger me-1"></i> Tus organizaciones</h3>
                        <span class="org-count-badge"><?php echo count($orgExplorerOrgs); ?></span>
                    </div>
                    <div class="list-group list-group-flush org-explorer-list org-explorer-list-orgs">
                        <?php foreach ($orgExplorerOrgs as $o): ?>
                            <?php $oid = (int)($o['organization_id'] ?? 0); if ($oid <= 0) continue; ?>
                            <a href="tickets.php?view=org&amp;org_id=<?php echo $oid; ?>" class="list-group-item list-group-item-action org-explorer-row">
                                <span class="org-explorer-icon"><i class="bi bi-building"></i></span>
                                <span class="org-explorer-row-body">
                                    <span class="org-explorer-row-title"><?php echo html((string)($o['name'] ?? '')); ?></span>
                                </span>
                                <span class="org-explorer-row-action"><i class="bi bi-chevron-right"></i></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

        <?php elseif ($orgExplorerMemberId <= 0): ?>
            <div class="org-view-tabs">
                <a href="<?php echo $orgOrgBaseUrl; ?>&amp;list=users" class="org-view-tab <?php echo $orgExplorerListMode === 'users' ? 'active' : ''; ?>">
                    <i class="bi bi-people"></i> Por usuario
                </a>
                <a href="<?php echo $orgOrgBaseUrlAll; ?>" class="org-view-tab <?php echo $orgExplorerListMode === 'all' ? 'active' : ''; ?>">
                    <i class="bi bi-collection"></i> Todos los tickets
                </a>
                <a href="<?php echo $orgOrgBaseUrl; ?>&amp;list=quotes" class="org-view-tab <?php echo $orgExplorerListMode === 'quotes' ? 'active' : ''; ?>">
                    <i class="bi bi-file-earmark-text"></i> Cotizaciones
                </a>
            </div>

            <?php if ($orgExplorerListMode === 'all'): ?>
                <?php
                $ticketMonthFilterHidden = [
                    'view' => 'org',
                    'org_id' => (string)$orgExplorerOrgId,
                    'list' => 'all',
                ];
                $ticketMonthFilterResetUrl = 'tickets.php?view=org&org_id=' . (int)$orgExplorerOrgId . '&list=all';
                $ticketMonthFilterResetPage = 'oat';
                $ticketMonthPickerCompact = true;
                ?>
                <div class="org-list-section">
                    <div class="org-panel-head org-panel-head--tickets">
                        <div class="org-panel-head__left">
                            <h3><i class="bi bi-ticket-perforated text-danger me-1"></i> Todos los tickets</h3>
                        </div>
                        <div class="org-panel-head__actions">
                            <?php require __DIR__ . '/ticket-month-filter.inc.php'; ?>
                            <span class="org-count-badge"><?php echo $orgAllTicketsTotal; ?></span>
                        </div>
                    </div>
                <?php if ($orgAllTicketsTotal <= 0): ?>
                    <div class="org-empty">
                        <i class="bi bi-inbox" aria-hidden="true"></i>
                        <p class="text-muted mb-0"><?php echo !empty($ticketMonthFilter) ? 'No hay tickets en el mes seleccionado.' : 'No hay tickets en esta organización.'; ?></p>
                    </div>
                <?php else: ?>
                        <div class="list-group list-group-flush org-explorer-list org-explorer-list-tickets">
                            <?php foreach ($orgExplorerAllTickets as $tk): ?>
                                <?php
                                $tid = (int)($tk['id'] ?? 0);
                                if ($tid <= 0) continue;
                                $ownerId = (int)($tk['owner_user_id'] ?? 0);
                                $ownerName = trim((string)($tk['owner_firstname'] ?? '') . ' ' . (string)($tk['owner_lastname'] ?? ''));
                                if ($ownerName === '') {
                                    $ownerName = (string)($tk['owner_email'] ?? 'Usuario');
                                }
                                $ownerIsYou = ($orgLoggedUserId > 0 && $ownerId === $orgLoggedUserId);
                                $href = 'view-ticket.php?id=' . $tid . '&from=org&org_id=' . $orgExplorerOrgId . '&list=all&member_id=' . $ownerId;
                                if ($orgAllTicketsPage > 1) {
                                    $href .= '&oat=' . $orgAllTicketsPage;
                                }
                                if ($orgMonthQuery !== '') {
                                    $href .= $orgMonthQuery;
                                }
                                ?>
                                <a href="<?php echo html($href); ?>" class="list-group-item list-group-item-action org-explorer-row org-explorer-row-ticket">
                                    <span class="org-ticket-num">#<?php echo html((string)($tk['ticket_number'] ?? '')); ?></span>
                                    <span class="org-explorer-row-body">
                                        <span class="org-explorer-row-title"><?php echo html((string)($tk['subject'] ?? '')); ?></span>
                                        <span class="org-explorer-row-sub org-ticket-owner">
                                            <i class="bi bi-person" aria-hidden="true"></i>
                                            <?php echo html($ownerName); ?><?php if ($ownerIsYou): ?><span class="org-you-badge org-you-badge--inline">Tú</span><?php endif; ?>
                                            <?php if (!empty($tk['created'])): ?>
                                                <span class="org-ticket-owner-sep">·</span>
                                                <?php echo html(formatDate((string)($tk['created'] ?? ''))); ?>
                                            <?php endif; ?>
                                        </span>
                                    </span>
                                    <?php if (($tk['approval_status'] ?? '') === 'pending'): ?>
                                        <span class="org-status-badge" style="background: #fef3c7; color: #d97706; border: 1px solid #fcd34d;">
                                            <i class="bi bi-shield-lock-fill"></i> Pendiente aprobación
                                        </span>
                                    <?php else: ?>
                                        <?php if (!empty($tk['status_name'])): ?>
                                            <?php
                                                $orgStatusColor = normalizeTicketHexColor((string)($tk['status_color'] ?? ''), '#64748b');
                                                $orgStatusBadgeStyle = clientTicketBadgeStyle($orgStatusColor, !empty($isDarkMode));
                                            ?>
                                            <span class="org-status-badge" style="<?php echo html($orgStatusBadgeStyle); ?>">
                                                <?php echo html((string)$tk['status_name']); ?>
                                            </span>
                                        <?php endif; ?>

                                    <?php endif; ?>
                                    <span class="org-explorer-row-cta btn-org-primary"><i class="bi bi-eye"></i> Ver hilo</span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                        <?php if ($orgAllTicketsTotalPages > 1 && function_exists('renderModernPagination')): ?>
                            <?php echo renderModernPagination($orgAllTicketsPage, $orgAllTicketsTotalPages, $orgAllTicketsPaginationParams, 'oat'); ?>
                        <?php endif; ?>
                <?php endif; ?>
                </div>

            <?php elseif ($orgExplorerListMode === 'quotes'): ?>
                <div class="org-list-section">
                    <div class="org-panel-head org-panel-head--tickets" style="flex-wrap: wrap;">
                        <div class="org-panel-head__left">
                            <h3><i class="bi bi-file-earmark-text text-danger me-1"></i> Cotizaciones</h3>
                        </div>
                        <div class="org-panel-head__actions d-flex align-items-center gap-2">
                            <span class="org-count-badge"><?php echo $orgQuotesTotal; ?></span>
                            <!-- Botón de solicitar removido para el jefe -->
                        </div>
                    </div>

                    <?php if ($orgQuotesTotal <= 0): ?>
                        <div class="org-empty">
                            <i class="bi bi-file-earmark-x" aria-hidden="true"></i>
                            <p class="text-muted mb-0">No hay cotizaciones para esta organización.</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush org-explorer-list">
                            <?php foreach ($orgExplorerQuotes as $doc): ?>
                                <?php
                                $isQuote = ($doc['doc_type'] === 'quote');
                                $isUnread = (!$isQuote && empty($doc['is_read']));
                                
                                if ($isQuote) {
                                    $statusColors = [
                                        'draft'    => ['color' => '#94a3b8', 'label' => 'Borrador'],
                                        'pending'  => ['color' => '#64748b', 'label' => 'Pendiente'],
                                        'requested'=> ['color' => '#eab308', 'label' => 'Solicitada'],
                                        'answered' => ['color' => '#3b82f6', 'label' => 'En Revisión'],
                                        'accepted' => ['color' => '#22c55e', 'label' => 'Aceptada'],
                                        'rejected' => ['color' => '#ef4444', 'label' => 'Rechazada']
                                    ];
                                    $st = $statusColors[$doc['status']] ?? $statusColors['draft'];
                                } else {
                                    $st = ['color' => '#64748b', 'label' => 'Informe'];
                                }
                                
                                $href = $isQuote ? "view-quote.php?id=" . $doc['id'] : "informe-jefe.php?id=" . $doc['id'];
                                $icon = $isQuote ? "bi-file-earmark-text" : "bi-megaphone";
                                $unreadClass = $isUnread ? 'unread-item border-primary' : '';
                                ?>
                                <a href="<?php echo $href; ?>" class="list-group-item list-group-item-action org-explorer-row d-flex align-items-center flex-wrap gap-3 <?php echo $unreadClass; ?>" style="<?php echo $isUnread ? 'border-left: 4px solid #ef4444 !important;' : ''; ?>">
                                    <span class="org-ticket-num d-flex flex-column align-items-center justify-content-center" style="min-width:55px; text-align:center;">
                                        <i class="bi <?php echo $icon; ?> fs-5 quote-icon"></i>
                                        <span style="font-size: 0.75rem; font-weight: 800; color: #64748b; margin-top: 2px;">#<?php echo $doc['id']; ?></span>
                                    </span>
                                    <div class="flex-grow-1" style="min-width:200px;">
                                        <div class="fw-bold mb-1 quote-title"><?php echo html($doc['subject'] ?: 'Sin título'); ?></div>
                                        <div class="small quote-date">
                                            <i class="bi bi-calendar3 me-1"></i> <?php echo html(date('d/m/Y h:i A', strtotime($doc['created_at']))); ?>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge rounded-pill" style="background-color: <?php echo $st['color']; ?>1A; color: <?php echo $st['color']; ?>; border: 1px solid <?php echo $st['color']; ?>33; font-weight: 600; padding: 0.5em 0.8em; letter-spacing: 0.02em;">
                                            <?php echo $st['label']; ?>
                                        </span>
                                    </div>
                                    <span class="org-explorer-row-cta btn-org-primary ms-2"><i class="bi bi-eye"></i> Ver</span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                        <?php if ($orgQuotesTotalPages > 1): ?>
                            <?php $orgQuotesPaginationParams = '&view=org&org_id=' . (int)$orgExplorerOrgId . '&list=quotes'; ?>
                            <?php echo renderModernPagination($orgQuotesPage, $orgQuotesTotalPages, $orgQuotesPaginationParams, 'oqp'); ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

            <?php elseif ($orgUsersTotal <= 0): ?>
                <div class="org-empty">
                    <i class="bi bi-person-x" aria-hidden="true"></i>
                    <p class="text-muted mb-0">No hay usuarios en esta organización.</p>
                </div>
            <?php else: ?>
                <div class="org-list-section">
                    <div class="org-panel-head">
                        <div>
                            <h3><i class="bi bi-people text-danger me-1"></i> Usuarios</h3>
                            <div class="org-panel-meta"><?php echo html($orgExplorerOrgName); ?></div>
                        </div>
                        <span class="org-count-badge"><?php echo $orgUsersTotal; ?></span>
                    </div>
                    <div class="list-group list-group-flush org-explorer-list org-explorer-list-users">
                        <?php foreach ($orgExplorerMembers as $m): ?>
                            <?php
                            $mid = (int)($m['id'] ?? 0);
                            if ($mid <= 0) continue;
                            $mName = trim((string)($m['firstname'] ?? '') . ' ' . (string)($m['lastname'] ?? ''));
                            if ($mName === '') $mName = (string)($m['email'] ?? 'Usuario');
                            $isOrgLoggedUser = ($orgLoggedUserId > 0 && $mid === $orgLoggedUserId);
                            ?>
                            <a href="tickets.php?view=org&amp;org_id=<?php echo $orgExplorerOrgId; ?>&amp;member_id=<?php echo $mid; ?>" class="list-group-item list-group-item-action org-explorer-row<?php echo $isOrgLoggedUser ? ' org-explorer-row--self' : ''; ?>">
                                <span class="org-explorer-icon org-explorer-icon-user<?php echo $isOrgLoggedUser ? ' org-explorer-icon--self' : ''; ?>"><i class="bi bi-<?php echo $isOrgLoggedUser ? 'person-check' : 'person'; ?>"></i></span>
                                <span class="org-explorer-row-body">
                                    <span class="org-explorer-row-title">
                                        <?php echo html($mName); ?>
                                        <?php if ($isOrgLoggedUser): ?>
                                            <span class="org-you-badge">Tú</span>
                                        <?php endif; ?>
                                    </span>
                                    <span class="org-explorer-row-sub"><?php echo html((string)($m['email'] ?? '')); ?></span>
                                </span>
                                <span class="org-explorer-row-cta btn-org-primary"><i class="bi bi-ticket-perforated"></i> <?php echo $isOrgLoggedUser ? 'Mis tickets' : 'Ver tickets'; ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($orgUsersTotalPages > 1 && function_exists('renderModernPagination')): ?>
                        <?php echo renderModernPagination($orgUsersPage, $orgUsersTotalPages, $orgUsersPaginationParams, 'oup'); ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <?php
            $ticketMonthFilterHidden = [
                'view' => 'org',
                'org_id' => (string)$orgExplorerOrgId,
                'member_id' => (string)$orgExplorerMemberId,
            ];
            $ticketMonthFilterResetUrl = 'tickets.php?view=org&org_id=' . (int)$orgExplorerOrgId . '&member_id=' . (int)$orgExplorerMemberId;
            $ticketMonthFilterResetPage = 'otp';
            $ticketMonthPickerCompact = true;
            ?>
            <div class="org-list-section">
                <div class="org-panel-head org-panel-head--tickets">
                    <div class="org-panel-head__left">
                        <h3><i class="bi bi-ticket-perforated text-danger me-1"></i> Tickets</h3>
                        <?php if ($orgExplorerMemberName !== ''): ?>
                            <div class="org-panel-meta">
                                <?php echo html($orgExplorerMemberName); ?>
                                <?php if ($orgLoggedUserId > 0 && $orgExplorerMemberId === $orgLoggedUserId): ?>
                                    <span class="org-you-badge">Tú</span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="org-panel-head__actions">
                        <?php require __DIR__ . '/ticket-month-filter.inc.php'; ?>
                        <span class="org-count-badge"><?php echo $orgTicketsTotal; ?></span>
                    </div>
                </div>
            <?php if ($orgTicketsTotal <= 0): ?>
                <div class="org-empty">
                    <i class="bi bi-inbox" aria-hidden="true"></i>
                    <p class="text-muted mb-0"><?php echo !empty($ticketMonthFilter) ? 'No hay tickets en el mes seleccionado.' : 'Este usuario no tiene tickets registrados.'; ?></p>
                </div>
            <?php else: ?>
                    <div class="list-group list-group-flush org-explorer-list org-explorer-list-tickets">
                        <?php foreach ($orgExplorerTickets as $tk): ?>
                            <?php
                            $tid = (int)($tk['id'] ?? 0);
                            $href = 'view-ticket.php?id=' . $tid . '&from=org&org_id=' . $orgExplorerOrgId . '&member_id=' . $orgExplorerMemberId;
                            if ($orgTicketsPage > 1) {
                                $href .= '&otp=' . $orgTicketsPage;
                            }
                            if ($orgMonthQuery !== '') {
                                $href .= $orgMonthQuery;
                            }
                            ?>
                            <a href="<?php echo html($href); ?>" class="list-group-item list-group-item-action org-explorer-row org-explorer-row-ticket">
                                <span class="org-ticket-num">#<?php echo html((string)($tk['ticket_number'] ?? '')); ?></span>
                                <span class="org-explorer-row-body">
                                    <span class="org-explorer-row-title"><?php echo html((string)($tk['subject'] ?? '')); ?></span>
                                    <span class="org-explorer-row-sub">
                                        <?php echo !empty($tk['created']) ? html(formatDate((string)$tk['created'])) : ''; ?>
                                    </span>
                                </span>
                                <?php if (($tk['approval_status'] ?? '') === 'pending'): ?>
                                    <span class="org-status-badge" style="background: #fef3c7; color: #d97706; border: 1px solid #fcd34d;">
                                        <i class="bi bi-shield-lock-fill"></i> Revisión Pendiente
                                    </span>
                                <?php else: ?>
                                    <?php if (!empty($tk['status_name'])): ?>
                                        <?php
                                            $orgStatusColor = normalizeTicketHexColor((string)($tk['status_color'] ?? ''), '#64748b');
                                            $orgStatusBadgeStyle = clientTicketBadgeStyle($orgStatusColor, !empty($isDarkMode));
                                        ?>
                                        <span class="org-status-badge" style="<?php echo html($orgStatusBadgeStyle); ?>">
                                            <?php echo html((string)$tk['status_name']); ?>
                                        </span>
                                    <?php endif; ?>

                                <?php endif; ?>
                                <span class="org-explorer-row-cta btn-org-primary"><i class="bi bi-eye"></i> Ver hilo</span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($orgTicketsTotalPages > 1 && function_exists('renderModernPagination')): ?>
                        <?php echo renderModernPagination($orgTicketsPage, $orgTicketsTotalPages, $orgTicketsPaginationParams, 'otp'); ?>
                    <?php endif; ?>
            <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Solicitar Cotización -->
<div class="modal fade" id="newQuoteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="tickets.php?view=org&org_id=<?php echo $orgExplorerOrgId; ?>&list=quotes" class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header bg-light border-bottom-0" style="border-radius: 16px 16px 0 0;">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-file-earmark-plus text-danger me-2"></i>Solicitar Cotización</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">
                <input type="hidden" name="action_type" value="quote_action">
                <input type="hidden" name="quote_action_name" value="create">
                <input type="hidden" name="org_id" value="<?php echo $orgExplorerOrgId; ?>">

                <div class="mb-3">
                    <label class="form-label fw-bold text-muted small text-uppercase">Título del Servicio/Producto</label>
                    <input type="text" name="title" class="form-control form-control-lg bg-light" placeholder="Ej. Instalación de Red..." required style="border-radius: 12px;">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold text-muted small text-uppercase">Descripción / Detalles Adicionales</label>
                    <textarea name="description" class="form-control bg-light" rows="4" placeholder="Describe lo que necesitas..." style="border-radius: 12px;"></textarea>
                </div>
            </div>
            <div class="modal-footer border-top-0 pt-0 pb-4 px-4">
                <button type="button" class="btn btn-light rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-danger rounded-pill px-4 fw-bold shadow-sm">Enviar Solicitud</button>
            </div>
        </form>
    </div>
</div>
