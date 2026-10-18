<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../../app/helpers/auth.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/models/PlaceSuggestion.php';

requireAdmin();

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$model = new PlaceSuggestion($pdo);
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    $action = $_POST['action'] ?? '';

    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        $error = 'Invalid form submission.';
    } elseif (!$id || $id < 1) {
        $error = 'Invalid suggestion ID.';
    } elseif (!in_array($action, ['approve', 'reject'], true)) {
        $error = 'Invalid action.';
    } else {
        try {
            if ($action === 'approve') {
                $model->approve($id, (int) $_SESSION['user_id']);
                $message = 'Suggestion approved and published.';
            } else {
                $model->reject($id, (int) $_SESSION['user_id']);
                $message = 'Suggestion rejected.';
            }
        } catch (Throwable $e) {
            error_log('Suggestion moderation error: ' . $e->getMessage());
            $error = 'Could not update the suggestion.';
        }
    }
}

$statusFilter = $_GET['status'] ?? 'pending';
if (!in_array($statusFilter, ['pending','approved','rejected'], true)) {
    $statusFilter = 'pending';
}
$suggestions = $model->getAll($statusFilter);

function escape(string $v): string {
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Place Suggestions | Hangout Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="admin-body">

<header class="site-header">
    <div class="site-header__inner">
        <div class="brand">
            <a href="index.php" class="brand__name">
                Hangout <span class="brand__badge">Admin</span>
            </a>
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

<main class="container">
    <h1>Place Suggestions</h1>

    <?php if ($message): ?><div class="alert alert--success"><?= escape($message) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert--error" role="alert"><?= escape($error) ?></div><?php endif; ?>

    <nav class="admin-nav" style="margin-bottom: 1rem;">
        <?php foreach (['pending','approved','rejected'] as $s): ?>
            <a class="admin-nav__link <?= $statusFilter === $s ? 'is-active' : '' ?>"
               href="?status=<?= $s ?>"><?= ucfirst($s) ?></a>
        <?php endforeach; ?>
    </nav>

    <?php if (empty($suggestions)): ?>
        <div class="empty-state">
            <h3>No <?= escape($statusFilter) ?> suggestions</h3>
        </div>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th><th>Name</th><th>Address</th>
                        <th>Coords</th><th>Submitted by</th>
                        <th>Date</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($suggestions as $s): ?>
                        <tr>
                            <td><?= (int)$s['id'] ?></td>
                            <td><strong><?= escape($s['name']) ?></strong></td>
                            <td><?= escape($s['address']) ?></td>
                            <td class="data-table__date">
                                <?= escape((string)$s['latitude']) ?>,
                                <?= escape((string)$s['longitude']) ?>
                            </td>
                            <td><?= escape($s['submitted_by']) ?></td>
                            <td class="data-table__date"><?= escape($s['created_at']) ?></td>
                            <td>
                                <?php if ($s['status'] === 'pending'): ?>
                                    <form method="POST" action="suggestions.php" class="inline-form"
                                          onsubmit="return confirm('Approve this suggestion and publish it?');">
                                        <input type="hidden" name="csrf_token" value="<?= escape($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button class="btn btn--sm btn--solid">Approve</button>
                                    </form>
                                    <form method="POST" action="suggestions.php" class="inline-form"
                                          onsubmit="return confirm('Reject this suggestion?');">
                                        <input type="hidden" name="csrf_token" value="<?= escape($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button class="btn btn--sm btn--danger">Reject</button>
                                    </form>
                                <?php elseif ($s['status'] === 'approved'): ?>
                                    <span class="status-pill status-pill--active">Approved</span>
                                <?php else: ?>
                                    <span class="status-pill status-pill--inactive">Rejected</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</main>
</body>
</html>