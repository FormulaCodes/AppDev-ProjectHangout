
<?php

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/models/Place.php';

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($id === false || $id === null || $id < 1) {
    http_response_code(404);
    exit('Place not found.');
}

$placeModel = new Place($pdo);
$place = $placeModel->getById($id);

if ($place === null) {
    http_response_code(404);
    exit('Place not found.');
}

$tags = $place['tags'] !== null
    ? explode(', ', $place['tags'])
    : [];

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escape($place['name']) ?> | Hangout</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <header>
        <h1>Hangout</h1>
        <p>Discover places around your campus.</p>
    </header>

    <main>
        <p><a href="index.php">← Back to Explore Places</a></p>

        <article class="place-detail">
            <h2><?= escape($place['name']) ?></h2>

            <p><?= nl2br(escape($place['description'] ?? 'No description available yet.')) ?></p>

            <h3>Address</h3>
            <p><?= escape($place['address']) ?></p>

            <h3>Tags</h3>

            <?php if (empty($tags)): ?>
                <p>No tags available.</p>
            <?php else: ?>
                <div class="tag-list">
                    <?php foreach ($tags as $tag): ?>
                        <span class="tag">
                            <?= escape($tag) ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <h3>Location coordinates</h3>

            <?php if (
                $place['latitude'] !== null
                && $place['longitude'] !== null
            ): ?>
                <p>
                    Latitude: <?= escape((string) $place['latitude']) ?><br>
                    Longitude: <?= escape((string) $place['longitude']) ?>
                </p>
            <?php else: ?>
                <p>Location coordinates have not been added yet.</p>
            <?php endif; ?>
        </article>
    </main>

</body>
</html>
