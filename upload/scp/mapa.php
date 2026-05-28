<?php
/**
 * Funcionalidad: Mapa de Agentes (Standalone)
 * Este archivo carga el mapa dentro del layout del sistema.
 */

require_once '../../config.php';
require_once '../../includes/helpers.php';
require_once '../../includes/Auth.php';

requireLogin('agente');
$staff = getCurrentUser();
$currentRoute = 'mapa';

$roleName = getCurrentStaffRoleName();
$canViewMap = roleHasPermission('agent.map');

if (!$canViewMap) {
    $_SESSION['flash_error'] = 'No tienes permiso para ver el mapa de agentes.';
    header('Location: index.php');
    exit;
}

// El contenido del mapa
ob_start();
require __DIR__ . '/modules/mapa-view.inc.php';
$content = ob_get_clean();

// Renderizar con el layout estándar
require __DIR__ . '/layout/layout.php';
