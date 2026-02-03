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
$currentRoute = 'staff';

$content = '
<div class="page-header">
    <h1>Agentes</h1>
    <p>Gestión de agentes y permisos</p>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5>Lista de Agentes</h5>
            </div>
            <div class="card-body">
                <p>Aquí se gestionarán los agentes del sistema.</p>
            </div>
        </div>
    </div>
</div>
';

require_once 'layout_admin.php';
?>