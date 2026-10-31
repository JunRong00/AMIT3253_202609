<?php
require '../config.php';
require '../auth.php';
require '../helpers.php';
require_admin();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$error = '';
$uploadDir = __DIR__ . '/../uploads';

$stmt = $conn->prepare('SELECT * FROM locations WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$location = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$location) {
    die('Location not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $building = trim($_POST['building']);
    $name     = trim($_POST['name']);
    $floor    = trim($_POST['floor']);

    [$newImageUrl, $uploadError] = handle_image_upload($_FILES['image'] ?? null, $uploadDir, 'location');
    $image_url = $newImageUrl ?? $location['image_url'];

    if ($building === '' || $name === '' || $floor === '') {
        $error = 'All fields are required.';
        $location = array_merge($location, compact('building', 'name', 'floor', 'image_url'));
    } elseif ($uploadError) {
        $error = $uploadError;
    } else {
        if ($newImageUrl) {
            delete_image_file($location['image_url'], $uploadDir);
        }

        $stmt = $conn->prepare('UPDATE locations SET building=?, name=?, floor=?, image_url=? WHERE id=?');
        $stmt->bind_param('ssssi', $building, $name, $floor, $image_url, $id);
        $stmt->execute();
        $stmt->close();
        header('Location: locations.php');
        exit;
    }
}

$pageTitle = 'Edit Location';
require 'partials/header.php';
?>
<div class="form-card">
<h1>Edit Location</h1>
<?php if ($error): ?><p class="alert alert-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form method="post" enctype="multipart/form-data">
<input type="hidden" name="id" value="<?= (int)$location['id'] ?>">
<label>Building <input type="text" name="building" value="<?= htmlspecialchars($location['building']) ?>" required></label>
<label>Location Name <input type="text" name="name" value="<?= htmlspecialchars($location['name']) ?>" required></label>
<label>Floor <input type="text" name="floor" value="<?= htmlspecialchars($location['floor']) ?>" required></label>
<label>Current Photo
<img class="table-thumb" style="width:96px;height:96px;" src="<?= htmlspecialchars(entity_image_url($location)) ?>" alt="<?= htmlspecialchars($location['name']) ?>">
</label>
<label>Replace Photo <input type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp"></label>
<button type="submit">Update Location</button>
</form>
<p><a class="btn btn-secondary btn-small" href="locations.php">Back to locations</a></p>
</div>
<?php require 'partials/footer.php'; ?>
