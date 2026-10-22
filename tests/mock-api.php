<?php
declare(strict_types=1);
// Isolated HTTP fixture, never served from public/ or used by the deployed application.
$body = file_get_contents('php://input');
file_put_contents(getenv('ZAPQIO_APPLICATION_DATA_DIRECTORY') . '/api-request.json', json_encode([
    'path' => $_SERVER['REQUEST_URI'], 'method' => $_SERVER['REQUEST_METHOD'], 'body' => $body,
    'authorization' => $_SERVER['HTTP_AUTHORIZATION'] ?? '', 'key' => $_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? ''
], JSON_THROW_ON_ERROR));
header('Content-Type: application/json');
echo '{"runId":"11111111-1111-1111-1111-111111111111"}';
