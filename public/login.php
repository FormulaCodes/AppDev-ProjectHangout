
<?php

session_start();

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/models/User.php';

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($submittedToken) ||
        !hash_equals($_SESSION['csrf_token'], $submittedToken)
    ) {
        $error = 'Invalid form submission. Please refresh and try again.';
    } else {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        if (
            !is_string($email) ||
            !is_string($password) ||
            $email === '' ||
            $password === ''
        ) {
            $error = 'Please enter your email and password.';
        } else {
            $userModel = new User($pdo);
            $user = $userModel->authenticate(trim($email), $password);

            if ($user === null) {
                $error = 'Invalid email or password.';
            } else {
                session_regenerate_id(true);

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                header('Location: index.php');
                exit;
            }
        }
    }
}

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Hangout</title>
    <meta name="description" content="Log in to Hangout to discover and review places around campus.">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<a class="skip-link" href="#main">Skip to content</a>

<header class="site-header">
    <div class="site-header__inner">
        <div class="brand">
            <a href="index.php" class="brand__name">Hangout</a>
            <p class="brand__tagline">Welcome back to your campus community.</p>
        </div>
    </div>
</header>

<main id="main" class="container container--narrow">

    <section class="auth-card" aria-labelledby="login-heading">
        <header class="auth-card__header">
            <h1 id="login-heading">Login</h1>
            <p class="auth-card__subtitle">Sign in to review places and save your favorites.</p>
        </header>

        <?php if ($error !== ''): ?>
            <div class="alert alert--error" role="alert">
                <?= escape($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" class="auth-form">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= escape($_SESSION['csrf_token']) ?>"
            >

            <div class="field">
                <label for="email">Email</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    autocomplete="email"
                    maxlength="255"
                    required
                >
            </div>

            <div class="field">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    autocomplete="current-password"
                    required
                >
            </div>

            <button type="submit" class="btn btn--solid btn--block">Login</button>
        </form>

        <footer class="auth-card__footer">
            <p>Don't have an account? <a href="register.php">Register here</a></p>
            <p><a href="index.php" class="link-muted">← Back to Hangout</a></p>
        </footer>
    </section>

</main>
</body>
</html>