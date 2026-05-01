<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Results';
$pageDescription = 'Published Verivote election results and integrity snapshots.';
$activeNav = 'results';
$eventId = (int) ($_GET['event'] ?? 0);
$event = $eventId > 0 ? fetch_event_by_id($eventId) : null;

include __DIR__ . '/includes/header.php';
?>
<?php if (!$event): ?>
    <?php $resultEvents = array_filter(fetch_public_events(), static fn(array $item): bool => can_view_public_results($item)); ?>
    <section class="section">
        <div class="section-head">
            <div>
                <span class="eyebrow">Published Results</span>
                <h2>Events with visible tallies</h2>
            </div>
            <p>Only events configured for public visibility appear here.</p>
        </div>
        <div class="grid-cards">
            <?php foreach ($resultEvents as $resultEvent): ?>
                <article class="panel" data-reveal>
                    <div class="pill-row">
                        <span class="badge <?= e(badge_class($resultEvent['status'])); ?>"><?= e(format_status($resultEvent['status'])); ?></span>
                    </div>
                    <h3><?= e($resultEvent['title']); ?></h3>
                    <p><?= e($resultEvent['description']); ?></p>
                    <a class="button button--primary" href="<?= e(base_url('/results.php?event=' . $resultEvent['id'])); ?>">Open results</a>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
<?php elseif (!can_view_public_results($event)): ?>
    <section class="section">
        <div class="alert alert--warning">Results for this event are not public yet.</div>
    </section>
<?php else: ?>
    <?php
    $results = compute_event_results((int) $event['id']);
    $snapshot = latest_result_snapshot((int) $event['id']);
    ?>
    <section class="section">
        <article class="panel" data-reveal>
            <span class="eyebrow">Result Ledger</span>
            <h2><?= e($event['title']); ?></h2>
            <p><?= e($event['description']); ?></p>
            <div class="grid-3">
                <div class="card">
                    <strong><?= e((string) $results['total']); ?></strong>
                    <p>Total recorded ballots</p>
                </div>
                <div class="card">
                    <strong><?= e((string) count($results['rows'])); ?></strong>
                    <p>Options on the ballot</p>
                </div>
                <div class="card">
                    <strong><?= e($snapshot ? substr($snapshot['integrity_hash'], 0, 12) . '...' : 'Live'); ?></strong>
                    <p>Latest integrity snapshot</p>
                </div>
            </div>
        </article>
    </section>

    <section class="section">
        <article class="results-strip" data-reveal>
            <?php foreach ($results['rows'] as $row): ?>
                <div class="result-bar">
                    <div class="list-row">
                        <div>
                            <strong><?= e($row['option_label']); ?></strong>
                            <p><?= e((string) $row['total_votes']); ?> votes</p>
                        </div>
                        <strong><?= e((string) $row['percentage']); ?>%</strong>
                    </div>
                    <div class="result-bar__track">
                        <div class="result-bar__fill" style="width: <?= e((string) $row['percentage']); ?>%"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </article>
    </section>

    <?php if ($snapshot): ?>
        <section class="section">
            <article class="ledger" data-reveal>
                <span class="eyebrow">Snapshot</span>
                <h2>Latest published result checkpoint</h2>
                <pre><?= e($snapshot['snapshot_json']); ?></pre>
                <div class="inline-actions">
                    <span class="badge badge-muted">Hash <?= e($snapshot['integrity_hash']); ?></span>
                </div>
            </article>
        </section>
    <?php endif; ?>
<?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
