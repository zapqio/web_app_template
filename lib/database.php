<?php
declare(strict_types=1);

function data_directory(): string
{
    $directory = getenv('ZAPQIO_APPLICATION_DATA_DIRECTORY');
    if ($directory === false || $directory === '') {
        throw new RuntimeException('Application data directory is not configured.');
    }

    return $directory;
}

function database(): PDO
{
    $database = new PDO('sqlite:' . data_directory() . '/state.sqlite');
    $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $database->exec('PRAGMA busy_timeout = 5000');
    $database->exec('PRAGMA synchronous = FULL');
    return $database;
}
