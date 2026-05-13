<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$eventId = (int) ($_GET['event'] ?? $_POST['event_id'] ?? 0);
$event = fetch_event_by_id($eventId);

if (!$event) {
    flash('error', 'Event not found.');
    redirect('/creator/dashboard.php');
}

require_event_permission($eventId, 'manage_event');

$catalog = verification_method_catalog();

if (is_post_request()) {
    verify_csrf_or_fail();
    $action = (string) ($_POST['action'] ?? 'save_method');

    if ($action === 'save_method') {
        $methodId = (int) ($_POST['method_id'] ?? 0);
        $editing = $methodId > 0 ? fetch_one(
            'SELECT * FROM verification_methods WHERE id = :id AND event_id = :event_id',
            ['id' => $methodId, 'event_id' => $eventId]
        ) : null;

        $methodKey = $editing['method_key'] ?? trim((string) ($_POST['method_key'] ?? ''));
        $definition = verification_method_definition($methodKey);
        $label = trim((string) ($_POST['label'] ?? ($definition['label'] ?? '')));
        $description = trim((string) ($_POST['description'] ?? ($definition['description'] ?? '')));
        $instructions = trim((string) ($_POST['instructions'] ?? ''));
        $sequenceOrder = max(1, (int) ($_POST['sequence_order'] ?? 1));
        $isRequired = !empty($_POST['is_required']) ? 1 : 0;
        $isActive = !empty($_POST['is_active']) ? 1 : 0;
        $errors = [];

        if (!$definition) {
            $errors[] = 'Select a supported verification method.';
        }

        if ($label === '') {
            $errors[] = 'Method label is required.';
        }

        if ($editing === null && fetch_one(
            'SELECT id FROM verification_methods WHERE event_id = :event_id AND method_key = :method_key',
            ['event_id' => $eventId, 'method_key' => $methodKey]
        )) {
            $errors[] = 'That verification method already exists for this event.';
        }

        if ($errors === []) {
            $config = $definition['config'];
            if ($instructions !== '') {
                $config['instructions'] = $instructions;
            }

            if ($editing) {
                execute_statement(
                    'UPDATE verification_methods
                     SET label = :label,
                         description = :description,
                         is_required = :is_required,
                         requires_reviewer = :requires_reviewer,
                         sequence_order = :sequence_order,
                         config_json = :config_json,
                         is_active = :is_active
                     WHERE id = :id AND event_id = :event_id',
                    [
                        'label' => $label,
                        'description' => $description !== '' ? $description : null,
                        'is_required' => $isRequired,
                        'requires_reviewer' => $definition['requires_reviewer'] ? 1 : 0,
                        'sequence_order' => $sequenceOrder,
                        'config_json' => json_or_null($config),
                        'is_active' => $isActive,
                        'id' => $editing['id'],
                        'event_id' => $eventId,
                    ]
                );
                write_audit_log('verification_method_updated', 'verification_methods', (string) $editing['id'], 'Verification method updated.', $eventId, ['method_key' => $methodKey]);
                flash('success', 'Verification method updated.');
            } else {
                execute_statement(
                    'INSERT INTO verification_methods (
                         event_id, method_key, label, description, is_required, requires_reviewer, sequence_order, config_json, is_active
                     ) VALUES (
                         :event_id, :method_key, :label, :description, :is_required, :requires_reviewer, :sequence_order, :config_json, :is_active
                     )',
                    [
                        'event_id' => $eventId,
                        'method_key' => $methodKey,
                        'label' => $label,
                        'description' => $description !== '' ? $description : null,
                        'is_required' => $isRequired,
                        'requires_reviewer' => $definition['requires_reviewer'] ? 1 : 0,
                        'sequence_order' => $sequenceOrder,
                        'config_json' => json_or_null($config),
                        'is_active' => $isActive,
                    ]
                );
                write_audit_log('verification_method_added', 'verification_methods', (string) db()->lastInsertId(), 'Verification method added.', $eventId, ['method_key' => $methodKey]);
                flash('success', 'Verification method added.');
            }
        } else {
            flash_errors($errors);
        }
    }

    if ($action === 'toggle_method') {
        $methodId = (int) ($_POST['method_id'] ?? 0);
        $method = fetch_one(
            'SELECT * FROM verification_methods WHERE id = :id AND event_id = :event_id',
            ['id' => $methodId, 'event_id' => $eventId]
        );

        if ($method) {
            $newState = (int) $method['is_active'] === 1 ? 0 : 1;
            execute_statement(
                'UPDATE verification_methods SET is_active = :is_active WHERE id = :id',
                ['is_active' => $newState, 'id' => $methodId]
            );
            write_audit_log('verification_method_toggled', 'verification_methods', (string) $methodId, 'Verification method active state changed.', $eventId, ['is_active' => $newState]);
            flash('success', 'Verification method state updated.');
        }
    }

    if ($action === 'delete_method') {
        $methodId = (int) ($_POST['method_id'] ?? 0);
        $linkedVerification = fetch_one('SELECT id FROM voter_verifications WHERE verification_method_id = :id LIMIT 1', ['id' => $methodId]);

        if ($linkedVerification) {
            flash('error', 'This method has already been used by submissions. Deactivate it instead of deleting it.');
        } else {
            execute_statement(
                'DELETE FROM verification_methods WHERE id = :id AND event_id = :event_id',
                ['id' => $methodId, 'event_id' => $eventId]
            );
            write_audit_log('verification_method_deleted', 'verification_methods', (string) $methodId, 'Verification method deleted.', $eventId);
            flash('success', 'Verification method deleted.');
        }
    }

    redirect('/creator/verification_methods.php?event=' . $eventId);
}

$methods = fetch_event_verification_methods($eventId);
$editMethodId = (int) ($_GET['edit'] ?? 0);
$editMethod = $editMethodId > 0 ? fetch_one(
    'SELECT * FROM verification_methods WHERE id = :id AND event_id = :event_id',
    ['id' => $editMethodId, 'event_id' => $eventId]
) : null;
$existingKeys = array_map(static fn(array $method): string => $method['method_key'], $methods);
$availableKeys = array_values(array_filter(
    supported_verification_method_keys(),
    static fn(string $key): bool => !in_array($key, $existingKeys, true)
));
$readiness = event_readiness($eventId);

$pageTitle = 'Verification Methods';
$pageHeading = 'Verification Methods';
$pageDescription = 'Configure the event verification policy that drives voter eligibility approval.';
$isDashboard = true;
$sidebarContext = current_role_slug() ?? 'event_creator';
$activeSidebar = 'creator-dashboard';
$activeEventTool = 'verification-methods';
$eventContextId = $eventId;

include dirname(__DIR__) . '/includes/header.php';
?>
<div class="stat-strip">
    <div class="stat-strip__item">
        <strong><?= e((string) count($methods)); ?></strong>
        <p>Configured methods</p>
    </div>
    <div class="stat-strip__item">
        <strong><?= e((string) count(array_filter($methods, static fn(array $method): bool => (int) $method['is_active'] === 1))); ?></strong>
        <p>Active</p>
    </div>
    <div class="stat-strip__item">
        <strong><?= e((string) count(array_filter($methods, static fn(array $method): bool => (int) $method['requires_reviewer'] === 1))); ?></strong>
        <p>Reviewer-driven</p>
    </div>
    <div class="stat-strip__item">
        <strong><?= $readiness['verification_method_count'] > 0 ? 'Ready' : 'Missing'; ?></strong>
        <p>Verification status</p>
    </div>
</div>

<section class="grid-2">
    <article class="panel">
        <span class="eyebrow"><?= $editMethod ? 'Edit Method' : 'Add Method'; ?></span>
        <h2><?= e($event['title']); ?></h2>
        <form method="post" class="form-grid form-grid--single">
            <?= csrf_field(); ?>
            <input type="hidden" name="action" value="save_method">
            <input type="hidden" name="event_id" value="<?= e((string) $eventId); ?>">
            <input type="hidden" name="method_id" value="<?= e((string) ($editMethod['id'] ?? 0)); ?>">
            <div class="field">
                <label for="method_key">Method family</label>
                <?php if ($editMethod): ?>
                    <input id="method_key" type="text" value="<?= e(format_status($editMethod['method_key'])); ?>" disabled>
                <?php else: ?>
                    <select id="method_key" name="method_key" required>
                        <option value="">Select a method</option>
                        <?php foreach ($availableKeys as $methodKey): ?>
                            <?php $definition = verification_method_definition($methodKey); ?>
                            <option value="<?= e($methodKey); ?>"><?= e($definition['label']); ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </div>
            <div class="field">
                <label for="label">Display label</label>
                <input id="label" type="text" name="label" value="<?= e($editMethod['label'] ?? ''); ?>" required>
            </div>
            <div class="field">
                <label for="description">Description</label>
                <textarea id="description" name="description"><?= e((string) ($editMethod['description'] ?? '')); ?></textarea>
            </div>
            <div class="field">
                <label for="instructions">Internal instructions</label>
                <textarea id="instructions" name="instructions"><?= e((string) (json_decode_array($editMethod['config_json'] ?? null)['instructions'] ?? '')); ?></textarea>
            </div>
            <div class="field">
                <label for="sequence_order">Sequence order</label>
                <input id="sequence_order" type="number" min="1" name="sequence_order" value="<?= e((string) ($editMethod['sequence_order'] ?? (count($methods) + 1))); ?>">
            </div>
            <label><input type="checkbox" name="is_required" value="1" <?= !isset($editMethod['is_required']) || (int) $editMethod['is_required'] === 1 ? 'checked' : ''; ?>> Required for approval</label>
            <label><input type="checkbox" name="is_active" value="1" <?= !isset($editMethod['is_active']) || (int) $editMethod['is_active'] === 1 ? 'checked' : ''; ?>> Active</label>
            <button class="button button--primary" type="submit"><?= $editMethod ? 'Save method' : 'Add method'; ?></button>
            <?php if ($editMethod): ?>
                <a class="button button--ghost" href="<?= e(base_url('/creator/verification_methods.php?event=' . $eventId)); ?>">Cancel edit</a>
            <?php endif; ?>
        </form>
        <?php if (!$editMethod && $availableKeys === []): ?>
            <div class="alert alert--info">All supported verification method families have already been added to this event.</div>
        <?php endif; ?>
    </article>

    <article class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Method</th>
                <th>Status</th>
                <th>Flags</th>
                <th>Order</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$methods): ?>
                <tr><td colspan="5">No verification methods configured yet.</td></tr>
            <?php else: ?>
                <?php foreach ($methods as $method): ?>
                    <tr>
                        <td>
                            <strong><?= e($method['label']); ?></strong>
                            <p><?= e($method['method_key']); ?></p>
                        </td>
                        <td><span class="badge <?= (int) $method['is_active'] === 1 ? 'badge-success' : 'badge-muted'; ?>"><?= (int) $method['is_active'] === 1 ? 'Active' : 'Inactive'; ?></span></td>
                        <td>
                            <div class="pill-row">
                                <span class="badge <?= (int) $method['is_required'] === 1 ? 'badge-warning' : 'badge-muted'; ?>"><?= (int) $method['is_required'] === 1 ? 'Required' : 'Optional'; ?></span>
                                <span class="badge <?= (int) $method['requires_reviewer'] === 1 ? 'badge-success' : 'badge-muted'; ?>"><?= (int) $method['requires_reviewer'] === 1 ? 'Reviewer' : 'Self-service'; ?></span>
                            </div>
                        </td>
                        <td><?= e((string) $method['sequence_order']); ?></td>
                        <td class="table-actions">
                            <a href="<?= e(base_url('/creator/verification_methods.php?event=' . $eventId . '&edit=' . $method['id'])); ?>">Edit</a>
                            <form method="post">
                                <?= csrf_field(); ?>
                                <input type="hidden" name="action" value="toggle_method">
                                <input type="hidden" name="event_id" value="<?= e((string) $eventId); ?>">
                                <input type="hidden" name="method_id" value="<?= e((string) $method['id']); ?>">
                                <button class="button button--ghost" type="submit"><?= (int) $method['is_active'] === 1 ? 'Deactivate' : 'Activate'; ?></button>
                            </form>
                            <form method="post">
                                <?= csrf_field(); ?>
                                <input type="hidden" name="action" value="delete_method">
                                <input type="hidden" name="event_id" value="<?= e((string) $eventId); ?>">
                                <input type="hidden" name="method_id" value="<?= e((string) $method['id']); ?>">
                                <button class="button button--danger" type="submit" data-confirm="Delete this verification method?">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </article>
</section>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
