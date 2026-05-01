<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$eventId = (int) ($_GET['event'] ?? 0);
$event = fetch_event_by_id($eventId);

if (!$event || !can_view_public_audit($event)) {
    http_response_code(404);
    echo json_encode(['error' => 'Audit feed not available.']);
    exit;
}

$rows = fetch_all(
    'SELECT public_receipt_hash, ballot_hash, submitted_at
     FROM ballots
     WHERE event_id = :event_id
     ORDER BY submitted_at DESC
     LIMIT 50',
    ['event_id' => $eventId]
);

echo json_encode(['rows' => $rows], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
