<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_role('super_admin');

if (is_post_request()) {
    verify_csrf_or_fail();

    $intSettings = [
        'password_min_length'            => ['min' => 6,  'max' => 128],
        'verification_code_expiry_minutes' => ['min' => 5,  'max' => 60],
        'max_verification_code_attempts'  => ['min' => 3,  'max' => 20],
        'max_login_attempts'             => ['min' => 3,  'max' => 20],
        'login_lockout_window_minutes'   => ['min' => 5,  'max' => 120],
        'password_reset_expiry_minutes'  => ['min' => 10, 'max' => 1440],
    ];

    $boolSettings = [
        'password_require_uppercase',
        'password_require_lowercase',
        'password_require_numbers',
        'password_require_special',
        'maintenance_mode',
        'allow_voter_self_registration',
    ];

    $errors = [];

    foreach ($intSettings as $key => $bounds) {
        $raw = (int) ($_POST[$key] ?? 0);
        if ($raw < $bounds['min'] || $raw > $bounds['max']) {
            $errors[] = ucwords(str_replace('_', ' ', $key)) . ' must be between ' . $bounds['min'] . ' and ' . $bounds['max'] . '.';
        }
    }

    if ($errors === []) {
        foreach ($intSettings as $key => $bounds) {
            set_site_setting($key, (string) (int) $_POST[$key]);
        }
        foreach ($boolSettings as $key) {
            set_site_setting($key, isset($_POST[$key]) ? '1' : '0');
        }

        write_audit_log('site_settings_updated', 'system', '0', 'Site settings updated by admin.', null);
        flash('success', 'Settings saved.');
    } else {
        flash_errors($errors);
    }

    redirect('/admin/site_settings.php');
}

$s = get_all_site_settings();

$pageTitle       = 'Site Settings';
$pageHeading     = 'Site Settings';
$pageDescription = 'Configure platform-wide security and behaviour settings.';
$isDashboard     = true;
$sidebarContext  = 'super_admin';
$activeSidebar   = 'admin-site-settings';

include dirname(__DIR__) . '/includes/header.php';
?>
<form method="post">
    <?= csrf_field(); ?>

    <section class="grid-2">
        <article class="panel">
            <span class="eyebrow">Password Policy</span>
            <h2>Password requirements</h2>
            <div class="form-grid">
                <div class="field field--full">
                    <label for="password_min_length">Minimum length (characters)</label>
                    <input id="password_min_length" type="number" name="password_min_length"
                           value="<?= e((string) ($s['password_min_length'] ?? 10)); ?>" min="6" max="128" required>
                </div>
                <div class="field field--full">
                    <label class="toggle-label">
                        <input type="checkbox" name="password_require_uppercase" value="1"
                            <?= !empty($s['password_require_uppercase']) ? 'checked' : ''; ?>>
                        Require uppercase letter
                    </label>
                </div>
                <div class="field field--full">
                    <label class="toggle-label">
                        <input type="checkbox" name="password_require_lowercase" value="1"
                            <?= !empty($s['password_require_lowercase']) ? 'checked' : ''; ?>>
                        Require lowercase letter
                    </label>
                </div>
                <div class="field field--full">
                    <label class="toggle-label">
                        <input type="checkbox" name="password_require_numbers" value="1"
                            <?= !empty($s['password_require_numbers']) ? 'checked' : ''; ?>>
                        Require number
                    </label>
                </div>
                <div class="field field--full">
                    <label class="toggle-label">
                        <input type="checkbox" name="password_require_special" value="1"
                            <?= !empty($s['password_require_special']) ? 'checked' : ''; ?>>
                        Require special character
                    </label>
                </div>
            </div>
        </article>

        <article class="panel">
            <span class="eyebrow">Rate Limiting &amp; Lockout</span>
            <h2>Login security</h2>
            <div class="form-grid">
                <div class="field field--full">
                    <label for="max_login_attempts">Max login attempts before lockout</label>
                    <input id="max_login_attempts" type="number" name="max_login_attempts"
                           value="<?= e((string) ($s['max_login_attempts'] ?? 6)); ?>" min="3" max="20" required>
                </div>
                <div class="field field--full">
                    <label for="login_lockout_window_minutes">Lockout window (minutes)</label>
                    <input id="login_lockout_window_minutes" type="number" name="login_lockout_window_minutes"
                           value="<?= e((string) ($s['login_lockout_window_minutes'] ?? 15)); ?>" min="5" max="120" required>
                </div>
                <div class="field field--full">
                    <label for="password_reset_expiry_minutes">Password reset link expiry (minutes)</label>
                    <input id="password_reset_expiry_minutes" type="number" name="password_reset_expiry_minutes"
                           value="<?= e((string) ($s['password_reset_expiry_minutes'] ?? 30)); ?>" min="10" max="1440" required>
                </div>
            </div>
        </article>
    </section>

    <section class="grid-2">
        <article class="panel">
            <span class="eyebrow">Verification</span>
            <h2>Code settings</h2>
            <div class="form-grid">
                <div class="field field--full">
                    <label for="verification_code_expiry_minutes">Verification code expiry (minutes)</label>
                    <input id="verification_code_expiry_minutes" type="number" name="verification_code_expiry_minutes"
                           value="<?= e((string) ($s['verification_code_expiry_minutes'] ?? 15)); ?>" min="5" max="60" required>
                    <span class="helper-text">Applies to both account phone/email OTPs and event-level verification codes.</span>
                </div>
                <div class="field field--full">
                    <label for="max_verification_code_attempts">Max code attempts per window</label>
                    <input id="max_verification_code_attempts" type="number" name="max_verification_code_attempts"
                           value="<?= e((string) ($s['max_verification_code_attempts'] ?? 6)); ?>" min="3" max="20" required>
                </div>
            </div>
        </article>

        <article class="panel">
            <span class="eyebrow">Platform</span>
            <h2>General behaviour</h2>
            <div class="form-grid">
                <div class="field field--full">
                    <label class="toggle-label">
                        <input type="checkbox" name="allow_voter_self_registration" value="1"
                            <?= !empty($s['allow_voter_self_registration']) ? 'checked' : ''; ?>>
                        Allow voter self-registration
                    </label>
                    <span class="helper-text">When off, only admin-created accounts can register for events.</span>
                </div>
                <div class="field field--full">
                    <label class="toggle-label">
                        <input type="checkbox" name="maintenance_mode" value="1"
                            <?= !empty($s['maintenance_mode']) ? 'checked' : ''; ?>>
                        Maintenance mode
                    </label>
                    <span class="helper-text">When enabled, show a maintenance notice to non-admin visitors.</span>
                </div>
            </div>
        </article>
    </section>

    <div class="panel" style="display:flex;justify-content:flex-end;gap:12px;">
        <button class="button button--primary" type="submit">Save all settings</button>
    </div>
</form>

<section class="panel">
    <span class="eyebrow">Current values</span>
    <h2>Active configuration</h2>
    <div class="list-shell">
        <?php foreach ($s as $key => $value): ?>
            <?php if ($key === 'bypass_password_validation') continue; ?>
            <div class="list-row">
                <strong style="font-family:ui-monospace,monospace;font-size:0.82rem;"><?= e($key); ?></strong>
                <span class="badge <?= $value === true || $value === 1 ? 'badge-success' : ($value === false || $value === 0 ? 'badge-muted' : 'badge-muted'); ?>">
                    <?= e(is_bool($value) ? ($value ? 'true' : 'false') : (string) $value); ?>
                </span>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
