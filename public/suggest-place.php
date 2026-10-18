<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/models/PlaceSuggestion.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$success = '';

$lat = filter_input(INPUT_GET, 'lat', FILTER_VALIDATE_FLOAT);
$lng = filter_input(INPUT_GET, 'lng', FILTER_VALIDATE_FLOAT);

if ($lat === false || $lat === null || $lat < -90 || $lat > 90) $lat = null;
if ($lng === false || $lng === null || $lng < -180 || $lng > 180) $lng = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        $error = 'Invalid form submission. Please refresh and try again.';
    } else {
       
        $postLat = filter_input(INPUT_POST, 'latitude', FILTER_VALIDATE_FLOAT);
        $postLng = filter_input(INPUT_POST, 'longitude', FILTER_VALIDATE_FLOAT);

        $name        = trim((string) ($_POST['name'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $address     = trim((string) ($_POST['address'] ?? ''));

        if ($name === '' || $description === '' || $address === '') {
            $error = 'Please fill in all fields.';
        } elseif ($postLat === false || $postLat === null
               || $postLat < -90  || $postLat > 90
               || $postLng === false || $postLng === null
               || $postLng < -180 || $postLng > 180) {
            $error = 'Please pick a valid location on the map.';
        } else {
            try {
                $model = new PlaceSuggestion($pdo);
                $model->create(
                    (int) $_SESSION['user_id'],
                    $name, $description, $address,
                    (float) $postLat, (float) $postLng
                );
                $success = 'Thanks! Your suggestion is now pending admin review.';
            } catch (Throwable $e) {
                error_log('Suggestion error: ' . $e->getMessage());
                $error = 'Could not submit your suggestion. Please try again.';
            }
        }
    }
}

function escape(string $v): string {
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Suggest a Place | Hangout</title>
    <link rel="stylesheet"
          href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
          crossorigin="">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<a class="skip-link" href="#main">Skip to content</a>

<header class="site-header">
    <div class="site-header__inner">
        <div class="brand">
            <a href="index.php" class="brand__name">Hangout</a>
            <p class="brand__tagline">Suggest a place for the directory.</p>
        </div>
        <nav class="auth-nav" aria-label="Account">
            <a class="btn btn--ghost" href="index.php">Back to Explore</a>
        </nav>
    </div>
</header>

<main id="main" class="container">

    <header class="page-header">
        <h1>Suggest a Place</h1>
        <p class="page-header__subtitle">
            Click the map to pick a location, then fill in the details.
            Suggestions are reviewed by an admin before they go live.
        </p>
    </header>

    <?php if ($success !== ''): ?>
        <div class="alert alert--success" role="status"><?= escape($success) ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="alert alert--error" role="alert"><?= escape($error) ?></div>
    <?php endif; ?>

    <section class="panel">
        <h2>1. Pick a location</h2>
        <div id="suggest-map" class="places-map" style="height: 400px;"></div>
        <p id="picked-coords" class="text-muted" style="margin-top: 0.5rem;">
            Click the map to drop a marker.
        </p>
    </section>

    <?php if ($success === ''): ?>
    <section class="panel">
        <h2>2. Describe the place</h2>
        <form method="POST" action="suggest-place.php" class="admin-form">
            <input type="hidden" name="csrf_token"
                   value="<?= escape($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="latitude"  id="f-lat"
                   value="<?= $lat !== null ? escape((string)$lat) : '' ?>">
            <input type="hidden" name="longitude" id="f-lng"
                   value="<?= $lng !== null ? escape((string)$lng) : '' ?>">

            <div class="field">
                <label for="name">Place name</label>
                <input type="text" id="name" name="name" maxlength="150" required>
            </div>

            <div class="field">
                <label for="address">Address</label>
                <input type="text" id="address" name="address" maxlength="255" required>
            </div>

            <div class="field">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="4" required></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn--solid">Submit for review</button>
            </div>
        </form>
    </section>
    <?php endif; ?>

</main>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
        crossorigin=""></script>
<script>
(function () {
    const CAMPUS = [43.653226, -79.383184]; // change to your campus
    const mapEl = document.getElementById('suggest-map');
    const latInput = document.getElementById('f-lat');
    const lngInput = document.getElementById('f-lng');
    const coordsLabel = document.getElementById('picked-coords');

    const map = L.map(mapEl).setView(CAMPUS, 14);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap contributors</a>'
    }).addTo(map);

    let marker = null;

    const initialLat = parseFloat(latInput.value);
    const initialLng = parseFloat(lngInput.value);
    if (!isNaN(initialLat) && !isNaN(initialLng)) {
        placeMarker(initialLat, initialLng);
        map.setView([initialLat, initialLng], 16);
    }

    map.on('click', (e) => {
        placeMarker(e.latlng.lat, e.latlng.lng);
    });

    function placeMarker(lat, lng) {
        if (marker) {
            marker.setLatLng([lat, lng]);
        } else {
            marker = L.marker([lat, lng], { draggable: true }).addTo(map);
            marker.on('dragend', () => {
                const p = marker.getLatLng();
                updateCoords(p.lat, p.lng);
            });
        }
        updateCoords(lat, lng);
    }

    function updateCoords(lat, lng) {
        const latFixed = lat.toFixed(6);
        const lngFixed = lng.toFixed(6);
        latInput.value = latFixed;
        lngInput.value = lngFixed;
        coordsLabel.textContent = 'Selected: ' + latFixed + ', ' + lngFixed;
    }
})();
</script>
</body>
</html>