<?php
ob_start();
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
    <h1 class="h3 m-0">Notificaciones</h1>
</div>

<div class="card mt-3">
    <div class="card-body">
        <div class="fw-semibold mb-2">Avisos automáticos</div>
        <div class="row g-3">
            <div class="col-12 col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <div class="text-muted">Pago próximo a vencer</div>
                        <div class="fs-3 fw-semibold">-</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <div class="text-muted">Pago vencido</div>
                        <div class="fs-3 fw-semibold">-</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <div class="text-muted">Empresa bloqueada</div>
                        <div class="fs-3 fw-semibold">-</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-body">
        <div class="fw-semibold mb-2">Enviar aviso manual</div>
        <form>
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label class="form-label">Empresa</label>
                    <select class="form-select" disabled>
                        <option>Próximamente</option>
                    </select>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label">Tipo de aviso</label>
                    <select class="form-select" disabled>
                        <option>Recordatorio de pago</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Mensaje</label>
                    <textarea class="form-control" rows="4" disabled></textarea>
                </div>
            </div>

            <div class="d-flex gap-2 mt-3">
                <button class="btn btn-primary" type="button" disabled>Enviar</button>
                <button class="btn btn-outline-secondary" type="button" disabled>Cancelar</button>
            </div>
        </form>
    </div>
</div>

<?php
$content = (string)ob_get_clean();
$currentRoute = 'notificaciones';
require __DIR__ . '/layout.php';
