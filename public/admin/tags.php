<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../../app/helpers/auth.php';
require_once __DIR__ . '/../../app/config/database.php';

requireAdmin();

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Generate a URL-safe slug from a display name.
 * "Study Friendly" -> "study-friendly"
 */
function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim((string) $text, '-');
    return $text === '' ? 'tag' : $text;
}

$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        $errorMessage = 'Invalid form submission. Please refresh the page.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'create') {
            $name = trim((string) ($_POST['name'] ?? ''));

            if ($name === '') {
                $errorMessage = 'Tag name is required.';
            } elseif (mb_strlen($name) > 50) {
                $errorMessage = 'Tag name must be 50 characters or fewer.';
            } else {
                $slug = slugify($name);

                try {
                    $stmt = $pdo->prepare(
                        'INSERT INTO tags (name, slug) VALUES (:name, :slug)'
                    );
                    $stmt->execute(['name' => $name, 'slug' => $slug]);

                    header('Location: tags.php?created=1');
                    exit;
                } catch (PDOException $e) {
                    if (isset($e->errorInfo[1]) && (int) $e->errorInfo[1] === 1062) {
                        $errorMessage = 'A tag with that name or slug already exists.';
                    } else {
                        error_log('Tag create error: ' . $e->getMessage());
                        $errorMessage = 'Could not create the tag.';
                    }
                }
            }
        } elseif ($action === 'rename') {
            $id   = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            $name = trim((string) ($_POST['name'] ?? ''));

            if (!$id || $id < 1) {
                $errorMessage = 'Invalid tag ID.';
            } elseif ($name === '') {
                $errorMessage = 'Tag name is required.';
            } elseif (mb_strlen($name) > 50) {
                $errorMessage = 'Tag name must be 50 characters or fewer.';
            } else {
                $slug = slugify($name);

                try {
                    $stmt = $pdo->prepare(
                        'UPDATE tags SET name = :name, slug = :slug WHERE id = :id'
                    );
                    $stmt->execute(['id' => $id, 'name' => $name, 'slug' => $slug]);

                    header('Location: tags.php?renamed=1');
                    exit;
                } catch (PDOException $e) {
                    if (isset($e->errorInfo[1]) && (int) $e->errorInfo[1] === 1062) {
                        $errorMessage = 'Another tag already uses that name or slug.';
                    } else {
                        error_log('Tag rename error: ' . $e->getMessage());
                        $errorMessage = 'Could not rename the tag.';
                    }
                }
            }
        } elseif ($action === 'delete') {
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

            if (!$id || $id < 1) {
                $errorMessage = 'Invalid tag ID.';
            } else {
                // Refuse to delete if any places use this tag
                $stmt = $pdo->prepare(
                    'SELECT COUNT(*) FROM place_tags WHERE tag_id = :id'
                );
                $stmt->execute(['id' => $id]);
                $usage = (int) $stmt->fetchColumn();

                if ($usage > 0) {
                    $errorMessage = "Cannot delete: $usage place(s) use this tag. "
                                  . 'Remove it from those places first.';
                } else {
                    $pdo->prepare('DELETE FROM tags WHERE id = :id')
                        ->execute(['id' => $id]);

                    header('Location: tags.php?deleted=1');
                    exit;
                }
            }
        } else {
            $errorMessage = 'Unknown action.';
        }
    }
}

if (isset($_GET['created'])) $successMessage = 'Tag created.';
if (isset($_GET['renamed'])) $successMessage = 'Tag renamed.';
if (isset($_GET['deleted'])) $successMessage = 'Tag deleted.';

// Fetch all tags with usage counts
$tags = $pdo->query("
    SELECT
        t.id,
        t.name,
        t.slug,
        COUNT(pt.place_id) AS usage_count
    FROM tags t
    LEFT JOIN place_tags pt ON pt.tag_id = t.id
    GROUP BY t.id, t.name, t.slug
    ORDER BY t.name ASC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Tags | Hangout Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="admin-body">

<a class="skip-link" href="#main">Skip to content</a>

<header class="site-header">
    <div class="site-header__inner">
        <div class="brand">
            <a href="index.php" class="brand__name">
                Hangout <span class="brand__badge">Admin</span>
            </a>
            <p class="brand__tagline">Manage places and reviews.</p>
        </div>

        <nav class="admin-nav" aria-label="Admin sections">
            <a href="index.php" class="admin-nav__link">Dashboard</a>
            <a href="places.php" class="admin-nav__link">Places</a>
            <a href="tags.php" class="admin-nav__link is-active" aria-current="page">Tags</a>
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
            <h1>Manage Tags</h1>
            <p class="page-header__subtitle">
                Create tags and rename them. A tag can't be deleted while places still use it.
            </p>
        </div>
    </header>

    <?php if ($successMessage !== ''): ?>
        <div class="alert alert--success" role="status"><?= escape($successMessage) ?></div>
    <?php endif; ?>

    <?php if ($errorMessage !== ''): ?>
        <div class="alert alert--error" role="alert"><?= escape($errorMessage) ?></div>
    <?php endif; ?>

    <section class="panel" aria-labelledby="new-tag-heading">
        <header class="panel__header">
            <h2 id="new-tag-heading">Create a new tag</h2>
        </header>

        <form method="POST" action="tags.php" class="admin-form">
            <input type="hidden" name="csrf_token" value="<?= escape($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="action" value="create">

            <div class="field">
                <label for="new-tag-name">Tag name</label>
                <input
                    type="text"
                    id="new-tag-name"
                    name="name"
                    maxlength="50"
                    placeholder="e.g. Pet-Friendly"
                    required
                >
                <span class="field-hint">Slug is generated automatically.</span>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn--solid">Create Tag</button>
            </div>
        </form>
    </section>

    <section class="panel" aria-labelledby="all-tags-heading">
        <header class="panel__header panel__header--row">
            <h2 id="all-tags-heading">All tags</h2>
            <p class="results-count">
                <?= count($tags) ?> <?= count($tags) === 1 ? 'tag' : 'tags' ?>
            </p>
        </header>

        <?php if (empty($tags)): ?>
            <div class="empty-state">
                <h3>No tags yet</h3>
                <p>Use the form above to create the first tag.</p>
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col">Slug</th>
                            <th scope="col">Used by</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tags as $tag): ?>
                            <?php $inUse = (int) $tag['usage_count'] > 0; ?>
                            <tr>
                                <td>
                                    <form method="POST" action="tags.php" class="inline-form inline-form--flex" style="gap: 0.5rem;">
                                        <input type="hidden" name="csrf_token" value="<?= escape($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="action" value="rename">
                                        <input type="hidden" name="id" value="<?= (int) $tag['id'] ?>">
                                        <input
                                            type="text"
                                            name="name"
                                            value="<?= escape($tag['name']) ?>"
                                            maxlength="50"
                                            class="tag-rename-input"
                                            aria-label="Rename <?= escape($tag['name']) ?>"
                                        >
                                        <button type="submit" class="btn btn--sm btn--ghost">Rename</button>
                                    </form>
                                </td>
                                <td class="data-table__id"><code><?= escape($tag['slug']) ?></code></td>
                                <td>
                                    <span class="status-pill status-pill--<?= $inUse ? 'active' : 'inactive' ?>">
                                        <?= (int) $tag['usage_count'] ?>
                                        <?= (int) $tag['usage_count'] === 1 ? 'place' : 'places' ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="POST" action="tags.php" class="inline-form"
                                          onsubmit="return confirm('Delete this tag? This cannot be undone.');">
                                        <input type="hidden" name="csrf_token" value="<?= escape($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int) $tag['id'] ?>">
                                        <button
                                            type="submit"
                                            class="btn btn--sm btn--danger"
                                            <?= $inUse ? 'disabled title="Remove from all places first"' : '' ?>
                                        >Delete</button>
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