<?php
$files = [
    'c:/Server/Apache24/htdocs/sistema-tickets/upload/open.php',
    'c:/Server/Apache24/htdocs/sistema-tickets/upload/profile.php',
    'c:/Server/Apache24/htdocs/sistema-tickets/upload/view-ticket.php'
];

$search = [
    '#2563eb',
    '37,99,235',
    '#1d4ed8',
    'rgba(37, 99, 235',
    '#0ea5e9',
    '99, 102, 241',
    '#3b82f6',
    '#eff6ff',
    '#e0e7ff',
    '#2563EB',
    '#1D4ED8'
];

$replace = [
    '#ef4444',
    '239,68,68',
    '#dc2626',
    'rgba(239, 68, 68',
    '#f87171',
    '239, 68, 68',
    '#ef4444',
    '#fef2f2',
    '#fef2f2',
    '#ef4444',
    '#dc2626'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        $newContent = str_replace($search, $replace, $content);
        file_put_contents($file, $newContent);
        echo "Replaced in $file\n";
    } else {
        echo "File not found: $file\n";
    }
}
