
<?php

require_once __DIR__ . '/../../app/helpers/auth.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/models/Place.php';

requireAdmin();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$placeModel = new Place($pdo);

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    http_response_code(400);
    exit('Invalid place ID.');
}

$place = $placeModel->getById($id);

if (!$place) {
    http_response_code(404);
    exit('Place not found.');
}

$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        http_response_code(403);
        exit('Invalid security token. Please return and try again.');
    }

    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($name === '' || $description === '' || $address === '') {
        $errorMessage = 'Please fill in all fields.';
    } else {
        try {
            $placeModel->update(
                $id,
                $name,
                $description,
                $address
            );

            header('Location: places.php?updated=1');
            exit;
        } catch (PDOException $e) {
            $errorMessage = 'Unable to update the place. Please try again.';
        }
    }

    $place['name'] = $name;
    $place['description'] = $description;
    $place['address'] = $address;
}

function escapeValue(?string $value): string
{
    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Place | Hangout</title>
</head>
<body>
    <h1>Edit Place</h1>

    <p>
        <a href="places.php">Back to Manage Places</a>
    </p>

    <?php if ($errorMessage !== ''): ?>
        <p style="color: red;">
            <?= escapeValue($errorMessage) ?>
        </p>
    <?php endif; ?>

    <form method="POST" action="edit-place.php?id=<?= (int) $id ?>">
        <input
            type="hidden"
            name="csrf_token"
            value="<?= escapeValue($_SESSION['csrf_token']) ?>"
        >

        <div>
            <label for="name">Place Name</label><br>
            <input
                type="text"
                id="name"
                name="name"
                maxlength="150"
                value="<?= escapeValue($place['name']) ?>"
                required
            >
        </div>

        <br>

        <div>
            <label for="description">Description</label><br>
            <textarea
                id="description"
                name="description"
                rows="5"
                required
            ><?= escapeValue($place['description']) ?></textarea>
        </div>

        <br>

        <div>
            <label for="address">Address</label><br>
            <input
                type="text"
                id="address"
                name="address"
                maxlength="255"
                value="<?= escapeValue($place['address']) ?>"
                required
            >
        </div>

        <br>

        <button type="submit">Save Changes</button>
    </form>
</body>
</html>
