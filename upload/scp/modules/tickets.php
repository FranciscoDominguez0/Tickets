<?php
require __DIR__ . '/tickets/tickets-bootstrap.inc.php';
require __DIR__ . '/tickets/tickets-ajax.inc.php';

if (isset($_GET['a']) && $_GET['a'] === 'open' && isset($_SESSION['staff_id'])) {
    require __DIR__ . '/tickets/tickets-open.inc.php';
} elseif (isset($_GET['id']) && is_numeric($_GET['id'])) {
    require __DIR__ . '/tickets/tickets-view-controller.inc.php';
} else {
    require __DIR__ . '/tickets/tickets-list-controller.inc.php';
    require __DIR__ . '/tickets/tickets-list-view.inc.php';
}
