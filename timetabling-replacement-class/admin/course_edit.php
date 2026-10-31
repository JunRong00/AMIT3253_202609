<?php
require '../config.php';
require '../auth.php';
require_admin();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$error = '';

$stmt = $conn->prepare('SELECT * FROM courses WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$course = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$course) {
    die('Course not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code        = strtoupper(trim($_POST['code']));
    $name        = trim($_POST['name']);
    $lecturer_id = (int)$_POST['lecturer_id'] ?: null;

    if ($code === '' || $name === '') {
        $error = 'Course code and name are required.';
    } else {
        $stmt = $conn->prepare('UPDATE courses SET code=?, name=?, lecturer_id=? WHERE id=?');
        $stmt->bind_param('ssii', $code, $name, $lecturer_id, $id);
        if ($stmt->execute()) {
            $stmt->close();
            header('Location: courses.php');
            exit;
        }
        $error = 'A course with this code already exists.';
        $stmt->close();
    }
    $course = array_merge($course, compact('code', 'name', 'lecturer_id'));
}

$lecturers = $conn->query("SELECT id, name FROM users WHERE role = 'lecturer' ORDER BY name")->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Edit Course';
require 'partials/header.php';
?>
<div class="form-card">
<h1>Edit Course</h1>
<?php if ($error): ?><p class="alert alert-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form method="post">
<input type="hidden" name="id" value="<?= (int)$course['id'] ?>">
<label>Course Code <input type="text" name="code" value="<?= htmlspecialchars($course['code']) ?>" required></label>
<label>Course Name <input type="text" name="name" value="<?= htmlspecialchars($course['name']) ?>" required></label>
<label>Lecturer
<select name="lecturer_id">
<option value="0">-- Unassigned --</option>
<?php foreach ($lecturers as $l): ?>
<option value="<?= (int)$l['id'] ?>" <?= (int)$course['lecturer_id'] === (int)$l['id'] ? 'selected' : '' ?>><?= htmlspecialchars($l['name']) ?></option>
<?php endforeach; ?>
</select>
</label>
<button type="submit">Update Course</button>
</form>
<p><a class="btn btn-secondary btn-small" href="courses.php">Back to courses</a></p>
</div>
<?php require 'partials/footer.php'; ?>
