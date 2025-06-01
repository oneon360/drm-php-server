<?php
// Blokir akses langsung yang tidak mengandung secret header
if (!isset($_SERVER['HTTP_X_WORKER_SECRET']) || $_SERVER['HTTP_X_WORKER_SECRET'] !== 'abc123') {
    http_response_code(200);
    header('Content-Type: application/json');
    exit(json_encode(["error" => "Unauthorized"]));
}

// Gunakan content-type biner agar browser tidak menampilkan JSON
header('Content-Type: application/octet-stream');
header('Cache-Control: no-store');

// Ambil header
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
$accept = $_SERVER['HTTP_ACCEPT'] ?? '';
$acceptEncoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
$xRequestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$host = $_SERVER['HTTP_HOST'] ?? '';

// === Blokir scraping tools ===
$blockedToolsPattern = '/curl|wget|httpclient|python|requests|aiohttp|urllib|powershell|php|java|okhttp|axios|fetch|node-fetch|postman|insomnia|scrapy|selenium|puppeteer|phantomjs/i';
if (preg_match($blockedToolsPattern, $ua)) {
    http_response_code(200);
    echo json_encode(["error" => "Tool blocked"]);
    exit;
}

// === Blokir ExoPlayer dan Kodi permanen ===
if (stripos($ua, 'ExoPlayer') !== false || stripos($ua, 'Kodi') !== false) {
    http_response_code(200);
    echo json_encode(["error" => "Blocked UA"]);
    exit;
}

// === Hanya izinkan Chrome WebView ===
// Cek pola: Chrome/xxx Mobile dan mengandung "wv"
$isChromeWV = preg_match('/Chrome\/[\d.]+ Mobile/', $ua) && stripos($ua, 'wv') !== false;
if (!$isChromeWV) {
    http_response_code(200);
    echo json_encode(["error" => "Only Chrome WebView allowed"]);
    exit;
}

// === Cek parameter k ===
$k = $_GET['k'] ?? null;
if (!$k || !preg_match('/^[a-zA-Z0-9]{6,20}$/', $k)) {
    http_response_code(200);
    echo json_encode(["error" => "Invalid or missing parameter"]);
    exit;
}

// === Path ke file key ===
$keyFile = '/var/www/keys/keylist.json'; // Ganti sesuai path Anda
if (!file_exists($keyFile)) {
    http_response_code(200);
    echo json_encode(["error" => "Key file not found"]);
    exit;
}

// === Baca dan validasi key JSON ===
$keys = json_decode(file_get_contents($keyFile), true);
if (!isset($keys[$k]) || !isset($keys[$k]['key'])) {
    http_response_code(200);
    echo json_encode(["error" => "Key not found"]);
    exit;
}

// === Parsing key ===
$raw = explode(':', $keys[$k]['key']);
if (count($raw) !== 2) {
    http_response_code(200);
    echo json_encode(["error" => "Invalid key format"]);
    exit;
}

$key_id_hex = $raw[0];
$key_hex    = $raw[1];

// === Helper konversi HEX ke Base64 URL-safe ===
function hexToBase64UrlSafe($hex) {
    $base64 = base64_encode(hex2bin($hex));
    return rtrim(strtr($base64, '+/', '-_'), '=');
}

// === Output DRM JSON ===
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
