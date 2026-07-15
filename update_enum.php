<?php
require 'config.php';
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}
// Get current ENUM
$result = $mysqli->query("SHOW COLUMNS FROM quotes LIKE 'status'");
$row = $result->fetch_assoc();
$type = $row['Type']; // e.g. enum('draft','pending','requested','answered','accepted','rejected')
echo "Old Type: " . $type . "\n";

// Append 'waiting_oc' if not exists
if (strpos($type, "'waiting_oc'") === false) {
    $newType = str_replace(")", ",'waiting_oc')", $type);
    echo "New Type: " . $newType . "\n";
    $sql = "ALTER TABLE quotes MODIFY status $newType NOT NULL DEFAULT 'draft'";
    if ($mysqli->query($sql)) {
        echo "Table updated successfully.\n";
    } else {
        echo "Error: " . $mysqli->error . "\n";
    }
} else {
    echo "waiting_oc already exists.\n";
}
