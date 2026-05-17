<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$eventId = (int) ($_GET['event'] ?? 0);
$event = fetch_event_by_id($eventId);

if (!$event || !can_view_public_results($event)) {
    http_response_code(404);
    echo json_encode(['error' => 'Results not available.']);
    exit;
}

echo json_encode([
    'event' => [
        'id' => $event['id'],
        'title' => $event['title'],
        'status' => effective_event_status($event),
    ],
    'results' => compute_event_results($eventId),
    'snapshot' => latest_result_snapshot($eventId),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
