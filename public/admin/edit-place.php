<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../../app/helpers/auth.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/models/Place.php';

requireAdmin();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function escapeValue($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// ---- Read ID first, before anything else uses it ----
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    http_response_code(400);
    exit('Invalid place ID.');
}

// ---- Instantiate model, then load data ----
$placeModel = new Place($pdo);

$place = $placeModel->getById($id, true); // includeInactive=true so we can edit inactive places too
if (!$place) {
    http_response_code(404);
    exit('Place not found.');
}

$allTags       = $placeModel->getAllTags();
$currentTagIds = $placeModel->getTagIdsForPlace($id);

$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        exit('Invalid security token. Please return and try again.');
    }

    $name        = trim((string) ($_POST['name'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $address     = trim((string) ($_POST['address'] ?? ''));

    // Preserve existing coordinates (form doesn't edit them yet)
    $latitude  = $place['latitude']  !== null ? (float) $place['latitude']  : null;
    $longitude = $place['longitude'] !== null ? (float) $place['longitude'] : null;

    if ($name === '' || $description === '' || $address === '') {
        $errorMessage = 'Please fill in all fields.';
    } elseif (mb_strlen($name) > 150 || mb_strlen($address) > 255) {
        $errorMessage = 'Name or address is too long.';
    } else {
        try {
            $placeModel->update($id, $name, $description, $address, $latitude, $longitude);

            $submittedTags = $_POST['tags'] ?? [];
            if (!is_array($submittedTags)) {
                $submittedTags = [];
            }
            $submittedTags = array_filter(
                array_map('intval', $submittedTags),
                fn($tid) => $tid > 0
            );

            $placeModel->setTags($id, $submittedTags);

            header('Location: places.php?updated=1');
            exit;
        } catch (PDOException $e) {
            error_log('Place update error: ' . $e->getMessage());
            $errorMessage = 'Unable to update the place. Please try again.';
        }
    }

    // Repopulate form fields on error
    $place['name']        = $name;
    $place['description'] = $description;
    $place['address']     = $address;
    $currentTagIds        = $submittedTags ?? [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Place | Hangout Admin</title>
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
            <a href="places.php" class="admin-nav__link is-active" aria-current="page">Places</a>
            <a href="tags.php" class="admin-nav__link">Tags</a>
            <a href="suggestions.php" class="admin-nav__link">Suggestions</a>
            <a href="reviews.php" class="admin-nav__link">Reviews</a>
            <a href="users.php" class="admin-nav__link">Users</a>
            <a href="../index.php" class="btn btn--ghost">View site</a>
        </nav>
    </div>
</header>

<main id="main" class="container">

    <nav class="breadcrumb" aria-label="Breadcrumb">
        <a href="places.php">← Back to Manage Places</a>
    </nav>

    <header class="page-header">
        <div>
            <h1>Edit Place</h1>
            <p class="page-header__subtitle">
                Editing <strong><?= escapeValue($place['name']) ?></strong>
                <?php if (isset($place['is_active']) && (int) $place['is_active'] === 0): ?>
                    — <span class="status-pill status-pill--inactive">Inactive</span>
                <?php endif; ?>
            </p>
        </div>
    </header>

    <?php if ($errorMessage !== ''): ?>
        <div class="alert alert--error" role="alert"><?= escapeValue($errorMessage) ?></div>
    <?php endif; ?>

    <section class="panel">
        <form method="POST" action="edit-place.php?id=<?= (int) $id ?>" class="admin-form">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= escapeValue($_SESSION['csrf_token']) ?>"
            >

            <div class="field">
                <label for="name">Place Name</label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    maxlength="150"
                    value="<?= escapeValue($place['name']) ?>"
                    required
                >
            </div>

            <div class="field">
                <label for="address">Address</label>
                <input
                    type="text"
                    id="address"
                    name="address"
                    maxlength="255"
                    value="<?= escapeValue($place['address']) ?>"
                    required
                >
            </div>

            <div class="field">
                <label for="description">Description</label>
                <textarea
                    id="description"
                    name="description"
                    rows="5"
                    required
                ><?= escapeValue($place['description']) ?></textarea>
            </div>

            <fieldset class="tag-fieldset">
                <legend>Tags <span class="label-hint">(optional)</span></legend>

                <?php if (empty($allTags)): ?>
                    <p class="text-muted">
                        No tags exist yet. <a href="tags.php">Create one first</a>.
                    </p>
                <?php else: ?>
                    <div class="tag-checkbox-grid">
                        <?php foreach ($allTags as $tag): ?>
                            <label class="tag-checkbox">
                                <input
                                    type="checkbox"
                                    name="tags[]"
                                    value="<?= (int) $tag['id'] ?>"
                                    <?= in_array((int) $tag['id'], $currentTagIds, true) ? 'checked' : '' ?>
                                >
                                <span><?= escapeValue($tag['name']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </fieldset>

            <?php if (
                $place['latitude'] !== null
                && $place['longitude'] !== null
            ): ?>
                <p class="field-hint">
                    Coordinates preserved:
                    <?= escapeValue((string) $place['latitude']) ?>,
                    <?= escapeValue((string) $place['longitude']) ?>
                </p>
            <?php endif; ?>

            <div class="form-actions">
                <button type="submit" class="btn btn--solid">Save Changes</button>
                <a href="places.php" class="btn btn--text">Cancel</a>
            </div>
        </form>
    </section>

</main>
</body>
</html>