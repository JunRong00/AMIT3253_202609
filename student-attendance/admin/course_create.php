<?php
require '../config.php';
require '../auth.php';
require '../helpers.php';
require_admin();

$error = '';
$uploadDir = __DIR__ . '/../uploads';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code        = strtoupper(trim($_POST['code']));
    $name        = trim($_POST['name']);
    $lecturer_id = (int)$_POST['lecturer_id'] ?: null;

    [$image_url, $uploadError] = handle_image_upload($_FILES['image'] ?? null, $uploadDir, 'course');

    if ($code === '' || $name === '') {
        $error = 'Course code and name are required.';
    } elseif ($uploadError) {
        $error = $uploadError;
    } else {
        $stmt = $conn->prepare('INSERT INTO courses (code, name, lecturer_id, image_url) VALUES (?, ?, ?, ?)');
        $stmt->bind_param('ssis', $code, $name, $lecturer_id, $image_url);
        if ($stmt->execute()) {
            $stmt->close();
            header('Location: courses.php');
            exit;
        }
        $error = 'A course with this code already exists.';
        $stmt->close();
        delete_image_file($image_url, $uploadDir);
    }
}

$lecturers = $conn->query("SELECT id, name FROM users WHERE role = 'lecturer' ORDER BY name")->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Add Course';
require 'partials/header.php';
?>
<div class="form-card">
<h1>Add Course</h1>
<?php if ($error): ?><p class="alert alert-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form method="post" enctype="multipart/form-data">
<label>Course Code <input type="text" name="code" placeholder="e.g. AMIT3253" value="<?= htmlspecialchars($_POST['code'] ?? '') ?>" required></label>
<label>Course Name <input type="text" name="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required></label>
<label>Lecturer
<select name="lecturer_id">
<option value="0">-- Unassigned --</option>
<?php foreach ($lecturers as $l): ?>
<option value="<?= (int)$l['id'] ?>" <?= (int)($_POST['lecturer_id'] ?? 0) === (int)$l['id'] ? 'selected' : '' ?>><?= htmlspecialchars($l['name']) ?></option>
<?php endforeach; ?>
</select>
</label>
<label>Photo <input type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp"></label>
<button type="submit">Add Course</button>
</form>
<p class="form-hint">Need a new lecturer? Create their account from <a href="user_create.php">Users &rarr; Add Admin / Lecturer</a>.</p>
<p><a class="btn btn-secondary btn-small" href="courses.php">Back to courses</a></p>
</div>
<?php require 'partials/footer.php'; ?>
