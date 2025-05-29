<?php
header('Content-Type: application/json');

// Daftar User-Agent mencurigakan yang ingin diblokir
$blockAgents = [
    'python', 'requests', 'urllib', 'httpclient',
    'curl', 'wget', 'scrapy', 'postman',
    'httpie', 'okhttp', 'insomnia', 'axios', 'java',
    'powershell', 'php', 'bot', 'crawler', 'spider',
];

// Ambil User-Agent
$ua = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');

// Cek apakah termasuk agen terblokir
function isBlockedAgent($ua, $blockAgents) {
    foreach ($blockAgents as $bad) {
        if (strpos($ua, $bad) !== false) {
            return true;
        }
    }
    return false;
}

// Tolak jika terdeteksi agen tidak sah
if (isBlockedAgent($ua, $blockAgents)) {
    http_response_code(403);
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
    exit;
}

// --- lanjutkan proses DRM seperti biasa ---

function hexToBase64($hex) {
    return base64_encode(hex2bin($hex));
}

$id = $_GET['id'] ?? null;

if (!$id) {
    http_response_code(400);
    echo json_encode(["error" => "Missing ID"]);
    exit;
}

$keyFile = __DIR__ . '/var/www/keys/keylist.json';

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
