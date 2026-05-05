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
    if (get_site_setting('bypass_password_validation', get_test_setting('bypass_password_validation', false))) {
        return;
    }

    $minLen = (int) get_site_setting('password_min_length', 10);
    if (strlen($password) < $minLen) {
        $errors[] = 'Password must be at least ' . $minLen . ' characters.';
    }

    $complexityErrors = [];
    if (get_site_setting('password_require_uppercase', true) && !preg_match('/[A-Z]/', $password)) {
        $complexityErrors[] = 'uppercase';
    }
    if (get_site_setting('password_require_lowercase', true) && !preg_match('/[a-z]/', $password)) {
        $complexityErrors[] = 'lowercase';
    }
    if (get_site_setting('password_require_numbers', true) && !preg_match('/[0-9]/', $password)) {
        $complexityErrors[] = 'numeric';
    }
    if (get_site_setting('password_require_special', false) && !preg_match('/[^A-Za-z0-9]/', $password)) {
        $complexityErrors[] = 'special character';
    }

    if ($complexityErrors !== []) {
        $errors[] = 'Password must include ' . implode(', ', $complexityErrors) . ' characters.';
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
