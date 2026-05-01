<?php
declare(strict_types=1);

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/includes/helpers.php';
require_once BASE_PATH . '/includes/logger.php';
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/includes/flash.php';
require_once BASE_PATH . '/includes/csrf.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/audit.php';
require_once BASE_PATH . '/includes/sms.php';
require_once BASE_PATH . '/includes/email.php';
require_once BASE_PATH . '/includes/uploads.php';
require_once BASE_PATH . '/includes/validations.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name((string) app_config('session_name'));
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

ensure_upload_directories();
