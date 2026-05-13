<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_role(['co_admin', 'super_admin']);

$events = has_role('super_admin')
    ? fetch_all(
        'SELECT events.*,
                (SELECT COUNT(*) FROM voter_event_submissions WHERE event_id = events.id AND status IN ("pending", "under_review")) AS review_count
         FROM events
         ORDER BY events.updated_at DESC'
    )
    : fetch_all(
        'SELECT events.*, co_admins.permissions_json,
                (SELECT COUNT(*) FROM voter_event_submissions WHERE event_id = events.id AND status IN ("pending", "under_review")) AS review_count
         FROM co_admins
         INNER JOIN events ON events.id = co_admins.event_id
         WHERE co_admins.user_id = :user_id AND co_admins.is_active = 1
         ORDER BY events.updated_at DESC',
        ['user_id' => current_user()['id']]
    );

$pageTitle = 'Co-Admin Dashboard';
$pageHeading = 'Co-Admin Dashboard';
$pageDescription = 'Work on assigned events with limited configurable permissions.';
$isDashboard = true;
$sidebarContext = 'co_admin';
$activeSidebar = 'coadmin-dashboard';

include dirname(__DIR__) . '/includes/header.php';
?>
<section class="panel">
    <span class="eyebrow">Assigned Events</span>
    <h2>Operational scope</h2>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Event</th>
                <th>Status</th>
                <th>Queue</th>
                <th>Permissions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($events as $event): ?>
                <?php $permissions = json_decode((string) ($event['permissions_json'] ?? '{}'), true) ?: []; ?>
                <tr>
                    <td>
                        <strong><?= e($event['title']); ?></strong>
                        <p><?= e(format_datetime($event['start_at'], 'M j, Y H:i')); ?></p>
                    </td>
                    <td><span class="badge <?= e(badge_class($event['status'])); ?>"><?= e(format_status($event['status'])); ?></span></td>
                    <td><?= e((string) $event['review_count']); ?> awaiting review</td>
                    <td>
                        <div class="pill-row">
                            <?php foreach ($permissions as $permission => $granted): ?>
                                <?php if ($granted): ?>
                                    <span class="badge badge-success"><?= e(format_status($permission)); ?></span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        <div class="table-actions" style="margin-top:8px;">
                            <a href="<?= e(base_url('/event.php?event=' . $event['id'])); ?>">View</a>
                            <?php if (!empty($permissions['review_verifications'])): ?>
                                <a href="<?= e(base_url('/creator/verifications.php?event=' . $event['id'])); ?>">Verifications</a>
                            <?php endif; ?>
                            <?php if (!empty($permissions['manage_candidates'])): ?>
                                <a href="<?= e(base_url('/creator/candidates.php?event=' . $event['id'])); ?>">Candidates</a>
                            <?php endif; ?>
                            <?php if (!empty($permissions['manage_fields'])): ?>
                                <a href="<?= e(base_url('/creator/required_fields.php?event=' . $event['id'])); ?>">Fields</a>
                            <?php endif; ?>
                            <?php if (!empty($permissions['issue_tokens'])): ?>
                                <a href="<?= e(base_url('/creator/tokens.php?event=' . $event['id'])); ?>">Tokens</a>
                            <?php endif; ?>
                            <?php if (!empty($permissions['view_results'])): ?>
                                <a href="<?= e(base_url('/creator/results.php?event=' . $event['id'])); ?>">Results</a>
                            <?php endif; ?>
                            <?php if (empty($permissions)): ?>
                                <a href="<?= e(base_url('/creator/verifications.php?event=' . $event['id'])); ?>">Open tools</a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
