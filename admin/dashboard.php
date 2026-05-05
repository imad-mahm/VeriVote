<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_role('super_admin');

$stats = [
    'users' => (int) (fetch_one('SELECT COUNT(*) AS aggregate FROM users')['aggregate'] ?? 0),
    'events' => (int) (fetch_one('SELECT COUNT(*) AS aggregate FROM events')['aggregate'] ?? 0),
    'pending_verifications' => (int) (fetch_one('SELECT COUNT(*) AS aggregate FROM voter_verifications WHERE status = "pending"')['aggregate'] ?? 0),
    'ballots' => (int) (fetch_one('SELECT COUNT(*) AS aggregate FROM ballots')['aggregate'] ?? 0),
];

$recentEvents = fetch_all(
    'SELECT events.*, users.full_name AS creator_name
     FROM events
     INNER JOIN users ON users.id = events.created_by
     ORDER BY events.updated_at DESC
     LIMIT 8'
);
$recentAudits = fetch_all(
    'SELECT audit_logs.*, users.full_name
     FROM audit_logs
     LEFT JOIN users ON users.id = audit_logs.actor_user_id
     ORDER BY audit_logs.id DESC
     LIMIT 12'
);

$pageTitle = 'Super Admin Dashboard';
$pageHeading = 'Super Admin Dashboard';
$pageDescription = 'Monitor the full platform: events, privileged access, verification volume, and audit activity.';
$isDashboard = true;
$sidebarContext = 'super_admin';
$activeSidebar = 'admin-dashboard';

include dirname(__DIR__) . '/includes/header.php';
?>
<div class="stat-strip">
    <div class="stat-strip__item">
        <strong><?= e((string) $stats['users']); ?></strong>
        <p>User accounts</p>
    </div>
    <div class="stat-strip__item">
        <strong><?= e((string) $stats['events']); ?></strong>
        <p>Events</p>
    </div>
    <div class="stat-strip__item">
        <strong><?= e((string) $stats['pending_verifications']); ?></strong>
        <p>Pending verifications</p>
    </div>
    <div class="stat-strip__item">
        <strong><?= e((string) $stats['ballots']); ?></strong>
        <p>Recorded ballots</p>
    </div>
</div>

<section class="grid-2">
    <article class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Event</th>
                <th>Status</th>
                <th>Creator</th>
                <th>Links</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($recentEvents as $event): ?>
                <tr>
                    <td>
                        <strong><?= e($event['title']); ?></strong>
                        <p><?= e(format_datetime($event['start_at'], 'M j, Y H:i')); ?></p>
                    </td>
                    <td><span class="badge <?= e(badge_class($event['status'])); ?>"><?= e(format_status($event['status'])); ?></span></td>
                    <td><?= e($event['creator_name']); ?></td>
                    <td class="table-actions">
                        <a href="<?= e(base_url('/creator/event_form.php?event=' . $event['id'])); ?>">Settings</a>
                        <a href="<?= e(base_url('/creator/audit_logs.php?event=' . $event['id'])); ?>">Audit</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </article>

    <article class="list-shell">
        <span class="eyebrow">Recent Audit Activity</span>
        <h2>Latest privileged actions</h2>
        <?php foreach ($recentAudits as $log): ?>
            <div class="list-row">
                <div>
                    <strong><?= e($log['action_type']); ?></strong>
                    <p><?= e(($log['full_name'] ?: 'Anonymous / system') . ' • ' . $log['description']); ?></p>
                </div>
                <span class="badge badge-muted"><?= e(format_datetime($log['created_at'], 'M j H:i')); ?></span>
            </div>
        <?php endforeach; ?>
    </article>
</section>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
