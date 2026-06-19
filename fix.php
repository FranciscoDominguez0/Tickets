<?php
$files = [
    'upload/scp/css/dark.css',
    'upload/scp/css/scp.css',
    'upload/scp/css/profile.css',
    'upload/scp/css/tickets.css'
];

$gray_colors = ['18181b', '111113', '1a1a1a', '1e1e1e', '111', '141414', '0a0a0a', '161616', '1c1c1c', '171717', '252525', '2a2a2a', '27272a'];

foreach ($files as $file) {
    if (!file_exists($file)) continue;
    
    $content = file_get_contents($file);
    
    // Split into lines
    $lines = explode("\n", $content);
    $current_selector = '';
    
    foreach ($lines as $i => $line) {
        // Track basic selector context if not indented
        if (preg_match('/^([a-zA-Z0-9_.#,:\-\[\]=\"\s]+)\s*\{/', $line, $m)) {
            $current_selector = $m[1];
        }
        
        // Skip specific selectors that broke before
        if (strpos($current_selector, 'ticket-view-overview') !== false ||
            strpos($current_selector, 'ticket-view-entry') !== false ||
            strpos($current_selector, 'umc-avatar') !== false) {
            continue;
        }
        
        // Check for backgrounds
        if (preg_match('/background(-color)?\s*:/i', $line)) {
            // Replace gradients
            $line = preg_replace('/linear-gradient\([^)]+\)/i', '#000000', $line);
            
            // Replace specific grays
            foreach ($gray_colors as $color) {
                $line = preg_replace('/#' . $color . '\b/i', '#000000', $line);
            }
            
            $lines[$i] = $line;
        }
    }
    
    file_put_contents($file, implode("\n", $lines));
}

echo "Done.";
