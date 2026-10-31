<?php
require '../config.php';
require '../auth.php';
require '../helpers.php';
require_admin();

$error = '';
$uploadDir = __DIR__ . '/../uploads';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name            = trim($_POST['name']);
    $description     = trim($_POST['description']);
    $fee             = (float)$_POST['fee'];
    $processing_days = (int)$_POST['processing_days'];
    $max_copies      = (int)$_POST['max_copies'];

    [$image_url, $uploadError] = handle_image_upload($_FILES['image'] ?? null, $uploadDir, 'document');

    if ($name === '' || $description === '' || $fee < 0 || $processing_days < 1 || $max_copies < 1) {
        $error = 'All fields are required; processing days and max copies must be at least 1.';
    } elseif ($uploadError) {
        $error = $uploadError;
    } else {
        $stmt = $conn->prepare('INSERT INTO document_types (name, description, fee, processing_days, max_copies, image_url) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('ssdiis', $name, $description, $fee, $processing_days, $max_copies, $image_url);
        $stmt->execute();
        $stmt->close();
        header('Location: document_types.php');
        exit;
    }
}

$pageTitle = 'Add Document Type';
require 'partials/header.php';
?>
<div class="form-card">
<h1>Add Document Type</h1>
<?php if ($error): ?><p class="alert alert-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form method="post" enctype="multipart/form-data">
<label>Name <input type="text" name="name" required></label>
<label>Description <textarea name="description" rows="3" maxlength="500" required></textarea></label>
<label>Fee per Copy (RM) <input type="number" name="fee" step="0.01" min="0" value="0.00" required></label>
<label>Processing Days (working days) <input type="number" name="processing_days" min="1" value="3" required></label>
<label>Max Copies per Request <input type="number" name="max_copies" min="1" value="3" required></label>
<label>Photo <input type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp"></label>
<button type="submit">Add Document Type</button>
</form>
<p><a class="btn btn-secondary btn-small" href="document_types.php">Back to documents</a></p>
</div>
<?php require 'partials/footer.php'; ?>
