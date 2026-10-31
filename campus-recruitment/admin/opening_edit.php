<?php
require '../config.php';
require '../auth.php';
require '../helpers.php';
require_admin();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$error = '';
$uploadDir = __DIR__ . '/../uploads';

$stmt = $conn->prepare('SELECT * FROM openings WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$opening = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$opening) {
    die('Opening not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title        = trim($_POST['title']);
    $organisation = trim($_POST['organisation']);
    $category     = $_POST['category'] ?? '';
    $description  = trim($_POST['description']);
    $location     = trim($_POST['location']);
    $vacancies    = (int)$_POST['vacancies'];
    $deadline     = $_POST['deadline'] ?? '';

    [$newImageUrl, $uploadError] = handle_image_upload($_FILES['image'] ?? null, $uploadDir, 'opening');
    $image_url = $newImageUrl ?? $opening['image_url'];

    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM applications WHERE opening_id = ? AND status = 'Accepted'");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $accepted = (int)$stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();

    if ($title === '' || $organisation === '' || $description === '' || $location === '' || $deadline === '' || $vacancies < 1) {
        $error = 'All fields are required and vacancies must be at least 1.';
    } elseif (!in_array($category, opening_categories(), true)) {
        $error = 'Please choose a valid category.';
    } elseif ($vacancies < $accepted) {
        $error = "Vacancies can't be lower than the $accepted applicant(s) already accepted.";
    } elseif ($uploadError) {
        $error = $uploadError;
    } else {
        if ($newImageUrl) {
            delete_image_file($opening['image_url'], $uploadDir);
        }

        $stmt = $conn->prepare('UPDATE openings SET title=?, organisation=?, category=?, description=?, location=?, vacancies=?, deadline=?, image_url=? WHERE id=?');
        $stmt->bind_param('sssssissi', $title, $organisation, $category, $description, $location, $vacancies, $deadline, $image_url, $id);
        $stmt->execute();
        $stmt->close();
        header('Location: openings.php');
        exit;
    }
    $opening = array_merge($opening, compact('title', 'organisation', 'category', 'description', 'location', 'vacancies', 'deadline', 'image_url'));
}

$pageTitle = 'Edit Opening';
require 'partials/header.php';
?>
<div class="form-card">
<h1>Edit Opening</h1>
<?php if ($error): ?><p class="alert alert-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form method="post" enctype="multipart/form-data">
<input type="hidden" name="id" value="<?= (int)$opening['id'] ?>">
<label>Title <input type="text" name="title" value="<?= htmlspecialchars($opening['title']) ?>" required></label>
<label>Organisation <input type="text" name="organisation" value="<?= htmlspecialchars($opening['organisation']) ?>" required></label>
<label>Category
<select name="category" required>
<?php foreach (opening_categories() as $c): ?>
<option value="<?= htmlspecialchars($c) ?>" <?= $opening['category'] === $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
<?php endforeach; ?>
</select>
</label>
<label>Description <textarea name="description" rows="4" required><?= htmlspecialchars($opening['description']) ?></textarea></label>
<label>Location <input type="text" name="location" value="<?= htmlspecialchars($opening['location']) ?>" required></label>
<label>Vacancies <input type="number" name="vacancies" min="1" value="<?= (int)$opening['vacancies'] ?>" required></label>
<label>Application Deadline <input type="date" name="deadline" value="<?= htmlspecialchars($opening['deadline']) ?>" required></label>
<label>Current Photo
<img class="table-thumb" style="width:96px;height:96px;" src="<?= htmlspecialchars(entity_image_url($opening)) ?>" alt="<?= htmlspecialchars($opening['title']) ?>">
</label>
<label>Replace Photo <input type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp"></label>
<button type="submit">Update Opening</button>
</form>
<p><a class="btn btn-secondary btn-small" href="openings.php">Back to openings</a></p>
</div>
<?php require 'partials/footer.php'; ?>
