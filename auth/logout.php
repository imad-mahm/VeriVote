<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

logout_user();
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name((string) app_config('session_name'));
    session_start();
}
flash('success', 'You have been signed out.');
redirect('/auth/login.php');
