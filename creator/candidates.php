<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$eventId = (int) ($_GET['event'] ?? $_POST['event_id'] ?? 0);
$event = fetch_event_by_id($eventId);

if (!$event) {
    flash('error', 'Event not found.');
    redirect('/creator/dashboard.php');
}

require_event_permission($eventId, 'manage_candidates');

if (is_post_request()) {
    verify_csrf_or_fail();
    $action = (string) ($_POST['action'] ?? 'save');

    if ($action === 'save') {
        $candidateId = (int) ($_POST['candidate_id'] ?? 0);
        $label = trim((string) ($_POST['option_label'] ?? ''));
        $description = trim((string) ($_POST['option_description'] ?? ''));
        $order = (int) ($_POST['display_order'] ?? 1);

        if ($label === '') {
            flash('error', 'Candidate or option label is required.');
        } else {
            if ($candidateId > 0) {
                execute_statement(
                    'UPDATE candidates_or_options
                     SET option_label = :option_label,
                         option_description = :option_description,
                         display_order = :display_order
                     WHERE id = :id AND event_id = :event_id',
                    [
                        'option_label' => $label,
                        'option_description' => $description !== '' ? $description : null,
                        'display_order' => $order,
                        'id' => $candidateId,
                        'event_id' => $eventId,
                    ]
                );
                write_audit_log('candidate_updated', 'candidates_or_options', (string) $candidateId, 'Ballot option updated.', $eventId);
                flash('success', 'Option updated successfully.');
            } else {
                execute_statement(
                    'INSERT INTO candidates_or_options (event_id, option_label, option_description, display_order, is_active)
                     VALUES (:event_id, :option_label, :option_description, :display_order, 1)',
                    [
                        'event_id' => $eventId,
                        'option_label' => $label,
                        'option_description' => $description !== '' ? $description : null,
                        'display_order' => $order,
                    ]
                );
                write_audit_log('candidate_added', 'candidates_or_options', (string) db()->lastInsertId(), 'Ballot option added.', $eventId);
                flash('success', 'Option added successfully.');
            }
        }
    }

    if ($action === 'toggle') {
        $candidateId = (int) ($_POST['candidate_id'] ?? 0);
        $candidate = fetch_one(
            'SELECT * FROM candidates_or_options WHERE id = :id AND event_id = :event_id',
            ['id' => $candidateId, 'event_id' => $eventId]
        );

        if ($candidate) {
            $newState = (int) $candidate['is_active'] === 1 ? 0 : 1;
            execute_statement(
                'UPDATE candidates_or_options SET is_active = :is_active WHERE id = :id',
                ['is_active' => $newState, 'id' => $candidateId]
            );
            write_audit_log('candidate_toggled', 'candidates_or_options', (string) $candidateId, 'Ballot option state changed.', $eventId, ['is_active' => $newState]);
            flash('success', 'Option state updated.');
        }
    }

    redirect('/creator/candidates.php?event=' . $eventId);
}

$candidates = fetch_event_candidates($eventId);
$editCandidateId = (int) ($_GET['edit'] ?? 0);
$editCandidate = $editCandidateId > 0 ? fetch_one(
    'SELECT * FROM candidates_or_options WHERE id = :id AND event_id = :event_id',
    ['id' => $editCandidateId, 'event_id' => $eventId]
) : null;

$pageTitle = 'Candidate Management';
$pageHeading = 'Candidate Management';
$pageDescription = 'Add and manage ballot options for this event.';
$isDashboard = true;
$sidebarContext = current_role_slug() ?? 'event_creator';
$activeSidebar = 'creator-dashboard';
$activeEventTool = 'candidates';
$eventContextId = $eventId;

include dirname(__DIR__) . '/includes/header.php';
?>
<section class="grid-2">
    <article class="panel">
        <span class="eyebrow"><?= $editCandidate ? 'Edit Option' : 'Add Option'; ?></span>
        <h2><?= e($event['title']); ?></h2>
        <form method="post" class="form-grid form-grid--single">
            <?= csrf_field(); ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="event_id" value="<?= e((string) $eventId); ?>">
            <input type="hidden" name="candidate_id" value="<?= e((string) ($editCandidate['id'] ?? 0)); ?>">
            <div class="field">
                <label for="option_label">Candidate / option label</label>
                <input id="option_label" type="text" name="option_label" value="<?= e((string) ($editCandidate['option_label'] ?? '')); ?>" required>
            </div>
            <div class="field">
                <label for="option_description">Description</label>
                <textarea id="option_description" name="option_description"><?= e((string) ($editCandidate['option_description'] ?? '')); ?></textarea>
            </div>
            <div class="field">
                <label for="display_order">Display order</label>
                <input id="display_order" type="number" name="display_order" min="1" value="<?= e((string) ($editCandidate['display_order'] ?? 1)); ?>">
            </div>
            <button class="button button--primary" type="submit"><?= $editCandidate ? 'Save option' : 'Add option'; ?></button>
            <?php if ($editCandidate): ?>
                <a class="button button--ghost" href="<?= e(base_url('/creator/candidates.php?event=' . $eventId)); ?>">Cancel edit</a>
            <?php endif; ?>
        </form>
    </article>

    <article class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Option</th>
                <th>Status</th>
                <th>Order</th>
                <th>Action</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($candidates as $candidate): ?>
                <tr>
                    <td>
                        <strong><?= e($candidate['option_label']); ?></strong>
                        <p><?= e($candidate['option_description']); ?></p>
                    </td>
                    <td><span class="badge <?= $candidate['is_active'] ? 'badge-success' : 'badge-muted'; ?>"><?= $candidate['is_active'] ? 'Active' : 'Inactive'; ?></span></td>
                    <td><?= e((string) $candidate['display_order']); ?></td>
                    <td>
                        <form method="post">
                            <?= csrf_field(); ?>
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="event_id" value="<?= e((string) $eventId); ?>">
                            <input type="hidden" name="candidate_id" value="<?= e((string) $candidate['id']); ?>">
                            <div class="table-actions">
                                <a href="<?= e(base_url('/creator/candidates.php?event=' . $eventId . '&edit=' . $candidate['id'])); ?>">Edit</a>
                                <button class="button button--ghost" type="submit"><?= $candidate['is_active'] ? 'Deactivate' : 'Activate'; ?></button>
                            </div>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </article>
</section>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
