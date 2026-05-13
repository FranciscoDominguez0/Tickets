<?php
$content = file_get_contents('c:/Server/Apache24/htdocs/sistema-tickets/upload/view-ticket.php');
$opens = substr_count($content, '<div');
$closes = substr_count($content, '</div');
echo "Opens: $opens\nCloses: $closes\n";
if ($opens !== $closes) {
    echo "MISMATCH!\n";
}
