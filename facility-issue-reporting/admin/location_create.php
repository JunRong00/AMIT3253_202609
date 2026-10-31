<?php
require '../config.php';
require '../auth.php';
require '../helpers.php';
require_admin();

$error = '';
$uploadDir = __DIR__ . '/../uploads';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $building = trim($_POST['building']);
    $name     = trim($_POST['name']);
    $floor    = trim($_POST['floor']);

    [$image_url, $uploadError] = handle_image_upload($_FILES['image'] ?? null, $uploadDir, 'location');

    if ($building === '' || $name === '' || $floor === '') {
        $error = 'All fields are required.';
    } elseif ($uploadError) {
        $error = $uploadError;
    } else {
        $stmt = $conn->prepare('INSERT INTO locations (building, name, floor, image_url) VALUES (?, ?, ?, ?)');
        $stmt->bind_param('ssss', $building, $name, $floor, $image_url);
        $stmt->execute();
        $stmt->close();
        header('Location: locations.php');
        exit;
    }
}

$pageTitle = 'Add Location';
require 'partials/header.php';
?>
<div class="form-card">
<h1>Add Location</h1>
<?php if ($error): ?><p class="alert alert-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form method="post" enctype="multipart/form-data">
<label>Building <input type="text" name="building" placeholder="e.g. Block A" required></label>
<label>Location Name <input type="text" name="name" placeholder="e.g. Lecture Hall DK A" required></label>
<label>Floor <input type="text" name="floor" placeholder="e.g. Ground Floor" required></label>
<label>Photo <input type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp"></label>
<button type="submit">Add Location</button>
</form>
<p><a class="btn btn-secondary btn-small" href="locations.php">Back to locations</a></p>
</div>
<?php require 'partials/footer.php'; ?>
