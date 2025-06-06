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
        if (!$has_browser_headers && !$is_drm_request) return true;
    }
    return false;
}

// Validasi HTTPS
$is_https = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
    $_SERVER['SERVER_PORT'] == 443 ||
    (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
);
if (!$is_https) {
    respond(["error" => "HTTPS required"]);
}

// Validasi Secret Worker
if (!isset($_SERVER['HTTP_X_WORKER_SECRET']) || $_SERVER['HTTP_X_WORKER_SECRET'] !== 'abc123') {
    respond(["error" => "Unexpected response"]);
}

// Set response DRM
header('Content-Type: application/octet-stream');
header('Cache-Control: no-store');

// Header request
$ua         = $_SERVER['HTTP_USER_AGENT'] ?? '';
$accept     = $_SERVER['HTTP_ACCEPT'] ?? '';
$sec_fetch  = $_SERVER['HTTP_SEC_FETCH_MODE'] ?? '';
$sec_ch_ua  = $_SERVER['HTTP_SEC_CH_UA'] ?? '';
$connection = $_SERVER['HTTP_CONNECTION'] ?? '';
$encoding   = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';

// Anti bot & spoofing
$bad_ua_keywords = [
    'curl','wget','httpie','fetch','lwp-request','http_request2',
    'bot','spider','crawl','crawler','slurp','yandex','baiduspider','bingbot','ahrefs','semrush','mj12bot',
    'python','java','perl','go-http-client','okhttp','axios','reqwest','scrapy','requests','aiohttp','urllib','mechanize',
    'node-fetch','got','puppeteer','playwright','headless','phantomjs','nightmare',
    'libwww','httpclient','python-requests','jakarta','unirest','axios/',
    'postman','insomnia','rest-client','paw/','advanced rest client','hoppscotch',
    'cfnetwork','dalvik','react-native','expo','electron',
    'powershell','azure-cli','unknown','undefined','(compatible;)','python-urllib'
];
foreach ($bad_ua_keywords as $bad) {
    if (stripos($ua, $bad) !== false) {
        respond(["error" => "Access denied"]);
    }
}
if (is_fake_browser($ua, $accept, $sec_fetch, $sec_ch_ua)) {
    respond(["error" => "Browser spoofing or invalid DRM request"]);
}
if (stripos($accept, 'text/html') !== false) {
    respond(["error" => "Access denied"]);
}
if (!empty($sec_fetch) || !empty($sec_ch_ua)) {
    respond(["error" => "Access denied"]);
}
if (stripos($connection, 'keep-alive') !== false && empty($ua)) {
    respond(["error" => "Access denied"]);
}
if (stripos($encoding, 'gzip') === false) {
    respond(["error" => "Access denied"]);
}

// Ambil parameter key
$k = $_GET['k'] ?? '';
if (!preg_match('/^[a-zA-Z0-9]{6,20}$/', $k)) {
    respond(["error" => "Unexpected response"]);
}

// === Ambil key dari URL eksternal ===
$keyFile = 'https://bitbucket.org/idplay3r/drm/raw/5614666cbae3d283ad2fd5c4a951200c1c06294e/keylist.json';
if (preg_match('/^https?:\/\//', $keyFile)) {
    $context = stream_context_create([
        'http' => ['timeout' => 3],
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]
    ]);
    $json = @file_get_contents($keyFile, false, $context);
    if (!$json) respond(["error" => "Failed to fetch keylist"]);
} else {
    if (!file_exists($keyFile)) respond(["error" => "Keylist not found"]);
    $json = @file_get_contents($keyFile);
}

$keys = json_decode($json, true);
if (!is_array($keys)) respond(["error" => "Invalid keylist format"]);
if (!isset($keys[$k]) || !isset($keys[$k]['key'])) respond(["error" => "Key not found"]);
$raw = explode(':', $keys[$k]['key']);
if (count($raw) !== 2) respond(["error" => "Invalid key format"]);

function hexToBase64UrlSafe($hex) {
    $base64 = base64_encode(hex2bin($hex));
    return rtrim(strtr($base64, '+/', '-_'), '=');
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
