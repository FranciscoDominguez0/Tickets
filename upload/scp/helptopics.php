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
$currentRoute = 'helptopics';

$content = '
<div class="page-header">
    <h1>Administrar</h1>
    <p>Gestión de temas de ayuda y categorías</p>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5>Temas de Ayuda</h5>
            </div>
            <div class="card-body">
                <p>Aquí se administrarán los temas de ayuda.</p>
            </div>
        </div>
    </div>
</div>
';

require_once 'layout_admin.php';
?>