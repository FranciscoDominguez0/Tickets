<?php
/**
 * Controlador de Lista de Cotizaciones
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (isset($_POST['csrf_token']) && Auth::validateCSRF($_POST['csrf_token'])) {
        $quoteId = (int)($_POST['id'] ?? 0);
        if ($quoteId > 0) {
            $stmt = $mysqli->prepare("DELETE FROM quotes WHERE id = ? AND empresa_id = ?");
            if ($stmt) {
                $stmt->bind_param("ii", $quoteId, $eid);
                $stmt->execute();
                $_SESSION['flash_msg'] = 'Cotización eliminada correctamente.';
            }
        }
        header("Location: cotizaciones.php");
        exit;
    }
}

$statusFilter = $_GET['status'] ?? '';
$searchQuery = $_GET['q'] ?? '';

$page = max(1, (int)($_GET['p'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$totalQuotes = countCotizaciones($mysqli, $eid, $statusFilter, $searchQuery);
$totalPages = ceil($totalQuotes / $limit);

$quotes = getCotizaciones($mysqli, $eid, $statusFilter, $searchQuery, $limit, $offset);

require __DIR__ . '/cotizaciones-list-view.inc.php';
