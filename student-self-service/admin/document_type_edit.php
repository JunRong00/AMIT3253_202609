<?php
require '../config.php';
require '../auth.php';
require '../helpers.php';
require_admin();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$error = '';
$uploadDir = __DIR__ . '/../uploads';

$stmt = $conn->prepare('SELECT * FROM document_types WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$type = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$type) {
    die('Document type not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name            = trim($_POST['name']);
    $description     = trim($_POST['description']);
    $fee             = (float)$_POST['fee'];
    $processing_days = (int)$_POST['processing_days'];
    $max_copies      = (int)$_POST['max_copies'];

    [$newImageUrl, $uploadError] = handle_image_upload($_FILES['image'] ?? null, $uploadDir, 'document');
    $image_url = $newImageUrl ?? $type['image_url'];

    if ($name === '' || $description === '' || $fee < 0 || $processing_days < 1 || $max_copies < 1) {
        $error = 'All fields are required; processing days and max copies must be at least 1.';
        $type = array_merge($type, compact('name', 'description', 'fee', 'processing_days', 'max_copies', 'image_url'));
    } elseif ($uploadError) {
        $error = $uploadError;
    } else {
        if ($newImageUrl) {
            delete_image_file($type['image_url'], $uploadDir);
        }

        $stmt = $conn->prepare('UPDATE document_types SET name=?, description=?, fee=?, processing_days=?, max_copies=?, image_url=? WHERE id=?');
        $stmt->bind_param('ssdiisi', $name, $description, $fee, $processing_days, $max_copies, $image_url, $id);
        $stmt->execute();
        $stmt->close();
        header('Location: document_types.php');
        exit;
    }
}

$pageTitle = 'Edit Document Type';
require 'partials/header.php';
?>
<div class="form-card">
<h1>Edit Document Type</h1>
<?php if ($error): ?><p class="alert alert-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form method="post" enctype="multipart/form-data">
<input type="hidden" name="id" value="<?= (int)$type['id'] ?>">
<label>Name <input type="text" name="name" value="<?= htmlspecialchars($type['name']) ?>" required></label>
<label>Description <textarea name="description" rows="3" maxlength="500" required><?= htmlspecialchars($type['description']) ?></textarea></label>
<label>Fee per Copy (RM) <input type="number" name="fee" step="0.01" min="0" value="<?= htmlspecialchars($type['fee']) ?>" required></label>
<label>Processing Days (working days) <input type="number" name="processing_days" min="1" value="<?= (int)$type['processing_days'] ?>" required></label>
<label>Max Copies per Request <input type="number" name="max_copies" min="1" value="<?= (int)$type['max_copies'] ?>" required></label>
<label>Current Photo
<img class="table-thumb" style="width:96px;height:96px;" src="<?= htmlspecialchars(entity_image_url($type)) ?>" alt="<?= htmlspecialchars($type['name']) ?>">
</label>
<label>Replace Photo <input type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp"></label>
<button type="submit">Update Document Type</button>
</form>
<p><a class="btn btn-secondary btn-small" href="document_types.php">Back to documents</a></p>
</div>
<?php require 'partials/footer.php'; ?>
