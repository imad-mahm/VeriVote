<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$slug  = trim((string) ($_GET['slug'] ?? ''));
$event = $slug !== '' ? fetch_event_by_slug($slug) : fetch_event_by_id((int) ($_GET['event'] ?? 0));

if (!$event) {
    http_response_code(404);
    flash('error', 'Event not found.');
    redirect('/events.php');
}

$pageTitle       = $event['title'];
$pageDescription = $event['description'];
$activeNav       = 'events';
$candidates      = fetch_all(
    'SELECT * FROM candidates_or_options
     WHERE event_id = :event_id AND is_active = 1
     ORDER BY display_order ASC, id ASC',
    ['event_id' => $event['id']]
);
$requiredFields  = fetch_event_required_fields((int) $event['id']);
$methods         = fetch_event_verification_methods((int) $event['id'], true);

include __DIR__ . '/includes/header.php';
?>
<section class="section section--top" data-reveal>
    <div class="page-intro">
        <div>
            <div class="pill-row" style="margin-bottom:14px;">
                <span class="badge <?= e(badge_class($event['status'])); ?>"><?= e(format_status($event['status'])); ?></span>
                <span class="badge badge-muted"><?= e(format_status($event['ballot_type'])); ?></span>
                <span class="badge badge-muted"><?= e(format_status($event['result_visibility'])); ?></span>
            </div>
            <h1><?= e($event['title']); ?></h1>
            <p style="margin-top:12px;"><?= e($event['description']); ?></p>
        </div>
        <div class="def-list">
            <dl>
                <div class="def-row">
                    <dt>Window</dt>
                    <dd><?= e(format_datetime($event['start_at'], 'M j, Y H:i')); ?> &ndash; <?= e(format_datetime($event['end_at'], 'M j, Y H:i')); ?></dd>
                </div>
                <div class="def-row">
                    <dt>Verification</dt>
                    <dd><?= e(format_status($event['verification_policy'])); ?></dd>
                </div>
                <div class="def-row">
                    <dt>Created by</dt>
                    <dd><?= e($event['creator_name']); ?></dd>
                </div>
            </dl>
        </div>
    </div>

    <?php if (!empty($event['event_notice'])): ?>
        <div class="alert alert--info"><?= e($event['event_notice']); ?></div>
    <?php endif; ?>

    <div class="inline-actions" style="padding-top:8px;">
        <a class="button button--primary" href="<?= e(base_url('/voter/register_event.php?event=' . $event['id'])); ?>">Register for this event</a>
        <a class="button button--ghost"   href="<?= e(base_url('/voter/cast_vote.php?event=' . $event['id'])); ?>">Cast vote</a>
        <a class="button button--ghost"   href="<?= e(base_url('/voter/verify_vote.php?event=' . $event['id'])); ?>">Verify receipt</a>
    </div>
</section>

<section class="section" data-reveal>
    <div class="grid-2">
        <article class="panel">
            <span class="eyebrow">Required fields</span>
            <h2>Voter submission data</h2>
            <?php if (!$requiredFields): ?>
                <p>No required fields configured for this event.</p>
            <?php else: ?>
                <div class="list-shell list-shell--bare">
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
            <?php endif; ?>
        </article>

        <article class="panel">
            <span class="eyebrow">Verification steps</span>
            <h2>Eligibility pipeline</h2>
            <?php if (!$methods): ?>
                <p>No verification methods configured for this event.</p>
            <?php else: ?>
                <div class="list-shell list-shell--bare">
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
            <?php endif; ?>
        </article>
    </div>
</section>

<?php if ($candidates): ?>
<section class="section" data-reveal>
    <div class="section-rule">
        <div>
            <span class="eyebrow">Ballot options</span>
            <h2>Candidates on this ballot</h2>
        </div>
    </div>
    <div class="event-rows">
        <?php foreach ($candidates as $i => $candidate): ?>
            <div class="event-row event-row--full">
                <div>
                    <strong><?= e($candidate['option_label']); ?></strong>
                    <?php if ($candidate['option_description']): ?>
                        <p style="margin-top:4px; font-size:0.88rem;"><?= e($candidate['option_description']); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
