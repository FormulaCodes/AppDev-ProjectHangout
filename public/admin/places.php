<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../../app/helpers/auth.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/models/Place.php';

requireAdmin();

$placeModel = new Place($pdo);
$allTags = $placeModel->getAllTags();

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
            // ---- Read inputs ----
            $nameInput        = $_POST['name'] ?? '';
            $descriptionInput = $_POST['description'] ?? '';
            $addressInput     = $_POST['address'] ?? '';
            $latInput         = $_POST['latitude'] ?? '';
            $lngInput         = $_POST['longitude'] ?? '';
            $imageUrlInput    = $_POST['image_url'] ?? '';

            $name        = is_string($nameInput) ? trim($nameInput) : '';
            $description = is_string($descriptionInput) ? trim($descriptionInput) : '';
            $address     = is_string($addressInput) ? trim($addressInput) : '';

            // ---- Coordinates (optional) ----
            $latitude  = null;
            $longitude = null;

            if (is_string($latInput) && $latInput !== '' && is_numeric($latInput)) {
                $latitude = (float) $latInput;
                if ($latitude < -90 || $latitude > 90) {
                    $errorMessage = 'Latitude must be between -90 and 90.';
                }
            }

            if ($errorMessage === '' && is_string($lngInput) && $lngInput !== '' && is_numeric($lngInput)) {
                $longitude = (float) $lngInput;
                if ($longitude < -180 || $longitude > 180) {
                    $errorMessage = 'Longitude must be between -180 and 180.';
                }
            }

            // ---- Image URL (optional, must be http/https) ----
            $imageUrl = null;
            if ($errorMessage === '' && is_string($imageUrlInput) && $imageUrlInput !== '') {
                $trimmed = trim($imageUrlInput);
                if (strlen($trimmed) > 500) {
                    $errorMessage = 'Image URL is too long.';
                } elseif (!filter_var($trimmed, FILTER_VALIDATE_URL)) {
                    $errorMessage = 'Image URL must be a valid URL.';
                } elseif (!preg_match('#^https?://#i', $trimmed)) {
                    $errorMessage = 'Image URL must start with http:// or https://.';
                } else {
                    $imageUrl = $trimmed;
                }
            }

            // ---- Required fields ----
            if ($errorMessage === '') {
                if ($name === '' || $description === '' || $address === '') {
                    $errorMessage = 'Please fill in all required fields.';
                } elseif (mb_strlen($name) > 150 || mb_strlen($address) > 255) {
                    $errorMessage = 'The place name or address is too long.';
                }
            }

            // ---- Create only on full success ----
            if ($errorMessage === '') {
                try {
                    $newId = $placeModel->create(
                        $name,
                        $description,
                        $address,
                        $latitude,
                        $longitude,
                        $imageUrl
                    );

                    $tagIds = $_POST['tags'] ?? [];
                    if (!is_array($tagIds)) {
                        $tagIds = [];
                    }
                    $tagIds = array_filter(
                        array_map('intval', $tagIds),
                        fn($tid) => $tid > 0
                    );

                    $placeModel->setTags($newId, $tagIds);

                    header('Location: places.php?added=1');
                    exit;
                } catch (PDOException $e) {
                    error_log($e->getMessage());
                    $errorMessage = 'Unable to add the place. Please try again.';
                }
            }
        } else {
            $errorMessage = 'Unknown action.';
        }
    }
}

if (isset($_GET['added']))    $successMessage = 'Place added successfully!';
if (isset($_GET['restored'])) $successMessage = 'Place restored successfully!';
if (isset($_GET['deleted']))  $successMessage = 'Place deactivated successfully!';
if (isset($_GET['updated']))  $successMessage = 'Place updated successfully.';

if (isset($_GET['error'])) {
    $messages = [
        'invalid_id'    => 'Invalid place ID.',
        'not_found'     => 'The place was not found.',
        'delete_failed' => 'The place could not be deactivated. Please try again.',
    ];
    $error = $_GET['error'];
    $errorMessage = $messages[$error] ?? 'An unexpected error occurred.';
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

$CAMPUS_CENTER = ['lat' => 14.490516, 'lng' => 121.055372];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Places | Hangout Admin</title>

    <link
        rel="stylesheet"
        href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
        crossorigin=""
    >
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
            <p class="panel__hint">
                Required: name, address, description.
                Optional: tags, image, coordinates.
            </p>
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

            <div class="field">
                <label for="image_url">Image URL <span class="label-hint">(optional)</span></label>
                <input
                    type="url"
                    id="image_url"
                    name="image_url"
                    maxlength="500"
                    placeholder="https://example.com/photo.jpg"
                >
                <span class="field-hint">
                    Paste a direct link to an image (Unsplash, Imgur, etc.).
                </span>
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
                                >
                                <span><?= escape($tag['name']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </fieldset>

            <fieldset class="coords-fieldset">
                <legend>Location on the map <span class="label-hint">(optional)</span></legend>
                <p class="field-hint">
                    Click the map to drop a pin, or type coordinates manually.
                </p>

                <div class="coords-row">
                    <div class="field">
                        <label for="latitude">Latitude</label>
                        <input
                            type="number"
                            id="latitude"
                            name="latitude"
                            step="any"
                            min="-90"
                            max="90"
                            placeholder="e.g. 14.490516"
                        >
                    </div>
                    <div class="field">
                        <label for="longitude">Longitude</label>
                        <input
                            type="number"
                            id="longitude"
                            name="longitude"
                            step="any"
                            min="-180"
                            max="180"
                            placeholder="e.g. 121.055372"
                        >
                    </div>
                </div>

                <div id="add-place-map" class="places-map places-map--compact"></div>
            </fieldset>

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
                            <th scope="col"><span class="visually-hidden">Thumbnail</span></th>
                            <th scope="col">ID</th>
                            <th scope="col">Name</th>
                            <th scope="col">Address</th>
                            <th scope="col">Tags</th>
                            <th scope="col">Coords</th>
                            <th scope="col">Status</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($places as $place): ?>
                            <?php $isActive = (int) $place['is_active'] === 1; ?>
                            <tr class="<?= $isActive ? '' : 'is-muted' ?>">
                                <td>
                                    <?php if (!empty($place['image_url'])): ?>
                                        <img
                                            src="<?= escape($place['image_url']) ?>"
                                            alt=""
                                            class="table-thumb"
                                            loading="lazy"
                                        >
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>

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

                                <td class="data-table__id">
                                    <?php if ($place['latitude'] !== null && $place['longitude'] !== null): ?>
                                        <span title="<?= escape((string) $place['latitude']) ?>, <?= escape((string) $place['longitude']) ?>">
                                            <?= escape(number_format((float) $place['latitude'], 4)) ?>,
                                            <?= escape(number_format((float) $place['longitude'], 4)) ?>
                                        </span>
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
                                            <input type="hidden" name="id" value="<?= (int) $place['id'] ?>">
                                            <input type="hidden" name="csrf_token" value="<?= escape($_SESSION['csrf_token']) ?>">
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
                                            <input type="hidden" name="csrf_token" value="<?= escape($_SESSION['csrf_token']) ?>">
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

<script
    src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
    integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
    crossorigin=""
></script>
<script>
(function () {
    const CAMPUS = [<?= (float) $CAMPUS_CENTER['lat'] ?>, <?= (float) $CAMPUS_CENTER['lng'] ?>];
    const mapEl = document.getElementById('add-place-map');
    const latInput = document.getElementById('latitude');
    const lngInput = document.getElementById('longitude');
    if (!mapEl || !latInput || !lngInput) return;

    const map = L.map(mapEl).setView(CAMPUS, 15);

    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap contributors</a>'
    }).addTo(map);

    let marker = null;

    function setMarker(lat, lng, moveView) {
        const latFixed = parseFloat(lat).toFixed(6);
        const lngFixed = parseFloat(lng).toFixed(6);

        if (marker) {
            marker.setLatLng([latFixed, lngFixed]);
        } else {
            marker = L.marker([latFixed, lngFixed], { draggable: true }).addTo(map);
            marker.on('dragend', () => {
                const p = marker.getLatLng();
                syncInputs(p.lat, p.lng);
            });
        }

        syncInputs(latFixed, lngFixed);

        if (moveView) {
            map.setView([latFixed, lngFixed], Math.max(map.getZoom(), 16));
        }
    }

    function syncInputs(lat, lng) {
        latInput.value = parseFloat(lat).toFixed(6);
        lngInput.value = parseFloat(lng).toFixed(6);
    }

    if (latInput.value && lngInput.value) {
        const lat = parseFloat(latInput.value);
        const lng = parseFloat(lngInput.value);
        if (!isNaN(lat) && !isNaN(lng)) {
            setMarker(lat, lng, true);
        }
    }

    map.on('click', (e) => {
        setMarker(e.latlng.lat, e.latlng.lng, false);
    });

    function onInputChange() {
        const lat = parseFloat(latInput.value);
        const lng = parseFloat(lngInput.value);
        if (!isNaN(lat) && !isNaN(lng)
            && lat >= -90 && lat <= 90
            && lng >= -180 && lng <= 180) {
            setMarker(lat, lng, false);
        }
    }
    latInput.addEventListener('change', onInputChange);
    lngInput.addEventListener('change', onInputChange);
})();
</script>

</body>
</html>s