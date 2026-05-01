<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$eventId = (int) ($_GET['event'] ?? $_POST['event_id'] ?? 0);
$event = fetch_event_by_id($eventId);

if (!$event) {
    flash('error', 'Event not found.');
    redirect('/creator/dashboard.php');
}

require_event_permission($eventId, 'manage_fields');

if (is_post_request()) {
    verify_csrf_or_fail();
    $action = (string) ($_POST['action'] ?? 'save');

    if ($action === 'save') {
        $fieldId = (int) ($_POST['field_id'] ?? 0);
        $label = trim((string) ($_POST['field_label'] ?? ''));
        $type = (string) ($_POST['field_type'] ?? 'text');
        $keyInput = trim((string) ($_POST['field_key'] ?? ''));
        $fieldKey = $keyInput !== '' ? slugify($keyInput) : slugify($label);
        $placeholder = trim((string) ($_POST['placeholder'] ?? ''));
        $helpText = trim((string) ($_POST['help_text'] ?? ''));
        $fieldOrder = (int) ($_POST['field_order'] ?? 1);
        $isRequired = !empty($_POST['is_required']) ? 1 : 0;
        $isSystemField = !empty($_POST['is_system_field']) ? 1 : 0;
        $options = array_values(array_filter(array_map('trim', explode(',', (string) ($_POST['options_csv'] ?? '')))));

        if ($label === '') {
            flash('error', 'Field label is required.');
        } else {
            try {
                if ($fieldId > 0) {
                    execute_statement(
                        'UPDATE event_required_fields
                         SET field_key = :field_key,
                             field_label = :field_label,
                             field_type = :field_type,
                             placeholder = :placeholder,
                             help_text = :help_text,
                             options_json = :options_json,
                             validation_rules_json = :validation_rules_json,
                             is_required = :is_required,
                             is_system_field = :is_system_field,
                             field_order = :field_order
                         WHERE id = :id AND event_id = :event_id',
                        [
                            'field_key' => $fieldKey,
                            'field_label' => $label,
                            'field_type' => $type,
                            'placeholder' => $placeholder !== '' ? $placeholder : null,
                            'help_text' => $helpText !== '' ? $helpText : null,
                            'options_json' => $options ? json_encode($options, JSON_UNESCAPED_SLASHES) : null,
                            'validation_rules_json' => null,
                            'is_required' => $isRequired,
                            'is_system_field' => $isSystemField,
                            'field_order' => $fieldOrder,
                            'id' => $fieldId,
                            'event_id' => $eventId,
                        ]
                    );
                    write_audit_log('required_field_updated', 'event_required_fields', (string) $fieldId, 'Required field updated.', $eventId);
                    flash('success', 'Required field updated.');
                } else {
                    execute_statement(
                        'INSERT INTO event_required_fields (
                             event_id, field_key, field_label, field_type, placeholder, help_text,
                             options_json, validation_rules_json, is_required, is_system_field, field_order
                         ) VALUES (
                             :event_id, :field_key, :field_label, :field_type, :placeholder, :help_text,
                             :options_json, :validation_rules_json, :is_required, :is_system_field, :field_order
                         )',
                        [
                            'event_id' => $eventId,
                            'field_key' => $fieldKey,
                            'field_label' => $label,
                            'field_type' => $type,
                            'placeholder' => $placeholder !== '' ? $placeholder : null,
                            'help_text' => $helpText !== '' ? $helpText : null,
                            'options_json' => $options ? json_encode($options, JSON_UNESCAPED_SLASHES) : null,
                            'validation_rules_json' => null,
                            'is_required' => $isRequired,
                            'is_system_field' => $isSystemField,
                            'field_order' => $fieldOrder,
                        ]
                    );
                    write_audit_log('required_field_added', 'event_required_fields', (string) db()->lastInsertId(), 'Required field added to event.', $eventId);
                    flash('success', 'Required field saved.');
                }
            } catch (Throwable $exception) {
                flash('error', 'Could not save the field. Make sure the field key is unique for this event.');
            }
        }
    }

    if ($action === 'delete') {
        $fieldId = (int) ($_POST['field_id'] ?? 0);
        execute_statement(
            'DELETE FROM event_required_fields WHERE id = :id AND event_id = :event_id',
            ['id' => $fieldId, 'event_id' => $eventId]
        );
        write_audit_log('required_field_deleted', 'event_required_fields', (string) $fieldId, 'Required field removed from event.', $eventId);
        flash('success', 'Required field removed.');
    }

    redirect('/creator/required_fields.php?event=' . $eventId);
}

$fields = fetch_event_required_fields($eventId);
$editFieldId = (int) ($_GET['edit'] ?? 0);
$editField = $editFieldId > 0 ? fetch_one(
    'SELECT * FROM event_required_fields WHERE id = :id AND event_id = :event_id',
    ['id' => $editFieldId, 'event_id' => $eventId]
) : null;

$pageTitle = 'Required Fields';
$pageHeading = 'Required Fields';
$pageDescription = 'Configure dynamic voter data requirements for this event.';
$isDashboard = true;
$sidebarContext = current_role_slug() ?? 'event_creator';
$activeEventTool = 'required-fields';
$eventContextId = $eventId;

include dirname(__DIR__) . '/includes/header.php';
?>
<section class="grid-2">
    <article class="panel">
        <span class="eyebrow"><?= $editField ? 'Edit Field' : 'Add Field'; ?></span>
        <h2><?= e($event['title']); ?></h2>
        <form method="post" class="form-grid">
            <?= csrf_field(); ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="event_id" value="<?= e((string) $eventId); ?>">
            <input type="hidden" name="field_id" value="<?= e((string) ($editField['id'] ?? 0)); ?>">
            <div class="field">
                <label for="field_label">Field label</label>
                <input id="field_label" type="text" name="field_label" value="<?= e((string) ($editField['field_label'] ?? '')); ?>" required>
            </div>
            <div class="field">
                <label for="field_key">Field key</label>
                <input id="field_key" type="text" name="field_key" value="<?= e((string) ($editField['field_key'] ?? '')); ?>" placeholder="auto-generated-if-empty">
            </div>
            <div class="field">
                <label for="field_type">Field type</label>
                <select id="field_type" name="field_type">
                    <?php foreach (['text', 'email', 'phone', 'date', 'textarea', 'select', 'file', 'image', 'id_number', 'passport', 'address'] as $type): ?>
                        <option value="<?= e($type); ?>" <?= (($editField['field_type'] ?? 'text') === $type) ? 'selected' : ''; ?>><?= e(format_status($type)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="field_order">Order</label>
                <input id="field_order" type="number" name="field_order" min="1" value="<?= e((string) ($editField['field_order'] ?? (count($fields) + 1))); ?>">
            </div>
            <div class="field field--full">
                <label for="placeholder">Placeholder</label>
                <input id="placeholder" type="text" name="placeholder" value="<?= e((string) ($editField['placeholder'] ?? '')); ?>">
            </div>
            <div class="field field--full">
                <label for="help_text">Help text</label>
                <textarea id="help_text" name="help_text"><?= e((string) ($editField['help_text'] ?? '')); ?></textarea>
            </div>
            <div class="field field--full">
                <label for="options_csv">Select options (comma-separated)</label>
                <input id="options_csv" type="text" name="options_csv" value="<?= e(implode(', ', json_decode_array($editField['options_json'] ?? null))); ?>">
            </div>
            <label><input type="checkbox" name="is_required" value="1" <?= !isset($editField['is_required']) || (int) $editField['is_required'] === 1 ? 'checked' : ''; ?>> Required</label>
            <label><input type="checkbox" name="is_system_field" value="1" <?= isset($editField['is_system_field']) && (int) $editField['is_system_field'] === 1 ? 'checked' : ''; ?>> System field</label>
            <div class="field field--full">
                <button class="button button--primary" type="submit"><?= $editField ? 'Save field' : 'Add field'; ?></button>
                <?php if ($editField): ?>
                    <a class="button button--ghost" href="<?= e(base_url('/creator/required_fields.php?event=' . $eventId)); ?>">Cancel edit</a>
                <?php endif; ?>
            </div>
        </form>
    </article>

    <article class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Field</th>
                <th>Type</th>
                <th>Flags</th>
                <th>Action</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($fields as $field): ?>
                <tr>
                    <td>
                        <strong><?= e($field['field_label']); ?></strong>
                        <p><?= e($field['field_key']); ?></p>
                    </td>
                    <td><?= e(format_status($field['field_type'])); ?></td>
                    <td>
                        <div class="pill-row">
                            <span class="badge <?= $field['is_required'] ? 'badge-warning' : 'badge-muted'; ?>"><?= $field['is_required'] ? 'Required' : 'Optional'; ?></span>
                            <span class="badge <?= $field['is_system_field'] ? 'badge-muted' : 'badge-success'; ?>"><?= $field['is_system_field'] ? 'System' : 'Custom'; ?></span>
                        </div>
                    </td>
                    <td>
                        <div class="table-actions">
                            <a href="<?= e(base_url('/creator/required_fields.php?event=' . $eventId . '&edit=' . $field['id'])); ?>">Edit</a>
                            <form method="post">
                                <?= csrf_field(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="event_id" value="<?= e((string) $eventId); ?>">
                                <input type="hidden" name="field_id" value="<?= e((string) $field['id']); ?>">
                                <button class="button button--danger" type="submit" data-confirm="Delete this field configuration?">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </article>
</section>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
