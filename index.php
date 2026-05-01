<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Secure Verifiable Online Voting';
$pageDescription = 'Verivote delivers secure, privacy-preserving, verifiable online voting for serious election workflows.';
$activeNav = 'home';

$events = fetch_public_events();
$featuredEvents = array_slice($events, 0, 3);
$counts = [
    'events' => (int) (fetch_one('SELECT COUNT(*) AS aggregate FROM events') ['aggregate'] ?? 0),
    'verifications' => (int) (fetch_one('SELECT COUNT(*) AS aggregate FROM voter_verifications WHERE status = "approved"') ['aggregate'] ?? 0),
    'ballots' => (int) (fetch_one('SELECT COUNT(*) AS aggregate FROM ballots') ['aggregate'] ?? 0),
];

include __DIR__ . '/includes/header.php';
?>
<section class="hero">
    <div class="hero__inner">
        <div class="hero__content" data-reveal>
            <span class="eyebrow">Verivote</span>
            <h1>Security first. Verification built in. Privacy preserved.</h1>
            <p class="lede">
                Verivote is a serious MVP for online voting where eligibility checks, trusted verifier workflows,
                one-time ballot credentials, and public audit artifacts are all first-class product features.
            </p>
            <div class="hero__actions">
                <a class="button button--primary" href="<?= e(base_url('/events.php')); ?>">Review live events</a>
                <a class="button button--ghost" href="<?= e(base_url('/audit.php')); ?>">Inspect audit proofs</a>
            </div>
            <div class="hero__metrics">
                <div class="metric">
                    <strong><?= e((string) $counts['events']); ?></strong>
                    <span>Tracked events</span>
                </div>
                <div class="metric">
                    <strong><?= e((string) $counts['verifications']); ?></strong>
                    <span>Approved verification steps</span>
                </div>
                <div class="metric">
                    <strong><?= e((string) $counts['ballots']); ?></strong>
                    <span>Recorded anonymous ballots</span>
                </div>
            </div>
        </div>

        <div class="hero__visual" data-reveal>
            <div class="hero-rail">
                <span class="eyebrow">Integrity Model</span>
                <div class="status-row">
                    <div class="card">
                        <strong>Identity</strong>
                        <p>Registration data, required fields, and uploaded evidence remain separate from ballot storage.</p>
                    </div>
                    <div class="card">
                        <strong>Credential</strong>
                        <p>Approved voters receive a random single-use token stored hashed in the database.</p>
                    </div>
                    <div class="card">
                        <strong>Ballot</strong>
                        <p>Votes are linked to anonymous ballot keys and personal receipts, not direct voter IDs.</p>
                    </div>
                </div>
                <div class="card">
                    <strong>Anti-impersonation controls</strong>
                    <p>Email ownership checks, document review, manual admin approval, and trusted in-person verifier confirmation can all be combined per event.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section" data-reveal>
    <div class="section-head">
        <div>
            <span class="eyebrow">Core Value</span>
            <h2>Designed around the hard parts of voting.</h2>
        </div>
        <p>Verivote focuses on duplicate prevention, credential control, vote integrity, and verifiable transparency without making ballots publicly attributable.</p>
    </div>
    <div class="grid-cards">
        <article class="card">
            <h3>Layered verification</h3>
            <p>Each event defines its own required fields and approval methods, including trusted verifier approval and manual review.</p>
        </article>
        <article class="card">
            <h3>Single-use credentials</h3>
            <p>Every approved voter gets one cryptographically random token per event with usage tracking, expiry, and revocation support.</p>
        </article>
        <article class="card">
            <h3>Personal receipts</h3>
            <p>After voting, the voter receives a private receipt that proves their vote was recorded without exposing identity on the public ledger.</p>
        </article>
    </div>
</section>

<section class="section" data-reveal>
    <div class="section-head">
        <div>
            <span class="eyebrow">Active Surface</span>
            <h2>Public election access</h2>
        </div>
        <a class="button button--ghost" href="<?= e(base_url('/events.php')); ?>">View all events</a>
    </div>
    <div class="grid-cards">
        <?php foreach ($featuredEvents as $event): ?>
            <article class="panel">
                <div class="pill-row">
                    <span class="badge <?= e(badge_class($event['status'])); ?>"><?= e(format_status($event['status'])); ?></span>
                    <span class="badge badge-muted"><?= e(format_status($event['ballot_type'])); ?></span>
                </div>
                <h3><?= e($event['title']); ?></h3>
                <p><?= e($event['description']); ?></p>
                <div class="list-row">
                    <div>
                        <strong>Voting window</strong>
                        <p><?= e(format_datetime($event['start_at'])); ?> to <?= e(format_datetime($event['end_at'])); ?></p>
                    </div>
                    <a class="button button--primary" href="<?= e(base_url('/event.php?slug=' . urlencode($event['slug']))); ?>">Open event</a>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="section" data-reveal>
    <div class="grid-2">
        <article class="panel">
            <span class="eyebrow">Threat Coverage</span>
            <h2>Practical controls for real risks</h2>
            <div class="list-shell">
                <div class="list-row"><strong>Duplicate registrations</strong><p>One submission per voter per event, identity review, and audit-tracked approval states.</p></div>
                <div class="list-row"><strong>Impersonation</strong><p>Trusted verifier approval, document checks, and phone-first ownership confirmation with SMS OTP.</p></div>
                <div class="list-row"><strong>Replay and double voting</strong><p>Single-use tokens are revalidated inside a transaction before any ballot write happens.</p></div>
                <div class="list-row"><strong>Insider tampering</strong><p>Privileged actions are written to a hash-chained audit trail for later inspection.</p></div>
            </div>
        </article>
        <article class="panel">
            <span class="eyebrow">Next Actions</span>
            <h2>Start as a voter or operator</h2>
            <div class="list-shell">
                <div class="list-row">
                    <div>
                        <strong>Voter path</strong>
                        <p>Create an account, complete event verification, receive a ballot token, cast a vote, and keep your receipt.</p>
                    </div>
                    <a class="button button--primary" href="<?= e(base_url('/auth/register.php')); ?>">Register</a>
                </div>
                <div class="list-row">
                    <div>
                        <strong>Operator path</strong>
                        <p>Event creators, co-admins, and verifiers can access role dashboards with seed accounts after setup.</p>
                    </div>
                    <a class="button button--ghost" href="<?= e(base_url('/auth/login.php')); ?>">Open dashboard</a>
                </div>
            </div>
        </article>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
