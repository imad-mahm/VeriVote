<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$eventId = (int) ($_GET['event'] ?? 0);

if ($eventId === 0) {
    redirect('/events.php');
}

$pageTitle       = 'Results';
$pageDescription = 'Published Verivote election results and integrity snapshots.';
$activeNav       = 'events';
$event           = fetch_event_by_id($eventId);

include __DIR__ . '/includes/header.php';
?>
<?php if (!$event || !can_view_public_results($event)): ?>
    <section class="section section--top">
        <div class="alert alert--warning">Results for this election are not public yet. <a href="<?= e(base_url('/events.php')); ?>" style="color:inherit;text-decoration:underline;">Back to elections</a></div>
    </section>

<?php else: ?>
    <?php
    $results  = compute_event_results((int) $event['id']);
    $snapshot = latest_result_snapshot((int) $event['id']);
    ?>

    <section class="section section--top" data-reveal>
        <div class="breadcrumb" style="margin-bottom:16px;">
            <a href="<?= e(base_url('/events.php')); ?>">Elections</a>
            <span>&rsaquo;</span>
            <a href="<?= e(base_url('/event.php?event=' . $event['id'])); ?>"><?= e($event['title']); ?></a>
            <span>&rsaquo;</span>
            <span>Results</span>
        </div>
        <div class="page-intro">
            <div>
                <span class="eyebrow">Result ledger</span>
                <h1><?= e($event['title']); ?></h1>
                <?php if ($event['description']): ?>
                    <p style="margin-top:12px;"><?= e($event['description']); ?></p>
                <?php endif; ?>
            </div>
            <div class="def-list">
                <dl>
                    <div class="def-row">
                        <dt>Total ballots</dt>
                        <dd><strong><?= e((string) $results['total']); ?></strong></dd>
                    </div>
                    <div class="def-row">
                        <dt>Options</dt>
                        <dd><?= e((string) count($results['rows'])); ?></dd>
                    </div>
                    <?php if ($snapshot): ?>
                    <div class="def-row">
                        <dt>Snapshot hash</dt>
                        <dd style="font-family:ui-monospace,monospace;font-size:0.8rem;word-break:break-all;"><?= e(substr($snapshot['integrity_hash'], 0, 16) . '...'); ?></dd>
                    </div>
                    <?php endif; ?>
                </dl>
            </div>
        </div>
    </section>

    <section class="section" data-reveal>
        <article class="results-strip">
            <?php foreach ($results['rows'] as $row): ?>
                <div class="result-bar">
                    <div class="list-row" style="padding:10px 0;">
                        <div>
                            <strong><?= e($row['option_label']); ?></strong>
                            <p style="font-size:0.82rem;"><?= e((string) $row['total_votes']); ?> vote<?= $row['total_votes'] != 1 ? 's' : ''; ?></p>
                        </div>
                        <strong style="font-size:1.1rem; font-family:'Playfair Display',serif;"><?= e((string) $row['percentage']); ?>%</strong>
                    </div>
                    <div class="result-bar__track">
                        <div class="result-bar__fill" style="width: <?= e((string) $row['percentage']); ?>%"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </article>
    </section>

    <section class="section" data-reveal>
        <div class="inline-actions">
            <a class="button button--ghost" href="<?= e(base_url('/event.php?event=' . $event['id'])); ?>">&larr; Back to election</a>
            <?php if (can_view_public_audit($event)): ?>
                <a class="button button--ghost" href="<?= e(base_url('/audit.php?event=' . $event['id'])); ?>">View audit ledger</a>
            <?php endif; ?>
            <a class="button button--ghost" href="<?= e(base_url('/voter/verify_vote.php?event=' . $event['id'])); ?>">Verify a receipt</a>
        </div>
    </section>

    <?php if ($snapshot): ?>
    <?php $snapshotData = json_decode((string) $snapshot['snapshot_json'], true) ?? []; ?>
    <section class="section" data-reveal>
        <article class="panel">
            <span class="eyebrow">Result seal — <?= e(format_datetime($snapshot['created_at'])); ?></span>
            <h2 style="margin-top:4px;">Verified snapshot</h2>
            <p style="margin-top:6px; font-size:0.88rem; color:var(--ink-2);">These counts were sealed at election close. The seal below proves this table has not been altered since it was published.</p>
            <div class="list-shell list-shell--bare" style="margin-top:14px;">
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
                <p style="font-size:0.75rem; color:var(--ink-3); text-transform:uppercase; letter-spacing:.05em;">SHA-256 seal</p>
                <p style="font-family:ui-monospace,monospace;font-size:0.74rem;word-break:break-all;margin-top:4px;"><?= e($snapshot['integrity_hash']); ?></p>
            </div>
        </article>
    </section>
    <?php endif; ?>

<?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
