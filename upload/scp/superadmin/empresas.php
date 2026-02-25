<?php
ob_start();
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
    <h1 class="h3 m-0">Empresas</h1>
</div>

<div class="card mt-3">
    <div class="card-body">
        <div class="fw-semibold mb-2">Lista de empresas</div>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Estado</th>
                        <th>Fecha vencimiento</th>
                        <th>Días restantes</th>
                        <th>Estado de pago</th>
                        <th>Bloqueada</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="text-muted" colspan="7">Próximamente: listado real desde la tabla empresas.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-body">
        <div class="fw-semibold mb-2">Detalle de empresa</div>
        <div class="text-muted">Selecciona una empresa para ver el detalle.</div>

        <div class="row g-3 mt-1">
            <div class="col-12 col-lg-6">
                <div class="card">
                    <div class="card-body">
                        <div class="fw-semibold mb-2">Datos generales</div>
                        <div class="row g-2">
                            <div class="col-6 text-muted">Nombre</div>
                            <div class="col-6">-</div>
                            <div class="col-6 text-muted">Fecha inicio servicio</div>
                            <div class="col-6">-</div>
                            <div class="col-6 text-muted">Precio mensual</div>
                            <div class="col-6">-</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-6">
                <div class="card">
                    <div class="card-body">
                        <div class="fw-semibold mb-2">Estado de servicio</div>
                        <div class="row g-2">
                            <div class="col-6 text-muted">Fecha vencimiento</div>
                            <div class="col-6">-</div>
                            <div class="col-6 text-muted">Días restantes</div>
                            <div class="col-6">-</div>
                            <div class="col-6 text-muted">Estado de pago</div>
                            <div class="col-6">-</div>
                            <div class="col-6 text-muted">Motivo de bloqueo</div>
                            <div class="col-6">-</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2 mt-3">
            <button class="btn btn-primary btn-sm" type="button" disabled>Registrar pago</button>
            <button class="btn btn-outline-primary btn-sm" type="button" disabled>Extender servicio manualmente</button>
            <button class="btn btn-outline-warning btn-sm" type="button" disabled>Suspender empresa</button>
            <button class="btn btn-outline-secondary btn-sm" type="button" disabled>Editar precio mensual</button>
            <button class="btn btn-outline-danger btn-sm" type="button" disabled>Bloquear</button>
            <button class="btn btn-outline-success btn-sm" type="button" disabled>Desbloquear</button>
        </div>
    </div>
</div>

<?php
$content = (string)ob_get_clean();
$currentRoute = 'empresas';
require __DIR__ . '/layout.php';
