<?php
// public/index.php

$id = $_GET['id'] ?? null;

if (!$id) {
    http_response_code(400);
    echo "Missing ID";
    exit;
}

$keys = json_decode(file_get_contents(__DIR__ . '/../keys/keylist.json'), true);

if (!isset($keys[$id])) {
    http_response_code(404);
    echo "Key not found";
    exit;
}

// Output the raw license key string
header('Content-Type: text/plain');
echo $keys[$id];
