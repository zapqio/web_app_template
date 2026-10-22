<?php
declare(strict_types=1);

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$root = dirname(__DIR__);
foreach (['pdo_sqlite', 'curl'] as $extension) {
    check(extension_loaded($extension), 'Missing PHP extension: ' . $extension);
}

foreach (['public/index.php', 'public/run.php', 'public/status.php', 'public/api/webhook.php',
    'lib/database.php', 'lib/zapq.php', 'lib/webhooks.php', 'lib/config.php',
    'README.md', 'docs/agent.md', '.github/workflows/check.yml', '.gitlab-ci.yml'] as $path) {
    check(is_file($root . '/' . $path), 'Missing template file: ' . $path);
}

$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
$linted = 0;
foreach ($files as $file) {
    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
    if (str_starts_with($relative, '.git/')) {
        continue;
    }

    check(!preg_match('/\.(sqlite(?:-.*)?|db)$/i', $relative) &&
        !preg_match('~^public/(?:lib|tests|docs|data)/~', $relative) && $file->getFilename() !== 'webhook.json',
        'Private data in template: ' . $relative);
    if ($file->getExtension() === 'php') {
        passthru(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file->getPathname()), $status);
        check($status === 0, 'PHP lint: ' . $relative);
        $linted++;
    }
}

require __DIR__ . '/contracts.php';
require __DIR__ . '/webhooks.php';
require __DIR__ . '/http.php';
echo "Template OK: $linted PHP files linted; API, webhook, persistence and HTTP checks passed.\n";
