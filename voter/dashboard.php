<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_login();

if (!has_role(['voter', 'super_admin'])) {
    redirect(dashboard_home_for_role((string) current_role_slug()));
}

$user          = current_user();
$submissions   = fetch_all(
    'SELECT ves.*, events.title AS event_title, events.slug, events.status AS event_status
     FROM voter_event_submissions ves
     INNER JOIN events ON events.id = ves.event_id
     WHERE ves.user_id = :user_id
     ORDER BY ves.id DESC',
    ['user_id' => $user['id']]
);
$tokens        = fetch_all(
    'SELECT vt.*, events.title AS event_title
     FROM voting_tokens vt
     INNER JOIN events ON events.id = vt.event_id
     INNER JOIN voter_event_submissions ves ON ves.id = vt.submission_id
     WHERE ves.user_id = :user_id
     ORDER BY vt.id DESC',
    ['user_id' => $user['id']]
);
$notifications = fetch_all(
    'SELECT * FROM notifications WHERE user_id = :user_id ORDER BY id DESC LIMIT 8',
    ['user_id' => $user['id']]
);

$pageTitle       = 'Voter Dashboard';
$pageHeading     = 'Voter Dashboard';
$pageDescription = 'Track your registrations, verification steps, and ballot credentials.';
$isDashboard     = true;
$sidebarContext  = 'voter';
$activeSidebar   = 'voter-dashboard';

include dirname(__DIR__) . '/includes/header.php';
?>
<?php if (empty($user['phone_verified_at'])): ?>
    <div class="alert alert--warning">
        Phone unverified. <a href="<?= e(base_url('/auth/verify-phone.php')); ?>" style="color:inherit;text-decoration:underline;">Verify now</a> to unlock event registration.
    </div>
<?php endif; ?>

<div class="stat-strip">
    <div class="stat-strip__item">
        <strong><?= e((string) count($submissions)); ?></strong>
        <p>Event submissions</p>
    </div>
    <div class="stat-strip__item">
        <strong><?= e((string) count(array_filter($submissions, static fn(array $item): bool => $item['status'] === 'approved'))); ?></strong>
        <p>Approved</p>
    </div>
    <div class="stat-strip__item">
        <strong><?= e((string) count(array_filter($tokens, static fn(array $item): bool => $item['status'] === 'issued'))); ?></strong>
        <p>Active tokens</p>
    </div>
    <div class="stat-strip__item">
        <strong><?= e((string) count(array_filter($tokens, static fn(array $item): bool => $item['status'] === 'used'))); ?></strong>
        <p>Ballots cast</p>
    </div>
</div>

<section class="grid-2">
    <article class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Event</th>
                    <th>Status</th>
                    <th>Submitted</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$submissions): ?>
                    <tr><td colspan="4">No event submissions yet. <a href="<?= e(base_url('/events.php')); ?>" class="table-actions" style="display:inline;">Browse elections</a></td></tr>
                <?php else: ?>
                    <?php foreach ($submissions as $submission): ?>
                        <tr>
                            <td>
                                <strong><?= e($submission['event_title']); ?></strong>
                                <p><?= e($submission['submission_reference']); ?></p>
                            </td>
                            <td><span class="badge <?= e(badge_class($submission['status'])); ?>"><?= e(format_status($submission['status'])); ?></span></td>
                            <td><?= e(format_datetime($submission['submitted_at'], 'M j, Y')); ?></td>
                            <td class="table-actions"><a href="<?= e(base_url('/voter/register_event.php?event=' . $submission['event_id'])); ?>">Open</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </article>

    <article class="panel">
        <span class="eyebrow">Quick actions</span>
        <h2>Continue</h2>
        <div class="list-shell list-shell--bare">
            <div class="list-row">
                <div>
                    <strong>Register for an event</strong>
                    <p>Browse active or scheduled public events and submit your voter information.</p>
                </div>
                <a class="button button--primary" href="<?= e(base_url('/events.php')); ?>">Browse</a>
            </div>
            <div class="list-row">
                <div>
                    <strong>Cast a vote</strong>
                    <p>Use your one-time token when the event is active.</p>
                </div>
                <a class="button button--ghost" href="<?= e(base_url('/voter/cast_vote.php')); ?>">Vote</a>
            </div>
            <div class="list-row">
                <div>
                    <strong>Verify a receipt</strong>
                    <p>Confirm your ballot was recorded without exposing your identity.</p>
                </div>
                <a class="button button--ghost" href="<?= e(base_url('/voter/verify_vote.php')); ?>">Verify</a>
            </div>
        </div>
    </article>
</section>

<section class="grid-2">
    <article class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Token</th>
                    <th>Event</th>
                    <th>Status</th>
                    <th>Expiry</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$tokens): ?>
                    <tr><td colspan="4">No voting tokens issued yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($tokens as $token): ?>
                        <tr>
                            <td>
                                <strong style="font-family:ui-monospace,monospace;font-size:0.82rem;"><?= e($token['token_reference']); ?></strong>
                                <p>ends &middot;<?= e($token['token_last4']); ?></p>
                            </td>
                            <td><?= e($token['event_title']); ?></td>
                            <td><span class="badge <?= e(badge_class($token['status'])); ?>"><?= e(format_status($token['status'])); ?></span></td>
                            <td><?= e(format_datetime($token['expires_at'], 'M j, Y H:i')); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </article>

    <article class="list-shell">
        <span class="eyebrow">Notifications</span>
        <h2>Recent messages</h2>
        <?php if (!$notifications): ?>
            <p>No notifications found.</p>
        <?php else: ?>
            <?php foreach ($notifications as $notification): ?>
                <div class="list-row">
                    <div>
                        <strong><?= e($notification['subject']); ?></strong>
                        <p><?= e($notification['destination']); ?></p>
                    </div>
                    <span class="badge <?= e(badge_class($notification['status'])); ?>"><?= e(format_status($notification['status'])); ?></span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </article>
</section>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
