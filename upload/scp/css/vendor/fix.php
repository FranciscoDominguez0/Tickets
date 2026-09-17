<?php
$file = 'summernote-lite.min.css';
$content = file_get_contents($file);

// Check if it is UTF-16LE and convert to UTF-8 if so
if (substr($content, 0, 2) === "\xFF\xFE") {
    $content = mb_convert_encoding(substr($content, 2), 'UTF-8', 'UTF-16LE');
}

$content = str_replace('url("font/summernote.woff2") format("woff2"),', '', $content);
file_put_contents($file, $content);
echo "Fixed";
