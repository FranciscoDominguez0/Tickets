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
$stmt = $mysqli->prepare("SELECT t.id, t.ticket_number, t.subject, t.closed, t.dept_id,
                                 d.name as department_name, d.requires_report,
                                 s.firstname as staff_first, s.lastname as staff_last
                          FROM tickets t
                          JOIN departments d ON t.dept_id = d.id
                          LEFT JOIN staff s ON t.staff_id = s.id
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

    $matStmt = $mysqli->prepare("SELECT * FROM ticket_report_materials WHERE report_id = ? ORDER BY id ASC");
    $matStmt->bind_param('i', $reportData['id']);
    $matStmt->execute();
    $resM = $matStmt->get_result();
    while ($m = $resM->fetch_assoc()) {
        $materials[] = $m;
    }
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
        
        $matNames = $_POST['mat_name'] ?? [];
        $matQtys = $_POST['mat_qty'] ?? [];

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

                // Insert materials
                if (is_array($matNames)) {
                    $sqlM = "INSERT INTO ticket_report_materials (report_id, material_name, quantity) VALUES (?, ?, ?)";
                    $inM = $mysqli->prepare($sqlM);
                    foreach ($matNames as $k => $mname) {
                        $mname = trim((string)$mname);
                        $mqty = trim((string)($matQtys[$k] ?? ''));
                        if ($mname !== '' && $mqty !== '') {
                            $inM->bind_param('iss', $reportId, $mname, $mqty);
                            $inM->execute();
                        }
                    }
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
<div class="tickets-shell">
    <div class="tickets-header mb-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2">
            <div>
                <h1>Registro de Reporte de Ticket</h1>
                <div class="sub">Ticket #<?php echo htmlspecialchars($ticket['ticket_number']); ?> - <?php echo htmlspecialchars($ticket['subject']); ?></div>
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

    <div class="row">
        <!-- Panel Izquierdo: Formulario -->
        <div class="col-lg-8 mb-4">
            <div class="card settings-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong><i class="bi bi-card-text me-2"></i> Datos del Reporte</strong>
                    <?php if ($reportExists): ?>
                        <span class="badge bg-success">Completado</span>
                    <?php else: ?>
                        <span class="badge bg-warning text-dark">Pendiente</span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (!$reportExists): ?>
                        <form method="POST" action="reporte_costos.php?ticket_id=<?php echo $ticketId; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            
                            <h6 class="mb-3 border-bottom pb-2 text-primary"><i class="bi bi-tools me-1"></i> Materiales Utilizados</h6>
                            <div id="materials-container">
                                <div class="row g-2 mb-2 material-row align-items-end">
                                    <div class="col-md-6">
                                        <label class="form-label small text-muted">Nombre del material</label>
                                        <input type="text" name="mat_name[]" class="form-control" placeholder="Ej: Cable LAN Cat6">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small text-muted">Cantidad / Medida</label>
                                        <input type="text" name="mat_qty[]" class="form-control" placeholder="Ej: 50 metros">
                                    </div>
                                    <div class="col-md-2">
                                        <button type="button" class="btn btn-outline-danger w-100 btn-remove-row" disabled><i class="bi bi-trash"></i></button>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-4 mt-2">
                                <button type="button" class="btn btn-sm btn-outline-primary" id="btn-add-row"><i class="bi bi-plus-lg"></i> Agregar Material</button>
                            </div>

                            <h6 class="mb-3 border-bottom pb-2 text-primary mt-4"><i class="bi bi-card-checklist me-1"></i> Detalles del Trabajo</h6>
                            
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
                                <div class="input-group" style="max-width: 300px;">
                                    <span class="input-group-text"><i class="bi bi-currency-dollar"></i></span>
                                    <input type="text" name="final_price" class="form-control" placeholder="Ej: 1500.00 o Revisión Gratuita">
                                </div>
                                <div class="form-text">Si aplica, ingresa el monto facturado o un acuerdo de costo.</div>
                            </div>

                            <div class="border-top pt-3 d-flex justify-content-end">
                                <button type="submit" class="btn btn-primary" style="background: linear-gradient(135deg, #2563eb, #1d4ed8); border: none;"><i class="bi bi-save me-1"></i> Guardar Reporte</button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-secondary text-dark mb-4">
                            Este ticket ya tiene un reporte generado. Los datos no pueden modificarse.
                        </div>

                        <h6 class="mb-3 border-bottom pb-2 text-primary"><i class="bi bi-tools me-1"></i> Materiales Utilizados</h6>
                        <?php if (empty($materials)): ?>
                            <p class="text-muted fst-italic">No se registraron materiales.</p>
                        <?php else: ?>
                            <div class="table-responsive mb-4">
                                <table class="table table-sm table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Material</th>
                                            <th style="width: 30%;">Cantidad / Medida</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($materials as $m): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($m['material_name']); ?></td>
                                                <td><?php echo htmlspecialchars($m['quantity']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>

                        <h6 class="mb-3 border-bottom pb-2 text-primary"><i class="bi bi-card-checklist me-1"></i> Detalles del Trabajo</h6>
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

                        <div class="mb-3 p-3 bg-light rounded border border-info align-items-center d-inline-block">
                            <strong class="text-secondary">Precio Final:</strong>
                            <span class="fs-5 ms-2 fw-bold text-dark"><?php echo htmlspecialchars($reportData['final_price'] ?: 'No aplica'); ?></span>
                        </div>
                        
                        <div class="mt-4 pt-3 border-top">
                            <a href="reporte_pdf.php?report_id=<?php echo (int)$reportData['id']; ?>" target="_blank" class="btn btn-outline-danger btn-lg"><i class="bi bi-file-earmark-pdf me-2"></i> Generar / Ver PDF</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Panel Derecho: Info ticket -->
        <div class="col-lg-4">
            <div class="card settings-card">
                <div class="card-header bg-light">
                    <strong>Resumen del Ticket</strong>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span class="text-muted"><i class="bi bi-hash"></i> Número</span>
                            <strong class="text-dark"><?php echo htmlspecialchars($ticket['ticket_number']); ?></strong>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span class="text-muted"><i class="bi bi-building"></i> Departamento</span>
                            <span class="badge bg-secondary"><?php echo htmlspecialchars($ticket['department_name']); ?></span>
                        </li>
                        <li class="list-group-item px-0">
                            <span class="text-muted d-block mb-1"><i class="bi bi-person-badge"></i> Técnico asignado</span>
                            <strong class="text-dark">
                                <?php 
                                $sName = trim(($ticket['staff_first'] ?? '') . ' ' . ($ticket['staff_last'] ?? ''));
                                echo $sName !== '' ? htmlspecialchars($sName) : 'Sin asignar';
                                ?>
                            </strong>
                        </li>
                        <li class="list-group-item px-0">
                            <span class="text-muted d-block mb-1"><i class="bi bi-calendar-check"></i> Fecha de cierre</span>
                            <strong class="text-dark"><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($ticket['closed']))); ?></strong>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('materials-container');
    const btnAdd = document.getElementById('btn-add-row');

    if (!container || !btnAdd) return;

    // Helper to update trash buttons (disable if only 1 row)
    function updateTrashButtons() {
        const rows = container.querySelectorAll('.material-row');
        const buttons = container.querySelectorAll('.btn-remove-row');
        buttons.forEach(btn => {
            btn.disabled = rows.length <= 1;
        });
    }

    // Add new row
    btnAdd.addEventListener('click', function() {
        const firstRow = container.querySelector('.material-row');
        if (!firstRow) return;
        const newRow = firstRow.cloneNode(true);
        // Clear inputs
        const inputs = newRow.querySelectorAll('input');
        inputs.forEach(input => input.value = '');
        container.appendChild(newRow);
        updateTrashButtons();
    });

    // Delegated event for removing row
    container.addEventListener('click', function(e) {
        if (e.target.closest('.btn-remove-row')) {
            const rowCount = container.querySelectorAll('.material-row').length;
            if (rowCount > 1) {
                const row = e.target.closest('.material-row');
                row.remove();
                updateTrashButtons();
            }
        }
    });

    updateTrashButtons();
});
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/layout/layout.php';
