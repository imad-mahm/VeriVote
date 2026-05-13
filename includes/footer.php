<?php
declare(strict_types=1);
?>
<?php if ($isDashboard ?? false): ?>
        </main>
    </div>
<?php else: ?>
    </main>
    <footer class="site-footer">
        <div class="container site-footer__inner">
            <div>
                <strong>Verivote</strong>
                <p>Secure. Verifiable. Privacy-preserving.</p>
            </div>
            <div class="site-footer__links">
                <a href="<?= e(base_url('/events.php')); ?>">Elections</a>
                <a href="<?= e(base_url('/voter/verify_vote.php')); ?>">Verify receipt</a>
                <?php if (current_user()): ?>
                    <a href="<?= e(base_url(dashboard_home_for_role((string) current_role_slug()))); ?>">Dashboard</a>
                    <a href="<?= e(base_url('/auth/logout.php')); ?>">Sign out</a>
                <?php else: ?>
                    <a href="<?= e(base_url('/auth/login.php')); ?>">Log in</a>
                    <a href="<?= e(base_url('/auth/register.php')); ?>">Register</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="footer-colophon" aria-hidden="true">Verivote</div>
    </footer>
<?php endif; ?>
<script src="<?= e(base_url('/assets/js/app.js')); ?>" defer></script>
</body>
</html>
