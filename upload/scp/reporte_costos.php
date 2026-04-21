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

// 2. Verificar si ya existe un reporte
$reportExists = false;
$reportData = null;
$materials = [];

$chkStmt = $mysqli->prepare("SELECT * FROM ticket_reports WHERE ticket_id = ?");
$chkStmt->bind_param('i', $ticketId);
$chkStmt->execute();
$resR = $chkStmt->get_result();
if ($resR && $resR->num_rows > 0) {
    $reportExists = true;
    $reportData = $resR->fetch_assoc();

}

// 3. Procesar formulario si se envió (y no existe reporte)
$errors = [];
$successMsg = '';

if (!$reportExists && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !Auth::validateCSRF($_POST['csrf_token'])) {
        $errors[] = 'Token de seguridad inválido. Recarga la página.';
    } else {
        $desc = trim((string)($_POST['work_description'] ?? ''));
        $obs = trim((string)($_POST['observations'] ?? ''));
        $price = trim((string)($_POST['final_price'] ?? ''));
        

        if ($desc === '') {
            $errors[] = 'La descripción del trabajo es obligatoria.';
        }

        if (empty($errors)) {
            $mysqli->begin_transaction();
            try {
                // Insert report
                $sqlR = "INSERT INTO ticket_reports (ticket_id, work_description, observations, final_price, created_by, created_at)
                         VALUES (?, ?, ?, ?, ?, NOW())";
                $inR = $mysqli->prepare($sqlR);
                $sid = (int)$_SESSION['staff_id'];
                $inR->bind_param('isssi', $ticketId, $desc, $obs, $price, $sid);
                $inR->execute();
                
                $reportId = $mysqli->insert_id;


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

@media (max-width: 576px) {
    .material-card .mat-fields { grid-template-columns: 1fr; }
    .price-input-wrap { max-width: 100%; }
    .form-footer-sticky { flex-direction: column; }
    .form-footer-sticky .btn { width: 100%; justify-content: center; }
    .btn-pdf-action { width: 100%; }
    .price-display-box { flex-direction: column; align-items: flex-start; gap: 4px; }
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
                        <form method="POST" action="reporte_costos.php?ticket_id=<?php echo $ticketId; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                            <!-- Sección detalles -->
                            <h6 class="mb-3 border-bottom pb-2 text-primary mt-3"><i class="bi bi-card-checklist me-1"></i> Detalles del Trabajo</h6>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Descripción del trabajo realizado <span class="text-danger">*</span></label>
                                <textarea name="work_description" class="form-control" rows="4" required placeholder="Describe las acciones tomadas para resolver el ticket..."></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Observaciones adicionales <span class="text-muted fw-normal">(Opcional)</span></label>
                                <textarea name="observations" class="form-control" rows="2" placeholder="Cualquier nota extra relevante..."></textarea>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold">Precio final del servicio</label>
                                <div class="input-group price-input-wrap">
                                    <span class="input-group-text"><i class="bi bi-currency-dollar"></i></span>
                                    <input type="text" name="final_price" class="form-control" placeholder="Ej: 20.00">
                                </div>
                            </div>

                            <div class="form-footer-sticky">
                                <button type="submit" class="btn btn-primary d-flex align-items-center justify-content-center gap-2" style="background: linear-gradient(135deg, #2563eb, #1d4ed8); border: none;">
                                    <i class="bi bi-save"></i> Guardar Reporte
                                </button>
                            </div>
                        </form>

                    <?php else: ?>
                        <!-- Vista de reporte ya completado -->
                        <div class="alert alert-secondary text-dark mb-4">
                            <i class="bi bi-lock me-1"></i> Este ticket ya tiene un reporte generado. Los datos no pueden modificarse.
                        </div>

                        <h6 class="mb-3 border-bottom pb-2 text-primary mt-3"><i class="bi bi-card-checklist me-1"></i> Detalles del Trabajo</h6>
                        <div class="mb-3 p-3 bg-light rounded border">
                            <strong class="d-block text-secondary mb-1">Descripción:</strong>
                            <?php echo nl2br(htmlspecialchars($reportData['work_description'])); ?>
                        </div>

                        <?php if (!empty($reportData['observations'])): ?>
                        <div class="mb-3 p-3 bg-light rounded border">
                            <strong class="d-block text-secondary mb-1">Observaciones:</strong>
                            <?php echo nl2br(htmlspecialchars($reportData['observations'])); ?>
                        </div>
                        <?php endif; ?>

                        <div class="price-display-box">
                            <span class="price-label">Precio Final del Servicio</span>
                            <span class="price-value"><?php echo htmlspecialchars($reportData['final_price'] ?: 'No aplica'); ?></span>
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
