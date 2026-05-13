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

    if ($action === 'issue') {
        $submissionId = (int) ($_POST['submission_id'] ?? 0);
        $submission = fetch_submission_by_id($submissionId);

        if ($submission && $submission['status'] === 'approved') {
            try {
                $issued = issue_voting_token($eventId, $submissionId, (int) $actor['id'], 'sms');
                $delivery = deliver_voting_token($event, $submission, $issued);
                write_audit_log('token_issued', 'voting_tokens', $issued['reference'], 'Voting token issued.', $eventId, [
                    'submission_id' => $submissionId,
                    'delivery_channel' => $delivery['channel'],
                    'fallback_used' => $delivery['fallback_used'],
                ]);

                if (!$delivery['success']) {
                    flash('error', 'Token issued but delivery failed — the voter did not receive it via SMS or email. Reference: ' . $issued['reference']);
                } else {
                    $channelLabel = $delivery['fallback_used'] ? 'email fallback' : 'SMS';
                    flash('success', 'Token issued and delivered via ' . $channelLabel . '. Reference: ' . $issued['reference']);
                }
            } catch (Throwable $exception) {
                log_activity('token.issue_error', ['event_id' => $eventId, 'submission_id' => $submissionId, 'error' => $exception->getMessage()], 'ERROR');
                flash('error', 'Could not issue token. Please try again.');
            }
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
                    flash('error', 'Token reissued but delivery failed — the voter did not receive it via SMS or email. Reference: ' . $issued['reference']);
                } else {
                    $channelLabel = $delivery['fallback_used'] ? 'email fallback' : 'SMS';
                    flash('success', 'Token reissued and delivered via ' . $channelLabel . '. Reference: ' . $issued['reference']);
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

include dirname(__DIR__) . '/includes/header.php';
?>
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
                            <form method="post">
                                <?= csrf_field(); ?>
                                <input type="hidden" name="action" value="issue">
                                <input type="hidden" name="event_id" value="<?= e((string) $eventId); ?>">
                                <input type="hidden" name="submission_id" value="<?= e((string) $submission['id']); ?>">
                                <button class="button button--primary" type="submit">Issue token</button>
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
                <th>Reference</th>
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
                        <strong><?= e($token['token_reference']); ?></strong>
                        <p>Ends with <?= e($token['token_last4']); ?></p>
                    </td>
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
