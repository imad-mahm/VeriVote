document.addEventListener('DOMContentLoaded', function () {
    const baseUrl = (document.body && document.body.dataset.baseUrl) || '';
    const navToggle = document.querySelector('[data-nav-toggle]');
    const navMenu = document.querySelector('[data-nav-menu]');

    // Stagger — observe [data-stagger] containers; assign --stagger-i to each child
    const staggerObserver = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                staggerObserver.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });

    // Auto-stagger stats grids, feature card grids, and hero metric rows
    document.querySelectorAll('.stats-grid, .grid-cards, .hero__metrics').forEach(function (grid) {
        grid.setAttribute('data-stagger', '');
    });

    document.querySelectorAll('[data-stagger]').forEach(function (container) {
        Array.from(container.children).forEach(function (child, i) {
            child.style.setProperty('--stagger-i', i);
        });
        staggerObserver.observe(container);
    });

    // Stat counter — count up from 0 when a .stat-box strong or .metric strong enters the viewport
    const statEls = document.querySelectorAll('.stat-box strong, .metric strong');
    if (statEls.length) {
        const counterObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                const el = entry.target;
                const target = parseInt(el.textContent.replace(/\D/g, ''), 10);
                if (isNaN(target) || target === 0) return;
                const duration = 900;
                const startTime = performance.now();
                function tick(now) {
                    var progress = Math.min((now - startTime) / duration, 1);
                    var eased = 1 - Math.pow(1 - progress, 3); // ease-out cubic
                    el.textContent = Math.round(eased * target);
                    if (progress < 1) {
                        requestAnimationFrame(tick);
                    } else {
                        el.textContent = target;
                    }
                }
                requestAnimationFrame(tick);
                counterObserver.unobserve(el);
            });
        }, { threshold: 0.5 });

        statEls.forEach(function (el) { counterObserver.observe(el); });
    }

    if (navToggle && navMenu) {
        navToggle.addEventListener('click', function () {
            navMenu.classList.toggle('is-open');
        });
    }

    const revealNodes = document.querySelectorAll('[data-reveal]');
    if (revealNodes.length) {
        const observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.15 });

        revealNodes.forEach(function (node) {
            observer.observe(node);
        });
    }

    document.querySelectorAll('[data-copy]').forEach(function (button) {
        button.addEventListener('click', async function () {
            const value = button.getAttribute('data-copy');

            if (!value) {
                return;
            }

            try {
                await navigator.clipboard.writeText(value);
                button.textContent = 'Copied';
                setTimeout(function () {
                    button.textContent = 'Copy';
                }, 1500);
            } catch (error) {
                button.textContent = 'Unavailable';
            }
        });
    });

    document.querySelectorAll('[data-confirm]').forEach(function (element) {
        element.addEventListener('click', function (event) {
            const message = element.getAttribute('data-confirm') || 'Are you sure?';

            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    const auditFeed = document.querySelector('[data-audit-feed]');
    if (auditFeed) {
        const eventId = auditFeed.getAttribute('data-event-id');
        if (eventId) {
            fetch(baseUrl + '/api/verification_feed.php?event=' + encodeURIComponent(eventId))
                .then(function (response) {
                    return response.ok ? response.json() : null;
                })
                .then(function (payload) {
                    if (!payload || !Array.isArray(payload.rows)) {
                        return;
                    }

                    auditFeed.innerHTML = payload.rows.map(function (row) {
                        return '<div class="list-row"><div><strong>' +
                            row.public_receipt_hash.slice(0, 16) +
                            '...</strong><p>Ballot hash ' +
                            row.ballot_hash.slice(0, 24) +
                            '...</p></div><span class="badge badge-muted">' +
                            row.submitted_at +
                            '</span></div>';
                    }).join('');
                })
                .catch(function () {
                    auditFeed.innerHTML = '<div class="alert alert--warning">Audit feed unavailable.</div>';
                });
        }
    }
});
