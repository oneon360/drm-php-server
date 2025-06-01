<?php
// Blokir akses langsung yang tidak mengandung secret header
if (!isset($_SERVER['HTTP_X_WORKER_SECRET']) || $_SERVER['HTTP_X_WORKER_SECRET'] !== 'abc123') {
    http_response_code(403);
    header('Content-Type: application/json');
    exit(json_encode(["error" => "Unauthorized"]));
}

// Gunakan content-type biner agar browser tidak menampilkan JSON
header('Content-Type: application/octet-stream');
header('Cache-Control: no-store');

// Ambil header User-Agent dan Accept
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
$accept = $_SERVER['HTTP_ACCEPT'] ?? '';

// Deteksi iPhone Safari / Firefox Mobile
$isIphone = stripos($ua, 'iPhone') !== false;
$isSafariMobile = $isIphone && stripos($ua, 'Safari') !== false && preg_match('/Version\/\d+/', $ua);
$isFirefoxMobile = $isIphone && stripos($ua, 'Firefox') !== false;

// Deteksi Firefox Desktop atau Android
$isFirefoxDesktopOrAndroid = stripos($ua, 'Firefox') !== false && !$isFirefoxMobile;

// Deteksi Chrome asli (real)
$isChromeReal = stripos($ua, 'Chrome') !== false &&
                stripos($ua, 'crios') === false &&
                !$isSafariMobile &&
                !$isFirefoxDesktopOrAndroid;

// Blokir jika bukan Chrome asli dan Accept: text/html (indikasi browser biasa)
if (!$isChromeReal && stripos($accept, 'text/html') !== false) {
    http_response_code(200);
    echo json_encode(["error" => "Unexpected UA"]);
    exit;
}

// Konversi HEX ke Base64 URL-safe
function hexToBase64UrlSafe($hex) {
    $base64 = base64_encode(hex2bin($hex));
    return rtrim(strtr($base64, '+/', '-_'), '=');
}

// Ambil parameter acak (misal ?k=9aB4xZ)
$k = $_GET['k'] ?? null;
if (!$k || !preg_match('/^[a-zA-Z0-9]{6,20}$/', $k)) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid or missing parameter"]);
    exit;
}

// Path ke file key
$keyFile = '/var/www/keys/keylist.json'; // Ganti sesuai path Anda
if (!file_exists($keyFile)) {
    http_response_code(500);
    echo json_encode(["error" => "Key file not found"]);
    exit;
}

// Ambil JSON dan validasi format
$keys = json_decode(file_get_contents($keyFile), true);
if (!isset($keys[$k]) || !isset($keys[$k]['key'])) {
    http_response_code(404);
    echo json_encode(["error" => "Key not found"]);
    exit;
}

// Pisahkan key ID dan key dari format "id:key"
$raw = explode(':', $keys[$k]['key']);
if (count($raw) !== 2) {
    http_response_code(500);
    echo json_encode(["error" => "Invalid key format"]);
    exit;
}

$key_id_hex = $raw[0];
$key_hex    = $raw[1];

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
