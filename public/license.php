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

// Set response agar tidak terbaca browser
header('Content-Type: application/octet-stream');
header('Cache-Control: no-store');

// Ambil header penting
$headers = getallheaders();
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
$accept = $_SERVER['HTTP_ACCEPT'] ?? '';
$sec_fetch = $_SERVER['HTTP_SEC_FETCH_MODE'] ?? '';
$sec_ch_ua = $_SERVER['HTTP_SEC_CH_UA'] ?? '';
$connection = $_SERVER['HTTP_CONNECTION'] ?? '';
$encoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';

// Deteksi fingerprint alat curl-like
function is_curl_like($ua, $accept, $encoding, $connection, $headers) {
    if (
        empty($accept) || strpos($accept, '*/*') !== false ||
        empty($encoding) ||
        $connection === 'close' ||
        preg_match('/curl|httpie|python|wget|libhttp|powershell|http-client/i', $ua)
    ) {
        return true;
    }

    if (
        (stripos($ua, 'exoplayer') !== false && ($headers['x-requested-with'] ?? '') !== 'com.google.android.exoplayer') ||
        (stripos($ua, 'kodi') !== false && ($headers['x-requested-with'] ?? '') !== 'org.xbmc.kodi')
    ) {
        return true;
    }

    return false;
}

if (is_curl_like($ua, $accept, $encoding, $connection, $headers)) {
    respond(["error" => "Blocked by curl-like filter"]);
}

// Blokir User-Agent mencurigakan
$bad_ua_keywords = [
    'curl','wget','httpie','fetch','lwp-request','http_request2',
    'bot','spider','crawl','crawler','slurp','yandex','baiduspider','bingbot','ahrefs','semrush',
    'python','java','perl','go-http-client','okhttp','axios','reqwest','scrapy','requests','aiohttp','urllib','mechanize',
    'node-fetch','got','puppeteer','playwright','headless','phantomjs','nightmare',
    'libwww','httpclient','http-client','python-requests','jakarta','unirest','axios/',
    'postman','insomnia','rest-client','paw/','advanced rest client','hoppscotch',
    'cfnetwork','dalvik','java/','react-native','expo','electron',
    'powershell','microsoft azure','azure-cli',
    'unknown','undefined','mozilla/5.0 (compatible;)','java/','python-urllib'
];
foreach ($bad_ua_keywords as $bad) {
    if (stripos($ua, $bad) !== false) {
        respond(["error" => "Access denied"]);
    }
}

// Blokir indikasi browser modern
if (stripos($accept, 'text/html') !== false || !empty($sec_fetch) || !empty($sec_ch_ua)) {
    respond(["error" => "Access denied"]);
}

// Blokir koneksi aneh
if (stripos($connection, 'keep-alive') !== false && empty($ua)) {
    respond(["error" => "Access denied"]);
}

// Blokir Accept-Encoding tanpa gzip
if (stripos($encoding, 'gzip') === false) {
    respond(["error" => "Access denied"]);
}

// ==============================
// === VALIDASI PARAMETER KEY ==
// ==============================

function hexToBase64UrlSafe($hex) {
    $base64 = base64_encode(hex2bin($hex));
    return rtrim(strtr($base64, '+/', '-_'), '=');
}

$k = $_GET['k'] ?? '';
if (!preg_match('/^[a-zA-Z0-9]{6,20}$/', $k)) {
    respond(["error" => "Unexpected response"]);
}

// Path ke file JSON DRM key list
$keyFile = '/var/www/keys/keylist.json'; // ← Sesuaikan lokasi file Anda
if (!file_exists($keyFile)) {
    respond(["error" => "Unexpected response"]);
}

$keys = json_decode(file_get_contents($keyFile), true);
if (!is_array($keys) || !isset($keys[$k]['key'])) {
    respond(["error" => "Unexpected response"]);
}

$raw = explode(':', $keys[$k]['key']);
if (count($raw) !== 2) {
    respond(["error" => "Unexpected response"]);
}

$key_id_hex = $raw[0];
$key_hex    = $raw[1];

// Output ClearKey JSON license
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
