<?php
// Hanya lanjut jika header rahasia valid
if ($_SERVER['HTTP_X_WORKER_SECRET'] ?? '' !== 'abc123') {
    http_response_code(403);
    header('Content-Type: application/json');
    exit(json_encode(["error" => "Unauthorized"]));
}

// Gunakan content-type octet agar tidak terbaca browser
header('Content-Type: application/octet-stream');
header('Cache-Control: no-store');

// Ambil User-Agent dan Accept
$ua     = $_SERVER['HTTP_USER_AGENT'] ?? '';
$accept = $_SERVER['HTTP_ACCEPT'] ?? '';

// Deteksi UA yang dilarang (ExoPlayer dan Kodi)
$ua_lower = strtolower($ua);
if (strpos($ua_lower, 'kodi') !== false || strpos($ua_lower, 'exoplayer') !== false) {
    http_response_code(200);
    echo json_encode(["error" => "Player blocked"]);
    exit;
}

// Deteksi hanya Chrome WebView yang boleh lanjut
// Ciri-ciri umum Chrome WebView Android:
// - Mengandung "Chrome/" dan "wv" (WebView) atau "Version/"
// - Tidak mengandung "ExoPlayer", "Kodi", "Firefox", "Safari", dsb

$isChromeWebView = (
    strpos($ua, 'Chrome/') !== false &&
    (strpos($ua, 'wv') !== false || strpos($ua, 'Version/') !== false) &&
    strpos($ua_lower, 'safari') === false &&
    strpos($ua_lower, 'firefox') === false &&
    strpos($ua_lower, 'kodi') === false &&
    strpos($ua_lower, 'exoplayer') === false
);

// Blokir permanen semua selain Chrome WebView
if (!$isChromeWebView) {
    http_response_code(200);
    echo json_encode(["error" => "Only Chrome WebView allowed"]);
    exit;
}

// Konversi HEX ke Base64 URL-safe
function hexToBase64UrlSafe($hex) {
    $bin = @hex2bin($hex);
    if ($bin === false) return null;
    return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
}

// Validasi parameter ?k=xxx
$k = $_GET['k'] ?? '';
if (!preg_match('/^[a-zA-Z0-9]{6,20}$/', $k)) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid or missing parameter"]);
    exit;
}

// Path file key
$keyFile = '/var/www/keys/keylist.json';
if (!is_file($keyFile)) {
    http_response_code(500);
    echo json_encode(["error" => "Key file not found"]);
    exit;
}

// Ambil data key
$keys = json_decode(file_get_contents($keyFile), true);
if (!isset($keys[$k]['key'])) {
    http_response_code(404);
    echo json_encode(["error" => "Key not found"]);
    exit;
}

// Pecah key menjadi ID dan nilai
$raw = explode(':', $keys[$k]['key']);
if (count($raw) !== 2) {
    http_response_code(500);
    echo json_encode(["error" => "Invalid key format"]);
    exit;
}

$key_id_b64 = hexToBase64UrlSafe($raw[0]);
$key_b64    = hexToBase64UrlSafe($raw[1]);

if (!$key_id_b64 || !$key_b64) {
    http_response_code(500);
    echo json_encode(["error" => "Invalid key encoding"]);
    exit;
}

// Tampilkan ClearKey JSON
echo json_encode([
    "keys" => [[
        "kty" => "oct",
        "kid" => $key_id_b64,
        "k"   => $key_b64
    ]],
    "type" => "temporary"
]);
exit;
?>
