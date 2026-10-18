
<?php

require_once __DIR__ . '/../../app/helpers/auth.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/models/Place.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: places.php');
    exit;
}

$token = $_POST['csrf_token'] ?? '';

if (
    !is_string($token) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $token)
) {
    http_response_code(403);
    exit('Invalid security token. Please return and try again.');
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    header('Location: places.php?error=invalid_id');
    exit;
}

$placeModel = new Place($pdo);

try {
    $deleted = $placeModel->delete($id);

    if ($deleted) {
        header('Location: places.php?deleted=1');
    } else {
        header('Location: places.php?error=not_found');
    }

    exit;
} catch (PDOException $e) {
    
    header('Location: places.php?error=delete_failed');
    exit;
}
