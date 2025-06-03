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

// Ambil header penting
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
$accept = $_SERVER['HTTP_ACCEPT'] ?? '';
$sec_fetch = $_SERVER['HTTP_SEC_FETCH_MODE'] ?? '';
$sec_ch_ua = $_SERVER['HTTP_SEC_CH_UA'] ?? '';
$connection = $_SERVER['HTTP_CONNECTION'] ?? '';
$encoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';

// ===================
// === ANTI-BOT ====
// ===================

// Blokir User-Agent mencurigakan (bot, curl, python, wget, dll)
$bad_ua_keywords = [
    // Tools CLI umum
    'curl', 'wget', 'httpie', 'fetch', 'lwp-request', 'http_request2',

    // Bot dan crawler klasik
    'bot', 'spider', 'crawl', 'crawler', 'slurp', 'yandex', 'baiduspider', 'bingbot', 'ahrefs', 'semrush', 'mj12bot',

    // Tools scraping modern
    'python', 'java', 'perl', 'go-http-client', 'okhttp', 'axios', 'reqwest', 'scrapy', 'requests', 'aiohttp', 'urllib', 'mechanize',

    // Node.js & headless
    'node-fetch', 'got', 'puppeteer', 'playwright', 'headless', 'phantomjs', 'nightmare',

    // HTTP libraries & user-agent default
    'libwww', 'httpclient', 'http-client', 'python-requests', 'jakarta', 'unirest', 'axios/',

    // Tools eksplorasi API
    'postman', 'insomnia', 'rest-client', 'paw/', 'advanced rest client', 'hoppscotch',

    // Emulator / device spoofing
    'cfnetwork', 'okhttp', 'dalvik', 'java/', 'react-native', 'expo', 'electron',

    // PowerShell & Azure
    'powershell', 'microsoft azure', 'azure-cli',

    // Fake browser / invalid
    'unknown', 'undefined', 'mozilla/5.0 (compatible;)', 'java/', 'python-urllib'
];

foreach ($bad_ua_keywords as $bad) {
    if (stripos($ua, $bad) !== false) {
        respond(["error" => "Access denied"]);
    }
}

// Deteksi browser palsu hanya jika User-Agent mengandung browser populer,
// tapi tidak menyertakan tanda-tanda permintaan JSON atau DRM (misalnya tidak ada Accept: application/json)
if (preg_match('/(chrome|safari|mozilla|firefox)/i', $ua)) {
    $is_browser = !empty($sec_fetch) || !empty($sec_ch_ua);
    $is_drm_request = stripos($accept, 'application/json') !== false || stripos($accept, 'application/octet-stream') !== false;

    // Jika mengaku browser tapi tidak menunjukkan tanda akses DRM atau JSON
    if (!$is_browser && !$is_drm_request) {
        respond(["error" => "Browser spoofing or invalid DRM request"]);
    }
}


// Blokir jika Accept mengandung text/html (indikasi browser)
if (stripos($accept, 'text/html') !== false) {
    respond(["error" => "Access denied"]);
}

// Blokir jika sec-fetch-* atau sec-ch-ua muncul (indikasi browser modern)
if (!empty($sec_fetch) || !empty($sec_ch_ua)) {
    respond(["error" => "Access denied"]);
}

// Blokir koneksi aneh atau terlalu umum (indikasi tools HTTP)
if (stripos($connection, 'keep-alive') !== false && empty($ua)) {
    respond(["error" => "Access denied"]);
}

// Blokir jika Accept-Encoding tidak berisi gzip (banyak bot lupa set ini)
if (stripos($encoding, 'gzip') === false) {
    respond(["error" => "Access denied"]);
}

// ==============================
// === VALIDASI PARAMETER KEY ==
// ==============================

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
