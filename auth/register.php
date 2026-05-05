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
            flash('warning', 'Account created, but OTP SMS failed: ' . $exception->getMessage() . ' Use resend on the verification page.');
        }

        write_audit_log('user_registered', 'users', (string) $userId, 'New voter account registered.', null, ['email' => $email]);
        clear_old_input();
        flash('success', 'Your account has been created. Verify your phone number to unlock event registration.');
        redirect('/auth/verify-email.php');
    }

    flash_errors($errors);
}

$pageTitle       = 'Create Account';
$pageDescription = 'Register as a Verivote voter and verify your phone number.';

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
                    <strong>Create account</strong>
                    <p>Provide your name, email, phone number, and a secure password.</p>
                </div>
            </div>
            <div class="process-step">
                <div>
                    <strong>Verify phone</strong>
                    <p>An OTP is sent to your number to confirm ownership before you can register for events.</p>
                </div>
            </div>
            <div class="process-step">
                <div>
                    <strong>Register for events</strong>
                    <p>Submit the required fields and pass the event's verification pipeline to receive a ballot token.</p>
                </div>
            </div>
        </div>
    </div>
    <div class="auth-shell__form">
        <div>
            <h2>Set up your account</h2>
            <p style="margin-top:6px;">All fields are required. Your phone number must be reachable.</p>
        </div>
        <form method="post" class="form-grid">
            <?= csrf_field(); ?>
            <div class="field field--full">
                <label for="full_name">Full name</label>
                <input id="full_name" type="text" name="full_name" value="<?= e(old_input('full_name')); ?>" autocomplete="name" required>
            </div>
            <div class="field">
                <label for="email">Email address</label>
                <input id="email" type="email" name="email" value="<?= e(old_input('email')); ?>" autocomplete="email" required>
            </div>
            <div class="field">
                <label for="phone">Phone number</label>
                <input id="phone" type="text" name="phone" value="<?= e(old_input('phone')); ?>" placeholder="+961..." autocomplete="tel" required>
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input id="password" type="password" name="password" autocomplete="new-password" required>
            </div>
            <div class="field">
                <label for="password_confirmation">Confirm password</label>
                <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required>
            </div>
            <div class="field field--full">
                <span class="helper-text">Minimum <?= e((string) get_site_setting('password_min_length', 10)); ?> characters with upper, lower, and numeric characters required.</span>
            </div>
            <div class="field field--full">
                <button class="button button--primary" type="submit">Create account</button>
            </div>
        </form>
        <div class="auth-form-footer">
            Already have an account? <a href="<?= e(base_url('/auth/login.php')); ?>">Log in</a>
        </div>
    </div>
</section>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
