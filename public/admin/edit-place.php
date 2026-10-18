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

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    http_response_code(400);
    exit('Invalid place ID.');
}

$placeModel = new Place($pdo);

$place = $placeModel->getById($id, true);
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

    $latInput = $_POST['latitude']  ?? '';
    $lngInput = $_POST['longitude'] ?? '';

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

    $imageUrlInput  = $_POST['image_url'] ?? '';
    $removeImage    = isset($_POST['remove_image']);
    $existingImage  = $place['image_url'] ?? null;

    $imageUrl = $existingImage;

    if ($removeImage) {
        $imageUrl = null;
    } elseif (is_string($imageUrlInput) && trim($imageUrlInput) !== '') {
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

    if ($errorMessage === '') {
        if ($name === '' || $description === '' || $address === '') {
            $errorMessage = 'Please fill in all required fields.';
        } elseif (mb_strlen($name) > 150 || mb_strlen($address) > 255) {
            $errorMessage = 'Name or address is too long.';
        }
    }

    if ($errorMessage === '') {
        try {
            $placeModel->update(
                $id,
                $name,
                $description,
                $address,
                $latitude,
                $longitude,
                $imageUrl
            );

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

    $place['name']        = $name;
    $place['description'] = $description;
    $place['address']     = $address;
    $place['latitude']    = $latitude;
    $place['longitude']   = $longitude;
    $place['image_url']   = $imageUrl;
    $currentTagIds        = $submittedTags ?? [];
}

$CAMPUS_CENTER = ['lat' => 14.490516, 'lng' => 121.055372];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Place | Hangout Admin</title>

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

            <fieldset class="image-fieldset">
                <legend>Image <span class="label-hint">(optional)</span></legend>

                <?php if (!empty($place['image_url'])): ?>
                    <div class="current-image">
                        <img
                            src="<?= escapeValue($place['image_url']) ?>"
                            alt="Current image for <?= escapeValue($place['name']) ?>"
                            class="current-image__img"
                            loading="lazy"
                        >
                        <label class="current-image__remove">
                            <input
                                type="checkbox"
                                name="remove_image"
                                value="1"
                            >
                            Remove this image
                        </label>
                    </div>
                <?php endif; ?>

                <div class="field">
                    <label for="image_url">
                        <?= !empty($place['image_url']) ? 'Replace with a new URL' : 'Image URL' ?>
                    </label>
                    <input
                        type="url"
                        id="image_url"
                        name="image_url"
                        maxlength="500"
                        value="<?= escapeValue($_POST['image_url'] ?? '') ?>"
                        placeholder="https://example.com/photo.jpg"
                    >
                    <span class="field-hint">
                        Paste a direct image link (Unsplash, Imgur, etc.).
                        Leave blank to keep the current image.
                    </span>
                </div>
            </fieldset>

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

            <fieldset class="coords-fieldset">
                <legend>Location on the map <span class="label-hint">(optional)</span></legend>
                <p class="field-hint">
                    Click the map to move the pin, drag it, or type coordinates.
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
                            value="<?= $place['latitude'] !== null ? escapeValue((string) $place['latitude']) : '' ?>"
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
                            value="<?= $place['longitude'] !== null ? escapeValue((string) $place['longitude']) : '' ?>"
                            placeholder="e.g. 121.055372"
                        >
                    </div>
                </div>

                <div id="edit-place-map" class="places-map places-map--compact"></div>
            </fieldset>

            <div class="form-actions">
                <button type="submit" class="btn btn--solid">Save Changes</button>
                <a href="places.php" class="btn btn--text">Cancel</a>
            </div>
        </form>
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
    const mapEl = document.getElementById('edit-place-map');
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
</html>