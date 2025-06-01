<?php
// Blokir akses langsung tanpa header rahasia
if ($_SERVER['HTTP_X_WORKER_SECRET'] ?? '' !== 'abc123') {
    http_response_code(403);
    header('Content-Type: application/json');
    exit(json_encode(["error" => "Unauthorized"]));
}

// Gunakan header octet-stream agar tidak tampil di browser
header('Content-Type: application/octet-stream');
header('Cache-Control: no-store');

// Ambil User-Agent dan Accept
$ua     = $_SERVER['HTTP_USER_AGENT'] ?? '';
$accept = $_SERVER['HTTP_ACCEPT'] ?? '';

// Deteksi iPhone
$isIphone = stripos($ua, 'iPhone') !== false;

// Deteksi Mobile Safari dan Firefox Mobile
$isSafariMobile   = $isIphone && stripos($ua, 'Safari') !== false && preg_match('/Version\/\d+/', $ua);
$isFirefoxMobile  = $isIphone && stripos($ua, 'Firefox') !== false;

// Deteksi Firefox (Desktop/Android)
$isFirefoxGeneric = stripos($ua, 'Firefox') !== false;

// Deteksi Chrome asli (bukan CriOS, bukan Safari mobile, bukan Firefox)
$isChromeReal = stripos($ua, 'Chrome') !== false &&
                stripos($ua, 'CriOS') === false &&
                !$isSafariMobile &&
                !$isFirefoxGeneric;

// Blokir jika bukan Chrome asli dan terindikasi browser biasa
if (!$isChromeReal && stripos($accept, 'text/html') !== false) {
    http_response_code(200);
    echo json_encode(["error" => "Unexpected UA"]);
    exit;
}

// Fungsi konversi HEX ke Base64 URL-safe
function hexToBase64UrlSafe($hex) {
    $bin = @hex2bin($hex);
    if ($bin === false) return null;
    return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
}

// Ambil parameter ?k=xxxx
$k = $_GET['k'] ?? '';
if (!preg_match('/^[a-zA-Z0-9]{6,20}$/', $k)) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid or missing parameter"]);
    exit;
}

// Path ke file key
$keyFile = '/var/www/keys/keylist.json';
if (!is_file($keyFile)) {
    http_response_code(500);
    echo json_encode(["error" => "Key file not found"]);
    exit;
}

// Ambil isi file key
$keys = json_decode(file_get_contents($keyFile), true);
if (!isset($keys[$k]['key'])) {
    http_response_code(404);
    echo json_encode(["error" => "Key not found"]);
    exit;
}

// Pecah format "keyid:key"
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
