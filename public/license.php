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

// Fungsi konversi HEX ke Base64 URL-safe
function hexToBase64UrlSafe($hex) {
    $base64 = base64_encode(hex2bin($hex));
    return rtrim(strtr($base64, '+/', '-_'), '=');
}

// Ambil parameter ID
$id = $_GET['id'] ?? null;
if (!$id || !preg_match('/^var\d+$/', $id)) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid or missing ID"]);
    exit;
}

// Path ke key file
$keyFile = '/var/www/keys/keylist.json'; // Ubah sesuai lokasi file kamu
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

// Ekstrak key dan key_id
$raw = explode(':', $keys[$id]);
if (count($raw) !== 2) {
    http_response_code(500);
    echo json_encode(["error" => "Invalid key format"]);
    exit;
}

$key_id_hex = $raw[0];
$key_hex    = $raw[1];

// ----------------------------
// Deteksi Chrome Asli
// ----------------------------

$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
$accept = $_SERVER['HTTP_ACCEPT'] ?? '';
$via = $_SERVER['HTTP_VIA'] ?? '';
$x_requested_with = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
$xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';

$isChromeReal =
    stripos($ua, 'Chrome') !== false &&
    stripos($ua, 'crios') === false &&
    stripos($ua, 'Edg') === false &&
    stripos($ua, 'Brave') === false &&
    stripos($ua, 'OPR') === false &&
    stripos($ua, 'Vivaldi') === false &&
    stripos($accept, 'text/html') !== false;

// Blokir UA palsu atau hasil spoofing
if (!$isChromeReal || stripos($ua, 'curl') !== false || stripos($ua, 'python') !== false || stripos($ua, 'axios') !== false || stripos($ua, 'httpclient') !== false || stripos($ua, 'go-http') !== false || stripos($ua, 'wget') !== false || stripos($ua, 'java') !== false || stripos($ua, 'perl') !== false || stripos($ua, 'powershell') !== false || $via || !$xff || ($x_requested_with && $x_requested_with !== 'com.google.android.exoplayer' && $x_requested_with !== 'XMLHttpRequest')) {
    http_response_code(200);
    echo json_encode(["error" => "Unexpected UA"]);
    exit;
}

// ----------------------------
// Output ClearKey dalam format JSON terenkripsi
// ----------------------------
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
