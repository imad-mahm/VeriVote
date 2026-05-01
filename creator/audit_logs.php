<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$eventId = (int) ($_GET['event'] ?? 0);
$event = fetch_event_by_id($eventId);

if (!$event) {
    flash('error', 'Event not found.');
    redirect('/creator/dashboard.php');
}

require_event_permission($eventId, 'view_results');

$logs = fetch_all(
    'SELECT audit_logs.*, users.full_name
     FROM audit_logs
     LEFT JOIN users ON users.id = audit_logs.actor_user_id
     WHERE audit_logs.event_id = :event_id
     ORDER BY audit_logs.id DESC
     LIMIT 200',
    ['event_id' => $eventId]
);

$pageTitle = 'Audit Logs';
$pageHeading = 'Audit Logs';
$pageDescription = 'Review the event-specific tamper-evident audit chain.';
$isDashboard = true;
$sidebarContext = current_role_slug() ?? 'event_creator';
$activeEventTool = 'audit-log';
$eventContextId = $eventId;

include dirname(__DIR__) . '/includes/header.php';
?>
<section class="table-wrap">
    <table>
        <thead>
        <tr>
            <th>When</th>
            <th>Actor</th>
            <th>Action</th>
            <th>Description</th>
            <th>Hash</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($logs as $log): ?>
            <tr>
                <td><?= e(format_datetime($log['created_at'], 'M j, Y H:i')); ?></td>
                <td><?= e($log['full_name'] ?: 'Anonymous / system'); ?></td>
                <td><?= e($log['action_type']); ?></td>
                <td>
                    <strong><?= e($log['description']); ?></strong>
                    <p><?= e($log['target_table'] . '#' . $log['target_id']); ?></p>
                </td>
                <td><span class="badge badge-muted"><?= e(substr($log['entry_hash'], 0, 18)); ?>...</span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
