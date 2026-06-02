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
$orgExplorerListMode = (isset($orgExplorerListMode) && (string)$orgExplorerListMode === 'all') ? 'all' : 'users';
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
?>
<link rel="stylesheet" href="css/client-org-explorer.css?v=<?php echo $orgCssV; ?>">

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
                <a href="<?php echo $orgOrgBaseUrl; ?>" class="org-view-tab <?php echo $orgExplorerListMode === 'users' ? 'active' : ''; ?>">
                    <i class="bi bi-people"></i> Por usuario
                </a>
                <a href="<?php echo $orgOrgBaseUrlAll; ?>" class="org-view-tab <?php echo $orgExplorerListMode === 'all' ? 'active' : ''; ?>">
                    <i class="bi bi-collection"></i> Todos los tickets
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
                                            <?php echo html($ownerName); ?>
                                            <?php if (!empty($tk['created'])): ?>
                                                <span class="org-ticket-owner-sep">·</span>
                                                <?php echo html(formatDate((string)($tk['created'] ?? ''))); ?>
                                            <?php endif; ?>
                                        </span>
                                    </span>
                                    <?php if (!empty($tk['status_name'])): ?>
                                        <span class="org-status-badge" style="background:<?php echo html((string)($tk['status_color'] ?? '#64748b')); ?>;">
                                            <?php echo html((string)$tk['status_name']); ?>
                                        </span>
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
                            ?>
                            <a href="tickets.php?view=org&amp;org_id=<?php echo $orgExplorerOrgId; ?>&amp;member_id=<?php echo $mid; ?>" class="list-group-item list-group-item-action org-explorer-row">
                                <span class="org-explorer-icon org-explorer-icon-user"><i class="bi bi-person"></i></span>
                                <span class="org-explorer-row-body">
                                    <span class="org-explorer-row-title"><?php echo html($mName); ?></span>
                                    <span class="org-explorer-row-sub"><?php echo html((string)($m['email'] ?? '')); ?></span>
                                </span>
                                <span class="org-explorer-row-cta btn-org-primary"><i class="bi bi-ticket-perforated"></i> Ver tickets</span>
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
                            <div class="org-panel-meta"><?php echo html($orgExplorerMemberName); ?></div>
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
                                <?php if (!empty($tk['status_name'])): ?>
                                    <span class="org-status-badge" style="background:<?php echo html((string)($tk['status_color'] ?? '#64748b')); ?>;">
                                        <?php echo html((string)$tk['status_name']); ?>
                                    </span>
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
