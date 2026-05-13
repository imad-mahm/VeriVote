<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_login();
if (!has_role(['voter', 'super_admin'])) {
    redirect(dashboard_home_for_role((string) current_role_slug()));
}

$user = current_user();

if (is_post_request()) {
    verify_csrf_or_fail();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'update_profile') {
        store_old_input($_POST);
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $errors = [];

        validate_required('Full name', $fullName, $errors);

        if ($errors === []) {
            execute_statement(
                'UPDATE users SET full_name = :full_name WHERE id = :id',
                ['full_name' => $fullName, 'id' => $user['id']]
            );
            write_audit_log('profile_updated', 'users', (string) $user['id'], 'Voter updated their profile.', null);
            refresh_current_user();
            clear_old_input();
            flash('success', 'Profile updated.');
        } else {
            flash_errors($errors);
        }
        redirect('/voter/account.php');
    }

    if ($action === 'change_password') {
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
        $errors = [];

        $fresh = fetch_one('SELECT password_hash FROM users WHERE id = :id', ['id' => $user['id']]);
        if (!$fresh || !password_verify($currentPassword, (string) $fresh['password_hash'])) {
            $errors[] = 'Current password is incorrect.';
        }

        validate_password_input($newPassword, $errors);

        if ($newPassword !== $confirmPassword) {
            $errors[] = 'New password confirmation does not match.';
        }

        if ($errors === []) {
            execute_statement(
                'UPDATE users SET password_hash = :hash WHERE id = :id',
                ['hash' => password_hash($newPassword, PASSWORD_DEFAULT), 'id' => $user['id']]
            );
            write_audit_log('password_changed', 'users', (string) $user['id'], 'Voter changed their password.', null);
            flash('success', 'Password changed successfully.');
        } else {
            flash_errors($errors);
        }
        redirect('/voter/account.php');
    }
}

$user = current_user();

$pageTitle = 'My Account';
$pageHeading = 'My Account';
$pageDescription = 'Manage your profile and password.';
$isDashboard = true;
$sidebarContext = 'voter';
$activeSidebar = 'voter-account';

include dirname(__DIR__) . '/includes/header.php';
?>
<section class="grid-2">
    <article class="panel">
        <span class="eyebrow">Profile</span>
        <h2>Personal details</h2>
        <form method="post" class="form-grid">
            <?= csrf_field(); ?>
            <input type="hidden" name="action" value="update_profile">
            <div class="field field--full">
                <label for="full_name">Full name</label>
                <input id="full_name" type="text" name="full_name" value="<?= e(old_input('full_name', (string) $user['full_name'])); ?>" required>
            </div>
            <div class="field field--full">
                <label>Email address</label>
                <input type="email" value="<?= e((string) $user['email']); ?>" disabled>
                <span class="helper-text">Email cannot be changed. Contact support if you need to update it.</span>
            </div>
            <div class="field field--full">
                <label>Phone number</label>
                <input type="text" value="<?= e((string) $user['phone']); ?>" disabled>
                <span class="helper-text">Phone cannot be changed after verification.</span>
            </div>
            <div class="field field--full">
                <button class="button button--primary" type="submit">Save changes</button>
            </div>
        </form>
    </article>

    <article class="panel">
        <span class="eyebrow">Security</span>
        <h2>Change password</h2>
        <form method="post" class="form-grid">
            <?= csrf_field(); ?>
            <input type="hidden" name="action" value="change_password">
            <div class="field field--full">
                <label for="current_password">Current password</label>
                <input id="current_password" type="password" name="current_password" autocomplete="current-password" required>
            </div>
            <div class="field field--full">
                <label for="new_password">New password</label>
                <input id="new_password" type="password" name="new_password" autocomplete="new-password" required>
            </div>
            <div class="field field--full">
                <label for="confirm_password">Confirm new password</label>
                <input id="confirm_password" type="password" name="confirm_password" autocomplete="new-password" required>
            </div>
            <div class="field field--full">
                <button class="button button--primary" type="submit">Change password</button>
            </div>
        </form>
    </article>
</section>

<section class="panel">
    <span class="eyebrow">Account Status</span>
    <h2>Details</h2>
    <div class="list-shell">
        <div class="list-row">
            <strong>Account status</strong>
            <span class="badge <?= e(badge_class($user['status'])); ?>"><?= e(format_status($user['status'])); ?></span>
        </div>
        <div class="list-row">
            <strong>Email verified</strong>
            <span class="badge <?= !empty($user['email_verified_at']) ? 'badge-success' : 'badge-warning'; ?>">
                <?= !empty($user['email_verified_at']) ? 'Verified' : 'Unverified'; ?>
            </span>
        </div>
        <div class="list-row">
            <strong>Phone verified</strong>
            <span class="badge <?= !empty($user['phone_verified_at']) ? 'badge-success' : 'badge-warning'; ?>">
                <?= !empty($user['phone_verified_at']) ? 'Verified ' . e(format_datetime($user['phone_verified_at'], 'M j, Y')) : 'Unverified'; ?>
            </span>
        </div>
        <div class="list-row">
            <strong>Member since</strong>
            <span><?= e(format_datetime($user['created_at'], 'M j, Y')); ?></span>
        </div>
        <div class="list-row">
            <strong>Last login</strong>
            <span><?= !empty($user['last_login_at']) ? e(format_datetime($user['last_login_at'], 'M j, Y H:i')) : '—'; ?></span>
        </div>
    </div>
</section>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
