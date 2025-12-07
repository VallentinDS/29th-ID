<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('html_errors', '0');

require_once __DIR__ . '/../lib/servicecoat.php';

function json_response(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    json_response(405, ['error' => 'Method not allowed. Use POST.']);
}

$expectedKey = getenv('SERVICE_COAT_API_KEY');
if ($expectedKey === false || $expectedKey === '') {
    json_response(500, ['error' => 'Service not configured with API key']);
}

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
if ($authHeader === '' && function_exists('getallheaders')) {
    $headers = getallheaders();
    if (isset($headers['Authorization'])) {
        $authHeader = $headers['Authorization'];
    } elseif (isset($headers['authorization'])) {
        $authHeader = $headers['authorization'];
    }
}
if (!preg_match('/^Bearer\s+(\S+)$/', $authHeader, $matches) || !hash_equals($expectedKey, $matches[1])) {
    header('WWW-Authenticate: Bearer');
    json_response(401, ['error' => 'Unauthorized']);
}

$rawBody = file_get_contents('php://input');
if ($rawBody === false || $rawBody === '') {
    json_response(400, ['error' => 'Missing request body']);
}

$data = json_decode($rawBody, true);
if (!is_array($data)) {
    json_response(400, ['error' => 'Invalid JSON in request body']);
}

$requiredFields = ['last_name', 'rank_abbr', 'unit_key'];
foreach ($requiredFields as $field) {
    if (!array_key_exists($field, $data) || !is_string($data[$field]) || trim($data[$field]) === '') {
        json_response(400, ['error' => "Missing or invalid field: {$field}"]);
    }
}

$awards = [];
if (array_key_exists('awards_abbr', $data)) {
    if (!is_array($data['awards_abbr'])) {
        json_response(400, ['error' => 'awards_abbr must be an array']);
    }
    foreach ($data['awards_abbr'] as $award) {
        if (!is_string($award)) {
            json_response(400, ['error' => 'awards_abbr must contain only strings']);
        }
        $awards[] = $award;
    }
}

$balance = 0;
if (array_key_exists('balance', $data)) {
    if (!is_numeric($data['balance'])) {
        json_response(400, ['error' => 'balance must be numeric']);
    }
    $balance = (int) $data['balance'];
}

try {
    $serviceCoat = new ServiceCoat();
    $imageData = $serviceCoat->update(
        $data['last_name'],
        $data['rank_abbr'],
        $data['unit_key'],
        $awards,
        $balance
    );
} catch (Throwable $exception) {
    error_log('Service coat generation failed: ' . $exception->getMessage());
    json_response(500, ['error' => 'Failed to generate service coat']);
}

if (!is_string($imageData) || $imageData === '') {
    json_response(500, ['error' => 'Failed to generate service coat']);
}

$crop = $serviceCoat->getSignatureCropParameters();
if (
    is_array($crop) &&
    isset($crop['y'], $crop['height'])
) {
    $headerValue = sprintf(
        'y=%d;height=%d',
        $crop['y'],
        $crop['height']
    );
    header('X-Service-Coat-Signature-Crop: ' . $headerValue);
}

header('Content-Type: image/png');
header('Content-Length: ' . strlen($imageData));
echo $imageData;
