<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_role('super_admin');

if (is_post_request()) {
    verify_csrf_or_fail();
    $action   = (string) ($_POST['action'] ?? '');
    $targetId = (int) ($_POST['user_id'] ?? 0);

    if (in_array($action, ['suspend', 'activate'], true) && $targetId > 0) {
        $target = fetch_one(
            'SELECT users.id, users.full_name, roles.slug AS role_slug
             FROM users INNER JOIN roles ON roles.id = users.role_id
             WHERE users.id = :id',
            ['id' => $targetId]
        );

        if ($target && $target['role_slug'] === 'voter') {
            $newStatus = $action === 'suspend' ? 'suspended' : 'active';
            execute_statement(
                'UPDATE users SET status = :status WHERE id = :id',
                ['status' => $newStatus, 'id' => $targetId]
            );
            write_audit_log(
                'voter_status_changed', 'users', (string) $targetId,
                'Voter status set to ' . $newStatus . '.', null,
                ['voter_name' => $target['full_name'], 'new_status' => $newStatus]
            );
            flash('success', $target['full_name'] . ' has been ' . ($newStatus === 'suspended' ? 'suspended' : 'reactivated') . '.');
        } else {
            flash('error', 'Voter not found.');
        }
    }

    redirect('/admin/voters.php');
}

$search       = trim((string) ($_GET['q'] ?? ''));
$statusFilter = (string) ($_GET['status'] ?? '');
$page         = max(1, (int) ($_GET['page'] ?? 1));
$perPage      = 25;
$offset       = ($page - 1) * $perPage;

$whereClauses = ['roles.slug = "voter"'];
$params       = [];

if ($search !== '') {
    $whereClauses[] = '(users.full_name LIKE :search OR users.email LIKE :search OR users.phone LIKE :search)';
    $params['search'] = '%' . $search . '%';
}

if (in_array($statusFilter, ['active', 'suspended', 'pending'], true)) {
    $whereClauses[] = 'users.status = :status';
    $params['status'] = $statusFilter;
}

$where = 'WHERE ' . implode(' AND ', $whereClauses);

$total = (int) (fetch_one(
    'SELECT COUNT(*) AS aggregate FROM users INNER JOIN roles ON roles.id = users.role_id ' . $where,
    $params
)['aggregate'] ?? 0);

$voters = fetch_all(
    'SELECT users.id, users.full_name, users.email, users.phone, users.status,
            users.phone_verified_at, users.created_at, users.last_login_at,
            (SELECT COUNT(*) FROM voter_event_submissions WHERE voter_event_submissions.user_id = users.id) AS submission_count,
            (SELECT COUNT(*) FROM voting_tokens vt
             INNER JOIN voter_event_submissions ves ON ves.id = vt.submission_id
             WHERE ves.user_id = users.id AND vt.status = "used") AS ballot_count
     FROM users
     INNER JOIN roles ON roles.id = users.role_id
     ' . $where . '
     ORDER BY users.id DESC
     LIMIT ' . $perPage . ' OFFSET ' . $offset,
    $params
);

$totalPages = (int) ceil($total / $perPage);

$pageTitle       = 'Voter Accounts';
$pageHeading     = 'Voter Accounts';
$pageDescription = 'View and manage registered voter accounts.';
$isDashboard     = true;
$sidebarContext  = 'super_admin';
$activeSidebar   = 'admin-voters';

include dirname(__DIR__) . '/includes/header.php';
?>
<section class="panel">
    <form method="get" class="form-grid form-grid--inline">
        <div class="field">
            <label for="q">Search</label>
            <input id="q" type="text" name="q" value="<?= e($search); ?>" placeholder="Name, email or phone">
        </div>
        <div class="field">
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="">All statuses</option>
                <option value="active"    <?= $statusFilter === 'active'    ? 'selected' : ''; ?>>Active</option>
                <option value="suspended" <?= $statusFilter === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                <option value="pending"   <?= $statusFilter === 'pending'   ? 'selected' : ''; ?>>Pending</option>
            </select>
        </div>
        <div class="field field--action">
            <button class="button button--primary" type="submit">Filter</button>
            <?php if ($search !== '' || $statusFilter !== ''): ?>
                <a class="button button--ghost" href="<?= e(base_url('/admin/voters.php')); ?>">Clear</a>
            <?php endif; ?>
        </div>
    </form>
</section>

<div class="table-wrap">
    <table>
        <thead>
        <tr>
            <th>Voter</th>
            <th>Status</th>
            <th>Phone</th>
            <th>Submissions</th>
            <th>Ballots</th>
            <th>Joined</th>
            <th>Last login</th>
            <th>Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php if (!$voters): ?>
            <tr><td colspan="8">No voters found<?= $search !== '' || $statusFilter !== '' ? ' matching that filter' : ''; ?>.</td></tr>
        <?php else: ?>
            <?php foreach ($voters as $voter): ?>
                <tr>
                    <td>
                        <strong><?= e($voter['full_name']); ?></strong>
                        <p><?= e($voter['email']); ?></p>
                        <p><?= e((string) $voter['phone']); ?></p>
                    </td>
                    <td><span class="badge <?= e(badge_class($voter['status'])); ?>"><?= e(format_status($voter['status'])); ?></span></td>
                    <td>
                        <span class="badge <?= !empty($voter['phone_verified_at']) ? 'badge-success' : 'badge-warning'; ?>">
                            <?= !empty($voter['phone_verified_at']) ? 'Verified' : 'Unverified'; ?>
                        </span>
                    </td>
                    <td><?= e((string) $voter['submission_count']); ?></td>
                    <td><?= e((string) $voter['ballot_count']); ?></td>
                    <td><?= e(format_datetime($voter['created_at'], 'M j, Y')); ?></td>
                    <td><?= !empty($voter['last_login_at']) ? e(format_datetime($voter['last_login_at'], 'M j, Y')) : '—'; ?></td>
                    <td class="table-actions">
                        <?php if ($voter['status'] === 'active'): ?>
                            <form method="post">
                                <?= csrf_field(); ?>
                                <input type="hidden" name="action" value="suspend">
                                <input type="hidden" name="user_id" value="<?= e((string) $voter['id']); ?>">
                                <button class="button button--danger" type="submit"
                                    data-confirm="Suspend <?= e($voter['full_name']); ?>? They will be unable to log in.">
                                    Suspend
                                </button>
                            </form>
                        <?php elseif ($voter['status'] === 'suspended'): ?>
                            <form method="post">
                                <?= csrf_field(); ?>
                                <input type="hidden" name="action" value="activate">
                                <input type="hidden" name="user_id" value="<?= e((string) $voter['id']); ?>">
                                <button class="button button--ghost" type="submit">Reactivate</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($totalPages > 1): ?>
    <nav class="pagination">
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <a class="<?= $p === $page ? 'is-active' : ''; ?>"
               href="<?= e(base_url('/admin/voters.php?' . http_build_query(['q' => $search, 'status' => $statusFilter, 'page' => $p]))); ?>">
                <?= $p; ?>
            </a>
        <?php endfor; ?>
    </nav>
<?php endif; ?>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
