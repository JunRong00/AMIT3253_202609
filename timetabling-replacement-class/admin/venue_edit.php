<?php
require '../config.php';
require '../auth.php';
require '../helpers.php';
require_admin();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$error = '';
$uploadDir = __DIR__ . '/../uploads';

$stmt = $conn->prepare('SELECT * FROM venues WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$venue = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$venue) {
    die('Venue not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']);
    $building = trim($_POST['building']);
    $capacity = (int)$_POST['capacity'];

    [$newImageUrl, $uploadError] = handle_image_upload($_FILES['image'] ?? null, $uploadDir, 'venue');
    $image_url = $newImageUrl ?? $venue['image_url'];

    if ($name === '' || $building === '' || $capacity < 1) {
        $error = 'All fields are required and capacity must be at least 1.';
        $venue = array_merge($venue, compact('name', 'building', 'capacity', 'image_url'));
    } elseif ($uploadError) {
        $error = $uploadError;
    } else {
        if ($newImageUrl) {
            delete_image_file($venue['image_url'], $uploadDir);
        }

        $stmt = $conn->prepare('UPDATE venues SET name=?, building=?, capacity=?, image_url=? WHERE id=?');
        $stmt->bind_param('ssisi', $name, $building, $capacity, $image_url, $id);
        $stmt->execute();
        $stmt->close();
        header('Location: venues.php');
        exit;
    }
}

$pageTitle = 'Edit Venue';
require 'partials/header.php';
?>
<div class="form-card">
<h1>Edit Venue</h1>
<?php if ($error): ?><p class="alert alert-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form method="post" enctype="multipart/form-data">
<input type="hidden" name="id" value="<?= (int)$venue['id'] ?>">
<label>Venue Name <input type="text" name="name" value="<?= htmlspecialchars($venue['name']) ?>" required></label>
<label>Building <input type="text" name="building" value="<?= htmlspecialchars($venue['building']) ?>" required></label>
<label>Capacity (seats) <input type="number" name="capacity" min="1" value="<?= (int)$venue['capacity'] ?>" required></label>
<label>Current Photo
<img class="table-thumb" style="width:96px;height:96px;" src="<?= htmlspecialchars(entity_image_url($venue)) ?>" alt="<?= htmlspecialchars($venue['name']) ?>">
</label>
<label>Replace Photo <input type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp"></label>
<button type="submit">Update Venue</button>
</form>
<p><a class="btn btn-secondary btn-small" href="venues.php">Back to venues</a></p>
</div>
<?php require 'partials/footer.php'; ?>
