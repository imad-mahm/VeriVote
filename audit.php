<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$eventId = (int) ($_GET['event'] ?? 0);

if ($eventId === 0) {
    redirect('/events.php');
}

$pageTitle       = 'Audit Verification';
$pageDescription = 'Public audit artifacts, used credential proofs, and anonymous ballot receipts.';
$activeNav       = 'events';
$event           = fetch_event_by_id($eventId);

include __DIR__ . '/includes/header.php';
?>
<?php if (!$event || !can_view_public_audit($event)): ?>
    <section class="section section--top">
        <div class="alert alert--warning">Public audit output is not available for this election. <a href="<?= e(base_url('/events.php')); ?>" style="color:inherit;text-decoration:underline;">Back to elections</a></div>
    </section>

<?php else: ?>
    <?php
    $usedTokens = fetch_all(
        'SELECT token_reference, public_token_hash, issued_at, used_at
         FROM voting_tokens
         WHERE event_id = :event_id AND status = "used"
         ORDER BY used_at DESC',
        ['event_id' => $event['id']]
    );
    $ledgerRows = fetch_all(
        'SELECT public_receipt_hash, ballot_hash, submitted_at
         FROM ballots
         WHERE event_id = :event_id
         ORDER BY submitted_at DESC
         LIMIT 50',
        ['event_id' => $event['id']]
    );
    $snapshot = latest_result_snapshot((int) $event['id']);
    ?>

    <section class="section section--top" data-reveal>
        <div class="page-intro">
            <div>
                <span class="eyebrow">Audit surface</span>
                <h1><?= e($event['title']); ?></h1>
                <p style="margin-top:12px;">Used token proofs and ballot receipt hashes are published without exposing who held a given token or what any named voter selected.</p>
            </div>
            <?php if ($snapshot): ?>
            <div class="def-list">
                <dl>
                    <div class="def-row">
                        <dt>Snapshot hash</dt>
                        <dd style="font-family:ui-monospace,monospace;font-size:0.78rem;word-break:break-all;"><?= e(substr($snapshot['integrity_hash'], 0, 20) . '...'); ?></dd>
                    </div>
                    <div class="def-row">
                        <dt>Generated</dt>
                        <dd><?= e(format_datetime($snapshot['created_at'])); ?></dd>
                    </div>
                </dl>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="section" data-reveal>
        <div class="grid-2">
            <article class="list-shell">
                <span class="eyebrow">Used credential proofs</span>
                <h2>Token ledger</h2>
                <?php if (!$usedTokens): ?>
                    <p style="color:var(--ink-3); font-size:0.88rem;">No used tokens published for this event yet.</p>
                <?php else: ?>
                    <?php foreach ($usedTokens as $token): ?>
                        <div class="list-row">
                            <div>
                                <strong style="font-family:ui-monospace,monospace;font-size:0.82rem;"><?= e($token['token_reference']); ?></strong>
                                <p style="font-size:0.78rem;word-break:break-all;"><?= e($token['public_token_hash']); ?></p>
                            </div>
                            <span class="badge badge-success" style="flex-shrink:0;"><?= e(format_datetime($token['used_at'], 'M j H:i')); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </article>

            <article class="list-shell">
                <span class="eyebrow">Ballot ledger</span>
                <h2>Anonymous receipt hashes</h2>
                <div data-audit-feed data-event-id="<?= e((string) $event['id']); ?>">
                    <?php foreach ($ledgerRows as $row): ?>
                        <div class="list-row">
                            <div>
                                <strong style="font-family:ui-monospace,monospace;font-size:0.78rem;word-break:break-all;"><?= e($row['public_receipt_hash']); ?></strong>
                                <p style="font-size:0.74rem;word-break:break-all;"><?= e($row['ballot_hash']); ?></p>
                            </div>
                            <span class="badge badge-muted" style="flex-shrink:0;"><?= e(format_datetime($row['submitted_at'], 'M j H:i')); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </article>
        </div>
    </section>
<?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
