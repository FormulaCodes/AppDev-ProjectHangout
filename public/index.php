<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/models/Place.php';

$placeModel = new Place($pdo);

$search = isset($_GET['search']) && is_string($_GET['search'])
    ? trim($_GET['search'])
    : '';

$tagSlug = isset($_GET['tag']) && is_string($_GET['tag'])
    ? trim($_GET['tag'])
    : '';

$tags   = $placeModel->getAllTags();
$places = $placeModel->getAll($search, $tagSlug);

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$placeCount = count($places);
$hasFilters = $search !== '' || $tagSlug !== '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hangout — Discover Your Next Spot</title>
    <meta name="description" content="Discover places around your campus.">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<a class="skip-link" href="#main">Skip to content</a>

<header class="site-header">
    <div class="site-header__inner">
        <div class="brand">
            <a href="index.php" class="brand__name">Hangout</a>
            <p class="brand__tagline">Discover places around your campus.</p>
        </div>

        <nav class="auth-nav" aria-label="Account">
            <?php if (isset($_SESSION['user_id'])): ?>
                <span class="auth-nav__welcome">
                    Welcome, <?= escape((string) $_SESSION['username']) ?>
                </span>
                <form method="POST" action="logout.php" class="auth-nav__form">
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= escape($_SESSION['csrf_token']) ?>"
                    >
                    <button type="submit" class="btn btn--ghost">Logout</button>
                </form>
            <?php else: ?>
                <a class="btn btn--ghost" href="login.php">Login</a>
                <a class="btn btn--solid" href="register.php">Register</a>
            <?php endif; ?>
            <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
    <a class="btn btn--ghost" href="admin/index.php">Admin dashboard</a>
<?php endif; ?>
        </nav>
    </div>
</header>

<main id="main" class="container">

    <section class="explore" aria-labelledby="explore-heading">
        <div class="explore__header">
            <h1 id="explore-heading">Explore places</h1>
            <p class="explore__subtitle">
                Find your next study spot, cafe, or hangout around campus.
            </p>
        </div>

        <form method="GET" action="index.php" class="search-form" role="search">
            <div class="field">
                <label for="search">Search places</label>
                <input
                    type="search"
                    id="search"
                    name="search"
                    placeholder="Try a name, address, or keyword…"
                    value="<?= escape($search) ?>"
                >
            </div>

            <div class="field">
                <label for="tag">Filter by tag</label>
                <select id="tag" name="tag">
                    <option value="">All tags</option>
                    <?php foreach ($tags as $tag): ?>
                        <option
                            value="<?= escape($tag['slug']) ?>"
                            <?= $tagSlug === $tag['slug'] ? 'selected' : '' ?>
                        >
                            <?= escape($tag['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="search-form__actions">
                <button type="submit" class="btn btn--solid">Find places</button>
                <?php if ($hasFilters): ?>
                    <a class="btn btn--text" href="index.php">Clear filters</a>
                <?php endif; ?>
            </div>
        </form>

        <p class="results-count" role="status" aria-live="polite">
            <?= $placeCount ?> <?= $placeCount === 1 ? 'place' : 'places' ?> found<?= $hasFilters ? ' for your filters' : '' ?>.
        </p>

        <?php if ($placeCount === 0): ?>
            <div class="empty-state">
                <h2>No places match your search</h2>
                <p>Try different keywords or <a href="index.php">clear your filters</a>.</p>
            </div>
        <?php else: ?>
            <ul class="places-grid" role="list">
                <?php foreach ($places as $place): ?>
                    <?php
                   
                        $rawTags = $place['tags'] ?? '';
                        $tagItems = [];
                        if (is_array($rawTags)) {
                            foreach ($rawTags as $t) {
                                $tagItems[] = is_array($t) ? ($t['name'] ?? '') : (string) $t;
                            }
                        } elseif (is_string($rawTags) && $rawTags !== '') {
                            $tagItems = array_filter(array_map('trim', explode(',', $rawTags)));
                        }
                    ?>
                    <li>
                        <article class="place-card">
                            <header class="place-card__header">
                                <h2 class="place-card__title">
                                    <a href="place.php?id=<?= (int) $place['id'] ?>">
                                        <?= escape($place['name']) ?>
                                    </a>
                                </h2>
                            </header>

                            <?php if (!empty($place['description'])): ?>
                                <p class="place-card__desc">
                                    <?= escape($place['description']) ?>
                                </p>
                            <?php endif; ?>

                            <p class="place-card__meta">
                                <span class="place-card__label">Address</span>
                                <?= escape($place['address']) ?>
                            </p>

                            <div class="tag-list">
                                <?php if ($tagItems): ?>
                                    <?php foreach ($tagItems as $tagName): ?>
                                        <span class="tag"><?= escape($tagName) ?></span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="tag tag--muted">No tags yet</span>
                                <?php endif; ?>
                            </div>
                        </article>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

</main>
</body>
</html>