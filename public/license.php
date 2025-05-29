<?php
header('Content-Type: application/json');

/**
 * Convert HEX to Base64 URL-safe (tanpa padding)
 * Cocok untuk DRM license format ala JavaScript
 */
function hexToBase64UrlSafe($hex) {
    $base64 = base64_encode(hex2bin($hex));
    return rtrim(strtr($base64, '+/', '-_'), '=');
}

$id = $_GET['id'] ?? null;

if (!$id) {
    http_response_code(400);
    echo json_encode(["error" => "Missing ID"]);
    exit;
}

// File keylist.json ada di luar folder publik (Docker copy ke /var/www/keys)
$keyFile = '/var/www/keys/keylist.json';

if (!file_exists($keyFile)) {
    http_response_code(500);
    echo json_encode(["error" => "Key file not found"]);
    exit;
}

$keys = json_decode(file_get_contents($keyFile), true);

if (!isset($keys[$id])) {
    http_response_code(404);
    echo json_encode(["error" => "Key not found"]);
    exit;
}

$raw = explode(':', $keys[$id]);

if (count($raw) !== 2) {
    http_response_code(500);
    echo json_encode(["error" => "Invalid key format"]);
    exit;
}

$key_id_hex = $raw[0];
$key_hex = $raw[1];

// Convert HEX to base64url
$kid_b64 = hexToBase64UrlSafe($key_id_hex);
$k_b64   = hexToBase64UrlSafe($key_hex);

// Output sesuai ClearKey spec
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
