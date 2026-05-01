<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_role('super_admin');

if (is_post_request()) {
    verify_csrf_or_fail();

    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $email = normalize_email((string) ($_POST['email'] ?? ''));
    $phoneInput = trim((string) ($_POST['phone'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $roleId = (int) ($_POST['role_id'] ?? 0);
    $errors = [];

    validate_required('Full name', $fullName, $errors);
    validate_required('Email address', $email, $errors);
    validate_email_input('Email address', $email, $errors);
    validate_required('Phone number', $phoneInput, $errors);
    $normalizedPhone = validate_phone_input('Phone number', $phoneInput, $errors);
    validate_password_input($password, $errors);

    $role = fetch_one('SELECT * FROM roles WHERE id = :id', ['id' => $roleId]);
    if (!$role || !in_array($role['slug'], ['event_creator', 'co_admin', 'verifier'], true)) {
        $errors[] = 'Choose a valid privileged role.';
    }

    if (fetch_one('SELECT id FROM users WHERE email = :email', ['email' => $email])) {
        $errors[] = 'That email address is already in use.';
    }

    if ($normalizedPhone !== null && fetch_one('SELECT id FROM users WHERE phone = :phone', ['phone' => $normalizedPhone])) {
        $errors[] = 'That phone number is already in use.';
    }

    if ($errors === []) {
        execute_statement(
            'INSERT INTO users (role_id, full_name, email, phone, password_hash, status, email_verified_at, phone_verified_at)
             VALUES (:role_id, :full_name, :email, :phone, :password_hash, "active", NOW(), NOW())',
            [
                'role_id' => $roleId,
                'full_name' => $fullName,
                'email' => $email,
                'phone' => $normalizedPhone,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]
        );
        $newUserId = (int) db()->lastInsertId();
        write_audit_log('privileged_user_created', 'users', (string) $newUserId, 'Privileged user created.', null, ['role' => $role['slug']]);
        flash('success', 'Privileged user created successfully.');
        redirect('/admin/users.php');
    }

    flash_errors($errors);
}

$roles = fetch_all('SELECT * FROM roles WHERE slug IN ("event_creator", "co_admin", "verifier") ORDER BY id ASC');
$users = fetch_all(
    'SELECT users.*, roles.name AS role_name, roles.slug AS role_slug
     FROM users
     INNER JOIN roles ON roles.id = users.role_id
     WHERE roles.slug IN ("event_creator", "co_admin", "verifier")
     ORDER BY users.id DESC'
);

$pageTitle = 'Privileged Users';
$pageHeading = 'Privileged Users';
$pageDescription = 'Create and monitor event creators, co-admins, and verifiers.';
$isDashboard = true;
$sidebarContext = 'super_admin';
$activeSidebar = 'admin-users';

include dirname(__DIR__) . '/includes/header.php';
?>
<section class="grid-2">
    <article class="panel">
        <span class="eyebrow">Create Privileged Account</span>
        <h2>New operator</h2>
        <form method="post" class="form-grid">
            <?= csrf_field(); ?>
            <div class="field field--full">
                <label for="full_name">Full name</label>
                <input id="full_name" type="text" name="full_name" required>
            </div>
            <div class="field">
                <label for="email">Email</label>
                <input id="email" type="email" name="email" required>
            </div>
            <div class="field">
                <label for="phone">Phone</label>
                <input id="phone" type="text" name="phone" placeholder="+961..." required>
            </div>
            <div class="field">
                <label for="role_id">Role</label>
                <select id="role_id" name="role_id" required>
                    <option value="">Select role</option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= e((string) $role['id']); ?>"><?= e($role['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="password">Temporary password</label>
                <input id="password" type="password" name="password" required>
            </div>
            <div class="field field--full">
                <button class="button button--primary" type="submit">Create user</button>
            </div>
        </form>
    </article>

    <article class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>User</th>
                <th>Role</th>
                <th>Status</th>
                <th>Phone Verified</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $account): ?>
                <tr>
                    <td>
                        <strong><?= e($account['full_name']); ?></strong>
                        <p><?= e($account['email']); ?></p>
                    </td>
                    <td><?= e($account['role_name']); ?></td>
                    <td><span class="badge <?= e(badge_class($account['status'])); ?>"><?= e(format_status($account['status'])); ?></span></td>
                    <td><?= !empty($account['phone_verified_at']) ? 'Yes' : 'No'; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </article>
</section>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
