<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/models/Place.php';
require_once __DIR__ . '/../app/models/Review.php';

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

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$reviewModel = new Review($pdo);
$reviews = $reviewModel->getByPlaceId($id);
$summary = $reviewModel->getSummary($id);
$userHasReviewed = false;

$reviewMessage = '';
$reviewMessageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }

    $submittedToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($submittedToken) ||
        !hash_equals($_SESSION['csrf_token'], $submittedToken)
    ) {
        $reviewMessage = 'Invalid form submission. Refresh the page and try again.';
        $reviewMessageType = 'error';
    } else {
        $ratingInput = $_POST['rating'] ?? '';
        $commentInput = $_POST['comment'] ?? '';

        $rating = is_string($ratingInput) &&
            ctype_digit($ratingInput)
            ? (int) $ratingInput
            : 0;

        $comment = is_string($commentInput)
            ? trim($commentInput)
            : '';

        if ($rating < 1 || $rating > 5) {
            $reviewMessage = 'Please select a rating from 1 to 5.';
            $reviewMessageType = 'error';
        } elseif (strlen($comment) > 2000) {
            $reviewMessage = 'Your comment must be 2000 characters or fewer.';
            $reviewMessageType = 'error';
        } else {
            try {
                $reviewModel->create(
                    (int) $_SESSION['user_id'],
                    $id,
                    $rating,
                    $comment === '' ? null : $comment
                );

                header(
                    'Location: place.php?id=' . $id . '&review=success'
                );
                exit;
            } catch (PDOException $e) {
                if (
                    isset($e->errorInfo[1]) &&
                    (int) $e->errorInfo[1] === 1062
                ) {
                    $reviewMessage = 'You have already reviewed this place.';
                } else {
                    error_log($e->getMessage());
                    $reviewMessage = 'Could not submit your review. Please try again.';
                }

                $reviewMessageType = 'error';
            } catch (InvalidArgumentException $e) {
                $reviewMessage = $e->getMessage();
                $reviewMessageType = 'error';
            }
        }
    }
}

if (isset($_GET['review']) && $_GET['review'] === 'success') {
    $reviewMessage = 'Your review has been submitted successfully!';
    $reviewMessageType = 'success';
}

if (isset($_SESSION['user_id'])) {
    $userHasReviewed = $reviewModel->hasUserReviewed(
        (int) $_SESSION['user_id'],
        $id
    );
}

$tags = $place['tags'] !== null
    ? explode(', ', $place['tags'])
    : [];

$hasCoords = $place['latitude'] !== null && $place['longitude'] !== null;
$isLoggedIn = isset($_SESSION['user_id']);
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

<a class="skip-link" href="#main">Skip to content</a>

<header class="site-header">
    <div class="site-header__inner">
        <div class="brand">
            <a href="index.php" class="brand__name">Hangout</a>
            <p class="brand__tagline">Discover places around your campus.</p>
        </div>
        <nav class="auth-nav" aria-label="Account">
            <?php if ($isLoggedIn): ?>
                <span class="auth-nav__welcome">
                    Welcome, <?= escape((string) $_SESSION['username']) ?>
                </span>
            <?php else: ?>
                <a class="btn btn--ghost" href="login.php">Login</a>
                <a class="btn btn--solid" href="register.php">Register</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<main id="main" class="container">

    <nav class="breadcrumb" aria-label="Breadcrumb">
        <a href="index.php">← Back to Explore Places</a>
    </nav>

    <article class="place-detail" aria-labelledby="place-title">

        <header class="place-detail__header">
            <h1 id="place-title"><?= escape($place['name']) ?></h1>

            <?php if (!empty($summary['total_reviews']) && $summary['total_reviews'] > 0): ?>
                <p class="place-detail__rating">
                    <span class="rating-stars" aria-hidden="true">★</span>
                    <strong><?= number_format($summary['average_rating'], 1) ?> / 5</strong>
                    <span class="place-detail__rating-count">
                        (<?= (int) $summary['total_reviews'] ?>
                        <?= $summary['total_reviews'] === 1 ? 'review' : 'reviews' ?>)
                    </span>
                </p>
            <?php endif; ?>
        </header>

        <p class="place-detail__description">
            <?= nl2br(escape($place['description'] ?? 'No description available yet.')) ?>
        </p>

        <div class="place-detail__meta">

            <section class="detail-block" aria-labelledby="detail-address">
                <h2 id="detail-address" class="detail-block__label">Address</h2>
                <p><?= escape($place['address']) ?></p>
            </section>

            <section class="detail-block" aria-labelledby="detail-tags">
                <h2 id="detail-tags" class="detail-block__label">Tags</h2>
                <?php if (empty($tags)): ?>
                    <p class="text-muted">No tags available.</p>
                <?php else: ?>
                    <div class="tag-list">
                        <?php foreach ($tags as $tag): ?>
                            <span class="tag"><?= escape($tag) ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="detail-block" aria-labelledby="detail-coords">
                <h2 id="detail-coords" class="detail-block__label">Location</h2>
                <?php if ($hasCoords): ?>
                    <p class="coords">
                        <span><span class="coords__key">Lat:</span> <?= escape((string) $place['latitude']) ?></span>
                        <span><span class="coords__key">Lng:</span> <?= escape((string) $place['longitude']) ?></span>
                    </p>
                <?php else: ?>
                    <p class="text-muted">Location coordinates have not been added yet.</p>
                <?php endif; ?>
            </section>

        </div>
    </article>

    <section class="reviews-section" aria-labelledby="reviews-heading">
        <header class="reviews-section__header">
            <h2 id="reviews-heading">Ratings and Reviews</h2>

            <div class="review-summary">
                <?php if ($summary['total_reviews'] > 0): ?>
                    <p class="review-summary__score">
                        <span class="review-summary__avg"><?= number_format($summary['average_rating'], 1) ?></span>
                        <span class="review-summary__max">/ 5</span>
                    </p>
                    <p class="review-summary__count">
                        <?= (int) $summary['total_reviews'] ?>
                        <?= $summary['total_reviews'] === 1 ? 'review' : 'reviews' ?>
                    </p>
                <?php else: ?>
                    <p class="review-summary__score"><strong>No ratings yet</strong></p>
                    <p class="review-summary__count">Be the first to review this place.</p>
                <?php endif; ?>
            </div>
        </header>

        <?php if (empty($reviews)): ?>
            <div class="empty-state">
                <h3>No reviews yet</h3>
                <p>There are no comments for this place yet.</p>
            </div>
        <?php else: ?>
            <div class="reviews-list">
                <?php foreach ($reviews as $review): ?>
                    <article class="review-card">
                        <header class="review-card-header">
                            <strong class="review-card__author">
                                <?= escape($review['username']) ?>
                            </strong>
                            <span class="review-card__rating" aria-label="<?= (int) $review['rating'] ?> out of 5">
                                <?= (int) $review['rating'] ?> / 5
                            </span>
                        </header>

                        <?php if (
                            $review['comment'] !== null &&
                            trim($review['comment']) !== ''
                        ): ?>
                            <p class="review-card__comment">
                                <?= nl2br(escape($review['comment'])) ?>
                            </p>
                        <?php else: ?>
                            <p class="review-no-comment">No written comment.</p>
                        <?php endif; ?>

                        <footer class="review-card__footer">
                            <small><?= escape($review['created_at']) ?></small>
                        </footer>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="review-form-section" id="review-form">
            <h3>Share Your Experience</h3>

            <?php if ($reviewMessage !== ''): ?>
                <div
                    role="status"
                    class="alert alert--<?= $reviewMessageType === 'success' ? 'success' : 'error' ?>"
                >
                    <?= escape($reviewMessage) ?>
                </div>
            <?php endif; ?>

            <?php if (!$isLoggedIn): ?>
                <p class="text-muted">
                    Please <a href="login.php">log in</a> to leave a review.
                </p>

            <?php elseif ($userHasReviewed): ?>
                <p class="text-muted">
                    You have already reviewed this place. Thanks for sharing!
                </p>

            <?php else: ?>
                <form
                    method="POST"
                    action="place.php?id=<?= (int) $id ?>"
                    class="review-form"
                >
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= escape($_SESSION['csrf_token']) ?>"
                    >

                    <fieldset class="rating-fieldset">
                        <legend>Rating</legend>
                        <div class="rating-input">
                            <?php for ($score = 1; $score <= 5; $score++): ?>
                                <label class="rating-input__option">
                                    <input
                                        type="radio"
                                        name="rating"
                                        value="<?= $score ?>"
                                        required
                                    >
                                    <span class="rating-input__label">
                                        <span class="rating-input__num"><?= $score ?></span>
                                        <span class="rating-input__max">/5</span>
                                    </span>
                                </label>
                            <?php endfor; ?>
                        </div>
                    </fieldset>

                    <div class="field">
                        <label for="comment">Comment <span class="label-hint">(optional)</span></label>
                        <textarea
                            id="comment"
                            name="comment"
                            maxlength="2000"
                            rows="4"
                            placeholder="What should other people know about this place?"
                        ></textarea>
                    </div>

                    <button type="submit" class="btn btn--solid">Submit Review</button>
                </form>
            <?php endif; ?>
        </div>
    </section>

</main>
</body>
</html>
