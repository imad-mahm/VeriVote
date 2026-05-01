<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$eventId = (int) ($_GET['event'] ?? $_POST['event_id'] ?? 0);
$event = $eventId > 0 ? fetch_event_by_id($eventId) : null;
$validatedToken = null;
$receiptResult = null;

if (is_post_request()) {
    verify_csrf_or_fail();
    $action = (string) ($_POST['action'] ?? 'preview_token');
    $eventId = (int) ($_POST['event_id'] ?? $eventId);
    $event = $eventId > 0 ? fetch_event_by_id($eventId) : null;

    if (!$event) {
        flash('error', 'Select a valid event to continue.');
        redirect('/voter/cast_vote.php');
    }

    if ($action === 'preview_token') {
        $limit = consume_rate_limit(
            'vote-token',
            $eventId . '|' . client_ip(),
            (int) app_config('security.token_attempts'),
            (int) app_config('security.token_window_seconds')
        );

        if (!$limit['allowed']) {
            flash('error', 'Too many token attempts. Please wait ' . $limit['retry_after'] . ' seconds.');
            redirect('/voter/cast_vote.php?event=' . $eventId);
        }

        $tokenInput = trim((string) ($_POST['token'] ?? ''));
        $tokenRow = validate_voting_token($eventId, $tokenInput);

        if (
            !$tokenRow
            || $tokenRow['status'] !== 'issued'
            || $tokenRow['submission_status'] !== 'approved'
            || $tokenRow['revoked_at'] !== null
            || $tokenRow['used_at'] !== null
            || new DateTimeImmutable($tokenRow['expires_at']) < new DateTimeImmutable('now')
            || !event_is_active($event)
        ) {
            flash('error', 'Token is invalid, revoked, used, expired, or not yet approved.');
            redirect('/voter/cast_vote.php?event=' . $eventId);
        }

        $validatedToken = $tokenInput;
        clear_rate_limit('vote-token', $eventId . '|' . client_ip());
    }

    if ($action === 'cast_ballot') {
        $tokenInput = trim((string) ($_POST['token'] ?? ''));
        $candidateId = (int) ($_POST['candidate_id'] ?? 0);

        try {
            $receiptResult = cast_ballot($eventId, $tokenInput, $candidateId);
            write_audit_log(
                'ballot_cast',
                'events',
                (string) $eventId,
                'Anonymous ballot cast successfully.',
                $eventId,
                ['public_receipt_hash' => $receiptResult['receipt_public_hash']]
            );
        } catch (Throwable $exception) {
            flash('error', $exception->getMessage());
            redirect('/voter/cast_vote.php?event=' . $eventId);
        }
    }
}

$pageTitle = 'Cast Vote';
$pageHeading = 'Cast Vote';
$pageDescription = 'Validate your one-time Verivote token and cast a single anonymous ballot.';
$isDashboard = true;
$sidebarContext = current_role_slug() ?? 'voter';
$activeSidebar = 'voter-cast';
$activeEvents = array_filter(fetch_public_events(), static fn(array $item): bool => $item['status'] === 'active');

include dirname(__DIR__) . '/includes/header.php';
?>
<?php if (!$event): ?>
    <section class="panel">
        <span class="eyebrow">Active Elections</span>
        <h2>Select an event before entering your token</h2>
        <div class="grid-cards">
            <?php foreach ($activeEvents as $active): ?>
                <article class="card">
                    <h3><?= e($active['title']); ?></h3>
                    <p><?= e($active['description']); ?></p>
                    <a class="button button--primary" href="<?= e(base_url('/voter/cast_vote.php?event=' . $active['id'])); ?>">Open ballot</a>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
<?php elseif ($receiptResult): ?>
    <section class="panel">
        <span class="eyebrow">Ballot Recorded</span>
        <h2>Your vote has been submitted</h2>
        <div class="alert alert--success">
            Your selection for <strong><?= e($receiptResult['option']); ?></strong> was recorded successfully.
        </div>
        <div class="card">
            <strong>Private receipt</strong>
            <p>Keep this receipt secure. It is the only value you can use later to personally verify your vote.</p>
            <div class="inline-actions">
                <span class="badge badge-success"><?= e($receiptResult['receipt']); ?></span>
                <button class="button button--ghost" type="button" data-copy="<?= e($receiptResult['receipt']); ?>">Copy</button>
            </div>
        </div>
        <div class="card">
            <strong>Public audit reference</strong>
            <p><?= e($receiptResult['receipt_public_hash']); ?></p>
        </div>
    </section>
<?php elseif ($validatedToken): ?>
    <?php
    $candidates = fetch_all(
        'SELECT * FROM candidates_or_options
         WHERE event_id = :event_id AND is_active = 1
         ORDER BY display_order ASC, id ASC',
        ['event_id' => $eventId]
    );
    ?>
    <section class="panel">
        <span class="eyebrow">Ballot Selection</span>
        <h2><?= e($event['title']); ?></h2>
        <p>Choose exactly one option. This ballot can only be submitted once.</p>
        <form method="post" class="form-grid form-grid--single">
            <?= csrf_field(); ?>
            <input type="hidden" name="action" value="cast_ballot">
            <input type="hidden" name="event_id" value="<?= e((string) $eventId); ?>">
            <input type="hidden" name="token" value="<?= e($validatedToken); ?>">
            <div class="vote-options">
                <?php foreach ($candidates as $candidate): ?>
                    <label class="vote-option">
                        <input type="radio" name="candidate_id" value="<?= e((string) $candidate['id']); ?>" required>
                        <span>
                            <strong><?= e($candidate['option_label']); ?></strong>
                            <p><?= e($candidate['option_description']); ?></p>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
            <button class="button button--primary" type="submit">Submit ballot</button>
        </form>
    </section>
<?php else: ?>
    <section class="panel">
        <span class="eyebrow">Token Validation</span>
        <h2><?= e($event['title']); ?></h2>
        <p>Enter your one-time voting token. The system checks the event window, approval state, expiry, and whether the token has already been used.</p>
        <form method="post" class="form-grid form-grid--single">
            <?= csrf_field(); ?>
            <input type="hidden" name="action" value="preview_token">
            <input type="hidden" name="event_id" value="<?= e((string) $eventId); ?>">
            <div class="field">
                <label for="token">Voting token</label>
                <input id="token" type="text" name="token" placeholder="VT-..." required>
            </div>
            <button class="button button--primary" type="submit">Validate token</button>
        </form>
    </section>
<?php endif; ?>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
