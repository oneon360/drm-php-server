<?php
header('Content-Type: application/json');

// Daftar User-Agent whitelist IPTV player yang valid
$allowedAgents = [
    'kodi',             // Kodi
    'exoplayer',        // ExoPlayer
    'vlc',              // VLC player
    'ffmpeg',           // FFmpeg
    'chrome',           // Chrome (kadang dipakai player custom)
    'mozilla',          // Mozilla Firefox (kadang dipakai player custom)
    'samsungbrowser',   // Samsung browser (kadang player smart TV)
    'applewebkit',      // Apple devices browser engine
];

// Daftar kata yang mengindikasikan bot/python/curl/etc yang mau diblokir
$blockAgents = [
    'python', 'requests', 'urllib', 'httpclient',
    'curl', 'wget', 'scrapy', 'postman', 'php', 'bot',
];

// Ambil user-agent dari header
$ua = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');

// Fungsi untuk cek whitelist IPTV
function isAllowedAgent($ua, $allowedAgents) {
    foreach ($allowedAgents as $allowed) {
        if (strpos($ua, $allowed) !== false) {
            return true;
        }
    }
    return false;
}

// Fungsi cek apakah mengandung agen bot
function isBlockedAgent($ua, $blockAgents) {
    foreach ($blockAgents as $block) {
        if (strpos($ua, $block) !== false) {
            return true;
        }
    }
    return false;
}

// Jika bukan dari IPTV whitelist dan terdeteksi bot, tolak akses
if (!isAllowedAgent($ua, $allowedAgents) && isBlockedAgent($ua, $blockAgents)) {
    header("HTTP/1.1 403 Forbidden");
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
    exit;
}

// --- lanjutkan script DRM key --- //

function hexToBase64($hex) {
    return base64_encode(hex2bin($hex));
}

$id = $_GET['id'] ?? null;

if (!$id) {
    http_response_code(400);
    echo json_encode(["error" => "Missing ID"]);
    exit;
}

$keyFile = __DIR__ . '/../keys/keylist.json';

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

$raw = explode(':', $keys[$id]);

if (count($raw) !== 2) {
    http_response_code(500);
    echo json_encode(["error" => "Invalid key format"]);
    exit;
}

$key_id_hex = $raw[0];
$key_hex = $raw[1];

$kid_b64 = hexToBase64($key_id_hex);
$k_b64 = hexToBase64($key_hex);

echo json_encode([
    "keys" => [
        [
            "kty" => "oct",
            "kid" => $kid_b64,
            "k"   => $k_b64
        ]
    ],
    "type" => "temporary"
]);
