<?php
$keyFile = __DIR__ . '/../keys/keylist.json';
$id = isset($_GET['id']) ? trim($_GET['id']) : null;

if (!$id || !preg_match('/^[a-zA-Z0-9_-]+$/', $id)) {
    http_response_code(400);
    echo "Invalid or missing ID.";
    exit;
}

$keyData = json_decode(file_get_contents($keyFile), true);

if (!isset($keyData[$id])) {
    http_response_code(404);
    echo "Key not found for ID: $id";
    exit;
}

header('Content-Type: text/plain');
echo $keyData[$id];
