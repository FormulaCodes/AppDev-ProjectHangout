
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
                $reviewMessage = 'Please select a valid rating.';
                $reviewMessageType = 'error';
            }
        }
    }
}

if (isset($_GET['review']) && $_GET['review'] === 'success') {
    $reviewMessage = 'Your review has been submitted successfully!';
    $reviewMessageType = 'success';     }

if (isset($_SESSION['user_id'])) {
    $userHasReviewed = $reviewModel->hasUserReviewed(
        (int) $_SESSION['user_id'],
        $id
    );
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
        
        <section class="reviews-section">
            <h2>Ratings and Reviews</h2>

            <div class="review-summary">
                <?php if ($summary['total_reviews'] > 0): ?>
                    <p>
                        <strong>
                            <?= number_format($summary['average_rating'], 1) ?>
                            / 5
                        </strong>
                        based on
                        <?= $summary['total_reviews'] ?>
                        <?= $summary['total_reviews'] === 1
                            ? 'review'
                            : 'reviews' ?>
                    </p>
                <?php else: ?>
                    <p>No reviews yet. Be the first to share your experience!</p>
                <?php endif; ?>
            </div>

            <?php if (empty($reviews)): ?>
                <p>There are no comments for this place yet.</p>
            <?php else: ?>
                <div class="reviews-list">
                    <?php foreach ($reviews as $review): ?>
                        <article class="review-card">
                            <div class="review-card-header">
                                <strong>
                                    <?= escape($review['username']) ?>
                                </strong>

                                <span>
                                    <?= (int) $review['rating'] ?> / 5
                                </span>
                            </div>

                            <?php if (
                                $review['comment'] !== null &&
                                trim($review['comment']) !== ''
                            ): ?>
                                <p>
                                    <?= nl2br(escape($review['comment'])) ?>
                                </p>
                            <?php else: ?>
                                <p class="review-no-comment">
                                    No written comment.
                                </p>
                            <?php endif; ?>

                            <small>
                                <?= escape($review['created_at']) ?>
                            </small>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
                <div class="review-form-section">
                    <h3>Share Your Experience</h3>

                    <?php if ($reviewMessage !== ''): ?>
                        <p role="status" class="review-message">
                            <?= escape($reviewMessage) ?>
                        </p>
                    <?php endif; ?>

                    <?php if (!isset($_SESSION['user_id'])): ?>
                        <p>
                            Please <a href="login.php">log in</a>
                            to leave a review.
                        </p>

                    <?php elseif ($userHasReviewed): ?>
                        <p>You have already reviewed this place. Thanks for sharing!</p>

                    <?php else: ?>
                        <form method="POST" action="place.php?id=<?= $id ?>"
                            class="search-form">

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= escape($_SESSION['csrf_token']) ?>"
                            >

                            <label>Rating</label>

                            <?php for ($score = 1; $score <= 5; $score++): ?>
                                <label>
                                    <input
                                        type="radio"
                                        name="rating"
                                        value="<?= $score ?>"
                                        required
                                    >
                                    <?= $score ?> / 5
                                </label>
                            <?php endfor; ?>

                            <label for="comment">Comment (optional)</label>
                            <textarea
                                id="comment"
                                name="comment"
                                maxlength="2000"
                                rows="4"
                                placeholder="What should other people know about this place?"
                            ></textarea>

                            <button type="submit">Submit Review</button>
                        </form>
                    <?php endif; ?>
                </div>

        </section>

    </main>

</body>
</html>
