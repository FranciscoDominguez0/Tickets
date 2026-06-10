<?php
if (!isset($ticketView) || !is_array($ticketView)) return;
 $t = $ticketView;
$tid = (int) $t['id'];
$entries = $t['thread_entries'] ?? [];
$entryReadMap = (isset($entryReadMap) && is_array($entryReadMap)) ? $entryReadMap : [];
$countPublic = count(array_filter($entries, function ($e) { return (int)($e['is_internal'] ?? 0) === 0; }));

$isWalkinTicket = (!empty($t['walkin_phone']) || !empty($t['walkin_address']));
$topicName = trim((string)($t['topic_name'] ?? ''));
$isRedesInformatica = (stripos($topicName, 'redes') !== false || stripos($topicName, 'informática') !== false || stripos($topicName, 'informatica') !== false);

$printCompanyName = trim((string)getAppSetting('company.name', ''));
if ($printCompanyName === '') $printCompanyName = (string)APP_NAME;
$printCompanyWebsite = trim((string)getAppSetting('company.website', ''));
if ($printCompanyWebsite === '') $printCompanyWebsite = (string)APP_URL;
$printLogoUrl = (string)getCompanyLogoUrl('publico/img/vigitec-logo.png');

$backUrl = '';
if (isset($_GET['back'])) {
    $candidate = trim((string)$_GET['back']);
    if ($candidate !== '') {
        // Permitir 'tickets.php?...' o una URL/ruta completa que contenga '/upload/scp/'
        $path = (string)parse_url($candidate, PHP_URL_PATH);
        if ($path === '') {
            $path = $candidate;
        }
        $path = ltrim(str_replace('\\', '/', $path), '/');

        $needle = 'upload/scp/';
        $pos = strpos($path, $needle);
        if ($pos !== false) {
            $path = substr($path, $pos + strlen($needle));
        }
        $path = trim($path);

        $query = (string)parse_url($candidate, PHP_URL_QUERY);
        $rel = $path . ($query !== '' ? ('?' . $query) : '');
        $rel = trim($rel);
        if ($rel !== '') {
            $backUrl = (string)toAppAbsoluteUrl('upload/scp/' . $rel);
        }
    }
}

if ($backUrl === '') {
    $ref = trim((string)($_SERVER['HTTP_REFERER'] ?? ''));
    if ($ref !== '') {
        $appBase = rtrim((string)(defined('APP_URL') ? APP_URL : ''), '/');
        if ($appBase !== '' && str_starts_with($ref, $appBase)) {
            $refPath = (string)parse_url($ref, PHP_URL_PATH);
            $refQuery = (string)parse_url($ref, PHP_URL_QUERY);
            $refRel = ltrim($refPath, '/');
            if (str_starts_with($refRel, 'upload/scp/')) {
                $candidate = substr($refRel, strlen('upload/scp/'));
                if ($refQuery !== '') {
                    $candidate .= '?' . $refQuery;
                }
                $candidate = trim($candidate);
                if ($candidate !== '' && !str_starts_with($candidate, 'tickets.php?id=' . (string)$tid)) {
                    $backUrl = (string)toAppAbsoluteUrl('upload/scp/' . $candidate);
                }
            }
        }
    }
}

$backUrlFinal = ($backUrl !== '' ? $backUrl : 'tickets.php');

$ticketClientSignatureUrl = '';
$ticketClientSignaturePath = trim((string)($t['client_signature'] ?? ''));
if ($ticketClientSignaturePath !== '') {
    $projectRoot = realpath(dirname(__DIR__, 3));
    $sigPath = ltrim(str_replace('\\', '/', $ticketClientSignaturePath), '/');
    if ($projectRoot !== false && str_starts_with($sigPath, 'firmas/')) {
        $fullSigPath = $projectRoot . '/' . $sigPath;
        if (is_file($fullSigPath)) {
            $ticketClientSignatureUrl = toAppAbsoluteUrl($sigPath) . '?v=' . (string)@filemtime($fullSigPath);
        }
    }
}
?>

<div class="ticket-view-wrap">
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'closed_report' && roleHasPermission('ticket.reports')): ?>
        <style>
            @keyframes tvIn {
                from { opacity: 0; transform: translate(-50%, -16px); }
                to   { opacity: 1; transform: translate(-50%, 0); }
            }
            #tv-billing-toast {
                position: fixed;
                top: 74px;
                left: 50%;
                transform: translateX(-50%);
                z-index: 9999;
                width: min(520px, 96vw);
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                animation: tvIn 0.38s cubic-bezier(.22, 1, .36, 1) both;
                pointer-events: auto;
            }
            #tv-billing-toast .tvb-card {
                background: radial-gradient(circle at 0% 0%, #ef4444 0%, #1a0000 35%, #000000 100%);
                border-radius: 10px;
                box-shadow: 0 12px 40px rgba(29, 78, 216, 0.35), 0 2px 8px rgba(0, 0, 0, 0.14);
                overflow: hidden;
            }
            #tv-billing-toast .tvb-row {
                display: flex;
                align-items: center;
                gap: 14px;
                padding: 14px 16px;
            }
            #tv-billing-toast .tvb-dot {
                width: 8px;
                height: 8px;
                min-width: 8px;
                border-radius: 50%;
                background: rgba(255, 255, 255, 0.9);
                box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.2);
            }
            #tv-billing-toast .tvb-text {
                flex: 1;
                min-width: 0;
            }
            #tv-billing-toast .tvb-ticket-ref {
                display: inline-block;
                font-size: 0.7rem;
                font-weight: 700;
                letter-spacing: 0.06em;
                text-transform: uppercase;
                color: rgba(255, 255, 255, 0.7);
                margin-bottom: 2px;
            }
            #tv-billing-toast .tvb-msg {
                font-size: 0.88rem;
                font-weight: 600;
                color: rgba(255, 255, 255, 0.92);
                line-height: 1.3;
                margin: 0;
            }
            #tv-billing-toast .tvb-msg strong {
                color: #ffffff;
                font-weight: 800;
            }
            #tv-billing-toast .tvb-actions {
                display: flex;
                align-items: center;
                gap: 8px;
                flex-shrink: 0;
            }
            #tv-billing-toast .tvb-btn {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                background: rgba(255, 255, 255, 0.18);
                color: #fff;
                font-size: 0.78rem;
                font-weight: 700;
                padding: 6px 14px;
                border-radius: 6px;
                text-decoration: none;
                border: 1px solid rgba(255, 255, 255, 0.28);
                cursor: pointer;
                transition: background 0.15s;
                white-space: nowrap;
                letter-spacing: 0.01em;
            }
            #tv-billing-toast .tvb-btn:hover { background: rgba(255, 255, 255, 0.28); color: #fff; }
            #tv-billing-toast .tvb-dismiss {
                background: none;
                border: none;
                color: rgba(255, 255, 255, 0.55);
                cursor: pointer;
                padding: 4px;
                line-height: 1;
                display: flex;
                align-items: center;
                transition: color 0.15s;
            }
            #tv-billing-toast .tvb-dismiss:hover { color: rgba(255, 255, 255, 0.9); }
            #tv-billing-toast .tvb-bar {
                height: 2px;
                background: rgba(255, 255, 255, 0.12);
                position: relative;
                overflow: hidden;
            }
            #tv-billing-toast .tvb-bar-fill {
                position: absolute;
                inset: 0;
                background: rgba(255, 255, 255, 0.5);
                transform-origin: left;
                animation: tvBarShrink 12s linear forwards;
            }
            @keyframes tvBarShrink {
                from { transform: scaleX(1); }
                to   { transform: scaleX(0); }
            }
            @media (max-width: 520px) {
                #tv-billing-toast .tvb-row {
                    flex-wrap: wrap;
                    gap: 10px;
                }
                #tv-billing-toast .tvb-text {
                    flex: 1 1 100%;
                }
                #tv-billing-toast .tvb-actions {
                    margin-left: 22px;
                }
            }

            /* Estilos de tarjetas de adjuntos (estilo Open Ticket) */
            .dz-preview-card {
                display: flex;
                align-items: center;
                gap: 12px;
                background: #fff;
                border: 1px solid #e2e8f0;
                border-radius: 14px;
                padding: 10px 14px;
                margin-right: 8px;
                margin-bottom: 8px;
                min-width: 260px;
                max-width: 320px;
                transition: all 0.2s ease;
                box-shadow: 0 2px 6px rgba(0,0,0,0.02);
            }
            .dz-preview-card:hover {
                border-color: #cbd5e1;
                box-shadow: 0 4px 12px rgba(0,0,0,0.05);
                transform: translateY(-1px);
            }
            .dz-preview-icon {
                width: 44px;
                height: 44px;
                min-width: 44px;
                border-radius: 10px;
                overflow: hidden;
                background: #f8fafc;
                display: flex;
                align-items: center;
                justify-content: center;
                border: 1px solid #f1f5f9;
            }
            .dz-preview-icon img, .dz-preview-icon video {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }
            .dz-preview-icon i {
                font-size: 1.4rem;
            }
            .dz-preview-info {
                flex: 1;
                min-width: 0;
            }
            .dz-preview-name {
                font-size: 0.88rem;
                font-weight: 700;
                color: #0f172a;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                margin-bottom: 2px;
            }
            .dz-preview-size {
                font-size: 0.78rem;
                color: #64748b;
                font-weight: 500;
            }
            .dz-preview-remove {
                background: #fff;
                border: 1px solid #ef4444;
                color: #ef4444;
                font-size: 0.78rem;
                font-weight: 800;
                padding: 6px 14px;
                border-radius: 10px;
                cursor: pointer;
                transition: all 0.2s;
                line-height: 1;
            }
            .dz-preview-remove:hover {
                background: #ef4444;
                color: #fff;
                box-shadow: 0 4px 10px rgba(239, 68, 68, 0.2);
            }
        </style>

        <div id="tv-billing-toast" role="alert" aria-live="assertive">
            <div class="tvb-card">
                <div class="tvb-row">
                    <div class="tvb-dot"></div>
                    <div class="tvb-text">
                        <span class="tvb-ticket-ref">Ticket #<?php echo html($t['ticket_number'] ?? $tid); ?></span>
                        <p class="tvb-msg">Cerrado — <strong>recuerde realizar el reporte de servicio</strong></p>
                    </div>
                    <div class="tvb-actions">
                        <a href="reporte_costos.php?ticket_id=<?php echo (int)$tid; ?>" class="tvb-btn">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 5v14M5 12h14" stroke="#fff" stroke-width="2.4" stroke-linecap="round"/>
                            </svg>
                            Reportar
                        </a>
                        <button class="tvb-dismiss" onclick="tvBillingDismiss()" title="Cerrar" aria-label="Cerrar">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="tvb-bar"><div class="tvb-bar-fill"></div></div>
            </div>
        </div>
        <script>
            function tvBillingDismiss() {
                var el = document.getElementById('tv-billing-toast');
                if (!el) return;
                el.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                el.style.opacity = '0';
                el.style.transform = 'translateX(-50%) translateY(-10px)';
                setTimeout(function() { if (el && el.parentNode) el.parentNode.removeChild(el); }, 320);
            }
            setTimeout(tvBillingDismiss, 12000);
        </script>
    <?php endif; ?>

    <div id="assign-loading" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.45); z-index: 2000;">
        <div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); background:#fff; border-radius:14px; padding:16px 18px; border:1px solid #e2e8f0; box-shadow:0 16px 40px rgba(0,0,0,0.25); min-width: 220px; text-align:center;">
            <div class="spinner-border text-primary" role="status" style="width:2.25rem; height:2.25rem;"></div>
            <div style="margin-top:10px; font-weight:800; color:#0f172a;">Asignando…</div>
            <div style="margin-top:4px; color:#64748b; font-size:0.9rem;">Enviando notificación</div>
        </div>
    </div>
    <header class="ticket-view-header">
        <?php
        $hasOrgManager = false;
        $ticketApprovalStatus = 'none';

        if (isset($mysqli) && $mysqli && !empty($t['user_id'])) {
            $stmtA = $mysqli->prepare("SELECT status FROM ticket_approvals WHERE ticket_id = ? ORDER BY id DESC LIMIT 1");
            if ($stmtA) {
                $stmtA->bind_param('i', $tid);
                $stmtA->execute();
                $resA = $stmtA->get_result();
                if ($rowA = $resA->fetch_assoc()) {
                    $ticketApprovalStatus = $rowA['status'];
                }
            }

            $stmtM = $mysqli->prepare("SELECT 1 FROM user_organizations uo JOIN users u ON u.id = uo.user_id WHERE uo.organization_id = (SELECT organization_id FROM user_organizations WHERE user_id = ? LIMIT 1) AND u.org_tickets_view = 1 LIMIT 1");
            if ($stmtM) {
                $stmtM->bind_param('i', $t['user_id']);
                $stmtM->execute();
                $resM = $stmtM->get_result();
                if ($resM->num_rows > 0) {
                    $hasOrgManager = true;
                }
            }
        }
        
        $apprColor = '#64748b';
        if ($ticketApprovalStatus === 'pending') $apprColor = '#f59e0b';
        elseif ($ticketApprovalStatus === 'cotizacion') $apprColor = '#0ea5e9';
        elseif ($ticketApprovalStatus === 'aprobado') $apprColor = '#10b981';
        elseif ($ticketApprovalStatus === 'rechazado') $apprColor = '#ef4444';
        
        $apprTitle = 'Pendiente';
        if ($ticketApprovalStatus === 'cotizacion') $apprTitle = 'Cotización';
        elseif ($ticketApprovalStatus === 'aprobado') $apprTitle = 'Aprobado';
        elseif ($ticketApprovalStatus === 'rechazado') $apprTitle = 'Rechazado';
        ?>
        <!-- Mobile: título + badge en línea -->
        <div class="d-md-none" style="display: flex; align-items: center; flex-wrap: wrap; gap: 8px;">
            <h1 class="ticket-view-title" style="margin-bottom: 0;">
                <a href="tickets.php?id=<?php echo $tid; ?>" title="Recargar">
                    <i class="bi bi-arrow-clockwise"></i>
                </a>
                Ticket #<?php echo html($t['ticket_number']); ?>
            </h1>
            <?php if ($ticketApprovalStatus !== 'none'): ?>
                <span style="font-weight: 800; font-size: 0.75rem; color: <?php echo $apprColor; ?>; background: <?php echo $apprColor; ?>15; padding: 4px 10px; border-radius: 999px;">
                    <i class="bi bi-shield-check"></i> <?php echo $apprTitle; ?>
                </span>
            <?php endif; ?>
        </div>
        <!-- Desktop: título normal -->
        <h1 class="ticket-view-title d-none d-md-inline-flex" style="margin-bottom: 0; align-items: center; gap: 12px;">
            <a href="tickets.php?id=<?php echo $tid; ?>" title="Recargar">
                <i class="bi bi-arrow-clockwise"></i>
            </a>
            <span>Ticket #<?php echo html($t['ticket_number']); ?></span>
            
            <?php if ($ticketApprovalStatus !== 'none'): ?>
                <span style="font-weight: 800; font-size: 0.8rem; color: <?php echo $apprColor; ?>; background: <?php echo $apprColor; ?>15; padding: 4px 12px; border-radius: 999px; display: inline-flex; align-items: center; gap: 6px; letter-spacing: 0.02em;">
                    <i class="bi bi-shield-check" style="font-size: 0.9rem;"></i> <?php echo $apprTitle; ?>
                </span>
            <?php endif; ?>
        </h1>
        <?php
        $canTicketEdit = roleHasPermission('ticket.edit');
        $canTicketClose = roleHasPermission('ticket.close');
        $canTicketAssign = roleHasPermission('ticket.assign');
        $canTicketTransfer = roleHasPermission('ticket.transfer');
        $canTicketMerge = roleHasPermission('ticket.merge');
        $canTicketLink = roleHasPermission('ticket.link');
        $canTicketMark = roleHasPermission('ticket.markanswered');
        $canTicketDelete = roleHasPermission('ticket.delete');
        $canTicketPost = roleHasPermission('ticket.post');
        $canTicketReply = roleHasPermission('ticket.reply');
        ?>

        <div class="ticket-view-actions">
            <a href="<?php echo html($backUrlFinal); ?>" class="btn-icon" title="Volver"><i class="bi bi-arrow-left"></i></a>
            <div class="dropdown d-inline-block">
                <button class="btn-icon dropdown-toggle <?php echo ($canTicketEdit || $canTicketClose) ? '' : 'disabled'; ?>" type="button" <?php echo ($canTicketEdit || $canTicketClose) ? 'data-bs-toggle="dropdown"' : 'onclick="showNoPermissionAlert(\'cambiar el estado de este ticket\'); return false;"'; ?> title="<?php echo ($canTicketEdit || $canTicketClose) ? 'Estado' : 'Sin permiso'; ?>" style="<?php echo ($canTicketEdit || $canTicketClose) ? '' : 'pointer-events: auto; cursor: not-allowed;'; ?>">
                    <i class="bi bi-flag"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-end creative-dropdown-menu">
                    <div class="creative-dropdown-header">Cambiar Estado</div>
                    <?php
                    $st = $mysqli->query("SELECT id, name, color FROM ticket_status ORDER BY order_by, id");
                    while ($row = $st->fetch_assoc()): ?>
                        <?php
                        $stName = strtolower(trim((string)($row['name'] ?? '')));
                        $isClosing = ($stName !== '' && (str_contains($stName, 'cerrad') || str_contains($stName, 'resuelt') || str_contains($stName, 'closed') || str_contains($stName, 'resolved')));
                        $allowed = $isClosing ? $canTicketClose : $canTicketEdit;
                        
                        $statusIcon = 'bi-circle-fill';
                        $dbColor = trim((string)($row['color'] ?? ''));
                        $statusColor = ($dbColor !== '') ? $dbColor : '#94a3b8';

                        if (str_contains($stName, 'abiert') || str_contains($stName, 'open')) $statusIcon = 'bi-record-circle-fill';
                        elseif ($isClosing) $statusIcon = 'bi-check-circle-fill';
                        elseif (str_contains($stName, 'espera') || str_contains($stName, 'wait')) $statusIcon = 'bi-pause-circle-fill';
                        elseif (str_contains($stName, 'pendient')) $statusIcon = 'bi-clock-fill';
                        
                        $isOpening = (str_contains($stName, 'abiert') || str_contains($stName, 'open'));
                        $ticketIsClosed = !empty($t['closed']);
                        ?>
                        <a class="creative-dropdown-item <?php echo (int)$row['id'] === (int)$t['status_id'] ? 'active' : ''; ?> <?php echo $allowed ? '' : 'disabled'; ?> <?php echo ($allowed && $isClosing) ? 'js-status-close' : ''; ?>"
                           <?php
                           if ($allowed && $isClosing) {
                               echo 'href="#" data-close-status-id="' . (int)$row['id'] . '" data-close-status-name="' . html($row['name']) . '"';
                           } elseif ($allowed && $isOpening && $ticketIsClosed) {
                               echo 'href="#" data-bs-toggle="modal" data-bs-target="#modalReopenTicket"';
                           } elseif ($allowed) {
                               echo 'href="tickets.php?id=' . $tid . '&action=status&status_id=' . (int)$row['id'] . '"';
                           } else {
                               echo 'href="#" tabindex="-1" aria-disabled="true"';
                           }
                           ?>>
                            <div class="creative-dropdown-icon">
                                <i class="bi <?php echo $statusIcon; ?>" style="color: <?php echo $statusColor; ?>; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));"></i>
                            </div>
                            <span><?php echo html($row['name']); ?></span>
                            <i class="bi bi-check-circle-fill creative-dropdown-check"></i>
                        </a>
                    <?php endwhile; ?>
                </div>
            </div>

            <div class="dropdown d-inline-block">
                <button class="btn-icon dropdown-toggle <?php echo $canTicketAssign ? '' : 'disabled'; ?>" type="button" <?php echo $canTicketAssign ? 'data-bs-toggle="dropdown"' : 'onclick="showNoPermissionAlert(\'asignar este ticket\'); return false;"'; ?> title="<?php echo $canTicketAssign ? 'Asignar' : 'Sin permiso'; ?>" style="<?php echo $canTicketAssign ? '' : 'pointer-events: auto; cursor: not-allowed;'; ?>">
                    <i class="bi bi-person"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-end creative-dropdown-menu">
                    <div class="creative-dropdown-header">Asignar Agente</div>
                    <a href="tickets.php?id=<?php echo $tid; ?>&action=assign&staff_id=0" class="creative-dropdown-item <?php echo empty($t['staff_id']) ? 'active' : ''; ?>">
                        <div class="creative-dropdown-icon"><i class="bi bi-person-dash"></i></div>
                        <span>— Sin asignar —</span>
                        <i class="bi bi-check-circle-fill creative-dropdown-check"></i>
                    </a>
                    <?php
                    $tdept = (int) ($t['dept_id'] ?? 0);
                    $gd = isset($generalDeptId) ? (int) $generalDeptId : 0;
                    $empresaId = function_exists('empresaId') ? (int)empresaId() : (int)($_SESSION['empresa_id'] ?? 0);
                    $st = null;

                    $hasStaffDepartmentsTable = false;
                    if (isset($mysqli) && $mysqli) {
                        try {
                            $rt = $mysqli->query("SHOW TABLES LIKE 'staff_departments'");
                            $hasStaffDepartmentsTable = ($rt && $rt->num_rows > 0);
                        } catch (Throwable $e) {}
                    }

                    if ($tdept > 0) {
                        if ($hasStaffDepartmentsTable) {
                            $stmtSt = $mysqli->prepare("SELECT DISTINCT s.id, s.firstname, s.lastname FROM staff s JOIN staff_departments sd ON sd.staff_id = s.id WHERE s.empresa_id = ? AND s.is_active = 1 AND sd.dept_id = ? ORDER BY s.firstname, s.lastname");
                            if ($stmtSt) { $stmtSt->bind_param('ii', $empresaId, $tdept); $stmtSt->execute(); $st = $stmtSt->get_result(); }
                        } else {
                            $stmtSt = $mysqli->prepare("SELECT id, firstname, lastname FROM staff WHERE empresa_id = ? AND is_active = 1 AND (dept_id = ? OR dept_id = ?) ORDER BY firstname, lastname");
                            if ($stmtSt) { $stmtSt->bind_param('iii', $empresaId, $tdept, $gd); $stmtSt->execute(); $st = $stmtSt->get_result(); }
                        }
                    } else {
                        $stmtSt = $mysqli->prepare("SELECT id, firstname, lastname FROM staff WHERE empresa_id = ? AND is_active = 1 ORDER BY firstname, lastname");
                        if ($stmtSt) { $stmtSt->bind_param('i', $empresaId); $stmtSt->execute(); $st = $stmtSt->get_result(); }
                    }

                    while ($st && $row = $st->fetch_assoc()): 
                        $initials = strtoupper(substr($row['firstname'], 0, 1) . substr($row['lastname'], 0, 1));
                        $isActive = (int)$row['id'] === (int)($t['staff_id'] ?? 0);
                    ?>
                    <a href="tickets.php?id=<?php echo $tid; ?>&action=assign&staff_id=<?php echo (int)$row['id']; ?>" class="creative-dropdown-item <?php echo $isActive ? 'active' : ''; ?>">
                        <div class="creative-dropdown-avatar"><?php echo html($initials); ?></div>
                        <span><?php echo html(trim($row['firstname'] . ' ' . $row['lastname'])); ?></span>
                        <i class="bi bi-check-circle-fill creative-dropdown-check"></i>
                    </a>
                    <?php endwhile; ?>
                </div>
            </div>

            <button class="btn-icon <?php echo $canTicketTransfer ? '' : 'disabled'; ?>" style="<?php echo $canTicketTransfer ? '' : 'pointer-events: auto; cursor: not-allowed;'; ?>" title="Transferir" type="button" <?php echo $canTicketTransfer ? 'data-bs-toggle="modal" data-bs-target="#modalTransfer"' : 'onclick="showNoPermissionAlert(\'transferir este ticket\'); return false;"'; ?>>
                <i class="bi bi-arrow-left-right"></i>
            </button>

            <button class="btn-icon" title="Imprimir" type="button" data-action="print"><i class="bi bi-printer"></i></button>

            <div class="dropdown d-inline-block">
                <button class="btn-icon dropdown-toggle" type="button" data-bs-toggle="dropdown" title="Configuración">
                    <i class="bi bi-gear"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-end creative-dropdown-menu">
                    <div class="creative-dropdown-header">Opciones de Ticket</div>
                    
                    <a class="creative-dropdown-item <?php echo $canTicketEdit ? '' : 'disabled'; ?>" href="#" style="<?php echo $canTicketEdit ? '' : 'pointer-events: auto; cursor: not-allowed;'; ?>" <?php echo $canTicketEdit ? 'data-bs-toggle="modal" data-bs-target="#modalOwner"' : 'onclick="showNoPermissionAlert(\'cambiar el propietario de este ticket\'); return false;"'; ?>>
                        <div class="creative-dropdown-icon"><i class="bi bi-person-badge"></i></div>
                        <span>Cambiar Propietario</span>
                    </a>
                    
                    <a class="creative-dropdown-item <?php echo $canTicketMerge ? '' : 'disabled'; ?>" href="#" style="<?php echo $canTicketMerge ? '' : 'pointer-events: auto; cursor: not-allowed;'; ?>" <?php echo $canTicketMerge ? 'data-bs-toggle="modal" data-bs-target="#modalMerge"' : 'onclick="showNoPermissionAlert(\'fusionar tickets\'); return false;"'; ?>>
                        <div class="creative-dropdown-icon"><i class="bi bi-link-45deg"></i></div>
                        <span>Unir Tiquetes</span>
                    </a>
                    
                    <a class="creative-dropdown-item <?php echo $canTicketLink ? '' : 'disabled'; ?>" href="#" style="<?php echo $canTicketLink ? '' : 'pointer-events: auto; cursor: not-allowed;'; ?>" <?php echo $canTicketLink ? 'data-bs-toggle="modal" data-bs-target="#modalLinked"' : 'onclick="showNoPermissionAlert(\'vincular tickets\'); return false;"'; ?>>
                        <div class="creative-dropdown-icon"><i class="bi bi-link"></i></div>
                        <span>Tickets vinculados</span>
                    </a>
                    
                    <div style="height: 1px; background: rgba(0,0,0,0.05); margin: 6px 0;"></div>
                    
                    <a class="creative-dropdown-item <?php echo $canTicketEdit ? '' : 'disabled'; ?>" href="#" style="<?php echo $canTicketEdit ? '' : 'pointer-events: auto; cursor: not-allowed;'; ?>" <?php echo $canTicketEdit ? 'data-bs-toggle="modal" data-bs-target="#modalSupportTimes"' : 'onclick="showNoPermissionAlert(\'editar tiempos de soporte\'); return false;"'; ?>>
                        <div class="creative-dropdown-icon"><i class="bi bi-clock-history"></i></div>
                        <span>Editar horas de soporte</span>
                    </a>

                    <div style="height: 1px; background: rgba(0,0,0,0.05); margin: 6px 0;"></div>
                    
                    <a class="creative-dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#modalReferrals">
                        <div class="creative-dropdown-icon"><i class="bi bi-share"></i></div>
                        <span>Administrar referidos</span>
                    </a>
                    

                    
                    <a class="creative-dropdown-item <?php echo $canTicketEdit ? '' : 'disabled'; ?>" href="#" style="<?php echo $canTicketEdit ? '' : 'pointer-events: auto; cursor: not-allowed;'; ?>" <?php echo $canTicketEdit ? 'data-bs-toggle="modal" data-bs-target="#modalCollaborators"' : 'onclick="showNoPermissionAlert(\'gestionar colaboradores\'); return false;"'; ?>>
                        <div class="creative-dropdown-icon"><i class="bi bi-people"></i></div>
                        <span>Gestionar Colaboradores</span>
                    </a>

                    <div style="height: 1px; background: rgba(0,0,0,0.05); margin: 6px 0;"></div>
                    
                    <?php if ($hasOrgManager && $ticketApprovalStatus === 'none' && empty($t['closed'])): ?>
                    <a class="creative-dropdown-item" href="#" onclick="document.getElementById('form-request-approval').submit(); return false;">
                        <div class="creative-dropdown-icon"><i class="bi bi-shield-lock"></i></div>
                        <span>Solicitar revisión ejecutiva</span>
                    </a>
                    <form id="form-request-approval" method="post" action="tickets.php?id=<?php echo $tid; ?>" style="display: none;" onsubmit="return confirm('¿Solicitar revisión ejecutiva del jefe de la organización?');">
                        <input type="hidden" name="action" value="request_approval">
                        <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">
                    </form>
                    <?php endif; ?>

                    <a class="creative-dropdown-item text-danger <?php echo $canTicketEdit ? '' : 'disabled'; ?>" href="#" style="<?php echo $canTicketEdit ? '' : 'pointer-events: auto; cursor: not-allowed;'; ?>" <?php echo $canTicketEdit ? 'data-bs-toggle="modal" data-bs-target="#modalBlockEmail"' : 'onclick="showNoPermissionAlert(\'bloquear el correo del usuario\'); return false;"'; ?>>
                        <div class="creative-dropdown-icon text-danger" style="background: rgba(239, 68, 68, 0.1);"><i class="bi bi-envelope-x"></i></div>
                        <span>Bloquear Email</span>
                    </a>
                    
                    <a class="creative-dropdown-item text-danger <?php echo $canTicketDelete ? '' : 'disabled'; ?>" href="#" style="<?php echo $canTicketDelete ? '' : 'pointer-events: auto; cursor: not-allowed;'; ?>" <?php echo $canTicketDelete ? 'data-bs-toggle="modal" data-bs-target="#modalDelete"' : 'onclick="showNoPermissionAlert(\'eliminar este ticket\'); return false;"'; ?>>
                        <div class="creative-dropdown-icon text-danger" style="background: rgba(239, 68, 68, 0.1);"><i class="bi bi-trash"></i></div>
                        <span>Borrar Ticket</span>
                    </a>
                </div>
            </div>

            <?php if (empty($t['closed'])): ?>
                <button type="button" class="btn-icon <?php echo ($canTicketClose && !empty($t['signature_requested'])) ? 'text-warning' : ''; ?> <?php echo $canTicketClose ? '' : 'disabled'; ?>" 
                        title="<?php echo $canTicketClose ? (!empty($t['signature_requested']) ? 'Firma ya solicitada' : 'Solicitar firma del cliente') : 'Sin permiso'; ?>"
                        <?php echo $canTicketClose ? 'data-bs-toggle="modal" data-bs-target="#modalRequestSignature"' : 'onclick="showNoPermissionAlert(\'solicitar firma del cliente\'); return false;"'; ?>
                        style="<?php echo $canTicketClose ? '' : 'pointer-events: auto; cursor: not-allowed;'; ?>">
                    <i class="bi <?php echo ($canTicketClose && !empty($t['signature_requested'])) ? 'bi-envelope-check' : 'bi-pen-fill'; ?>"></i>
                </button>
            <?php endif; ?>
        </div>
    </header>

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'sig_requested'): ?>
        <div class="alert alert-success mx-4 mt-3 alert-dismissible fade show" id="signatureSuccessAlert" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> Solicitud de firma enviada correctamente al cliente.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <script>
            setTimeout(function() {
                var alertEl = document.getElementById('signatureSuccessAlert');
                if (alertEl) {
                    var bsAlert = new bootstrap.Alert(alertEl);
                    bsAlert.close();
                }
            }, 5000);
        </script>
    <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'times_updated'): ?>
        <div class="alert alert-success mx-4 mt-3 alert-dismissible fade show" id="timesSuccessAlert" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> Tiempos de soporte actualizados correctamente.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <script>
            setTimeout(function() {
                var alertEl = document.getElementById('timesSuccessAlert');
                if (alertEl) {
                    var bsAlert = new bootstrap.Alert(alertEl);
                    bsAlert.close();
                }
            }, 5000);
        </script>
    <?php endif; ?>

    <!-- Modal: Solicitar Firma -->
    <div class="modal fade" id="modalRequestSignature" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 16px; border: none; box-shadow: 0 20px 60px rgba(0,0,0,0.15);">
                <div class="modal-header" style="border-bottom: 1px solid #f1f5f9; padding: 20px 24px;">
                    <h5 class="modal-title" style="font-weight: 700; color: #0f172a;">Solicitud de Firma</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="padding: 30px 24px; text-align: center;">
                    <div style="width: 64px; height: 64px; background: rgba(239, 68, 68, 0.1); color: #ef4444; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 28px;">
                        <i class="bi bi-envelope-paper-fill"></i>
                    </div>
                    <h4 style="font-weight: 800; color: #1e293b; margin-bottom: 12px; letter-spacing: -0.02em;">¿Enviar solicitud por correo?</h4>
                    <p style="color: #64748b; font-size: 15px; line-height: 1.6; margin-bottom: 0;">
                        Se enviará un correo electrónico a <strong><?php echo html($t['user_email']); ?></strong> con un enlace seguro para que el cliente pueda firmar y cerrar este ticket remotamente.
                    </p>
                    <?php if (!empty($t['signature_requested'])): ?>
                        <div class="alert alert-warning mt-3 mb-0" style="font-size: 13px; border-radius: 10px;">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i> Ya se envió una solicitud anteriormente. Si continúas, se generará un nuevo token.
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer" style="border-top: 1px solid #f1f5f9; padding: 16px 24px; background: #f8fafc; border-bottom-left-radius: 16px; border-bottom-right-radius: 16px;">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal" style="border-radius: 10px; font-weight: 600; padding: 10px 20px; color: #64748b; border: 1px solid #e2e8f0;">Cancelar</button>
                    <a href="tickets.php?id=<?php echo $tid; ?>&action=request_signature" class="btn btn-primary" style="border-radius: 10px; font-weight: 600; padding: 10px 24px; background: #ef4444; border: none; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2);">
                        Enviar Solicitud
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Modales: Propietario, Transferir, Unir, Vinculados, Colaboradores, Bloquear, Borrar -->
    <div class="modal fade" id="modalOwner" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" action="tickets.php?id=<?php echo $tid; ?>">
                    <input type="hidden" name="action" value="owner">
                    <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">
                    <div class="modal-header"><h5 class="modal-title">Cambiar Propietario</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <label class="form-label">Nuevo propietario (usuario)</label>
                        <input type="hidden" name="user_id" id="owner-user-id" value="">
                        <input type="text" class="form-control" id="owner-user-search" autocomplete="off" placeholder="Buscar por nombre o correo…" value="">
                        <div id="owner-user-results" class="list-group mt-2" style="max-height: 240px; overflow:auto; display:none;"></div>
                        <div class="form-text" id="owner-user-selected" style="display:none;"></div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary" id="owner-user-submit" disabled>Cambiar</button></div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalTransfer" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" action="tickets.php?id=<?php echo $tid; ?>">
                    <input type="hidden" name="action" value="transfer">
                    <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">
                    <div class="modal-header"><h5 class="modal-title">Transferir Ticket</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <p class="text-muted small mb-2">Mueve este ticket a otro departamento. Si el agente asignado no pertenece al nuevo departamento, el ticket quedará sin asignar.</p>
                        <label class="form-label">Nuevo departamento</label>
                        <select name="dept_id" class="form-select" required>
                            <?php
                            $empresaId = function_exists('empresaId') ? (int)empresaId() : (int)($_SESSION['empresa_id'] ?? 0);
                            $stmtD = $mysqli->prepare("SELECT id, name FROM departments WHERE empresa_id = ? AND is_active = 1 ORDER BY name");
                            $depts = null;
                            if ($stmtD) {
                                $stmtD->bind_param('i', $empresaId);
                                $stmtD->execute();
                                $depts = $stmtD->get_result();
                            }
                            while ($depts && $d = $depts->fetch_assoc()):
                            ?>
                                <option value="<?php echo (int)$d['id']; ?>" <?php echo (int)$d['id'] === (int)($t['dept_id'] ?? 0) ? 'selected' : ''; ?>><?php echo html($d['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Transferir</button></div>
                </form>
            </div>
        </div>
    </div>
    <div class="modal fade" id="modalMerge" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" action="tickets.php?id=<?php echo $tid; ?>">
                    <input type="hidden" name="action" value="merge">
                    <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">
                    <div class="modal-header"><h5 class="modal-title">Unir Tiquetes</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <p class="text-muted small">Este ticket se cerrará y sus mensajes se copiarán al ticket destino (el destino mantiene su número y recibe una copia de los mensajes).</p>
                        <label class="form-label">Ticket destino (ID o número)</label>
                        <input type="text" name="target_ticket_id" class="form-control" placeholder="Ej: 5" required>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-danger">Unir y cerrar este ticket</button></div>
                </form>
            </div>
        </div>
    </div>
    <div class="modal fade" id="modalLinked" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Tickets vinculados</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <?php $linked = $t['linked_tickets'] ?? []; ?>
                    <?php if (empty($linked)): ?>
                        <p class="text-muted mb-3">No hay tickets vinculados.</p>
                    <?php else: ?>
                        <ul class="list-group mb-3">
                            <?php foreach ($linked as $lt): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <a href="tickets.php?id=<?php echo (int)$lt['id']; ?>">#<?php echo html($lt['ticket_number']); ?> — <?php echo html($lt['subject']); ?></a>
                                    <a href="tickets.php?id=<?php echo $tid; ?>&action=unlink&linked_id=<?php echo (int)$lt['id']; ?>" class="btn btn-sm btn-outline-danger">Quitar</a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <form method="post" action="tickets.php?id=<?php echo $tid; ?>" class="mt-2">
                        <input type="hidden" name="action" value="link">
                        <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">
                        <div class="input-group">
                            <input type="text" name="linked_ticket_id" class="form-control" placeholder="ID o número del ticket a vincular" required>
                            <button type="submit" class="btn btn-primary">Vincular</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="modalCollaborators" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Gestionar Colaboradores</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <?php $collabs = $t['collaborators'] ?? []; ?>
                    <?php if (!empty($collabs)): ?>
                        <ul class="list-group mb-3">
                            <?php foreach ($collabs as $c): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <?php echo html(trim($c['firstname'].' '.$c['lastname']).' ('.$c['email'].')'); ?>
                                    <a href="tickets.php?id=<?php echo $tid; ?>&action=collab_remove&user_id=<?php echo (int)$c['user_id']; ?>" class="btn btn-sm btn-outline-danger">Quitar</a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <form method="post" action="tickets.php?id=<?php echo $tid; ?>">
                        <input type="hidden" name="action" value="collab_add">
                        <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">
                        <div class="input-group">
                            <select name="user_id" class="form-select" required>
                                <option value="">— Añadir usuario —</option>
                                <?php
                                $empresaId = function_exists('empresaId') ? (int)empresaId() : (int)($_SESSION['empresa_id'] ?? 0);
                                $stmtU = $mysqli->prepare("SELECT id, firstname, lastname, email FROM users WHERE empresa_id = ? ORDER BY firstname, lastname");
                                $users = null;
                                if ($stmtU) {
                                    $stmtU->bind_param('i', $empresaId);
                                    $stmtU->execute();
                                    $users = $stmtU->get_result();
                                }
                                while ($u = $users->fetch_assoc()):
                                    if ((int)$u['id'] === (int)$t['user_id']) continue;
                                    $inCollab = false;
                                    foreach ($collabs as $c) { if ((int)$c['user_id'] === (int)$u['id']) { $inCollab = true; break; } }
                                    if ($inCollab) continue;
                                ?>
                                    <option value="<?php echo (int)$u['id']; ?>"><?php echo html(trim($u['firstname'].' '.$u['lastname']).' ('.$u['email'].')'); ?></option>
                                <?php endwhile; ?>
                            </select>
                            <button type="submit" class="btn btn-primary">Añadir</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="modalBlockEmail" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" action="tickets.php?id=<?php echo $tid; ?>">
                    <input type="hidden" name="action" value="block_email">
                    <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">
                    <div class="modal-header"><h5 class="modal-title">Bloquear Email</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <p>¿Bloquear el email <strong><?php echo html($t['user_email']); ?></strong>? El usuario no podrá iniciar sesión ni crear tickets.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger">Bloquear</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="modal fade" id="modalDelete" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <?php
                $canDeleteTicket = roleHasPermission('ticket.delete');
                ?>
                <form method="post" action="tickets.php?id=<?php echo $tid; ?>">
                    <input type="hidden" name="action" value="<?php echo $canDeleteTicket ? 'delete' : 'delete_request'; ?>">
                    <input type="hidden" name="confirm" value="1">
                    <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">
                    <div class="modal-header">
                        <h5 class="modal-title"><?php echo $canDeleteTicket ? 'Borrar Ticket' : 'Solicitar Borrado'; ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <?php if (!$canDeleteTicket): ?>
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                Tu solicitud debe ser aprobada por un administrador.
                            </div>
                        <?php else: ?>
                            <p class="text-danger">¿Eliminar este ticket y todo su historial? Esta acción no se puede deshacer.</p>
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label">Motivo del borrado</label>
                            <textarea name="delete_reason" class="form-control" rows="3" required placeholder="Escribe el motivo aquí..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger"><?php echo $canDeleteTicket ? 'Borrar permanentemente' : 'Enviar Solicitud'; ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="modalPriority" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" action="tickets.php?id=<?php echo $tid; ?>">
                    <input type="hidden" name="action" value="priority_update">
                    <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">
                    <div class="modal-header"><h5 class="modal-title">Cambiar Prioridad</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <label class="form-label">Nueva prioridad</label>
                        <select name="priority_id" class="form-select" required>
                            <?php
                            $stmtP = $mysqli->prepare("SELECT id, name FROM priorities ORDER BY level");
                            $prioritiesResult = null;
                            if ($stmtP) {
                                $stmtP->execute();
                                $prioritiesResult = $stmtP->get_result();
                            }
                            while ($prioritiesResult && $p = $prioritiesResult->fetch_assoc()):
                            ?>
                                <option value="<?php echo (int)$p['id']; ?>" <?php echo (int)$p['id'] === (int)($t['priority_id'] ?? 0) ? 'selected' : ''; ?>><?php echo html($p['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Cambiar Prioridad</button></div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal 1: Elegir tipo de cierre -->
    <div class="modal fade" id="modalCloseChoiceScp" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered scp-modal-dialog-sm">
            <div class="modal-content scp-modal-content">
                <div class="scp-modal-header">
                    <div class="scp-modal-header-icon"><i class="bi bi-check2-circle"></i></div>
                    <div class="scp-modal-header-text">
                        <div class="scp-modal-header-title">Cerrar Ticket #<?php echo html($t['ticket_number']); ?></div>
                        <div class="scp-modal-header-sub" id="closeChoiceStatusLabelScp"></div>
                    </div>
                    <button type="button" class="scp-modal-close" data-bs-dismiss="modal" aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
                </div>
                <div class="scp-modal-body scp-modal-body-choice">
                    <p class="scp-choice-label">¿Cómo deseas cerrar este ticket?</p>
                    <div class="scp-choice-buttons">
                        <button type="button" class="scp-btn-choice scp-btn-choice-primary" id="btnCloseWithSignatureScp">
                            <span class="scp-btn-choice-icon"><i class="bi bi-pen"></i></span>
                            <span class="scp-btn-choice-text">
                                <span class="scp-btn-choice-main">Con firma del cliente</span>
                                <span class="scp-btn-choice-sub">El cliente firmará digitalmente</span>
                            </span>
                            <i class="bi bi-chevron-right scp-btn-choice-arrow"></i>
                        </button>
                        <button type="button" class="scp-btn-choice scp-btn-choice-ghost" id="btnCloseWithoutSignatureScp">
                            <span class="scp-btn-choice-icon"><i class="bi bi-x-circle"></i></span>
                            <span class="scp-btn-choice-text">
                                <span class="scp-btn-choice-main">Sin firma</span>
                                <span class="scp-btn-choice-sub">Cerrar sin conformidad del cliente</span>
                            </span>
                            <i class="bi bi-chevron-right scp-btn-choice-arrow"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal 2: Cerrar sin firma -->
    <div class="modal fade" id="modalCloseNoSignatureScp" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered scp-modal-dialog-sm">
            <div class="modal-content scp-modal-content">
                <div class="scp-modal-header">
                    <div class="scp-modal-header-icon scp-modal-header-icon-warn"><i class="bi bi-exclamation-triangle"></i></div>
                    <div class="scp-modal-header-text">
                        <div class="scp-modal-header-title">Cerrar sin firma</div>
                        <div class="scp-modal-header-sub">El ticket se cerrará sin conformidad</div>
                    </div>
                    <button type="button" class="scp-modal-close" data-bs-dismiss="modal" aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
                </div>
                <div class="scp-modal-body">
                    <div class="scp-nosig-info">
                        <div class="scp-nosig-info-icon"><i class="bi bi-info-circle-fill"></i></div>
                        <div>
                            <p class="scp-nosig-info-title">Este ticket se cerrará sin firma del cliente.</p>
                            <p class="scp-nosig-info-desc">Se enviará una notificación interna a los agentes configurados.</p>
                        </div>
                    </div>
                </div>
                <div class="scp-modal-footer">
                    <button type="button" class="scp-btn-cancel" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="scp-btn-confirm" id="btnConfirmCloseNoSigScp"><i class="bi bi-check2-circle"></i> Cerrar ticket</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal 3: Cerrar con firma -->
    <div class="modal fade" id="modalCloseWithSignatureScp" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered scp-modal-dialog-sig">
            <div class="modal-content scp-modal-content">
                <div class="scp-modal-header">
                    <div class="scp-modal-header-icon"><i class="bi bi-vector-pen"></i></div>
                    <div class="scp-modal-header-text">
                        <div class="scp-modal-header-title">Firma del cliente</div>
                        <div class="scp-modal-header-sub">Dibuje la firma en el área indicada</div>
                    </div>
                    <button type="button" class="scp-modal-close" data-bs-dismiss="modal" aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
                </div>
                <div class="scp-modal-body">
                    <div class="mb-3">
                        <label class="scp-field-label">Motivo de cierre <span class="scp-field-optional">(opcional)</span></label>
                        <textarea id="closeMessageWithSigScp" class="scp-textarea" rows="2" placeholder="Describe brevemente el motivo del cierre..."></textarea>
                    </div>
                    <div class="scp-sig-section">
                        <div class="scp-sig-header">
                            <label class="scp-field-label mb-0">Firma del cliente</label>
                            <button type="button" class="scp-btn-clear" id="btnClearSignatureScp"><i class="bi bi-eraser"></i> Limpiar</button>
                        </div>
                        <div class="scp-sig-canvas-wrap" id="sigCanvasWrap">
                            <canvas id="signatureCanvasScp" width="700" height="240" class="scp-signature-canvas"></canvas>
                            <div class="scp-sig-hint"><i class="bi bi-pencil-fill"></i> Firme aquí con el dedo o el ratón</div>
                        </div>
                        <div class="scp-rotate-hint" id="scpRotateHint">
                            <i class="bi bi-phone-landscape"></i>
                            <span>Gira el teléfono horizontalmente para una mejor experiencia de firma</span>
                        </div>
                    </div>
                </div>
                <div class="scp-modal-footer">
                    <button type="button" class="scp-btn-cancel" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="scp-btn-confirm" id="btnConfirmCloseWithSigScp"><i class="bi bi-check2-circle"></i> Cerrar ticket</button>
                </div>
            </div>
        </div>
    </div>


    <!-- Resumen del ticket -->



    <div class="ticket-view-overview">
        <?php 
            // Estilos compartidos para badges
            $pColor = $t['priority_color'] ?: '#64748b'; 
            $pStyle = "background: {$pColor}15; color: {$pColor}; border: none; border-radius: 50rem;";
            $sColor = $t['status_color'] ?: '#64748b';
            $sStyle = "background: {$sColor}15; color: {$sColor}; border: none; border-radius: 50rem;";
        ?>

        <!-- DISEÑO MÓVIL (Visible solo en pantallas pequeñas) -->
        <div class="d-md-none">
            <!-- Header: Estado y Prioridad -->
            <div class="mobile-header">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div class="mobile-badge" style="<?php echo $sStyle; ?>">
                        <span class="dot" style="background: <?php echo $sColor; ?>;"></span>
                        <?php echo html($t['status_name']); ?>
                    </div>
                    <?php if (!empty($t['closed']) && (int)($t['has_report'] ?? 0) === 1): ?>
                        <?php $bstatus = $t['billing_status'] ?? 'pending'; ?>
                        <?php if ($bstatus === 'confirmed'): ?>
                            <a href="reporte_costos.php?ticket_id=<?php echo $tid; ?>" class="billing-icon billing-icon-confirmed" style="color: #16a34a; font-size: 1.35rem; line-height: 1; transition: transform 0.2s;" title="Facturado - Ver reporte"><i class="bi bi-patch-check-fill"></i></a>
                        <?php elseif ($bstatus === 'visita_tecnica'): ?>
                            <a href="reporte_costos.php?ticket_id=<?php echo $tid; ?>" class="billing-icon billing-icon-visita" style="color: #0284c7; font-size: 1.35rem; line-height: 1; transition: transform 0.2s;" title="Visita Técnica - Ver reporte"><i class="bi bi-geo-alt-fill"></i></a>
                        <?php elseif ($bstatus === 'cotizacion'): ?>
                            <a href="reporte_costos.php?ticket_id=<?php echo $tid; ?>" class="billing-icon billing-icon-cotizacion" style="color: #4f46e5; font-size: 1.35rem; line-height: 1; transition: transform 0.2s;" title="Cotización - Ver reporte"><i class="bi bi-file-earmark-text-fill"></i></a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <?php if ($canTicketEdit): ?>
                    <a href="#" class="mobile-badge" style="<?php echo $pStyle; ?> text-decoration:none;" data-bs-toggle="modal" data-bs-target="#modalPriority">
                        <i class="bi bi-bar-chart-fill"></i>
                        <?php echo html($t['priority_name']); ?>
                    </a>
                <?php else: ?>
                    <div class="mobile-badge" style="<?php echo $pStyle; ?>">
                        <i class="bi bi-bar-chart-fill"></i>
                        <?php echo html($t['priority_name']); ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php
            $bstatus = $t['billing_status'] ?? 'pending';
            $canConfirmBilling = roleHasPermission('admin.access');
            if (!empty($t['closed']) && (int)($t['has_report'] ?? 0) === 1 && $bstatus === 'pending'): ?>
                <div class="mobile-header" style="margin-top: 8px;">
                    <?php if ($canConfirmBilling): ?>
                        <a href="#" class="mobile-badge billing-badge-pending" style="background: #fef9c3; color: #854d0e; border: 1px solid #fef08a; text-decoration:none;" data-bs-toggle="modal" data-bs-target="#modalConfirmBilling">
                            <i class="bi bi-clock-history"></i> Pendiente Facturación
                        </a>
                    <?php else: ?>
                        <a href="reporte_costos.php?ticket_id=<?php echo $tid; ?>" class="mobile-badge billing-badge-pending" style="background: #fef9c3; color: #854d0e; border: 1px solid #fef08a; text-decoration:none;">
                            <i class="bi bi-clock-history"></i> Pendiente Facturación
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Sección Usuario -->
            <div class="mobile-user-section">
                <?php
                    if ($isWalkinTicket) {
                        // Para no recurrentes: el nombre real del cliente está en walkin_name o en el subject
                        $mobileClientName = trim($t['walkin_name'] ?? '');
                        if ($mobileClientName === '') {
                            $mobileClientName = trim($t['subject'] ?? 'Cliente no recurrente');
                        }
                    } else {
                        $mobileClientName = trim($t['user_name'] ?? '');
                    }
                    $mobileInitials = '';
                    $mobileParts = preg_split('/\s+/', $mobileClientName);
                    if (!empty($mobileParts[0])) $mobileInitials .= mb_substr($mobileParts[0], 0, 1);
                    if (!empty($mobileParts[1])) $mobileInitials .= mb_substr($mobileParts[1], 0, 1);
                    $mobileInitials = strtoupper($mobileInitials ?: '?');
                ?>
                <div class="mobile-avatar" style="font-size: 1rem; font-weight: 900; letter-spacing: 0.04em; <?php echo $isWalkinTicket ? 'background: #fef3c7; color: #92400e;' : 'background: #eff6ff; color: #2563eb;'; ?>">
                    <?php echo html($mobileInitials); ?>
                </div>
                <div class="mobile-user-info">
                    <?php if ($isWalkinTicket): ?>
                        <div style="margin-bottom: 4px;">
                            <span style="display: inline-flex; align-items: center; gap: 4px; background: #fef3c7; color: #92400e; border: 1px solid #fde68a; border-radius: 6px; font-size: 0.6rem; font-weight: 900; letter-spacing: 0.06em; padding: 2px 8px; text-transform: uppercase;">
                                <i class="bi bi-person-walking"></i> Cliente No Recurrente
                            </span>
                        </div>
                    <?php endif; ?>
                    <div class="name"><?php if (!$isWalkinTicket && !empty($t['user_id'])): ?><a href="users.php?id=<?php echo (int)$t['user_id']; ?>" style="color:inherit;text-decoration:none;"><?php echo html($mobileClientName); ?></a><?php else: ?><?php echo html($mobileClientName); ?><?php endif; ?></div>
                    <?php if (!empty($t['walkin_address'] ?? '') || !empty($t['user_address'] ?? '')): ?>
                    <div class="sub">
                        <i class="bi bi-geo-alt"></i> 
                        <span class="location-text" title="<?php echo html($isWalkinTicket ? ($t['walkin_address'] ?? '') : ($t['user_address'] ?? '')); ?>"><?php echo html($isWalkinTicket ? ($t['walkin_address'] ?? '') : ($t['user_address'] ?? '')); ?></span>
                    </div>
                    <?php 
                        $hasCoords = (!$isWalkinTicket) && !empty($t['user_latitude']) && !empty($t['user_longitude']);
                        if ($hasCoords): 
                            $lat = (float)$t['user_latitude'];
                            $lng = (float)$t['user_longitude'];
                            $wazeApp = 'waze://?ll=' . $lat . ',' . $lng . '&navigate=yes';
                            $wazeWeb = 'https://waze.com/ul?ll=' . $lat . ',' . $lng . '&navigate=yes';
                    ?>
                    <div style="margin-top: 4px; margin-bottom: 6px;">
                        <a href="#" onclick="abrirWazeInteligente(event, '<?php echo $wazeApp; ?>', '<?php echo $wazeWeb; ?>')" class="btn-waze-premium">
                            <i class="bi bi-geo-alt-fill"></i> Abrir en Waze
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                    <?php if (!empty($t['walkin_phone'] ?? '') || !empty($t['user_phone'] ?? '')): ?>
                    <div class="sub">
                        <i class="bi bi-telephone"></i> 
                        <?php echo html($isWalkinTicket ? ($t['walkin_phone'] ?? '') : ($t['user_phone'] ?? '')); ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Grilla Inferior: Tema, Asignado y Tiempos -->
            <div class="mobile-grid">

                <div class="mobile-grid-item">
                    <label><i class="bi bi-bookmark"></i> TEMA</label>
                    <div class="val"><?php echo html($t['topic_name'] ?: 'General'); ?></div>
                </div>
                <div class="mobile-grid-item">
                    <label><i class="bi bi-person-check"></i> ASIGNADO A</label>
                    <div class="val"><?php echo html($t['staff_name'] ?: 'Sin asignar'); ?></div>
                </div>
                <div class="mobile-grid-item">
                    <label><i class="bi bi-reply-all"></i> ÚLTIMA RESPUESTA</label>
                    <div class="val"><?php echo $t['last_response'] ? date('d/m/y h:i A', strtotime($t['last_response'])) : '—'; ?></div>
                </div>
            </div>
        </div>

        <!-- DISEÑO DESKTOP (Visible en pantallas medianas y grandes) -->
        <div class="d-none d-md-grid ticket-view-overview-desktop">
            <!-- Columna 1: Estado y Tiempos -->
            <div>
                <div class="field">
                    <label>ESTADO</label>
                    <div class="value" style="display:flex; flex-direction:column; gap:8px; align-items:flex-start;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span class="badge-status" style="<?php echo $sStyle; ?> padding: 6px 14px;">
                                <span style="display:inline-block; width:8px; height:8px; border-radius:50%; background-color:<?php echo html($t['status_color'] ?? '#0f172a'); ?>; margin-right:8px;"></span>
                                <?php echo html($t['status_name']); ?>
                            </span>
                            <?php if (!empty($t['closed']) && (int)($t['has_report'] ?? 0) === 1): ?>
                                <?php $bstatus = $t['billing_status'] ?? 'pending'; ?>
                                <?php if ($bstatus === 'confirmed'): ?>
                                    <a href="reporte_costos.php?ticket_id=<?php echo $tid; ?>" class="billing-icon" style="color: #16a34a; font-size: 1.35rem; line-height: 1; transition: transform 0.2s; text-decoration: none;" title="Facturado - Ver reporte" onmouseover="this.style.transform='scale(1.1)';" onmouseout="this.style.transform='scale(1)';"><i class="bi bi-patch-check-fill"></i></a>
                                <?php elseif ($bstatus === 'visita_tecnica'): ?>
                                    <a href="reporte_costos.php?ticket_id=<?php echo $tid; ?>" class="billing-icon" style="color: #0284c7; font-size: 1.35rem; line-height: 1; transition: transform 0.2s; text-decoration: none;" title="Visita Técnica - Ver reporte" onmouseover="this.style.transform='scale(1.1)';" onmouseout="this.style.transform='scale(1)';"><i class="bi bi-geo-alt-fill"></i></a>
                                <?php elseif ($bstatus === 'cotizacion'): ?>
                                    <a href="reporte_costos.php?ticket_id=<?php echo $tid; ?>" class="billing-icon" style="color: #4f46e5; font-size: 1.35rem; line-height: 1; transition: transform 0.2s; text-decoration: none;" title="Cotización - Ver reporte" onmouseover="this.style.transform='scale(1.1)';" onmouseout="this.style.transform='scale(1)';"><i class="bi bi-file-earmark-text-fill"></i></a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <?php 
                        $bstatus = $t['billing_status'] ?? 'pending';
                        if (!empty($t['closed']) && (int)($t['has_report'] ?? 0) === 1 && $bstatus === 'pending'): ?>
                            <div style="display: flex; gap: 8px;">
                            <?php if ($canConfirmBilling): ?>
                                <a href="#" class="billing-badge-pending" style="display: inline-flex; align-items: center; gap: 6px; background: #fef9c3; color: #854d0e; padding: 4px 12px; border-radius: 50rem; border: 1px solid #fef08a; text-decoration: none; font-size: 0.85rem; font-weight: 600; transition: transform 0.2s;" title="Haz clic para confirmar" data-bs-toggle="modal" data-bs-target="#modalConfirmBilling" onmouseover="this.style.transform='scale(1.02)';" onmouseout="this.style.transform='scale(1)';"><i class="bi bi-clock-history" style="font-size: 1.1rem;"></i> Pendiente Facturación</a>
                            <?php else: ?>
                                <a href="reporte_costos.php?ticket_id=<?php echo $tid; ?>" class="billing-badge-pending" style="display: inline-flex; align-items: center; gap: 6px; background: #fef9c3; color: #854d0e; padding: 4px 12px; border-radius: 50rem; border: 1px solid #fef08a; text-decoration: none; font-size: 0.85rem; font-weight: 600; transition: transform 0.2s;" title="Ver reporte" onmouseover="this.style.transform='scale(1.02)';" onmouseout="this.style.transform='scale(1)';"><i class="bi bi-clock-history" style="font-size: 1.1rem;"></i> Pendiente Facturación</a>
                            <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>


                
                <div class="field">
                    <label>PRIORIDAD</label>
                    <div class="value">
                        <?php if ($canTicketEdit): ?>
                            <a href="#" class="badge-status" style="<?php echo $pStyle; ?> text-decoration:none; padding: 6px 14px;" data-bs-toggle="modal" data-bs-target="#modalPriority">
                                <i class="bi bi-bar-chart-fill" style="margin-right:6px;"></i> <?php echo html($t['priority_name']); ?>
                            </a>
                        <?php else: ?>
                            <span class="badge-status" style="<?php echo $pStyle; ?> padding: 6px 14px;">
                                <i class="bi bi-bar-chart-fill" style="margin-right:6px;"></i> <?php echo html($t['priority_name']); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="divider"></div>

                <div class="field">
                    <label><i class="bi bi-building"></i> DEPARTAMENTO</label>
                    <div class="value" style="font-weight: 700; color: #334155;"><?php echo html($t['dept_name']); ?></div>
                </div>
                
                <div class="field">
                    <label><i class="bi bi-calendar-event"></i> CREADO EN</label>
                    <div class="value" style="color: #64748b; font-size: 0.9rem;"><?php echo $t['created'] ? date('d/m/y h:i A', strtotime($t['created'])) : '—'; ?></div>
                </div>
            </div>

            <!-- Columna 2: Cliente y Ubicación -->
            <div>
                <div class="field">
                    <label><i class="bi bi-person"></i> <?php echo $isWalkinTicket ? 'CLIENTE NO RECURRENTE' : 'CLIENTE'; ?></label>
                    <div class="value title <?php echo !$isWalkinTicket ? 'highlight' : ''; ?>">
                        <?php 
                        if ($isWalkinTicket) {
                            $displayName = trim($t['walkin_name'] ?? '');
                            if ($displayName === '') $displayName = trim($t['subject'] ?? 'Cliente no recurrente');
                            echo html($displayName);
                        } else {
                            echo '<a href="users.php?id=' . (int)$t['user_id'] . '" style="color:inherit;text-decoration:none;" title="Ver perfil del cliente">' . html($t['user_name']) . '</a>';
                        }
                        ?>
                    </div>
                    
                    <?php if ($isWalkinTicket): ?>
                        <?php if (!empty($t['walkin_phone'])): ?>
                            <div class="value sub-info"><i class="bi bi-telephone text-muted"></i><strong>Tel:</strong> <?php echo html($t['walkin_phone']); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($t['walkin_address'])): ?>
                            <div class="value sub-info"><i class="bi bi-geo-alt text-muted"></i><strong>UBICACIÓN:</strong> <span class="location-text" title="<?php echo html($t['walkin_address']); ?>"><?php echo html($t['walkin_address']); ?></span></div>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php if (!empty($t['user_phone'])): ?>
                            <div class="value sub-info"><i class="bi bi-telephone text-muted"></i><strong>Tel:</strong> <?php echo html($t['user_phone']); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($t['user_address'])): ?>
                            <div class="value sub-info"><i class="bi bi-geo-alt text-muted"></i><strong>UBICACIÓN:</strong> <span class="location-text" title="<?php echo html($t['user_address']); ?>"><?php echo html($t['user_address']); ?></span></div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php 
                        $hasCoords = (!$isWalkinTicket) && !empty($t['user_latitude']) && !empty($t['user_longitude']);
                        if ($hasCoords): 
                            $lat = (float)$t['user_latitude'];
                            $lng = (float)$t['user_longitude'];
                            $wazeApp = 'waze://?ll=' . $lat . ',' . $lng . '&navigate=yes';
                            $wazeWeb = 'https://waze.com/ul?ll=' . $lat . ',' . $lng . '&navigate=yes';
                    ?>
                        <div class="mt-2">
                            <a href="#" onclick="abrirWazeInteligente(event, '<?php echo $wazeApp; ?>', '<?php echo $wazeWeb; ?>')" class="btn-waze-premium">
                                <i class="bi bi-geo-alt-fill"></i> Abrir en Waze
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="divider"></div>

                <div class="field">
                    <label><i class="bi bi-bookmark"></i> TEMA</label>
                    <div class="value" style="font-weight: 700; color: #334155;">
                        <?php
                        $topicName = trim((string)($t['topic_name'] ?? ''));
                        echo html($topicName !== '' ? $topicName : 'General');
                        ?>
                    </div>
                </div>
            </div>

            <!-- Columna 3: Asignación y Actividad -->
            <div>

                <div class="field">
                    <label><i class="bi bi-person-check"></i> ASIGNADO A</label>
                    <div class="value" style="font-size: 1.1rem; font-weight: 700; color: #0f172a;">
                        <?php echo html($t['staff_name'] ?: 'Sin asignar'); ?>
                    </div>
                </div>

                <?php if (!$isWalkinTicket && !empty($t['anydesk'])): ?>
                <div class="field">
                    <label><i class="bi bi-pc-display"></i> ANYDESK</label>
                    <div class="value" style="display: flex; align-items: center; justify-content: space-between;">
                        <span style="font-size: 1.15rem; font-weight: 800; color: #0f172a; letter-spacing: 0.02em; user-select: all;" title="Haz clic para seleccionar todo">
                            <?php echo html($t['anydesk']); ?>
                        </span>
                        <button type="button" class="btn-copy-anydesk" onclick="navigator.clipboard.writeText('<?php echo htmlspecialchars($t['anydesk'], ENT_QUOTES); ?>').then(() => { let i = this.querySelector('i'); i.className = 'bi bi-check2 text-success'; setTimeout(() => i.className='bi bi-copy', 2000); })" title="Copiar AnyDesk">
                            <i class="bi bi-copy" style="font-size: 1rem;"></i>
                        </button>
                    </div>
                </div>
                <?php endif; ?>

                <div class="divider"></div>
                
                <div class="field">
                    <label><i class="bi bi-reply-all"></i> ÚLTIMA RESPUESTA</label>
                    <div class="value" style="color: #475569;"><?php echo $t['last_response'] ? date('d/m/y h:i A', strtotime($t['last_response'])) : '—'; ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Pestañas: Hilo del ticket -->
    <ul class="ticket-view-tabs" role="tablist">
        <li><a class="tab active" href="#thread"><i class="bi bi-chat-left-text"></i> Hilo del Ticket (<?php echo $countPublic; ?>)</a></li>
    </ul>

    <div class="ticket-view-tab-content" id="thread" data-print-area="thread">
        <div class="ticket-print-header">
            <div class="tph-left">
                <?php if ($printLogoUrl !== ''): ?>
                    <div class="tph-logo"><img src="<?php echo html($printLogoUrl); ?>" alt="<?php echo html($printCompanyName); ?>"></div>
                <?php endif; ?>
                <div class="tph-brand">
                    <div class="tph-company"><?php echo html($printCompanyName); ?></div>
                    <div class="tph-website"><?php echo html($printCompanyWebsite); ?></div>
                </div>
            </div>
            <div class="tph-right">
                <div class="tph-ticket">Ticket <?php echo html($t['ticket_number']); ?></div>
                <div class="tph-subject"><?php echo html(function_exists('cleanPlainText') ? cleanPlainText((string)($t['subject'] ?? '')) : (string)($t['subject'] ?? '')); ?></div>
                <div class="tph-meta">
                    <?php echo html((string)($t['user_name'] ?? '')); ?> · <?php echo html((string)($t['user_email'] ?? '')); ?>
                </div>
                <div class="tph-meta">
                    Impreso: <?php echo date('d/m/Y h:i A'); ?>
                </div>
            </div>
        </div>
        <?php
        $msg = $_GET['msg'] ?? '';
        $msgText = ['reply_sent' => 'Respuesta publicada correctamente.', 'created' => 'Ticket creado correctamente.', 'updated' => 'Estado actualizado.', 'assigned' => 'Asignación actualizada.', 'marked' => 'Marcado como contestado.', 'owner' => 'Propietario cambiado.', 'transferred' => 'Ticket transferido correctamente.', 'blocked' => 'Email bloqueado.', 'linked' => 'Ticket vinculado.', 'unlinked' => 'Vinculación eliminada.', 'collab_added' => 'Colaborador añadido.', 'collab_removed' => 'Colaborador quitado.', 'merged' => 'Tickets unidos correctamente.'];
        if ($msg && isset($msgText[$msg])): ?>
            <div class="alert alert-success alert-dismissible fade show"><?php echo html($msgText[$msg]); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <script>
          (function () {
            try {
              var url = new URL(window.location.href);
              if (url.searchParams.has('msg')) {
                url.searchParams.delete('msg');
                window.history.replaceState({}, document.title, url.pathname + (url.search ? url.search : '') + url.hash);
              }
            } catch (e) {}

            var cleanupModals = function () {
              try {
                var overlay = document.getElementById('assign-loading');
                if (overlay) overlay.style.display = 'none';
                document.querySelectorAll('.modal.show').forEach(function (el) {
                  if (window.bootstrap && window.bootstrap.Modal) {
                    var inst = window.bootstrap.Modal.getInstance(el);
                    if (inst) inst.hide();
                  }
                  el.classList.remove('show');
                  el.style.display = 'none';
                  el.setAttribute('aria-hidden', 'true');
                });
                document.querySelectorAll('.modal-backdrop').forEach(function (b) { b.remove(); });
                document.body.classList.remove('modal-open');
                document.body.style.removeProperty('padding-right');
              } catch (e2) {}
            };

            window.addEventListener('pageshow', function (ev) {
              if (ev && ev.persisted) {
                cleanupModals();
              }
            });
          })();
        </script>

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
                $cssClass = $isInternal ? 'internal' : ($isStaff ? 'staff' : 'user');
                $initials = '';
                $parts = preg_split('/\s+/', trim($author));
                $sub1 = function ($str) {
                    if ($str === null) return '';
                    $str = (string) $str;
                    if ($str === '') return '';
                    return function_exists('mb_substr') ? mb_substr($str, 0, 1) : substr($str, 0, 1);
                };
                if (!empty($parts[0])) $initials .= $sub1($parts[0]);
                if (!empty($parts[1])) $initials .= $sub1($parts[1]);
                $initials = strtoupper($initials ?: 'U');
                ?>
                <div class="ticket-view-entry <?php echo $cssClass; ?>">
                    <div class="entry-row">
                        <div class="entry-avatar" aria-hidden="true">
                            <span class="entry-avatar-inner"><?php echo html($initials); ?></span>
                        </div>
                        <div class="entry-bubble-wrapper">
                            <?php 
                                $current_entry_id = (int)($e['id'] ?? 0); 
                            ?>
                            <?php if ($isStaff): ?>
                                <div class="entry-header">
                                    <span class="author-name"><?php echo html($author); ?></span>
                                    <span class="author-role">Técnico</span>
                                    <div class="entry-actions ms-auto">
                                        <?php if (roleHasPermission('ticket.edit') || (int)($e['staff_id'] ?? 0) === (int)$_SESSION['staff_id']): ?>
                                            <button type="button" class="btn btn-sm btn-link text-primary p-0 me-2 js-edit-entry" data-id="<?php echo $current_entry_id; ?>" title="Editar"><i class="bi bi-pencil-square"></i></button>
                                            <button type="button" class="btn btn-sm btn-link text-danger p-0 js-delete-entry" data-id="<?php echo $current_entry_id; ?>" title="Eliminar"><i class="bi bi-trash"></i></button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="entry-header">
                                    <span class="author-name"><?php echo html($author); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="entry-content">
                                <div class="entry-meta-top">
                                    <?php echo $e['created'] ? date('d/m/y h:i A', strtotime($e['created'])) : ''; ?>
                                </div>

                                <div class="entry-body"><?php
                                    echo sanitizeRichText((string)($e['body'] ?? ''));
                                ?></div>

                                <?php $eid = (int) ($e['id'] ?? 0); ?>
                                <?php if (!empty($attachmentsByEntry[$eid])): ?>
                                    <div class="chat-att-list">
                                        <?php foreach ($attachmentsByEntry[$eid] as $a): ?>
                                            <?php
                                                $mime = strtolower((string)($a['mimetype'] ?? ''));
                                                $filename = strtolower((string)($a['original_filename'] ?? ''));
                                                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                                                $isImage = str_starts_with($mime, 'image/');
                                                $isVideo = str_starts_with($mime, 'video/') || in_array($ext, ['mp4', 'webm', 'mov', 'mkv']);
                                                $isPdf = ($mime === 'application/pdf' || $ext === 'pdf');
                                                $isDocx = ($mime === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' || $ext === 'docx');
                                                
                                                $type = 'unknown';
                                                $iconClass = 'bi-file-earmark-text text-secondary';
                                                
                                                if ($isImage) {
                                                    $type = 'image';
                                                    $iconClass = 'bi-file-earmark-image text-primary';
                                                } elseif ($isVideo) {
                                                    $type = 'video';
                                                    $iconClass = 'bi-file-earmark-play-fill text-warning';
                                                } elseif ($isPdf) {
                                                    $type = 'pdf';
                                                    $iconClass = 'bi-filetype-pdf text-danger';
                                                } elseif ($isDocx) {
                                                    $type = 'docx';
                                                    $iconClass = 'bi-file-word text-info';
                                                }

                                                $previewUrl = "tickets.php?id=" . (int)$tid . "&download=" . (int)$a['id'] . "&inline=1";
                                            ?>
                                            <div class="chat-att-item">
                                                <div class="chat-att-icon"><i class="bi <?php echo $iconClass; ?>"></i></div>
                                                <div class="chat-att-info">
                                                    <a href="tickets.php?id=<?php echo (int)$tid; ?>&download=<?php echo (int)$a['id']; ?>" 
                                                       <?php if ($type !== 'unknown'): ?>
                                                       class="att-preview-trigger att-filename" 
                                                       data-preview-url="<?php echo html($previewUrl); ?>"
                                                       data-preview-type="<?php echo $type; ?>"
                                                       <?php if ($type === 'image' || $type === 'pdf' || $type === 'video'): ?>
                                                       data-mobile-inline="1"
                                                       <?php endif; ?>
                                                       <?php else: ?>
                                                       class="att-filename"
                                                       <?php endif; ?>
                                                       title="<?php echo html($a['original_filename'] ?? 'archivo'); ?>"
                                                    ><?php echo html($a['original_filename'] ?? 'archivo'); ?></a>
                                                    <div class="att-size"><?php echo isset($a['size']) ? number_format((int)$a['size'] / 1024, 0) . ' KB' : ''; ?></div>
                                                </div>
                                                <a href="tickets.php?id=<?php echo (int)$tid; ?>&download=<?php echo (int)$a['id']; ?>" class="chat-att-download" title="Descargar"><i class="bi bi-download"></i></a>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="entry-footer">
                                <?php if ($isInternal): ?> <span class="badge bg-warning text-dark me-2">Nota interna</span><?php endif; ?>
                                <?php if ($isStaff): ?>
                                    <?php
                                    $entryReadByUser = !empty($entryReadMap[(int)($e['id'] ?? 0)]['user']);
                                    echo threadEntryReadReceiptHtml($entryReadByUser, true);
                                    ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($ticket_closed_info)): ?>
            <div class="ticket-closed-banner">
                <div class="ticket-closed-icon"><i class="bi bi-hand-thumbs-up"></i></div>
                <div class="ticket-closed-text">
                    Cerrado por
                    <span class="ticket-closed-avatar" aria-hidden="true"><i class="bi bi-person-fill"></i></span>
                    <strong><?php echo html($ticket_closed_info['by'] ?? 'Agente'); ?></strong>
                    &nbsp;&bull;&nbsp; 
                    <?php echo !empty($ticket_closed_info['at']) ? date('d/m/y h:i A', strtotime($ticket_closed_info['at'])) : ''; ?>
                </div>
            </div>

            <?php if (!empty($t['close_message'])): ?>
                <div class="ticket-close-note">
                    <div class="ticket-close-note-title">Motivo de cierre</div>
                    <div class="ticket-close-note-body"><?php echo nl2br(html((string)$t['close_message'])); ?></div>
                </div>
            <?php endif; ?>

            <?php if ($ticketClientSignatureUrl !== ''): ?>
                <div class="ticket-signature-print-box">
                    <div class="ticket-signature-print-title">Firma del cliente</div>
                    <div class="ticket-signature-print-body">
                        <img src="<?php echo html($ticketClientSignatureUrl); ?>" alt="Firma del cliente" class="ticket-signature-print-image">
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Responder -->
    <div class="ticket-view-reply">
        <?php if (!empty($t['closed'])): ?>
            <div style="text-align:center; padding:40px 20px; background:#f8fafc; border:1px dashed #cbd5e1; border-radius:14px; margin-bottom:20px;" class="closed-reply-prompt">
                <i class="bi bi-lock-fill" style="font-size:2rem; color:#94a3b8; margin-bottom:10px; display:block;"></i>
                <h4 style="font-size:1.1rem; color:#475569; font-weight:700; margin-bottom:6px;">Este ticket está cerrado</h4>
                <p style="color:#64748b; font-size:0.95rem; margin-bottom:16px;">Para escribir una nueva respuesta, primero debes reabrir el ticket.</p>
                <?php if ($canTicketEdit): ?>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalReopenTicket" style="border-radius:10px; font-weight:700; padding:10px 24px; box-shadow:0 4px 12px rgba(37,99,235,0.2);">
                        <i class="bi bi-unlock-fill me-2"></i>Reabrir Ticket
                    </button>
                <?php else: ?>
                    <div class="badge bg-secondary">No tienes permisos para reabrir</div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <form method="post" action="tickets.php?id=<?php echo $tid; ?>" enctype="multipart/form-data" id="form-reply">
                <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">
                <div class="mb-3">
                    <label class="form-label fw-bold">Respuesta</label>
                    <textarea name="body" id="reply_body" class="form-control" placeholder="Escribe tu respuesta aquí..."></textarea>
                </div>
                <?php $ticketMaxFileMb = (int)getAppSetting('tickets.ticket_max_file_mb', '10'); ?>
                <div class="attach-zone" id="attach-zone" data-action="attachments-browse">
                    <input type="file" name="attachments[]" id="attachments" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.txt,.mp4,.webm,.mov,.mkv">
                    <div class="dz-icon"><i class="bi bi-paperclip"></i></div>
                    <div class="attach-text">Arrastra o <a href="#" data-action="attachments-browse">selecciona archivos</a></div>
                    <div class="attach-hint">PDF, JPG, PNG, DOC, Video (Máx. <?php echo $ticketMaxFileMb; ?>MB)</div>
                    <div class="attach-list" id="attach-list"></div>
                </div>
                <div class="reply-buttons">
                    <button type="submit" name="do" value="reply" class="btn btn-reply btn-publish">
                        <i class="bi bi-send"></i> Responder
                    </button>
                    <?php if (!empty($canTicketClose) && empty($t['closed'])): ?>
                    <button type="button" class="btn btn-reply btn-primary-reply" id="btnCloseTicketBottom">
                        <i class="bi bi-check2-circle"></i> Cerrar
                    </button>
                    <?php endif; ?>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- Modal: Editar Mensaje -->
<div class="modal fade" id="modalEditEntry" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 24px; overflow: hidden; background: #ffffff;">
            <form method="post" action="tickets.php?id=<?php echo $tid; ?>" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit_entry">
                <input type="hidden" name="entry_id" id="edit-entry-id" value="">
                <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">
                
                <div class="modal-header border-0" style="padding: 24px 32px 16px; background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%); border-bottom: 1px solid #f1f5f9 !important;">
                    <div class="d-flex align-items-center gap-3">
                        <div style="width: 42px; height: 42px; border-radius: 12px; background: #eff6ff; color: #2563eb; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                            <i class="bi bi-pencil-square"></i>
                        </div>
                        <div>
                            <h5 class="modal-title mb-0" style="font-weight: 800; color: #0f172a; letter-spacing: -0.02em;">Editar Mensaje</h5>
                            <div style="font-size: 0.8rem; color: #64748b; font-weight: 600;">Modifica el contenido del hilo del ticket</div>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="background-color: #f1f5f9; border-radius: 50%; padding: 10px;"></button>
                </div>

                <div class="modal-body" style="padding: 32px; background: #ffffff;">
                    <div class="mb-4">
                        <label class="form-label mb-2" style="font-weight: 700; color: #334155; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.05em;">Contenido del mensaje</label>
                        <div class="editor-wrapper" style="border-radius: 16px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
                            <textarea name="body" id="edit-entry-body" class="form-control" rows="10"></textarea>
                        </div>
                    </div>

                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label mb-2" style="font-weight: 700; color: #334155; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.05em;">Adjuntos Actuales</label>
                            <div id="edit-entry-attachments" class="d-flex flex-column gap-2" style="max-height: 200px; overflow-y: auto; padding-right: 4px;">
                                <!-- Se llena vía JS con un diseño de tarjetas mini -->
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label mb-2" style="font-weight: 700; color: #334155; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.05em;">Añadir nuevos</label>
                            <div class="edit-upload-zone" style="border: 2px dashed #e2e8f0; border-radius: 16px; padding: 20px; text-align: center; transition: all 0.2s; background: #f8fafc; cursor: pointer;" onclick="document.getElementById('edit-new-files').click();">
                                <input type="file" name="attachments[]" id="edit-new-files" class="d-none" multiple onchange="window.validateEditFiles && window.validateEditFiles(this)">
                                <i class="bi bi-cloud-arrow-up" style="font-size: 1.5rem; color: #94a3b8;"></i>
                                <div id="edit-upload-hint" style="font-size: 0.85rem; color: #64748b; font-weight: 600; margin-top: 4px;">Haz clic para subir más archivos</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-0" style="padding: 24px 32px 32px; background: #f8fafc; border-top: 1px solid #f1f5f9 !important; gap: 12px;">
                    <button type="button" class="btn" data-bs-dismiss="modal" style="font-weight: 700; color: #64748b; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 10px 24px; font-size: 0.9rem;">Cancelar</button>
                    <button type="submit" class="btn" style="font-weight: 800; color: #ffffff; background: #ef4444; border: none; border-radius: 12px; padding: 10px 28px; font-size: 0.9rem; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.25);">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Confirmar Borrado de Mensaje -->
<div class="modal fade" id="modalDeleteEntry" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="tickets.php?id=<?php echo $tid; ?>">
                <input type="hidden" name="action" value="delete_entry">
                <input type="hidden" name="entry_id" id="delete-entry-id" value="">
                <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Eliminar Mensaje</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-danger">¿Estás seguro de que deseas eliminar este mensaje? Esta acción no se puede deshacer y también eliminará sus archivos adjuntos.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Eliminar permanentemente</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Confirmar Reabrir Ticket -->
<div class="modal fade" id="modalReopenTicket" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmar Reapertura</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-secondary" style="font-size: 1.05rem;">¿Estás seguro de que deseas reabrir este ticket? <br><br>El estado cambiará a <strong>Abierto</strong> y se habilitará nuevamente el área para enviar respuestas.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <a href="tickets.php?id=<?php echo $tid; ?>&action=status&status_id=1" class="btn btn-primary">Sí, reabrir ticket</a>
            </div>
        </div>
    </div>
</div>

<link href="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.css" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/lang/summernote-es-ES.min.js"></script>
<div class="modal fade" id="vigitecImageInsertModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Insertar Imagen</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="vigitecImageInsertFile" class="form-label">Subir archivo de imagen</label>
                    <input type="file" class="form-control" id="vigitecImageInsertFile" accept="image/png, image/jpeg, image/gif, image/webp">
                </div>
                <div class="text-center my-2 text-muted small">Ó</div>
                <div class="mb-3">
                    <label for="vigitecImageInsertUrl" class="form-label">Desde URL web</label>
                    <input type="url" class="form-control" id="vigitecImageInsertUrl" placeholder="https://ejemplo.com/imagen.jpg">
                </div>
                <div class="form-text mt-2 text-primary" style="font-size:0.85rem;"><i class="bi bi-info-circle"></i> Archivos grandes pueden tardar en subir. Tamaño máximo ~5MB.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="vigitecImageInsertConfirm">Insertar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="videoInsertModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Insertar video</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <label for="videoInsertUrl" class="form-label">URL (YouTube o Vimeo)</label>
                <input type="url" class="form-control" id="videoInsertUrl" placeholder="https://www.youtube.com/watch?v=...">
                <div class="form-text">Pega un enlace de YouTube/Vimeo y se insertará en tu respuesta.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="videoInsertConfirm">Insertar</button>
            </div>
        </div>
    </div>
</div>
<style>
.note-editor .note-editable {
    position: relative;
}
.ticket-view-actions .dropdown-item.is-loading {
    opacity: 0.6;
    pointer-events: none;
}
.note-editor.signature-preview-on .note-editable {
    padding-bottom: 160px;
}
.note-editor.signature-preview-on .note-editable::after {
    content: attr(data-signature);
    white-space: pre-line;
    position: absolute;
    left: 12px;
    right: 12px;
    bottom: 10px;
    color: #6b7280;
    font-weight: 600;
    font-size: 12px;
    line-height: 1.4;
    border-top: 1px dashed #e5e7eb;
    padding-top: 12px;
    pointer-events: none;
    opacity: 0.95;
}

.ticket-view-entry .entry-body img { max-width: 100% !important; max-height: 320px !important; width: auto !important; height: auto !important; display: block; object-fit: contain; border-radius: 6px; }
.ticket-view-entry .entry-body iframe { max-width: 100% !important; width: 100% !important; aspect-ratio: 16 / 9; height: auto !important; display: block; }
.ticket-view-entry .entry-body table { max-width: 100%; width: auto; overflow-x: auto; display: block; }
.ticket-view-entry .entry-body { overflow-wrap: break-word; word-break: break-word; overflow: hidden; }
.ticket-view-entry .entry-bubble-wrapper { min-width: 0; overflow: hidden; }

.note-editor .note-editable img { max-width: 100% !important; max-height: 320px !important; width: auto !important; height: auto !important; display: block; object-fit: contain; }
.note-editor .note-editable iframe { max-width: 100% !important; width: 100% !important; aspect-ratio: 16 / 9; height: auto !important; display: block; }

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
    .att-image-preview-container {
        margin-top: -20px; /* Leave space for bottom button */
    }
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
    var staffHasSignature = <?php echo !empty($staff_has_signature) ? 'true' : 'false'; ?>;
    var staffSignatureText = <?php echo json_encode((string)($staff_signature ?? '')); ?>;

    var assignOverlay = document.getElementById('assign-loading');
    function showAssignOverlay() {
        if (assignOverlay) assignOverlay.style.display = 'block';
    }
    var assignLinks = document.querySelectorAll('.ticket-view-actions a[href*="action=assign"]');
    if (assignLinks && assignLinks.length) {
        assignLinks.forEach(function (a) {
            a.addEventListener('click', function () {
                try { this.classList.add('is-loading'); } catch (e) {}
                showAssignOverlay();
            });
        });
    }

    function setSignaturePreview(enabled) {
        var editor = document.querySelector('.note-editor');
        var editable = document.querySelector('.note-editor .note-editable');
        if (!editor || !editable) return;
        if (enabled && staffHasSignature) {
            editor.classList.add('signature-preview-on');
            editable.setAttribute('data-signature', staffSignatureText);
            var lines = (staffSignatureText || '').split(/\r?\n/).length;
            var pad = 70 + (lines * 18);
            if (pad < 160) pad = 160;
            if (pad > 360) pad = 360;
            editable.style.paddingBottom = pad + 'px';
        } else {
            editor.classList.remove('signature-preview-on');
            editable.removeAttribute('data-signature');
            editable.style.paddingBottom = '';
        }
    }

    if (typeof jQuery !== 'undefined' && jQuery().summernote) {
        var videoModalEl = document.getElementById('videoInsertModal');
        var videoUrlEl = document.getElementById('videoInsertUrl');
        var videoConfirmEl = document.getElementById('videoInsertConfirm');
        var videoModal = null;
        var onVideoSubmit = null;
        if (videoModalEl && window.bootstrap && bootstrap.Modal) {
            videoModal = new bootstrap.Modal(videoModalEl);
        }

        var imageModalEl = document.getElementById('vigitecImageInsertModal');
        var imageFileEl = document.getElementById('vigitecImageInsertFile');
        var imageUrlEl = document.getElementById('vigitecImageInsertUrl');
        var imageConfirmEl = document.getElementById('vigitecImageInsertConfirm');
        var imageModal = null;
        var onImageSubmit = null;
        if (imageModalEl && window.bootstrap && bootstrap.Modal) {
            imageModal = new bootstrap.Modal(imageModalEl);
        }

        function openVideoModal(cb) {
            onVideoSubmit = cb;
            if (!videoModal || !videoUrlEl) return;
            videoUrlEl.value = '';
            videoModal.show();
            setTimeout(function () { try { videoUrlEl.focus(); } catch (e) {} }, 100);
        }

        function openImageModal(cb) {
            onImageSubmit = cb;
            if (!imageModal || !imageFileEl) return;
            imageFileEl.value = '';
            if (imageUrlEl) imageUrlEl.value = '';
            imageModal.show();
            setTimeout(function () { try { imageFileEl.focus(); } catch (e) {} }, 100);
        }

        if (videoConfirmEl) {
            videoConfirmEl.addEventListener('click', function () {
                if (!onVideoSubmit || !videoUrlEl) return;
                var url = (videoUrlEl.value || '').trim();
                if (url) {
                    onVideoSubmit(url);
                    if (videoModal) videoModal.hide();
                }
            });
        }

        if (imageConfirmEl) {
            imageConfirmEl.addEventListener('click', function () {
                if (!onImageSubmit || !imageFileEl) return;
                var f = imageFileEl.files && imageFileEl.files[0] ? imageFileEl.files[0] : null;
                var url = imageUrlEl ? (imageUrlEl.value || '').trim() : '';
                if (!f && !url) return;
                try { imageModal && imageModal.hide(); } catch (e) {}
                try { onImageSubmit(f || url); } catch (e2) {}
            });
        }

        // --- Manejo de Edición y Borrado de Entradas ---
        // Sincronizar Summernote antes de enviar el formulario de edición
        $('#modalEditEntry form').on('submit', function() {
            var $textarea = $('#edit-entry-body');
            var code = $textarea.summernote('code');
            $textarea.val(code);
            $(this).find('button[type="submit"]').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Guardando...');
        });

        $(document).on('click', '.js-edit-entry', function() {
            var entryId = $(this).data('id');
            var entryBody = $(this).closest('.ticket-view-entry').find('.entry-body').html();
            
            $('#edit-entry-id').val(entryId);
            $('#edit-entry-body').summernote('code', entryBody);
            
            // Limpiar y cargar adjuntos actuales
            var $attList = $('#edit-entry-attachments').empty();
            $(this).closest('.ticket-view-entry').find('.chat-att-list .chat-att-item').each(function() {
                var attLink = $(this).find('a[href*="download="]').attr('href');
                if (!attLink) return;
                var match = attLink.match(/download=(\d+)/);
                if (!match) return;
                var attId = match[1];
                var filename = $(this).find('.att-filename').text();
                var iconClass = $(this).find('.chat-att-icon i').attr('class') || 'bi bi-paperclip';
                
                $attList.append(
                    '<div class="edit-att-item d-flex align-items-center justify-content-between" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 10px 14px;">' +
                    '  <div class="d-flex align-items-center gap-2 overflow-hidden">' +
                    '    <div class="edit-att-icon" style="font-size: 1.1rem; flex: 0 0 auto;"><i class="' + iconClass + '"></i></div>' +
                    '    <span class="text-truncate" style="font-size: 0.88rem; font-weight: 600; color: #334155;">' + filename + '</span>' +
                    '  </div>' +
                    '  <div class="form-check form-switch ms-2" title="Marcar para eliminar">' +
                    '    <input class="form-check-input" type="checkbox" name="delete_attachments[]" value="' + attId + '" id="del-att-' + attId + '">' +
                    '    <label class="form-check-label text-danger" for="del-att-' + attId + '" style="font-size: 0.75rem; font-weight: 700; cursor: pointer;"><i class="bi bi-trash"></i></label>' +
                    '  </div>' +
                    '</div>'
                );
            });

            $('#modalEditEntry').modal('show');
        });

        $(document).on('click', '.js-delete-entry', function() {
            var entryId = $(this).data('id');
            $('#delete-entry-id').val(entryId);
            $('#modalDeleteEntry').modal('show');
        });

        // Inicializar summernote para el editor de edición
        $('#edit-entry-body').summernote({
            height: 250,
            lang: 'es-ES',
            toolbar: [
                ['style', ['bold', 'italic', 'underline', 'clear']],
                ['para', ['ul', 'ol']],
                ['view', ['codeview']]
            ],
            callbacks: {
                onImageUpload: function(files) {
                    // Aquí se podría implementar la subida de imágenes para la edición
                }
            }
        });

        if (videoUrlEl) {
            videoUrlEl.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    videoConfirmEl && videoConfirmEl.click();
                }
            });
        }
        if (imageFileEl) {
            imageFileEl.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    imageConfirmEl && imageConfirmEl.click();
                }
            });
        }
        if (imageUrlEl) {
            imageUrlEl.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    imageConfirmEl && imageConfirmEl.click();
                }
            });
        }

        function toEmbedUrl(url) {
            url = (url || '').trim();
            if (!url) return '';
            if (url.indexOf('//') === 0) url = 'https:' + url;
            if (/^https?:\/\/(www\.)?(youtube\.com\/embed\/|youtube-nocookie\.com\/embed\/)/i.test(url)) return url;
            if (/^https?:\/\/(www\.)?player\.vimeo\.com\/video\//i.test(url)) return url;
            var m = url.match(/(?:youtube\.com\/watch\?v=|youtube\.com\/shorts\/|youtu\.be\/)([A-Za-z0-9_-]{6,})/i);
            if (m && m[1]) return 'https://www.youtube-nocookie.com/embed/' + m[1] + '?rel=0';
            var v = url.match(/vimeo\.com\/(?:video\/)?(\d+)/i);
            if (v && v[1]) return 'https://player.vimeo.com/video/' + v[1];
            return '';
        }

        var myVideoBtn = function (context) {
            var ui = jQuery.summernote.ui;
            return ui.button({
                contents: '<i class="note-icon-video"></i>',
                tooltip: 'Insertar video (YouTube/Vimeo)',
                click: function () {
                    openVideoModal(function (url) {
                        var embed = toEmbedUrl(url);
                        if (!embed) {
                            alert('Formato de enlace no soportado. Usa un enlace de YouTube o Vimeo.');
                            return;
                        }
                        var html = '<iframe src="' + embed.replace(/"/g, '') + '" width="560" height="315" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>';
                        context.invoke('editor.pasteHTML', html);
                    });
                }
            }).render();
        };

        var myImageBtn = function () {
            var ui = jQuery.summernote.ui;
            return ui.button({
                contents: '<i class="note-icon-picture"></i>',
                tooltip: 'Insertar imagen',
                click: function () {
                    openImageModal(function (fileOrUrl) {
                        if (!fileOrUrl) return;
                        if (typeof fileOrUrl === 'string') {
                            jQuery('#reply_body').summernote('insertImage', fileOrUrl);
                            return;
                        }
                        var file = fileOrUrl;
                        var data = new FormData();
                        data.append('file', file);
                        data.append('csrf_token', <?php echo json_encode((string)($_SESSION['csrf_token'] ?? '')); ?>);
                        fetch('../editor_image_upload.php', {
                            method: 'POST',
                            body: data,
                            credentials: 'same-origin'
                        })
                        .then(function (r) { return r.json(); })
                        .then(function (json) {
                            if (!json || !json.ok || !json.url) throw new Error((json && json.error) ? json.error : 'Upload failed');
                            jQuery('#reply_body').summernote('insertImage', json.url);
                        })
                        .catch(function (err) {
                            alert('No se pudo subir la imagen. Intenta con otra o usa Adjuntar archivos.');
                            try { console.error(err); } catch (e) {}
                        });
                    });
                }
            }).render();
        };

        var isMobile = window.innerWidth <= 768;
        var toolbarConfig = isMobile ? [
            ['font', ['bold', 'italic', 'underline', 'clear']],
            ['insert', ['link', 'myImage']],
            ['view', ['fullscreen']]
        ] : [
            ['style', ['style', 'paragraph']],
            ['font', ['bold', 'italic', 'underline', 'strikethrough', 'clear']],
            ['fontname', ['fontname']],
            ['color', ['color']],
            ['fontsize', ['fontsize']],
            ['insert', ['link', 'myImage', 'myVideo', 'table', 'hr']],
            ['view', ['codeview', 'fullscreen']],
            ['para', ['ul', 'ol', 'paragraph']]
        ];

        var placeholderText = isMobile 
            ? 'Empezar escribiendo su respuesta aquí...' 
            : 'Escribe tu respuesta aquí...';

        jQuery('#reply_body').summernote({
            height: isMobile ? 160 : 260,
            lang: 'es-ES',
            placeholder: placeholderText,
            toolbar: toolbarConfig,
            buttons: {
                myVideo: myVideoBtn,
                myImage: myImageBtn
            },
            fontNames: ['Arial', 'Arial Black', 'Comic Sans MS', 'Courier New', 'Helvetica', 'Impact', 'Tahoma', 'Times New Roman', 'Verdana'],
            fontSizes: ['8', '9', '10', '11', '12', '14', '16', '18', '24', '36']
        });

        // Aplicar previsualización inicial según radio seleccionado
        setTimeout(function () {
            var checked = document.querySelector('input[name="signature"]:checked');
            setSignaturePreview(checked && checked.value === 'staff');
        }, 0);
    }

    // Toggle de previsualización al cambiar la opción de firma
    var sigRadios = document.querySelectorAll('input[name="signature"]');
    if (sigRadios && sigRadios.length) {
        sigRadios.forEach(function (r) {
            r.addEventListener('change', function () {
                setSignaturePreview(this.value === 'staff');
            });
        });
    }

    var zone = document.getElementById('attach-zone');
    var input = document.getElementById('attachments');
    var list = document.getElementById('attach-list');
    if (zone && input) {
        zone.addEventListener('click', function (e) {
            try {
                var t = e && e.target ? e.target : null;
                if (t && t.tagName && t.tagName.toLowerCase() === 'a') {
                    e.preventDefault();
                }
            } catch (err) {}
            try {
                if (input && typeof input.showPicker === 'function') {
                    input.showPicker();
                } else if (input) {
                    input.click();
                }
            } catch (err2) {
                try { if (input) input.click(); } catch (err3) {}
            }
        });
    }
    function humanSize(bytes) {
        if (!bytes) return '0 B';
        var units = ['B', 'KB', 'MB', 'GB'];
        var i = Math.floor(Math.log(bytes) / Math.log(1024));
        i = Math.min(i, units.length - 1);
        return (bytes / Math.pow(1024, i)).toFixed(i === 0 ? 0 : 1) + ' ' + units[i];
    }
    function removeAt(index) {
        try {
            var dt = new DataTransfer();
            for (var i = 0; i < input.files.length; i++) {
                if (i !== index) dt.items.add(input.files[i]);
            }
            input.files = dt.files;
            updateList();
        } catch (e) {}
    }
    function updateList() {
        list.innerHTML = '';
        var maxMb = <?php echo (int)getAppSetting('tickets.ticket_max_file_mb', '10'); ?>;
        var maxSize = maxMb * 1024 * 1024;
        var phpUploadLimit = <?php echo getUploadMaxSize(); ?>;
        if (maxSize > phpUploadLimit) maxSize = phpUploadLimit; // No exceder el límite real de PHP
        var tooLarge = [];

        if (input.files.length) {
            var dt = new DataTransfer();
            for (var i = 0; i < input.files.length; i++) {
                var file = input.files[i];
                if (file.size > maxSize) {
                    tooLarge.push(file.name + ' (' + humanSize(file.size) + ')');
                } else {
                    dt.items.add(file);
                }
            }
            
            if (tooLarge.length) {
                input.files = dt.files;
                var msg = 'Los siguientes archivos superan el límite de ' + maxMb + 'MB y han sido descartados:<br><br><span style="color:#ef4444;font-weight:600">' + tooLarge.join('<br>') + '</span>';
                window.__showCreativePopScp && window.__showCreativePopScp(msg, 'Archivo demasiado grande');
            }

            for (var i = 0; i < input.files.length; i++) {
                var file = input.files[i];
                var ext = file.name.split('.').pop().toLowerCase();
                var iconHtml = '<i class="bi bi-file-earmark-text"></i>';
                
                if (['pdf'].includes(ext)) {
                    iconHtml = '<i class="bi bi-file-earmark-pdf-fill" style="color: #ef4444;"></i>';
                } else if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) {
                    iconHtml = '<i class="bi bi-file-earmark-image" style="color: #3b82f6;"></i>';
                } else if (['doc', 'docx'].includes(ext)) {
                    iconHtml = '<i class="bi bi-file-earmark-word-fill" style="color: #0ea5e9;"></i>';
                } else if (['xls', 'xlsx'].includes(ext)) {
                    iconHtml = '<i class="bi bi-file-earmark-excel-fill" style="color: #10b981;"></i>';
                } else if (['zip', 'rar'].includes(ext)) {
                    iconHtml = '<i class="bi bi-file-earmark-zip-fill" style="color: #f59e0b;"></i>';
                } else if (['mp4', 'webm', 'mov', 'mkv'].includes(ext)) {
                    iconHtml = '<i class="bi bi-file-earmark-play-fill" style="color: #f59e0b;"></i>';
                }

                var card = document.createElement('div');
                card.className = 'dz-preview-card';
                card.innerHTML = 
                    '<div class="dz-preview-icon" id="preview-icon-'+i+'">' + iconHtml + '</div>' +
                    '<div class="dz-preview-info">' +
                        '<div class="dz-preview-name" title="'+file.name+'">' + file.name + '</div>' +
                        '<div class="dz-preview-size">' + humanSize(file.size) + '</div>' +
                    '</div>' +
                    '<button type="button" class="dz-preview-remove" data-remove-index="'+i+'">Quitar</button>';
                
                list.appendChild(card);

                if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) {
                    (function(idx, f) {
                        var reader = new FileReader();
                        reader.onload = function(e) {
                            var iconDiv = document.getElementById('preview-icon-'+idx);
                            if (iconDiv) {
                                iconDiv.innerHTML = '<img src="' + e.target.result + '" alt="preview">';
                            }
                        };
                        reader.readAsDataURL(f);
                    })(i, file);
                } else if (['mp4', 'webm', 'mov', 'mkv'].includes(ext)) {
                    (function(idx, f) {
                        var video = document.createElement('video');
                        video.preload = 'metadata';
                        video.onloadedmetadata = function() {
                            var iconDiv = document.getElementById('preview-icon-'+idx);
                            if (iconDiv) {
                                video.style.width = '100%';
                                video.style.height = '100%';
                                video.style.objectFit = 'cover';
                                iconDiv.innerHTML = '';
                                iconDiv.appendChild(video);
                            }
                        };
                        video.src = URL.createObjectURL(f);
                    })(i, file);
                }
            }
        }
    }
    list.addEventListener('click', function(e) {
        var btn = e.target.closest('.dz-preview-remove');
        if (btn) {
            e.preventDefault();
            e.stopPropagation();
            removeAt(parseInt(btn.getAttribute('data-remove-index')));
        }
    });
    input.addEventListener('change', updateList);

    window.validateEditFiles = function(input) {
        var maxMb = <?php echo (int)getAppSetting('tickets.ticket_max_file_mb', '10'); ?>;
        var maxSize = maxMb * 1024 * 1024;
        var phpUploadLimit = <?php echo getUploadMaxSize(); ?>;
        if (maxSize > phpUploadLimit) maxSize = phpUploadLimit; // No exceder el límite real de PHP
        var tooLarge = [];
        var dt = new DataTransfer();
        
        for (var i = 0; i < input.files.length; i++) {
            var file = input.files[i];
            if (file.size > maxSize) {
                tooLarge.push(file.name + ' (' + humanSize(file.size) + ')');
            } else {
                dt.items.add(file);
            }
        }
        
        if (tooLarge.length) {
            input.files = dt.files;
            var msg = 'Los siguientes archivos superan el límite de ' + maxMb + 'MB y han sido descartados:<br><br><span style="color:#ef4444;font-weight:600">' + tooLarge.join('<br>') + '</span>';
            window.__showCreativePopScp && window.__showCreativePopScp(msg, 'Archivo demasiado grande');
        }
        
        document.getElementById('edit-upload-hint').textContent = input.files.length + ' archivos seleccionados';
    };
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
    var btnReset = document.getElementById('btn-reset');
    if (btnReset) {
        btnReset.addEventListener('click', function() {
            if (typeof jQuery !== 'undefined' && jQuery('#reply_body').length && jQuery('#reply_body').summernote('code')) {
                jQuery('#reply_body').summernote('reset');
            }
            input.value = '';
            list.innerHTML = '';
        });
    }

    var ownerSearch = document.getElementById('owner-user-search');
    var ownerResults = document.getElementById('owner-user-results');
    var ownerId = document.getElementById('owner-user-id');
    var ownerSelected = document.getElementById('owner-user-selected');
    var ownerSubmit = document.getElementById('owner-user-submit');
    if (ownerSearch && ownerResults && ownerId && ownerSelected && ownerSubmit) {
        var ownerTimer = null;
        var lastOwnerQuery = '';

        function hideOwnerResults() {
            ownerResults.style.display = 'none';
            ownerResults.innerHTML = '';
        }

        function setOwnerSelected(id, label) {
            ownerId.value = String(id || '');
            ownerSelected.textContent = label || '';
            ownerSelected.style.display = label ? '' : 'none';
            ownerSubmit.disabled = !(id && Number(id) > 0);
            hideOwnerResults();
        }

        ownerSearch.addEventListener('input', function () {
            var q = String(ownerSearch.value || '').trim();
            setOwnerSelected('', '');
            if (q.length < 2) {
                hideOwnerResults();
                return;
            }
            if (ownerTimer) window.clearTimeout(ownerTimer);
            ownerTimer = window.setTimeout(function () {
                if (q === lastOwnerQuery) return;
                lastOwnerQuery = q;
                fetch('tickets.php?action=user_search&q=' + encodeURIComponent(q), {
                    credentials: 'same-origin'
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    ownerResults.innerHTML = '';
                    if (!data || !data.ok || !data.items || !data.items.length) {
                        hideOwnerResults();
                        return;
                    }
                    data.items.forEach(function (u) {
                        var a = document.createElement('a');
                        a.href = '#';
                        a.className = 'list-group-item list-group-item-action';
                        var nm = String(u.name || '').trim();
                        var em = String(u.email || '').trim();
                        a.textContent = (nm ? nm : 'Usuario') + (em ? (' (' + em + ')') : '');
                        a.addEventListener('click', function (e) {
                            e.preventDefault();
                            setOwnerSelected(u.id, a.textContent);
                        });
                        ownerResults.appendChild(a);
                    });
                    ownerResults.style.display = '';
                })
                .catch(function () {
                    hideOwnerResults();
                });
            }, 250);
        });

        document.addEventListener('click', function (e) {
            if (!ownerResults.contains(e.target) && e.target !== ownerSearch) {
                hideOwnerResults();
            }
        });

        var modalOwner = document.getElementById('modalOwner');
        if (modalOwner) {
            modalOwner.addEventListener('shown.bs.modal', function () {
                ownerSearch.focus();
            });
            modalOwner.addEventListener('hidden.bs.modal', function () {
                ownerSearch.value = '';
                setOwnerSelected('', '');
                lastOwnerQuery = '';
            });
        }
    }

    var ticketId = <?php echo (int)$tid; ?>;
    var csrfToken = <?php echo json_encode($_SESSION['csrf_token'] ?? ''); ?>;
    var closingStatusId = 0;
    var closingStatusName = '';
    var isClosingRequestBusy = false;

    var modalChoiceEl = document.getElementById('modalCloseChoiceScp');
    var modalNoSigEl = document.getElementById('modalCloseNoSignatureScp');
    var modalWithSigEl = document.getElementById('modalCloseWithSignatureScp');
    var modalChoice = (modalChoiceEl && window.bootstrap) ? bootstrap.Modal.getOrCreateInstance(modalChoiceEl) : null;
    var modalNoSig = (modalNoSigEl && window.bootstrap) ? bootstrap.Modal.getOrCreateInstance(modalNoSigEl) : null;
    var modalWithSig = (modalWithSigEl && window.bootstrap) ? bootstrap.Modal.getOrCreateInstance(modalWithSigEl) : null;

    var closeStatusLabel = document.getElementById('closeChoiceStatusLabelScp');
    var btnWithSig = document.getElementById('btnCloseWithSignatureScp');
    var btnWithoutSig = document.getElementById('btnCloseWithoutSignatureScp');
    var btnConfirmNoSig = document.getElementById('btnConfirmCloseNoSigScp');
    var btnConfirmWithSig = document.getElementById('btnConfirmCloseWithSigScp');
    var btnCloseTicketBottom = document.getElementById('btnCloseTicketBottom');

    var canvas = document.getElementById('signatureCanvasScp');
    var ctx = canvas ? canvas.getContext('2d') : null;
    var drawing = false;
    var hasDrawn = false;
    var lastX = 0;
    var lastY = 0;

    function setBusy(isBusy) {
        isClosingRequestBusy = isBusy;
        if (btnConfirmNoSig) btnConfirmNoSig.disabled = isBusy;
        if (btnConfirmWithSig) btnConfirmWithSig.disabled = isBusy;
    }

    function closeByAjax(signatureData, closeMessage) {
        if (!closingStatusId || isClosingRequestBusy) return;
        setBusy(true);
        var formData = new FormData();
        formData.append('ticket_id', String(ticketId));
        formData.append('status_id', String(closingStatusId));
        formData.append('close_message', closeMessage || '');
        formData.append('signature_data', signatureData || '');
        formData.append('csrf_token', csrfToken || '');

        fetch('../../agente/close-ticket.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data && data.success) {
                var requiresReport = <?php echo (roleHasPermission('ticket.reports') && (int)($t['requires_report'] ?? 0) === 1) ? 1 : 0; ?>;
                var finalMsg = 'updated';
                if (requiresReport === 1) {
                    finalMsg = 'closed_report';
                }
                window.location.href = 'tickets.php?id=' + ticketId + '&msg=' + finalMsg;
                return;
            }
            setBusy(false);
            alert('Error: ' + ((data && data.error) ? data.error : 'No se pudo cerrar el ticket'));
        })
        .catch(function () {
            setBusy(false);
            alert('Error de conexion al cerrar el ticket');
        });
    }

    var statusCloseLinks = document.querySelectorAll('.js-status-close');
    if (statusCloseLinks && statusCloseLinks.length) {
        statusCloseLinks.forEach(function (link) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                closingStatusId = parseInt(this.getAttribute('data-close-status-id') || '0', 10);
                closingStatusName = String(this.getAttribute('data-close-status-name') || '');
                if (!closingStatusId || !modalChoice) return;
                
                if (closingStatusName.toLowerCase().indexOf('resuelto') !== -1 || closingStatusName.toLowerCase().indexOf('resolved') !== -1) {
                    window.location.href = 'tickets.php?id=' + ticketId + '&action=status&status_id=' + closingStatusId;
                    return;
                }

                if (closeStatusLabel) closeStatusLabel.textContent = closingStatusName !== '' ? ('Estado de cierre: ' + closingStatusName) : '';
                if (canvas && ctx) {
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    hasDrawn = false;
                }
                var msgYes = document.getElementById('closeMessageWithSigScp');
                if (msgYes) msgYes.value = '';
                modalChoice.show();
            });
        });
    }

    // Image Preview Logic
    (function() {
        var previewContainer = document.createElement('div');
        previewContainer.className = 'att-image-preview-container';
        
        // Boton de cierre (para movil)
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
                iframe.src = url + '#toolbar=0&navpanes=0&scrollbar=1'; // Habilitar scrollbar
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
            if (isMobile) return; // En movil no ocultamos por timeout de mouseleave
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

            // Soporte para "dejar presionado" (long press) en movil
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

    if (btnWithSig) {

        btnWithSig.addEventListener('click', function () {
            if (modalChoice) modalChoice.hide();
            if (modalWithSig) modalWithSig.show();
        });
    }
    if (btnWithoutSig) {
        btnWithoutSig.addEventListener('click', function () {
            if (modalChoice) modalChoice.hide();
            if (modalNoSig) modalNoSig.show();
        });
    }
    if (btnConfirmNoSig) {
        btnConfirmNoSig.addEventListener('click', function () {
            closeByAjax('', '');
        });
    }
    if (btnConfirmWithSig) {
        btnConfirmWithSig.addEventListener('click', function () {
            if (!canvas || !ctx || !hasDrawn) {
                alert('Por favor dibuje la firma del cliente antes de cerrar.');
                return;
            }
            var msgYes = document.getElementById('closeMessageWithSigScp');
            closeByAjax(canvas.toDataURL('image/png'), msgYes ? msgYes.value.trim() : '');
        });
    }

    if (btnCloseTicketBottom) {
        btnCloseTicketBottom.addEventListener('click', function () {
            var closeOptions = Array.prototype.slice.call(document.querySelectorAll('.js-status-close'));
            var preferredClose = null;
            for (var i = 0; i < closeOptions.length; i++) {
                var statusName = String(closeOptions[i].getAttribute('data-close-status-name') || '').toLowerCase();
                if (statusName.indexOf('cerrad') !== -1 || statusName.indexOf('closed') !== -1) {
                    preferredClose = closeOptions[i];
                    break;
                }
            }
            if (!preferredClose && closeOptions.length > 0) {
                preferredClose = closeOptions[0];
            }
            if (!preferredClose) {
                alert('No hay un estado de cierre configurado.');
                return;
            }
            closingStatusId = parseInt(preferredClose.getAttribute('data-close-status-id') || '0', 10);
            closingStatusName = String(preferredClose.getAttribute('data-close-status-name') || '');
            if (!closingStatusId || !modalChoice) {
                alert('No se pudo iniciar el cierre del ticket.');
                return;
            }
            
            if (closingStatusName.toLowerCase().indexOf('resuelto') !== -1 || closingStatusName.toLowerCase().indexOf('resolved') !== -1) {
                window.location.href = 'tickets.php?id=' + ticketId + '&action=status&status_id=' + closingStatusId;
                return;
            }

            if (closeStatusLabel) closeStatusLabel.textContent = closingStatusName !== '' ? ('Estado de cierre: ' + closingStatusName) : '';
            if (canvas && ctx) {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                hasDrawn = false;
            }
            var msgYes = document.getElementById('closeMessageWithSigScp');
            if (msgYes) msgYes.value = '';
            modalChoice.show();
        });
    }

    function getCanvasPos(e) {
        var rect = canvas.getBoundingClientRect();
        var scaleX = canvas.width / rect.width;
        var scaleY = canvas.height / rect.height;
        if (e.touches && e.touches.length > 0) {
            return {
                x: (e.touches[0].clientX - rect.left) * scaleX,
                y: (e.touches[0].clientY - rect.top) * scaleY
            };
        }
        return {
            x: (e.clientX - rect.left) * scaleX,
            y: (e.clientY - rect.top) * scaleY
        };
    }

    function startDraw(e) {
        if (!canvas || !ctx) return;
        drawing = true;
        var pos = getCanvasPos(e);
        lastX = pos.x;
        lastY = pos.y;
        e.preventDefault();
    }

    function draw(e) {
        if (!drawing || !canvas || !ctx) return;
        var pos = getCanvasPos(e);
        ctx.beginPath();
        ctx.moveTo(lastX, lastY);
        ctx.lineTo(pos.x, pos.y);
        ctx.strokeStyle = '#111827';
        ctx.lineWidth = 2.4;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.stroke();
        lastX = pos.x;
        lastY = pos.y;
        hasDrawn = true;
        e.preventDefault();
    }

    function stopDraw(e) {
        drawing = false;
        if (e) e.preventDefault();
    }

    if (canvas && ctx) {
        canvas.addEventListener('mousedown', startDraw);
        canvas.addEventListener('mousemove', draw);
        canvas.addEventListener('mouseup', stopDraw);
        canvas.addEventListener('mouseleave', stopDraw);
        canvas.addEventListener('touchstart', startDraw, { passive: false });
        canvas.addEventListener('touchmove', draw, { passive: false });
        canvas.addEventListener('touchend', stopDraw, { passive: false });

        var clearBtn = document.getElementById('btnClearSignatureScp');
        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                hasDrawn = false;
            });
        }
    }
    var phpPostMaxSize = <?php echo getPostMaxSize(); ?>;
    var phpUploadMaxSize = <?php echo getUploadMaxSize(); ?>;

    function validateFormTotalSize(form) {
        var files = form.querySelectorAll('input[type="file"]');
        var total = 0;
        files.forEach(function(input) {
            if (input.files) {
                for (var i = 0; i < input.files.length; i++) {
                    total += input.files[i].size;
                }
            }
        });

        // Margen de seguridad del 5% para otros campos del formulario
        var limit = phpPostMaxSize * 0.95; 
        if (total > limit) {
            var msg = 'La suma total de los archivos (' + humanSize(total) + ') excede el límite permitido por el servidor (' + humanSize(phpPostMaxSize) + ').<br><br>Por favor, sube menos archivos o archivos más pequeños.';
            window.__showCreativePopScp && window.__showCreativePopScp(msg, 'Límite de subida excedido');
            return false;
        }
        return true;
    }

    var formReply = document.getElementById('form-reply');
    if (formReply) {
        formReply.addEventListener('submit', function(e) {
            if (!validateFormTotalSize(this)) {
                e.preventDefault();
            }
        });
    }

    var formEdit = document.querySelector('#modalEditEntry form');
    if (formEdit) {
        formEdit.addEventListener('submit', function(e) {
            if (!validateFormTotalSize(this)) {
                e.preventDefault();
                // Si el botón ya se puso en "Cargando", resetearlo (aunque usualmente el preventDefault es antes)
                var btn = this.querySelector('button[type="submit"]');
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = 'Guardar Cambios';
                }
            }
        });
    }
});
</script>

<!-- Modal Confirmar Facturación -->
<div class="modal fade" id="modalConfirmBilling" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 20px; border: none; box-shadow: 0 25px 60px rgba(0,0,0,0.2); overflow: hidden;">
            <div class="modal-header" style="border-bottom: 1px solid #f1f5f9; padding: 22px 26px; background: #fff;">
                <h5 class="modal-title" style="font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 12px; font-size: 1.15rem;">
                    <div style="width: 36px; height: 36px; background: #dcfce7; color: #166534; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-shield-check"></i>
                    </div>
                    Confirmar Facturación
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="padding: 26px; background: #fff;">
                <p style="color: #475569; font-size: 1rem; line-height: 1.6; margin-bottom: 18px;">
                    ¿Estás seguro de que deseas marcar este ticket como <strong style="color: #0f172a;">facturado</strong>? Esta acción confirmará el proceso de facturación de forma permanente.
                </p>
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 14px; border-radius: 12px; display: flex; align-items: center; justify-content: space-between; gap: 12px;">
                    <span style="font-size: 0.9rem; color: #64748b; font-weight: 600; line-height: 1.3;"><i class="bi bi-file-earmark-text me-1"></i> Puedes revisar los costos antes de confirmar:</span>
                    <a href="reporte_costos.php?ticket_id=<?php echo $tid; ?>" target="_blank" class="btn btn-sm" style="background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; font-weight: 700; border-radius: 8px; white-space: nowrap;">Ver Reporte</a>
                </div>
            </div>
            <div class="modal-footer" style="border-top: 1px solid #f1f5f9; padding: 20px 26px; gap: 12px; background: #f8fafc;">
                <button type="button" class="btn" data-bs-dismiss="modal" style="background: #fff; color: #64748b; font-weight: 700; border-radius: 12px; padding: 10px 20px; border: 1px solid #e2e8f0; font-size: 0.9rem;">Cancelar</button>
                <a href="tickets.php?id=<?php echo $tid; ?>&action=confirm_billing" class="btn" style="background: #15803d; color: #fff; font-weight: 700; border-radius: 12px; padding: 10px 24px; border: none; box-shadow: 0 4px 15px rgba(21, 128, 61, 0.3); font-size: 0.9rem;">Confirmar y Facturar</a>
            </div>
        </div>
    </div>
</div>

<style>
    .creative-pop-overlay-scp{position:fixed; inset:0; background:rgba(15,23,42,.46); display:none; align-items:center; justify-content:center; padding:18px; z-index:9999; backdrop-filter: blur(10px);}
    .creative-pop-scp{max-width:560px; width:100%; background:rgba(255,255,255,0.95); border:1px solid rgba(226,232,240,0.92); border-radius:22px; box-shadow:0 30px 90px rgba(15,23,42,.30); overflow:hidden; backdrop-filter: blur(10px); animation: creativePopInScp .14s ease-out;}
    .creative-pop-head-scp{display:flex; align-items:center; gap:12px; padding:18px 20px; background:linear-gradient(135deg,#1e293b,#0f172a); color:#fff;}
    .creative-pop-icon-scp{width:42px; height:42px; border-radius:12px; background:rgba(255,255,255,.1); display:flex; align-items:center; justify-content:center; flex:0 0 auto;}
    .creative-pop-title-scp{font-weight:900; margin:0; font-size:1.1rem; letter-spacing:-0.01em;}
    .creative-pop-body-scp{padding:24px 20px; color:#334155; font-weight:500; line-height:1.6; font-size:0.95rem;}
    .creative-pop-actions-scp{display:flex; gap:12px; justify-content:flex-end; padding:0 20px 20px;}
    .creative-pop-btn-scp{border:1px solid transparent; border-radius:12px; padding:12px 24px; font-weight:800; cursor:pointer; transition: all 0.2s;}
    .creative-pop-btn-scp.primary{background:#0f172a; color:#fff;}
    .creative-pop-btn-scp.primary:hover{transform: translateY(-2px); box-shadow: 0 4px 12px rgba(15, 23, 42, 0.2);}
    @keyframes creativePopInScp{from{transform:translateY(10px) scale(.98); opacity:0;}to{transform:translateY(0) scale(1); opacity:1;}}
</style>

<div class="creative-pop-overlay-scp" id="creativePopScp" role="dialog" aria-modal="true">
    <div class="creative-pop-scp">
        <div class="creative-pop-head-scp">
            <div class="creative-pop-icon-scp"><i class="bi bi-exclamation-triangle-fill" style="color: #f59e0b; font-size: 1.25rem;"></i></div>
            <div>
                <div class="creative-pop-title-scp" id="creativePopTitleScp">Advertencia</div>
                <div style="opacity:.7; font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:0.05em;">Aviso del sistema</div>
            </div>
            <button type="button" class="btn-close btn-close-white ms-auto" aria-label="Cerrar" onclick="window.__hideCreativePopScp && window.__hideCreativePopScp()"></button>
        </div>
        <div class="creative-pop-body-scp" id="creativePopMsgScp"></div>
        <div class="creative-pop-actions-scp">
            <button type="button" class="creative-pop-btn-scp primary" onclick="window.__hideCreativePopScp && window.__hideCreativePopScp()">Entendido</button>
        </div>
    </div>
</div>

<script>
    (function () {
        var overlay = document.getElementById('creativePopScp');
        var msgEl = document.getElementById('creativePopMsgScp');
        var titleEl = document.getElementById('creativePopTitleScp');
        window.__showCreativePopScp = function (msg, title) {
            if (!overlay || !msgEl) return;
            msgEl.innerHTML = msg || '';
            if (titleEl) titleEl.textContent = title || 'Advertencia';
            overlay.style.display = 'flex';
        };
        window.__hideCreativePopScp = function () {
            if (!overlay) return;
            overlay.style.display = 'none';
        };
        overlay && overlay.addEventListener('click', function (e) {
            if (e.target === overlay) window.__hideCreativePopScp();
        });
    })();
</script>

<script>
    function abrirWazeInteligente(event, appUrl, webUrl) {
        event.preventDefault();
        var isMobile = /Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent);
        if (isMobile) {
            // Intentar abrir la app nativa; si no está instalada, caer a la web
            var start = Date.now();
            window.location.href = appUrl;
            setTimeout(function () {
                // Si la página sigue visible (app no abrió), abrir la web
                if (Date.now() - start < 2000) {
                    window.open(webUrl, '_blank');
                }
            }, 1500);
        } else {
            window.open(webUrl, '_blank');
        }
    }
</script>

<!-- Modal Administrar Referidos -->
<div class="modal fade" id="modalReferrals" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px; overflow: hidden;">
            <div class="modal-header border-0 bg-dark text-white py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-share me-2"></i>Administrar Referidos</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-muted small mb-4">Los agentes o departamentos referidos podrán ver y participar en este ticket sin ser los propietarios primarios.</p>
                
                <form action="tickets.php?id=<?php echo $tid; ?>" method="POST" class="mb-4 p-3 rounded-3" style="background: rgba(0,0,0,0.02); border: 1px dashed rgba(0,0,0,0.1);">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="action" value="referral_add">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Referir a Departamento</label>
                        <select name="ref_dept_id" class="form-select border-0 shadow-sm">
                            <option value="0">— Seleccionar Departamento —</option>
                            <?php
                            $eid_ref = (int)empresaId();
                            $resD = $mysqli->query("SELECT id, name FROM departments WHERE empresa_id = $eid_ref AND is_active = 1 ORDER BY name");
                            if ($resD) {
                                while($d = $resD->fetch_assoc()) {
                                    echo "<option value='".(int)$d['id']."'>".html($d['name'])."</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>

                    <div class="text-center my-2 text-muted small">o</div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Referir a Agente</label>
                        <select name="ref_staff_id" class="form-select border-0 shadow-sm">
                            <option value="0">— Seleccionar Agente —</option>
                            <?php
                            $resS = $mysqli->query("SELECT id, firstname, lastname FROM staff WHERE empresa_id = $eid_ref AND is_active = 1 ORDER BY firstname");
                            if ($resS) {
                                while($s = $resS->fetch_assoc()) {
                                    echo "<option value='".(int)$s['id']."'>".html($s['firstname'].' '.$s['lastname'])."</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-danger w-100 fw-bold py-2 shadow-sm" style="border-radius: 10px;">
                        <i class="bi bi-plus-lg me-2"></i>Agregar Referencia
                    </button>
                </form>

                <h6 class="fw-bold mb-3 small text-uppercase letter-spacing-1">Referidos Actuales</h6>
                <div class="referrals-list">
                    <?php if (empty($ticketView['referrals'])): ?>
                        <div class="text-center py-4 text-muted border rounded-3 bg-light opacity-75">
                            <i class="bi bi-info-circle mb-2 d-block fs-4"></i>
                            No hay referidos para este ticket
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush border rounded-3">
                            <?php foreach ($ticketView['referrals'] as $ref): ?>
                                <div class="list-group-item d-flex align-items-center justify-content-between py-3">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="bg-light text-danger rounded-circle d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                                            <i class="bi <?php echo $ref['dept_id'] ? 'bi-building' : 'bi-person'; ?>"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold small"><?php echo $ref['dept_id'] ? html($ref['dept_name']) : html($ref['firstname'].' '.$ref['lastname']); ?></div>
                                            <div class="text-muted" style="font-size: 0.7rem;"><?php echo $ref['dept_id'] ? 'Departamento' : 'Agente'; ?></div>
                                        </div>
                                    </div>
                                    <a href="tickets.php?id=<?php echo $tid; ?>&action=referral_delete&ref_id=<?php echo $ref['id']; ?>" class="btn btn-sm btn-outline-danger border-0 rounded-circle" title="Eliminar">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Edit Support Times -->
<div class="modal fade" id="modalSupportTimes" tabindex="-1" aria-labelledby="modalSupportTimesLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
            <div class="modal-header border-0 bg-light" style="padding: 24px; border-bottom: 1px solid #e2e8f0 !important;">
                <h5 class="modal-title" id="modalSupportTimesLabel" style="font-weight: 800; color: #0f172a; font-size: 1.15rem; display: flex; align-items: center; gap: 10px;">
                    <div style="width: 38px; height: 38px; border-radius: 10px; background: #e0e7ff; color: #4f46e5; display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
                        <i class="bi bi-clock"></i>
                    </div>
                    Tiempos de Soporte
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="background-color: #f1f5f9; border-radius: 50%; padding: 10px;"></button>
            </div>
            <form action="tickets.php?id=<?php echo $tid; ?>" method="post">
                <input type="hidden" name="action" value="update_support_times">
                <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">
                <div class="modal-body" style="padding: 24px;">
                    <p style="color: #475569; font-size: 0.95rem; margin-bottom: 20px; line-height: 1.5;">
                        Ingresa la hora de inicio y fin del soporte para que consten en el reporte PDF.
                    </p>
                    <div class="mb-4">
                        <label class="form-label" style="font-weight: 700; color: #334155; font-size: 0.9rem; margin-bottom: 8px;">Hora de Inicio</label>
                        <input type="datetime-local" step="60" class="form-control" name="support_start" value="<?php echo !empty($t['support_start']) ? html(date('Y-m-d\TH:i', strtotime($t['support_start']))) : ''; ?>" style="border-radius: 10px; padding: 12px 16px; border: 2px solid #e2e8f0; font-size: 0.95rem; transition: all 0.2s ease;">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="font-weight: 700; color: #334155; font-size: 0.9rem; margin-bottom: 8px;">Hora de Fin</label>
                        <input type="datetime-local" step="60" class="form-control" name="support_end" value="<?php echo !empty($t['support_end']) ? html(date('Y-m-d\TH:i', strtotime($t['support_end']))) : ''; ?>" style="border-radius: 10px; padding: 12px 16px; border: 2px solid #e2e8f0; font-size: 0.95rem; transition: all 0.2s ease;">
                    </div>
                </div>
                <div class="modal-footer border-0" style="padding: 0 24px 24px; background: transparent;">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal" style="border-radius: 10px; font-weight: 600; padding: 10px 20px; color: #64748b; background: #f1f5f9; border: none; transition: all 0.2s;">Cancelar</button>
                    <button type="submit" class="btn btn-primary" style="border-radius: 10px; font-weight: 600; padding: 10px 24px; box-shadow: 0 4px 12px rgba(13, 110, 253, 0.2); transition: all 0.2s;">Guardar tiempos</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Prevenir que la rueda del ratón desplace el modal al usar el calendario nativo
document.addEventListener('DOMContentLoaded', function() {
    var dateInputs = document.querySelectorAll('input[type="datetime-local"]');
    var modal = document.getElementById('modalSupportTimes');
    
    dateInputs.forEach(function(input) {
        // Bloquear scroll del modal cuando el input está activo (calendario abierto)
        input.addEventListener('focus', function() {
            if (modal) modal.style.overflow = 'hidden';
        });
        
        input.addEventListener('blur', function() {
            if (modal) modal.style.overflow = 'auto'; // restaurar
        });

        // Prevenir cambiar el valor por error al hacer scroll sobre la caja de texto
        input.addEventListener('wheel', function(e) {
            e.preventDefault();
        });
    });
});
</script>
