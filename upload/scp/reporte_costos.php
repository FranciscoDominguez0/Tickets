<?php
require_once '../../config.php';
require_once '../../includes/helpers.php';
require_once '../../includes/Auth.php';

// Verificación de autenticación
if (!isset($_SESSION['staff_id'])) {
    header('Location: login.php');
    exit;
}

requireLogin('agente');
$staff = getCurrentUser();

$currentRoute = 'reportes';
$eid = empresaId();

$ticketId = isset($_GET['ticket_id']) ? (int)$_GET['ticket_id'] : 0;
if ($ticketId <= 0) {
    die("Ticket no especificado.");
}

// 1. Obtener datos del ticket para validar
$stmt = $mysqli->prepare("SELECT t.id, t.ticket_number, t.subject, t.closed, t.dept_id, t.user_id,
                                 d.name as department_name, d.requires_report,
                                 s.firstname as staff_first, s.lastname as staff_last,
                                 u.firstname as user_first, u.lastname as user_last, u.email as user_email
                          FROM tickets t
                          JOIN departments d ON t.dept_id = d.id
                          LEFT JOIN staff s ON t.staff_id = s.id
                          LEFT JOIN users u ON t.user_id = u.id
                          WHERE t.id = ? AND t.empresa_id = ?");
$stmt->bind_param('ii', $ticketId, $eid);
$stmt->execute();
$ticket = $stmt->get_result()->fetch_assoc();

if (!$ticket) {
    die("Ticket no existe o no tiene acceso.");
}
if ((int)($ticket['requires_report'] ?? 0) === 0) {
    die("El departamento de este ticket no requiere reporte.");
}
if (empty($ticket['closed'])) {
    die("El ticket no está cerrado, no se puede realizar el reporte.");
}

// Marcar como visto → quitar badge NEW en la lista (Persistente en DB)
$sid = (int)$_SESSION['staff_id'];
$mysqli->query("INSERT IGNORE INTO staff_reports_seen (staff_id, ticket_id) VALUES ($sid, $ticketId)");

// ── Crear tabla de items si no existe ──────────────────────────────────────
$tblCheck = $mysqli->query("SHOW TABLES LIKE 'ticket_report_items'");
if (!$tblCheck || $tblCheck->num_rows === 0) {
    $mysqli->query("CREATE TABLE `ticket_report_items` (
        `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
        `empresa_id` int(11) NOT NULL DEFAULT 1,
        `report_id` int(11) unsigned NOT NULL,
        `description` text NOT NULL,
        `price` decimal(10,2) NOT NULL DEFAULT 0.00,
        `created_at` datetime NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `idx_empresa_id` (`empresa_id`),
        KEY `idx_report_id` (`report_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// 2. Verificar si ya existe un reporte
$reportExists = false;
$reportData = null;
$reportItems = [];
$total = 0.00;

$chkStmt = $mysqli->prepare("SELECT * FROM ticket_reports WHERE ticket_id = ? AND empresa_id = ?");
$chkStmt->bind_param('ii', $ticketId, $eid);
$chkStmt->execute();
$resR = $chkStmt->get_result();
if ($resR && $resR->num_rows > 0) {
    $reportExists = true;
    $reportData = $resR->fetch_assoc();

    $itemsStmt = $mysqli->prepare("SELECT * FROM ticket_report_items WHERE report_id = ? ORDER BY id ASC");
    $itemsStmt->bind_param('i', $reportData['id']);
    $itemsStmt->execute();
    $itemsRes = $itemsStmt->get_result();
    while ($it = $itemsRes->fetch_assoc()) {
        $reportItems[] = $it;
        $total += (float)$it['price'];
    }
}

// 3. Procesar formulario si se envió (y no existe reporte)
$errors = [];
$successMsg = '';

if (!$reportExists && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !Auth::validateCSRF($_POST['csrf_token'])) {
        $errors[] = 'Token de seguridad inválido. Recarga la página.';
    } else {
        $obs = trim((string)($_POST['observations'] ?? ''));
        $itemDescs = (array) ($_POST['item_description'] ?? []);
        $itemPrices = (array) ($_POST['item_price'] ?? []);

        $items = [];
        $count = max(count($itemDescs), count($itemPrices));
        for ($i = 0; $i < $count; $i++) {
            $d = trim((string)($itemDescs[$i] ?? ''));
            $p = trim((string)($itemPrices[$i] ?? ''));
            if ($d !== '' && $p !== '') {
                $items[] = ['desc' => $d, 'price' => (float) str_replace(',', '.', $p)];
            }
        }

        if (count($items) === 0) {
            $errors[] = 'Debe agregar al menos un ítem con descripción y precio.';
        }

        if (empty($errors)) {
            $total = array_sum(array_column($items, 'price'));

            // Concatenar descripciones para compatibilidad con columnas existentes
            $workDescLines = [];
            foreach ($items as $it) {
                $workDescLines[] = $it['desc'] . ' → $' . number_format($it['price'], 2);
            }
            $workDescConcat = implode("\n", $workDescLines);
            $totalStr = number_format($total, 2, '.', '');

            $mysqli->begin_transaction();
            try {
                // Insert report
                $sqlR = "INSERT INTO ticket_reports (empresa_id, ticket_id, work_description, observations, final_price, created_by, billing_status, created_at)
                         VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())";
                $inR = $mysqli->prepare($sqlR);
                $sid = (int)$_SESSION['staff_id'];
                $eid = empresaId();
                $inR->bind_param('iisssi', $eid, $ticketId, $workDescConcat, $obs, $totalStr, $sid);
                $inR->execute();

                $reportId = $mysqli->insert_id;

                // Insert items
                $insItem = $mysqli->prepare("INSERT INTO ticket_report_items (empresa_id, report_id, description, price) VALUES (?, ?, ?, ?)");
                foreach ($items as $it) {
                    $insItem->bind_param('iisd', $eid, $reportId, $it['desc'], $it['price']);
                    $insItem->execute();
                }

                $mysqli->commit();
                // Redirigir para limpiar POST
                header("Location: reporte_costos.php?ticket_id=$ticketId&msg=saved");
                exit;
            } catch (Exception $e) {
                $mysqli->rollback();
                $errors[] = 'Error al guardar en base de datos: ' . $e->getMessage();
            }
        }
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'saved') {
    $successMsg = "Reporte guardado correctamente.";
}

ob_start();
?>
<style>
/* ═══════════════════════════════════════════════════════════════
   reporte_costos.php – Mobile-First Responsive Styles
   ═══════════════════════════════════════════════════════════════ */

/* ── Base / Mobile First ── */
.tickets-shell { padding: 0.5rem; }
.tickets-header h1 { font-size: 1.25rem; }

/* Overview: mobile-only cards; desktop uses original tickets.css */
@media (max-width: 767px) {
    .ticket-view-overview {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
        background: transparent;
        border: none;
        border-radius: 0;
        padding: 0;
        box-shadow: none;
    }
    .ticket-view-overview .field {
        display: flex;
        flex-direction: column;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 12px;
        box-shadow: 0 1px 2px rgba(0,0,0,0.04);
        margin-bottom: 0;
    }
    .ticket-view-overview label {
        font-size: 0.6rem;
        text-transform: uppercase;
        letter-spacing: 0.07em;
        color: #94a3b8;
        font-weight: 700;
        margin-bottom: 6px;
    }
    .ticket-view-overview .value {
        font-size: 0.9rem;
        color: #0f172a;
        font-weight: 600;
        line-height: 1.3;
        word-break: break-word;
    }
}

/* Cards */
.settings-card {
    border-radius: 14px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 1px 4px rgba(0,0,0,0.04);
}
.settings-card .card-header {
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    font-size: 0.9rem;
    padding: 12px 14px;
}
.settings-card .card-body { padding: 14px; }

/* Section titles */
h6.border-bottom {
    font-size: 0.85rem;
    padding-bottom: 8px;
    margin-top: 18px;
}

/* ── Items table: card list on mobile ── */
.items-table-wrap { margin-bottom: 12px; }
.items-table-wrap .table { margin-bottom: 0; }
.items-table-wrap .table tfoot td { border-top: 2px solid #cbd5e1; }
.items-table-wrap .table tfoot tr.table-primary td { background: #eff6ff !important; }

/* Mobile cards for dynamic rows */
@media (max-width: 576px) {
    .items-table-wrap .table thead,
    .items-table-wrap .table tfoot { display: none; }

    .items-table-wrap .table tbody tr {
        display: flex;
        flex-direction: column;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 12px 14px;
        margin-bottom: 10px;
        gap: 8px;
    }
    .items-table-wrap .table tbody td {
        display: block;
        border: none;
        padding: 0;
        width: 100% !important;
    }
    .items-table-wrap .table tbody td[data-label]::before {
        content: attr(data-label);
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #94a3b8;
        font-weight: 700;
        display: block;
        margin-bottom: 4px;
    }
    .items-table-wrap .table tbody td:last-child {
        text-align: right;
        margin-top: 2px;
    }
    .items-table-wrap .table tbody input.form-control {
        font-size: 1rem; /* prevent zoom on iOS */
        padding: 10px 12px;
        height: auto;
    }

    /* Show total as a sticky bar on mobile */
    .mobile-total-bar {
        display: flex !important;
        align-items: center;
        justify-content: space-between;
        position: sticky;
        bottom: 8px;
        background: #1e3a8a;
        color: #fff;
        padding: 12px 16px;
        border-radius: 12px;
        font-weight: 700;
        font-size: 1.05rem;
        box-shadow: 0 4px 12px rgba(30,58,138,0.25);
        margin-top: 8px;
        z-index: 10;
    }
}
@media (min-width: 577px) {
    .mobile-total-bar { display: none !important; }
}

/* ── Footer buttons ── */
.form-footer-sticky {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding-top: 16px;
    border-top: 1px solid #e2e8f0;
}
@media (max-width: 576px) {
    .form-footer-sticky { flex-direction: column; }
    .form-footer-sticky .btn {
        width: 100%;
        justify-content: center;
        padding: 12px;
        font-size: 1rem;
    }
    .btn-pdf-action { width: 100%; padding: 12px; }
}

/* ── Alerts ── */
.alert { border-radius: 12px; font-size: 0.9rem; }

/* ── Read-only list (completed report) ── */
.mat-read-list { list-style: none; padding: 0; margin: 0 0 16px; }
.mat-read-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 14px;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
    margin-bottom: 8px;
    gap: 10px;
    font-size: 0.95rem;
    background: #f8fafc;
}
.mat-read-item .mat-name { color: #0f172a; font-weight: 500; }
.mat-read-item .mat-qty {
    white-space: nowrap;
    background: #f1f5f9;
    color: #475569;
    font-size: 0.85rem;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: 20px;
}

/* ── Price display (completed) ── */
.price-display-box {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 18px;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 10px;
    margin-bottom: 16px;
}
.price-display-box .price-label { font-weight: 600; color: #64748b; font-size: 0.85rem; }
.price-display-box .price-value { font-size: 1.3rem; font-weight: 700; color: #1e3a8a; }
@media (max-width: 576px) {
    .price-display-box { flex-direction: column; align-items: flex-start; gap: 4px; }
}

/* ── Desktop enhancements ── */
@media (min-width: 768px) {
    .tickets-shell { padding: 1rem; }
    .tickets-header h1 { font-size: 1.5rem; }
}
</style>

<div class="tickets-shell">
    <div class="tickets-header mb-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2">
            <div>
                <h1>Facturación</h1>
            </div>
            <div>
                <a href="reporte_tickets.php" class="btn btn-outline-light btn-sm"><i class="bi bi-arrow-left"></i> Volver a Reportes</a>
            </div>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $e) echo '<li>'.htmlspecialchars($e).'</li>'; ?>
            </ul>
        </div>
    <?php endif; ?>
    <?php if ($successMsg): ?>
        <div class="alert alert-success mt-2 mb-3" id="autoDismissAlert" style="transition: opacity 0.5s ease;">
            <i class="bi bi-check-circle me-1"></i> <?php echo htmlspecialchars($successMsg); ?>
        </div>
        <script>
            setTimeout(function() {
                var alert = document.getElementById('autoDismissAlert');
                if (alert) {
                    alert.style.opacity = '0';
                    setTimeout(function() { alert.remove(); }, 500);
                }
            }, 3500);
        </script>
    <?php endif; ?>

    <?php
    $clientName = trim(($ticket['user_first'] ?? '') . ' ' . ($ticket['user_last'] ?? ''));
    $clientName = $clientName !== '' ? $clientName : ($ticket['user_email'] ?? 'Usuario Web');
    $staffName = trim(($ticket['staff_first'] ?? '') . ' ' . ($ticket['staff_last'] ?? ''));
    $staffName = $staffName !== '' ? $staffName : 'Sin asignar';
    $closedDate = !empty($ticket['closed']) ? date('d/m/Y H:i', strtotime($ticket['closed'])) : 'N/A';
    ?>

    <!-- Sección de Información General Arriba -->
    <div class="ticket-view-overview mb-4">
        <div class="field">
            <label>Número de Ticket</label>
            <div class="value fs-5"><a href="tickets.php?id=<?php echo $ticketId; ?>" class="text-decoration-none">#<?php echo htmlspecialchars($ticket['ticket_number']); ?></a></div>
        </div>
        <div class="field">
            <label>Cliente (Dueño)</label>
            <div class="value mt-1"><?php echo htmlspecialchars($clientName); ?></div>
        </div>
        <div class="field">
            <label>Departamento</label>
            <div class="value mt-1"><span class="badge bg-secondary"><?php echo htmlspecialchars($ticket['department_name']); ?></span></div>
        </div>
        <div class="field">
            <label>Técnico Asignado</label>
            <div class="value mt-1 text-primary fw-bold">
                <i class="bi bi-person-badge"></i> <?php echo htmlspecialchars($staffName); ?>
            </div>
        </div>
        <div class="field">
            <label>Fecha de Cierre</label>
            <div class="value mt-1 text-muted"><?php echo htmlspecialchars($closedDate); ?></div>
        </div>
        <div class="field">
            <label>Estado del Reporte</label>
            <div class="value mt-1">
                <?php if ($reportExists): ?>
                    <?php if (($reportData['billing_status'] ?? 'pending') === 'confirmed'): ?>
                        <span class="badge bg-success">Facturado</span>
                    <?php else: ?>
                        <span class="badge bg-warning text-dark">Pendiente Facturación</span>
                    <?php endif; ?>
                <?php else: ?>
                    <span class="badge bg-secondary">Sin Reporte</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Formulario / Visualización del Reporte -->
    <div class="row g-3">
        <div class="col-12">
            <div class="card settings-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong><i class="bi bi-card-text me-2"></i> Datos del Reporte</strong>
                </div>
                <div class="card-body">
                    <?php if (!$reportExists): ?>
                        <form method="POST" action="reporte_costos.php?ticket_id=<?php echo $ticketId; ?>" id="reportForm">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                            <!-- Sección detalles -->
                            <h6 class="mb-3 border-bottom pb-2 text-primary mt-2"><i class="bi bi-card-checklist me-1"></i> Detalles del Trabajo</h6>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Trabajos realizados <span class="text-danger">*</span></label>
                                <div class="items-table-wrap">
                                    <table class="table table-sm table-bordered" id="itemsTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 60%;">Descripción</th>
                                                <th style="width: 30%;">Precio (USD)</th>
                                                <th style="width: 10%;"></th>
                                            </tr>
                                        </thead>
                                        <tbody id="itemsBody">
                                            <!-- Filas dinámicas -->
                                        </tbody>
                                        <tfoot>
                                            <tr class="table-primary">
                                                <td class="text-end fw-bold">Total:</td>
                                                <td colspan="2" class="fw-bold" id="totalDisplay">$0.00</td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                    <!-- Mobile-only total bar -->
                                    <div class="mobile-total-bar" id="mobileTotalBar">
                                        <span>Total</span>
                                        <span id="mobileTotalDisplay">$0.00</span>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-outline-primary btn-sm w-100 w-md-auto mt-2" id="btnAddItem">
                                    <i class="bi bi-plus-circle"></i> Agregar ítem
                                </button>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Observaciones <span class="text-muted fw-normal">(Opcional)</span></label>
                                <textarea name="observations" class="form-control" rows="2" placeholder="Cualquier nota extra relevante..."></textarea>
                            </div>

                            <div class="form-footer-sticky">
                                <button type="submit" class="btn btn-primary d-flex align-items-center justify-content-center gap-2" style="background: linear-gradient(135deg, #2563eb, #1d4ed8); border: none;">
                                    <i class="bi bi-save"></i> Guardar Reporte
                                </button>
                            </div>
                        </form>

                        <script>
                        (function() {
                            const itemsBody = document.getElementById('itemsBody');
                            const btnAdd = document.getElementById('btnAddItem');

                            function formatMoney(n) {
                                return '$' + parseFloat(n || 0).toFixed(2);
                            }

                            function recalc() {
                                let total = 0;
                                itemsBody.querySelectorAll('tr').forEach(function(row) {
                                    const priceInput = row.querySelector('.item-price');
                                    const price = parseFloat(priceInput ? priceInput.value : 0) || 0;
                                    total += price;
                                });
                                document.getElementById('totalDisplay').textContent = formatMoney(total);
                                const mobileTotal = document.getElementById('mobileTotalDisplay');
                                if (mobileTotal) mobileTotal.textContent = formatMoney(total);
                            }

                            function addRow(desc, price) {
                                const tr = document.createElement('tr');
                                tr.innerHTML = '<td data-label="Descripción">' +
                                    '<input type="text" name="item_description[]" class="form-control form-control-sm item-desc" value="' + (desc || '') + '" placeholder="Ej: Instalación de panel" required>' +
                                    '</td>' +
                                    '<td data-label="Precio (USD)">' +
                                    '<input type="number" name="item_price[]" class="form-control form-control-sm item-price" value="' + (price || '') + '" step="0.01" min="0" placeholder="0.00" required>' +
                                    '</td>' +
                                    '<td data-label="Acción" class="text-center">' +
                                    '<button type="button" class="btn btn-link btn-sm text-danger btn-remove-item" title="Eliminar"><i class="bi bi-trash"></i></button>' +
                                    '</td>';

                                tr.querySelector('.btn-remove-item').addEventListener('click', function() {
                                    if (itemsBody.querySelectorAll('tr').length > 1) {
                                        tr.remove();
                                        recalc();
                                    }
                                });
                                tr.querySelector('.item-price').addEventListener('input', recalc);
                                itemsBody.appendChild(tr);
                                recalc();
                            }

                            btnAdd.addEventListener('click', function() {
                                addRow();
                            });

                            // Inicializar con una fila
                            addRow();

                            // Validación antes de enviar
                            document.getElementById('reportForm').addEventListener('submit', function(e) {
                                const rows = itemsBody.querySelectorAll('tr');
                                let valid = false;
                                rows.forEach(function(row) {
                                    const d = row.querySelector('.item-desc').value.trim();
                                    const p = row.querySelector('.item-price').value.trim();
                                    if (d !== '' && p !== '') valid = true;
                                });
                                if (!valid) {
                                    e.preventDefault();
                                    alert('Debe agregar al menos un ítem con descripción y precio.');
                                }
                            });
                        })();
                        </script>

                    <?php else: ?>
                        <!-- Vista de reporte ya completado -->
                        <div class="alert alert-secondary text-dark mb-3">
                            <i class="bi bi-lock me-1"></i> Este ticket ya tiene un reporte generado. Los datos no pueden modificarse.
                        </div>

                        <?php if (!empty($reportData['observations'])): ?>
                        <div class="mb-3 p-3 bg-light rounded border">
                            <strong class="d-block text-secondary mb-1">Observaciones:</strong>
                            <?php echo nl2br(htmlspecialchars($reportData['observations'])); ?>
                        </div>
                        <?php endif; ?>

                        <h6 class="mb-3 border-bottom pb-2 text-primary mt-3"><i class="bi bi-card-checklist me-1"></i> Detalle de Trabajos Realizados</h6>

                        <!-- Mobile card list -->
                        <div class="d-block d-md-none">
                            <?php foreach ($reportItems as $it): ?>
                            <div class="mat-read-item">
                                <span class="mat-name"><?php echo htmlspecialchars($it['description']); ?></span>
                                <span class="mat-qty">$<?php echo number_format((float)$it['price'], 2); ?></span>
                            </div>
                            <?php endforeach; ?>
                            <div class="price-display-box">
                                <span class="price-label">Total del Servicio</span>
                                <span class="price-value">$<?php echo number_format($total, 2); ?></span>
                            </div>
                        </div>

                        <!-- Desktop table -->
                        <div class="d-none d-md-block items-table-wrap">
                            <table class="table table-sm table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>Descripción</th>
                                        <th class="text-end" style="width: 160px;">Precio</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reportItems as $it): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($it['description']); ?></td>
                                        <td class="text-end">$<?php echo number_format((float)$it['price'], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="table-primary">
                                        <td class="text-end fw-bold">Total:</td>
                                        <td class="text-end fw-bold">$<?php echo number_format($total, 2); ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <div class="d-flex flex-column flex-md-row justify-content-end gap-2 mt-3">
                            <a href="reporte_pdf.php?report_id=<?php echo (int)$reportData['id']; ?>" class="btn btn-outline-primary btn-sm btn-pdf-action">
                                <i class="bi bi-file-earmark-pdf"></i> Descargar PDF
                            </a>
                        </div>

                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const pdfBtn = document.querySelector('.btn-pdf-action');
    if (pdfBtn) {
        pdfBtn.addEventListener('click', function() {
            if (document.getElementById('downloadLoadingOverlay')) return;
            const overlay = document.createElement('div');
            overlay.id = 'downloadLoadingOverlay';
            overlay.style.position = 'fixed';
            overlay.style.top = '0';
            overlay.style.left = '0';
            overlay.style.width = '100vw';
            overlay.style.height = '100vh';
            overlay.style.backgroundColor = 'rgba(0, 0, 0, 0.7)';
            overlay.style.display = 'flex';
            overlay.style.flexDirection = 'column';
            overlay.style.justifyContent = 'center';
            overlay.style.alignItems = 'center';
            overlay.style.zIndex = '9999';
            overlay.style.color = '#fff';
            overlay.style.backdropFilter = 'blur(3px)';
            overlay.style.transition = 'opacity 0.3s ease';

            const spinner = document.createElement('div');
            spinner.className = 'spinner-border text-light mb-3';
            spinner.style.width = '3rem';
            spinner.style.height = '3rem';
            spinner.setAttribute('role', 'status');

            const text = document.createElement('h4');
            text.textContent = 'Generando PDF, por favor espere...';
            text.style.fontWeight = '600';
            text.style.textShadow = '0 2px 4px rgba(0,0,0,0.5)';

            const subtext = document.createElement('div');
            subtext.textContent = 'Esto puede demorar unos segundos.';
            subtext.style.opacity = '0.8';

            overlay.appendChild(spinner);
            overlay.appendChild(text);
            overlay.appendChild(subtext);
            document.body.appendChild(overlay);

            // Clear token cookie before starting
            document.cookie = "fileDownloadToken=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";

            // Polling para detectar cuando la descarga finalice
            const tokenCheck = setInterval(() => {
                if (document.cookie.includes('fileDownloadToken=true')) {
                    clearInterval(tokenCheck);
                    // Clean up cookie
                    document.cookie = "fileDownloadToken=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
                    if(document.body.contains(overlay)) {
                        overlay.style.opacity = '0';
                        setTimeout(() => overlay.remove(), 300);
                    }
                }
            }, 500);

            // Fallback after 60 seconds just in case it fails
            setTimeout(() => {
                clearInterval(tokenCheck);
                if(document.body.contains(overlay)) {
                    overlay.style.opacity = '0';
                    setTimeout(() => overlay.remove(), 300);
                }
            }, 60000);
        });
    }
});
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/layout/layout.php';
