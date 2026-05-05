<?php
declare(strict_types=1);

function refresh_current_user(): ?array
{
    unset($_SESSION['cached_user']);

    return current_user(true);
}

function current_user(bool $refresh = false): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    if (!$refresh && isset($_SESSION['cached_user'])) {
        return $_SESSION['cached_user'];
    }

    $user = fetch_one(
        'SELECT users.*, roles.slug AS role_slug, roles.name AS role_name
         FROM users
         INNER JOIN roles ON roles.id = users.role_id
         WHERE users.id = :user_id',
        ['user_id' => (int) $_SESSION['user_id']]
    );

    if (!$user) {
        logout_user();

        return null;
    }

    $_SESSION['cached_user'] = $user;

    return $user;
}

function current_role_slug(): ?string
{
    return current_user()['role_slug'] ?? null;
}

function login_user(int $userId): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    refresh_current_user();
    execute_statement('UPDATE users SET last_login_at = NOW(), failed_login_count = 0 WHERE id = :id', ['id' => $userId]);
    log_activity('auth.login_success', ['user_id' => $userId]);
}

function logout_user(): void
{
    if (!empty($_SESSION['user_id'])) {
        log_activity('auth.logout', ['user_id' => (int) $_SESSION['user_id']]);
    }
    unset($_SESSION['cached_user']);
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

function has_role(string|array $roleSlug): bool
{
    $current = current_role_slug();

    if ($current === null) {
        return false;
    }

    if (is_array($roleSlug)) {
        return in_array($current, $roleSlug, true);
    }

    return $current === $roleSlug;
}

function require_login(): void
{
    if (!current_user()) {
        flash('error', 'Please sign in to continue.');
        redirect('/auth/login.php');
    }
}

function require_role(string|array $roles): void
{
    require_login();

    if (!has_role($roles)) {
        http_response_code(403);
        flash('error', 'You do not have permission to access that page.');
        redirect(dashboard_home_for_role((string) current_role_slug()));
    }
}

function user_can_manage_event(int $eventId, ?string $permission = null): bool
{
    $user = current_user();

    if (!$user) {
        return false;
    }

    if ($user['role_slug'] === 'super_admin') {
        return true;
    }

    $eventAdmin = fetch_one(
        'SELECT * FROM event_admins WHERE event_id = :event_id AND user_id = :user_id',
        ['event_id' => $eventId, 'user_id' => $user['id']]
    );

    if ($eventAdmin) {
        if ($permission === null || $eventAdmin['assignment_type'] === 'owner' || !$eventAdmin['permissions_json']) {
            return true;
        }

        $permissions = json_decode((string) $eventAdmin['permissions_json'], true) ?: [];

        return (bool) ($permissions[$permission] ?? false);
    }

    $coAdmin = fetch_one(
        'SELECT * FROM co_admins WHERE event_id = :event_id AND user_id = :user_id AND is_active = 1',
        ['event_id' => $eventId, 'user_id' => $user['id']]
    );

    if ($coAdmin) {
        $permissions = json_decode((string) $coAdmin['permissions_json'], true) ?: [];

        return $permission === null ? true : (bool) ($permissions[$permission] ?? false);
    }

    return false;
}

function user_can_verify_event(int $eventId): bool
{
    $user = current_user();

    if (!$user) {
        return false;
    }

    if ($user['role_slug'] === 'super_admin' || user_can_manage_event($eventId, 'review_verifications')) {
        return true;
    }

    $verifier = fetch_one(
        'SELECT * FROM verifiers WHERE event_id = :event_id AND user_id = :user_id AND is_active = 1',
        ['event_id' => $eventId, 'user_id' => $user['id']]
    );

    return $verifier !== null;
}

function user_can_access_event_evidence(int $eventId): bool
{
    $user = current_user();

    if (!$user) {
        return false;
    }

    if ($user['role_slug'] === 'super_admin') {
        return true;
    }

    return user_can_manage_event($eventId, 'review_verifications') || user_can_verify_event($eventId);
}

function require_event_permission(int $eventId, ?string $permission = null): void
{
    if (!user_can_manage_event($eventId, $permission)) {
        http_response_code(403);
        flash('error', 'You do not have access to that event operation.');
        redirect(dashboard_home_for_role((string) current_role_slug()));
    }
}

function attempt_login(string $identifier, string $password): bool
{
    $identifier = trim($identifier);
    $normalizedEmail = normalize_email($identifier);
    $normalizedPhone = normalize_phone_number($identifier);
    $rateLimitSubject = ($normalizedPhone ?? $normalizedEmail) . '|' . client_ip();

    $limit = consume_rate_limit(
        'login',
        $rateLimitSubject,
        get_site_setting('max_login_attempts', (int) app_config('security.login_attempts')),
        get_site_setting('login_lockout_window_minutes', 15) * 60
    );

    if (!$limit['allowed']) {
        log_activity('auth.login_rate_limited', ['identifier' => $identifier], 'WARN');
        flash('error', 'Too many login attempts. Please wait ' . $limit['retry_after'] . ' seconds.');

        return false;
    }

    $user = null;

    if ($normalizedPhone !== null) {
        $user = fetch_one(
            'SELECT users.*, roles.slug AS role_slug
             FROM users
             INNER JOIN roles ON roles.id = users.role_id
             WHERE users.phone = :phone
             LIMIT 1',
            ['phone' => $normalizedPhone]
        );
    }

    if ($user === null && filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL)) {
        $user = fetch_one(
            'SELECT users.*, roles.slug AS role_slug
             FROM users
             INNER JOIN roles ON roles.id = users.role_id
             WHERE users.email = :email
             LIMIT 1',
            ['email' => $normalizedEmail]
        );
    }

    if (!$user || !password_verify($password, $user['password_hash'])) {
        if ($user) {
            execute_statement(
                'UPDATE users SET failed_login_count = failed_login_count + 1 WHERE id = :id',
                ['id' => $user['id']]
            );
        }

        log_activity('auth.login_failed', [
            'identifier' => $identifier,
            'matched_user_id' => $user ? (int) $user['id'] : null,
        ], 'WARN');
        flash('error', 'Invalid login credentials.');

        return false;
    }

    if ($user['status'] !== 'active') {
        log_activity('auth.login_blocked_inactive', [
            'user_id' => (int) $user['id'],
            'status' => $user['status'],
        ], 'WARN');
        flash('error', 'Your account is not active yet.');

        return false;
    }

    clear_rate_limit('login', $rateLimitSubject);
    login_user((int) $user['id']);

    return true;
}
