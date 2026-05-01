<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (current_user()) {
    redirect(dashboard_home_for_role((string) current_role_slug()));
}

if (is_post_request()) {
    verify_csrf_or_fail();
    store_old_input($_POST);

    $identifier = trim((string) ($_POST['identifier'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (attempt_login($identifier, $password)) {
        clear_old_input();
        flash('success', 'Welcome back to Verivote.');
        redirect(dashboard_home_for_role((string) current_role_slug()));
    }
}

$pageTitle = 'Log In';
$pageDescription = 'Access Verivote voter and operator dashboards.';
$activeNav = 'login';

include dirname(__DIR__) . '/includes/header.php';
?>
<section class="auth-shell" data-reveal>
    <div class="auth-shell__visual">
        <div>
            <span class="eyebrow">Secure Access</span>
            <h1>Log in to Verivote</h1>
        </div>
        <div class="list-shell">
            <div class="list-row"><strong>Session security</strong><p>PHP sessions, server-side role checks, CSRF protection, and audit logging for sensitive actions.</p></div>
            <div class="list-row"><strong>Voting security</strong><p>Tokens are validated server-side and ballots are written without direct voter identity linkage.</p></div>
            <div class="list-row"><strong>Seed access</strong><p>Demo role accounts are created from `database/seed.sql` for local testing.</p></div>
        </div>
    </div>
    <div class="auth-shell__form">
        <div>
            <span class="eyebrow">Account Login</span>
            <h2>Continue to your workspace</h2>
        </div>
        <form method="post" class="form-grid form-grid--single">
            <?= csrf_field(); ?>
            <div class="field">
                <label for="identifier">Email or phone</label>
                <input id="identifier" type="text" name="identifier" value="<?= e(old_input('identifier')); ?>" required>
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input id="password" type="password" name="password" required>
            </div>
            <button class="button button--primary" type="submit">Log in</button>
        </form>
        <div class="inline-actions">
            <a href="<?= e(base_url('/auth/register.php')); ?>">Create a voter account</a>
            <a href="<?= e(base_url('/auth/forgot-password.php')); ?>">Forgot password?</a>
        </div>
    </div>
</section>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
