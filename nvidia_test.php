<?php
// Standalone diagnostic — not part of the app. Delete after testing.
$key = getenv('NVIDIA_API_KEY');
if (!$key) die('Set NVIDIA_API_KEY in the server environment before running this diagnostic.');

$payload = json_encode([
    'model' => 'meta/llama-3.2-11b-vision-instruct',
    'messages' => [['role' => 'user', 'content' => 'say hello']],
]);

$ch = curl_init('https://integrate.api.nvidia.com/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $key],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_VERBOSE => true,
    CURLOPT_STDERR => fopen('php://output', 'w'), // dump verbose curl log directly to the page
    CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_TIMEOUT => 30,
]);

echo "<pre>";
echo "--- curl_version ---\n";
print_r(curl_version());
echo "\n--- verbose log ---\n";
$response = curl_exec($ch);
echo "\n--- result ---\n";
echo "curl_errno: " . curl_errno($ch) . "\n";
echo "curl_error: " . curl_error($ch) . "\n";
echo "http_code: " . curl_getinfo($ch, CURLINFO_HTTP_CODE) . "\n";
echo "response: " . var_export($response, true) . "\n";
curl_close($ch);
echo "</pre>";
