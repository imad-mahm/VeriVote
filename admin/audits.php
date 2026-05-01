<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_role('super_admin');

$logs = fetch_all(
    'SELECT audit_logs.*, users.full_name, events.title AS event_title
     FROM audit_logs
     LEFT JOIN users ON users.id = audit_logs.actor_user_id
     LEFT JOIN events ON events.id = audit_logs.event_id
     ORDER BY audit_logs.id DESC
     LIMIT 300'
);

$pageTitle = 'Platform Audit Trail';
$pageHeading = 'Platform Audit Trail';
$pageDescription = 'Review recent privileged actions across the Verivote platform.';
$isDashboard = true;
$sidebarContext = 'super_admin';
$activeSidebar = 'admin-audits';

include dirname(__DIR__) . '/includes/header.php';
?>
<section class="table-wrap">
    <table>
        <thead>
        <tr>
            <th>When</th>
            <th>Actor</th>
            <th>Event</th>
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
                <td><?= e($log['event_title'] ?: 'Platform'); ?></td>
                <td><?= e($log['action_type']); ?></td>
                <td><?= e($log['description']); ?></td>
                <td><span class="badge badge-muted"><?= e(substr($log['entry_hash'], 0, 16)); ?>...</span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
