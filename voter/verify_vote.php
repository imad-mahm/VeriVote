<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$eventId = (int) ($_GET['event'] ?? $_POST['event_id'] ?? 0);
$event = $eventId > 0 ? fetch_event_by_id($eventId) : null;
$ballot = null;

if (is_post_request()) {
    verify_csrf_or_fail();
    $eventId = (int) ($_POST['event_id'] ?? 0);
    $event = fetch_event_by_id($eventId);

    if (!$event || !(bool) $event['personal_verification_enabled']) {
        flash('error', 'This event does not support personal receipt verification.');
        redirect('/voter/verify_vote.php');
    }

    $receipt = trim((string) ($_POST['receipt'] ?? ''));
    $ballot = find_ballot_by_receipt($eventId, $receipt);

    if (!$ballot) {
        flash('error', 'No ballot was found for that receipt and event.');
        redirect('/voter/verify_vote.php?event=' . $eventId);
    }
}

$pageTitle = 'Verify Vote';
$pageHeading = 'Verify Vote';
$pageDescription = 'Confirm a recorded ballot using a private Verivote receipt.';
$isDashboard = true;
$sidebarContext = current_role_slug() ?? 'voter';
$activeSidebar = 'voter-verify';

include dirname(__DIR__) . '/includes/header.php';
?>
<section class="panel">
    <span class="eyebrow">Receipt Verification</span>
    <h2>Check your recorded ballot</h2>
    <form method="post" class="form-grid">
        <?= csrf_field(); ?>
        <div class="field">
            <label for="event_id">Event</label>
            <select id="event_id" name="event_id" required>
                <option value="">Select an event</option>
                <?php foreach (fetch_public_events() as $listEvent): ?>
                    <?php if ((int) $listEvent['personal_verification_enabled'] === 1): ?>
                        <option value="<?= e((string) $listEvent['id']); ?>" <?= $eventId === (int) $listEvent['id'] ? 'selected' : ''; ?>>
                            <?= e($listEvent['title']); ?>
                        </option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="receipt">Private receipt</label>
            <input id="receipt" type="text" name="receipt" placeholder="VR-..." required>
        </div>
        <div class="field field--full">
            <button class="button button--primary" type="submit">Verify receipt</button>
        </div>
    </form>
</section>

<?php if ($ballot): ?>
    <section class="grid-2">
        <article class="panel">
            <span class="eyebrow">Recorded Ballot</span>
            <h2><?= e($ballot['event_title']); ?></h2>
            <div class="list-shell">
                <div class="list-row">
                    <strong>Recorded option</strong>
                    <span><?= e($ballot['option_snapshot']); ?></span>
                </div>
                <div class="list-row">
                    <strong>Recorded at</strong>
                    <span><?= e(format_datetime($ballot['submitted_at'], 'M j, Y H:i')); ?></span>
                </div>
                <div class="list-row">
                    <strong>Public receipt hash</strong>
                    <span><?= e($ballot['public_receipt_hash']); ?></span>
                </div>
            </div>
        </article>
        <article class="ledger">
            <span class="eyebrow">Ballot Proof</span>
            <h2>Verification artifact</h2>
            <pre><?= e($ballot['ballot_hash']); ?></pre>
        </article>
    </section>
<?php endif; ?>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
