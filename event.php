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
$pageDescription = $event['description'] ?: '';
$activeNav       = 'events';
$viewer          = current_user();
$viewerRole      = $viewer !== null ? (string) ($viewer['role_slug'] ?? '') : '';

$effectiveStatus = effective_event_status($event);
$isScheduled     = $effectiveStatus === 'scheduled';
$isActive        = $effectiveStatus === 'active';
$isClosed        = in_array($effectiveStatus, ['closed', 'archived'], true);

// For voters, resolve their registration and voting state
$submission  = null;
$hasVoted    = false;
$hasToken    = false;

if ($viewerRole === 'voter' && $viewer !== null) {
    $submission = fetch_one(
        'SELECT * FROM voter_event_submissions
         WHERE event_id = :event_id AND user_id = :user_id
         ORDER BY id DESC LIMIT 1',
        ['event_id' => $event['id'], 'user_id' => $viewer['id']]
    );
    if ($submission !== null) {
        $usedToken = fetch_one(
            'SELECT id FROM voting_tokens WHERE submission_id = :sid AND status = "used" LIMIT 1',
            ['sid' => $submission['id']]
        );
        $hasVoted = $usedToken !== null;
        if (!$hasVoted) {
            $issuedToken = fetch_one(
                'SELECT id FROM voting_tokens WHERE submission_id = :sid AND status = "issued" LIMIT 1',
                ['sid' => $submission['id']]
            );
            $hasToken = $issuedToken !== null;
        }
    }
}

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
                <span class="badge <?= e(badge_class($effectiveStatus)); ?>"><?= e(format_status($effectiveStatus)); ?></span>
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
                    <dd><?php
                        $methodCount = count($methods);
                        if ($methodCount === 0) {
                            echo 'None';
                        } elseif ($event['verification_policy'] === 'any_one') {
                            echo 'Any 1 of ' . $methodCount . ' step' . ($methodCount !== 1 ? 's' : '');
                        } else {
                            echo 'All ' . $methodCount . ' step' . ($methodCount !== 1 ? 's' : '') . ' required';
                        }
                    ?></dd>
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

        <?php if (in_array($viewerRole, ['event_creator', 'super_admin'], true)): ?>
            <?php /* ── Admin / Creator ── */ ?>
            <a class="button button--primary" href="<?= e(base_url('/creator/event_form.php?event=' . $event['id'])); ?>">Manage event</a>

        <?php elseif ($viewerRole === 'co_admin'): ?>
            <?php /* ── Co-admin ── */ ?>
            <a class="button button--primary" href="<?= e(base_url('/creator/verifications.php?event=' . $event['id'])); ?>">Open tools</a>

        <?php elseif ($viewerRole === 'verifier'): ?>
            <?php /* ── Trusted verifier ── */ ?>
            <a class="button button--primary" href="<?= e(base_url('/creator/verifications.php?event=' . $event['id'])); ?>">Verifications</a>

        <?php elseif ($viewerRole === 'voter'): ?>
            <?php /* ── Voter: state machine ── */ ?>

            <?php if ($isClosed): ?>
                <?php /* Past event — nothing to do */ ?>
                <span class="badge badge-muted" style="align-self:center;">This election has ended</span>

            <?php elseif ($hasVoted): ?>
                <?php /* Already cast a ballot */ ?>
                <span class="badge badge-success" style="align-self:center;">You have voted</span>
                <a class="button button--ghost" href="<?= e(base_url('/voter/verify_vote.php?event=' . $event['id'])); ?>">Verify my receipt</a>

            <?php elseif ($submission !== null && $submission['status'] === 'approved' && $hasToken): ?>
                <?php /* Approved + token issued — ready to vote */ ?>
                <?php if ($isActive): ?>
                    <a class="button button--primary" href="<?= e(base_url('/voter/cast_vote.php?event=' . $event['id'])); ?>">Cast your vote</a>
                <?php else: ?>
                    <span class="badge badge-warning" style="align-self:center;">Voting opens <?= e(format_datetime($event['start_at'], 'M j, Y')); ?></span>
                <?php endif; ?>

            <?php elseif ($submission !== null && $submission['status'] === 'approved'): ?>
                <?php /* Approved but token not yet issued */ ?>
                <span class="badge badge-warning" style="align-self:center;">Approved — awaiting token</span>
                <a class="button button--ghost" href="<?= e(base_url('/voter/register_event.php?event=' . $event['id'])); ?>">View status</a>

            <?php elseif ($submission !== null && $submission['status'] === 'rejected'): ?>
                <?php /* Rejected — can re-register if event is open */ ?>
                <span class="badge badge-error" style="align-self:center;">Registration rejected</span>
                <?php if ($isActive || $isScheduled): ?>
                    <a class="button button--ghost" href="<?= e(base_url('/voter/register_event.php?event=' . $event['id'])); ?>">Re-apply</a>
                <?php endif; ?>

            <?php elseif ($submission !== null): ?>
                <?php /* Pending or under review */ ?>
                <span class="badge badge-warning" style="align-self:center;">Registration under review</span>
                <a class="button button--ghost" href="<?= e(base_url('/voter/register_event.php?event=' . $event['id'])); ?>">View status</a>

            <?php elseif (($isActive || $isScheduled) && $event['allow_self_registration']): ?>
                <?php /* Not registered yet — event open for registration */ ?>
                <a class="button button--primary" href="<?= e(base_url('/voter/register_event.php?event=' . $event['id'])); ?>">Register to vote</a>
                <?php if ($isScheduled): ?>
                    <span class="badge badge-muted" style="align-self:center;">Voting opens <?= e(format_datetime($event['start_at'], 'M j, Y')); ?></span>
                <?php endif; ?>

            <?php elseif ($isClosed || !$event['allow_self_registration']): ?>
                <span class="badge badge-muted" style="align-self:center;">Registration closed</span>
            <?php endif; ?>

        <?php else: ?>
            <?php /* ── Guest (not logged in) ── */ ?>
            <?php if ($isClosed): ?>
                <span class="badge badge-muted" style="align-self:center;">This election has ended</span>
            <?php elseif ($event['allow_self_registration']): ?>
                <a class="button button--primary" href="<?= e(base_url('/auth/register.php')); ?>">Create account to register</a>
                <a class="button button--ghost" href="<?= e(base_url('/auth/login.php')); ?>">Log in</a>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (can_view_public_results($event)): ?>
            <a class="button button--ghost" href="<?= e(base_url('/results.php?event=' . $event['id'])); ?>">Results</a>
        <?php endif; ?>
        <?php if (can_view_public_audit($event)): ?>
            <a class="button button--ghost" href="<?= e(base_url('/audit.php?event=' . $event['id'])); ?>">Audit trail</a>
        <?php endif; ?>
        <?php if (!$isClosed || $hasVoted): ?>
            <a class="button button--ghost" href="<?= e(base_url('/voter/verify_vote.php?event=' . $event['id'])); ?>">Verify receipt</a>
        <?php endif; ?>

    </div>
</section>

<?php if (!$isClosed && ($requiredFields || $methods)): ?>
<section class="section" data-reveal>
    <div class="grid-2">
        <?php if ($requiredFields): ?>
        <article class="panel">
            <span class="eyebrow">Required fields</span>
            <h2>What you need to provide</h2>
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
        </article>
        <?php endif; ?>

        <?php if ($methods): ?>
        <article class="panel">
            <span class="eyebrow">Verification steps</span>
            <h2>Eligibility pipeline</h2>
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
        </article>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

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
