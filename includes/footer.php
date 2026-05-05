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
                <a href="<?= e(base_url('/auth/login.php')); ?>">Log in</a>
                <a href="<?= e(base_url('/auth/register.php')); ?>">Register</a>
            </div>
        </div>
        <div class="footer-colophon" aria-hidden="true">Verivote</div>
    </footer>
<?php endif; ?>
<script src="<?= e(base_url('/assets/js/app.js')); ?>" defer></script>
</body>
</html>
