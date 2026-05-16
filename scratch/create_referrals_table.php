<?php
$mysqli = new mysqli('localhost', 'root', '12345678', 'tickets_db');
if ($mysqli->connect_error) die('Connect Error');

$sql = "CREATE TABLE IF NOT EXISTS ticket_referrals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    staff_id INT DEFAULT NULL,
    dept_id INT DEFAULT NULL,
    created DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ticket (ticket_id),
    KEY idx_staff (staff_id),
    KEY idx_dept (dept_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($mysqli->query($sql)) {
    echo "Table ticket_referrals created or already exists.\n";
} else {
    echo "Error: " . $mysqli->error . "\n";
}
?>
