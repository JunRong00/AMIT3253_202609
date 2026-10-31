<?php
require '../config.php';
require '../auth.php';
require_admin();

$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_error']);

$courses = $conn->query('
    SELECT c.*, (SELECT COUNT(*) FROM results r WHERE r.course_id = c.id) AS result_count
    FROM courses c
    ORDER BY c.code
');

$pageTitle = 'Manage Courses';
require 'partials/header.php';
?>
<h1>Courses</h1>
<p><a class="btn btn-small" href="course_create.php">+ Add Course</a></p>
<?php if ($flashError): ?><p class="alert alert-error"><?= htmlspecialchars($flashError) ?></p><?php endif; ?>
<table>
<tr><th>Code</th><th>Course</th><th>Credit Hours</th><th>Results Entered</th><th>Actions</th></tr>
<?php while ($c = $courses->fetch_assoc()): ?>
<tr>
<td><?= htmlspecialchars($c['code']) ?></td>
<td><?= htmlspecialchars($c['name']) ?></td>
<td><?= (int)$c['credit_hours'] ?></td>
<td><?= (int)$c['result_count'] ?></td>
<td>
<a class="btn btn-secondary btn-small" href="course_edit.php?id=<?= (int)$c['id'] ?>">Edit</a>
<form action="course_delete.php" method="post" style="display:inline" onsubmit="return confirm('Delete this course? Any results for it must be removed first.');">
<input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
<button type="submit" class="btn-small btn-danger">Delete</button>
</form>
</td>
</tr>
<?php endwhile; ?>
</table>
<?php require 'partials/footer.php'; ?>
