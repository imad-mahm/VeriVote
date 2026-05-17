<?php
declare(strict_types=1);

function get_test_setting(string $key, mixed $default = null): mixed
{
    $path = BASE_PATH . '/config/test_settings.json';
    if (!is_file($path)) {
        return $default;
    }
    $data = json_decode((string) file_get_contents($path), true);
    return is_array($data) ? ($data[$key] ?? $default) : $default;
}

function save_test_settings(array $settings): void
{
    $path = BASE_PATH . '/config/test_settings.json';
    file_put_contents($path, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function load_all_test_settings(): array
{
    $path = BASE_PATH . '/config/test_settings.json';
    if (!is_file($path)) {
        return [];
    }
    $data = json_decode((string) file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

function app_config(?string $key = null, mixed $default = null): mixed
{
    global $appConfig;

    if ($key === null) {
        return $appConfig;
    }

    $segments = explode('.', $key);
    $value = $appConfig;

    foreach ($segments as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }

        $value = $value[$segment];
    }

    return $value;
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

function app_base_path(): string
{
    static $cached = null;

    if (is_string($cached)) {
        return $cached;
    }

    $documentRoot = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $projectRoot = realpath(BASE_PATH);

    if ($documentRoot === false || $projectRoot === false) {
        $cached = '';
        return $cached;
    }

    $documentRoot = str_replace('\\', '/', $documentRoot);
    $projectRoot = str_replace('\\', '/', $projectRoot);

    if ($projectRoot === $documentRoot) {
        $cached = '';
        return $cached;
    }

    $prefix = rtrim($documentRoot, '/') . '/';
    if (!str_starts_with($projectRoot, $prefix)) {
        $cached = '';
        return $cached;
    }

    $relative = trim(substr($projectRoot, strlen($prefix)), '/');
    $cached = $relative === '' ? '' : '/' . $relative;

    return $cached;
}

function base_url(string $path = ''): string
{
    $appUrl = (string) app_config('app_url', '');
    $path = '/' . ltrim($path, '/');

    if ($appUrl !== '') {
        return $appUrl . $path;
    }

    $basePath = app_base_path();
    if ($basePath === '') {
        return $path;
    }

    return $path === '/' ? $basePath . '/' : $basePath . $path;
}

function e(null|string|int|float $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . (str_starts_with($path, 'http') ? $path : base_url($path)));
    exit;
}

function request_method(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

function is_post_request(): bool
{
    return request_method() === 'POST';
}

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 64);
}

function user_agent_string(): string
{
    return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'), 0, 255);
}

function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    $text = trim($text, '-');

    return $text !== '' ? $text : 'event-' . bin2hex(random_bytes(3));
}

function format_datetime(?string $value, string $format = 'M j, Y H:i T'): string
{
    if (!$value) {
        return 'N/A';
    }

    return (new DateTimeImmutable($value))->format($format);
}

function channel_toggle_html(string $default = 'sms'): string
{
    $sms   = $default === 'sms'   ? ' checked' : '';
    $email = $default === 'email' ? ' checked' : '';
    return '<span class="channel-toggle">'
        . '<label class="channel-toggle__opt">'
        . '<input type="radio" name="channel" value="sms"' . $sms . '>'
        . '<span>SMS</span></label>'
        . '<label class="channel-toggle__opt">'
        . '<input type="radio" name="channel" value="email"' . $email . '>'
        . '<span>Email</span></label>'
        . '</span>';
}

function format_status(string $value): string
{
    return ucwords(str_replace('_', ' ', $value));
}

function badge_class(string $status): string
{
    return match ($status) {
        'active', 'approved', 'verified', 'used', 'sent' => 'badge-success',
        'warning', 'scheduled', 'under_review', 'pending', 'queued' => 'badge-warning',
        'error', 'rejected', 'revoked', 'failed', 'suspended' => 'badge-danger',
        default => 'badge-muted',
    };
}

function secure_digest(string $value): string
{
    return hash_hmac('sha256', $value, (string) app_config('app_key'));
}

function hash_token_value(string $token): string
{
    return secure_digest('token:' . $token);
}

function hash_code_value(string $code): string
{
    return secure_digest('code:' . $code);
}

function hash_receipt_value(string $receipt): string
{
    return secure_digest('receipt:' . $receipt);
}

function public_receipt_hash(string $receipt): string
{
    return hash('sha256', secure_digest('public-receipt:' . $receipt));
}

function random_reference(string $prefix, int $bytes = 4): string
{
    return strtoupper($prefix) . '-' . strtoupper(bin2hex(random_bytes($bytes)));
}

function random_vote_token(): string
{
    return 'VT-' . strtoupper(bin2hex(random_bytes(16)));
}

function random_receipt_code(): string
{
    return 'VR-' . strtoupper(bin2hex(random_bytes(10)));
}

function random_numeric_code(int $length = 6): string
{
    $code = '';

    for ($i = 0; $i < $length; $i++) {
        $code .= (string) random_int(0, 9);
    }

    return $code;
}

function json_or_null(array $value): ?string
{
    return $value === [] ? null : json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function json_decode_array(null|string $json): array
{
    if ($json === null || trim($json) === '') {
        return [];
    }

    $decoded = json_decode($json, true);

    return is_array($decoded) ? $decoded : [];
}

function normalize_email(string $email): string
{
    return strtolower(trim($email));
}

function user_with_phone_exists(string $normalizedPhone, ?int $excludeUserId = null): bool
{
    $params = ['phone' => $normalizedPhone];
    $sql = 'SELECT id FROM users WHERE phone = :phone';

    if ($excludeUserId !== null) {
        $sql .= ' AND id <> :exclude_id';
        $params['exclude_id'] = $excludeUserId;
    }

    return fetch_one($sql . ' LIMIT 1', $params) !== null;
}

function ensure_unique_phone_number(string $normalizedPhone, ?int $excludeUserId = null): void
{
    if (user_with_phone_exists($normalizedPhone, $excludeUserId)) {
        throw new RuntimeException('That phone number is already in use.');
    }
}

function verification_method_catalog(): array
{
    return [
        'email_verification' => [
            'label' => 'Email Verification',
            'description' => 'A one-time email code confirms address ownership.',
            'requires_reviewer' => false,
            'config' => ['expiry_minutes' => (int) app_config('security.verification_code_expiry_minutes')],
        ],
        'sms_verification' => [
            'label' => 'SMS Verification',
            'description' => 'A one-time phone code confirms the submitted number is reachable.',
            'requires_reviewer' => false,
            'config' => ['expiry_minutes' => (int) app_config('security.verification_code_expiry_minutes')],
        ],
        'document_review' => [
            'label' => 'Document Review',
            'description' => 'An event reviewer validates the uploaded identity evidence.',
            'requires_reviewer' => true,
            'config' => ['allowed_roles' => ['event_creator', 'co_admin', 'super_admin']],
        ],
        'manual_review' => [
            'label' => 'Manual Admin Review',
            'description' => 'A final privileged reviewer checks the submission before token issuance.',
            'requires_reviewer' => true,
            'config' => ['allowed_roles' => ['event_creator', 'co_admin', 'super_admin']],
        ],
        'trusted_verifier' => [
            'label' => 'Trusted Verifier Approval',
            'description' => 'A trusted verifier performs in-person or manual anti-impersonation confirmation.',
            'requires_reviewer' => true,
            'config' => ['allowed_roles' => ['verifier', 'super_admin']],
        ],
    ];
}

function verification_method_definition(string $methodKey): ?array
{
    return verification_method_catalog()[$methodKey] ?? null;
}

function supported_verification_method_keys(): array
{
    return array_keys(verification_method_catalog());
}

function fetch_one(string $sql, array $params = []): ?array
{
    $statement = db()->prepare($sql);
    $statement->execute($params);
    $row = $statement->fetch();

    return $row ?: null;
}

function fetch_all(string $sql, array $params = []): array
{
    $statement = db()->prepare($sql);
    $statement->execute($params);

    return $statement->fetchAll();
}

function execute_statement(string $sql, array $params = []): bool
{
    $statement = db()->prepare($sql);

    return $statement->execute($params);
}

function effective_event_status(array $event): string
{
    if ($event['status'] !== 'active') {
        return $event['status'];
    }
    $now = new DateTimeImmutable('now');
    if ($now < new DateTimeImmutable($event['start_at'])) {
        return 'scheduled';
    }
    if ($now > new DateTimeImmutable($event['end_at'])) {
        return 'closed';
    }
    return 'active';
}

function event_is_active(array $event): bool
{
    return effective_event_status($event) === 'active';
}

function can_view_public_results(array $event): bool
{
    if ($event['result_visibility'] === 'public_live') {
        return true;
    }

    if ($event['result_visibility'] === 'public_after_close') {
        $effective = effective_event_status($event);
        return in_array($effective, ['closed', 'archived'], true);
    }

    return false;
}

function can_view_public_audit(array $event): bool
{
    return (bool) $event['public_audit_enabled'] && can_view_public_results($event);
}

function fetch_public_events(): array
{
    $events = fetch_all(
        'SELECT events.*, users.full_name AS creator_name
         FROM events
         INNER JOIN users ON users.id = events.created_by
         WHERE events.status IN ("scheduled", "active", "closed", "archived")
         ORDER BY events.start_at ASC'
    );

    $order = ['active' => 0, 'scheduled' => 1, 'closed' => 2, 'archived' => 3];
    foreach ($events as &$event) {
        $event['status'] = effective_event_status($event);
    }
    unset($event);
    usort($events, static fn($a, $b) => ($order[$a['status']] ?? 9) <=> ($order[$b['status']] ?? 9));
    return $events;
}

function fetch_event_by_id(int $eventId): ?array
{
    return fetch_one(
        'SELECT events.*, users.full_name AS creator_name
         FROM events
         INNER JOIN users ON users.id = events.created_by
         WHERE events.id = :event_id',
        ['event_id' => $eventId]
    );
}

function fetch_event_by_slug(string $slug): ?array
{
    return fetch_one(
        'SELECT events.*, users.full_name AS creator_name
         FROM events
         INNER JOIN users ON users.id = events.created_by
         WHERE events.slug = :slug',
        ['slug' => $slug]
    );
}

function fetch_event_candidates(int $eventId): array
{
    return fetch_all(
        'SELECT * FROM candidates_or_options
         WHERE event_id = :event_id
         ORDER BY display_order ASC, id ASC',
        ['event_id' => $eventId]
    );
}

function fetch_event_required_fields(int $eventId): array
{
    return fetch_all(
        'SELECT * FROM event_required_fields
         WHERE event_id = :event_id
         ORDER BY field_order ASC, id ASC',
        ['event_id' => $eventId]
    );
}

function fetch_event_verification_methods(int $eventId, bool $activeOnly = false): array
{
    return fetch_all(
        'SELECT * FROM verification_methods
         WHERE event_id = :event_id' . ($activeOnly ? ' AND is_active = 1' : '') . '
         ORDER BY sequence_order ASC, id ASC',
        ['event_id' => $eventId]
    );
}

function fetch_user_submission(int $eventId, int $userId): ?array
{
    return fetch_one(
        'SELECT * FROM voter_event_submissions
         WHERE event_id = :event_id AND user_id = :user_id',
        ['event_id' => $eventId, 'user_id' => $userId]
    );
}

function fetch_submission_by_id(int $submissionId): ?array
{
    return fetch_one(
        'SELECT ves.*, events.title AS event_title, users.full_name, users.email, users.phone, users.phone_verified_at
         FROM voter_event_submissions ves
         INNER JOIN events ON events.id = ves.event_id
         INNER JOIN users ON users.id = ves.user_id
         WHERE ves.id = :submission_id',
        ['submission_id' => $submissionId]
    );
}

function fetch_submission_answers(int $submissionId): array
{
    return fetch_all(
        'SELECT vsa.*, erf.is_required
         FROM voter_submission_answers vsa
         LEFT JOIN event_required_fields erf ON erf.id = vsa.field_id
         WHERE vsa.submission_id = :submission_id
         ORDER BY vsa.id ASC',
        ['submission_id' => $submissionId]
    );
}

function fetch_submission_verifications(int $submissionId): array
{
    return fetch_all(
        'SELECT vv.*, vm.label, vm.method_key, vm.description, vm.is_required, vm.requires_reviewer, vm.sequence_order, vm.is_active,
                users.full_name AS reviewer_name
         FROM voter_verifications vv
         INNER JOIN verification_methods vm ON vm.id = vv.verification_method_id
         LEFT JOIN users ON users.id = vv.verifier_user_id
         WHERE vv.submission_id = :submission_id
         ORDER BY vm.sequence_order ASC, vv.id ASC',
        ['submission_id' => $submissionId]
    );
}

function fetch_submission_answer_by_id(int $answerId): ?array
{
    return fetch_one(
        'SELECT vsa.*, ves.id AS submission_id, ves.event_id, ves.user_id, ves.submission_reference, events.title AS event_title
         FROM voter_submission_answers vsa
         INNER JOIN voter_event_submissions ves ON ves.id = vsa.submission_id
         INNER JOIN events ON events.id = ves.event_id
         WHERE vsa.id = :answer_id',
        ['answer_id' => $answerId]
    );
}

function queue_notification(?int $userId, ?int $eventId, string $channel, string $destination, string $subject, string $body, ?string $deliveryCode = null, array $metadata = []): int
{
    execute_statement(
        'INSERT INTO notifications (user_id, event_id, channel, destination, subject, body, delivery_code, metadata_json, status)
         VALUES (:user_id, :event_id, :channel, :destination, :subject, :body, :delivery_code, :metadata_json, "queued")',
        [
            'user_id' => $userId,
            'event_id' => $eventId,
            'channel' => $channel,
            'destination' => $destination,
            'subject' => $subject,
            'body' => $body,
            'delivery_code' => $deliveryCode,
            'metadata_json' => json_or_null($metadata),
        ]
    );

    return (int) db()->lastInsertId();
}

function consume_rate_limit(string $scope, string $subject, int $maxAttempts, int $windowSeconds): array
{
    $now = new DateTimeImmutable('now');
    $existing = fetch_one(
        'SELECT * FROM rate_limits WHERE scope_key = :scope_key AND subject_key = :subject_key',
        ['scope_key' => $scope, 'subject_key' => $subject]
    );

    if (!$existing || new DateTimeImmutable($existing['expires_at']) <= $now) {
        execute_statement(
            'INSERT INTO rate_limits (scope_key, subject_key, attempts, window_started_at, expires_at)
             VALUES (:scope_key, :subject_key, 1, :window_started_at, :expires_at)
             ON DUPLICATE KEY UPDATE attempts = 1, window_started_at = VALUES(window_started_at), expires_at = VALUES(expires_at)',
            [
                'scope_key' => $scope,
                'subject_key' => $subject,
                'window_started_at' => $now->format('Y-m-d H:i:s'),
                'expires_at' => $now->modify('+' . $windowSeconds . ' seconds')->format('Y-m-d H:i:s'),
            ]
        );

        return ['allowed' => true, 'remaining' => $maxAttempts - 1, 'retry_after' => $windowSeconds];
    }

    $attempts = (int) $existing['attempts'] + 1;
    execute_statement(
        'UPDATE rate_limits SET attempts = :attempts WHERE id = :id',
        ['attempts' => $attempts, 'id' => $existing['id']]
    );

    $retryAfter = max(0, (new DateTimeImmutable($existing['expires_at']))->getTimestamp() - $now->getTimestamp());

    return [
        'allowed' => $attempts <= $maxAttempts,
        'remaining' => max(0, $maxAttempts - $attempts),
        'retry_after' => $retryAfter,
    ];
}

function clear_rate_limit(string $scope, string $subject): void
{
    execute_statement(
        'DELETE FROM rate_limits WHERE scope_key = :scope_key AND subject_key = :subject_key',
        ['scope_key' => $scope, 'subject_key' => $subject]
    );
}

function create_submission_verification_records(int $submissionId, int $eventId, array $account): void
{
    $methods = fetch_event_verification_methods($eventId, true);
    $event = fetch_event_by_id($eventId);
    $accountPhone = normalize_phone_number((string) ($account['phone'] ?? ''));

    foreach ($methods as $method) {
        execute_statement(
            'INSERT INTO voter_verifications (submission_id, verification_method_id, is_required_snapshot, status)
             VALUES (:submission_id, :method_id, :is_required_snapshot, "pending")',
            [
                'submission_id' => $submissionId,
                'method_id' => $method['id'],
                'is_required_snapshot' => (int) $method['is_required'],
            ]
        );

        $verificationId = (int) db()->lastInsertId();

        if (in_array($method['method_key'], ['email_verification', 'sms_verification'], true)) {
            $code = random_numeric_code(6);
            $destination = $method['method_key'] === 'email_verification'
                ? normalize_email((string) ($account['email'] ?? ''))
                : $accountPhone;
            $purpose = $method['method_key'] === 'email_verification' ? 'event_email' : 'event_sms';
            $expiry = (new DateTimeImmutable('now'))
                ->modify('+' . (int) app_config('security.verification_code_expiry_minutes') . ' minutes')
                ->format('Y-m-d H:i:s');

            if ($destination === '' || $destination === null) {
                if ($method['method_key'] === 'sms_verification') {
                    throw new RuntimeException('SMS verification requires a valid phone number.');
                }

                continue;
            }

            execute_statement(
                'INSERT INTO verification_codes (user_id, submission_id, verification_id, purpose, destination, code_hash, expires_at)
                 VALUES (:user_id, :submission_id, :verification_id, :purpose, :destination, :code_hash, :expires_at)',
                [
                    'user_id' => $account['id'],
                    'submission_id' => $submissionId,
                    'verification_id' => $verificationId,
                    'purpose' => $purpose,
                    'destination' => $destination,
                    'code_hash' => hash_code_value($code),
                    'expires_at' => $expiry,
                ]
            );

            if ($method['method_key'] === 'sms_verification') {
                $smsResult = send_sms_notification(
                    (int) $account['id'],
                    $eventId,
                    $destination,
                    'Verivote event verification code',
                    sms_event_verification_message($code, (string) ($event['title'] ?? 'Verivote event')),
                    $code,
                    [
                        'verification_method' => $method['method_key'],
                        'verification_id' => $verificationId,
                        'submission_id' => $submissionId,
                    ]
                );

                if (!$smsResult['success']) {
                    log_activity('sms.event_code_send_failed', [
                        'user_id'         => $account['id'],
                        'event_id'        => $eventId,
                        'verification_id' => $verificationId,
                        'submission_id'   => $submissionId,
                    ], 'WARN');
                    // Delivery failure does not block submission. The voter can resend from the status page.
                }
            } else {
                send_email_notification(
                    (int) $account['id'],
                    $eventId,
                    $destination,
                    'Verivote verification code',
                    'Your verification code is ' . $code . '. It expires in ' . (int) app_config('security.verification_code_expiry_minutes') . ' minutes.',
                    $code,
                    ['verification_method' => $method['method_key']]
                );
            }
        }
    }
}

function submission_ready_for_approval(int $submissionId): bool
{
    $submission = fetch_one(
        'SELECT ves.id, events.verification_policy
         FROM voter_event_submissions ves
         INNER JOIN events ON events.id = ves.event_id
         WHERE ves.id = :submission_id',
        ['submission_id' => $submissionId]
    );

    if (!$submission) {
        return false;
    }

    $statuses = fetch_all(
        'SELECT vv.status, vv.is_required_snapshot
         FROM voter_verifications vv
         WHERE vv.submission_id = :submission_id',
        ['submission_id' => $submissionId]
    );

    if ($submission['verification_policy'] === 'any_one') {
        foreach ($statuses as $status) {
            if (in_array($status['status'], ['approved', 'waived'], true)) {
                return true;
            }
        }

        return false;
    }

    foreach ($statuses as $status) {
        if ((int) $status['is_required_snapshot'] === 1 && !in_array($status['status'], ['approved', 'waived'], true)) {
            return false;
        }
    }

    if ($statuses === []) {
        return true;
    }

    return true;
}

function submission_verification_summary(int $submissionId): array
{
    $verifications = fetch_submission_verifications($submissionId);
    $requiredTotal = 0;
    $requiredComplete = 0;
    $pendingTotal = 0;
    $rejectedTotal = 0;
    $blockers = [];

    foreach ($verifications as $verification) {
        if ((int) $verification['is_required_snapshot'] === 1) {
            $requiredTotal++;

            if (in_array($verification['status'], ['approved', 'waived'], true)) {
                $requiredComplete++;
            } else {
                $blockers[] = $verification['label'];
            }
        }

        if (in_array($verification['status'], ['pending', 'under_review'], true)) {
            $pendingTotal++;
        }

        if ($verification['status'] === 'rejected') {
            $rejectedTotal++;
        }
    }

    return [
        'required_total' => $requiredTotal,
        'required_complete' => $requiredComplete,
        'pending_total' => $pendingTotal,
        'rejected_total' => $rejectedTotal,
        'blockers' => $blockers,
    ];
}

function current_submission_blockers(int $submissionId): array
{
    return submission_verification_summary($submissionId)['blockers'];
}

function user_can_review_verification_method(int $eventId, string $methodKey): bool
{
    $user = current_user();

    if (!$user) {
        return false;
    }

    if ($user['role_slug'] === 'super_admin' || user_can_manage_event($eventId, 'review_verifications')) {
        return true;
    }

    if ($user['role_slug'] === 'verifier' && $methodKey === 'trusted_verifier') {
        return fetch_one(
            'SELECT id FROM verifiers WHERE event_id = :event_id AND user_id = :user_id AND is_active = 1',
            ['event_id' => $eventId, 'user_id' => $user['id']]
        ) !== null;
    }

    return false;
}

function user_can_finalize_submission(int $eventId): bool
{
    $user = current_user();

    return $user !== null && ($user['role_slug'] === 'super_admin' || user_can_manage_event($eventId, 'review_verifications'));
}

function secure_upload_absolute_path(string $relativePath): ?string
{
    $relativePath = ltrim($relativePath, '/');
    $absolute = realpath(BASE_PATH . '/' . $relativePath);
    $uploadsBase = realpath((string) app_config('uploads.base_dir'));

    if ($absolute === false || $uploadsBase === false) {
        return null;
    }

    if (!str_starts_with($absolute, $uploadsBase . DIRECTORY_SEPARATOR) && $absolute !== $uploadsBase) {
        return null;
    }

    return is_file($absolute) ? $absolute : null;
}

function field_value_label(array $field): string
{
    return $field['field_label'] ?: format_status($field['field_key']);
}

function validate_dynamic_field_input(array $field, ?string $value, ?array $file = null, bool $allowExistingFile = false): array
{
    $errors = [];
    $fieldType = $field['field_type'];
    $label = field_value_label($field);
    $value = $value !== null ? trim($value) : null;
    $rules = json_decode_array($field['validation_rules_json'] ?? null);
    $isRequired = (int) $field['is_required'] === 1;

    if (in_array($fieldType, ['file', 'image'], true)) {
        $hasUpload = $file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        if ($isRequired && !$hasUpload && !$allowExistingFile) {
            $errors[] = $label . ' is required.';
        }

        return $errors;
    }

    if ($isRequired && $value === '') {
        $errors[] = $label . ' is required.';
        return $errors;
    }

    if ($value === '') {
        return $errors;
    }

    if (isset($rules['min_length']) && mb_strlen($value) < (int) $rules['min_length']) {
        $errors[] = $label . ' must be at least ' . (int) $rules['min_length'] . ' characters.';
    }

    if (isset($rules['max_length']) && mb_strlen($value) > (int) $rules['max_length']) {
        $errors[] = $label . ' must be at most ' . (int) $rules['max_length'] . ' characters.';
    }

    switch ($fieldType) {
        case 'email':
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[] = $label . ' must be a valid email address.';
            }
            break;

        case 'phone':
            if (normalize_phone_number($value) === null) {
                $errors[] = $label . ' must be a valid phone number.';
            }
            break;

        case 'date':
            $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
            if (!$date || $date->format('Y-m-d') !== $value) {
                $errors[] = $label . ' must be a valid date.';
            }
            break;

        case 'id_number':
        case 'passport':
            if (!preg_match('/^[A-Za-z0-9\\-\\/]{4,40}$/', $value)) {
                $errors[] = $label . ' must contain 4-40 letters, numbers, dashes, or slashes.';
            }
            break;

        case 'address':
            if (mb_strlen($value) < 6) {
                $errors[] = $label . ' must be detailed enough for review.';
            }
            break;

        case 'select':
            $options = json_decode_array($field['options_json'] ?? null);
            if ($options !== [] && !in_array($value, $options, true)) {
                $errors[] = $label . ' must match one of the configured options.';
            }
            break;
    }

    return $errors;
}

function submission_answer_value_map(array $answers): array
{
    $map = [];

    foreach ($answers as $answer) {
        $map[$answer['field_key']] = $answer;
        if ($answer['field_id']) {
            $map['field_' . $answer['field_id']] = $answer;
        }
    }

    return $map;
}

function prepare_submission_answers_payload(array $fields, array $requestData, array $requestFiles, array $existingAnswers = []): array
{
    $errors = [];
    $payload = [];
    $profileUpdates = [];
    $existingMap = submission_answer_value_map($existingAnswers);

    foreach ($fields as $field) {
        $fieldName = 'field_' . $field['id'];
        $fieldType = $field['field_type'];
        $existingAnswer = $existingMap[$fieldName] ?? null;
        $textValue = null;
        $filePath = $existingAnswer['file_path'] ?? null;
        $originalName = $existingAnswer['original_filename'] ?? null;

        if (in_array($fieldType, ['file', 'image'], true)) {
            $file = $requestFiles[$fieldName] ?? null;
            $errors = array_merge($errors, validate_dynamic_field_input($field, null, $file, $existingAnswer !== null && !empty($existingAnswer['file_path'])));

            if ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                try {
                    $stored = store_uploaded_file($file, $fieldType === 'image' ? 'photos' : 'documents');
                    $filePath = $stored['path'];
                    $originalName = $stored['original_name'];
                } catch (Throwable $exception) {
                    $errors[] = $field['field_label'] . ': ' . $exception->getMessage();
                }
            }
        } else {
            $textValue = trim((string) ($requestData[$fieldName] ?? ''));
            $errors = array_merge($errors, validate_dynamic_field_input($field, $textValue));

            if ($textValue !== '' && $fieldType === 'phone') {
                $textValue = normalize_phone_number($textValue) ?? $textValue;
            }
        }

        $payload[] = [
            'field_id' => (int) $field['id'],
            'field_key' => $field['field_key'],
            'field_label' => $field['field_label'],
            'field_type' => $fieldType,
            'text_value' => $textValue,
            'file_path' => $filePath,
            'original_name' => $originalName,
        ];

        if ($textValue !== null && $textValue !== '') {
            $profileUpdates[$field['field_key']] = $textValue;
        }
    }

    return [
        'errors' => $errors,
        'answers' => $payload,
        'profile_updates' => $profileUpdates,
    ];
}

function replace_submission_answers(int $submissionId, array $answers): void
{
    execute_statement('DELETE FROM voter_submission_answers WHERE submission_id = :submission_id', ['submission_id' => $submissionId]);

    foreach ($answers as $row) {
        execute_statement(
            'INSERT INTO voter_submission_answers (
                 submission_id, field_id, field_key, field_label, field_type, text_value, file_path, original_filename
             ) VALUES (
                 :submission_id, :field_id, :field_key, :field_label, :field_type, :text_value, :file_path, :original_filename
             )',
            [
                'submission_id' => $submissionId,
                'field_id' => $row['field_id'],
                'field_key' => $row['field_key'],
                'field_label' => $row['field_label'],
                'field_type' => $row['field_type'],
                'text_value' => $row['text_value'],
                'file_path' => $row['file_path'],
                'original_filename' => $row['original_name'],
            ]
        );
    }
}

function update_profile_from_submission_answers(int $userId, array $profileUpdates): void
{
    $profileChanged = false;

    if (!empty($profileUpdates['date_of_birth']) || !empty($profileUpdates['passport_number']) || !empty($profileUpdates['address']) || !empty($profileUpdates['id_number'])) {
        execute_statement(
            'UPDATE voter_profiles
             SET date_of_birth = COALESCE(:date_of_birth, date_of_birth),
                 address_line = COALESCE(:address_line, address_line),
                 national_id_number = COALESCE(:national_id_number, national_id_number),
                 passport_number = COALESCE(:passport_number, passport_number)
             WHERE user_id = :user_id',
            [
                'date_of_birth' => $profileUpdates['date_of_birth'] ?? null,
                'address_line' => $profileUpdates['address'] ?? null,
                'national_id_number' => $profileUpdates['id_number'] ?? null,
                'passport_number' => $profileUpdates['passport_number'] ?? null,
                'user_id' => $userId,
            ]
        );
        $profileChanged = true;
    }

    if (!empty($profileUpdates['phone'])) {
        $normalizedPhone = normalize_phone_number((string) $profileUpdates['phone']);

        if ($normalizedPhone === null) {
            throw new RuntimeException('The submitted phone number is invalid.');
        }

        ensure_unique_phone_number($normalizedPhone, $userId);
        $current = fetch_one('SELECT phone FROM users WHERE id = :id', ['id' => $userId]);
        $phoneChanged = $current && $current['phone'] !== $normalizedPhone;

        execute_statement(
            'UPDATE users
             SET phone = :phone,
                 phone_verified_at = CASE WHEN :phone_changed = 1 THEN NULL ELSE phone_verified_at END
             WHERE id = :id',
            [
                'phone' => $normalizedPhone,
                'phone_changed' => $phoneChanged ? 1 : 0,
                'id' => $userId,
            ]
        );
        $profileChanged = true;
    }

    if ($profileChanged && function_exists('current_user') && function_exists('refresh_current_user')) {
        $sessionUser = current_user();
        if ($sessionUser && (int) $sessionUser['id'] === $userId) {
            refresh_current_user();
        }
    }
}

function reset_submission_verifications(int $submissionId, int $eventId, array $account): void
{
    execute_statement('DELETE FROM verification_codes WHERE submission_id = :submission_id', ['submission_id' => $submissionId]);
    execute_statement('DELETE FROM voter_verifications WHERE submission_id = :submission_id', ['submission_id' => $submissionId]);
    create_submission_verification_records($submissionId, $eventId, $account);
}

function event_readiness(int $eventId): array
{
    $counts = fetch_one(
        'SELECT
             (SELECT COUNT(*) FROM candidates_or_options WHERE event_id = :candidate_event_id AND is_active = 1) AS candidate_count,
             (SELECT COUNT(*) FROM event_required_fields WHERE event_id = :field_event_id) AS field_count,
             (SELECT COUNT(*) FROM verification_methods WHERE event_id = :method_event_id AND is_active = 1) AS verification_method_count',
        [
            'candidate_event_id' => $eventId,
            'field_event_id' => $eventId,
            'method_event_id' => $eventId,
        ]
    ) ?: ['candidate_count' => 0, 'field_count' => 0, 'verification_method_count' => 0];

    $issues = [];

    if ((int) $counts['candidate_count'] < 2) {
        $issues[] = 'At least two active ballot options are recommended.';
    }

    if ((int) $counts['field_count'] === 0) {
        $issues[] = 'No required voter fields are configured.';
    }

    if ((int) $counts['verification_method_count'] === 0) {
        $issues[] = 'No active verification methods are configured.';
    }

    return [
        'candidate_count' => (int) $counts['candidate_count'],
        'field_count' => (int) $counts['field_count'],
        'verification_method_count' => (int) $counts['verification_method_count'],
        'is_ready' => $issues === [],
        'issues' => $issues,
    ];
}

function active_token_for_submission(int $submissionId): ?array
{
    return fetch_one(
        'SELECT * FROM voting_tokens
         WHERE submission_id = :submission_id AND status = "issued"
         ORDER BY id DESC LIMIT 1',
        ['submission_id' => $submissionId]
    );
}

function issue_voting_token(int $eventId, int $submissionId, int $issuerId, string $deliveryChannel = 'portal', ?string $expiresAt = null): array
{
    if (active_token_for_submission($submissionId)) {
        throw new RuntimeException('This submission already has an active token.');
    }

    $rawToken = random_vote_token();
    $anonymousKey = hash('sha256', bin2hex(random_bytes(32)));
    $publicTokenHash = hash('sha256', secure_digest('public-token:' . $rawToken));
    $expiresAt = $expiresAt ?: (new DateTimeImmutable('now'))
        ->modify('+' . (int) app_config('security.token_expiry_hours') . ' hours')
        ->format('Y-m-d H:i:s');

    execute_statement(
        'INSERT INTO voting_tokens (
             event_id, submission_id, issued_by, token_hash, token_last4,
             anonymous_ballot_key, public_token_hash, delivery_channel, expires_at
         ) VALUES (
             :event_id, :submission_id, :issued_by, :token_hash, :token_last4,
             :anonymous_ballot_key, :public_token_hash, :delivery_channel, :expires_at
         )',
        [
            'event_id' => $eventId,
            'submission_id' => $submissionId,
            'issued_by' => $issuerId,
            'token_hash' => hash_token_value($rawToken),
            'token_last4' => substr($rawToken, -4),
            'anonymous_ballot_key' => $anonymousKey,
            'public_token_hash' => $publicTokenHash,
            'delivery_channel' => $deliveryChannel,
            'expires_at' => $expiresAt,
        ]
    );

    $tokenId = (int) db()->lastInsertId();

    log_activity('token.issued', [
        'event_id' => $eventId,
        'submission_id' => $submissionId,
        'issuer_user_id' => $issuerId,
        'token_id' => $tokenId,
        'delivery_channel' => $deliveryChannel,
        'expires_at' => $expiresAt,
    ]);

    return [
        'id' => $tokenId,
        'token' => $rawToken,
        'expires_at' => $expiresAt,
    ];
}

function deliver_voting_token(array $event, array $submission, array $issued, string $preferredChannel = 'sms'): array
{
    $subject    = 'Your Verivote voting token';
    $emailBody  = 'Your voting token for "' . $event['title'] . '" is ' . $issued['token'] . '. It expires at ' . $issued['expires_at'] . '.';
    $smsMessage = sms_token_delivery_message((string) $issued['token'], (string) $event['title'], (string) $issued['expires_at']);
    $meta       = ['token_id' => (int) $issued['id'], 'submission_id' => (int) $submission['id'], 'delivery_purpose' => 'voting_token'];

    if ($preferredChannel === 'email') {
        if (!empty($submission['email'])) {
            send_email_notification(
                (int) $submission['user_id'], (int) $event['id'],
                (string) $submission['email'], $subject, $emailBody,
                (string) $issued['token'], $meta
            );
            execute_statement('UPDATE voting_tokens SET delivery_channel = "email" WHERE id = :id', ['id' => $issued['id']]);
            return ['success' => true, 'channel' => 'email', 'fallback_used' => false];
        }
        execute_statement('UPDATE voting_tokens SET delivery_channel = "email" WHERE id = :id', ['id' => $issued['id']]);
        return ['success' => false, 'channel' => 'email', 'fallback_used' => false];
    }

    // SMS preferred
    $smsResult = ['success' => false, 'provider_code' => 'phone_not_verified'];
    if (!empty($submission['phone']) && !empty($submission['phone_verified_at'])) {
        $smsResult = send_sms_notification(
            (int) $submission['user_id'], (int) $event['id'],
            (string) $submission['phone'], $subject, $smsMessage,
            (string) $issued['token'], $meta
        );
    }

    if (!empty($smsResult['success'])) {
        execute_statement('UPDATE voting_tokens SET delivery_channel = "sms" WHERE id = :id', ['id' => $issued['id']]);
        return ['success' => true, 'channel' => 'sms', 'fallback_used' => false];
    }

    // SMS failed — fall back to email if enabled
    if ((bool) app_config('sms.fallback_to_email', true) && !empty($submission['email'])) {
        send_email_notification(
            (int) $submission['user_id'], (int) $event['id'],
            (string) $submission['email'], $subject, $emailBody,
            (string) $issued['token'],
            array_merge($meta, ['fallback_from_sms' => true, 'sms_provider_code' => $smsResult['provider_code'] ?? null])
        );
        execute_statement('UPDATE voting_tokens SET delivery_channel = "email" WHERE id = :id', ['id' => $issued['id']]);
        return ['success' => true, 'channel' => 'email', 'fallback_used' => true];
    }

    execute_statement('UPDATE voting_tokens SET delivery_channel = "sms" WHERE id = :id', ['id' => $issued['id']]);
    return ['success' => false, 'channel' => 'sms', 'fallback_used' => false];
}

function send_account_phone_verification_code(array $user): array
{
    $phone = normalize_phone_number((string) ($user['phone'] ?? ''));
    if ($phone === null) {
        throw new RuntimeException('A valid phone number is required for account verification.');
    }

    // Resolve email — may not be in the $user array passed from registration flow
    $email = (string) ($user['email'] ?? '');
    if ($email === '') {
        $row = fetch_one('SELECT email FROM users WHERE id = :id', ['id' => $user['id']]);
        $email = (string) ($row['email'] ?? '');
    }

    $expiryMinutes = (int) get_site_setting('verification_code_expiry_minutes', (int) app_config('security.verification_code_expiry_minutes'));
    $code      = random_numeric_code(6);
    $expiresAt = (new DateTimeImmutable('now'))
        ->modify('+' . $expiryMinutes . ' minutes')
        ->format('Y-m-d H:i:s');

    execute_statement(
        'INSERT INTO verification_codes (user_id, purpose, destination, code_hash, expires_at)
         VALUES (:user_id, "account_phone", :destination, :code_hash, :expires_at)',
        [
            'user_id'   => $user['id'],
            'destination' => $phone,
            'code_hash' => hash_code_value($code),
            'expires_at' => $expiresAt,
        ]
    );

    $smsResult = send_sms_notification(
        (int) $user['id'],
        null,
        $phone,
        'Verivote account verification code',
        sms_account_verification_message($code),
        $code,
        ['purpose' => 'account_phone']
    );

    if (!$smsResult['success']) {
        $providerCode    = (string) ($smsResult['provider_code'] ?? '');
        $providerMessage = (string) ($smsResult['provider_message'] ?? '');
        $detail          = trim($providerCode . ' ' . $providerMessage);
        throw new RuntimeException(
            'Could not send account verification code by SMS.'
            . ($detail !== '' ? ' Provider response: ' . $detail . '.' : '')
        );
    }

    // Also send via email (best-effort — SMS already succeeded)
    $emailResult = null;
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $emailBody = "Your Verivote account verification code is: {$code}\n\n"
            . "This code expires in {$expiryMinutes} minutes. Enter it on the phone verification page.\n\n"
            . "If you did not register for a Verivote account, you can safely ignore this message.";
        try {
            $emailResult = send_email_notification(
                (int) $user['id'],
                null,
                $email,
                "Verivote verification code: {$code}",
                $emailBody,
                $code,
                ['purpose' => 'account_phone']
            );
        } catch (Throwable) {
            // Email is secondary — do not fail if it cannot be delivered
        }
    }

    return ['code' => $code, 'expires_at' => $expiresAt, 'sms' => $smsResult, 'email' => $emailResult];
}

function revoke_voting_token(int $tokenId, int $actorId): void
{
    execute_statement(
        'UPDATE voting_tokens
         SET status = "revoked", revoked_at = NOW(), revoked_by = :actor_id
         WHERE id = :token_id AND status = "issued"',
        ['actor_id' => $actorId, 'token_id' => $tokenId]
    );

    log_activity('token.revoked', [
        'token_id' => $tokenId,
        'actor_user_id' => $actorId,
    ]);
}

function validate_voting_token(int $eventId, string $token): ?array
{
    $hash = hash_token_value($token);

    return fetch_one(
        'SELECT vt.*, ves.status AS submission_status, events.title AS event_title, events.start_at, events.end_at, events.status AS event_status
         FROM voting_tokens vt
         INNER JOIN voter_event_submissions ves ON ves.id = vt.submission_id
         INNER JOIN events ON events.id = vt.event_id
         WHERE vt.event_id = :event_id AND vt.token_hash = :token_hash
         LIMIT 1',
        ['event_id' => $eventId, 'token_hash' => $hash]
    );
}

function cast_ballot(int $eventId, string $token, int $candidateId): array
{
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $statement = $pdo->prepare(
            'SELECT vt.*, ves.status AS submission_status, events.title AS event_title, events.start_at, events.end_at, events.status AS event_status
             FROM voting_tokens vt
             INNER JOIN voter_event_submissions ves ON ves.id = vt.submission_id
             INNER JOIN events ON events.id = vt.event_id
             WHERE vt.event_id = :event_id AND vt.token_hash = :token_hash
             FOR UPDATE'
        );
        $statement->execute([
            'event_id' => $eventId,
            'token_hash' => hash_token_value($token),
        ]);
        $tokenRow = $statement->fetch();

        if (!$tokenRow) {
            throw new RuntimeException('Invalid voting token.');
        }

        if ($tokenRow['status'] !== 'issued' || $tokenRow['used_at'] !== null || $tokenRow['revoked_at'] !== null) {
            throw new RuntimeException('This token is no longer valid.');
        }

        if ($tokenRow['submission_status'] !== 'approved') {
            throw new RuntimeException('This voter has not been approved to vote yet.');
        }

        if ((new DateTimeImmutable($tokenRow['expires_at'])) < new DateTimeImmutable('now')) {
            execute_statement('UPDATE voting_tokens SET status = "expired" WHERE id = :id', ['id' => $tokenRow['id']]);
            log_activity('token.expired', [
                'token_id' => (int) $tokenRow['id'],
                'event_id' => $eventId,
            ], 'WARN');
            throw new RuntimeException('This token has expired.');
        }

        $event = fetch_event_by_id($eventId);
        if (!$event || !event_is_active($event)) {
            throw new RuntimeException('This event is not currently accepting ballots.');
        }

        $candidate = fetch_one(
            'SELECT * FROM candidates_or_options WHERE id = :candidate_id AND event_id = :event_id AND is_active = 1',
            ['candidate_id' => $candidateId, 'event_id' => $eventId]
        );

        if (!$candidate) {
            throw new RuntimeException('Invalid ballot selection.');
        }

        $receipt = random_receipt_code();
        $receiptHash = hash_receipt_value($receipt);
        $publicReceipt = public_receipt_hash($receipt);
        $ballotHash = hash('sha256', $tokenRow['anonymous_ballot_key'] . '|' . $candidate['id'] . '|' . $receiptHash . '|' . microtime(true));

        execute_statement(
            'INSERT INTO ballots (
                 event_id, candidate_option_id, anonymous_ballot_key, option_snapshot,
                 receipt_hash, public_receipt_hash, ballot_hash, submitted_at, cast_ip_address, cast_user_agent
             ) VALUES (
                 :event_id, :candidate_option_id, :anonymous_ballot_key, :option_snapshot,
                 :receipt_hash, :public_receipt_hash, :ballot_hash, NOW(), :cast_ip_address, :cast_user_agent
             )',
            [
                'event_id' => $eventId,
                'candidate_option_id' => $candidateId,
                'anonymous_ballot_key' => $tokenRow['anonymous_ballot_key'],
                'option_snapshot' => $candidate['option_label'],
                'receipt_hash' => $receiptHash,
                'public_receipt_hash' => $publicReceipt,
                'ballot_hash' => $ballotHash,
                'cast_ip_address' => client_ip(),
                'cast_user_agent' => user_agent_string(),
            ]
        );

        execute_statement(
            'UPDATE voting_tokens
             SET status = "used", used_at = NOW(), usage_ip_address = :usage_ip, usage_user_agent = :usage_agent
             WHERE id = :token_id',
            [
                'usage_ip' => client_ip(),
                'usage_agent' => user_agent_string(),
                'token_id' => $tokenRow['id'],
            ]
        );

        $pdo->commit();

        log_activity('token.used', [
            'token_id' => (int) $tokenRow['id'],
            'event_id' => $eventId,
        ]);
        log_activity('vote.cast', [
            'event_id' => $eventId,
            'candidate_id' => (int) $candidate['id'],
            'option' => $candidate['option_label'],
            'public_receipt_hash' => $publicReceipt,
        ]);

        return [
            'receipt' => $receipt,
            'option' => $candidate['option_label'],
            'receipt_public_hash' => $publicReceipt,
        ];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        log_activity('vote.cast_failed', [
            'event_id' => $eventId,
            'error' => $exception->getMessage(),
        ], 'ERROR');

        throw $exception;
    }
}

function find_ballot_by_receipt(int $eventId, string $receipt): ?array
{
    return fetch_one(
        'SELECT ballots.*, events.title AS event_title
         FROM ballots
         INNER JOIN events ON events.id = ballots.event_id
         WHERE ballots.event_id = :event_id AND ballots.receipt_hash = :receipt_hash',
        [
            'event_id' => $eventId,
            'receipt_hash' => hash_receipt_value($receipt),
        ]
    );
}

function compute_event_results(int $eventId): array
{
    $rows = fetch_all(
        'SELECT co.id, co.option_label, COUNT(ballots.id) AS total_votes
         FROM candidates_or_options co
         LEFT JOIN ballots ON ballots.candidate_option_id = co.id
         WHERE co.event_id = :event_id AND co.is_active = 1
         GROUP BY co.id, co.option_label
         ORDER BY total_votes DESC, co.display_order ASC',
        ['event_id' => $eventId]
    );

    $total = array_sum(array_map(static fn(array $row): int => (int) $row['total_votes'], $rows));

    foreach ($rows as &$row) {
        $row['percentage'] = $total > 0 ? round(((int) $row['total_votes'] / $total) * 100, 2) : 0.0;
    }
    unset($row);

    return ['rows' => $rows, 'total' => $total];
}

function create_result_snapshot_record(int $eventId, int $userId, string $snapshotType = 'manual'): array
{
    $results = compute_event_results($eventId);
    $payload = [
        'generated_at' => (new DateTimeImmutable('now'))->format(DateTimeInterface::ATOM),
        'total_ballots' => $results['total'],
        'rows' => $results['rows'],
    ];
    $integrityHash = hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES));

    execute_statement(
        'INSERT INTO result_snapshots (event_id, generated_by, snapshot_type, total_ballots, snapshot_json, integrity_hash)
         VALUES (:event_id, :generated_by, :snapshot_type, :total_ballots, :snapshot_json, :integrity_hash)',
        [
            'event_id' => $eventId,
            'generated_by' => $userId,
            'snapshot_type' => $snapshotType,
            'total_ballots' => $results['total'],
            'snapshot_json' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'integrity_hash' => $integrityHash,
        ]
    );

    return ['payload' => $payload, 'integrity_hash' => $integrityHash];
}

function latest_result_snapshot(int $eventId): ?array
{
    return fetch_one(
        'SELECT rs.*, users.full_name AS generated_by_name
         FROM result_snapshots rs
         INNER JOIN users ON users.id = rs.generated_by
         WHERE rs.event_id = :event_id
         ORDER BY rs.id DESC
         LIMIT 1',
        ['event_id' => $eventId]
    );
}

function dashboard_home_for_role(string $roleSlug): string
{
    return match ($roleSlug) {
        'super_admin' => '/admin/dashboard.php',
        'event_creator' => '/creator/dashboard.php',
        'co_admin' => '/coadmin/dashboard.php',
        'verifier' => '/verifier/dashboard.php',
        default => '/voter/dashboard.php',
    };
}

function get_site_setting(string $key, mixed $default = null): mixed
{
    static $cache = [];
    static $loaded = false;

    if (!$loaded) {
        try {
            $rows = fetch_all('SELECT setting_key, setting_value, setting_type FROM site_settings');
            foreach ($rows as $row) {
                $cache[$row['setting_key']] = match ($row['setting_type']) {
                    'integer' => (int) $row['setting_value'],
                    'boolean' => (bool) (int) $row['setting_value'],
                    default   => (string) $row['setting_value'],
                };
            }
        } catch (Throwable) {
            // Table may not exist before migration is run
        }
        $loaded = true;
    }

    return $cache[$key] ?? $default;
}

function set_site_setting(string $key, string $value): void
{
    execute_statement(
        'INSERT INTO site_settings (setting_key, setting_value)
         VALUES (:key, :val)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
        ['key' => $key, 'val' => $value]
    );
}

function get_all_site_settings(): array
{
    try {
        $rows = fetch_all('SELECT * FROM site_settings ORDER BY category, setting_key');
        $result = [];
        foreach ($rows as $row) {
            $result[$row['setting_key']] = match ($row['setting_type']) {
                'integer' => (int) $row['setting_value'],
                'boolean' => (bool) (int) $row['setting_value'],
                default   => (string) $row['setting_value'],
            };
        }
        return $result;
    } catch (Throwable) {
        return [];
    }
}
