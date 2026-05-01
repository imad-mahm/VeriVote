<?php
declare(strict_types=1);

function activity_log_path(): string
{
    $dir = BASE_PATH . '/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    return $dir . '/app.log';
}

function log_activity(string $event, array $context = [], string $level = 'INFO'): void
{
    $path = activity_log_path();

    $userId = null;
    if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['user_id'])) {
        $userId = (int) $_SESSION['user_id'];
    }

    $ip = '';
    if (function_exists('client_ip')) {
        $ip = (string) client_ip();
    } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
        $ip = (string) $_SERVER['REMOTE_ADDR'];
    }

    $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
    $requestUri = $_SERVER['REQUEST_URI'] ?? '-';

    $contextJson = '';
    if ($context !== []) {
        $contextJson = ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    $line = sprintf(
        "[%s] [%s] [uid=%s ip=%s %s %s] %s%s\n",
        date('Y-m-d H:i:s'),
        strtoupper($level),
        $userId ?? '-',
        $ip !== '' ? $ip : '-',
        $requestMethod,
        $requestUri,
        $event,
        $contextJson
    );

    $maxSize = (int) app_config('logging.max_file_bytes', 5 * 1024 * 1024);
    if ($maxSize > 0 && is_file($path) && filesize($path) >= $maxSize) {
        @rename($path, $path . '.' . date('Ymd_His'));
    }

    @file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
}
