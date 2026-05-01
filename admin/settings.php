<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_role('super_admin');

if (is_post_request()) {
    verify_csrf_or_fail();

    $current = load_all_test_settings();
    $current['bypass_password_validation'] = isset($_POST['bypass_password_validation']);
    save_test_settings($current);

    write_audit_log(
        'test_settings_updated',
        'system',
        '0',
        'Test settings updated.',
        null,
        ['bypass_password_validation' => $current['bypass_password_validation']]
    );

    flash('success', 'Test settings saved.');
    redirect('/admin/settings.php');
}

$settings = load_all_test_settings();
$bypassEnabled = (bool) ($settings['bypass_password_validation'] ?? false);

$pageTitle = 'Test Settings';
$pageHeading = 'Test Settings';
$pageDescription = 'Development and testing overrides. These settings are not safe for production.';
$isDashboard = true;
$sidebarContext = 'super_admin';
$activeSidebar = 'admin-settings';

include dirname(__DIR__) . '/includes/header.php';
?>
<section class="grid-2">
    <article class="panel">
        <span class="eyebrow">Security Overrides</span>
        <h2>Password constraints</h2>
        <form method="post" class="form-grid">
            <?= csrf_field(); ?>
            <div class="field field--full">
                <label class="toggle-label">
                    <input type="checkbox" name="bypass_password_validation" value="1"<?= $bypassEnabled ? ' checked' : ''; ?>>
                    Bypass password validation
                </label>
                <span class="helper-text">When enabled, any password is accepted — no length or complexity requirements. For testing only.</span>
            </div>
            <div class="field field--full">
                <button class="button button--primary" type="submit">Save settings</button>
            </div>
        </form>
    </article>

    <article class="panel">
        <span class="eyebrow">Current State</span>
        <h2>Active overrides</h2>
        <div class="list-shell">
            <div class="list-row">
                <div>
                    <strong>Password validation bypass</strong>
                    <p>Skips minimum length and complexity checks on all password fields.</p>
                </div>
                <span class="badge <?= $bypassEnabled ? 'badge-danger' : 'badge-success'; ?>">
                    <?= $bypassEnabled ? 'ON' : 'OFF'; ?>
                </span>
            </div>
        </div>
    </article>
</section>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
