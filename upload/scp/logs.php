<?php
require_once '../../config.php';
require_once '../../includes/helpers.php';
require_once '../../includes/Auth.php';

if (!isset($_SESSION['staff_id'])) {
    header('Location: login.php');
    exit;
}

requireLogin('agente');
$staff = getCurrentUser();
$currentRoute = 'logs';

$content = '
<div class="page-header">
    <h1>Panel de Control</h1>
    <p>Logs y estadísticas del sistema</p>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5>Logs del Sistema</h5>
            </div>
            <div class="card-body">
                <p>Aquí se mostrarán los logs del sistema.</p>
            </div>
        </div>
    </div>
</div>
';

require_once 'layout_admin.php';
?>