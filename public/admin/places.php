<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../../app/helpers/auth.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/models/Place.php';

requireAdmin();

$placeModel = new Place($pdo);

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($submittedToken) ||
        !hash_equals($_SESSION['csrf_token'], $submittedToken)
    ) {
        $errorMessage = 'Invalid form submission. Please refresh the page.';
    } else {
        $action = $_POST['action'] ?? 'add';

        if ($action === 'restore') {
            $idInput = $_POST['id'] ?? '';

            $id = is_string($idInput) && ctype_digit($idInput)
                ? (int) $idInput
                : 0;

            if ($id < 1) {
                $errorMessage = 'Invalid place ID.';
            } elseif ($placeModel->restore($id)) {
                header('Location: places.php?restored=1');
                exit;
            } else {
                $errorMessage =
                    'The place could not be restored. It may already be active or no longer exist.';
            }
        } elseif ($action === 'add') {
            $nameInput = $_POST['name'] ?? '';
            $descriptionInput = $_POST['description'] ?? '';
            $addressInput = $_POST['address'] ?? '';

            $name = is_string($nameInput) ? trim($nameInput) : '';
            $description = is_string($descriptionInput)
                ? trim($descriptionInput)
                : '';
            $address = is_string($addressInput)
                ? trim($addressInput)
                : '';

            if ($name === '' || $description === '' || $address === '') {
                $errorMessage = 'Please fill in all fields.';
            } elseif (
                mb_strlen($name) > 150 ||
                mb_strlen($address) > 255
            ) {
                $errorMessage = 'The place name or address is too long.';
            } else {
                try {
                    $placeModel->create(
                        $name,
                        $description,
                        $address
                    );

                    header('Location: places.php?added=1');
                    exit;
                } catch (PDOException $e) {
                    error_log($e->getMessage());
                    $errorMessage =
                        'Unable to add the place. Please try again.';
                }
            }
        } else {
            $errorMessage = 'Unknown action.';
        }
    }
}

if (isset($_GET['added'])) {
    $successMessage = 'Place added successfully!';
} elseif (isset($_GET['restored'])) {
    $successMessage = 'Place restored successfully!';
} elseif (isset($_GET['deleted'])) {
    $successMessage = 'Place deactivated successfully!';
}

if (isset($_GET['updated'])) {
    $successMessage = 'Place updated successfully.';
}

if (isset($_GET['error'])) {
    $messages = [
        'invalid_id' => 'Invalid place ID.',
        'not_found' => 'The place was not found.',
        'delete_failed' =>
            'The place could not be deactivated. Please try again.'
    ];

    $error = $_GET['error'];
    $errorMessage = $messages[$error]
        ?? 'An unexpected error occurred.';
}

$places = $placeModel->getAll('', '', true);

$activeCount   = 0;
$inactiveCount = 0;
foreach ($places as $p) {
    if ((int) $p['is_active'] === 1) {
        $activeCount++;
    } else {
        $inactiveCount++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Places | Hangout Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="admin-body">

<a class="skip-link" href="#main">Skip to content</a>

<header class="site-header">
    <div class="site-header__inner">
        <div class="brand">
            <a href="index.php" class="brand__name">Hangout <span class="brand__badge">Admin</span></a>
            <p class="brand__tagline">Manage places and reviews.</p>
        </div>

        <nav class="admin-nav" aria-label="Admin sections">
            <a href="index.php" class="admin-nav__link">Dashboard</a>
            <a href="places.php" class="admin-nav__link is-active" aria-current="page">Places</a>
            <a href="reviews.php" class="admin-nav__link">Reviews</a>
            <a href="users.php" class="admin-nav__link">Users</a>
            <a href="../index.php" class="btn btn--ghost">View site</a>
        </nav>
    </div>
</header>

<main id="main" class="container">

    <header class="page-header">
        <div>
            <h1>Manage Places</h1>
            <p class="page-header__subtitle">
                Add new locations, deactivate places temporarily, or restore them.
            </p>
        </div>

        <ul class="stat-chips" role="list">
            <li class="stat-chip">
                <span class="stat-chip__value"><?= count($places) ?></span>
                <span class="stat-chip__label">Total</span>
            </li>
            <li class="stat-chip stat-chip--success">
                <span class="stat-chip__value"><?= $activeCount ?></span>
                <span class="stat-chip__label">Active</span>
            </li>
            <li class="stat-chip stat-chip--muted">
                <span class="stat-chip__value"><?= $inactiveCount ?></span>
                <span class="stat-chip__label">Inactive</span>
            </li>
        </ul>
    </header>

    <?php if ($successMessage !== ''): ?>
        <div class="alert alert--success" role="status">
            <?= escape($successMessage) ?>
        </div>
    <?php endif; ?>

    <?php if ($errorMessage !== ''): ?>
        <div class="alert alert--error" role="alert">
            <?= escape($errorMessage) ?>
        </div>
    <?php endif; ?>

    <section class="panel" aria-labelledby="add-place-heading">
        <header class="panel__header">
            <h2 id="add-place-heading">Add a New Place</h2>
            <p class="panel__hint">All fields are required.</p>
        </header>

        <form method="POST" action="places.php" class="admin-form">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= escape($_SESSION['csrf_token']) ?>"
            >
            <input type="hidden" name="action" value="add">

            <div class="field">
                <label for="name">Place Name</label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    maxlength="150"
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
                    required
                >
            </div>

            <div class="field">
                <label for="description">Description</label>
                <textarea
                    id="description"
                    name="description"
                    rows="4"
                    required
                ></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn--solid">Add Place</button>
            </div>
        </form>
    </section>

    <section class="panel" aria-labelledby="all-places-heading">
        <header class="panel__header">
            <h2 id="all-places-heading">All Places</h2>
        </header>

        <?php if (empty($places)): ?>
            <div class="empty-state">
                <h3>No places yet</h3>
                <p>Use the form above to add the first place.</p>
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th scope="col">ID</th>
                            <th scope="col">Name</th>
                            <th scope="col">Address</th>
                            <th scope="col">Tags</th>
                            <th scope="col">Status</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($places as $place): ?>
                            <?php $isActive = (int) $place['is_active'] === 1; ?>
                            <tr class="<?= $isActive ? '' : 'is-muted' ?>">
                                <td class="data-table__id"><?= (int) $place['id'] ?></td>

                                <td><strong><?= escape($place['name']) ?></strong></td>

                                <td><?= escape($place['address'] ?? '') ?></td>

                                <td>
                                    <?php
                                    $tagString = trim((string) ($place['tags'] ?? ''));
                                    $tagParts  = $tagString !== ''
                                        ? array_filter(array_map('trim', explode(',', $tagString)))
                                        : [];
                                    ?>
                                    <?php if ($tagParts): ?>
                                        <div class="tag-list">
                                            <?php foreach ($tagParts as $t): ?>
                                                <span class="tag"><?= escape($t) ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <span class="status-pill status-pill--<?= $isActive ? 'active' : 'inactive' ?>">
                                        <?= $isActive ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>

                                <td class="data-table__actions">
                                    <?php if ($isActive): ?>
                                        <a
                                            href="edit-place.php?id=<?= (int) $place['id'] ?>"
                                            class="btn btn--sm btn--ghost"
                                        >Edit</a>

                                        <form
                                            method="POST"
                                            action="delete-place.php"
                                            class="inline-form"
                                            onsubmit="return confirm('Deactivate this place? It will be hidden from public visitors and can be restored later.');"
                                        >
                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?= (int) $place['id'] ?>"
                                            >
                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= escape($_SESSION['csrf_token']) ?>"
                                            >
                                            <button type="submit" class="btn btn--sm btn--danger">
                                                Deactivate
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form
                                            method="POST"
                                            action="places.php"
                                            class="inline-form"
                                            onsubmit="return confirm('Restore this place and make it visible to public visitors again?');"
                                        >
                                            <input type="hidden" name="action" value="restore">
                                            <input type="hidden" name="id" value="<?= (int) $place['id'] ?>">
                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= escape($_SESSION['csrf_token']) ?>"
                                            >
                                            <button type="submit" class="btn btn--sm btn--solid">
                                                Restore
                                            </button>
                                        </form>
                                    <?php endif; ?>
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
