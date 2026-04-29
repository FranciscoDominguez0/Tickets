<?php
include 'config.php';
$res = $mysqli->query("SHOW COLUMNS FROM users");
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . "\n";
}
echo "--- TABLES ---\n";
$res = $mysqli->query("SHOW TABLES");
while($row = $res->fetch_array()) {
    echo $row[0] . "\n";
}
