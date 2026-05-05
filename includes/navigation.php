<?php
declare(strict_types=1);

$viewer = current_user();
?>
<header class="topbar">
    <div class="topbar__inner">
        <a class="brand" href="<?= e(base_url('/')); ?>">
            <img src="<?= e(base_url('/assets/images/logo-mark.svg')); ?>" alt="Verivote">
            <span>Verivote</span>
        </a>

        <button class="mobile-nav-toggle" type="button" data-nav-toggle aria-label="Toggle navigation">
            <span></span>
            <span></span>
        </button>

        <nav class="topnav" data-nav-menu>
            <a class="<?= $activeNav === 'home'   ? 'is-active' : ''; ?>" href="<?= e(base_url('/')); ?>">Home</a>
            <a class="<?= $activeNav === 'events' ? 'is-active' : ''; ?>" href="<?= e(base_url('/events.php')); ?>">Elections</a>
            <?php if ($viewer): ?>
                <a class="<?= $activeNav === 'dashboard' ? 'is-active' : ''; ?>" href="<?= e(base_url(dashboard_home_for_role($viewer['role_slug']))); ?>">Dashboard</a>
                <a class="button button--ghost" style="min-height:36px;padding:0 14px;font-size:0.85rem;" href="<?= e(base_url('/auth/logout.php')); ?>">Sign out</a>
            <?php else: ?>
                <a class="<?= $activeNav === 'login' ? 'is-active' : ''; ?>" href="<?= e(base_url('/auth/login.php')); ?>">Log in</a>
                <a class="button button--primary" style="min-height:36px;padding:0 16px;font-size:0.85rem;" href="<?= e(base_url('/auth/register.php')); ?>">Create account</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
