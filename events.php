<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle       = 'Elections';
$pageDescription = 'Browse scheduled, active, and completed Verivote elections. Register, vote, inspect results, and verify the audit trail.';
$activeNav       = 'events';
$events          = fetch_public_events();
$viewer          = current_user();
$viewerRole      = $viewer !== null ? (string) ($viewer['role_slug'] ?? '') : '';

include __DIR__ . '/includes/header.php';
?>
<section class="section section--top">
    <div class="page-intro">
        <div>
            <span class="eyebrow">Election directory</span>
            <h1>Public elections</h1>
            <p style="margin-top:12px;">Browse scheduled, active, and completed elections. Actions shown below depend on your account role and each election's configuration.</p>
        </div>
        <div style="display:flex; align-items:flex-end; justify-content:flex-end;">
            <span class="badge badge-muted"><?= e((string) count($events)); ?> election<?= count($events) !== 1 ? 's' : ''; ?></span>
        </div>
    </div>

    <?php if (!$events): ?>
        <div class="alert alert--info">No public elections are available right now.</div>
    <?php else: ?>
        <div class="event-rows">
            <?php foreach ($events as $event): ?>
                <div class="event-row" data-reveal>
                    <div>
                        <strong style="font-size:1rem;"><?= e($event['title']); ?></strong>
                        <div class="event-row__meta">
                            <span class="badge <?= e(badge_class($event['status'])); ?>"><?= e(format_status($event['status'])); ?></span>
                            <span><?= e(format_datetime($event['start_at'], 'M j, Y')); ?> &ndash; <?= e(format_datetime($event['end_at'], 'M j, Y')); ?></span>
                            <span><?= e($event['creator_name']); ?></span>
                        </div>
                        <?php if ($event['description']): ?>
                            <p style="margin-top:6px; font-size:0.88rem; max-width:70ch;"><?= e($event['description']); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="inline-actions">
                        <a class="button button--primary" href="<?= e(base_url('/event.php?slug=' . urlencode($event['slug']))); ?>">Open</a>

                        <?php if ($viewerRole === 'voter'): ?>
                            <a class="button button--ghost" href="<?= e(base_url('/voter/register_event.php?event=' . $event['id'])); ?>">Register</a>
                            <?php if ($event['status'] === 'active'): ?>
                                <a class="button button--ghost" href="<?= e(base_url('/voter/cast_vote.php?event=' . $event['id'])); ?>">Vote</a>
                            <?php endif; ?>
                        <?php elseif (in_array($viewerRole, ['event_creator', 'super_admin'], true)): ?>
                            <a class="button button--ghost" href="<?= e(base_url('/creator/event_form.php?event=' . $event['id'])); ?>">Manage</a>
                        <?php elseif ($viewerRole === 'co_admin'): ?>
                            <a class="button button--ghost" href="<?= e(base_url('/creator/verifications.php?event=' . $event['id'])); ?>">Tools</a>
                        <?php endif; ?>

                        <?php if (can_view_public_results($event)): ?>
                            <a class="button button--ghost" href="<?= e(base_url('/results.php?event=' . $event['id'])); ?>">Results</a>
                        <?php endif; ?>
                        <?php if (can_view_public_audit($event)): ?>
                            <a class="button button--ghost" href="<?= e(base_url('/audit.php?event=' . $event['id'])); ?>">Audit</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
