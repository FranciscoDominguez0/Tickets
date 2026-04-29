<?php
include 'config.php';
$res = $mysqli->query("SHOW COLUMNS FROM organizations");
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . "\n";
}
echo "--- USER ORG COLUMNS ---\n";
$res = $mysqli->query("SHOW COLUMNS FROM users LIKE '%org%'");
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . "\n";
}
