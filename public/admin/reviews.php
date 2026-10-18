
<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../../app/helpers/auth.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/models/Review.php';

requireAdmin();

$reviewModel = new Review($pdo);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function escapeReviewPage($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

$message = '';
$error = '';

if (isset($_GET['status'])) {
    if ($_GET['status'] === 'deactivated') {
        $message = 'Review deactivated successfully.';
    } elseif ($_GET['status'] === 'restored') {
        $message = 'Review restored successfully.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';
    $reviewId = filter_var(
        $_POST['review_id'] ?? null,
        FILTER_VALIDATE_INT
    );

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        $error = 'Invalid security token. Please refresh the page.';
    } elseif (!$reviewId || $reviewId < 1) {
        $error = 'Invalid review ID.';
    } elseif (!in_array($action, ['deactivate', 'restore'], true)) {
        $error = 'Invalid action.';
    } else {
        try {
            if ($action === 'deactivate') {
                $changed = $reviewModel->delete($reviewId);
                $status = 'deactivated';
            } else {
                $changed = $reviewModel->restore($reviewId);
                $status = 'restored';
            }

            if ($changed) {
                header(
                    'Location: reviews.php?status=' . $status
                );
                exit;
            }

            $error = 'No change was made. The review may already have that status.';
        } catch (PDOException $e) {
            error_log('Review moderation error: ' . $e->getMessage());
            $error = 'Unable to update the review. Please try again.';
        }
    }
}

$search = trim($_GET['search'] ?? '');

$placeIdInput = filter_input(
    INPUT_GET,
    'place_id',
    FILTER_VALIDATE_INT
);

$placeId = (
    $placeIdInput !== false &&
    $placeIdInput !== null &&
    $placeIdInput > 0
) ? $placeIdInput : null;

$status = $_GET['status_filter'] ?? 'all';

if (!in_array($status, ['all', 'active', 'inactive'], true)) {
    $status = 'all';
}

$ratingInput = filter_input(
    INPUT_GET,
    'rating',
    FILTER_VALIDATE_INT
);

$rating = (
    $ratingInput !== false &&
    $ratingInput !== null &&
    $ratingInput >= 1 &&
    $ratingInput <= 5
) ? $ratingInput : null;

$places = $reviewModel->getPlacesWithReviews();

$reviews = $reviewModel->getAll(
    $search,
    $placeId,
    $status,
    $rating
);

$hasFilters = $search !== '' || $placeId !== null || $status !== 'all' || $rating !== null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Review Moderation | Hangout Admin</title>
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
           <a href="index.php" class="admin-nav__link is-active" aria-current="page">Dashboard</a>
            <a href="places.php" class="admin-nav__link">Places</a>
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
            <h1>Review Moderation</h1>
            <p class="page-header__subtitle">
                Inspect reviews submitted by users. Deactivated reviews remain in the database and can be restored.
            </p>
        </div>
    </header>

    <?php if ($message !== ''): ?>
        <div class="alert alert--success" role="status">
            <?= escapeReviewPage($message) ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="alert alert--error" role="alert">
            <?= escapeReviewPage($error) ?>
        </div>
    <?php endif; ?>

    <section class="panel" aria-labelledby="filters-heading">
        <header class="panel__header">
            <h2 id="filters-heading">Filters</h2>
        </header>

        <form method="GET" action="reviews.php" class="filters-grid">
            <div class="field">
                <label for="search">Search reviews</label>
                <input
                    type="search"
                    id="search"
                    name="search"
                    placeholder="Username, place, or comment…"
                    value="<?= escapeReviewPage($search) ?>"
                >
            </div>

            <div class="field">
                <label for="place_id">Filter by place</label>
                <select id="place_id" name="place_id">
                    <option value="">All places</option>
                    <?php foreach ($places as $place): ?>
                        <option
                            value="<?= (int) $place['id'] ?>"
                            <?= $placeId === (int) $place['id'] ? 'selected' : '' ?>
                        >
                            <?= escapeReviewPage($place['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="status_filter">Review status</label>
                <select id="status_filter" name="status_filter">
                    <option value="all"     <?= $status === 'all'      ? 'selected' : '' ?>>All reviews</option>
                    <option value="active"  <?= $status === 'active'   ? 'selected' : '' ?>>Active only</option>
                    <option value="inactive"<?= $status === 'inactive' ? 'selected' : '' ?>>Inactive only</option>
                </select>
            </div>

            <div class="field">
                <label for="rating">Star rating</label>
                <select id="rating" name="rating">
                    <option value="">All ratings</option>
                    <?php for ($star = 5; $star >= 1; $star--): ?>
                        <option
                            value="<?= $star ?>"
                            <?= $rating === $star ? 'selected' : '' ?>
                        >
                            <?= $star ?> star<?= $star === 1 ? '' : 's' ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="filters-grid__actions">
                <button type="submit" class="btn btn--solid">Apply filters</button>
                <?php if ($hasFilters): ?>
                    <a href="reviews.php" class="btn btn--text">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <section class="panel" aria-labelledby="results-heading">
        <header class="panel__header panel__header--row">
            <h2 id="results-heading">Reviews</h2>
            <p class="results-count">
                <strong><?= count($reviews) ?></strong>
                <?= count($reviews) === 1 ? 'review' : 'reviews' ?>
                found
            </p>
        </header>

        <?php if (empty($reviews)): ?>
            <div class="empty-state">
                <h3>No reviews match</h3>
                <p>Try clearing your filters or adjusting your search.</p>
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table class="data-table data-table--reviews">
                    <thead>
                        <tr>
                            <th scope="col">ID</th>
                            <th scope="col">Place</th>
                            <th scope="col">Reviewer</th>
                            <th scope="col">Rating</th>
                            <th scope="col">Comment</th>
                            <th scope="col">Date</th>
                            <th scope="col">Status</th>
                            <th scope="col">Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($reviews as $review): ?>
                            <?php $isActive = (int) $review['is_active'] === 1; ?>
                            <tr class="<?= $isActive ? '' : 'is-muted' ?>">
                                <td class="data-table__id"><?= (int) $review['id'] ?></td>

                                <td><?= escapeReviewPage($review['place_name']) ?></td>

                                <td><?= escapeReviewPage($review['username']) ?></td>

                                <td>
                                    <span class="rating-badge" aria-label="<?= (int) $review['rating'] ?> out of 5">
                                        <?= (int) $review['rating'] ?> / 5
                                    </span>
                                </td>

                                <td class="data-table__comment">
                                    <?= escapeReviewPage($review['comment'] ?: '—') ?>
                                </td>

                                <td class="data-table__date">
                                    <?= escapeReviewPage($review['created_at']) ?>
                                </td>

                                <td>
                                    <span class="status-pill status-pill--<?= $isActive ? 'active' : 'inactive' ?>">
                                        <?= $isActive ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>

                                <td>
                                    <form
                                        method="POST"
                                        action="reviews.php"
                                        class="inline-form"
                                        onsubmit="return confirm('Are you sure you want to <?= $isActive ? 'deactivate' : 'restore' ?> this review?');"
                                    >
                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= escapeReviewPage($_SESSION['csrf_token']) ?>"
                                        >
                                        <input
                                            type="hidden"
                                            name="review_id"
                                            value="<?= (int) $review['id'] ?>"
                                        >
                                        <input
                                            type="hidden"
                                            name="action"
                                            value="<?= $isActive ? 'deactivate' : 'restore' ?>"
                                        >
                                        <button
                                            type="submit"
                                            class="btn btn--sm <?= $isActive ? 'btn--danger' : 'btn--solid' ?>"
                                        >
                                            <?= $isActive ? 'Deactivate' : 'Restore' ?>
                                        </button>
                                    </form>
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