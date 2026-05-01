<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_role(['verifier', 'super_admin']);

$user = current_user();

if (is_post_request()) {
    verify_csrf_or_fail();
    $verificationId = (int) ($_POST['verification_id'] ?? 0);
    $newStatus = (string) ($_POST['status'] ?? 'approved');
    $notes = trim((string) ($_POST['notes'] ?? ''));

    if (!in_array($newStatus, ['approved', 'rejected'], true)) {
        flash('error', 'Unsupported verifier decision.');
        redirect('/verifier/dashboard.php');
    }

    if ($notes === '') {
        flash('error', 'Trusted verifier actions require a note.');
        redirect('/verifier/dashboard.php');
    }

    $verification = has_role('super_admin')
        ? fetch_one(
            'SELECT vv.id, ves.event_id, ves.id AS submission_id
             FROM voter_verifications vv
             INNER JOIN voter_event_submissions ves ON ves.id = vv.submission_id
             INNER JOIN verification_methods vm ON vm.id = vv.verification_method_id
             WHERE vv.id = :id AND vm.method_key = "trusted_verifier"',
            ['id' => $verificationId]
        )
        : fetch_one(
            'SELECT vv.id, ves.event_id, ves.id AS submission_id
             FROM voter_verifications vv
             INNER JOIN voter_event_submissions ves ON ves.id = vv.submission_id
             INNER JOIN verification_methods vm ON vm.id = vv.verification_method_id
             INNER JOIN verifiers ON verifiers.event_id = ves.event_id AND verifiers.user_id = :user_id AND verifiers.is_active = 1
             WHERE vv.id = :id AND vm.method_key = "trusted_verifier"',
            ['id' => $verificationId, 'user_id' => $user['id']]
        );

    if ($verification) {
        execute_statement(
            'UPDATE voter_verifications
             SET status = :status, notes = :notes, verifier_user_id = :verifier_user_id, verified_at = NOW()
             WHERE id = :id',
            [
                'status' => $newStatus,
                'notes' => $notes !== '' ? $notes : null,
                'verifier_user_id' => $user['id'],
                'id' => $verificationId,
            ]
        );
        execute_statement(
            'UPDATE voter_event_submissions
             SET status = CASE WHEN status = "pending" THEN "under_review" ELSE status END,
                 last_reviewed_at = NOW()
             WHERE id = :submission_id',
            ['submission_id' => $verification['submission_id']]
        );
        write_audit_log('trusted_verifier_reviewed', 'voter_verifications', (string) $verificationId, 'Trusted verifier updated a verification step.', (int) $verification['event_id'], ['status' => $newStatus]);
        log_activity('verification.trusted_verifier_reviewed', [
            'verification_id' => $verificationId,
            'submission_id' => (int) $verification['submission_id'],
            'event_id' => (int) $verification['event_id'],
            'verifier_user_id' => (int) $user['id'],
            'status' => $newStatus,
        ]);
        flash('success', 'Verification updated.');
    }

    redirect('/verifier/dashboard.php');
}

$queue = has_role('super_admin')
    ? fetch_all(
        'SELECT vv.*, vm.label, ves.submission_reference, ves.id AS submission_id, events.id AS event_id, events.title AS event_title,
                users.full_name, users.email
         FROM voter_verifications vv
         INNER JOIN verification_methods vm ON vm.id = vv.verification_method_id
         INNER JOIN voter_event_submissions ves ON ves.id = vv.submission_id
         INNER JOIN events ON events.id = ves.event_id
         INNER JOIN users ON users.id = ves.user_id
         WHERE vm.method_key = "trusted_verifier" AND vv.status IN ("pending", "under_review")
         ORDER BY vv.id ASC'
    )
    : fetch_all(
        'SELECT vv.*, vm.label, ves.submission_reference, ves.id AS submission_id, events.id AS event_id, events.title AS event_title,
                users.full_name, users.email
         FROM voter_verifications vv
         INNER JOIN verification_methods vm ON vm.id = vv.verification_method_id
         INNER JOIN voter_event_submissions ves ON ves.id = vv.submission_id
         INNER JOIN events ON events.id = ves.event_id
         INNER JOIN users ON users.id = ves.user_id
         INNER JOIN verifiers ON verifiers.event_id = events.id AND verifiers.user_id = :user_id AND verifiers.is_active = 1
         WHERE vm.method_key = "trusted_verifier" AND vv.status IN ("pending", "under_review")
         ORDER BY vv.id ASC',
        ['user_id' => $user['id']]
    );

$pageTitle = 'Verifier Dashboard';
$pageHeading = 'Verifier Dashboard';
$pageDescription = 'Process trusted verifier approvals and in-person identity confirmations.';
$isDashboard = true;
$sidebarContext = 'verifier';
$activeSidebar = 'verifier-dashboard';

include dirname(__DIR__) . '/includes/header.php';
?>
<section class="table-wrap">
    <table>
        <thead>
        <tr>
            <th>Event</th>
            <th>Submission</th>
            <th>Voter</th>
            <th>Decision</th>
        </tr>
        </thead>
        <tbody>
        <?php if (!$queue): ?>
            <tr><td colspan="4">No trusted verifier tasks are pending.</td></tr>
        <?php else: ?>
            <?php foreach ($queue as $item): ?>
                <tr>
                    <td>
                        <strong><?= e($item['event_title']); ?></strong>
                        <p>#<?= e((string) $item['event_id']); ?></p>
                    </td>
                    <td>
                        <strong><?= e($item['submission_reference']); ?></strong>
                        <p><a href="<?= e(base_url('/creator/verifications.php?event=' . $item['event_id'] . '&submission=' . $item['submission_id'])); ?>">Open submission</a></p>
                    </td>
                    <td>
                        <strong><?= e($item['full_name']); ?></strong>
                        <p><?= e($item['email']); ?></p>
                    </td>
                    <td>
                        <form method="post" class="form-grid form-grid--single">
                            <?= csrf_field(); ?>
                            <input type="hidden" name="verification_id" value="<?= e((string) $item['id']); ?>">
                            <select name="status">
                                <option value="approved">Approve</option>
                                <option value="rejected">Reject</option>
                            </select>
                            <textarea name="notes" placeholder="Physical verification notes"></textarea>
                            <button class="button button--primary" type="submit">Save decision</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</section>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
