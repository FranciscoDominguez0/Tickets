<?php
require 'config.php';
$tables = ['users', 'organizations', 'organization_managers'];
foreach($tables as $t) {
    echo "TABLE: $t\n";
    $res = $mysqli->query("SHOW COLUMNS FROM $t");
    if($res) {
        while($r = $res->fetch_assoc()) {
            echo "  ".$r['Field']." - ".$r['Type']."\n";
        }
    } else {
        echo "  Table not found.\n";
    }
}
