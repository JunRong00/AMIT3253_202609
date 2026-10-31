<?php
require '../config.php';
require '../auth.php';
require_admin();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code         = strtoupper(trim($_POST['code']));
    $name         = trim($_POST['name']);
    $credit_hours = (int)$_POST['credit_hours'];

    if ($code === '' || $name === '' || $credit_hours < 1) {
        $error = 'All fields are required and credit hours must be at least 1.';
    } else {
        $stmt = $conn->prepare('INSERT INTO courses (code, name, credit_hours) VALUES (?, ?, ?)');
        $stmt->bind_param('ssi', $code, $name, $credit_hours);
        if ($stmt->execute()) {
            $stmt->close();
            header('Location: courses.php');
            exit;
        }
        $error = 'A course with this code already exists.';
        $stmt->close();
    }
}

$pageTitle = 'Add Course';
require 'partials/header.php';
?>
<div class="form-card">
<h1>Add Course</h1>
<?php if ($error): ?><p class="alert alert-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form method="post">
<label>Course Code <input type="text" name="code" placeholder="e.g. AMIT3253" value="<?= htmlspecialchars($_POST['code'] ?? '') ?>" required></label>
<label>Course Name <input type="text" name="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required></label>
<label>Credit Hours <input type="number" name="credit_hours" min="1" max="12" value="<?= (int)($_POST['credit_hours'] ?? 3) ?>" required></label>
<button type="submit">Add Course</button>
</form>
<p><a class="btn btn-secondary btn-small" href="courses.php">Back to courses</a></p>
</div>
<?php require 'partials/footer.php'; ?>
