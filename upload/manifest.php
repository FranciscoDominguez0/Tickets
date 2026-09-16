<?php
header('Content-Type: application/manifest+json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: public, max-age=3600');
echo file_get_contents(__DIR__ . '/manifest.json');
