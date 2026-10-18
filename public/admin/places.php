
<?php

require_once __DIR__ . '/../../app/helpers/auth.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/models/Place.php';

requireAdmin();

// Adjust this line if your database configuration
// exposes the PDO connection under a different variable.
$placeModel = new Place($pdo);
$places = $placeModel->getAll();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Places | Hangout</title>
</head>
<body>
    <h1>Manage Places</h1>

    <p>
        <a href="index.php">Admin Dashboard</a> |
        <a href="../index.php">Return to Hangout</a>
    </p>

    <h2>Existing Places</h2>

    <?php if (empty($places)): ?>
        <p>No places have been added yet.</p>
    <?php else: ?>
        <table border="1" cellpadding="8">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Address</th>
                    <th>Tags</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($places as $place): ?>
                    <tr>
                        <td>
                            <?= (int) $place['id'] ?>
                        </td>
                        <td>
                            <?= htmlspecialchars(
                                $place['name'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </td>
                        <td>
                            <?= htmlspecialchars(
                                $place['address'] ?? '',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </td>
                        <td>
                            <?= htmlspecialchars(
                                $place['tags'] ?? '',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</body>
</html>
