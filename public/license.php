<?php
// JSON DRM key store (seharusnya disimpan secara aman, misal database)
$drm_keys = [
    "9aB4xZ" => ["name" => "VAR1", "key" => "ec7ee27d83764e4b845c48cca31c8eef:9c0e4191203fccb0fde34ee29999129e"],
    "T5eR1d" => ["name" => "VAR2", "key" => "7eea72d6075245a99ee3255603d58853:6848ef60575579bf4d415db1032153ed"]
];

// Always respond as application/json
header("Content-Type: application/json");

// Return function
function respond($status, $message = null, $key = null) {
    echo json_encode([
        "status" => $status,
        "message" => $message,
        "key" => $key
    ]);
    exit;
}

// --- Header filtering ---
$ua = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
$accept = strtolower($_SERVER['HTTP_ACCEPT'] ?? '');
$contentType = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
$xReqWith = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
$secFetch = isset($_SERVER['HTTP_SEC_FETCH_MODE']) || isset($_SERVER['HTTP_SEC_FETCH_SITE']);

// --- Block common scraping tools ---
$block_agents = ['curl', 'wget', 'httpie', 'python', 'http-client', 'scrapy', 'okhttp', 'libhttp'];
foreach ($block_agents as $agent) {
    if (strpos($ua, $agent) !== false) {
        respond("error", "ua_blocked");
    }
}

// --- Block suspicious content types ---
if ($contentType === "application/octet-stream") {
    respond("error", "invalid_content_type");
}

// --- Block browser-like headers trying to spoof ---
if ($secFetch || strpos($accept, 'text/html') !== false) {
    respond("error", "browser_like_request");
}

// --- Require proper x-requested-with for DRM ---
if (strpos($ua, "exoplayer") !== false && $xReqWith !== "com.google.android.exoplayer") {
    respond("error", "spoofed_exoplayer");
}

// --- Allow only if UA matches known DRM player ---
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
    respond("error", "unauthorized_player");
}

// --- Extract key ID from GET or POST ---
$id = $_GET['id'] ?? $_POST['id'] ?? '';
if (!preg_match('/^[a-zA-Z0-9]+$/', $id)) {
    respond("error", "invalid_id_format");
}

if (!isset($drm_keys[$id])) {
    respond("error", "key_not_found");
}

// --- Return key if all checks pass ---
$keyData = $drm_keys[$id];
respond("ok", "key_granted", $keyData);

?>
