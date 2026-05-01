<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Events';
$pageDescription = 'Public list of scheduled, active, and published Verivote events.';
$activeNav = 'events';
$events = fetch_public_events();

include __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="section-head">
        <div>
            <span class="eyebrow">Event Directory</span>
            <h2>Available voting events</h2>
        </div>
        <p>Review each event’s voting window, verification policy, and public visibility before registering or casting a vote.</p>
    </div>

    <div class="grid-cards">
        <?php foreach ($events as $event): ?>
            <article class="panel" data-reveal>
                <div class="pill-row">
                    <span class="badge <?= e(badge_class($event['status'])); ?>"><?= e(format_status($event['status'])); ?></span>
                    <span class="badge badge-muted"><?= e(format_status($event['result_visibility'])); ?></span>
                </div>
                <h3><?= e($event['title']); ?></h3>
                <p><?= e($event['description']); ?></p>
                <div class="list-shell">
                    <div class="list-row">
                        <strong>Starts</strong>
                        <span><?= e(format_datetime($event['start_at'])); ?></span>
                    </div>
                    <div class="list-row">
                        <strong>Ends</strong>
                        <span><?= e(format_datetime($event['end_at'])); ?></span>
                    </div>
                    <div class="list-row">
                        <strong>Creator</strong>
                        <span><?= e($event['creator_name']); ?></span>
                    </div>
                </div>
                <div class="inline-actions">
                    <a class="button button--primary" href="<?= e(base_url('/event.php?slug=' . urlencode($event['slug']))); ?>">View details</a>
                    <?php if (can_view_public_results($event)): ?>
                        <a class="button button--ghost" href="<?= e(base_url('/results.php?event=' . $event['id'])); ?>">Results</a>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
