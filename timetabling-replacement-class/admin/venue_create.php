<?php
require '../config.php';
require '../auth.php';
require '../helpers.php';
require_admin();

$error = '';
$uploadDir = __DIR__ . '/../uploads';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']);
    $building = trim($_POST['building']);
    $capacity = (int)$_POST['capacity'];

    [$image_url, $uploadError] = handle_image_upload($_FILES['image'] ?? null, $uploadDir, 'venue');

    if ($name === '' || $building === '' || $capacity < 1) {
        $error = 'All fields are required and capacity must be at least 1.';
    } elseif ($uploadError) {
        $error = $uploadError;
    } else {
        $stmt = $conn->prepare('INSERT INTO venues (name, building, capacity, image_url) VALUES (?, ?, ?, ?)');
        $stmt->bind_param('ssis', $name, $building, $capacity, $image_url);
        $stmt->execute();
        $stmt->close();
        header('Location: venues.php');
        exit;
    }
}

$pageTitle = 'Add Venue';
require 'partials/header.php';
?>
<div class="form-card">
<h1>Add Venue</h1>
<?php if ($error): ?><p class="alert alert-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form method="post" enctype="multipart/form-data">
<label>Venue Name <input type="text" name="name" placeholder="e.g. Tutorial Room B204" required></label>
<label>Building <input type="text" name="building" placeholder="e.g. Block B" required></label>
<label>Capacity (seats) <input type="number" name="capacity" min="1" value="40" required></label>
<label>Photo <input type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp"></label>
<button type="submit">Add Venue</button>
</form>
<p><a class="btn btn-secondary btn-small" href="venues.php">Back to venues</a></p>
</div>
<?php require 'partials/footer.php'; ?>
