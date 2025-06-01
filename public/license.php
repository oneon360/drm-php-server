<?php
// Validasi secret header dari Worker
if (!isset($_SERVER['HTTP_X_WORKER_SECRET']) || $_SERVER['HTTP_X_WORKER_SECRET'] !== 'abc123') {
    http_response_code(403);
    header('Content-Type: application/json');
    exit(json_encode(["error" => "Unauthorized"]));
}

// Force response agar tidak diproses browser
header('Content-Type: application/octet-stream');
header('Cache-Control: no-store');

// Ambil parameter k
$k = $_GET['k'] ?? null;
if (!$k || !preg_match('/^[a-zA-Z0-9]{6,20}$/', $k)) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid or missing parameter"]);
    exit;
}

// Validasi User-Agent dan pemblokiran lanjutan
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
$accept = $_SERVER['HTTP_ACCEPT'] ?? '';

// Blokir alat dan library scraping
$toolBlock = '/curl|wget|httpclient|python|requests|aiohttp|urllib|httpie|powershell|php|perl|ruby|java|okhttp|libwww|fetch|node-fetch|axios|go-http-client|restsharp|csharp|net\/|postman|insomnia|superagent|got|reqwest|khttp|mechanize|lwp::simple|apache-httpclient|scrapy|selenium|puppeteer|phantomjs|winhttp|wininet|rest-client|r-curl|grequests|hyper/i';
if (preg_match($toolBlock, $ua)) {
    http_response_code(200);
    echo json_encode(["error" => "Tool blocked"]);
    exit;
}

// Blokir ExoPlayer
if (stripos($ua, 'ExoPlayer') !== false) {
    http_response_code(200);
    echo json_encode(["error" => "Blocked UA"]);
    exit;
}

// Izinkan hanya WebView Chrome
$isWV = stripos($ua, 'wv') !== false && preg_match('/Chrome\/[\d.]+ Mobile/', $ua);
if (!$isWV) {
    http_response_code(200);
    echo json_encode(["error" => "Only Chrome WebView allowed"]);
    exit;
}

// Ambil file kunci
$keyFile = '/var/www/keys/keylist.json'; // Ubah jika perlu
if (!file_exists($keyFile)) {
    http_response_code(500);
    echo json_encode(["error" => "Key file not found"]);
    exit;
}

$keys = json_decode(file_get_contents($keyFile), true);
if (!isset($keys[$k]['key'])) {
    http_response_code(404);
    echo json_encode(["error" => "Key not found"]);
    exit;
}

// Parsing key id dan key
$raw = explode(':', $keys[$k]['key']);
if (count($raw) !== 2) {
    http_response_code(500);
    echo json_encode(["error" => "Invalid key format"]);
    exit;
}

// Konversi ke Base64 URL-safe
function hexToBase64UrlSafe($hex) {
    $base64 = base64_encode(hex2bin($hex));
    return rtrim(strtr($base64, '+/', '-_'), '=');
}

$key_id_hex = $raw[0];
$key_hex = $raw[1];

// Output ClearKey JSON
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
