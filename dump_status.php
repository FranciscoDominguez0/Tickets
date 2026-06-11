 <?php
require 'config.php';
$res1 = $mysqli->query("SELECT * FROM ticket_approvals ORDER BY id DESC LIMIT 10");
echo "ticket_approvals:\n";
while($row = $res1->fetch_assoc()) print_r($row);

$res2 = $mysqli->query("SELECT * FROM quotes ORDER BY id DESC LIMIT 10");
echo "\nquotes:\n";
while($row = $res2->fetch_assoc()) print_r($row);
