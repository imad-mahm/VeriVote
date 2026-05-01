<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$slug = trim((string) ($_GET['slug'] ?? ''));
$event = $slug !== '' ? fetch_event_by_slug($slug) : fetch_event_by_id((int) ($_GET['event'] ?? 0));

if (!$event) {
    http_response_code(404);
    flash('error', 'Event not found.');
    redirect('/events.php');
}

$pageTitle = $event['title'];
$pageDescription = $event['description'];
$activeNav = 'events';
$candidates = fetch_all(
    'SELECT * FROM candidates_or_options
     WHERE event_id = :event_id AND is_active = 1
     ORDER BY display_order ASC, id ASC',
    ['event_id' => $event['id']]
);
$requiredFields = fetch_event_required_fields((int) $event['id']);
$methods = fetch_event_verification_methods((int) $event['id'], true);

include __DIR__ . '/includes/header.php';
?>
<section class="section">
    <article class="panel" data-reveal>
        <div class="pill-row">
            <span class="badge <?= e(badge_class($event['status'])); ?>"><?= e(format_status($event['status'])); ?></span>
            <span class="badge badge-muted"><?= e(format_status($event['ballot_type'])); ?></span>
            <span class="badge badge-muted"><?= e(format_status($event['result_visibility'])); ?></span>
        </div>
        <h1><?= e($event['title']); ?></h1>
        <p><?= e($event['description']); ?></p>
        <?php if (!empty($event['event_notice'])): ?>
            <div class="alert alert--info"><?= e($event['event_notice']); ?></div>
        <?php endif; ?>
        <div class="grid-3">
            <div class="card">
                <strong>Window</strong>
                <p><?= e(format_datetime($event['start_at'])); ?> to <?= e(format_datetime($event['end_at'])); ?></p>
            </div>
            <div class="card">
                <strong>Verification policy</strong>
                <p><?= e(format_status($event['verification_policy'])); ?></p>
            </div>
            <div class="card">
                <strong>Creator</strong>
                <p><?= e($event['creator_name']); ?></p>
            </div>
        </div>
        <div class="inline-actions">
            <a class="button button--primary" href="<?= e(base_url('/voter/register_event.php?event=' . $event['id'])); ?>">Register for this event</a>
            <a class="button button--ghost" href="<?= e(base_url('/voter/cast_vote.php?event=' . $event['id'])); ?>">Cast vote</a>
            <a class="button button--ghost" href="<?= e(base_url('/voter/verify_vote.php?event=' . $event['id'])); ?>">Verify receipt</a>
        </div>
    </article>
</section>

<section class="section" data-reveal>
    <div class="grid-2">
        <article class="panel">
            <span class="eyebrow">Required Fields</span>
            <h2>Submission data requested from voters</h2>
            <div class="list-shell">
                <?php foreach ($requiredFields as $field): ?>
                    <div class="list-row">
                        <div>
                            <strong><?= e($field['field_label']); ?></strong>
                            <p><?= e($field['help_text'] ?: format_status($field['field_type'])); ?></p>
                        </div>
                        <span class="badge <?= $field['is_required'] ? 'badge-warning' : 'badge-muted'; ?>">
                            <?= $field['is_required'] ? 'Required' : 'Optional'; ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </article>
        <article class="panel">
            <span class="eyebrow">Verification Steps</span>
            <h2>Eligibility approval pipeline</h2>
            <div class="list-shell">
                <?php foreach ($methods as $method): ?>
                    <div class="list-row">
                        <div>
                            <strong><?= e($method['label']); ?></strong>
                            <p><?= e($method['description'] ?: 'Configured for this event.'); ?></p>
                        </div>
                        <span class="badge <?= $method['is_required'] ? 'badge-warning' : 'badge-muted'; ?>">
                            <?= $method['is_required'] ? 'Required' : 'Optional'; ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </article>
    </div>
</section>

<section class="section" data-reveal>
    <article class="panel">
        <span class="eyebrow">Ballot</span>
        <h2>Options on this ballot</h2>
        <div class="grid-cards">
            <?php foreach ($candidates as $candidate): ?>
                <article class="card">
                    <h3><?= e($candidate['option_label']); ?></h3>
                    <p><?= e($candidate['option_description']); ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </article>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
