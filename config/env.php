<?php
declare(strict_types=1);

function load_env_file(string $path): void
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (str_starts_with($line, 'export ')) {
            $line = trim(substr($line, 7));
        }

        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $key = trim($parts[0]);
        if ($key === '' || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key)) {
            continue;
        }

        if (getenv($key) !== false) {
            continue;
        }

        $value = trim($parts[1]);
        $firstChar = $value !== '' ? $value[0] : '';
        $lastChar = $value !== '' ? $value[strlen($value) - 1] : '';

        if (($firstChar === '"' && $lastChar === '"') || ($firstChar === "'" && $lastChar === "'")) {
            $value = substr($value, 1, -1);
            if ($firstChar === '"') {
                $value = strtr($value, [
                    '\n' => "\n",
                    '\r' => "\r",
                    '\t' => "\t",
                    '\"' => '"',
                    '\\\\' => '\\',
                ]);
            } else {
                $value = str_replace("\\'", "'", $value);
            }
        } else {
            $commentPos = strpos($value, ' #');
            if ($commentPos !== false) {
                $value = rtrim(substr($value, 0, $commentPos));
            }
        }

        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}
