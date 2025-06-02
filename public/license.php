<?php
// Fungsi untuk merespons JSON dengan HTTP 200
function respond($data) {
    http_response_code(200);
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    exit(json_encode([
        ...$data,
        '_trace' => substr(md5(mt_rand()), 0, 8),
        '_ts' => time() * 1000
    ]));
}

// Blokir akses jika tidak menggunakan HTTPS
$is_https = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
    $_SERVER['SERVER_PORT'] == 443 ||
    (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
);
if (!$is_https) {
    respond(["error" => "HTTPS required"]);
}

// Validasi secret dari Cloudflare Worker
if (!isset($_SERVER['HTTP_X_WORKER_SECRET']) || $_SERVER['HTTP_X_WORKER_SECRET'] !== 'abc123') {
    respond(["error" => "Unexpected response"]);
}

// Gunakan Content-Type octet-stream agar tidak dibaca oleh browser
header('Content-Type: application/octet-stream');
header('Cache-Control: no-store');

// Validasi User-Agent dan Accept: blokir jika mengandung text/html (indikasi browser biasa)
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
$accept = $_SERVER['HTTP_ACCEPT'] ?? '';
if (stripos($accept, 'text/html') !== false) {
    respond(["error" => "Unexpected response"]);
}

// Fungsi untuk encode Base64 URL-Safe
function hexToBase64UrlSafe($hex) {
    $base64 = base64_encode(hex2bin($hex));
    return rtrim(strtr($base64, '+/', '-_'), '=');
}

// Ambil dan validasi parameter `k`
$k = $_GET['k'] ?? '';
if (!preg_match('/^[a-zA-Z0-9]{6,20}$/', $k)) {
    respond(["error" => "Unexpected response"]);
}

// Path ke file key list
$keyFile = '/var/www/keys/keylist.json'; // ← Ubah sesuai struktur server Anda
if (!file_exists($keyFile)) {
    respond(["error" => "Unexpected response"]);
}

// Baca dan parsing key list
$keys = json_decode(file_get_contents($keyFile), true);
if (!is_array($keys) || !isset($keys[$k]['key'])) {
    respond(["error" => "Unexpected response"]);
}

// Format key: "keyid:clearkey"
$raw = explode(':', $keys[$k]['key']);
if (count($raw) !== 2) {
    respond(["error" => "Unexpected response"]);
}

$key_id_hex = $raw[0];
$key_hex    = $raw[1];

// Output ClearKey JSON (license)
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
