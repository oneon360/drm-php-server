<?php
$id = $_GET['id'] ?? null;

if (!$id) {
    http_response_code(400);
    echo "Missing ID";
    exit;
}

$keyFile = '/var/www/keys/keylist.json';

if (!file_exists($keyFile)) {
    http_response_code(500);
    echo "Key file not found";
    exit;
}

$keys = json_decode(file_get_contents($keyFile), true);

if (!isset($keys[$id])) {
    http_response_code(404);
    echo "Key not found";
    exit;
}

// Output: key_id:key_value
header('Content-Type: text/plain');
echo $keys[$id];
