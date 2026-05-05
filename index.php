<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle       = 'Secure Verifiable Online Voting';
$pageDescription = 'Verivote delivers secure, privacy-preserving, verifiable online voting for serious election workflows.';
$activeNav       = 'home';

$events       = fetch_public_events();
$featuredEvents = array_slice($events, 0, 4);

include __DIR__ . '/includes/header.php';
?>

<section class="hero">
    <div class="hero__inner">
        <div class="hero__content" data-reveal>
            <span class="eyebrow">Verified digital elections</span>
            <h1>Trusted process.<br>Provable results.</h1>
            <p class="lede">
                Verivote runs the full election lifecycle: voter registration,
                layered identity checks, single-use ballot credentials, and a
                cryptographically chained audit trail. Every step is logged. Nothing is trusted implicitly.
            </p>
            <div class="hero__actions">
                <a class="button button--primary" href="<?= e(base_url('/events.php')); ?>">Browse elections</a>
                <?php if (current_user()): ?>
                    <a class="button button--ghost" href="<?= e(base_url(dashboard_home_for_role((string) current_role_slug()))); ?>">My dashboard</a>
                <?php else: ?>
                    <a class="button button--ghost" href="<?= e(base_url('/auth/register.php')); ?>">Create an account</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="hero__visual" data-reveal>
            <div class="hero-rail">
                <span class="eyebrow">Integrity model</span>
                <div class="integrity-grid">
                    <div class="integrity-grid__cell">
                        <strong>Identity layer</strong>
                        <p>Registration data, required fields, and uploaded evidence are stored separately from ballot records.</p>
                    </div>
                    <div class="integrity-grid__cell">
                        <strong>Credential layer</strong>
                        <p>Each approved voter receives one cryptographically random single-use token per event.</p>
                    </div>
                    <div class="integrity-grid__cell">
                        <strong>Ballot layer</strong>
                        <p>Votes link to anonymous ballot keys, not voter IDs. Receipts let voters verify without exposing identity.</p>
                    </div>
                    <div class="integrity-grid__cell">
                        <strong>Audit layer</strong>
                        <p>Every privileged action is written to a hash-chained log inspectable by anyone after the event.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section" data-reveal>
    <div class="section-rule" style="padding-top:40px;">
        <div>
            <span class="eyebrow">Why it works</span>
            <h2>Built around the hard parts of running an election.</h2>
        </div>
    </div>
    <div class="feature-list">
        <div class="feature-list__item">
            <span class="feature-list__num">01</span>
            <h3>Layered verification</h3>
            <p>Each event defines its own required fields and approval methods, from document review to trusted in-person verifier sign-off. Combinations are supported.</p>
        </div>
        <div class="feature-list__item">
            <span class="feature-list__num">02</span>
            <h3>Single-use credentials</h3>
            <p>Every approved voter gets exactly one token per event. Tokens expire, can be revoked, and are validated inside a database transaction before any ballot write.</p>
        </div>
        <div class="feature-list__item">
            <span class="feature-list__num">03</span>
            <h3>Personal receipts</h3>
            <p>After voting, each voter receives a private receipt that proves their ballot was recorded correctly without being attributable on the public audit ledger.</p>
        </div>
    </div>
</section>

<?php if ($featuredEvents): ?>
<section class="section" data-reveal>
    <div class="section-rule" style="padding-top:32px;">
        <div>
            <span class="eyebrow">Active surface</span>
            <h2>Live elections</h2>
        </div>
        <a class="button button--ghost" href="<?= e(base_url('/events.php')); ?>">All elections</a>
    </div>
    <div class="event-rows">
        <?php foreach ($featuredEvents as $event): ?>
            <div class="event-row">
                <div>
                    <strong><?= e($event['title']); ?></strong>
                    <div class="event-row__meta">
                        <span class="badge <?= e(badge_class($event['status'])); ?>"><?= e(format_status($event['status'])); ?></span>
                        <span><?= e(format_datetime($event['start_at'], 'M j, Y')); ?> &ndash; <?= e(format_datetime($event['end_at'], 'M j, Y')); ?></span>
                        <span><?= e($event['creator_name']); ?></span>
                    </div>
                </div>
                <a class="button button--ghost" href="<?= e(base_url('/event.php?slug=' . urlencode($event['slug']))); ?>">Open</a>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<section class="section" data-reveal>
    <div class="cta-split">
        <div class="cta-split__half">
            <span class="eyebrow">Voter path</span>
            <h3>Register, verify, vote.</h3>
            <p>Create an account, complete the event's verification steps, receive a ballot token, cast your vote, and keep a private receipt you can verify later.</p>
            <div>
                <a class="button button--primary" href="<?= e(base_url('/auth/register.php')); ?>">Create a voter account</a>
            </div>
        </div>
        <div class="cta-split__half">
            <span class="eyebrow">Operator path</span>
            <h3>Create, manage, audit.</h3>
            <p>Event creators configure verification methods, manage verifiers and co-admins, issue tokens, and publish tamper-evident result snapshots.</p>
            <div>
                <a class="button button--ghost" href="<?= e(base_url('/auth/login.php')); ?>">Access your dashboard</a>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
