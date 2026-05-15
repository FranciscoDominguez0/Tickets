<?php
$files = [
    'c:/Server/Apache24/htdocs/sistema-tickets/upload/open.php',
    'c:/Server/Apache24/htdocs/sistema-tickets/upload/profile.php',
    'c:/Server/Apache24/htdocs/sistema-tickets/upload/view-ticket.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        // Read file (which might be UTF-16LE from powershell)
        $content = file_get_contents($file);
        
        // If it starts with UTF-16LE BOM or has null bytes, convert to UTF-8
        if (strpos($content, "\0") !== false) {
            $content = mb_convert_encoding($content, 'UTF-8', 'UTF-16LE');
            // Remove BOM if present
            $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
            file_put_contents($file, $content);
            echo "Converted $file to UTF-8\n";
        } else {
            echo "$file is already UTF-8/ASCII\n";
        }
    }
}
