

<?php

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/models/Place.php';

$placeModel = new Place($pdo);

$search = isset($_GET['search']) && is_string($_GET['search'])
    ? trim($_GET['search'])
    : '';

$tagSlug = isset($_GET['tag']) && is_string($_GET['tag'])
    ? trim($_GET['tag'])
    : '';

$tags = $placeModel->getAllTags();
$places = $placeModel->getAll($search, $tagSlug);

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hangout | Discover Your Next Spot</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header>
        <h1>Hangout</h1>
        <p>Discover places around your campus.</p>
    </header>

    <main>
        <section>
            <h2>Explore Places</h2>

            <form method="GET" action="index.php" class="search-form">
                <label for="search">Search places</label>

                <input
                    type="search"
                    id="search"
                    name="search"
                    placeholder="Try a name, address, or keyword..."
                    value="<?= escape($search) ?>"
                >

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

                <button type="submit">Find Places</button>

                <a href="index.php">Clear filters</a>
            </form>

            <p>
                <?= count($places) ?>
                <?= count($places) === 1 ? 'place' : 'places' ?>
                found.
            </p>

            <?php if (empty($places)): ?>
                <p>No places match your search. Try different keywords or clear your filters.</p>
            <?php else: ?>
                <div class="places-grid">
                    <?php foreach ($places as $place): ?>
                        <article class="place-card">
                            <h3><?= escape($place['name']) ?></h3>

                            <p>
                                <?= escape($place['description'] ?? '') ?>
                            </p>

                            <p>
                                <strong>Address:</strong>
                                <?= escape($place['address']) ?>
                            </p>

                            <p>
                                <strong>Tags:</strong>
                                <?= escape($place['tags'] ?? 'No tags yet') ?>
                            </p>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>

