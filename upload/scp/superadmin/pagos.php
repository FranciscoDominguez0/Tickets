<?php
ob_start();
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
    <h1 class="h3 m-0">Facturación</h1>
</div>

<div class="card mt-3">
    <div class="card-body">
        <div class="fw-semibold mb-2">Registrar pago</div>
        <form>
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label class="form-label">Empresa</label>
                    <select class="form-select" disabled>
                        <option>Próximamente</option>
                    </select>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label">Monto</label>
                    <input type="number" step="0.01" class="form-control" placeholder="0.00" disabled>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label">Periodo pagado</label>
                    <input type="text" class="form-control" placeholder="Ej: 2026-02" disabled>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label">Método de pago</label>
                    <select class="form-select" disabled>
                        <option>Transferencia</option>
                    </select>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label">Referencia</label>
                    <input type="text" class="form-control" placeholder="# referencia" disabled>
                </div>
            </div>

            <div class="d-flex gap-2 mt-3">
                <button class="btn btn-primary" type="button" disabled>Guardar pago</button>
                <button class="btn btn-outline-secondary" type="button" disabled>Cancelar</button>
            </div>

            <div class="alert alert-info mt-3 mb-0">
                Acción automática (cuando se implemente): guarda pago, renueva vencimiento, desbloquea empresa.
            </div>
        </form>
    </div>
</div>

<div class="card mt-3">
    <div class="card-body">
        <div class="fw-semibold mb-2">Historial de pagos</div>

        <div class="row g-2 mb-3">
            <div class="col-12 col-md-4">
                <label class="form-label">Filtrar por empresa</label>
                <select class="form-select" disabled>
                    <option>Todas</option>
                </select>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label">Desde</label>
                <input type="date" class="form-control" disabled>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label">Hasta</label>
                <input type="date" class="form-control" disabled>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Empresa</th>
                        <th>Monto</th>
                        <th>Fecha</th>
                        <th>Periodo</th>
                        <th>Método</th>
                        <th>Registrado por</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="text-muted" colspan="6">Próximamente: historial real de pagos.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
$content = (string)ob_get_clean();
$currentRoute = 'pagos';
require __DIR__ . '/layout.php';
