<?php
declare(strict_types=1);

$pageTitle = $pageTitle ?? 'Verivote';
$pageDescription = $pageDescription ?? '';
$activeNav = $activeNav ?? '';
$isDashboard = $isDashboard ?? false;
$sidebarContext = $sidebarContext ?? (current_role_slug() ?? 'voter');
$flashes = pull_flashes();
$bodyBaseUrl = rtrim(base_url(''), '/');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle); ?> | Verivote</title>
    <meta name="description" content="<?= e($pageDescription ?: 'Verivote is a secure, verifiable, privacy-preserving online voting platform.'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(base_url('/assets/css/app.css')); ?>">
</head>
<body class="<?= $isDashboard ? 'dashboard-body' : 'public-body'; ?>" data-base-url="<?= e($bodyBaseUrl); ?>">
<?php include __DIR__ . '/navigation.php'; ?>
<?php if ($isDashboard): ?>
    <div class="app-shell">
        <?php include __DIR__ . '/sidebar.php'; ?>
        <main class="app-main">
            <section class="page-head">
                <div>
                    <span class="eyebrow">Verivote</span>
                    <h1><?= e($pageHeading ?? $pageTitle); ?></h1>
                    <?php if ($pageDescription !== ''): ?>
                        <p><?= e($pageDescription); ?></p>
                    <?php endif; ?>
                </div>
                <?php if ($user = current_user()): ?>
                    <div class="user-chip">
                        <strong><?= e($user['full_name']); ?></strong>
                        <span><?= e($user['role_name'] ?? format_status($user['role_slug'] ?? '')); ?></span>
                    </div>
                <?php endif; ?>
            </section>
            <?php if ($flashes): ?>
                <section class="flash-stack">
                    <?php foreach ($flashes as $flash): ?>
                        <div class="alert alert--<?= e($flash['type']); ?>"><?= e($flash['message']); ?></div>
                    <?php endforeach; ?>
                </section>
            <?php endif; ?>
<?php else: ?>
    <main class="public-main">
        <?php if ($flashes): ?>
            <section class="container flash-stack flash-stack--public">
                <?php foreach ($flashes as $flash): ?>
                    <div class="alert alert--<?= e($flash['type']); ?>"><?= e($flash['message']); ?></div>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
<?php endif; ?>
