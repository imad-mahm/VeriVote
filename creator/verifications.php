<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$eventId = (int) ($_GET['event'] ?? $_POST['event_id'] ?? 0);
$event = fetch_event_by_id($eventId);

if (!$event) {
    flash('error', 'Event not found.');
    redirect('/creator/dashboard.php');
}

require_login();

if (!user_can_access_event_evidence($eventId)) {
    http_response_code(403);
    flash('error', 'You do not have access to this verification queue.');
    redirect(dashboard_home_for_role((string) current_role_slug()));
}

if (is_post_request()) {
    verify_csrf_or_fail();
    $action = (string) ($_POST['action'] ?? '');
    $user = current_user();

    if ($action === 'update_verification') {
        $verificationId = (int) ($_POST['verification_id'] ?? 0);
        $newStatus = (string) ($_POST['status'] ?? 'under_review');
        $notes = trim((string) ($_POST['notes'] ?? ''));
        $verification = fetch_one(
            'SELECT vv.*, ves.event_id, ves.id AS submission_id, vm.method_key, vm.label
             FROM voter_verifications vv
             INNER JOIN voter_event_submissions ves ON ves.id = vv.submission_id
             INNER JOIN verification_methods vm ON vm.id = vv.verification_method_id
             WHERE vv.id = :id AND ves.event_id = :event_id',
            ['id' => $verificationId, 'event_id' => $eventId]
        );

        if (!$verification || !user_can_review_verification_method($eventId, $verification['method_key'])) {
            flash('error', 'You are not allowed to review that verification step.');
            redirect('/creator/verifications.php?event=' . $eventId);
        }

        $allowedStatuses = user_can_finalize_submission($eventId)
            ? ['pending', 'under_review', 'approved', 'rejected', 'waived']
            : ['under_review', 'approved', 'rejected'];

        if (!in_array($newStatus, $allowedStatuses, true)) {
            flash('error', 'Unsupported verification status.');
            redirect('/creator/verifications.php?event=' . $eventId . '&submission=' . $verification['submission_id']);
        }

        if ($newStatus === 'rejected' && $notes === '') {
            flash('error', 'Rejection notes are required for verification denials.');
            redirect('/creator/verifications.php?event=' . $eventId . '&submission=' . $verification['submission_id']);
        }

        if ($verification['method_key'] === 'trusted_verifier' && $notes === '') {
            flash('error', 'Trusted verifier actions require an explicit note.');
            redirect('/creator/verifications.php?event=' . $eventId . '&submission=' . $verification['submission_id']);
        }

        $verifiedAt = in_array($newStatus, ['approved', 'rejected', 'waived'], true)
            ? (new DateTimeImmutable('now'))->format('Y-m-d H:i:s')
            : null;

        execute_statement(
            'UPDATE voter_verifications
             SET status = :status,
                 notes = :notes,
                 verifier_user_id = :verifier_user_id,
                 verified_at = :verified_at
             WHERE id = :id',
            [
                'status' => $newStatus,
                'notes' => $notes !== '' ? $notes : null,
                'verifier_user_id' => $user['id'],
                'verified_at' => $verifiedAt,
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

        write_audit_log(
            'verification_reviewed',
            'voter_verifications',
            (string) $verificationId,
            'Verification status updated.',
            $eventId,
            ['status' => $newStatus, 'method_key' => $verification['method_key']]
        );
        log_activity('verification.reviewed', [
            'verification_id' => $verificationId,
            'submission_id' => (int) $verification['submission_id'],
            'event_id' => $eventId,
            'reviewer_user_id' => (int) $user['id'],
            'method_key' => $verification['method_key'],
            'status' => $newStatus,
        ]);
        flash('success', 'Verification updated.');
        redirect('/creator/verifications.php?event=' . $eventId . '&submission=' . $verification['submission_id']);
    }

    if ($action === 'set_submission_status') {
        $submissionId = (int) ($_POST['submission_id'] ?? 0);
        $newStatus = (string) ($_POST['submission_status'] ?? 'under_review');
        $notes = trim((string) ($_POST['approval_notes'] ?? ''));

        if (!user_can_finalize_submission($eventId)) {
            flash('error', 'Only event reviewers with elevated permissions can finalize submission status.');
            redirect('/creator/verifications.php?event=' . $eventId . '&submission=' . $submissionId);
        }

        if ($newStatus === 'approved' && !submission_ready_for_approval($submissionId)) {
            flash('error', 'All required verification steps must be approved before final approval.');
            redirect('/creator/verifications.php?event=' . $eventId . '&submission=' . $submissionId);
        }

        if ($newStatus === 'rejected' && $notes === '') {
            flash('error', 'Submission rejection notes are required.');
            redirect('/creator/verifications.php?event=' . $eventId . '&submission=' . $submissionId);
        }

        execute_statement(
            'UPDATE voter_event_submissions
             SET status = :status,
                 approval_notes = :approval_notes,
                 approved_at = :approved_at,
                 approved_by = :approved_by,
                 last_reviewed_at = NOW()
             WHERE id = :id AND event_id = :event_id',
            [
                'status' => $newStatus,
                'approval_notes' => $notes !== '' ? $notes : null,
                'approved_at' => $newStatus === 'approved' ? (new DateTimeImmutable('now'))->format('Y-m-d H:i:s') : null,
                'approved_by' => $newStatus === 'approved' ? $user['id'] : null,
                'id' => $submissionId,
                'event_id' => $eventId,
            ]
        );

        if ($newStatus === 'rejected') {
            $token = active_token_for_submission($submissionId);
            if ($token) {
                revoke_voting_token((int) $token['id'], (int) $user['id']);
                write_audit_log('token_revoked', 'voting_tokens', (string) $token['id'], 'Active token revoked because submission was rejected.', $eventId);
            }
        }

        write_audit_log('submission_status_changed', 'voter_event_submissions', (string) $submissionId, 'Submission status updated.', $eventId, ['status' => $newStatus]);
        $logEvent = $newStatus === 'approved' ? 'submission.approved'
            : ($newStatus === 'rejected' ? 'submission.rejected' : 'submission.status_changed');
        log_activity($logEvent, [
            'submission_id' => $submissionId,
            'event_id' => $eventId,
            'reviewer_user_id' => (int) $user['id'],
            'status' => $newStatus,
        ], $newStatus === 'rejected' ? 'WARN' : 'INFO');
        flash('success', 'Submission status updated.');
        redirect('/creator/verifications.php?event=' . $eventId . '&submission=' . $submissionId);
    }
}

$submissions = fetch_all(
    'SELECT ves.*, users.full_name, users.email,
            (SELECT COUNT(*) FROM voter_verifications vv WHERE vv.submission_id = ves.id AND vv.status IN ("pending", "under_review")) AS pending_steps,
            (SELECT COUNT(*) FROM voter_verifications vv WHERE vv.submission_id = ves.id AND vv.status = "rejected") AS rejected_steps
     FROM voter_event_submissions ves
     INNER JOIN users ON users.id = ves.user_id
     WHERE ves.event_id = :event_id
     ORDER BY FIELD(ves.status, "under_review", "pending", "rejected", "approved"), ves.updated_at DESC',
    ['event_id' => $eventId]
);

$selectedSubmissionId = (int) ($_GET['submission'] ?? ($submissions[0]['id'] ?? 0));
$selectedSubmission = $selectedSubmissionId ? fetch_submission_by_id($selectedSubmissionId) : null;
$selectedAnswers = $selectedSubmission ? fetch_submission_answers($selectedSubmissionId) : [];
$selectedVerifications = $selectedSubmission ? fetch_submission_verifications($selectedSubmissionId) : [];
$summary = $selectedSubmission ? submission_verification_summary($selectedSubmissionId) : null;
$canFinalize = user_can_finalize_submission($eventId);

$pageTitle = 'Verification Review';
$pageHeading = 'Verification Review';
$pageDescription = 'Review voter evidence, track blockers, and make auditable verification decisions.';
$isDashboard = true;
$sidebarContext = current_role_slug() ?? 'event_creator';
$activeSidebar = 'creator-dashboard';
$activeEventTool = 'verifications';
$eventContextId = $eventId;

include dirname(__DIR__) . '/includes/header.php';
?>
<section class="grid-2">
    <article class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Submission</th>
                <th>Status</th>
                <th>Queue</th>
                <th>User</th>
                <th>Open</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$submissions): ?>
                <tr><td colspan="5">No submissions found for this event.</td></tr>
            <?php else: ?>
                <?php foreach ($submissions as $submission): ?>
                    <tr>
                        <td>
                            <strong><?= e($submission['submission_reference']); ?></strong>
                            <p><?= e(format_datetime($submission['submitted_at'], 'M j, Y H:i')); ?></p>
                        </td>
                        <td><span class="badge <?= e(badge_class($submission['status'])); ?>"><?= e(format_status($submission['status'])); ?></span></td>
                        <td>
                            <div class="pill-row">
                                <?php if ((int) $submission['pending_steps'] > 0): ?>
                                    <span class="badge badge-warning"><?= e((string) $submission['pending_steps']); ?> pending</span>
                                <?php endif; ?>
                                <?php if ((int) $submission['rejected_steps'] > 0): ?>
                                    <span class="badge badge-danger"><?= e((string) $submission['rejected_steps']); ?> rejected</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <strong><?= e($submission['full_name']); ?></strong>
                            <p><?= e($submission['email']); ?></p>
                        </td>
                        <td><a href="<?= e(base_url('/creator/verifications.php?event=' . $eventId . '&submission=' . $submission['id'])); ?>">Review</a></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </article>

    <?php if ($selectedSubmission): ?>
        <article class="panel">
            <span class="eyebrow">Submission Detail</span>
            <h2><?= e($selectedSubmission['submission_reference']); ?></h2>
            <div class="list-shell">
                <div class="list-row">
                    <strong>Applicant</strong>
                    <span><?= e($selectedSubmission['full_name'] . ' / ' . $selectedSubmission['email']); ?></span>
                </div>
                <div class="list-row">
                    <strong>Status</strong>
                    <span class="badge <?= e(badge_class($selectedSubmission['status'])); ?>"><?= e(format_status($selectedSubmission['status'])); ?></span>
                </div>
                <div class="list-row">
                    <strong>Submitted at</strong>
                    <span><?= e(format_datetime($selectedSubmission['submitted_at'], 'M j, Y H:i')); ?></span>
                </div>
                <?php if (!empty($selectedSubmission['approval_notes'])): ?>
                    <div class="list-row">
                        <strong>Submission notes</strong>
                        <span><?= e($selectedSubmission['approval_notes']); ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($summary): ?>
                <div class="stat-strip">
                    <div class="stat-strip__item">
                        <strong><?= e((string) $summary['required_complete']); ?>/<?= e((string) $summary['required_total']); ?></strong>
                        <p>Required checks complete</p>
                    </div>
                    <div class="stat-strip__item">
                        <strong><?= e((string) $summary['pending_total']); ?></strong>
                        <p>Pending or under review</p>
                    </div>
                    <div class="stat-strip__item">
                        <strong><?= e((string) $summary['rejected_total']); ?></strong>
                        <p>Rejected checks</p>
                    </div>
                    <div class="stat-strip__item">
                        <strong><?= submission_ready_for_approval((int) $selectedSubmission['id']) ? 'Eligible' : 'Blocked'; ?></strong>
                        <p>Approval readiness</p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($summary && $summary['blockers'] !== []): ?>
                <div class="alert alert--warning">
                    Approval is blocked by: <?= e(implode(', ', $summary['blockers'])); ?>
                </div>
            <?php endif; ?>
        </article>
    <?php endif; ?>
</section>

<?php if ($selectedSubmission): ?>
    <section class="grid-2">
        <article class="panel">
            <span class="eyebrow">Evidence</span>
            <h2>Submitted answers</h2>
            <div class="list-shell">
                <?php foreach ($selectedAnswers as $answer): ?>
                    <div class="list-row">
                        <div>
                            <strong><?= e($answer['field_label']); ?></strong>
                            <?php if ($answer['file_path']): ?>
                                <p><a href="<?= e(base_url('/api/submission_file.php?answer=' . $answer['id'])); ?>" target="_blank" rel="noopener">Open securely</a></p>
                            <?php else: ?>
                                <p><?= e((string) $answer['text_value']); ?></p>
                            <?php endif; ?>
                        </div>
                        <?php if ((int) ($answer['is_required'] ?? 0) === 1): ?>
                            <span class="badge badge-warning">Required</span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </article>

        <article class="panel">
            <span class="eyebrow">Verification Steps</span>
            <h2>Decision workflow</h2>
            <div class="list-shell">
                <?php foreach ($selectedVerifications as $verification): ?>
                    <div class="list-row">
                        <div>
                            <strong><?= e($verification['label']); ?></strong>
                            <p><?= e($verification['description']); ?></p>
                            <?php if (!empty($verification['reviewer_name'])): ?>
                                <p>Reviewed by <?= e($verification['reviewer_name']); ?><?= $verification['verified_at'] ? ' on ' . e(format_datetime($verification['verified_at'], 'M j, Y H:i')) : ''; ?></p>
                            <?php endif; ?>
                            <?php if (!empty($verification['notes'])): ?>
                                <p><?= e($verification['notes']); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="table-actions">
                            <span class="badge <?= e(badge_class($verification['status'])); ?>"><?= e(format_status($verification['status'])); ?></span>
                            <?php if (user_can_review_verification_method($eventId, $verification['method_key'])): ?>
                                <form method="post" class="form-grid form-grid--single">
                                    <?= csrf_field(); ?>
                                    <input type="hidden" name="action" value="update_verification">
                                    <input type="hidden" name="event_id" value="<?= e((string) $eventId); ?>">
                                    <input type="hidden" name="verification_id" value="<?= e((string) $verification['id']); ?>">
                                    <select name="status">
                                        <?php foreach (($canFinalize ? ['pending', 'under_review', 'approved', 'rejected', 'waived'] : ['under_review', 'approved', 'rejected']) as $status): ?>
                                            <option value="<?= e($status); ?>" <?= $verification['status'] === $status ? 'selected' : ''; ?>><?= e(format_status($status)); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <textarea name="notes" placeholder="Reviewer notes"><?= e((string) $verification['notes']); ?></textarea>
                                    <button class="button button--ghost" type="submit">Save</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </article>
    </section>

    <?php if ($canFinalize): ?>
        <section class="panel">
            <span class="eyebrow">Final Decision</span>
            <h2>Submission status</h2>
            <form method="post" class="form-grid">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" value="set_submission_status">
                <input type="hidden" name="event_id" value="<?= e((string) $eventId); ?>">
                <input type="hidden" name="submission_id" value="<?= e((string) $selectedSubmission['id']); ?>">
                <div class="field">
                    <label for="submission_status">Submission status</label>
                    <select id="submission_status" name="submission_status">
                        <?php foreach (['pending', 'under_review', 'approved', 'rejected'] as $status): ?>
                            <option value="<?= e($status); ?>" <?= $selectedSubmission['status'] === $status ? 'selected' : ''; ?>><?= e(format_status($status)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field field--full">
                    <label for="approval_notes">Reviewer notes</label>
                    <textarea id="approval_notes" name="approval_notes"><?= e((string) $selectedSubmission['approval_notes']); ?></textarea>
                    <span class="helper-text">Rejection notes are required and visible to the voter on the resubmission screen.</span>
                </div>
                <div class="field field--full">
                    <button class="button button--primary" type="submit">Update submission</button>
                </div>
            </form>
        </section>
    <?php endif; ?>
<?php endif; ?>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
