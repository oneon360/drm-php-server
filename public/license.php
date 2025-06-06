<?php
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

// Hex ke Base64URL (untuk Widevine/PlayReady)
function hexToBase64UrlSafe($hex) {
    $base64 = base64_encode(hex2bin($hex));
    return rtrim(strtr($base64, '+/', '-_'), '=');
}

// Ambil parameter ?k=
$k = $_GET['k'] ?? '';
if (!preg_match('/^[a-zA-Z0-9]{6,20}$/', $k)) {
    respond(["error" => "Unexpected response"]);
}

// URL keylist.json dari Bitbucket atau sumber eksternal lain
$keyFile = 'https://bitbucket.org/idplay3r/drm/raw/5614666cbae3d283ad2fd5c4a951200c1c06294e/keylist.json';

// Cek apakah URL valid dan bisa dibaca
if (!preg_match('/^https?:\/\//', $keyFile)) {
    respond(["error" => "Invalid key source"]);
}

// Ambil isi keylist (dengan timeout dan context SSL yang aman)
$context = stream_context_create([
    'http' => ['timeout' => 4],
    'ssl'  => ['verify_peer' => true, 'verify_peer_name' => true]
]);

$json = @file_get_contents($keyFile, false, $context);
if (!$json) {
    respond(["error" => "Unexpected response"]);
}

// Decode dan validasi key
$keys = json_decode($json, true);
if (!is_array($keys) || !isset($keys[$k]['key'])) {
    respond(["error" => "Unexpected response"]);
}

$raw = explode(':', $keys[$k]['key']);
if (count($raw) !== 2 || strlen($raw[0]) !== 32 || strlen($raw[1]) !== 32) {
    respond(["error" => "Unexpected response"]);
}

// Final encode key ID dan key
$key_id_hex = $raw[0];
$key_hex    = $raw[1];

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
