<?php

/**
 * Charge un fichier .env simple (KEY=VALUE).
 */
function loadEnvFile(string $filePath): void
{
    if (!is_readable($filePath)) {
        return;
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }

        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $key = trim($parts[0]);
        $value = trim($parts[1]);

        if ($key === '') {
            continue;
        }

        if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') || (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
            $value = substr($value, 1, -1);
        }

        $_ENV[$key] = $value;
        putenv($key . '=' . $value);
    }
}

function envValue(string $key, string $default = ''): string
{
    $value = $_ENV[$key] ?? getenv($key);
    if ($value === false || $value === null || $value === '') {
        return $default;
    }

    return (string) $value;
}

loadEnvFile(__DIR__ . '/.env');

function getDatabaseConfig(): array
{
    return [
        'host' => envValue('DB_HOST', 'localhost'),
        'name' => envValue('DB_NAME', 'maisonmelinot'),
        'user' => envValue('DB_USER', 'root'),
        'pass' => envValue('DB_PASS', ''),
        'charset' => envValue('DB_CHARSET', 'utf8mb4'),
    ];
}

