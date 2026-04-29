<?php
include 'config.php';
$sql = "ALTER TABLE ticket_reports MODIFY COLUMN billing_status VARCHAR(50) DEFAULT 'pending'";
if ($mysqli->query($sql)) {
    echo "Column modified successfully\n";
} else {
    echo "Error: " . $mysqli->error . "\n";
}
