<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/zapq.php';

foreach (['', '../runs', 'https://example.invalid', "bad\nmethod"] as $method) {
    try {
        zapq_run_pipeline($method, '{}', 'test-key');
        throw new RuntimeException('Invalid method reached the API.');
    } catch (InvalidArgumentException $expected) {
    }
}

foreach (['', '{', "{'a':1}", '{"a":1} x', '[' . str_repeat('1,', 32768) . '1]'] as $input) {
    try {
        zapq_run_pipeline('demo', $input, 'test-key');
        throw new RuntimeException('Invalid JSON input reached the API.');
    } catch (InvalidArgumentException $expected) {
    }
}

foreach (['', "key\r\nInjected: header", 'key with spaces', str_repeat('a', 101)] as $key) {
    try {
        zapq_run_pipeline('demo', '{}', $key);
        throw new RuntimeException('Invalid idempotency key reached the API.');
    } catch (InvalidArgumentException $expected) {
    }
}

try {
    zapq_context();
    throw new RuntimeException('Missing gateway context was accepted.');
} catch (RuntimeException $expected) {
    check($expected->getMessage() === 'Open this application through the Zapq gateway.', 'Missing session error.');
}

$config = require dirname(__DIR__) . '/lib/config.php';
check(is_string($config['api_method']), 'Method is public configuration.');
check(json_validate($config['input_json']), 'Default input is valid JSON.');
