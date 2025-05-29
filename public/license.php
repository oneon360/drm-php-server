<?php
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

/**
 * Konversi HEX ke base64 URL-safe (tanpa padding)
 */
function hexToBase64UrlSafe($hex) {
    $base64 = base64_encode(hex2bin($hex));
    return rtrim(strtr($base64, '+/', '-_'), '=');
}

/**
 * Deteksi User-Agent mencurigakan dan bot termasuk spoofed browser
 */
function isSuspiciousClient() {
    $ua = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $encoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
    $conn = $_SERVER['HTTP_CONNECTION'] ?? '';
    $fetch = $_SERVER['HTTP_SEC_FETCH_SITE'] ?? '';
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'];

    // Daftar User-Agent umum yang mencurigakan
    $blockedUAs = [
        'curl', 'wget', 'python', 'php', 'go-http-client', 'postman', 'httpie',
        'httpclient', 'axios', 'node', 'java', 'libwww', 'perl', 'ruby'
    ];
    foreach ($blockedUAs as $bad) {
        if (strpos($ua, $bad) !== false) return true;
    }

    // Deteksi penyamaran: tampak seperti browser tapi tidak sepenuhnya
    if (
        stripos($ua, 'mozilla') !== false && // nyamar jadi browser
        (
            stripos($conn, 'keep-alive') !== false ||
            $fetch === 'same-origin' ||
            stripos($referer, 'example.com') !== false
        ) &&
        stripos($encoding, 'gzip') === false // requests default: tidak pakai gzip
    ) {
        return true;
    }

    return false;
}

// Blokir akses mencurigakan
if (isSuspiciousClient()) {
    http_response_code(403);
    echo json_encode(["error" => "Forbidden - suspected bot"]);
    exit;
}

// Validasi ID
$id = $_GET['id'] ?? null;
if (!$id) {
    http_response_code(400);
    echo json_encode(["error" => "Missing ID"]);
    exit;
}

// Lokasi file key
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

// Konversi ke format ClearKey
$key_id_hex = $raw[0];
$key_hex    = $raw[1];

$kid_b64 = hexToBase64UrlSafe($key_id_hex);
$k_b64   = hexToBase64UrlSafe($key_hex);

// Output JSON sesuai ClearKey
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
