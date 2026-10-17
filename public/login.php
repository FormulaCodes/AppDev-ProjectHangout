
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
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header>
    <h1>Hangout</h1>
    <p>Welcome back to your campus community.</p>
</header>

<main>
    <section class="place-detail">
        <h2>Login</h2>

        <?php if ($error !== ''): ?>
            <p role="alert"><?= escape($error) ?></p>
        <?php endif; ?>

        <form method="POST" action="login.php" class="search-form">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= escape($_SESSION['csrf_token']) ?>"
            >

            <label for="email">Email</label>
            <input
                type="email"
                id="email"
                name="email"
                autocomplete="email"
                maxlength="255"
                required
            >

            <label for="password">Password</label>
            <input
                type="password"
                id="password"
                name="password"
                autocomplete="current-password"
                required
            >

            <button type="submit">Login</button>
        </form>

        <p>Don't have an account? <a href="register.php">Register here</a></p>
        <p><a href="index.php">Back to Hangout</a></p>
    </section>
</main>
</body>
</html>
