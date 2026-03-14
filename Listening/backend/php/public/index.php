<?php
declare(strict_types=1);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET,POST,PUT,DELETE,OPTIONS');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$pythonBase = getenv('PYTHON_API_BASE');
if ($pythonBase === false || $pythonBase === '') {
    $pythonBase = 'http://127.0.0.1:8001';
}

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$query = $_SERVER['QUERY_STRING'] ?? '';
$target = rtrim($pythonBase, '/') . $path . ($query !== '' ? '?' . $query : '');

$headers = [];
foreach (getallheaders() as $name => $value) {
    if (strtolower($name) === 'host' || strtolower($name) === 'content-length') {
        continue;
    }
    $headers[] = $name . ': ' . $value;
}

$body = file_get_contents('php://input');
$ch = curl_init($target);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $_SERVER['REQUEST_METHOD']);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_HEADER, true);
if ($body !== false && $body !== '') {
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
}

$response = curl_exec($ch);
if ($response === false) {
    http_response_code(502);
    echo json_encode([
        'detail' => 'PHP gateway could not reach Python service',
        'error' => curl_error($ch),
    ], JSON_UNESCAPED_UNICODE);
    curl_close($ch);
    exit;
}

$status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$rawHeaders = substr($response, 0, $headerSize);
$responseBody = substr($response, $headerSize);
curl_close($ch);

http_response_code($status ?: 200);
foreach (explode("\r\n", $rawHeaders) as $headerLine) {
    if ($headerLine === '' || str_starts_with(strtolower($headerLine), 'http/')) {
        continue;
    }
    if (stripos($headerLine, 'transfer-encoding:') === 0 || stripos($headerLine, 'content-length:') === 0) {
        continue;
    }
    header($headerLine, false);
}

echo $responseBody;
