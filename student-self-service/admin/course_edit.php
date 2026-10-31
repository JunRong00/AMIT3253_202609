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
    $code         = strtoupper(trim($_POST['code']));
    $name         = trim($_POST['name']);
    $credit_hours = (int)$_POST['credit_hours'];

    if ($code === '' || $name === '' || $credit_hours < 1) {
        $error = 'All fields are required and credit hours must be at least 1.';
    } else {
        $stmt = $conn->prepare('UPDATE courses SET code=?, name=?, credit_hours=? WHERE id=?');
        $stmt->bind_param('ssii', $code, $name, $credit_hours, $id);
        if ($stmt->execute()) {
            $stmt->close();
            header('Location: courses.php');
            exit;
        }
        $error = 'A course with this code already exists.';
        $stmt->close();
    }
    $course = array_merge($course, compact('code', 'name', 'credit_hours'));
}

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
<label>Credit Hours <input type="number" name="credit_hours" min="1" max="12" value="<?= (int)$course['credit_hours'] ?>" required></label>
<button type="submit">Update Course</button>
</form>
<p><a class="btn btn-secondary btn-small" href="courses.php">Back to courses</a></p>
</div>
<?php require 'partials/footer.php'; ?>
