<?php
require '../config.php';
require '../auth.php';
require_admin();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code        = strtoupper(trim($_POST['code']));
    $name        = trim($_POST['name']);
    $lecturer_id = (int)$_POST['lecturer_id'] ?: null;

    if ($code === '' || $name === '') {
        $error = 'Course code and name are required.';
    } else {
        $stmt = $conn->prepare('INSERT INTO courses (code, name, lecturer_id) VALUES (?, ?, ?)');
        $stmt->bind_param('ssi', $code, $name, $lecturer_id);
        if ($stmt->execute()) {
            $stmt->close();
            header('Location: courses.php');
            exit;
        }
        $error = 'A course with this code already exists.';
        $stmt->close();
    }
}

$lecturers = $conn->query("SELECT id, name FROM users WHERE role = 'lecturer' ORDER BY name")->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Add Course';
require 'partials/header.php';
?>
<div class="form-card">
<h1>Add Course</h1>
<?php if ($error): ?><p class="alert alert-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form method="post">
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
<button type="submit">Add Course</button>
</form>
<p class="form-hint">Need a new lecturer? Create their account from <a href="user_create.php">Users &rarr; Add Admin / Lecturer</a>.</p>
<p><a class="btn btn-secondary btn-small" href="courses.php">Back to courses</a></p>
</div>
<?php require 'partials/footer.php'; ?>
