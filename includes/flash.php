<?php
declare(strict_types=1);

function flash(string $type, string $message): void
{
    $_SESSION['flash_messages'][] = [
        'type' => $type,
        'message' => $message,
    ];
}

function flash_errors(array $errors): void
{
    foreach ($errors as $error) {
        flash('error', $error);
    }
}

function pull_flashes(): array
{
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);

    return $messages;
}

function store_old_input(array $input): void
{
    unset($input['password'], $input['password_confirmation'], $input['csrf_token'], $input['token']);
    $_SESSION['old_input'] = $input;
}

function old_input(string $key, string $default = ''): string
{
    $value = $_SESSION['old_input'][$key] ?? $default;

    return is_string($value) ? $value : $default;
}

function clear_old_input(): void
{
    unset($_SESSION['old_input']);
}
