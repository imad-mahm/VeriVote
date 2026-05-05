<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_role('super_admin');

if (is_post_request()) {
    verify_csrf_or_fail();

    // --- Password policy ---
    $minLen = max(6, min(128, (int) ($_POST['password_min_length'] ?? 10)));
    set_site_setting('password_min_length', (string) $minLen);
    set_site_setting('password_require_uppercase', isset($_POST['password_require_uppercase']) ? '1' : '0');
    set_site_setting('password_require_lowercase', isset($_POST['password_require_lowercase']) ? '1' : '0');
    set_site_setting('password_require_numbers', isset($_POST['password_require_numbers']) ? '1' : '0');
    set_site_setting('password_require_special', isset($_POST['password_require_special']) ? '1' : '0');
    set_site_setting('bypass_password_validation', isset($_POST['bypass_password_validation']) ? '1' : '0');

    // --- Verification ---
    $verifyMethod = in_array($_POST['account_verification_method'] ?? '', ['sms', 'email'], true)
        ? (string) $_POST['account_verification_method']
        : 'sms';
    set_site_setting('account_verification_method', $verifyMethod);
    $codeExpiry = max(5, min(60, (int) ($_POST['verification_code_expiry_minutes'] ?? 15)));
    set_site_setting('verification_code_expiry_minutes', (string) $codeExpiry);
    $codeAttempts = max(1, min(10, (int) ($_POST['max_verification_code_attempts'] ?? 6)));
    set_site_setting('max_verification_code_attempts', (string) $codeAttempts);

    // --- Security / rate-limiting ---
    $maxLogins = max(1, min(20, (int) ($_POST['max_login_attempts'] ?? 6)));
    set_site_setting('max_login_attempts', (string) $maxLogins);
    $loginWindow = max(1, min(1440, (int) ($_POST['login_lockout_window_minutes'] ?? 15)));
    set_site_setting('login_lockout_window_minutes', (string) $loginWindow);
    $resetExpiry = max(5, min(1440, (int) ($_POST['password_reset_expiry_minutes'] ?? 30)));
    set_site_setting('password_reset_expiry_minutes', (string) $resetExpiry);

    // --- Platform ---
    $platformName = trim((string) ($_POST['platform_name'] ?? ''));
    if ($platformName === '') {
        $platformName = 'Verivote';
    }
    set_site_setting('platform_name', $platformName);
    $countryCode = preg_replace('/\D+/', '', (string) ($_POST['default_country_code'] ?? '961')) ?: '961';
    set_site_setting('default_country_code', $countryCode);
    set_site_setting('allow_voter_self_registration', isset($_POST['allow_voter_self_registration']) ? '1' : '0');
    set_site_setting('maintenance_mode', isset($_POST['maintenance_mode']) ? '1' : '0');

    write_audit_log(
        'site_config_updated',
        'system',
        '0',
        'Site configuration updated.',
        null,
        [
            'password_min_length'             => $minLen,
            'account_verification_method'     => $verifyMethod,
            'max_login_attempts'              => $maxLogins,
            'maintenance_mode'                => isset($_POST['maintenance_mode']),
        ]
    );

    flash('success', 'Configuration saved.');
    redirect('/admin/config.php');
}

$s = get_all_site_settings();

$cfg = [
    'password_min_length'             => $s['password_min_length']             ?? 10,
    'password_require_uppercase'      => $s['password_require_uppercase']      ?? true,
    'password_require_lowercase'      => $s['password_require_lowercase']      ?? true,
    'password_require_numbers'        => $s['password_require_numbers']        ?? true,
    'password_require_special'        => $s['password_require_special']        ?? false,
    'bypass_password_validation'      => $s['bypass_password_validation']      ?? false,
    'account_verification_method'     => $s['account_verification_method']     ?? 'sms',
    'verification_code_expiry_minutes'=> $s['verification_code_expiry_minutes']?? 15,
    'max_verification_code_attempts'  => $s['max_verification_code_attempts']  ?? 6,
    'max_login_attempts'              => $s['max_login_attempts']              ?? 6,
    'login_lockout_window_minutes'    => $s['login_lockout_window_minutes']    ?? 15,
    'password_reset_expiry_minutes'   => $s['password_reset_expiry_minutes']   ?? 30,
    'platform_name'                   => $s['platform_name']                   ?? 'Verivote',
    'default_country_code'            => $s['default_country_code']            ?? '961',
    'allow_voter_self_registration'   => $s['allow_voter_self_registration']   ?? true,
    'maintenance_mode'                => $s['maintenance_mode']                ?? false,
];

$pageTitle       = 'Site Configuration';
$pageHeading     = 'Site Configuration';
$pageDescription = 'Manage platform-wide settings: password policy, verification channels, security limits, and general options.';
$isDashboard     = true;
$sidebarContext  = 'super_admin';
$activeSidebar   = 'admin-config';

include dirname(__DIR__) . '/includes/header.php';
?>

<form method="post">
    <?= csrf_field(); ?>

    <?php /* ── Password Policy ─────────────────────────────────────────── */ ?>
    <section class="grid-2">
        <article class="panel">
            <span class="eyebrow">Password Policy</span>
            <h2>Password constraints</h2>

            <div class="form-grid">
                <div class="field">
                    <label for="password_min_length">Minimum length</label>
                    <input id="password_min_length" type="number" name="password_min_length"
                           value="<?= e((string) $cfg['password_min_length']); ?>"
                           min="6" max="128" required>
                    <span class="helper-text">Between 6 and 128 characters.</span>
                </div>

                <div class="field">
                    <label class="toggle-label">
                        <input type="checkbox" name="password_require_uppercase" value="1"
                               <?= $cfg['password_require_uppercase'] ? 'checked' : ''; ?>>
                        Require uppercase letter (A–Z)
                    </label>
                </div>

                <div class="field">
                    <label class="toggle-label">
                        <input type="checkbox" name="password_require_lowercase" value="1"
                               <?= $cfg['password_require_lowercase'] ? 'checked' : ''; ?>>
                        Require lowercase letter (a–z)
                    </label>
                </div>

                <div class="field">
                    <label class="toggle-label">
                        <input type="checkbox" name="password_require_numbers" value="1"
                               <?= $cfg['password_require_numbers'] ? 'checked' : ''; ?>>
                        Require numeric character (0–9)
                    </label>
                </div>

                <div class="field">
                    <label class="toggle-label">
                        <input type="checkbox" name="password_require_special" value="1"
                               <?= $cfg['password_require_special'] ? 'checked' : ''; ?>>
                        Require special character (!@#$…)
                    </label>
                </div>

                <div class="field field--full">
                    <label class="toggle-label">
                        <input type="checkbox" name="bypass_password_validation" value="1"
                               <?= $cfg['bypass_password_validation'] ? 'checked' : ''; ?>>
                        Bypass all password validation
                    </label>
                    <span class="helper-text">When enabled any password is accepted — no length or complexity checks. For testing only.</span>
                </div>
            </div>
        </article>

        <article class="panel">
            <span class="eyebrow">Current Rules</span>
            <h2>Active password policy</h2>
            <div class="list-shell">
                <div class="list-row">
                    <div><strong>Minimum length</strong></div>
                    <span class="badge badge-muted"><?= e((string) $cfg['password_min_length']); ?> chars</span>
                </div>
                <div class="list-row">
                    <div><strong>Uppercase required</strong></div>
                    <span class="badge <?= $cfg['password_require_uppercase'] ? 'badge-success' : 'badge-muted'; ?>">
                        <?= $cfg['password_require_uppercase'] ? 'Yes' : 'No'; ?>
                    </span>
                </div>
                <div class="list-row">
                    <div><strong>Lowercase required</strong></div>
                    <span class="badge <?= $cfg['password_require_lowercase'] ? 'badge-success' : 'badge-muted'; ?>">
                        <?= $cfg['password_require_lowercase'] ? 'Yes' : 'No'; ?>
                    </span>
                </div>
                <div class="list-row">
                    <div><strong>Numbers required</strong></div>
                    <span class="badge <?= $cfg['password_require_numbers'] ? 'badge-success' : 'badge-muted'; ?>">
                        <?= $cfg['password_require_numbers'] ? 'Yes' : 'No'; ?>
                    </span>
                </div>
                <div class="list-row">
                    <div><strong>Special chars required</strong></div>
                    <span class="badge <?= $cfg['password_require_special'] ? 'badge-success' : 'badge-muted'; ?>">
                        <?= $cfg['password_require_special'] ? 'Yes' : 'No'; ?>
                    </span>
                </div>
                <div class="list-row">
                    <div><strong>Validation bypassed</strong></div>
                    <span class="badge <?= $cfg['bypass_password_validation'] ? 'badge-danger' : 'badge-success'; ?>">
                        <?= $cfg['bypass_password_validation'] ? 'Yes — unsafe' : 'No'; ?>
                    </span>
                </div>
            </div>
        </article>
    </section>

    <?php /* ── Verification ──────────────────────────────────────────────── */ ?>
    <section class="grid-2">
        <article class="panel">
            <span class="eyebrow">Verification</span>
            <h2>Account verification</h2>

            <div class="form-grid">
                <div class="field field--full">
                    <label for="account_verification_method">OTP channel for new accounts</label>
                    <select id="account_verification_method" name="account_verification_method">
                        <option value="sms"   <?= $cfg['account_verification_method'] === 'sms'   ? 'selected' : ''; ?>>SMS (text message)</option>
                        <option value="email" <?= $cfg['account_verification_method'] === 'email' ? 'selected' : ''; ?>>Email</option>
                    </select>
                    <span class="helper-text">Channel used to send a one-time code when voters register.</span>
                </div>

                <div class="field">
                    <label for="verification_code_expiry_minutes">Code expiry (minutes)</label>
                    <input id="verification_code_expiry_minutes" type="number"
                           name="verification_code_expiry_minutes"
                           value="<?= e((string) $cfg['verification_code_expiry_minutes']); ?>"
                           min="5" max="60" required>
                    <span class="helper-text">How long a verification code stays valid (5–60 min).</span>
                </div>

                <div class="field">
                    <label for="max_verification_code_attempts">Max code attempts</label>
                    <input id="max_verification_code_attempts" type="number"
                           name="max_verification_code_attempts"
                           value="<?= e((string) $cfg['max_verification_code_attempts']); ?>"
                           min="1" max="10" required>
                    <span class="helper-text">Failed OTP submissions allowed before lockout (1–10).</span>
                </div>
            </div>
        </article>

        <article class="panel">
            <span class="eyebrow">Current State</span>
            <h2>Active verification settings</h2>
            <div class="list-shell">
                <div class="list-row">
                    <div><strong>OTP channel</strong></div>
                    <span class="badge badge-muted"><?= e(strtoupper($cfg['account_verification_method'])); ?></span>
                </div>
                <div class="list-row">
                    <div><strong>Code expiry</strong></div>
                    <span class="badge badge-muted"><?= e((string) $cfg['verification_code_expiry_minutes']); ?> min</span>
                </div>
                <div class="list-row">
                    <div><strong>Max attempts</strong></div>
                    <span class="badge badge-muted"><?= e((string) $cfg['max_verification_code_attempts']); ?></span>
                </div>
            </div>
        </article>
    </section>

    <?php /* ── Security / Rate-limiting ───────────────────────────────────── */ ?>
    <section class="grid-2">
        <article class="panel">
            <span class="eyebrow">Security</span>
            <h2>Rate limits &amp; expiry</h2>

            <div class="form-grid">
                <div class="field">
                    <label for="max_login_attempts">Max login attempts</label>
                    <input id="max_login_attempts" type="number" name="max_login_attempts"
                           value="<?= e((string) $cfg['max_login_attempts']); ?>"
                           min="1" max="20" required>
                    <span class="helper-text">Failed logins before the account is temporarily rate-limited (1–20).</span>
                </div>

                <div class="field">
                    <label for="login_lockout_window_minutes">Login lockout window (minutes)</label>
                    <input id="login_lockout_window_minutes" type="number" name="login_lockout_window_minutes"
                           value="<?= e((string) $cfg['login_lockout_window_minutes']); ?>"
                           min="1" max="1440" required>
                    <span class="helper-text">How long a login lockout lasts before resetting (1–1440 min).</span>
                </div>

                <div class="field">
                    <label for="password_reset_expiry_minutes">Password reset link expiry (minutes)</label>
                    <input id="password_reset_expiry_minutes" type="number" name="password_reset_expiry_minutes"
                           value="<?= e((string) $cfg['password_reset_expiry_minutes']); ?>"
                           min="5" max="1440" required>
                    <span class="helper-text">How long a password-reset link stays valid (5–1440 min).</span>
                </div>
            </div>
        </article>

        <article class="panel">
            <span class="eyebrow">Current Limits</span>
            <h2>Active security limits</h2>
            <div class="list-shell">
                <div class="list-row">
                    <div><strong>Max login attempts</strong></div>
                    <span class="badge badge-muted"><?= e((string) $cfg['max_login_attempts']); ?></span>
                </div>
                <div class="list-row">
                    <div><strong>Lockout window</strong></div>
                    <span class="badge badge-muted"><?= e((string) $cfg['login_lockout_window_minutes']); ?> min</span>
                </div>
                <div class="list-row">
                    <div><strong>Reset link expiry</strong></div>
                    <span class="badge badge-muted"><?= e((string) $cfg['password_reset_expiry_minutes']); ?> min</span>
                </div>
            </div>
        </article>
    </section>

    <?php /* ── Platform ──────────────────────────────────────────────────── */ ?>
    <section class="grid-2">
        <article class="panel">
            <span class="eyebrow">Platform</span>
            <h2>General settings</h2>

            <div class="form-grid">
                <div class="field field--full">
                    <label for="platform_name">Platform name</label>
                    <input id="platform_name" type="text" name="platform_name"
                           value="<?= e((string) $cfg['platform_name']); ?>"
                           maxlength="100" required>
                    <span class="helper-text">Shown in notifications, SMS templates, and email subjects.</span>
                </div>

                <div class="field">
                    <label for="default_country_code">Default phone country code</label>
                    <input id="default_country_code" type="text" name="default_country_code"
                           value="<?= e((string) $cfg['default_country_code']); ?>"
                           maxlength="6" placeholder="961" required>
                    <span class="helper-text">Digits only — used when a user omits the country prefix (e.g. 961 for Lebanon).</span>
                </div>

                <div class="field">
                    <label class="toggle-label">
                        <input type="checkbox" name="allow_voter_self_registration" value="1"
                               <?= $cfg['allow_voter_self_registration'] ? 'checked' : ''; ?>>
                        Allow public voter self-registration
                    </label>
                    <span class="helper-text">When disabled, the registration page is locked and only admins can create voters.</span>
                </div>

                <div class="field field--full">
                    <label class="toggle-label">
                        <input type="checkbox" name="maintenance_mode" value="1"
                               <?= $cfg['maintenance_mode'] ? 'checked' : ''; ?>>
                        Maintenance mode
                    </label>
                    <span class="helper-text">Displays a maintenance notice to all non-admin visitors. Does not affect the admin dashboard.</span>
                </div>
            </div>
        </article>

        <article class="panel">
            <span class="eyebrow">Current State</span>
            <h2>Active platform settings</h2>
            <div class="list-shell">
                <div class="list-row">
                    <div><strong>Platform name</strong></div>
                    <span class="badge badge-muted"><?= e((string) $cfg['platform_name']); ?></span>
                </div>
                <div class="list-row">
                    <div><strong>Default country code</strong></div>
                    <span class="badge badge-muted">+<?= e((string) $cfg['default_country_code']); ?></span>
                </div>
                <div class="list-row">
                    <div><strong>Self-registration</strong></div>
                    <span class="badge <?= $cfg['allow_voter_self_registration'] ? 'badge-success' : 'badge-warning'; ?>">
                        <?= $cfg['allow_voter_self_registration'] ? 'Open' : 'Locked'; ?>
                    </span>
                </div>
                <div class="list-row">
                    <div><strong>Maintenance mode</strong></div>
                    <span class="badge <?= $cfg['maintenance_mode'] ? 'badge-danger' : 'badge-success'; ?>">
                        <?= $cfg['maintenance_mode'] ? 'ON' : 'OFF'; ?>
                    </span>
                </div>
            </div>
        </article>
    </section>

    <div style="padding-top: var(--space-2, 1rem);">
        <button class="button button--primary" type="submit">Save configuration</button>
    </div>
</form>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
