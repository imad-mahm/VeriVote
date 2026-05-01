<?php
declare(strict_types=1);

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

require_once BASE_PATH . '/config/env.php';
load_env_file(BASE_PATH . '/.env');

$appConfig = [
    'app_name' => getenv('APP_NAME') ?: 'Verivote',
    'app_env' => getenv('APP_ENV') ?: 'development',
    'app_url' => rtrim((string) (getenv('APP_URL') ?: ''), '/'),
    'app_key' => getenv('APP_KEY') ?: 'verivote-dev-change-this-key-before-production',
    'timezone' => getenv('APP_TIMEZONE') ?: 'UTC',
    'session_name' => getenv('SESSION_NAME') ?: 'VERIVOTESESSID',
    'db' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: '3306',
        'name' => getenv('DB_NAME') ?: 'verivote',
        'user' => getenv('DB_USER') ?: 'root',
        'pass' => getenv('DB_PASS') ?: '',
        'charset' => 'utf8mb4',
    ],
    'uploads' => [
        'base_dir' => BASE_PATH . '/uploads',
        'max_file_size' => 5 * 1024 * 1024,
        'allowed_mimes' => [
            'image/jpeg',
            'image/png',
            'application/pdf',
        ],
    ],
    'security' => [
        'login_attempts' => 6,
        'login_window_seconds' => 900,
        'token_attempts' => 10,
        'token_window_seconds' => 900,
        'code_attempts' => 6,
        'code_window_seconds' => 900,
        'token_expiry_hours' => 24,
        'verification_code_expiry_minutes' => 15,
        'password_reset_expiry_minutes' => 30,
    ],
    'notifications' => [
        'from_email' => getenv('NOTIFY_FROM_EMAIL') ?: 'noreply@verivote.test',
        'from_name' => 'Verivote',
    ],
    'email' => [
        'enabled' => filter_var(getenv('EMAIL_ENABLED') ?: '0', FILTER_VALIDATE_BOOLEAN),
        'provider' => getenv('EMAIL_PROVIDER') ?: 'gmail_smtp',
        'retry_attempts' => max(1, (int) (getenv('EMAIL_RETRY_ATTEMPTS') ?: 2)),
        'retry_delay_ms' => max(0, (int) (getenv('EMAIL_RETRY_DELAY_MS') ?: 750)),
        'timeout_seconds' => max(3, (int) (getenv('EMAIL_TIMEOUT_SECONDS') ?: 15)),
        'gmail_smtp' => [
            'host' => getenv('SMTP_HOST') ?: 'smtp.gmail.com',
            'port' => max(1, (int) (getenv('SMTP_PORT') ?: 587)),
            'username' => getenv('SMTP_USERNAME') ?: '',
            'password' => getenv('SMTP_PASSWORD') ?: '',
            'encryption' => strtolower((string) (getenv('SMTP_ENCRYPTION') ?: 'starttls')),
            'from_email' => getenv('SMTP_FROM_EMAIL') ?: (getenv('NOTIFY_FROM_EMAIL') ?: ''),
            'from_name' => getenv('SMTP_FROM_NAME') ?: 'Verivote',
            'reply_to' => getenv('SMTP_REPLY_TO') ?: '',
        ],
    ],
    'phone' => [
        'default_country_code' => preg_replace('/\D+/', '', (string) (getenv('PHONE_DEFAULT_COUNTRY_CODE') ?: '961')) ?: '961',
    ],
    'sms' => [
        'enabled' => filter_var(getenv('SMS_ENABLED') ?: '1', FILTER_VALIDATE_BOOLEAN),
        'provider' => getenv('SMS_PROVIDER') ?: 'easy_sendsms',
        'sender_id' => getenv('SMS_SENDER_ID') ?: 'Verivote',
        'fallback_to_email' => filter_var(getenv('SMS_FALLBACK_TO_EMAIL') ?: '1', FILTER_VALIDATE_BOOLEAN),
        'retry_attempts' => max(1, (int) (getenv('SMS_RETRY_ATTEMPTS') ?: 3)),
        'retry_delay_ms' => max(0, (int) (getenv('SMS_RETRY_DELAY_MS') ?: 750)),
        'timeout_seconds' => max(3, (int) (getenv('SMS_TIMEOUT_SECONDS') ?: 15)),
        'templates' => [
            'account_verification' => getenv('SMS_TEMPLATE_ACCOUNT_VERIFICATION') ?: 'Verivote code: {code}. Expires in {minutes} min. [AR] Ramz tahaqoq Verivote: {code}. Saleh li muddat {minutes} daqiqa.',
            'event_verification' => getenv('SMS_TEMPLATE_EVENT_VERIFICATION') ?: 'Verification code for {event_title}: {code}. Expires in {minutes} min. [AR] Ramz tahaqoq li fa3aliat {event_title}: {code}. Saleh li muddat {minutes} daqiqa.',
            'token_delivery' => getenv('SMS_TEMPLATE_TOKEN_DELIVERY') ?: 'Your voting token for {event_title}: {token}. Expires at {expires_at}. [AR] Ramz altaswit li fa3aliat {event_title}: {token}. Yantahi fi {expires_at}.',
        ],
        'easy_sendsms' => [
            'base_url' => rtrim((string) (getenv('EASYSENDSMS_BASE_URL') ?: 'https://restapi.easysendsms.app'), '/'),
            'send_path' => getenv('EASYSENDSMS_SEND_PATH') ?: '/v1/rest/sms/send',
            'auth_type' => strtolower((string) (getenv('EASYSENDSMS_AUTH_TYPE') ?: 'apikey')),
            'token' => getenv('EASYSENDSMS_TOKEN') ?: '',
            'api_key' => getenv('EASYSENDSMS_API_KEY') ?: '',
            'api_secret' => getenv('EASYSENDSMS_API_SECRET') ?: '',
            'sender_id' => getenv('EASYSENDSMS_SENDER_ID') ?: '',
        ],
    ],
];

date_default_timezone_set($appConfig['timezone']);
