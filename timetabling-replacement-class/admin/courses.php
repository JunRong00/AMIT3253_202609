<?php
require '../config.php';
require '../auth.php';
require_admin();

$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_error']);

$courses = $conn->query('
    SELECT c.*, u.name AS lecturer_name,
           (SELECT COUNT(*) FROM timetable_entries t WHERE t.course_id = c.id) AS classes_per_week
    FROM courses c
    LEFT JOIN users u ON u.id = c.lecturer_id
    ORDER BY c.code
');

$pageTitle = 'Manage Courses';
require 'partials/header.php';
?>
<h1>Courses</h1>
<p><a class="btn btn-small" href="course_create.php">+ Add Course</a></p>
<?php if ($flashError): ?><p class="alert alert-error"><?= htmlspecialchars($flashError) ?></p><?php endif; ?>
<table>
<tr><th>Code</th><th>Course</th><th>Lecturer</th><th>Classes / Week</th><th>Actions</th></tr>
<?php while ($c = $courses->fetch_assoc()): ?>
<tr>
<td><?= htmlspecialchars($c['code']) ?></td>
<td><?= htmlspecialchars($c['name']) ?></td>
<td><?= htmlspecialchars($c['lecturer_name'] ?? 'Unassigned') ?></td>
<td><?= (int)$c['classes_per_week'] ?></td>
<td>
<a class="btn btn-secondary btn-small" href="course_edit.php?id=<?= (int)$c['id'] ?>">Edit</a>
<form action="course_delete.php" method="post" style="display:inline" onsubmit="return confirm('Delete this course? Its timetable classes and replacement requests must be removed first.');">
<input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
<button type="submit" class="btn-small btn-danger">Delete</button>
</form>
</td>
</tr>
<?php endwhile; ?>
</table>
<?php require 'partials/footer.php'; ?>
