<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../../app/helpers/auth.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/models/User.php';

requireAdmin();

$userModel = new User($pdo);

function escapeUserPage($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

$search = trim($_GET['search'] ?? '');
$role = $_GET['role'] ?? 'all';

if (!in_array($role, ['all', 'user', 'admin'], true)) {
    $role = 'all';
}

$users = $userModel->getAll($search, $role);

$hasFilters = $search !== '' || $role !== 'all';

$adminCount = 0;
$userCount  = 0;
foreach ($users as $u) {
    if (($u['role'] ?? '') === 'admin') {
        $adminCount++;
    } else {
        $userCount++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>User Management | Hangout Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="admin-body">

<a class="skip-link" href="#main">Skip to content</a>

<header class="site-header">
    <div class="site-header__inner">
        <div class="brand">
            <a href="index.php" class="brand__name">
                Hangout <span class="brand__badge">Admin</span>
            </a>
            <p class="brand__tagline">Manage places and reviews.</p>
        </div>

        <nav class="admin-nav" aria-label="Admin sections">
            <a href="index.php" class="admin-nav__link">Dashboard</a>
            <a href="places.php" class="admin-nav__link">Places</a>
             <a href="suggestions.php" class="admin-nav__link">Suggestions</a>
            <a href="reviews.php" class="admin-nav__link">Reviews</a>
            <a href="users.php" class="admin-nav__link is-active" aria-current="page">Users</a>
            <a href="../index.php" class="btn btn--ghost">View site</a>
        </nav>
    </div>
</header>

<main id="main" class="container">

    <header class="page-header">
        <div>
            <h1>User Management</h1>
            <p class="page-header__subtitle">
                Search registered accounts and filter them by account role.
            </p>
        </div>

        <ul class="stat-chips" role="list">
            <li class="stat-chip">
                <span class="stat-chip__value"><?= count($users) ?></span>
                <span class="stat-chip__label">Total</span>
            </li>
            <li class="stat-chip stat-chip--success">
                <span class="stat-chip__value"><?= $userCount ?></span>
                <span class="stat-chip__label">Users</span>
            </li>
            <li class="stat-chip stat-chip--accent">
                <span class="stat-chip__value"><?= $adminCount ?></span>
                <span class="stat-chip__label">Admins</span>
            </li>
        </ul>
    </header>

    <section class="panel" aria-labelledby="filters-heading">
        <header class="panel__header">
            <h2 id="filters-heading">Filters</h2>
        </header>

        <form method="GET" action="users.php" class="filters-grid filters-grid--two">
            <div class="field">
                <label for="search">Search username or email</label>
                <input
                    type="search"
                    id="search"
                    name="search"
                    placeholder="Enter a username or email…"
                    value="<?= escapeUserPage($search) ?>"
                >
            </div>

            <div class="field">
                <label for="role">Account role</label>
                <select id="role" name="role">
                    <option value="all"   <?= $role === 'all'   ? 'selected' : '' ?>>All roles</option>
                    <option value="user"  <?= $role === 'user'  ? 'selected' : '' ?>>Regular users</option>
                    <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Administrators</option>
                </select>
            </div>

            <div class="filters-grid__actions">
                <button type="submit" class="btn btn--solid">Apply filters</button>
                <?php if ($hasFilters): ?>
                    <a href="users.php" class="btn btn--text">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <section class="panel" aria-labelledby="results-heading">
        <header class="panel__header panel__header--row">
            <h2 id="results-heading">Accounts</h2>
            <p class="results-count" role="status" aria-live="polite">
                <strong><?= count($users) ?></strong>
                <?= count($users) === 1 ? 'account' : 'accounts' ?>
                found<?= $hasFilters ? ' for your filters' : '' ?>.
            </p>
        </header>

        <?php if (empty($users)): ?>
            <div class="empty-state">
                <h3>No accounts match</h3>
                <p>Try a different search or <a href="users.php">reset the filters</a>.</p>
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table class="data-table data-table--users">
                    <thead>
                        <tr>
                            <th scope="col">ID</th>
                            <th scope="col">Username</th>
                            <th scope="col">Email</th>
                            <th scope="col">Role</th>
                            <th scope="col">Registered</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <?php $isAdmin = ($user['role'] ?? '') === 'admin'; ?>
                            <tr>
                                <td class="data-table__id"><?= (int) $user['id'] ?></td>

                                <td><strong><?= escapeUserPage($user['username']) ?></strong></td>

                                <td class="data-table__email">
                                    <a href="mailto:<?= escapeUserPage($user['email']) ?>">
                                        <?= escapeUserPage($user['email']) ?>
                                    </a>
                                </td>

                                <td>
                                    <span class="role-pill role-pill--<?= $isAdmin ? 'admin' : 'user' ?>">
                                        <?= escapeUserPage(ucfirst($user['role'] ?? 'user')) ?>
                                    </span>
                                </td>

                                <td class="data-table__date">
                                    <?= escapeUserPage($user['created_at']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

</main>
</body>
</html>