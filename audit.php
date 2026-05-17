<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$eventId = (int) ($_GET['event'] ?? 0);

if ($eventId === 0) {
    redirect('/events.php');
}

$pageTitle       = 'Public Audit';
$pageDescription = 'Verify that every ballot in this election was cast by an authorised voter and that no votes were added, removed, or changed.';
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
        'SELECT public_token_hash, issued_at, used_at
         FROM voting_tokens
         WHERE event_id = :event_id AND status = "used"
         ORDER BY used_at ASC',
        ['event_id' => $event['id']]
    );
    $ballotRows = fetch_all(
        'SELECT public_receipt_hash, submitted_at
         FROM ballots
         WHERE event_id = :event_id
         ORDER BY submitted_at ASC
         LIMIT 200',
        ['event_id' => $event['id']]
    );
    $snapshot = latest_result_snapshot((int) $event['id']);
    $totalBallots = count($ballotRows);
    $totalCredentials = count($usedTokens);
    ?>

    <section class="section section--top" data-reveal>
        <div class="breadcrumb" style="margin-bottom:16px;">
            <a href="<?= e(base_url('/events.php')); ?>">Elections</a>
            <span>&rsaquo;</span>
            <a href="<?= e(base_url('/event.php?event=' . $event['id'])); ?>"><?= e($event['title']); ?></a>
            <span>&rsaquo;</span>
            <span>Audit</span>
        </div>
        <div class="page-intro">
            <div>
                <span class="eyebrow">Public audit</span>
                <h1><?= e($event['title']); ?></h1>
                <p style="margin-top:12px;">This page lets anyone verify that every vote in this election was cast by an authorised voter — and that no votes were added, removed, or changed — without revealing who voted or what they chose.</p>
            </div>
            <div class="def-list">
                <dl>
                    <div class="def-row">
                        <dt>Credentials used</dt>
                        <dd><strong><?= e((string) $totalCredentials); ?></strong></dd>
                    </div>
                    <div class="def-row">
                        <dt>Ballots recorded</dt>
                        <dd><strong><?= e((string) $totalBallots); ?></strong></dd>
                    </div>
                    <?php if ($totalCredentials === $totalBallots): ?>
                    <div class="def-row">
                        <dt>Counts match</dt>
                        <dd><span class="badge badge-success">Yes</span></dd>
                    </div>
                    <?php else: ?>
                    <div class="def-row">
                        <dt>Counts match</dt>
                        <dd><span class="badge badge-error">No — discrepancy detected</span></dd>
                    </div>
                    <?php endif; ?>
                </dl>
            </div>
        </div>
    </section>

    <section class="section" data-reveal>
        <div class="inline-actions">
            <a class="button button--ghost" href="<?= e(base_url('/event.php?event=' . $event['id'])); ?>">&larr; Back to election</a>
            <?php if (can_view_public_results($event)): ?>
                <a class="button button--ghost" href="<?= e(base_url('/results.php?event=' . $event['id'])); ?>">View results</a>
            <?php endif; ?>
            <a class="button button--ghost" href="<?= e(base_url('/voter/verify_vote.php?event=' . $event['id'])); ?>">Verify my vote</a>
        </div>
    </section>

    <?php /* ── Section 1: Were all credentials legitimate? ── */ ?>
    <section class="section" data-reveal>
        <div class="section-rule">
            <div>
                <span class="eyebrow">Check 1 of 3</span>
                <h2>Were all credentials legitimate?</h2>
                <p style="margin-top:8px; max-width:65ch;">Each row below represents one approved voter who cast a ballot. The code shown is a <strong>fingerprint</strong> of their ballot credential — it proves the credential was genuine and consumed exactly once, without revealing who held it or what they voted.</p>
            </div>
            <span class="badge <?= $totalCredentials > 0 ? 'badge-success' : 'badge-muted'; ?>"><?= e((string) $totalCredentials); ?> credential<?= $totalCredentials !== 1 ? 's' : ''; ?></span>
        </div>

        <?php if (!$usedTokens): ?>
            <div class="alert alert--info">No ballots have been cast yet.</div>
        <?php else: ?>
            <div class="list-shell list-shell--bare">
                <?php foreach ($usedTokens as $i => $token): ?>
                    <div class="list-row">
                        <div>
                            <strong style="font-size:0.82rem;">Ballot #<?= $i + 1; ?></strong>
                            <p style="font-family:ui-monospace,monospace;font-size:0.74rem;word-break:break-all;color:var(--ink-3);margin-top:2px;"><?= e($token['public_token_hash']); ?></p>
                        </div>
                        <span class="badge badge-muted" style="flex-shrink:0;white-space:nowrap;"><?= e(format_datetime($token['used_at'], 'M j, H:i')); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <?php /* ── Section 2: Is my vote in here? ── */ ?>
    <section class="section" data-reveal>
        <div class="section-rule">
            <div>
                <span class="eyebrow">Check 2 of 3</span>
                <h2>Is my vote in here?</h2>
                <p style="margin-top:8px; max-width:65ch;">Each row is a <strong>fingerprint of one anonymous ballot</strong>. If you voted in this election, your private receipt code (the <code>VR-…</code> code shown after you voted) can be used to find your entry and confirm your vote was counted.</p>
                <p style="margin-top:6px; max-width:65ch;">Nobody can reverse these fingerprints to learn who cast which ballot or what option was chosen.</p>
            </div>
            <a class="button button--ghost" href="<?= e(base_url('/voter/verify_vote.php?event=' . $event['id'])); ?>">Verify my receipt &rarr;</a>
        </div>

        <?php if (!$ballotRows): ?>
            <div class="alert alert--info">No ballots have been recorded yet.</div>
        <?php else: ?>
            <div class="list-shell list-shell--bare">
                <?php foreach ($ballotRows as $i => $row): ?>
                    <div class="list-row">
                        <div>
                            <strong style="font-size:0.82rem;">Ballot #<?= $i + 1; ?></strong>
                            <p style="font-family:ui-monospace,monospace;font-size:0.74rem;word-break:break-all;color:var(--ink-3);margin-top:2px;"><?= e($row['public_receipt_hash']); ?></p>
                        </div>
                        <span class="badge badge-muted" style="flex-shrink:0;white-space:nowrap;"><?= e(format_datetime($row['submitted_at'], 'M j, H:i')); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <?php /* ── Section 3: Have the results been tampered with? ── */ ?>
    <section class="section" data-reveal>
        <div class="section-rule">
            <div>
                <span class="eyebrow">Check 3 of 3</span>
                <h2>Have the results been tampered with?</h2>
                <p style="margin-top:8px; max-width:65ch;">When results are published, a <strong>seal</strong> is generated from the exact vote counts at that moment. If anyone were to change even a single vote count after the fact, the seal would no longer match — making tampering immediately visible to anyone who checks.</p>
            </div>
        </div>

        <?php if ($snapshot): ?>
            <?php $snapshotData = json_decode((string) $snapshot['snapshot_json'], true) ?? []; ?>
            <article class="panel" style="margin-top:16px;">
                <span class="eyebrow">Result seal — <?= e(format_datetime($snapshot['created_at'])); ?></span>
                <h3 style="margin-top:4px;">Sealed vote counts</h3>
                <div class="list-shell list-shell--bare" style="margin-top:12px;">
                    <?php foreach ($snapshotData as $option => $data): ?>
                        <div class="list-row">
                            <div>
                                <strong><?= e($option); ?></strong>
                                <p style="font-size:0.82rem;"><?= e((string) ($data['votes'] ?? 0)); ?> vote<?= ($data['votes'] ?? 0) !== 1 ? 's' : ''; ?></p>
                            </div>
                            <strong style="font-size:1rem;"><?= e(number_format((float) ($data['percentage'] ?? 0), 2)); ?>%</strong>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div style="margin-top:16px; padding-top:12px; border-top:1px solid var(--rule-soft);">
                    <p style="font-size:0.78rem; color:var(--ink-3);">Seal (SHA-256)</p>
                    <p style="font-family:ui-monospace,monospace;font-size:0.74rem;word-break:break-all;margin-top:4px;"><?= e($snapshot['integrity_hash']); ?></p>
                    <p style="font-size:0.78rem;color:var(--ink-3);margin-top:8px;">If the vote counts above match what you see on the Results page, and this seal matches what was published at election close, the results have not been altered.</p>
                </div>
            </article>
        <?php else: ?>
            <div class="alert alert--info" style="margin-top:16px;">No result seal has been published for this election yet. Seals are generated when an election closes.</div>
        <?php endif; ?>
    </section>

<?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
