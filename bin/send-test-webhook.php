<?php
// Sends a signed ProofAge-style webhook, e.g. to replay an outcome to a local shop.
// Usage: php bin/send-test-webhook.php <webhook-url> <secret> <verification-id> <status> [method]
if ($argc < 5) {
    fwrite(STDERR, "Usage: php bin/send-test-webhook.php <webhook-url> <secret> <verification-id> <status> [method]\n");
    exit(2);
}
[, $url, $secret, $verificationId, $status] = $argv;
$payload = ['verification_id' => $verificationId, 'status' => $status, 'timestamp' => gmdate('c')];
if (isset($argv[5])) {
    $payload['method'] = $argv[5];
}
$body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$ts = (string) time();
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $body,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'X-Timestamp: ' . $ts,
        'X-HMAC-Signature: ' . hash_hmac('sha256', $ts . '.' . $body, $secret),
        'X-ProofAge-Webhook-Delivery-Id: manual-' . bin2hex(random_bytes(8)),
    ],
]);
$response = curl_exec($ch);
echo curl_getinfo($ch, CURLINFO_RESPONSE_CODE) . ' ' . $response . "\n";
