<?php
require_once 'includes/config.php';
session_start();
$eid = 1;
$tid = 453;
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

$stmtR = $mysqli->prepare("SELECT DISTINCT s.id FROM staff s LEFT JOIN staff_departments sd ON sd.staff_id = s.id LEFT JOIN notification_recipients nr ON nr.staff_id = s.id AND nr.empresa_id = ? INNER JOIN tickets t ON t.id = ? WHERE s.empresa_id = ? AND s.is_active = 1 AND (nr.id IS NOT NULL OR s.dept_id = t.dept_id OR sd.dept_id = t.dept_id)");
if (!$stmtR) {
    die("Prepare failed: " . $mysqli->error);
}
$stmtR->bind_param('iii', $eid, $tid, $eid);
if (!$stmtR->execute()) {
    die("Execute failed: " . $stmtR->error);
}
$resR = $stmtR->get_result();
while ($r = $resR->fetch_assoc()) {
    echo "Found staff_id: " . $r['id'] . "\n";
}
echo "Done.\n";
