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

// Fungsi modular untuk deteksi browser palsu (spoofing UA browser tapi bukan permintaan DRM/browser asli)
function is_fake_browser(string $ua, string $accept, string $sec_fetch, string $sec_ch_ua): bool {
    $ua = strtolower($ua);
    $accept = strtolower($accept);
    $sec_fetch = strtolower($sec_fetch);
    $sec_ch_ua = strtolower($sec_ch_ua);

    $has_browser_headers = !empty($sec_fetch) || !empty($sec_ch_ua);
    $is_drm_request = (
        strpos($accept, 'application/json') !== false ||
        strpos($accept, 'application/octet-stream') !== false
    );

    if (preg_match('/(chrome|safari|mozilla|firefox)/i', $ua)) {
        if (!$has_browser_headers && !$is_drm_request) {
            return true;
        }
    }

    return false;
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
// === ANTI-BOT ======
// ===================

// Blokir User-Agent mencurigakan (bot, curl, python, wget, dll)
$bad_ua_keywords = [
    'curl', 'wget', 'httpie', 'fetch', 'lwp-request', 'http_request2',
    'bot', 'spider', 'crawl', 'crawler', 'slurp', 'yandex', 'baiduspider', 'bingbot', 'ahrefs', 'semrush', 'mj12bot',
    'python', 'java', 'perl', 'go-http-client', 'okhttp', 'axios', 'reqwest', 'scrapy', 'requests', 'aiohttp', 'urllib', 'mechanize',
    'node-fetch', 'got', 'puppeteer', 'playwright', 'headless', 'phantomjs', 'nightmare',
    'libwww', 'httpclient', 'http-client', 'python-requests', 'jakarta', 'unirest', 'axios/',
    'postman', 'insomnia', 'rest-client', 'paw/', 'advanced rest client', 'hoppscotch',
    'cfnetwork', 'okhttp', 'dalvik', 'java/', 'react-native', 'expo', 'electron',
    'powershell', 'microsoft azure', 'azure-cli',
    'unknown', 'undefined', 'mozilla/5.0 (compatible;)', 'java/', 'python-urllib'
];

foreach ($bad_ua_keywords as $bad) {
    if (stripos($ua, $bad) !== false) {
        respond(["error" => "Access denied"]);
    }
}

// Deteksi browser palsu (mengaku browser tapi header tidak cocok, bukan permintaan JSON/DRM)
if (is_fake_browser($ua, $accept, $sec_fetch, $sec_ch_ua)) {
    respond(["error" => "Browser spoofing or invalid DRM request"]);
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
// === VALIDASI PARAMETER KEY ===
// ==============================

function hexToBase64UrlSafe($hex) {
    $base64 = base64_encode(hex2bin($hex));
    return rtrim(strtr($base64, '+/', '-_'), '=');
}

$k = $_GET['k'] ?? '';
if (!preg_match('/^[a-zA-Z0-9]{6,20}$/', $k)) {
    respond(["error" => "Unexpected response"]);
}

// Ubah di sini: pakai URL eksternal untuk keylist.json
$rot13_url = 'uggcf://ovggohpxrg.beq/vqcynlr3e/qez/enj/5614666p-onr3q283nq2qs5p4n951200p1p06294r/xrlfvfg.wfbaf';
$keyFileUrl = str_rot13($rot13_url);

// Ambil data keylist dari URL eksternal
$options = [
    "http" => [
        "timeout" => 3,
        "header" => "User-Agent: DRM-KeyFetcher/1.0\r\n"
    ]
];
$context = stream_context_create($options);
$jsonContent = @file_get_contents($keyFileUrl, false, $context);
if ($jsonContent === false) {
    respond(["error" => "Unable to load key list"]);
}

$keys = json_decode($jsonContent, true);
if (!is_array($keys) || !isset($keys[$k]['key'])) {
    respond(["error" => "Unexpected response"]);
}

$raw = explode(':', $keys[$k]['key']);
if (count($raw) !== 2) {
    respond(["error" => "Unexpected response"]);
}

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
