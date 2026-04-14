<?php
/**
 * Estadísticas (vista independiente)
 * Reutiliza el módulo de estadísticas y el layout general.
 */

require_once '../../config.php';
require_once '../../includes/helpers.php';
require_once '../../includes/Auth.php';

if (!isset($_SESSION['staff_id'])) {
    header('Location: login.php');
    exit;
}

requireLogin('agente');
$staff = getCurrentUser();
$currentRoute = 'statistics';

ob_start();
require __DIR__ . '/modules/statistics.php';
$content = ob_get_clean();

require __DIR__ . '/layout/layout.php';

