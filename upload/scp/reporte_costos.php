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
        `report_id` int(11) unsigned NOT NULL,
        `description` text NOT NULL,
        `price` decimal(10,2) NOT NULL DEFAULT 0.00,
        `created_at` datetime NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `idx_report_id` (`report_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// 2. Verificar si ya existe un reporte
$reportExists = false;
$reportData = null;
$reportItems = [];
$total = 0.00;

$chkStmt = $mysqli->prepare("SELECT * FROM ticket_reports WHERE ticket_id = ?");
$chkStmt->bind_param('i', $ticketId);
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
                $sqlR = "INSERT INTO ticket_reports (ticket_id, work_description, observations, final_price, created_by, created_at)
                         VALUES (?, ?, ?, ?, ?, NOW())";
                $inR = $mysqli->prepare($sqlR);
                $sid = (int)$_SESSION['staff_id'];
                $inR->bind_param('isssi', $ticketId, $workDescConcat, $obs, $totalStr, $sid);
                $inR->execute();

                $reportId = $mysqli->insert_id;

                // Insert items
                $insItem = $mysqli->prepare("INSERT INTO ticket_report_items (report_id, description, price) VALUES (?, ?, ?)");
                foreach ($items as $it) {
                    $insItem->bind_param('isd', $reportId, $it['desc'], $it['price']);
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
/* ── reporte_costos.php – Estilos responsivos adicionales ── */

/* ── Tarjeta de material: móvil usa diseño en tarjeta ── */
.material-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 12px 14px;
    margin-bottom: 10px;
    position: relative;
}
.material-card .mat-fields {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
}
.material-card .mat-label {
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #64748b;
    font-weight: 600;
    margin-bottom: 4px;
}
.material-card .btn-remove-row {
    position: absolute;
    top: 10px;
    right: 10px;
    border: none;
    background: none;
    color: #ef4444;
    font-size: 1rem;
    line-height: 1;
    padding: 2px 5px;
    border-radius: 6px;
    transition: background 0.15s;
}
.material-card .btn-remove-row:hover:not(:disabled) {
    background: #fee2e2;
}
.material-card .btn-remove-row:disabled {
    color: #cbd5e1;
}

/* ── Precio: ancho completo en móvil ── */
.price-input-wrap {
    max-width: 340px;
    width: 100%;
}

/* ── Botón guardar sticky en móvil ── */
.form-footer-sticky {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding-top: 16px;
    border-top: 1px solid #e2e8f0;
}

/* ── Vista de reporte completado: materiales en tarjetas en móvil ── */
.mat-read-list { list-style: none; padding: 0; margin: 0 0 16px; }
.mat-read-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 14px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    margin-bottom: 6px;
    gap: 10px;
    font-size: 0.9rem;
}
.mat-read-item .mat-name { color: #0f172a; font-weight: 500; }
.mat-read-item .mat-qty {
    white-space: nowrap;
    background: #f1f5f9;
    color: #475569;
    font-size: 0.8rem;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 20px;
}

/* ── Caja de precio completado ── */
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

/* ── Botón PDF full-width en móvil ── */
.btn-pdf-action { min-width: 200px; }

/* ── Tabla de items ── */
.items-table-wrap { margin-bottom: 12px; }
.items-table-wrap .table { margin-bottom: 0; }
.items-table-wrap .table tfoot td { border-top: 2px solid #cbd5e1; }
.items-table-wrap .table tfoot tr.table-primary td { background: #eff6ff !important; }

@media (max-width: 576px) {
    .material-card .mat-fields { grid-template-columns: 1fr; }
    .price-input-wrap { max-width: 100%; }
    .form-footer-sticky { flex-direction: column; }
    .form-footer-sticky .btn { width: 100%; justify-content: center; }
    .btn-pdf-action { width: 100%; }
    .price-display-box { flex-direction: column; align-items: flex-start; gap: 4px; }
    .items-table-wrap .table thead { display: none; }
    .items-table-wrap .table tbody tr {
        display: block;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 10px 12px;
        margin-bottom: 8px;
    }
    .items-table-wrap .table tbody td {
        display: block;
        border: none;
        padding: 4px 0;
    }
    .items-table-wrap .table tbody td:before {
        content: attr(data-label);
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #64748b;
        font-weight: 600;
        display: block;
        margin-bottom: 2px;
    }
    .items-table-wrap .table tbody td:last-child { text-align: right; margin-top: 4px; }
}
</style>

<div class="tickets-shell">
    <div class="tickets-header mb-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2">
            <div>
                <h1>Reporte de Costos</h1>
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
        <div class="alert alert-success mt-2 mb-3">
            <i class="bi bi-check-circle me-1"></i> <?php echo htmlspecialchars($successMsg); ?>
        </div>
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
                    <span class="badge bg-success">Completado</span>
                <?php else: ?>
                    <span class="badge bg-warning text-dark">Pendiente</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Formulario / Visualización del Reporte -->
    <div class="row">
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
                            <h6 class="mb-3 border-bottom pb-2 text-primary mt-3"><i class="bi bi-card-checklist me-1"></i> Detalles del Trabajo</h6>

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
                                </div>
                                <button type="button" class="btn btn-outline-primary btn-sm" id="btnAddItem">
                                    <i class="bi bi-plus-circle"></i> Agregar ítem
                                </button>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Observaciones adicionales <span class="text-muted fw-normal">(Opcional)</span></label>
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
                        <div class="alert alert-secondary text-dark mb-4">
                            <i class="bi bi-lock me-1"></i> Este ticket ya tiene un reporte generado. Los datos no pueden modificarse.
                        </div>

                        <?php if (!empty($reportData['observations'])): ?>
                        <div class="mb-3 p-3 bg-light rounded border">
                            <strong class="d-block text-secondary mb-1">Observaciones:</strong>
                            <?php echo nl2br(htmlspecialchars($reportData['observations'])); ?>
                        </div>
                        <?php endif; ?>

                        <h6 class="mb-3 border-bottom pb-2 text-primary mt-3"><i class="bi bi-card-checklist me-1"></i> Detalle de Trabajos Realizados</h6>
                        <div class="items-table-wrap">
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

                        <div class="d-flex justify-content-end gap-2 mt-3">
                            <a href="reporte_pdf.php?report_id=<?php echo (int)$reportData['id']; ?>" target="_blank" class="btn btn-outline-primary btn-sm btn-pdf-action">
                                <i class="bi bi-file-earmark-pdf"></i> Descargar PDF
                            </a>
                        </div>

                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>


<?php
$content = ob_get_clean();
require_once __DIR__ . '/layout/layout.php';
