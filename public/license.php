<?php
// Blokir akses langsung yang tidak mengandung secret header
if (!isset($_SERVER['HTTP_X_WORKER_SECRET']) || $_SERVER['HTTP_X_WORKER_SECRET'] !== 'abc123') {
    http_response_code(403);
    header('Content-Type: application/json');
    exit(json_encode(["error" => "Unauthorized"]));
}

// Gunakan content-type biner agar browser tidak menampilkan JSON
header('Content-Type: application/octet-stream');
header('Cache-Control: no-store');

// Konversi HEX ke Base64 URL-safe
function hexToBase64UrlSafe($hex) {
    $base64 = base64_encode(hex2bin($hex));
    return rtrim(strtr($base64, '+/', '-_'), '=');
}

// Ambil parameter ID
$id = $_GET['id'] ?? null;
if (!$id || !preg_match('/^var\d+$/', $id)) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid or missing ID"]);
    exit;
}

// Path ke key file
$keyFile = '/var/www/keys/keylist.json'; // Ganti sesuai kebutuhan lokasi sebenarnya

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

// Format sesuai ClearKey JSON
echo json_encode([
    "keys" => [[
        "kty" => "oct",
        "kid" => hexToBase64UrlSafe($key_id_hex),
        "k"   => hexToBase64UrlSafe($key_hex)
    ]],
    "type" => "temporary"
]);
exit;
?>
