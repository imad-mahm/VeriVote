<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (current_user()) {
    redirect(dashboard_home_for_role((string) current_role_slug()));
}

if (is_post_request()) {
    verify_csrf_or_fail();
    store_old_input($_POST);

    $fullName             = trim((string) ($_POST['full_name'] ?? ''));
    $email                = normalize_email((string) ($_POST['email'] ?? ''));
    $phoneInput           = trim((string) ($_POST['phone'] ?? ''));
    $password             = (string) ($_POST['password'] ?? '');
    $passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');

    $errors = [];
    validate_required('Full name', $fullName, $errors);
    validate_required('Email address', $email, $errors);
    validate_email_input('Email address', $email, $errors);
    validate_required('Phone number', $phoneInput, $errors);
    $normalizedPhone = validate_phone_input('Phone number', $phoneInput, $errors);
    validate_password_input($password, $errors);

    if ($password !== $passwordConfirmation) {
        $errors[] = 'Password confirmation does not match.';
    }

    if (fetch_one('SELECT id FROM users WHERE email = :email', ['email' => $email])) {
        $errors[] = 'An account with that email already exists.';
    }

    if ($normalizedPhone !== null && fetch_one('SELECT id FROM users WHERE phone = :phone', ['phone' => $normalizedPhone])) {
        $errors[] = 'An account with that phone number already exists.';
    }

    if ($errors === []) {
        $voterRole = fetch_one('SELECT id FROM roles WHERE slug = "voter" LIMIT 1');
        execute_statement(
            'INSERT INTO users (role_id, full_name, email, phone, password_hash, status)
             VALUES (:role_id, :full_name, :email, :phone, :password_hash, "active")',
            [
                'role_id'       => $voterRole['id'] ?? 5,
                'full_name'     => $fullName,
                'email'         => $email,
                'phone'         => $normalizedPhone,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]
        );

        $userId = (int) db()->lastInsertId();
        execute_statement('INSERT INTO voter_profiles (user_id) VALUES (:user_id)', ['user_id' => $userId]);

        login_user($userId);
        try {
            send_account_phone_verification_code([
                'id'    => $userId,
                'phone' => $normalizedPhone,
            ]);
        } catch (Throwable $exception) {
            log_activity('sms.otp_send_failed', ['user_id' => $userId, 'error' => $exception->getMessage()], 'WARN');
            flash('warning', 'Account created. We could not send the SMS code — use the Resend button on the next page.');
        }

        write_audit_log('user_registered', 'users', (string) $userId, 'New voter account registered.', null, ['email' => $email]);
        clear_old_input();
        flash('success', 'Your account has been created. Verify your phone number to unlock event registration.');
        redirect('/auth/verify-phone.php');
    }

    flash_errors($errors);
}

$pageTitle       = 'Create Account';
$pageDescription = 'Register as a Verivote voter and verify your phone number.';
$activeNav       = 'login';

$minLen          = (int) get_site_setting('password_min_length', 10);
$reqUpper        = (bool) get_site_setting('password_require_uppercase', true);
$reqLower        = (bool) get_site_setting('password_require_lowercase', true);
$reqNum          = (bool) get_site_setting('password_require_numbers', true);
$reqSpecial      = (bool) get_site_setting('password_require_special', false);

$passwordHints = ['At least ' . $minLen . ' characters'];
if ($reqUpper)   $passwordHints[] = 'one uppercase letter';
if ($reqLower)   $passwordHints[] = 'one lowercase letter';
if ($reqNum)     $passwordHints[] = 'one number';
if ($reqSpecial) $passwordHints[] = 'one special character';

include dirname(__DIR__) . '/includes/header.php';
?>
<section class="auth-shell" data-reveal>
    <div class="auth-shell__visual">
        <div>
            <span class="eyebrow">Voter onboarding</span>
            <h1>Create your account</h1>
            <p style="margin-top:12px;">Public registration creates voter accounts only. Privileged accounts are managed by a super admin.</p>
        </div>
        <div class="process-steps" style="margin-top: auto;">
            <div class="process-step">
                <div>
                    <strong>Step 1 — Create account</strong>
                    <p>Your name, email, phone, and a secure password.</p>
                </div>
            </div>
            <div class="process-step">
                <div>
                    <strong>Step 2 — Verify phone</strong>
                    <p>A 6-digit code is sent via SMS to confirm you own the number.</p>
                </div>
            </div>
            <div class="process-step">
                <div>
                    <strong>Step 3 — Register for elections</strong>
                    <p>Upload identity documents and pass the event's verification pipeline.</p>
                </div>
            </div>
            <div class="process-step">
                <div>
                    <strong>Step 4 — Receive token &amp; vote</strong>
                    <p>Once approved, a single-use ballot token is delivered to you.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="auth-shell__form">
        <div>
            <h2>Set up your account</h2>
            <p style="margin-top:6px;">All fields are required. Use a phone number that can receive SMS.</p>
        </div>

        <form method="post" class="form-grid">
            <?= csrf_field(); ?>

            <div class="field field--full">
                <label for="full_name">Full name</label>
                <input id="full_name" type="text" name="full_name"
                       value="<?= e(old_input('full_name')); ?>"
                       placeholder="As it appears on your identity document"
                       autocomplete="name" required>
            </div>

            <div class="field field--full">
                <label for="email">Email address</label>
                <input id="email" type="email" name="email"
                       value="<?= e(old_input('email')); ?>"
                       placeholder="name@example.com"
                       autocomplete="email" required>
            </div>

            <div class="field field--full">
                <label for="phone">Phone number</label>
                <input id="phone" type="tel" name="phone"
                       value="<?= e(old_input('phone')); ?>"
                       placeholder="+961 71 000 000"
                       autocomplete="tel" required>
                <span class="helper-text">Include country code (e.g. +961 for Lebanon). A 6-digit SMS code will be sent to verify this number.</span>
            </div>

            <div class="field field--full register-divider">
                <span class="helper-text" style="display:block;padding-top:4px;border-top:1px solid var(--rule-soft);">Password</span>
            </div>

            <div class="field">
                <label for="password">Password</label>
                <input id="password" type="password" name="password"
                       autocomplete="new-password" required>
                <span class="helper-text"><?= e(implode(', ', $passwordHints)); ?>.</span>
            </div>

            <div class="field">
                <label for="password_confirmation">Confirm password</label>
                <input id="password_confirmation" type="password" name="password_confirmation"
                       autocomplete="new-password" required>
            </div>

            <div class="field field--full">
                <button class="button button--primary" type="submit">Create account &rarr;</button>
            </div>
        </form>

        <div class="auth-form-footer">
            Already have an account?
            <a href="<?= e(base_url('/auth/login.php')); ?>">Log in</a>
            <span>&middot;</span>
            <a class="muted" href="<?= e(base_url('/auth/forgot-password.php')); ?>">Forgot password</a>
        </div>
    </div>
</section>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
