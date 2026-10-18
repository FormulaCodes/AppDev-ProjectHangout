
<?php

session_start();

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/controllers/AuthController.php';

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (!is_string($submittedToken) ||
        !hash_equals($_SESSION['csrf_token'], $submittedToken)) {
        $result = [
            'success' => false,
            'errors' => ['Invalid form submission. Please refresh and try again.']
        ];
    } else {
        $controller = new AuthController($pdo);
        $result = $controller->register($_POST);
    }
}

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$showForm = ($result === null || !$result['success']);
$isError  = ($result !== null && !$result['success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account | Hangout</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<a class="skip-link" href="#main">Skip to content</a>

<header class="site-header">
    <div class="site-header__inner">
        <div class="brand">
            <a href="index.php" class="brand__name">Hangout</a>
            <p class="brand__tagline">Join your campus community.</p>
        </div>
    </div>
</header>

<main id="main" class="container container--narrow">

    <section class="auth-card" aria-labelledby="register-heading">
        <header class="auth-card__header">
            <h1 id="register-heading">Create an Account</h1>
            <p class="auth-card__subtitle">Share your favorite places and help other students find theirs.</p>
        </header>

        <?php if ($isError): ?>
            <div class="alert alert--error" role="alert">
                <p><strong>Please fix the following:</strong></p>
                <ul>
                    <?php foreach ($result['errors'] as $error): ?>
                        <li><?= escape($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php elseif ($result !== null && $result['success']): ?>
            <div class="alert alert--success" role="status">
                <?= escape($result['message']) ?>
            </div>
            <p><a href="login.php" class="btn btn--solid btn--block">Continue to login</a></p>
        <?php endif; ?>

        <?php if ($showForm): ?>
            <form method="POST" action="register.php" class="auth-form">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= escape($_SESSION['csrf_token']) ?>"
                >

                <div class="field">
                    <label for="username">Username</label>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        minlength="3"
                        maxlength="50"
                        autocomplete="username"
                        required
                    >
                    <span class="field-hint">3–50 characters.</span>
                </div>

                <div class="field">
                    <label for="email">Email</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        maxlength="255"
                        autocomplete="email"
                        required
                    >
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        minlength="8"
                        autocomplete="new-password"
                        required
                    >
                    <span class="field-hint">At least 8 characters.</span>
                </div>

                <div class="field">
                    <label for="confirm_password">Confirm Password</label>
                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        minlength="8"
                        autocomplete="new-password"
                        required
                    >
                </div>

                <button type="submit" class="btn btn--solid btn--block">Create Account</button>
            </form>
        <?php endif; ?>

        <footer class="auth-card__footer">
            <p><a href="index.php" class="link-muted">← Back to Hangout</a></p>
        </footer>
    </section>

</main>
</body>
</html>