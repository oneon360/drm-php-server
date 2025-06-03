<?php
header("Content-Type: application/json");

function respond($response) {
    echo json_encode($response);
    exit;
}

function hexToBase64UrlSafe($hex) {
    $base64 = base64_encode(hex2bin($hex));
    return rtrim(strtr($base64, '+/', '-_'), '=');
}

// --- Deteksi isCurlLike ---
function is_curl_like() {
    $headers = getallheaders();
    $ua = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
    $accept = strtolower($_SERVER['HTTP_ACCEPT'] ?? '');
    $encoding = strtolower($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '');
    $connection = strtolower($_SERVER['HTTP_CONNECTION'] ?? '');

    if (
        empty($accept) || strpos($accept, '*/*') !== false ||
        empty($encoding) ||
        $connection === 'close' ||
        preg_match('/curl|httpie|python|wget|libhttp|powershell|http-client/i', $ua)
    ) {
        return true;
    }

    if (
        (strpos($ua, 'exoplayer') !== false && ($headers['x-requested-with'] ?? '') !== 'com.google.android.exoplayer') ||
        (strpos($ua, 'kodi') !== false && ($headers['x-requested-with'] ?? '') !== 'org.xbmc.kodi')
    ) {
        return true;
    }

    return false;
}

if (is_curl_like()) {
    respond(["status" => "error", "message" => "iscurllike_detected"]);
}

// --- Validasi Header dan UA ---
$ua = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
$accept = strtolower($_SERVER['HTTP_ACCEPT'] ?? '');
$contentType = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
$xReqWith = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
$secFetch = isset($_SERVER['HTTP_SEC_FETCH_MODE']) || isset($_SERVER['HTTP_SEC_FETCH_SITE']);

if (
    strpos($ua, 'curl') !== false || strpos($ua, 'wget') !== false || strpos($ua, 'python') !== false
) {
    respond(["status" => "error", "message" => "ua_blocked"]);
}

if ($contentType === "application/octet-stream") {
    respond(["status" => "error", "message" => "invalid_content_type"]);
}

if ($secFetch || strpos($accept, 'text/html') !== false) {
    respond(["status" => "error", "message" => "browser_like_request"]);
}

if (strpos($ua, "exoplayer") !== false && $xReqWith !== "com.google.android.exoplayer") {
    respond(["status" => "error", "message" => "spoofed_exoplayer"]);
}

$allowed = false;
if (
    (strpos($ua, "exoplayer") !== false && $xReqWith === "com.google.android.exoplayer") ||
    (strpos($ua, "shaka") !== false) ||
    (strpos($ua, "kodi") !== false) ||
    (strpos($ua, "vlc") !== false)
) {
    $allowed = true;
}
if (!$allowed) {
    respond(["status" => "error", "message" => "unauthorized_player"]);
}

// --- Load key list dari file ---
$keyFile = '/var/www/keys/keylist.json';
if (!file_exists($keyFile)) {
    respond(["status" => "error", "message" => "unexpected_response"]);
}

$keyJson = file_get_contents($keyFile);
$keyList = json_decode($keyJson, true);
if (!is_array($keyList)) {
    respond(["status" => "error", "message" => "key_file_invalid"]);
}

// --- Ambil ID dari parameter ---
$id = $_GET['id'] ?? $_POST['id'] ?? '';
if (!preg_match('/^[a-zA-Z0-9]+$/', $id)) {
    respond(["status" => "error", "message" => "invalid_id_format"]);
}

if (!isset($keyList[$id])) {
    respond(["status" => "error", "message" => "key_not_found"]);
}

// --- Format respon DRM key ---
$keyData = $keyList[$id];
list($key_hex, $keyid_hex) = explode(':', $keyData['key']);
$key_b64 = hexToBase64UrlSafe($key_hex);
$keyid_b64 = hexToBase64UrlSafe($keyid_hex);

respond([
    "status" => "ok",
    "message" => "key_granted",
    "key" => [
        "name" => $keyData['name'],
        "key" => $key_b64,
        "kid" => $keyid_b64
    ]
]);
