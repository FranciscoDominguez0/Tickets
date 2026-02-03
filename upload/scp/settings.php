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
$currentRoute = 'settings';

$content = '
<div class="page-header">
    <h1>Configuración</h1>
    <p>Configuración general del sistema de tickets</p>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5>Configuración General</h5>
            </div>
            <div class="card-body">
                <button class="btn btn-outline-secondary w-100 mb-2" disabled>Configuración del Sistema</button>
                <button class="btn btn-outline-secondary w-100 mb-2" disabled>Gestión de Usuarios</button>
                <button class="btn btn-outline-secondary w-100 mb-2" disabled>Configuración de Correo</button>
                <button class="btn btn-outline-secondary w-100" disabled>Logs del Sistema</button>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5>Departamentos y Agentes</h5>
            </div>
            <div class="card-body">
                <button class="btn btn-outline-secondary w-100 mb-2" disabled>Gestión de Departamentos</button>
                <button class="btn btn-outline-secondary w-100 mb-2" disabled>Roles y Permisos</button>
                <button class="btn btn-outline-secondary w-100 mb-2" disabled>Reportes</button>
                <button class="btn btn-outline-secondary w-100" disabled>Estadísticas</button>
            </div>
        </div>
    </div>
</div>
';

require_once 'layout_admin.php';
?>