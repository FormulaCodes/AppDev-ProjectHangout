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


$mapPlaces = [];

foreach ($places as $place) {
    $lat = $place['latitude'] ?? null;
    $lng = $place['longitude'] ?? null;

    if (!is_numeric($lat) || !is_numeric($lng)) {
        continue;
    }

    $lat = (float) $lat;
    $lng = (float) $lng;

    if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        continue;
    }

    $mapPlaces[] = [
        'id' => (int) $place['id'],
        'name' => (string) $place['name'],
        'address' => (string) $place['address'],
        'latitude' => $lat,
        'longitude' => $lng
    ];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hangout — Discover Your Next Spot</title>
    <meta name="description" content="Discover places around your campus.">
    <link rel="stylesheet" href="assets/css/style.css">
    <link
    rel="stylesheet"
    href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
    crossorigin=""
>
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

        <section class="map-section" aria-labelledby="map-heading">
            <h2 id="map-heading">Explore on the map</h2>

            
                <div
                        id="places-map"
                        class="places-map"
                        role="region"
                        aria-label="Map of places matching your filters"
                    ></div>
                <p class="map-attribution-note">
                    Map data ©
                    <a href="https://www.openstreetmap.org/copyright"
                    target="_blank"
                    rel="noopener noreferrer">
                        OpenStreetMap contributors
                    </a>
                </p>
            <?php if (empty($mapPlaces)): ?>
                <p>
                    No places with coordinates match these filters.
                    You can still explore the places listed below.
                </p>
            <?php endif; ?>
        </section>

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

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function () {
    const places = <?= json_encode(
        $mapPlaces,
        JSON_HEX_TAG |
        JSON_HEX_APOS |
        JSON_HEX_QUOT |
        JSON_HEX_AMP
    ) ?>;

    const mapEl = document.getElementById('places-map');
    if (!mapEl) return;

    const map = L.map(mapEl);

    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap contributors</a>'
    }).addTo(map);

    const markers = [];

    places.forEach(place => {
        const marker = L.marker([
            place.latitude,
            place.longitude
        ]).addTo(map);

        const popup = document.createElement('div');

        const title = document.createElement('strong');
        title.textContent = place.name;
        popup.appendChild(title);

        const address = document.createElement('p');
        address.textContent = place.address;
        popup.appendChild(address);

        const link = document.createElement('a');
        link.textContent = 'View place details';
        link.href = 'place.php?id=' + encodeURIComponent(place.id);
        popup.appendChild(link);

        marker.bindPopup(popup);
        markers.push(marker);
    });

   
    const CAMPUS_CENTER = [14.490516, 121.055372];
    const CAMPUS_ZOOM   = 18;

    if (markers.length === 0) {
        map.setView(CAMPUS_CENTER, CAMPUS_ZOOM);
    } else if (markers.length === 1) {
        map.setView(markers[0].getLatLng(), CAMPUS_ZOOM);
    } else {
        const bounds = L.featureGroup(markers).getBounds();
        map.fitBounds(bounds, { padding: [30, 30], maxZoom: 16 });
    }

    // Always attach click handler — regardless of marker count
    map.on('click', (e) => {
        const lat = e.latlng.lat.toFixed(6);
        const lng = e.latlng.lng.toFixed(6);

        const popup = document.createElement('div');
        popup.innerHTML = `
            <p style="margin:0 0 6px;font-weight:600;">Suggest a place here?</p>
            <p style="margin:0 0 8px;font-size:0.9em;color:#555;">
                ${lat}, ${lng}
            </p>
        `;
        const link = document.createElement('a');
        link.textContent = 'Open suggestion form →';
        link.href = 'suggest-place.php?lat=' + encodeURIComponent(lat)
                  + '&lng=' + encodeURIComponent(lng);
        popup.appendChild(link);

        L.popup()
            .setLatLng(e.latlng)
            .setContent(popup)
            .openOn(map);
    });
})();
</script>

</body>
</html>