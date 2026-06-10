<?php
/**
 * Controlador para crear una nueva cotización
 */
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::validateCSRF($_POST['csrf_token'] ?? '');
    
    $title = trim($_POST['title'] ?? '');
    $org_id = (int)($_POST['org_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $description = trim($_POST['description'] ?? '');

    if (empty($title)) {
        $errors[] = 'El título es obligatorio.';
    }
    if ($org_id <= 0) {
        $errors[] = 'Debe seleccionar una organización.';
    }

    if (empty($errors)) {
        $staff_id = $_SESSION['staff_id'];
        $sql = "INSERT INTO quotes (empresa_id, org_id, staff_id, title, description, amount, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())";
        $stmt = $mysqli->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('iiissd', $eid, $org_id, $staff_id, $title, $description, $amount);
            if ($stmt->execute()) {
                $newId = $stmt->insert_id;
                $_SESSION['flash_msg'] = 'Cotización creada exitosamente como borrador.';
                header("Location: cotizaciones.php?id=$newId");
                exit;
            } else {
                $errors[] = 'Error al guardar la cotización en la base de datos.';
            }
        } else {
            $errors[] = 'Error en la preparación de la consulta.';
        }
    }
}

// Obtener lista de organizaciones para el select
$orgs = [];
$oStmt = $mysqli->prepare("SELECT id, name FROM organizations WHERE empresa_id = ? ORDER BY name ASC");
if ($oStmt) {
    $oStmt->bind_param('i', $eid);
    $oStmt->execute();
    $oRes = $oStmt->get_result();
    while ($o = $oRes->fetch_assoc()) {
        $orgs[] = $o;
    }
}

require __DIR__ . '/cotizaciones-open-view.inc.php';
