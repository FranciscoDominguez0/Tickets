<?php
/**
 * Controlador de Lista de Cotizaciones
 */
$statusFilter = $_GET['status'] ?? '';
$searchQuery = $_GET['q'] ?? '';

$quotes = getCotizaciones($mysqli, $eid, $statusFilter, $searchQuery);

// Procesar acciones masivas (borrar) si es necesario en el futuro

require __DIR__ . '/cotizaciones-list-view.inc.php';
