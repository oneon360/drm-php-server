<?php
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

/**
 * HEX to base64 URL-safe (no padding)
 */
function hexToBase64UrlSafe($hex) {
    $base64 = base64_encode(hex2bin($hex));
    return rtrim(strtr($base64, '+/', '-_'), '=');
}

/**
 * Deteksi hanya Kodi/IPTV yang diizinkan
 */
function isAllowedClient() {
    $ua = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');

    // Ijinkan hanya pemutar IPTV seperti Kodi
    $allowed = [
        'kodi',               // Kodi
        'inputstream.adaptive', // Modul lisensi Kodi
        'vlc',                // opsional: VLC (jika kamu mau)
        'iptv',               // generic
    ];

    foreach ($allowed as $ok) {
        if (strpos($ua, $ok) !== false) return true;
    }

    return false;
}

// Tolak semua selain pemutar
if (!isAllowedClient()) {
    http_response_code(403);
    echo json_encode(["error" => "Access denied"]);
    exit;
}

// Ambil ID dari parameter
$id = $_GET['id'] ?? null;
if (!$id) {
    http_response_code(400);
    echo json_encode(["error" => "Missing ID"]);
    exit;
}

// Lokasi key
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

$kid_b64 = hexToBase64UrlSafe($key_id_hex);
$k_b64 = hexToBase64UrlSafe($key_hex);

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
