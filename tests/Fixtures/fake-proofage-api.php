<?php
// Local stand-in for api.proofage.net used by integration checks.
// Run: php -S 127.0.0.1:8765 tests/Fixtures/fake-proofage-api.php
$secret = getenv('FAKE_SK') ?: 'sk_test_fake';
$dir = sys_get_temp_dir() . '/proofage-fake-api';
@mkdir($dir);
$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);
$body = file_get_contents('php://input');

function respond(int $status, array $data): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
}

if (str_starts_with($path, '/v1/')) {
    $expected = hash_hmac('sha256', $method . $uri . $body, $secret);
    if (!hash_equals($expected, $_SERVER['HTTP_X_HMAC_SIGNATURE'] ?? '')) {
        respond(401, ['code' => 'INVALID_SIGNATURE', 'message' => 'Invalid signature']);
        return true;
    }
}

if ($method === 'POST' && $path === '/v1/verifications') {
    $payload = json_decode($body, true);
    $id = sprintf('%08x-0000-4000-8000-%012x', random_int(0, 0xffffffff), random_int(0, 0xffffffffffff));
    $record = ['id' => $id, 'external_id' => $payload['external_id'] ?? null, 'callback_url' => $payload['callback_url'] ?? null, 'status' => 'created', 'url' => 'http://127.0.0.1:8765/hosted/' . $id];
    file_put_contents("$dir/$id.json", json_encode($record));
    respond(201, $record);
} elseif ($method === 'GET' && preg_match('#^/v1/verifications/([^/]+)$#', $path, $m) && is_file("$dir/{$m[1]}.json")) {
    respond(200, json_decode(file_get_contents("$dir/{$m[1]}.json"), true));
} elseif ($method === 'GET' && $path === '/v1/workspace') {
    respond(200, ['id' => 'ws_fake', 'name' => 'Fake workspace', 'mode' => 'test', 'flow_type' => 'age', 'age_mode' => 'estimation', 'age_threshold' => 18]);
} elseif ($method === 'POST' && preg_match('#^/__set/([^/]+)$#', $path, $m) && is_file("$dir/{$m[1]}.json")) {
    $record = json_decode(file_get_contents("$dir/{$m[1]}.json"), true);
    $record['status'] = $_GET['status'] ?? 'approved';
    if (isset($_GET['method'])) {
        $record['method'] = $_GET['method'];
    }
    file_put_contents("$dir/{$m[1]}.json", json_encode($record));
    respond(200, $record);
} elseif ($method === 'GET' && str_starts_with($path, '/hosted/')) {
    header('Content-Type: text/html');
    echo '<!doctype html><title>Fake hosted flow</title><h1>Fake ProofAge hosted flow</h1>';
} else {
    respond(404, ['code' => 'NOT_FOUND', 'message' => 'Not found']);
}

return true;
