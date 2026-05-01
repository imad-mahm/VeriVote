<?php
declare(strict_types=1);

function validate_required(string $label, mixed $value, array &$errors): void
{
    if ($value === null || trim((string) $value) === '') {
        $errors[] = $label . ' is required.';
    }
}

function validate_email_input(string $label, mixed $value, array &$errors): void
{
    if (!filter_var((string) $value, FILTER_VALIDATE_EMAIL)) {
        $errors[] = $label . ' must be a valid email address.';
    }
}

function validate_phone_input(string $label, mixed $value, array &$errors): ?string
{
    $normalized = normalize_phone_number((string) $value);

    if ($normalized === null) {
        $errors[] = $label . ' must be a valid phone number.';
    }

    return $normalized;
}

function validate_password_input(string $password, array &$errors): void
{
    if (get_test_setting('bypass_password_validation', false)) {
        return;
    }

    if (strlen($password) < 10) {
        $errors[] = 'Password must be at least 10 characters.';
    }

    if (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must include uppercase, lowercase, and numeric characters.';
    }
}

function validate_datetime_range(string $startAt, string $endAt, array &$errors): void
{
    try {
        $start = new DateTimeImmutable($startAt);
        $end = new DateTimeImmutable($endAt);

        if ($end <= $start) {
            $errors[] = 'End date must be later than start date.';
        }
    } catch (Throwable) {
        $errors[] = 'Please provide valid start and end date/time values.';
    }
}
