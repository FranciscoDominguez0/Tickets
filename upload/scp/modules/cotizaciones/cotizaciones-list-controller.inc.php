<?php
/**
 * Controlador de Lista de Cotizaciones
 */
$statusFilter = $_GET['status'] ?? '';
$searchQuery = $_GET['q'] ?? '';

$page = max(1, (int)($_GET['p'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$totalQuotes = countCotizaciones($mysqli, $eid, $statusFilter, $searchQuery);
$totalPages = ceil($totalQuotes / $limit);

$quotes = getCotizaciones($mysqli, $eid, $statusFilter, $searchQuery, $limit, $offset);

require __DIR__ . '/cotizaciones-list-view.inc.php';
