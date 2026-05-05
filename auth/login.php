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
    $password   = (string) ($_POST['password'] ?? '');

    if (attempt_login($identifier, $password)) {
        clear_old_input();
        flash('success', 'Welcome back to Verivote.');
        redirect(dashboard_home_for_role((string) current_role_slug()));
    }
}

$pageTitle       = 'Log In';
$pageDescription = 'Access Verivote voter and operator dashboards.';
$activeNav       = 'login';

include dirname(__DIR__) . '/includes/header.php';
?>
<section class="auth-shell" data-reveal>
    <div class="auth-shell__visual">
        <div>
            <span class="eyebrow">Secure access</span>
            <h1>Log in to Verivote</h1>
            <p style="margin-top:12px;">Continue to your voter workspace or operator dashboard.</p>
        </div>
        <div class="process-steps" style="margin-top: auto;">
            <div class="process-step">
                <div>
                    <strong>Session security</strong>
                    <p>Server-side role checks, CSRF protection, and audit logging on every sensitive action.</p>
                </div>
            </div>
            <div class="process-step">
                <div>
                    <strong>Ballot integrity</strong>
                    <p>Tokens are validated server-side. Ballots are written without direct voter identity linkage.</p>
                </div>
            </div>
            <div class="process-step">
                <div>
                    <strong>Audit trail</strong>
                    <p>Every login attempt and action is recorded in a hash-chained log visible to operators.</p>
                </div>
            </div>
        </div>
    </div>
    <div class="auth-shell__form">
        <div>
            <h2>Continue to your workspace</h2>
            <p style="margin-top:6px;">Use your email address or phone number.</p>
        </div>
        <form method="post" class="form-grid form-grid--single">
            <?= csrf_field(); ?>
            <div class="field">
                <label for="identifier">Email or phone</label>
                <input id="identifier" type="text" name="identifier" value="<?= e(old_input('identifier')); ?>" autocomplete="username" required>
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input id="password" type="password" name="password" autocomplete="current-password" required>
            </div>
            <button class="button button--primary" type="submit">Log in</button>
        </form>
        <div class="auth-form-footer">
            <a href="<?= e(base_url('/auth/register.php')); ?>">Create a voter account</a>
            <span>&middot;</span>
            <a class="muted" href="<?= e(base_url('/auth/forgot-password.php')); ?>">Forgot password</a>
        </div>
    </div>
</section>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
