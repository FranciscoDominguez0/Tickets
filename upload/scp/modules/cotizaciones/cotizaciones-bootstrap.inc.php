<?php
/**
 * Bootstrap para el módulo de Cotizaciones.
 * Carga funciones auxiliares si son necesarias.
 */
if (!isset($mysqli) || !$mysqli) {
    die('Database connection not available.');
}

$eid = empresaId();



function getCotizaciones($mysqli, $eid, $status = '', $search = '') {
    $where = ["q.empresa_id = ?"];
    $params = [$eid];
    $types = "i";

    if ($status !== '') {
        $where[] = "q.status = ?";
        $params[] = $status;
        $types .= "s";
    }

    if ($search !== '') {
        $where[] = "(q.title LIKE ? OR o.name LIKE ? OR s.firstname LIKE ?)";
        $searchParam = '%' . $search . '%';
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $types .= "sss";
    }

    $whereSql = implode(' AND ', $where);

    $sql = "SELECT q.*, 
            o.name as org_name, 
            CONCAT(s.firstname, ' ', s.lastname) as staff_name 
            FROM quotes q 
            LEFT JOIN organizations o ON q.org_id = o.id 
            LEFT JOIN staff s ON q.staff_id = s.id 
            WHERE $whereSql 
            ORDER BY q.created_at DESC";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) return [];

    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $quotes = [];
    while ($row = $res->fetch_assoc()) {
        $quotes[] = $row;
    }
    return $quotes;
}
