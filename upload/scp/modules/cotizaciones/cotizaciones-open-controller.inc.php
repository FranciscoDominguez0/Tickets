<?php
/**
 * Controlador para crear una nueva cotización
 */
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::validateCSRF($_POST['csrf_token'] ?? '');
    
    $title = trim($_POST['title'] ?? '');
    $sucursal = trim($_POST['sucursal'] ?? '');
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
        $sql = "INSERT INTO quotes (empresa_id, org_id, staff_id, title, sucursal, description, amount, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";
        $stmt = $mysqli->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('iiisssd', $eid, $org_id, $staff_id, $title, $sucursal, $description, $amount);
            if ($stmt->execute()) {
                $newId = $stmt->insert_id;
                
                // Insertar la descripción como el primer mensaje del hilo si no está vacía
                if (!empty($description)) {
                    $insMsg = $mysqli->prepare("INSERT INTO quote_messages (quote_id, staff_id, message, created_at) VALUES (?, ?, ?, NOW())");
                    if ($insMsg) {
                        $insMsg->bind_param('iis', $newId, $staff_id, $description);
                        $insMsg->execute();
                    }
                }
                
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
$oStmt = $mysqli->prepare("
    SELECT o.id, o.name 
    FROM organizations o 
    WHERE o.empresa_id = ? 
      AND EXISTS (
          SELECT 1 
          FROM user_organizations uo 
          JOIN users u ON u.id = uo.user_id AND u.empresa_id = uo.empresa_id 
          WHERE uo.organization_id = o.id 
            AND uo.empresa_id = o.empresa_id 
            AND u.org_tickets_view = 1 
            AND u.status = 'active'
      )
    ORDER BY o.name ASC
");
if ($oStmt) {
    $oStmt->bind_param('i', $eid);
    $oStmt->execute();
    $oRes = $oStmt->get_result();
    while ($o = $oRes->fetch_assoc()) {
        $orgs[] = $o;
    }
}

require __DIR__ . '/cotizaciones-open-view.inc.php';
