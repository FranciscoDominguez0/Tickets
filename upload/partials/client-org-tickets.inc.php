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
?>
<div class="page-header" style="margin-top: 0;">
    <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
        <div>
            <h2 class="mb-1">Tickets por organización</h2>
            <div class="sub">Consulta tickets de usuarios en tus organizaciones (solo lectura).</div>
        </div>
        <div>
            <a href="tickets.php" class="btn btn-outline-secondary btn-sm" style="border-radius:999px;font-weight:700;">
                <i class="bi bi-inbox"></i> Mis tickets
            </a>
        </div>
    </div>
</div>

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb mb-0" style="font-size:.88rem;">
        <li class="breadcrumb-item">
            <a href="tickets.php?view=org">Organizaciones</a>
        </li>
        <?php if ($orgExplorerOrgId > 0 && $orgExplorerOrgName !== ''): ?>
        <li class="breadcrumb-item <?php echo $orgExplorerMemberId <= 0 ? 'active' : ''; ?>">
            <?php if ($orgExplorerMemberId > 0): ?>
                <a href="tickets.php?view=org&amp;org_id=<?php echo $orgExplorerOrgId; ?>"><?php echo html($orgExplorerOrgName); ?></a>
            <?php else: ?>
                <?php echo html($orgExplorerOrgName); ?>
            <?php endif; ?>
        </li>
        <?php endif; ?>
        <?php if ($orgExplorerMemberId > 0 && $orgExplorerMemberName !== ''): ?>
        <li class="breadcrumb-item active"><?php echo html($orgExplorerMemberName); ?></li>
        <?php endif; ?>
    </ol>
</nav>

<div class="panel">
    <?php if ($orgExplorerOrgId <= 0): ?>
        <?php if (empty($orgExplorerOrgs)): ?>
            <p class="text-muted mb-0">No tienes organizaciones asignadas. Contacta al soporte.</p>
        <?php else: ?>
            <div class="list-group list-group-flush org-explorer-list">
                <?php foreach ($orgExplorerOrgs as $o): ?>
                    <?php $oid = (int)($o['organization_id'] ?? 0); if ($oid <= 0) continue; ?>
                    <a href="tickets.php?view=org&amp;org_id=<?php echo $oid; ?>" class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-3">
                        <span class="org-explorer-icon"><i class="bi bi-building"></i></span>
                        <span class="flex-grow-1 fw-semibold"><?php echo html((string)($o['name'] ?? '')); ?></span>
                        <i class="bi bi-chevron-right text-muted"></i>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <?php elseif ($orgExplorerMemberId <= 0): ?>
        <?php if (empty($orgExplorerMembers)): ?>
            <p class="text-muted mb-0">No hay usuarios en esta organización.</p>
        <?php else: ?>
            <div class="list-group list-group-flush org-explorer-list">
                <?php foreach ($orgExplorerMembers as $m): ?>
                    <?php
                    $mid = (int)($m['id'] ?? 0);
                    if ($mid <= 0) continue;
                    $mName = trim((string)($m['firstname'] ?? '') . ' ' . (string)($m['lastname'] ?? ''));
                    if ($mName === '') $mName = (string)($m['email'] ?? 'Usuario');
                    ?>
                    <a href="tickets.php?view=org&amp;org_id=<?php echo $orgExplorerOrgId; ?>&amp;member_id=<?php echo $mid; ?>" class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-3">
                        <span class="org-explorer-icon org-explorer-icon-user"><i class="bi bi-person"></i></span>
                        <span class="flex-grow-1">
                            <span class="fw-semibold d-block"><?php echo html($mName); ?></span>
                            <small class="text-muted"><?php echo html((string)($m['email'] ?? '')); ?></small>
                        </span>
                        <i class="bi bi-chevron-right text-muted"></i>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <?php if (empty($orgExplorerTickets)): ?>
            <p class="text-muted mb-0">Este usuario no tiene tickets registrados.</p>
        <?php else: ?>
            <div class="list-group list-group-flush org-explorer-list">
                <?php foreach ($orgExplorerTickets as $tk): ?>
                    <?php
                    $tid = (int)($tk['id'] ?? 0);
                    $href = 'view-ticket.php?id=' . $tid . '&from=org&org_id=' . $orgExplorerOrgId . '&member_id=' . $orgExplorerMemberId;
                    ?>
                    <a href="<?php echo html($href); ?>" class="list-group-item list-group-item-action py-3">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div>
                                <span class="fw-bold text-danger">#<?php echo html((string)($tk['ticket_number'] ?? '')); ?></span>
                                <span class="ms-2"><?php echo html((string)($tk['subject'] ?? '')); ?></span>
                            </div>
                            <?php if (!empty($tk['status_name'])): ?>
                                <span class="badge" style="background:<?php echo html((string)($tk['status_color'] ?? '#64748b')); ?>;color:#fff;">
                                    <?php echo html((string)$tk['status_name']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <small class="text-muted">
                            <?php echo !empty($tk['created']) ? html(date('d/m/Y H:i', strtotime((string)$tk['created']))) : ''; ?>
                            <?php echo !empty($tk['closed']) ? ' · Cerrado' : ' · Abierto'; ?>
                        </small>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<style>
.org-explorer-list .org-explorer-icon {
    width: 40px; height: 40px; border-radius: 12px;
    background: #fef2f2; color: #dc2626;
    display: flex; align-items: center; justify-content: center; font-size: 1.1rem;
}
.org-explorer-list .org-explorer-icon-user { background: #f1f5f9; color: #475569; }
body.dark-mode .org-explorer-list .org-explorer-icon { background: rgba(239,68,68,.15); color: #f87171; }
body.dark-mode .org-explorer-list .org-explorer-icon-user { background: #27272a; color: #a1a1aa; }
body.dark-mode .org-explorer-list .list-group-item { background: transparent; border-color: #2a2a2a; color: #e4e4e7; }
body.dark-mode .org-explorer-list .list-group-item:hover { background: #1f1f1f; }
</style>
