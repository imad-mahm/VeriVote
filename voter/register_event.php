<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_login();
if (!has_role(['voter', 'super_admin'])) {
    redirect(dashboard_home_for_role((string) current_role_slug()));
}

$user = current_user();
$eventId = (int) ($_GET['event'] ?? $_POST['event_id'] ?? 0);
$event = $eventId > 0 ? fetch_event_by_id($eventId) : null;

if (!$event) {
    flash('error', 'Event not found.');
    redirect('/events.php');
}

$submission = fetch_user_submission($eventId, (int) $user['id']);
$fields = fetch_event_required_fields($eventId);
$existingAnswers = $submission ? fetch_submission_answers((int) $submission['id']) : [];
$existingAnswerMap = submission_answer_value_map($existingAnswers);
$verifications = $submission ? fetch_submission_verifications((int) $submission['id']) : [];
$activeToken = $submission ? active_token_for_submission((int) $submission['id']) : null;
$canResubmit = $submission && $submission['status'] === 'rejected';
$summary = $submission ? submission_verification_summary((int) $submission['id']) : null;

if (is_post_request()) {
    verify_csrf_or_fail();
    $action = (string) ($_POST['action'] ?? 'save_submission');

    if ($action === 'save_submission') {
        store_old_input($_POST);
        $errors = [];

        if (empty($user['phone_verified_at'])) {
            $errors[] = 'Verify your account phone number before registering for an event.';
        }

        if ((int) $event['allow_self_registration'] !== 1 && !$submission) {
            $errors[] = 'This event does not allow self-registration.';
        }

        if ($submission && !$canResubmit) {
            $errors[] = 'You have already submitted registration data for this event.';
        }

        $prepared = prepare_submission_answers_payload($fields, $_POST, $_FILES, $existingAnswers);
        $errors = array_merge($errors, $prepared['errors']);

        if ($errors === []) {
            db()->beginTransaction();

            try {
                if ($submission && $canResubmit) {
                    $oldAnswersSnapshot = array_map(
                        static fn(array $answer): array => [
                            'field_key' => $answer['field_key'],
                            'field_label' => $answer['field_label'],
                            'text_value' => $answer['text_value'],
                            'file_path' => $answer['file_path'],
                        ],
                        $existingAnswers
                    );
                    $oldVerificationsSnapshot = array_map(
                        static fn(array $verification): array => [
                            'method_key' => $verification['method_key'],
                            'status' => $verification['status'],
                            'notes' => $verification['notes'],
                        ],
                        $verifications
                    );

                    replace_submission_answers((int) $submission['id'], $prepared['answers']);
                    update_profile_from_submission_answers((int) $user['id'], $prepared['profile_updates']);
                    $accountForVerification = fetch_one(
                        'SELECT id, email, phone FROM users WHERE id = :id',
                        ['id' => $user['id']]
                    ) ?: $user;
                    reset_submission_verifications((int) $submission['id'], $eventId, $accountForVerification);
                    execute_statement(
                        'UPDATE voter_event_submissions
                         SET status = "pending",
                             approval_notes = NULL,
                             approved_at = NULL,
                             approved_by = NULL,
                             submitted_at = NOW(),
                             last_reviewed_at = NULL
                         WHERE id = :id',
                        ['id' => $submission['id']]
                    );

                    if ($activeToken) {
                        revoke_voting_token((int) $activeToken['id'], (int) $user['id']);
                    }

                    db()->commit();

                    write_audit_log(
                        'submission_resubmitted',
                        'voter_event_submissions',
                        (string) $submission['id'],
                        'Voter resubmitted a rejected event registration.',
                        $eventId,
                        [
                            'event_title' => $event['title'],
                            'previous_answers' => $oldAnswersSnapshot,
                            'previous_verifications' => $oldVerificationsSnapshot,
                        ]
                    );

                    log_activity('submission.resubmitted', [
                        'submission_id' => (int) $submission['id'],
                        'event_id' => $eventId,
                        'user_id' => (int) $user['id'],
                    ]);

                    clear_old_input();
                    flash('success', 'Your corrected registration has been resubmitted for review.');
                    redirect('/voter/register_event.php?event=' . $eventId);
                }

                $reference = random_reference('SUB', 5);
                execute_statement(
                    'INSERT INTO voter_event_submissions (event_id, user_id, submission_reference, status)
                     VALUES (:event_id, :user_id, :submission_reference, "pending")',
                    [
                        'event_id' => $eventId,
                        'user_id' => $user['id'],
                        'submission_reference' => $reference,
                    ]
                );

                $submissionId = (int) db()->lastInsertId();
                replace_submission_answers($submissionId, $prepared['answers']);
                update_profile_from_submission_answers((int) $user['id'], $prepared['profile_updates']);
                $accountForVerification = fetch_one(
                    'SELECT id, email, phone FROM users WHERE id = :id',
                    ['id' => $user['id']]
                ) ?: $user;
                create_submission_verification_records($submissionId, $eventId, $accountForVerification);
                db()->commit();

                write_audit_log('submission_created', 'voter_event_submissions', (string) $submissionId, 'Voter submitted event registration.', $eventId, ['event_title' => $event['title']]);
                log_activity('submission.created', [
                    'submission_id' => $submissionId,
                    'event_id' => $eventId,
                    'user_id' => (int) $user['id'],
                    'reference' => $reference,
                ]);
                clear_old_input();
                flash('success', 'Your event registration has been submitted for review.');
                redirect('/voter/register_event.php?event=' . $eventId);
            } catch (Throwable $exception) {
                if (db()->inTransaction()) {
                    db()->rollBack();
                }

                log_activity('submission.error', ['message' => $exception->getMessage(), 'event_id' => $eventId, 'user_id' => (int) $user['id']]);
                flash('error', 'Could not save your registration. Please try again or contact support.');
            }
        } else {
            flash_errors($errors);
        }
    }

    if (in_array($action, ['verify_code', 'resend_code'], true) && $submission) {
        $verificationId = (int) ($_POST['verification_id'] ?? 0);
        $verification = fetch_one(
            'SELECT vv.*, vm.method_key, vm.label
             FROM voter_verifications vv
             INNER JOIN verification_methods vm ON vm.id = vv.verification_method_id
             WHERE vv.id = :id AND vv.submission_id = :submission_id',
            ['id' => $verificationId, 'submission_id' => $submission['id']]
        );

        if (!$verification) {
            flash('error', 'Verification step not found.');
            redirect('/voter/register_event.php?event=' . $eventId);
        }

        if ($action === 'resend_code') {
            $code = random_numeric_code(6);
            $expiresAt = (new DateTimeImmutable('now'))
                ->modify('+' . (int) app_config('security.verification_code_expiry_minutes') . ' minutes')
                ->format('Y-m-d H:i:s');
            $destination = $verification['method_key'] === 'sms_verification'
                ? normalize_phone_number((string) ($user['phone'] ?? ''))
                : normalize_email((string) $user['email']);

            if ($verification['method_key'] === 'sms_verification' && $destination === null) {
                flash('error', 'SMS verification requires a valid phone number on your account.');
                redirect('/voter/register_event.php?event=' . $eventId);
            }

            execute_statement(
                'INSERT INTO verification_codes (user_id, submission_id, verification_id, purpose, destination, code_hash, expires_at)
                 VALUES (:user_id, :submission_id, :verification_id, :purpose, :destination, :code_hash, :expires_at)',
                [
                    'user_id' => $user['id'],
                    'submission_id' => $submission['id'],
                    'verification_id' => $verificationId,
                    'purpose' => $verification['method_key'] === 'sms_verification' ? 'event_sms' : 'event_email',
                    'destination' => $destination,
                    'code_hash' => hash_code_value($code),
                    'expires_at' => $expiresAt,
                ]
            );

            if ($verification['method_key'] === 'sms_verification') {
                $smsResult = send_sms_notification(
                    (int) $user['id'],
                    $eventId,
                    (string) $destination,
                    'Verivote event verification code',
                    sms_event_verification_message($code, (string) $event['title']),
                    $code,
                    ['verification_method' => $verification['method_key'], 'verification_id' => $verificationId]
                );

                if (!$smsResult['success']) {
                    flash('error', 'Could not send SMS verification code. Please try again.');
                    redirect('/voter/register_event.php?event=' . $eventId);
                }
            } else {
                $emailResult = send_email_notification(
                    (int) $user['id'],
                    $eventId,
                    (string) $destination,
                    'Verivote event verification code',
                    'Your verification code is ' . $code . '.',
                    $code,
                    ['verification_method' => $verification['method_key']]
                );

                if (!$emailResult['success']) {
                    flash('error', 'Could not send the email verification code. Please try again.');
                    redirect('/voter/register_event.php?event=' . $eventId);
                }
            }

            flash('success', 'A fresh event verification code has been sent.');
            redirect('/voter/register_event.php?event=' . $eventId);
        }

        $limit = consume_rate_limit(
            'event-code',
            (string) $user['id'] . '|' . $eventId . '|' . client_ip(),
            (int) app_config('security.code_attempts'),
            (int) app_config('security.code_window_seconds')
        );

        if (!$limit['allowed']) {
            flash('error', 'Too many code attempts. Please wait ' . $limit['retry_after'] . ' seconds.');
            redirect('/voter/register_event.php?event=' . $eventId);
        }

        $code = trim((string) ($_POST['code'] ?? ''));
        $codeRecord = fetch_one(
            'SELECT * FROM verification_codes
             WHERE verification_id = :verification_id AND used_at IS NULL
             ORDER BY id DESC LIMIT 1',
            ['verification_id' => $verificationId]
        );

        if (
            !$codeRecord
            || !hash_equals($codeRecord['code_hash'], hash_code_value($code))
            || new DateTimeImmutable($codeRecord['expires_at']) < new DateTimeImmutable('now')
        ) {
            flash('error', 'Invalid or expired verification code.');
            redirect('/voter/register_event.php?event=' . $eventId);
        }

        execute_statement('UPDATE verification_codes SET used_at = NOW() WHERE id = :id', ['id' => $codeRecord['id']]);
        execute_statement(
            'UPDATE voter_verifications
             SET status = "approved", notes = "Code confirmed by voter.", verified_at = NOW()
             WHERE id = :id',
            ['id' => $verificationId]
        );
        execute_statement(
            'UPDATE voter_event_submissions
             SET status = CASE WHEN status = "pending" THEN "under_review" ELSE status END,
                 last_reviewed_at = NOW()
             WHERE id = :id',
            ['id' => $submission['id']]
        );

        write_audit_log('verification_completed', 'voter_verifications', (string) $verificationId, 'Voter completed a self-service verification step.', $eventId, ['method' => $verification['method_key']]);
        log_activity('verification.code_verified', [
            'verification_id' => $verificationId,
            'submission_id' => (int) $submission['id'],
            'event_id' => $eventId,
            'user_id' => (int) $user['id'],
            'method_key' => $verification['method_key'],
        ]);
        clear_rate_limit('event-code', (string) $user['id'] . '|' . $eventId . '|' . client_ip());
        flash('success', $verification['label'] . ' completed.');
        redirect('/voter/register_event.php?event=' . $eventId);
    }
}

$submission = fetch_user_submission($eventId, (int) $user['id']);
$existingAnswers = $submission ? fetch_submission_answers((int) $submission['id']) : [];
$existingAnswerMap = submission_answer_value_map($existingAnswers);
$verifications = $submission ? fetch_submission_verifications((int) $submission['id']) : [];
$activeToken = $submission ? active_token_for_submission((int) $submission['id']) : null;
$canResubmit = $submission && $submission['status'] === 'rejected';
$summary = $submission ? submission_verification_summary((int) $submission['id']) : null;

$pageTitle = 'Event Registration';
$pageHeading = $event['title'];
$pageDescription = 'Submit required voter details and track verification progress.';
$isDashboard = true;
$sidebarContext = 'voter';
$activeSidebar = '';

include dirname(__DIR__) . '/includes/header.php';
?>
<div class="breadcrumb" style="margin-bottom:16px;">
    <a href="<?= e(base_url('/events.php')); ?>">Elections</a>
    <span>&rsaquo;</span>
    <a href="<?= e(base_url('/event.php?event=' . $eventId)); ?>"><?= e($event['title']); ?></a>
    <span>&rsaquo;</span>
    <span>Register</span>
</div>

<section class="panel">
    <div class="pill-row">
        <span class="badge <?= e(badge_class(effective_event_status($event))); ?>"><?= e(format_status(effective_event_status($event))); ?></span>
        <span class="badge badge-muted"><?= e(format_status($event['verification_policy'])); ?></span>
    </div>
    <h2><?= e($event['title']); ?></h2>
    <p><?= e($event['description']); ?></p>
</section>

<?php if (!$submission || $canResubmit): ?>
    <?php if ($submission && $canResubmit): ?>
        <section class="panel">
            <span class="eyebrow">Correction Required</span>
            <h2>Previous submission was rejected</h2>
            <div class="alert alert--warning">
                <?= e($submission['approval_notes'] ?: 'Reviewers requested corrections before approval.'); ?>
            </div>
            <?php if ($summary && $summary['blockers'] !== []): ?>
                <div class="list-shell">
                    <?php foreach ($summary['blockers'] as $blocker): ?>
                        <div class="list-row">
                            <strong><?= e($blocker); ?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <section class="panel">
        <span class="eyebrow"><?= $canResubmit ? 'Resubmission Form' : 'Registration Form'; ?></span>
        <h2><?= $canResubmit ? 'Correct and resubmit your information' : 'Submit voter information'; ?></h2>
        <form method="post" enctype="multipart/form-data" class="form-grid">
            <?= csrf_field(); ?>
            <input type="hidden" name="action" value="save_submission">
            <input type="hidden" name="event_id" value="<?= e((string) $eventId); ?>">
            <?php foreach ($fields as $field): ?>
                <?php
                $fieldName = 'field_' . $field['id'];
                $fieldType = $field['field_type'];
                $existingValue = $existingAnswerMap[$fieldName]['text_value'] ?? '';
                $old = old_input($fieldName, (string) $existingValue);
                $existingFile = $existingAnswerMap[$fieldName]['original_filename'] ?? '';
                ?>
                <div class="field <?= in_array($fieldType, ['textarea', 'file', 'image'], true) ? 'field--full' : ''; ?>">
                    <label for="<?= e($fieldName); ?>"><?= e($field['field_label']); ?><?= $field['is_required'] ? ' *' : ''; ?></label>
                    <?php if ($fieldType === 'textarea'): ?>
                        <textarea id="<?= e($fieldName); ?>" name="<?= e($fieldName); ?>"><?= e($old); ?></textarea>
                    <?php elseif (in_array($fieldType, ['file', 'image'], true)): ?>
                        <input id="<?= e($fieldName); ?>" type="file" name="<?= e($fieldName); ?>" <?= (!$canResubmit && $field['is_required']) ? 'required' : ''; ?>>
                        <?php if ($existingFile !== ''): ?>
                            <span class="helper-text">Current file: <?= e($existingFile); ?>. Upload a new file only if you want to replace it.</span>
                        <?php endif; ?>
                    <?php elseif ($fieldType === 'select'): ?>
                        <select id="<?= e($fieldName); ?>" name="<?= e($fieldName); ?>" <?= $field['is_required'] ? 'required' : ''; ?>>
                            <option value="">Select an option</option>
                            <?php foreach (json_decode_array($field['options_json'] ?? null) as $option): ?>
                                <option value="<?= e((string) $option); ?>" <?= $old === (string) $option ? 'selected' : ''; ?>><?= e((string) $option); ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <?php
                        $inputType = match ($fieldType) {
                            'email' => 'email',
                            'phone' => 'text',
                            'date' => 'date',
                            default => 'text',
                        };
                        ?>
                        <input id="<?= e($fieldName); ?>" type="<?= e($inputType); ?>" name="<?= e($fieldName); ?>" value="<?= e($old); ?>" placeholder="<?= e((string) $field['placeholder']); ?>" <?= $field['is_required'] ? 'required' : ''; ?>>
                    <?php endif; ?>
                    <?php if (!empty($field['help_text'])): ?>
                        <span class="helper-text"><?= e($field['help_text']); ?></span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <div class="field field--full">
                <button class="button button--primary" type="submit"><?= $canResubmit ? 'Resubmit registration' : 'Submit registration'; ?></button>
            </div>
        </form>
    </section>
<?php elseif ($submission): ?>
    <div class="stat-strip">
        <div class="stat-strip__item">
            <strong><?= e((string) ($summary['required_complete'] ?? 0)); ?>/<?= e((string) ($summary['required_total'] ?? 0)); ?></strong>
            <p>Required checks complete</p>
        </div>
        <div class="stat-strip__item">
            <strong><?= e((string) ($summary['pending_total'] ?? 0)); ?></strong>
            <p>Pending steps</p>
        </div>
        <div class="stat-strip__item">
            <strong><?= e((string) ($summary['rejected_total'] ?? 0)); ?></strong>
            <p>Rejected steps</p>
        </div>
        <div class="stat-strip__item">
            <strong><?= $activeToken ? 'Issued' : 'Awaiting'; ?></strong>
            <p>Token status</p>
        </div>
    </div>

    <section class="grid-2">
        <article class="panel">
            <span class="eyebrow">Submission Status</span>
            <h2><?= e($submission['submission_reference']); ?></h2>
            <div class="list-shell">
                <div class="list-row">
                    <strong>Submission state</strong>
                    <span class="badge <?= e(badge_class($submission['status'])); ?>"><?= e(format_status($submission['status'])); ?></span>
                </div>
                <div class="list-row">
                    <strong>Submitted at</strong>
                    <span><?= e(format_datetime($submission['submitted_at'], 'M j, Y H:i')); ?></span>
                </div>
                <?php if (!empty($submission['approval_notes'])): ?>
                    <div class="list-row">
                        <strong>Review notes</strong>
                        <span><?= e($submission['approval_notes']); ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($activeToken): ?>
                    <div class="list-row">
                        <strong>Active token</strong>
                        <div>
                            <span class="badge badge-success"><?= e($activeToken['token_reference']); ?></span>
                            <p>Ends with <?= e($activeToken['token_last4']); ?> and expires <?= e(format_datetime($activeToken['expires_at'], 'M j, Y H:i')); ?></p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <?php if ($summary && $summary['blockers'] !== []): ?>
                <div class="alert alert--warning">Remaining blockers: <?= e(implode(', ', $summary['blockers'])); ?></div>
            <?php endif; ?>
        </article>

        <article class="panel">
            <span class="eyebrow">Verification Progress</span>
            <h2>Required event checks</h2>
            <div class="list-shell">
                <?php foreach ($verifications as $verification): ?>
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
                            <?php if (in_array($verification['method_key'], ['email_verification', 'sms_verification'], true) && $verification['status'] === 'pending'): ?>
                                <form method="post" class="inline-actions">
                                    <?= csrf_field(); ?>
                                    <input type="hidden" name="action" value="verify_code">
                                    <input type="hidden" name="event_id" value="<?= e((string) $eventId); ?>">
                                    <input type="hidden" name="verification_id" value="<?= e((string) $verification['id']); ?>">
                                    <input type="text" name="code" placeholder="Enter code" required>
                                    <button class="button button--primary" type="submit">Submit code</button>
                                </form>
                                <form method="post">
                                    <?= csrf_field(); ?>
                                    <input type="hidden" name="action" value="resend_code">
                                    <input type="hidden" name="event_id" value="<?= e((string) $eventId); ?>">
                                    <input type="hidden" name="verification_id" value="<?= e((string) $verification['id']); ?>">
                                    <button class="button button--ghost" type="submit">Resend</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </article>
    </section>
<?php endif; ?>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
