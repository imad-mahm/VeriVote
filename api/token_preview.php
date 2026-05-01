<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$eventId = (int) ($_REQUEST['event'] ?? 0);
$token = trim((string) ($_REQUEST['token'] ?? ''));
$event = fetch_event_by_id($eventId);

if (!$event || $token === '') {
    http_response_code(422);
    echo json_encode(['valid' => false, 'message' => 'Missing event or token.']);
    exit;
}

$limit = consume_rate_limit(
    'api-token-preview',
    $eventId . '|' . client_ip(),
    (int) app_config('security.token_attempts'),
    (int) app_config('security.token_window_seconds')
);

if (!$limit['allowed']) {
    http_response_code(429);
    echo json_encode(['valid' => false, 'message' => 'Too many attempts. Try again later.']);
    exit;
}

$tokenRow = validate_voting_token($eventId, $token);

if (
    !$tokenRow
    || $tokenRow['status'] !== 'issued'
    || $tokenRow['submission_status'] !== 'approved'
    || $tokenRow['revoked_at'] !== null
    || $tokenRow['used_at'] !== null
    || new DateTimeImmutable($tokenRow['expires_at']) < new DateTimeImmutable('now')
    || !event_is_active($event)
) {
    echo json_encode(['valid' => false, 'message' => 'Token is invalid or not approved.']);
    exit;
}

clear_rate_limit('api-token-preview', $eventId . '|' . client_ip());

echo json_encode([
    'valid' => true,
    'event' => $event['title'],
    'expires_at' => $tokenRow['expires_at'],
    'token_reference' => $tokenRow['token_reference'],
]);
