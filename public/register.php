
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
<header>
    <h1>Hangout</h1>
    <p>Join your campus community.</p>
</header>

<main>
    <section class="place-detail">
        <h2>Create an Account</h2>

        <?php if ($result !== null && !$result['success']): ?>
            <ul>
                <?php foreach ($result['errors'] as $error): ?>
                    <li><?= escape($error) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php elseif ($result !== null && $result['success']): ?>
            <p><?= escape($result['message']) ?></p>
            <p><a href="login.php">Continue to login</a></p>
        <?php endif; ?>

        <?php if ($result === null || !$result['success']): ?>
            <form method="POST" action="register.php" class="search-form">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= escape($_SESSION['csrf_token']) ?>"
                >

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

                <label for="email">Email</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    maxlength="255"
                    autocomplete="email"
                    required
                >

                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    minlength="8"
                    autocomplete="new-password"
                    required
                >

                <label for="confirm_password">Confirm Password</label>
                <input
                    type="password"
                    id="confirm_password"
                    name="confirm_password"
                    minlength="8"
                    autocomplete="new-password"
                    required
                >

                <button type="submit">Create Account</button>
            </form>
        <?php endif; ?>

        <p><a href="index.php">Back to Hangout</a></p>
    </section>
</main>
</body>
</html>
