<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_login();
$user = current_user();

if (is_post_request()) {
    verify_csrf_or_fail();
    $action = (string) ($_POST['action'] ?? 'verify');
    $user = refresh_current_user() ?? $user;

    if ($action === 'resend') {
        if (!empty($user['phone_verified_at'])) {
            flash('success', 'Your phone number is already verified.');
            redirect('/auth/verify-email.php');
        }

        try {
            send_account_phone_verification_code($user);
            flash('success', 'A fresh SMS verification code has been sent.');
        } catch (Throwable $exception) {
            flash('error', $exception->getMessage());
        }

        redirect('/auth/verify-email.php');
    }

    $limit = consume_rate_limit(
        'account-phone',
        (string) $user['id'] . '|' . client_ip(),
        (int) app_config('security.code_attempts'),
        (int) app_config('security.code_window_seconds')
    );

    if (!$limit['allowed']) {
        flash('error', 'Too many attempts. Please wait ' . $limit['retry_after'] . ' seconds.');
        redirect('/auth/verify-email.php');
    }

    $code = trim((string) ($_POST['code'] ?? ''));
    $record = fetch_one(
        'SELECT * FROM verification_codes
         WHERE user_id = :user_id AND purpose = "account_phone" AND used_at IS NULL
         ORDER BY id DESC LIMIT 1',
        ['user_id' => $user['id']]
    );

    if (
        !$record
        || !hash_equals($record['code_hash'], hash_code_value($code))
        || new DateTimeImmutable($record['expires_at']) < new DateTimeImmutable('now')
    ) {
        flash('error', 'Invalid or expired verification code.');
        redirect('/auth/verify-email.php');
    }

    execute_statement('UPDATE verification_codes SET used_at = NOW() WHERE id = :id', ['id' => $record['id']]);
    execute_statement('UPDATE users SET phone_verified_at = NOW() WHERE id = :id', ['id' => $user['id']]);
    clear_rate_limit('account-phone', (string) $user['id'] . '|' . client_ip());
    refresh_current_user();
    write_audit_log('phone_verified', 'users', (string) $user['id'], 'User verified their account phone.');
    log_activity('verification.phone_verified', [
        'user_id' => (int) $user['id'],
    ]);
    flash('success', 'Phone number verified successfully.');
    redirect(dashboard_home_for_role((string) current_role_slug()));
}

$pageTitle = 'Verify Phone';
$pageDescription = 'Confirm phone ownership for your Verivote account.';
$latestNotification = app_config('app_env') === 'development'
    ? fetch_one(
        'SELECT delivery_code, created_at
         FROM notifications
         WHERE user_id = :user_id AND channel = "sms"
         ORDER BY id DESC LIMIT 1',
        ['user_id' => $user['id']]
    )
    : null;

include dirname(__DIR__) . '/includes/header.php';
?>
<section class="section">
    <article class="panel" data-reveal>
        <span class="eyebrow">Account Verification</span>
        <h1>Verify your phone number</h1>
        <p>Enter the latest SMS code sent to <strong><?= e((string) $user['phone']); ?></strong>. Event registration stays locked until phone ownership is confirmed.</p>
        <?php if (!empty($user['phone_verified_at'])): ?>
            <div class="alert alert--success">This phone number is already verified.</div>
        <?php else: ?>
            <form method="post" class="form-grid form-grid--single">
                <?= csrf_field(); ?>
                <div class="field">
                    <label for="code">Verification code</label>
                    <input id="code" type="text" name="code" maxlength="6" required>
                </div>
                <button class="button button--primary" type="submit">Verify phone</button>
            </form>
            <form method="post">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" value="resend">
                <button class="button button--ghost" type="submit">Resend code</button>
            </form>
            <?php if ($latestNotification): ?>
                <div class="alert alert--info">
                    Development helper: latest queued code is <strong><?= e($latestNotification['delivery_code']); ?></strong>.
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </article>
</section>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
