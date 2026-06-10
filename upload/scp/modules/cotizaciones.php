<?php
/**
 * Dispatcher para el módulo de Cotizaciones.
 */
require_once __DIR__ . '/cotizaciones/cotizaciones-bootstrap.inc.php';

$action = $_GET['a'] ?? '';

if ($action === 'open') {
    require __DIR__ . '/cotizaciones/cotizaciones-open-controller.inc.php';
} elseif (isset($_GET['id'])) {
    require __DIR__ . '/cotizaciones/cotizaciones-view-controller.inc.php';
} else {
    require __DIR__ . '/cotizaciones/cotizaciones-list-controller.inc.php';
}
