


<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../../app/helpers/auth.php';

requireAdmin();

$username = $_SESSION['username'] ?? 'Admin';
function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Hangout | Admin Dashboard</title>
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
            <a href="index.php" class="admin-nav__link is-active" aria-current="page">Dashboard</a>
            <a href="places.php" class="admin-nav__link">Places</a>
            <a href="reviews.php" class="admin-nav__link">Reviews</a>
            <a href="users.php" class="admin-nav__link">Users</a>
            <a href="../index.php" class="btn btn--ghost">View site</a>
        </nav>
    </div>
</header>

<main id="main" class="container">

    <header class="page-header">
        <div>
            <h1>Hangout Admin Dashboard</h1>
            <p class="page-header__subtitle">
                Welcome back, <?= escape($username) ?>. Manage your places and keep the directory organized.
            </p>
        </div>
    </header>

    <section class="admin-grid" aria-label="Admin tools">

        <a href="places.php" class="admin-card">
            <div class="admin-icon" aria-hidden="true">📍</div>
            <h2>Manage Places</h2>
            <p>Add places, edit their details, deactivate listings, and restore previously deactivated places.</p>
            <span class="admin-card__cta">Open Places →</span>
        </a>

        <a href="reviews.php" class="admin-card">
            <div class="admin-icon" aria-hidden="true">💬</div>
            <h2>Review Moderation</h2>
            <p>Inspect submitted reviews and manage inappropriate or inactive content.</p>
            <span class="admin-card__cta">Open Reviews →</span>
        </a>

        <a href="users.php" class="admin-card">
    <div class="admin-icon" aria-hidden="true">👥</div>
    <h2>Manage Users</h2>
    <p>View registered accounts and manage user access.</p>
    <span class="admin-card__cta">Open Users →</span>
        </a>

    </section>

    <section class="panel admin-notice" aria-labelledby="admin-notice-heading">
        <header class="panel__header">
            <h2 id="admin-notice-heading">Administrator Access</h2>
        </header>
        <p>
            You have successfully accessed the protected admin area.
            Only authorized administrators should be able to use these management tools.
        </p>
    </section>

</main>
</body>
</html>