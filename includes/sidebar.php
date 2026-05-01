<?php
declare(strict_types=1);

$role = $sidebarContext ?? (current_role_slug() ?? 'voter');
$activeEventTool = $activeEventTool ?? '';
$links = match ($role) {
    'super_admin' => [
        ['label' => 'Overview', 'path' => '/admin/dashboard.php', 'slug' => 'admin-dashboard'],
        ['label' => 'Privileged Users', 'path' => '/admin/users.php', 'slug' => 'admin-users'],
        ['label' => 'Audit Trail', 'path' => '/admin/audits.php', 'slug' => 'admin-audits'],
        ['label' => 'Test Settings', 'path' => '/admin/settings.php', 'slug' => 'admin-settings'],
    ],
    'event_creator' => [
        ['label' => 'Overview', 'path' => '/creator/dashboard.php', 'slug' => 'creator-dashboard'],
        ['label' => 'New / Edit Event', 'path' => '/creator/event_form.php', 'slug' => 'creator-event'],
    ],
    'co_admin' => [
        ['label' => 'Overview', 'path' => '/coadmin/dashboard.php', 'slug' => 'coadmin-dashboard'],
    ],
    'verifier' => [
        ['label' => 'Verifier Queue', 'path' => '/verifier/dashboard.php', 'slug' => 'verifier-dashboard'],
    ],
    default => [
        ['label' => 'Overview', 'path' => '/voter/dashboard.php', 'slug' => 'voter-dashboard'],
        ['label' => 'Cast Vote', 'path' => '/voter/cast_vote.php', 'slug' => 'voter-cast'],
        ['label' => 'Verify Receipt', 'path' => '/voter/verify_vote.php', 'slug' => 'voter-verify'],
    ],
};
?>
<aside class="sidebar">
    <div class="sidebar__header">
        <span class="eyebrow">Secure Workspace</span>
        <strong><?= e(format_status(str_replace('_', ' ', $role))); ?></strong>
    </div>

    <nav class="sidebar__nav">
        <?php foreach ($links as $link): ?>
            <a class="<?= ($activeSidebar ?? '') === $link['slug'] ? 'is-active' : ''; ?>" href="<?= e(base_url($link['path'])); ?>">
                <?= e($link['label']); ?>
            </a>
        <?php endforeach; ?>

        <?php if (!empty($eventContextId)): ?>
            <div class="sidebar__section">
                <span class="eyebrow">Event Tools</span>
                <a class="<?= $activeEventTool === 'candidates' ? 'is-active' : ''; ?>" href="<?= e(base_url('/creator/candidates.php?event=' . $eventContextId)); ?>">Candidates</a>
                <a class="<?= $activeEventTool === 'required-fields' ? 'is-active' : ''; ?>" href="<?= e(base_url('/creator/required_fields.php?event=' . $eventContextId)); ?>">Required Fields</a>
                <a class="<?= $activeEventTool === 'verification-methods' ? 'is-active' : ''; ?>" href="<?= e(base_url('/creator/verification_methods.php?event=' . $eventContextId)); ?>">Verification Methods</a>
                <a class="<?= $activeEventTool === 'verifications' ? 'is-active' : ''; ?>" href="<?= e(base_url('/creator/verifications.php?event=' . $eventContextId)); ?>">Verifications</a>
                <a class="<?= $activeEventTool === 'tokens' ? 'is-active' : ''; ?>" href="<?= e(base_url('/creator/tokens.php?event=' . $eventContextId)); ?>">Token Issuance</a>
                <a class="<?= $activeEventTool === 'results' ? 'is-active' : ''; ?>" href="<?= e(base_url('/creator/results.php?event=' . $eventContextId)); ?>">Results</a>
                <a class="<?= $activeEventTool === 'audit-log' ? 'is-active' : ''; ?>" href="<?= e(base_url('/creator/audit_logs.php?event=' . $eventContextId)); ?>">Audit Log</a>
            </div>
        <?php endif; ?>
    </nav>
    <div class="sidebar__footer">
        <a class="button button--ghost button--block" href="<?= e(base_url('/auth/logout.php')); ?>">Sign out</a>
    </div>
</aside>
