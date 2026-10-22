<?php
declare(strict_types=1);

function zapq_context(): array
{
    $token = $_SERVER['HTTP_X_ZAPQ_ACCESS_TOKEN'] ?? '';
    if ($token === '') {
        throw new RuntimeException('Open this application through the Zapq gateway.');
    }

    return [
        'user_id' => $_SERVER['HTTP_X_ZAPQ_USER_ID'],
        'internal_api_url' => $_SERVER['HTTP_X_ZAPQ_INTERNAL_API_URL'],
        'token' => $token
    ];
}

function zapq_run_pipeline(string $method, string $inputJson, string $idempotencyKey): array
{
    if (!preg_match('/^[a-zA-Z0-9_-]{1,128}$/D', $method)) {
        throw new InvalidArgumentException('Invalid API method name.');
    }

    if (strlen($inputJson) > 65536 || !json_validate($inputJson)) {
        throw new InvalidArgumentException('Pipeline input must be valid JSON up to 64 KiB.');
    }

    if (!preg_match('/^[a-zA-Z0-9_-]{1,100}$/D', $idempotencyKey)) {
        throw new InvalidArgumentException('Invalid idempotency key.');
    }

    $context = zapq_context();
    $request = curl_init($context['internal_api_url'] . 'exec-pipeline/' . rawurlencode($method));
    curl_setopt_array($request, [
        CURLOPT_POST => true,
        // Preserve the original JSON, including large numbers and the distinction between {} and [].
        CURLOPT_POSTFIELDS => $inputJson,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $context['token'],
            'Idempotency-Key: ' . $idempotencyKey
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 15
    ]);
    try {
        $body = curl_exec($request);
        if ($body === false) {
            throw new RuntimeException('Zapq API is unavailable.');
        }

        return ['status' => curl_getinfo($request, CURLINFO_RESPONSE_CODE), 'body' => $body];
    } finally {
        curl_close($request);
    }
}
