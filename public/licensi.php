<?php
function hexToBase64($hex) {
    return base64_encode(hex2bin($hex));
}

$requestUri = $_SERVER['REQUEST_URI'];
$parts = explode('/', trim($requestUri, '/'));
$id = $parts[count($parts) - 1];

if (!$id) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(["error" => "Missing ID"]);
    exit;
}

$keyFile = dirname(__DIR__) . '/keys/keylist.json';

if (!file_exists($keyFile)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(["error" => "Key file not found"]);
    exit;
}

$keys = json_decode(file_get_contents($keyFile), true);

if (!isset($keys[$id])) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(["error" => "Key not found"]);
    exit;
}

$raw = explode(':', $keys[$id]);

if (count($raw) !== 2) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(["error" => "Invalid key format"]);
    exit;
}

$key_id_hex = $raw[0];
$key_hex = $raw[1];

$kid_b64 = hexToBase64($key_id_hex);
$k_b64   = hexToBase64($key_hex);

header('Content-Type: application/json');
echo json_encode([
    "keys" => [
        [
            "kty" => "oct",
            "kid" => $kid_b64,
            "k"   => $k_b64
        ]
    ],
    "type" => "temporary"
]);
exit;
