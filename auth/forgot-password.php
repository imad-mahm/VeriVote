<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (current_user()) {
    redirect(dashboard_home_for_role((string) current_role_slug()));
}

if (is_post_request()) {
    verify_csrf_or_fail();
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $limit = consume_rate_limit('password-reset', $email . '|' . client_ip(), 5, 900);

    if (!$limit['allowed']) {
        flash('error', 'Too many reset requests. Please wait ' . $limit['retry_after'] . ' seconds.');
        redirect('/auth/forgot-password.php');
    }

    $user = fetch_one('SELECT id, full_name, email FROM users WHERE email = :email AND status = "active" LIMIT 1', ['email' => $email]);

    if ($user) {
        execute_statement(
            'UPDATE password_reset_tokens SET used_at = NOW()
             WHERE user_id = :user_id AND used_at IS NULL',
            ['user_id' => $user['id']]
        );

        $token     = bin2hex(random_bytes(20));
        $expiresAt = (new DateTimeImmutable('now'))
            ->modify('+' . (int) app_config('security.password_reset_expiry_minutes') . ' minutes')
            ->format('Y-m-d H:i:s');

        execute_statement(
            'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at)
             VALUES (:user_id, :token_hash, :expires_at)',
            [
                'user_id'    => $user['id'],
                'token_hash' => secure_digest('password-reset:' . $token),
                'expires_at' => $expiresAt,
            ]
        );

        log_activity('password_reset.requested', ['user_id' => (int) $user['id']]);

        $resetLink = base_url('/auth/reset-password.php?token=' . urlencode($token));
        send_email_notification(
            (int) $user['id'], null, $user['email'],
            'Reset your Verivote password',
            'Use this password reset link: ' . $resetLink,
            null,
            ['purpose' => 'password_reset']
        );
    } else {
        log_activity('password_reset.unknown_email', ['email' => $email], 'WARN');
    }

    flash('success', 'If an account exists for that address, a reset link has been sent.');
    redirect('/auth/forgot-password.php');
}

$pageTitle       = 'Forgot Password';
$pageDescription = 'Request a password reset link for your Verivote account.';

include dirname(__DIR__) . '/includes/header.php';
?>
<div class="form-page" data-reveal>
    <div class="form-page__header">
        <span class="eyebrow">Password reset</span>
        <h1>Request a reset link</h1>
        <p>If the address exists, we'll email a link. Check your inbox and spam folder.</p>
    </div>
    <form method="post" class="form-grid form-grid--single">
        <?= csrf_field(); ?>
        <div class="field">
            <label for="email">Email address</label>
            <input id="email" type="email" name="email" autocomplete="email" required>
        </div>
        <button class="button button--primary" type="submit">Send reset link</button>
    </form>
    <div class="form-page-footer">
        Remembered it? <a href="<?= e(base_url('/auth/login.php')); ?>">Log in</a>
    </div>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
