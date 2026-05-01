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
                <a href="<?= e(base_url('/events.php')); ?>">Events</a>
                <a href="<?= e(base_url('/results.php')); ?>">Results</a>
                <a href="<?= e(base_url('/audit.php')); ?>">Audit</a>
            </div>
        </div>
    </footer>
<?php endif; ?>
<script src="<?= e(base_url('/assets/js/app.js')); ?>" defer></script>
</body>
</html>
