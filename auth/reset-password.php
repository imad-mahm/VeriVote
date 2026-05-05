<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$record = $token !== ''
    ? fetch_one(
        'SELECT * FROM password_reset_tokens
         WHERE token_hash = :token_hash AND used_at IS NULL
         ORDER BY id DESC LIMIT 1',
        ['token_hash' => secure_digest('password-reset:' . $token)]
    )
    : null;

if (is_post_request()) {
    verify_csrf_or_fail();
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');
    $errors = [];

    if (!$record || new DateTimeImmutable($record['expires_at']) < new DateTimeImmutable('now')) {
        $errors[] = 'Reset token is invalid or expired.';
    }

    validate_password_input($password, $errors);

    if ($password !== $passwordConfirmation) {
        $errors[] = 'Password confirmation does not match.';
    }

    if ($errors === []) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $burn = $pdo->prepare(
                'UPDATE password_reset_tokens
                 SET used_at = NOW()
                 WHERE id = :id AND used_at IS NULL'
            );
            $burn->execute(['id' => $record['id']]);

            if ($burn->rowCount() !== 1) {
                $pdo->rollBack();
                log_activity('password_reset.token_race_lost', [
                    'token_id' => (int) $record['id'],
                    'user_id' => (int) $record['user_id'],
                ], 'WARN');
                flash('error', 'This reset link has already been used. Please request a new one.');
                redirect('/auth/forgot-password.php');
            }

            execute_statement(
                'UPDATE password_reset_tokens
                 SET used_at = NOW()
                 WHERE user_id = :user_id AND used_at IS NULL',
                ['user_id' => $record['user_id']]
            );

            execute_statement(
                'UPDATE users SET password_hash = :password_hash WHERE id = :user_id',
                ['password_hash' => password_hash($password, PASSWORD_DEFAULT), 'user_id' => $record['user_id']]
            );

            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            log_activity('password_reset.failed', [
                'token_id' => (int) $record['id'],
                'user_id' => (int) $record['user_id'],
                'error' => $exception->getMessage(),
            ], 'ERROR');
            throw $exception;
        }

        write_audit_log(
            'password_reset_completed',
            'users',
            (string) $record['user_id'],
            'User completed a password reset.',
            null,
            ['token_id' => (int) $record['id']]
        );
        log_activity('password_reset.completed', [
            'user_id' => (int) $record['user_id'],
            'token_id' => (int) $record['id'],
        ]);

        flash('success', 'Password updated successfully. You can log in now.');
        redirect('/auth/login.php');
    }

    flash_errors($errors);
}

$pageTitle       = 'Reset Password';
$pageDescription = 'Set a new password for your Verivote account.';

include dirname(__DIR__) . '/includes/header.php';
?>
<div class="form-page" data-reveal>
    <div class="form-page__header">
        <span class="eyebrow">Password reset</span>
        <h1>Choose a new password</h1>
    </div>
    <?php if (!$record || new DateTimeImmutable($record['expires_at']) < new DateTimeImmutable('now')): ?>
        <div class="alert alert--error">This reset link is invalid or expired. <a href="<?= e(base_url('/auth/forgot-password.php')); ?>" style="color:inherit;text-decoration:underline;">Request a new one.</a></div>
    <?php else: ?>
        <form method="post" class="form-grid form-grid--single">
            <?= csrf_field(); ?>
            <input type="hidden" name="token" value="<?= e($token); ?>">
            <div class="field">
                <label for="password">New password</label>
                <input id="password" type="password" name="password" autocomplete="new-password" required>
            </div>
            <div class="field">
                <label for="password_confirmation">Confirm new password</label>
                <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required>
            </div>
            <button class="button button--primary" type="submit">Update password</button>
        </form>
    <?php endif; ?>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
