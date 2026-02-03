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
$currentRoute = 'emails';

$content = '
<div class="page-header">
    <h1>Correos Electrónicos</h1>
    <p>Configuración de plantillas de correo y notificaciones</p>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5>Plantillas de Correo</h5>
            </div>
            <div class="card-body">
                <p>Aquí se configurarán las plantillas de correo electrónico.</p>
            </div>
        </div>
    </div>
</div>
';

require_once 'layout_admin.php';
?>