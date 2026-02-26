<?php
require_once '../../../config.php';
require_once '../../../includes/helpers.php';

ob_start();

$empresas = [];
$mensaje = null;
$error = null;

/* =========================
   CARGAR EMPRESAS ACTIVAS
   ========================= */
if (isset($mysqli) && $mysqli) {
    $res = $mysqli->query("SELECT id, nombre FROM empresas WHERE estado != 'bloqueada' ORDER BY nombre");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $empresas[] = $row;
        }
    }
}

/* =========================
   REGISTRAR PAGO
   ========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $empresa_id = (int)($_POST['empresa_id'] ?? 0);
    $monto = (float)($_POST['monto'] ?? 0);
    $metodo = trim($_POST['metodo_pago'] ?? '');
    $referencia = trim($_POST['referencia'] ?? '');

    if ($empresa_id <= 0 || $monto <= 0) {
        $error = "Datos inválidos";
    } else {

        $mysqli->begin_transaction();

        try {
            // obtener empresa
            $q = $mysqli->query("SELECT fecha_vencimiento FROM empresas WHERE id = {$empresa_id} FOR UPDATE");
            $empresa = $q->fetch_assoc();

            $hoy = date('Y-m-d');

            $base = $empresa['fecha_vencimiento'];
            if (!$base || $base < $hoy) {
                $base = $hoy;
            }

            $nuevo_vencimiento = date('Y-m-d', strtotime($base . ' +1 month'));

            // guardar pago
            $stmt = $mysqli->prepare("
                INSERT INTO pagos_empresas
                (empresa_id, monto, fecha_pago, periodo_desde, periodo_hasta, metodo_pago, referencia)
                VALUES (?, ?, NOW(), ?, ?, ?, ?)
            ");

            $stmt->bind_param(
                "idssss",
                $empresa_id,
                $monto,
                $base,
                $nuevo_vencimiento,
                $metodo,
                $referencia
            );

            $stmt->execute();

            // actualizar empresa
            $stmt2 = $mysqli->prepare("
                UPDATE empresas
                SET fecha_vencimiento = ?,
                    estado_pago = 'al_dia',
                    bloqueada = 0,
                    motivo_bloqueo = NULL
                WHERE id = ?
            ");

            $stmt2->bind_param("si", $nuevo_vencimiento, $empresa_id);
            $stmt2->execute();

            $mysqli->commit();

            $mensaje = "Pago registrado y servicio renovado";

        } catch (Throwable $e) {
            $mysqli->rollback();
            $error = "Error al registrar pago";
        }
    }
}

/* =========================
   HISTORIAL DE PAGOS
   ========================= */
$pagos = [];

if (isset($mysqli) && $mysqli) {
    $sql = "
        SELECT p.*, e.nombre empresa_nombre
        FROM pagos_empresas p
        JOIN empresas e ON e.id = p.empresa_id
        ORDER BY p.fecha_pago DESC
        LIMIT 50
    ";

    $res = $mysqli->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $pagos[] = $row;
        }
    }
}
?>

<div class="d-flex align-items-center justify-content-between">
    <h1 class="h3 m-0">Facturación</h1>
</div>

<?php if ($mensaje): ?>
    <div class="alert alert-success mt-3"><?php echo html($mensaje); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger mt-3"><?php echo html($error); ?></div>
<?php endif; ?>

<div class="card mt-3">
    <div class="card-body">
        <div class="fw-semibold mb-3">Registrar pago</div>

        <form method="post">
            <div class="row g-3">

                <div class="col-md-6">
                    <label class="form-label">Empresa</label>
                    <select name="empresa_id" class="form-select" required>
                        <option value="">Seleccione</option>
                        <?php foreach ($empresas as $e): ?>
                            <option value="<?php echo (int)$e['id']; ?>">
                                <?php echo html($e['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Monto</label>
                    <input name="monto" type="number" step="0.01" class="form-control" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Método de pago</label>
                    <select name="metodo_pago" class="form-select">
                        <option value="transferencia">Transferencia</option>
                        <option value="efectivo">Efectivo</option>
                        <option value="tarjeta">Tarjeta</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Referencia</label>
                    <input name="referencia" class="form-control">
                </div>

            </div>

            <div class="mt-3">
                <button class="btn btn-primary">Guardar pago</button>
            </div>

            <div class="alert alert-info mt-3 mb-0">
                El sistema renueva automáticamente 1 mes y desbloquea la empresa.
            </div>
        </form>
    </div>
</div>

<div class="card mt-3">
    <div class="card-body">
        <div class="fw-semibold mb-3">Historial de pagos</div>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Empresa</th>
                        <th>Monto</th>
                        <th>Fecha</th>
                        <th>Periodo</th>
                        <th>Método</th>
                        <th>Referencia</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pagos)): ?>
                        <tr>
                            <td colspan="6" class="text-muted">Sin pagos registrados</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($pagos as $p): ?>
                            <tr>
                                <td class="fw-semibold"><?php echo html($p['empresa_nombre']); ?></td>
                                <td><?php echo number_format($p['monto'], 2); ?></td>
                                <td><?php echo html($p['fecha_pago']); ?></td>
                                <td><?php echo html($p['periodo_desde']); ?> - <?php echo html($p['periodo_hasta']); ?></td>
                                <td><?php echo html($p['metodo_pago']); ?></td>
                                <td><?php echo html($p['referencia']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
$content = (string)ob_get_clean();
$currentRoute = 'pagos';
require __DIR__ . '/layout.php';
