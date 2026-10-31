<?php
require '../config.php';
require '../auth.php';
require '../helpers.php';
require_admin();

$error = '';
$uploadDir = __DIR__ . '/../uploads';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title        = trim($_POST['title']);
    $organisation = trim($_POST['organisation']);
    $category     = $_POST['category'] ?? '';
    $description  = trim($_POST['description']);
    $location     = trim($_POST['location']);
    $vacancies    = (int)$_POST['vacancies'];
    $deadline     = $_POST['deadline'] ?? '';

    [$image_url, $uploadError] = handle_image_upload($_FILES['image'] ?? null, $uploadDir, 'opening');

    if ($title === '' || $organisation === '' || $description === '' || $location === '' || $deadline === '' || $vacancies < 1) {
        $error = 'All fields are required and vacancies must be at least 1.';
    } elseif (!in_array($category, opening_categories(), true)) {
        $error = 'Please choose a valid category.';
    } elseif ($uploadError) {
        $error = $uploadError;
    } else {
        $stmt = $conn->prepare('INSERT INTO openings (title, organisation, category, description, location, vacancies, deadline, image_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('sssssiss', $title, $organisation, $category, $description, $location, $vacancies, $deadline, $image_url);
        $stmt->execute();
        $stmt->close();
        header('Location: openings.php');
        exit;
    }
}

$pageTitle = 'Add Opening';
require 'partials/header.php';
?>
<div class="form-card">
<h1>Add Opening</h1>
<?php if ($error): ?><p class="alert alert-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form method="post" enctype="multipart/form-data">
<label>Title <input type="text" name="title" required></label>
<label>Organisation <input type="text" name="organisation" required></label>
<label>Category
<select name="category" required>
<?php foreach (opening_categories() as $c): ?>
<option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
<?php endforeach; ?>
</select>
</label>
<label>Description <textarea name="description" rows="4" required></textarea></label>
<label>Location <input type="text" name="location" required></label>
<label>Vacancies <input type="number" name="vacancies" min="1" value="1" required></label>
<label>Application Deadline <input type="date" name="deadline" min="<?= date('Y-m-d') ?>" required></label>
<label>Photo <input type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp"></label>
<button type="submit">Add Opening</button>
</form>
<p><a class="btn btn-secondary btn-small" href="openings.php">Back to openings</a></p>
</div>
<?php require 'partials/footer.php'; ?>
