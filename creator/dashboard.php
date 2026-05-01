<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_role(['event_creator', 'super_admin']);
$user = current_user();

$events = has_role('super_admin')
    ? fetch_all(
        'SELECT events.*,
                (SELECT COUNT(*) FROM voter_event_submissions WHERE event_id = events.id) AS submission_count,
                (SELECT COUNT(*) FROM voter_event_submissions WHERE event_id = events.id AND status IN ("pending", "under_review")) AS review_count,
                (SELECT COUNT(*) FROM voting_tokens WHERE event_id = events.id AND status = "issued") AS issued_tokens,
                (SELECT COUNT(*) FROM ballots WHERE event_id = events.id) AS ballots_count,
                (SELECT COUNT(*) FROM candidates_or_options WHERE event_id = events.id AND is_active = 1) AS candidate_count,
                (SELECT COUNT(*) FROM event_required_fields WHERE event_id = events.id) AS field_count,
                (SELECT COUNT(*) FROM verification_methods WHERE event_id = events.id AND is_active = 1) AS verification_method_count
         FROM events
         ORDER BY events.updated_at DESC'
    )
    : fetch_all(
        'SELECT events.*,
                (SELECT COUNT(*) FROM voter_event_submissions WHERE event_id = events.id) AS submission_count,
                (SELECT COUNT(*) FROM voter_event_submissions WHERE event_id = events.id AND status IN ("pending", "under_review")) AS review_count,
                (SELECT COUNT(*) FROM voting_tokens WHERE event_id = events.id AND status = "issued") AS issued_tokens,
                (SELECT COUNT(*) FROM ballots WHERE event_id = events.id) AS ballots_count,
                (SELECT COUNT(*) FROM candidates_or_options WHERE event_id = events.id AND is_active = 1) AS candidate_count,
                (SELECT COUNT(*) FROM event_required_fields WHERE event_id = events.id) AS field_count,
                (SELECT COUNT(*) FROM verification_methods WHERE event_id = events.id AND is_active = 1) AS verification_method_count
         FROM events
         INNER JOIN event_admins ON event_admins.event_id = events.id
         WHERE event_admins.user_id = :user_id
         ORDER BY events.updated_at DESC',
        ['user_id' => $user['id']]
    );

$pageTitle = 'Creator Dashboard';
$pageHeading = 'Creator Dashboard';
$pageDescription = 'Manage events, verification queues, token issuance, and result publication.';
$isDashboard = true;
$sidebarContext = current_role_slug() ?? 'event_creator';
$activeSidebar = 'creator-dashboard';

include dirname(__DIR__) . '/includes/header.php';
?>
<section class="stats-grid">
    <article class="stat-box">
        <strong><?= e((string) count($events)); ?></strong>
        <p>Managed events</p>
    </article>
    <article class="stat-box">
        <strong><?= e((string) array_sum(array_map(static fn(array $event): int => (int) $event['review_count'], $events))); ?></strong>
        <p>Submissions under review</p>
    </article>
    <article class="stat-box">
        <strong><?= e((string) array_sum(array_map(static fn(array $event): int => (int) $event['issued_tokens'], $events))); ?></strong>
        <p>Active issued tokens</p>
    </article>
    <article class="stat-box">
        <strong><?= e((string) array_sum(array_map(static fn(array $event): int => (int) $event['ballots_count'], $events))); ?></strong>
        <p>Recorded ballots</p>
    </article>
</section>

<section class="panel">
    <div class="section-head">
        <div>
            <span class="eyebrow">Events</span>
            <h2>Manage your elections</h2>
        </div>
        <a class="button button--primary" href="<?= e(base_url('/creator/event_form.php')); ?>">Create event</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Event</th>
                <th>Status</th>
                <th>Setup</th>
                <th>Submissions</th>
                <th>Tokens</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$events): ?>
                <tr><td colspan="6">No managed events found.</td></tr>
            <?php else: ?>
                <?php foreach ($events as $event): ?>
                    <?php
                    $isReady = (int) $event['candidate_count'] >= 2
                        && (int) $event['field_count'] > 0
                        && (int) $event['verification_method_count'] > 0;
                    ?>
                    <tr>
                        <td>
                            <strong><?= e($event['title']); ?></strong>
                            <p><?= e(format_datetime($event['start_at'], 'M j, Y H:i')); ?> to <?= e(format_datetime($event['end_at'], 'M j, Y H:i')); ?></p>
                        </td>
                        <td><span class="badge <?= e(badge_class($event['status'])); ?>"><?= e(format_status($event['status'])); ?></span></td>
                        <td>
                            <div class="pill-row">
                                <span class="badge <?= $isReady ? 'badge-success' : 'badge-warning'; ?>"><?= $isReady ? 'Ready' : 'Incomplete'; ?></span>
                                <span class="badge badge-muted"><?= e((string) $event['candidate_count']); ?> candidates</span>
                                <span class="badge badge-muted"><?= e((string) $event['field_count']); ?> fields</span>
                                <span class="badge badge-muted"><?= e((string) $event['verification_method_count']); ?> methods</span>
                            </div>
                        </td>
                        <td>
                            <strong><?= e((string) $event['submission_count']); ?> submissions</strong>
                            <p><?= e((string) $event['review_count']); ?> need review • <?= e((string) $event['ballots_count']); ?> ballots cast</p>
                        </td>
                        <td><?= e((string) $event['issued_tokens']); ?> active tokens</td>
                        <td class="table-actions">
                            <a href="<?= e(base_url('/creator/event_form.php?event=' . $event['id'])); ?>">Settings</a>
                            <a href="<?= e(base_url('/creator/verification_methods.php?event=' . $event['id'])); ?>">Methods</a>
                            <a href="<?= e(base_url('/creator/verifications.php?event=' . $event['id'])); ?>">Verifications</a>
                            <a href="<?= e(base_url('/creator/tokens.php?event=' . $event['id'])); ?>">Tokens</a>
                            <a href="<?= e(base_url('/creator/results.php?event=' . $event['id'])); ?>">Results</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
