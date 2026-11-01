<?php
require '../config.php';
require '../auth.php';
require '../helpers.php';
require_admin();

$studentId = (int)($_GET['student_id'] ?? $_POST['student_id'] ?? 0);
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $course_id = (int)($_POST['course_id'] ?? 0);
    $semester_id = (int)($_POST['semester_id'] ?? 0);
    $grade     = $_POST['grade'] ?? '';
    $points    = grade_points();

    if ($semester_id < 1) {
        $error = 'Please choose a semester.';
    } elseif (!isset($points[$grade])) {
        $error = 'Please choose a valid grade.';
    } else {
        $grade_point = $points[$grade];
        // Re-entering a result for the same course + semester corrects it
        // instead of adding a duplicate row (see the uniq_result key).
        $stmt = $conn->prepare('
            INSERT INTO results (user_id, course_id, semester_id, grade, grade_point) VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE grade = VALUES(grade), grade_point = VALUES(grade_point)
        ');
        $stmt->bind_param('iiisd', $studentId, $course_id, $semester_id, $grade, $grade_point);
        if ($stmt->execute()) {
            $success = 'Result saved.';
        } else {
            $error = 'Could not save the result - check the student, course and semester exist.';
        }
        $stmt->close();
    }
}

$students = $conn->query('SELECT id, name, id_number FROM users WHERE is_admin = 0 ORDER BY name')->fetch_all(MYSQLI_ASSOC);
$courses = $conn->query('SELECT id, code, name FROM courses ORDER BY code')->fetch_all(MYSQLI_ASSOC);
$semesters = $conn->query('SELECT * FROM semesters ORDER BY start_date DESC')->fetch_all(MYSQLI_ASSOC);
// Default to the semester that has most recently started.
$defaultSemesterId = (int)($_POST['semester_id'] ?? ($conn->query('SELECT id FROM semesters WHERE start_date <= CURDATE() ORDER BY start_date DESC LIMIT 1')->fetch_assoc()['id'] ?? 0));

$results = [];
if ($studentId > 0) {
    $stmt = $conn->prepare('
        SELECT r.id, s.code AS semester_code, s.name AS semester_name, r.grade, r.grade_point, c.code, c.name
        FROM results r
        JOIN courses c ON c.id = r.course_id
        JOIN semesters s ON s.id = r.semester_id
        WHERE r.user_id = ?
        ORDER BY s.start_date DESC, c.code
    ');
    $stmt->bind_param('i', $studentId);
    $stmt->execute();
    $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$pageTitle = 'Examination Results';
require 'partials/header.php';
?>
<h1>Examination Results</h1>
<p>Choose a student to view and enter their results. Entering a result for a course and semester that already has one corrects it.</p>

<form method="get" class="filter-bar">
<label>Student
<select name="student_id" onchange="this.form.submit()">
<option value="0">-- Select a student --</option>
<?php foreach ($students as $s): ?>
<option value="<?= (int)$s['id'] ?>" <?= (int)$s['id'] === $studentId ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?><?= $s['id_number'] ? ' (' . htmlspecialchars($s['id_number']) . ')' : '' ?></option>
<?php endforeach; ?>
</select>
</label>
<noscript><button type="submit">Show</button></noscript>
</form>

<?php if ($studentId > 0): ?>
<?php if ($error): ?><p class="alert alert-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<?php if ($success): ?><p class="alert alert-success"><?= htmlspecialchars($success) ?></p><?php endif; ?>
<form method="post" class="filter-bar">
<input type="hidden" name="student_id" value="<?= (int)$studentId ?>">
<label>Course
<select name="course_id" required>
<?php foreach ($courses as $c): ?>
<option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['code']) ?> <?= htmlspecialchars($c['name']) ?></option>
<?php endforeach; ?>
</select>
</label>
<label>Semester
<select name="semester_id" required>
<?php foreach ($semesters as $s): ?>
<option value="<?= (int)$s['id'] ?>" <?= (int)$s['id'] === $defaultSemesterId ? 'selected' : '' ?>><?= htmlspecialchars(semester_label($s)) ?></option>
<?php endforeach; ?>
</select>
</label>
<label>Grade
<select name="grade" required>
<?php foreach (grade_points() as $g => $p): ?>
<option value="<?= htmlspecialchars($g) ?>"><?= htmlspecialchars($g) ?> (<?= number_format($p, 2) ?>)</option>
<?php endforeach; ?>
</select>
</label>
<button type="submit">Save Result</button>
</form>

<?php if (empty($results)): ?>
<div class="empty-state">
<div class="empty-state-icon">&#128202;</div>
<p>No results entered for this student yet.</p>
</div>
<?php else: ?>
<table>
<tr><th>Semester</th><th>Course</th><th>Grade</th><th>Grade Point</th><th>Actions</th></tr>
<?php foreach ($results as $r): ?>
<tr>
<td><?= htmlspecialchars(semester_label(['code' => $r['semester_code'], 'name' => $r['semester_name']])) ?></td>
<td><?= htmlspecialchars($r['code']) ?> <?= htmlspecialchars($r['name']) ?></td>
<td><strong><?= htmlspecialchars($r['grade']) ?></strong></td>
<td><?= number_format($r['grade_point'], 2) ?></td>
<td>
<form action="result_delete.php" method="post" style="display:inline" onsubmit="return confirm('Delete this result?');">
<input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
<input type="hidden" name="student_id" value="<?= (int)$studentId ?>">
<button type="submit" class="btn-small btn-danger">Delete</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
<?php endif; ?>
<?php require 'partials/footer.php'; ?>
