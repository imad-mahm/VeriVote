<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

// Permanently moved to verify-phone.php — redirect preserving query string
http_response_code(301);
redirect('/auth/verify-phone.php' . ($_SERVER['QUERY_STRING'] !== '' ? '?' . $_SERVER['QUERY_STRING'] : ''));
