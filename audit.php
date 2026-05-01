<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Audit Verification';
$pageDescription = 'Public audit artifacts, used credential proofs, and anonymous ballot receipts.';
$activeNav = 'audit';
$eventId = (int) ($_GET['event'] ?? 0);
$event = $eventId > 0 ? fetch_event_by_id($eventId) : null;

include __DIR__ . '/includes/header.php';
?>
<?php if (!$event): ?>
    <?php $auditEvents = array_filter(fetch_public_events(), static fn(array $item): bool => can_view_public_audit($item)); ?>
    <section class="section">
        <div class="section-head">
            <div>
                <span class="eyebrow">Public Audit</span>
                <h2>Available verification ledgers</h2>
            </div>
            <p>Observers can inspect privacy-preserving receipt hashes and snapshot metadata without seeing voter identities.</p>
        </div>
        <div class="grid-cards">
            <?php foreach ($auditEvents as $auditEvent): ?>
                <article class="panel" data-reveal>
                    <div class="pill-row">
                        <span class="badge <?= e(badge_class($auditEvent['status'])); ?>"><?= e(format_status($auditEvent['status'])); ?></span>
                    </div>
                    <h3><?= e($auditEvent['title']); ?></h3>
                    <p><?= e($auditEvent['description']); ?></p>
                    <a class="button button--primary" href="<?= e(base_url('/audit.php?event=' . $auditEvent['id'])); ?>">Open audit page</a>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
<?php elseif (!can_view_public_audit($event)): ?>
    <section class="section">
        <div class="alert alert--warning">Public audit output is disabled for this event.</div>
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
    <section class="section">
        <article class="panel" data-reveal>
            <span class="eyebrow">Audit Surface</span>
            <h2><?= e($event['title']); ?></h2>
            <p>Used token proofs and ballot receipt hashes are published without exposing who held a given token or what any named person selected.</p>
            <?php if ($snapshot): ?>
                <div class="alert alert--info">Latest snapshot integrity hash: <?= e($snapshot['integrity_hash']); ?></div>
            <?php endif; ?>
        </article>
    </section>

    <section class="section">
        <div class="grid-2">
            <article class="list-shell" data-reveal>
                <span class="eyebrow">Used Credential Proofs</span>
                <h2>Published token ledger</h2>
                <?php if (!$usedTokens): ?>
                    <div class="alert alert--warning">No used tokens have been published for this event yet.</div>
                <?php else: ?>
                    <?php foreach ($usedTokens as $token): ?>
                        <div class="list-row">
                            <div>
                                <strong><?= e($token['token_reference']); ?></strong>
                                <p><?= e($token['public_token_hash']); ?></p>
                            </div>
                            <span class="badge badge-success"><?= e(format_datetime($token['used_at'], 'M j, Y H:i')); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </article>
            <article class="list-shell" data-reveal>
                <span class="eyebrow">Ballot Ledger</span>
                <h2>Anonymous receipt hashes</h2>
                <div data-audit-feed data-event-id="<?= e((string) $event['id']); ?>">
                    <?php foreach ($ledgerRows as $row): ?>
                        <div class="list-row">
                            <div>
                                <strong><?= e($row['public_receipt_hash']); ?></strong>
                                <p><?= e($row['ballot_hash']); ?></p>
                            </div>
                            <span class="badge badge-muted"><?= e(format_datetime($row['submitted_at'], 'M j H:i')); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </article>
        </div>
    </section>
<?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
