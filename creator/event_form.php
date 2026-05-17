<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_role(['event_creator', 'super_admin']);

$user = current_user();
$eventId = (int) ($_GET['event'] ?? $_POST['event_id'] ?? 0);
$event = $eventId > 0 ? fetch_event_by_id($eventId) : null;

if ($event) {
    require_event_permission($eventId, 'manage_event');
}

if (is_post_request()) {
    verify_csrf_or_fail();
    $action = (string) ($_POST['action'] ?? 'save_event');

    if ($action === 'save_event') {
        $title = trim((string) ($_POST['title'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $startAt = trim((string) ($_POST['start_at'] ?? ''));
        $endAt = trim((string) ($_POST['end_at'] ?? ''));
        $allowedStatuses = ['draft', 'scheduled', 'active', 'closed', 'archived'];
        $allowedVisibilities = ['private', 'public_after_close', 'public_live'];
        $allowedPolicies = ['all_required', 'any_one'];
        $status = in_array((string) ($_POST['status'] ?? ''), $allowedStatuses, true) ? (string) $_POST['status'] : 'draft';
        $resultVisibility = in_array((string) ($_POST['result_visibility'] ?? ''), $allowedVisibilities, true) ? (string) $_POST['result_visibility'] : 'public_after_close';
        $verificationPolicy = in_array((string) ($_POST['verification_policy'] ?? ''), $allowedPolicies, true) ? (string) $_POST['verification_policy'] : 'all_required';
        $eventNotice = trim((string) ($_POST['event_notice'] ?? ''));
        $allowSelfRegistration = !empty($_POST['allow_self_registration']) ? 1 : 0;
        $personalVerificationEnabled = !empty($_POST['personal_verification_enabled']) ? 1 : 0;
        $publicAuditEnabled = !empty($_POST['public_audit_enabled']) ? 1 : 0;
        $errors = [];

        validate_required('Title', $title, $errors);
        validate_required('Description', $description, $errors);
        validate_datetime_range($startAt, $endAt, $errors);

        if ($errors === []) {
            if ($event) {
                execute_statement(
                    'UPDATE events
                     SET title = :title,
                         description = :description,
                         start_at = :start_at,
                         end_at = :end_at,
                         status = :status,
                         result_visibility = :result_visibility,
                         verification_policy = :verification_policy,
                         allow_self_registration = :allow_self_registration,
                         personal_verification_enabled = :personal_verification_enabled,
                         public_audit_enabled = :public_audit_enabled,
                         event_notice = :event_notice
                     WHERE id = :id',
                    [
                        'title' => $title,
                        'description' => $description,
                        'start_at' => $startAt,
                        'end_at' => $endAt,
                        'status' => $status,
                        'result_visibility' => $resultVisibility,
                        'verification_policy' => $verificationPolicy,
                        'allow_self_registration' => $allowSelfRegistration,
                        'personal_verification_enabled' => $personalVerificationEnabled,
                        'public_audit_enabled' => $publicAuditEnabled,
                        'event_notice' => $eventNotice !== '' ? $eventNotice : null,
                        'id' => $event['id'],
                    ]
                );
                write_audit_log('event_updated', 'events', (string) $event['id'], 'Event settings updated.', $eventId);
                flash('success', 'Event updated successfully.');
                redirect('/creator/event_form.php?event=' . $event['id']);
            }

            $slug = slugify($title) . '-' . strtolower(bin2hex(random_bytes(2)));
            execute_statement(
                'INSERT INTO events (
                     created_by, title, slug, description, ballot_type, status, start_at, end_at,
                     result_visibility, verification_policy, allow_self_registration,
                     personal_verification_enabled, public_audit_enabled, event_notice
                 ) VALUES (
                     :created_by, :title, :slug, :description, "single_choice", :status, :start_at, :end_at,
                     :result_visibility, :verification_policy, :allow_self_registration,
                     :personal_verification_enabled, :public_audit_enabled, :event_notice
                 )',
                [
                    'created_by' => $user['id'],
                    'title' => $title,
                    'slug' => $slug,
                    'description' => $description,
                    'status' => $status,
                    'start_at' => $startAt,
                    'end_at' => $endAt,
                    'result_visibility' => $resultVisibility,
                    'verification_policy' => $verificationPolicy,
                    'allow_self_registration' => $allowSelfRegistration,
                    'personal_verification_enabled' => $personalVerificationEnabled,
                    'public_audit_enabled' => $publicAuditEnabled,
                    'event_notice' => $eventNotice !== '' ? $eventNotice : null,
                ]
            );

            $newEventId = (int) db()->lastInsertId();
            execute_statement(
                'INSERT INTO event_admins (event_id, user_id, assignment_type, permissions_json)
                 VALUES (:event_id, :user_id, "owner", :permissions_json)',
                [
                    'event_id' => $newEventId,
                    'user_id' => $user['id'],
                    'permissions_json' => json_encode([
                        'manage_event' => true,
                        'manage_candidates' => true,
                        'manage_fields' => true,
                        'review_verifications' => true,
                        'issue_tokens' => true,
                        'view_results' => true,
                    ], JSON_UNESCAPED_SLASHES),
                ]
            );
            write_audit_log('event_created', 'events', (string) $newEventId, 'New event created.', $newEventId);
            flash('success', 'Event created successfully. Configure fields, candidates, and verification steps next.');
            redirect('/creator/event_form.php?event=' . $newEventId);
        }

        flash_errors($errors);
    }

    if ($event && $action === 'assign_coadmin') {
        $coAdminUserId = (int) ($_POST['user_id'] ?? 0);
        $assignee = fetch_one(
            'SELECT users.id
             FROM users
             INNER JOIN roles ON roles.id = users.role_id
             WHERE users.id = :id AND roles.slug = "co_admin"',
            ['id' => $coAdminUserId]
        );

        if (!$assignee) {
            flash('error', 'Select a valid co-admin account.');
            redirect('/creator/event_form.php?event=' . $event['id']);
        }

        $permissions = [
            'review_verifications' => !empty($_POST['permission_review_verifications']),
            'manage_candidates' => !empty($_POST['permission_manage_candidates']),
            'manage_fields' => !empty($_POST['permission_manage_fields']),
            'issue_tokens' => !empty($_POST['permission_issue_tokens']),
            'view_results' => !empty($_POST['permission_view_results']),
        ];

        execute_statement(
            'INSERT INTO co_admins (event_id, user_id, assigned_by, permissions_json, is_active)
             VALUES (:event_id, :user_id, :assigned_by, :permissions_json, 1)
             ON DUPLICATE KEY UPDATE permissions_json = VALUES(permissions_json), is_active = 1, assigned_by = VALUES(assigned_by)',
            [
                'event_id' => $event['id'],
                'user_id' => $coAdminUserId,
                'assigned_by' => $user['id'],
                'permissions_json' => json_encode($permissions, JSON_UNESCAPED_SLASHES),
            ]
        );

        write_audit_log('coadmin_assigned', 'co_admins', (string) $coAdminUserId, 'Co-admin assigned to event.', $event['id'], $permissions);
        flash('success', 'Co-admin assignment saved.');
        redirect('/creator/event_form.php?event=' . $event['id']);
    }

    if ($event && $action === 'toggle_coadmin_status') {
        $assignmentId = (int) ($_POST['assignment_id'] ?? 0);
        $assignment = fetch_one(
            'SELECT * FROM co_admins WHERE id = :id AND event_id = :event_id',
            ['id' => $assignmentId, 'event_id' => $event['id']]
        );

        if ($assignment) {
            $newState = (int) $assignment['is_active'] === 1 ? 0 : 1;
            execute_statement(
                'UPDATE co_admins SET is_active = :is_active WHERE id = :id',
                ['is_active' => $newState, 'id' => $assignmentId]
            );
            write_audit_log('coadmin_toggled', 'co_admins', (string) $assignmentId, 'Co-admin assignment state updated.', $event['id'], ['is_active' => $newState]);
            flash('success', 'Co-admin assignment updated.');
        }

        redirect('/creator/event_form.php?event=' . $event['id']);
    }

    if ($event && $action === 'assign_verifier') {
        $verifierUserId = (int) ($_POST['user_id'] ?? 0);
        $assignee = fetch_one(
            'SELECT users.id
             FROM users
             INNER JOIN roles ON roles.id = users.role_id
             WHERE users.id = :id AND roles.slug = "verifier"',
            ['id' => $verifierUserId]
        );

        if (!$assignee) {
            flash('error', 'Select a valid verifier account.');
            redirect('/creator/event_form.php?event=' . $event['id']);
        }

        execute_statement(
            'INSERT INTO verifiers (event_id, user_id, assigned_by, is_active)
             VALUES (:event_id, :user_id, :assigned_by, 1)
             ON DUPLICATE KEY UPDATE is_active = 1, assigned_by = VALUES(assigned_by)',
            [
                'event_id' => $event['id'],
                'user_id' => $verifierUserId,
                'assigned_by' => $user['id'],
            ]
        );

        write_audit_log('verifier_assigned', 'verifiers', (string) $verifierUserId, 'Verifier assigned to event.', $event['id']);
        flash('success', 'Verifier assignment saved.');
        redirect('/creator/event_form.php?event=' . $event['id']);
    }

    if ($event && $action === 'toggle_verifier_status') {
        $assignmentId = (int) ($_POST['assignment_id'] ?? 0);
        $assignment = fetch_one(
            'SELECT * FROM verifiers WHERE id = :id AND event_id = :event_id',
            ['id' => $assignmentId, 'event_id' => $event['id']]
        );

        if ($assignment) {
            $newState = (int) $assignment['is_active'] === 1 ? 0 : 1;
            execute_statement(
                'UPDATE verifiers SET is_active = :is_active WHERE id = :id',
                ['is_active' => $newState, 'id' => $assignmentId]
            );
            write_audit_log('verifier_toggled', 'verifiers', (string) $assignmentId, 'Verifier assignment state updated.', $event['id'], ['is_active' => $newState]);
            flash('success', 'Verifier assignment updated.');
        }

        redirect('/creator/event_form.php?event=' . $event['id']);
    }
}

$event = $eventId > 0 ? fetch_event_by_id($eventId) : null;
$coAdminUsers = fetch_all(
    'SELECT users.id, users.full_name, users.email
     FROM users
     INNER JOIN roles ON roles.id = users.role_id
     WHERE roles.slug = "co_admin" AND users.status = "active"
     ORDER BY users.full_name ASC'
);
$verifierUsers = fetch_all(
    'SELECT users.id, users.full_name, users.email
     FROM users
     INNER JOIN roles ON roles.id = users.role_id
     WHERE roles.slug = "verifier" AND users.status = "active"
     ORDER BY users.full_name ASC'
);
$assignedCoAdmins = $event ? fetch_all(
    'SELECT co_admins.*, users.full_name, users.email
     FROM co_admins
     INNER JOIN users ON users.id = co_admins.user_id
     WHERE co_admins.event_id = :event_id
     ORDER BY co_admins.id DESC',
    ['event_id' => $event['id']]
) : [];
$assignedVerifiers = $event ? fetch_all(
    'SELECT verifiers.*, users.full_name, users.email
     FROM verifiers
     INNER JOIN users ON users.id = verifiers.user_id
     WHERE verifiers.event_id = :event_id
     ORDER BY verifiers.id DESC',
    ['event_id' => $event['id']]
) : [];
$readiness = $event ? event_readiness((int) $event['id']) : null;

$pageTitle = $event ? 'Edit Event' : 'Create Event';
$pageHeading = $event ? 'Event Settings' : 'Create Event';
$pageDescription = 'Define the event window, visibility, verification policy, and privileged assignments.';
$isDashboard = true;
$sidebarContext = current_role_slug() ?? 'event_creator';
$activeSidebar = 'creator-event';
$eventContextId = $event['id'] ?? null;

include dirname(__DIR__) . '/includes/header.php';
?>
<?php if ($event && $readiness): ?>
    <div class="stat-strip">
        <div class="stat-strip__item">
            <strong><?= e((string) $readiness['candidate_count']); ?></strong>
            <p>Active ballot options</p>
        </div>
        <div class="stat-strip__item">
            <strong><?= e((string) $readiness['field_count']); ?></strong>
            <p>Required fields</p>
        </div>
        <div class="stat-strip__item">
            <strong><?= e((string) $readiness['verification_method_count']); ?></strong>
            <p>Verification methods</p>
        </div>
        <div class="stat-strip__item">
            <strong><?= $readiness['is_ready'] ? 'Ready' : 'Incomplete'; ?></strong>
            <p>Configuration status</p>
        </div>
    </div>

    <?php if ($readiness['issues'] !== []): ?>
        <section class="panel">
            <span class="eyebrow">Readiness</span>
            <h2>Configuration gaps</h2>
            <div class="list-shell">
                <?php foreach ($readiness['issues'] as $issue): ?>
                    <div class="list-row">
                        <strong><?= e($issue); ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="inline-actions">
                <a class="button button--ghost" href="<?= e(base_url('/creator/candidates.php?event=' . $event['id'])); ?>">Candidates</a>
                <a class="button button--ghost" href="<?= e(base_url('/creator/required_fields.php?event=' . $event['id'])); ?>">Required Fields</a>
                <a class="button button--ghost" href="<?= e(base_url('/creator/verification_methods.php?event=' . $event['id'])); ?>">Verification Methods</a>
            </div>
        </section>
    <?php endif; ?>
<?php endif; ?>

<section class="panel">
    <span class="eyebrow">Event Configuration</span>
    <h2><?= e($event['title'] ?? 'Create a new event'); ?></h2>
    <form method="post" class="form-grid">
        <?= csrf_field(); ?>
        <input type="hidden" name="action" value="save_event">
        <input type="hidden" name="event_id" value="<?= e((string) ($event['id'] ?? 0)); ?>">
        <div class="field field--full">
            <label for="title">Title</label>
            <input id="title" type="text" name="title" value="<?= e($event['title'] ?? old_input('title')); ?>" required>
        </div>
        <div class="field field--full">
            <label for="description">Description</label>
            <textarea id="description" name="description" required><?= e($event['description'] ?? old_input('description')); ?></textarea>
        </div>
        <div class="field">
            <label for="start_at">Start date/time</label>
            <input id="start_at" type="datetime-local" name="start_at" value="<?= e(isset($event['start_at']) ? (new DateTimeImmutable($event['start_at']))->format('Y-m-d\TH:i') : old_input('start_at')); ?>" required>
        </div>
        <div class="field">
            <label for="end_at">End date/time</label>
            <input id="end_at" type="datetime-local" name="end_at" value="<?= e(isset($event['end_at']) ? (new DateTimeImmutable($event['end_at']))->format('Y-m-d\TH:i') : old_input('end_at')); ?>" required>
        </div>
        <div class="field">
            <label for="status">Status</label>
            <select id="status" name="status">
                <?php foreach (['draft', 'scheduled', 'active', 'closed', 'archived'] as $status): ?>
                    <option value="<?= e($status); ?>" <?= (($event['status'] ?? 'draft') === $status) ? 'selected' : ''; ?>><?= e(format_status($status)); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="result_visibility">Result visibility</label>
            <select id="result_visibility" name="result_visibility">
                <?php foreach (['private', 'public_after_close', 'public_live'] as $visibility): ?>
                    <option value="<?= e($visibility); ?>" <?= (($event['result_visibility'] ?? 'public_after_close') === $visibility) ? 'selected' : ''; ?>><?= e(format_status($visibility)); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="verification_policy">Verification policy</label>
            <select id="verification_policy" name="verification_policy">
                <?php foreach (['all_required', 'any_one', 'custom'] as $policy): ?>
                    <option value="<?= e($policy); ?>" <?= (($event['verification_policy'] ?? 'all_required') === $policy) ? 'selected' : ''; ?>><?= e(format_status($policy)); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field field--full">
            <label for="event_notice">Event notice</label>
            <textarea id="event_notice" name="event_notice"><?= e($event['event_notice'] ?? old_input('event_notice')); ?></textarea>
        </div>
        <div class="field">
            <label><input type="checkbox" name="allow_self_registration" value="1" <?= !isset($event['allow_self_registration']) || (int) $event['allow_self_registration'] === 1 ? 'checked' : ''; ?>> Allow self registration</label>
        </div>
        <div class="field">
            <label><input type="checkbox" name="personal_verification_enabled" value="1" <?= !isset($event['personal_verification_enabled']) || (int) $event['personal_verification_enabled'] === 1 ? 'checked' : ''; ?>> Enable personal receipt verification</label>
        </div>
        <div class="field">
            <label><input type="checkbox" name="public_audit_enabled" value="1" <?= !isset($event['public_audit_enabled']) || (int) $event['public_audit_enabled'] === 1 ? 'checked' : ''; ?>> Publish public audit artifacts</label>
        </div>
        <div class="field field--full">
            <button class="button button--primary" type="submit"><?= $event ? 'Save event' : 'Create event'; ?></button>
        </div>
    </form>
    <?php if ($event): ?>
        <div class="inline-actions">
            <a class="button button--ghost" href="<?= e(base_url('/creator/candidates.php?event=' . $event['id'])); ?>">Manage candidates</a>
            <a class="button button--ghost" href="<?= e(base_url('/creator/required_fields.php?event=' . $event['id'])); ?>">Manage required fields</a>
            <a class="button button--ghost" href="<?= e(base_url('/creator/verification_methods.php?event=' . $event['id'])); ?>">Manage verification methods</a>
        </div>
    <?php endif; ?>
</section>

<?php if ($event): ?>
    <section class="grid-2">
        <article class="panel">
            <span class="eyebrow">Assign Co-Admin</span>
            <h2>Event assistance</h2>
            <form method="post" class="form-grid form-grid--single">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" value="assign_coadmin">
                <input type="hidden" name="event_id" value="<?= e((string) $event['id']); ?>">
                <div class="field">
                    <label for="coadmin_user_id">User</label>
                    <select id="coadmin_user_id" name="user_id" required>
                        <option value="">Select a co-admin</option>
                        <?php foreach ($coAdminUsers as $candidateUser): ?>
                            <option value="<?= e((string) $candidateUser['id']); ?>"><?= e($candidateUser['full_name'] . ' (' . $candidateUser['email'] . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <label><input type="checkbox" name="permission_review_verifications" value="1" checked> Review verifications</label>
                <label><input type="checkbox" name="permission_issue_tokens" value="1" checked> Issue tokens</label>
                <label><input type="checkbox" name="permission_view_results" value="1" checked> View results</label>
                <label><input type="checkbox" name="permission_manage_candidates" value="1"> Manage candidates</label>
                <label><input type="checkbox" name="permission_manage_fields" value="1"> Manage required fields</label>
                <button class="button button--primary" type="submit">Assign co-admin</button>
            </form>
            <div class="list-shell">
                <?php foreach ($assignedCoAdmins as $assignment): ?>
                    <?php
                    $permissionLabels = array_map(
                        static fn(string $permission): string => format_status($permission),
                        array_keys(array_filter(json_decode_array($assignment['permissions_json'] ?? null)))
                    );
                    ?>
                    <div class="list-row">
                        <div>
                            <strong><?= e($assignment['full_name']); ?></strong>
                            <p><?= e($assignment['email']); ?></p>
                            <p><?= e(implode(', ', $permissionLabels)); ?></p>
                        </div>
                        <div class="table-actions">
                            <span class="badge <?= $assignment['is_active'] ? 'badge-success' : 'badge-muted'; ?>"><?= $assignment['is_active'] ? 'Active' : 'Inactive'; ?></span>
                            <form method="post">
                                <?= csrf_field(); ?>
                                <input type="hidden" name="action" value="toggle_coadmin_status">
                                <input type="hidden" name="event_id" value="<?= e((string) $event['id']); ?>">
                                <input type="hidden" name="assignment_id" value="<?= e((string) $assignment['id']); ?>">
                                <button class="button button--ghost" type="submit"><?= $assignment['is_active'] ? 'Deactivate' : 'Reactivate'; ?></button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </article>

        <article class="panel">
            <span class="eyebrow">Assign Verifier</span>
            <h2>Trusted verifier access</h2>
            <form method="post" class="form-grid form-grid--single">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" value="assign_verifier">
                <input type="hidden" name="event_id" value="<?= e((string) $event['id']); ?>">
                <div class="field">
                    <label for="verifier_user_id">User</label>
                    <select id="verifier_user_id" name="user_id" required>
                        <option value="">Select a verifier</option>
                        <?php foreach ($verifierUsers as $candidateUser): ?>
                            <option value="<?= e((string) $candidateUser['id']); ?>"><?= e($candidateUser['full_name'] . ' (' . $candidateUser['email'] . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="button button--primary" type="submit">Assign verifier</button>
            </form>
            <div class="list-shell">
                <?php foreach ($assignedVerifiers as $assignment): ?>
                    <div class="list-row">
                        <div>
                            <strong><?= e($assignment['full_name']); ?></strong>
                            <p><?= e($assignment['email']); ?></p>
                        </div>
                        <div class="table-actions">
                            <span class="badge <?= $assignment['is_active'] ? 'badge-success' : 'badge-muted'; ?>"><?= $assignment['is_active'] ? 'Active' : 'Inactive'; ?></span>
                            <form method="post">
                                <?= csrf_field(); ?>
                                <input type="hidden" name="action" value="toggle_verifier_status">
                                <input type="hidden" name="event_id" value="<?= e((string) $event['id']); ?>">
                                <input type="hidden" name="assignment_id" value="<?= e((string) $assignment['id']); ?>">
                                <button class="button button--ghost" type="submit"><?= $assignment['is_active'] ? 'Deactivate' : 'Reactivate'; ?></button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </article>
    </section>
<?php endif; ?>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
