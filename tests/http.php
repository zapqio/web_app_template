<?php
declare(strict_types=1);

function test_http(string $url, string $method = 'GET', ?string $body = null, array $headers = []): array
{
    $request = curl_init($url);
    curl_setopt_array($request, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 5, CURLOPT_FOLLOWLOCATION => false]);
    if ($body !== null) {
        curl_setopt($request, CURLOPT_POSTFIELDS, $body);
    }

    try {
        $response = curl_exec($request);
        return [curl_getinfo($request, CURLINFO_RESPONSE_CODE), $response];
    } finally {
        curl_close($request);
    }
}

function test_server(array $arguments, string $directory): array
{
    $socket = stream_socket_server('tcp://127.0.0.1:0', $code, $message);
    check($socket !== false, 'Reserve local test port.');
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $process = proc_open([PHP_BINARY, '-S', $address, ...$arguments], [
        0 => ['pipe', 'r'], 1 => ['file', $directory . '/http.log', 'a'],
        2 => ['file', $directory . '/http.log', 'a']
    ], $pipes);
    check(is_resource($process), 'Start local PHP test server.');
    fclose($pipes[0]);
    for ($attempt = 0; $attempt < 50; $attempt++) {
        $connection = @stream_socket_client('tcp://' . $address, $code, $message, 0.1);
        if ($connection !== false) {
            fclose($connection);
            return [$process, 'http://' . $address];
        }

        usleep(20000);
    }

    proc_terminate($process);
    proc_close($process);
    throw new RuntimeException('PHP test server did not start.');
}

$originalDirectory = getenv('ZAPQIO_APPLICATION_DATA_DIRECTORY');
$testDirectory = sys_get_temp_dir() . '/zapq-http-' . bin2hex(random_bytes(8));
mkdir($testDirectory, 0700);
putenv('ZAPQIO_APPLICATION_DATA_DIRECTORY=' . $testDirectory);
$servers = [];
try {
    [$process, $api] = test_server([__DIR__ . '/mock-api.php'], $testDirectory);
    $servers[] = $process;
    [$process, $app] = test_server(['-t', dirname(__DIR__) . '/public'], $testDirectory);
    $servers[] = $process;
    $owner = '22222222-2222-2222-2222-222222222222';
    $runId = '11111111-1111-1111-1111-111111111111';
    $auth = ['X-Zapq-Access-Token: test-only-token', 'X-Zapq-User-Id: ' . $owner,
        'X-Zapq-Internal-Api-Url: ' . $api . '/api/', 'X-Zapq-Csrf-Field: __RequestVerificationToken',
        'X-Zapq-Csrf-Token: test-only-csrf'];
    [$status, $html] = test_http($app . '/', headers: $auth);
    check($status === 200 && str_contains($html, 'Potok i webhook') && str_contains($html, 'test-only-csrf'), 'Single form with CSRF.');
    check(!str_contains($html, 'test-only-token'), 'Never put API credentials in HTML.');
    $input = '{ "name": "Łódź", "number": 9007199254740993 }';
    $boundary = 'zapq-test-boundary';
    $form = '';
    foreach (['method' => 'demo', 'input' => $input, 'idempotencyKey' => 'same-key'] as $name => $value) {
        $form .= '--' . $boundary . "\r\nContent-Disposition: form-data; name=\"" . $name . "\"\r\n\r\n" . $value . "\r\n";
    }

    $form .= '--' . $boundary . "--\r\n";
    [$status, $body] = test_http($app . '/run.php', 'POST', $form, [...$auth, 'Content-Type: multipart/form-data; boundary=' . $boundary]);
    check($status === 200 && json_decode($body, true)['runId'] === $runId, 'PHP starts pipeline from multipart form.');
    $request = json_decode(file_get_contents($testDirectory . '/api-request.json'), true);
    check($request === ['path' => '/api/exec-pipeline/demo', 'method' => 'POST', 'body' => $input,
        'authorization' => 'Bearer test-only-token', 'key' => 'same-key'], 'Outgoing API request preserves input, identity and idempotency.');

    [$status, $body] = test_http($app . '/status.php?runId=' . $runId, headers: $auth);
    check($status === 200 && json_decode($body, true)['webhookReceived'] === false, 'Wait for actual callback.');
    $secret = bin2hex(random_bytes(32));
    $hookId = '88888888-8888-8888-8888-888888888888';
    file_put_contents($testDirectory . '/webhook.json', json_encode(['webhookId' => $hookId, 'secrets' => [$secret]]));
    $event = ['id' => '33333333-3333-3333-3333-333333333333', 'event' => 'pipelineRun.jobFinished',
        'apiVersion' => '2026-07', 'tenant' => 'test', 'occurredAt' => '2026-10-22T10:00:00Z',
        'data' => ['runId' => $runId, 'status' => 'ok', 'lastOutput' => '{"message":"Łódź"}', 'steps' => [
            ['id' => '44444444-4444-4444-4444-444444444444', 'order' => 0, 'status' => 'ok']]]];
    $body = json_encode($event, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $timestamp = time();
    $headers = ['Content-Type: application/json', 'X-Webhook-Id: ' . $hookId,
        'X-Webhook-Event: ' . $event['event'], 'X-Webhook-Delivery: ' . $event['id'],
        'X-Webhook-Signature: t=' . $timestamp . ',v1=' . hash_hmac('sha256', $timestamp . '.' . $body, $secret)];
    [$status, $accepted] = test_http($app . '/api/webhook.php', 'POST', $body, $headers);
    check($status === 200 && json_decode($accepted, true)['duplicate'] === false, 'Anonymous signed callback accepted.');
    [$status, $accepted] = test_http($app . '/api/webhook.php', 'POST', $body, $headers);
    check($status === 200 && json_decode($accepted, true)['duplicate'] === true, 'Duplicate HTTP delivery acknowledged once.');
    check(test_http($app . '/api/webhook.php', 'POST', $body . ' ', $headers)[0] === 403, 'Tampered HTTP payload rejected.');
    check(test_http($app . '/api/webhook.php', 'POST', $body)[0] === 403, 'Unsigned callback rejected.');
    check(test_http($app . '/api/webhook.php')[0] === 405, 'Webhook is POST only.');
    [$status, $body] = test_http($app . '/status.php?runId=' . $runId, headers: $auth);
    $result = json_decode($body, true);
    check($status === 200 && $result['status'] === 'Ok' && $result['webhook'] === $event, 'Stored whole-pipeline result returned.');
    check(json_decode(file_get_contents($testDirectory . '/api-request.json'), true) === $request, 'Reading result does not call Zapq API.');
    $other = ['X-Zapq-Access-Token: test-only-token', 'X-Zapq-User-Id: ' . $hookId, 'X-Zapq-Internal-Api-Url: ' . $api . '/api/'];
    check(test_http($app . '/status.php?runId=' . $runId, headers: $other)[0] === 404, 'Other user cannot read result.');
} finally {
    foreach ($servers as $server) {
        proc_terminate($server);
        proc_close($server);
    }

    foreach (['state.sqlite', 'state.sqlite-journal', 'api-request.json', 'webhook.json', 'http.log'] as $name) {
        if (is_file($testDirectory . '/' . $name)) {
            unlink($testDirectory . '/' . $name);
        }
    }

    rmdir($testDirectory);
    putenv($originalDirectory === false ? 'ZAPQIO_APPLICATION_DATA_DIRECTORY' :
        'ZAPQIO_APPLICATION_DATA_DIRECTORY=' . $originalDirectory);
}
