<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$eventId = (int) ($_GET['event'] ?? $_POST['event_id'] ?? 0);
$event = fetch_event_by_id($eventId);

if (!$event) {
    flash('error', 'Event not found.');
    redirect('/creator/dashboard.php');
}

require_event_permission($eventId, 'issue_tokens');

if (is_post_request()) {
    verify_csrf_or_fail();
    $action = (string) ($_POST['action'] ?? '');
    $actor = current_user();

    $channel = in_array($_POST['channel'] ?? '', ['sms', 'email'], true) ? (string) $_POST['channel'] : 'sms';

    if ($action === 'issue') {
        $submissionId = (int) ($_POST['submission_id'] ?? 0);
        $submission = fetch_submission_by_id($submissionId);

        if ($submission && $submission['status'] === 'approved') {
            try {
                $issued = issue_voting_token($eventId, $submissionId, (int) $actor['id'], $channel);
                $delivery = deliver_voting_token($event, $submission, $issued, $channel);
                write_audit_log('token_issued', 'voting_tokens', (string) $issued['id'], 'Voting token issued.', $eventId, [
                    'submission_id' => $submissionId,
                    'delivery_channel' => $delivery['channel'],
                    'fallback_used' => $delivery['fallback_used'],
                ]);

                if (!$delivery['success']) {
                    flash('warning', 'Token created for ' . $submission['submission_reference'] . ' but could not be delivered via SMS or email. The voter will need a reissue when delivery is working.');
                } else {
                    $channelLabel = $delivery['fallback_used'] ? 'email fallback' : 'SMS';
                    flash('success', 'Token issued and delivered via ' . $channelLabel . ' to ' . $submission['submission_reference'] . '.');
                }
            } catch (Throwable $exception) {
                log_activity('token.issue_error', ['event_id' => $eventId, 'submission_id' => $submissionId, 'error' => $exception->getMessage()], 'ERROR');
                flash('error', 'Could not issue token — ' . $exception->getMessage());
            }
        }
    }

    if ($action === 'issue_all') {
        $pending = fetch_all(
            'SELECT ves.*, users.full_name, users.email, users.phone, users.phone_verified_at
             FROM voter_event_submissions ves
             INNER JOIN users ON users.id = ves.user_id
             WHERE ves.event_id = :event_id AND ves.status = "approved"',
            ['event_id' => $eventId]
        );

        $issued_count = 0;
        $failed_count = 0;
        $skipped_count = 0;

        foreach ($pending as $submission) {
            if (active_token_for_submission((int) $submission['id'])) {
                $skipped_count++;
                continue;
            }
            try {
                $issued = issue_voting_token($eventId, (int) $submission['id'], (int) $actor['id'], $channel);
                deliver_voting_token($event, $submission, $issued, $channel);
                write_audit_log('token_issued', 'voting_tokens', (string) $issued['id'], 'Voting token issued (bulk).', $eventId, [
                    'submission_id' => $submission['id'],
                ]);
                $issued_count++;
            } catch (Throwable $exception) {
                log_activity('token.issue_error', ['event_id' => $eventId, 'submission_id' => $submission['id'], 'error' => $exception->getMessage()], 'ERROR');
                $failed_count++;
            }
        }

        $parts = [];
        if ($issued_count > 0) $parts[] = $issued_count . ' token' . ($issued_count !== 1 ? 's' : '') . ' issued';
        if ($skipped_count > 0) $parts[] = $skipped_count . ' already had a token';
        if ($failed_count > 0)  $parts[] = $failed_count . ' failed';

        if ($failed_count > 0) {
            flash('warning', implode(', ', $parts) . '. Check the activity log for details.');
        } else {
            flash('success', implode(', ', $parts) . '.');
        }
    }

    if ($action === 'revoke') {
        $tokenId = (int) ($_POST['token_id'] ?? 0);
        $revokeTarget = fetch_one(
            'SELECT id FROM voting_tokens WHERE id = :id AND event_id = :event_id AND status = "issued"',
            ['id' => $tokenId, 'event_id' => $eventId]
        );
        if ($revokeTarget) {
            revoke_voting_token($tokenId, (int) $actor['id']);
            write_audit_log('token_revoked', 'voting_tokens', (string) $tokenId, 'Voting token revoked.', $eventId);
            flash('success', 'Token revoked.');
        } else {
            flash('error', 'Token not found or already revoked.');
        }
    }

    if ($action === 'reissue') {
        $tokenId = (int) ($_POST['token_id'] ?? 0);
        $tokenRecord = fetch_one('SELECT * FROM voting_tokens WHERE id = :id AND event_id = :event_id', ['id' => $tokenId, 'event_id' => $eventId]);

        if ($tokenRecord) {
            revoke_voting_token($tokenId, (int) $actor['id']);
            try {
                $issued = issue_voting_token($eventId, (int) $tokenRecord['submission_id'], (int) $actor['id'], 'sms');
                $submission = fetch_submission_by_id((int) $tokenRecord['submission_id']);
                if ($submission) {
                    $delivery = deliver_voting_token($event, $submission, $issued);
                } else {
                    $delivery = ['channel' => 'sms', 'fallback_used' => false, 'success' => false];
                }
                write_audit_log('token_reissued', 'voting_tokens', $issued['reference'], 'Voting token reissued.', $eventId, [
                    'submission_id' => $tokenRecord['submission_id'],
                    'delivery_channel' => $delivery['channel'],
                    'fallback_used' => $delivery['fallback_used'],
                ]);

                if (!$delivery['success']) {
                    flash('error', 'Token reissued but delivery failed — the voter did not receive it via SMS or email. Submission: ' . ($submission['submission_reference'] ?? ''));
                } else {
                    $channelLabel = $delivery['fallback_used'] ? 'email fallback' : 'SMS';
                    flash('success', 'Token reissued and delivered via ' . $channelLabel . ' to ' . ($submission['submission_reference'] ?? '') . '.');
                }
            } catch (Throwable $exception) {
                log_activity('token.reissue_error', ['event_id' => $eventId, 'token_id' => $tokenId, 'error' => $exception->getMessage()], 'ERROR');
                flash('error', 'Could not reissue token. Please try again.');
            }
        }
    }

    redirect('/creator/tokens.php?event=' . $eventId);
}

$approvedSubmissions = fetch_all(
    'SELECT ves.*, users.full_name, users.email, users.phone, users.phone_verified_at
     FROM voter_event_submissions ves
     INNER JOIN users ON users.id = ves.user_id
     WHERE ves.event_id = :event_id AND ves.status = "approved"
     ORDER BY ves.approved_at DESC',
    ['event_id' => $eventId]
);
$tokens = fetch_all(
    'SELECT vt.*, ves.submission_reference, users.full_name
     FROM voting_tokens vt
     INNER JOIN voter_event_submissions ves ON ves.id = vt.submission_id
     INNER JOIN users ON users.id = ves.user_id
     WHERE vt.event_id = :event_id
     ORDER BY vt.id DESC',
    ['event_id' => $eventId]
);

$pageTitle = 'Token Issuance';
$pageHeading = 'Token Issuance';
$pageDescription = 'Issue, revoke, and reissue one-time voting credentials for approved voters.';
$isDashboard = true;
$sidebarContext = current_role_slug() ?? 'event_creator';
$activeEventTool = 'tokens';
$eventContextId = $eventId;

$pendingIssueCount = count(array_filter($approvedSubmissions, static fn($s) => !active_token_for_submission((int) $s['id'])));

include dirname(__DIR__) . '/includes/header.php';
?>

<?php if ($pendingIssueCount > 0): ?>
<section class="panel" style="margin-bottom:0;">
    <div class="inline-actions" style="align-items:center;">
        <div>
            <strong><?= e((string) $pendingIssueCount); ?> approved voter<?= $pendingIssueCount !== 1 ? 's' : ''; ?> without a token</strong>
            <p style="font-size:0.85rem;color:var(--ink-3);margin-top:2px;">Issue all at once.</p>
        </div>
        <form method="post" style="display:flex;align-items:center;gap:12px;">
            <?= csrf_field(); ?>
            <input type="hidden" name="action" value="issue_all">
            <input type="hidden" name="event_id" value="<?= e((string) $eventId); ?>">
            <?= channel_toggle_html('sms'); ?>
            <button class="button button--primary" type="submit">Issue all (<?= e((string) $pendingIssueCount); ?>)</button>
        </form>
    </div>
</section>
<?php endif; ?>

<section class="grid-2">
    <article class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Approved Submission</th>
                <th>Status</th>
                <th>Issue</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($approvedSubmissions as $submission): ?>
                <tr>
                    <td>
                        <strong><?= e($submission['submission_reference']); ?></strong>
                        <p><?= e($submission['full_name'] . ' / ' . $submission['email']); ?></p>
                        <p><?= e((string) $submission['phone']); ?><?= empty($submission['phone_verified_at']) ? ' (unverified)' : ''; ?></p>
                    </td>
                    <td><span class="badge <?= active_token_for_submission((int) $submission['id']) ? 'badge-success' : 'badge-muted'; ?>"><?= active_token_for_submission((int) $submission['id']) ? 'Token issued' : 'Ready'; ?></span></td>
                    <td>
                        <?php if (!active_token_for_submission((int) $submission['id'])): ?>
                            <form method="post" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                                <?= csrf_field(); ?>
                                <input type="hidden" name="action" value="issue">
                                <input type="hidden" name="event_id" value="<?= e((string) $eventId); ?>">
                                <input type="hidden" name="submission_id" value="<?= e((string) $submission['id']); ?>">
                                <?= channel_toggle_html('sms'); ?>
                                <button class="button button--primary" type="submit">Issue</button>
                            </form>
                        <?php else: ?>
                            <span class="badge badge-success">Active token exists</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </article>

    <article class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Voter</th>
                <th>Status</th>
                <th>Expiry</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($tokens as $token): ?>
                <tr>
                    <td>
                        <strong><?= e($token['full_name']); ?></strong>
                        <p><?= e($token['submission_reference']); ?></p>
                    </td>
                    <td><span class="badge <?= e(badge_class($token['status'])); ?>"><?= e(format_status($token['status'])); ?></span></td>
                    <td><?= e(format_datetime($token['expires_at'], 'M j, Y H:i')); ?></td>
                    <td class="table-actions">
                        <?php if ($token['status'] === 'issued'): ?>
                            <form method="post">
                                <?= csrf_field(); ?>
                                <input type="hidden" name="action" value="revoke">
                                <input type="hidden" name="event_id" value="<?= e((string) $eventId); ?>">
                                <input type="hidden" name="token_id" value="<?= e((string) $token['id']); ?>">
                                <button class="button button--danger" type="submit">Revoke</button>
                            </form>
                        <?php endif; ?>
                        <form method="post">
                            <?= csrf_field(); ?>
                            <input type="hidden" name="action" value="reissue">
                            <input type="hidden" name="event_id" value="<?= e((string) $eventId); ?>">
                            <input type="hidden" name="token_id" value="<?= e((string) $token['id']); ?>">
                            <button class="button button--ghost" type="submit">Reissue</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </article>
</section>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
