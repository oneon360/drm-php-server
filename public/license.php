<?php
// Blokir akses langsung yang tidak mengandung secret header
if (!isset($_SERVER['HTTP_X_WORKER_SECRET']) || $_SERVER['HTTP_X_WORKER_SECRET'] !== 'abc123') {
    http_response_code(403);
    exit(json_encode(["error" => "Unauthorized"]));
}

header('Content-Type: application/json');

/**
 * Convert HEX to Base64 URL-safe (tanpa padding)
 */
function hexToBase64UrlSafe($hex) {
    $base64 = base64_encode(hex2bin($hex));
    return rtrim(strtr($base64, '+/', '-_'), '=');
}

// Ambil parameter ID dari URL
$id = $_GET['id'] ?? null;

if (!$id || !preg_match('/^var\d+$/', $id)) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid or missing ID"]);
    exit;
}

// Lokasi file keylist.json
$keyFile = __DIR__ . ''/var/www/keys/keylist.json'; // atau ganti ke '/var/www/keys/keylist.json' jika di Docker

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
$key_hex    = $raw[1];

// Convert HEX ke base64url
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
exit;
?>
