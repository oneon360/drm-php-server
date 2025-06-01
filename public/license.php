<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store');
header('Server:');
header('X-Powered-By:');

function respond($data) {
    $data['_trace'] = substr(md5(mt_rand()), 0, 8);
    $data['_ts'] = time() * 1000;
    echo json_encode($data);
    exit;
}

// Validasi header X-Worker-Secret
$workerSecret = $_SERVER['HTTP_X_WORKER_SECRET'] ?? '';
if ($workerSecret !== 'abc123') {
    respond(['error' => 'Unauthorized']);
}

// Ambil parameter
$k = $_GET['k'] ?? '';
if (!preg_match('/^[a-zA-Z0-9]{6,20}$/', $k)) {
    respond(['error' => 'Invalid key']);
}

// Validasi header penting
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
$accept = $_SERVER['HTTP_ACCEPT'] ?? '';

// Blokir browser (jika Accept mengandung text/html)
if (stripos($accept, 'text/html') !== false) {
    respond(['error' => 'Unexpected response']);
}

// Blokir alat scraping dan script tools (toolUABlock)
if (preg_match('/(curl|wget|httpclient|python|requests|aiohttp|urllib|httpie|powershell|php|perl|ruby|java|okhttp|libwww|fetch|node-fetch|axios|go-http-client|restsharp|csharp|net\/|postman|insomnia|superagent|got|reqwest|khttp|mechanize|lwp::simple|apache-httpclient|scrapy|selenium|puppeteer|phantomjs|winhttp|wininet|rest-client|r-curl|grequests|hyper)/i', $ua)) {
    respond(['error' => 'Unexpected response']);
}

// Simulasi key dari backend DRM
$dummyKey = base64_encode(hash_hmac('sha256', $k, 'my-secret', true));

// Berikan respons JSON dengan license key
respond([
    'key' => $dummyKey,
    'status' => 'ok'
]);
