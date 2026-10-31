<?php
require '../config.php';
require '../auth.php';
require '../helpers.php';
require_admin();

$courseId = (int)($_GET['course_id'] ?? $_POST['course_id'] ?? 0);
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $studentIds = array_map('intval', (array)($_POST['student_ids'] ?? []));

    if ($courseId < 1 || empty($studentIds)) {
        $error = 'Please choose a course and at least one student.';
    } else {
        // INSERT IGNORE + the uniq_enrolment key skips anyone already
        // enrolled. The SELECT only lets real students (not lecturers or
        // admins) through.
        $stmt = $conn->prepare("
            INSERT IGNORE INTO enrolments (user_id, course_id)
            SELECT u.id, c.id FROM users u JOIN courses c ON c.id = ?
            WHERE u.id = ? AND u.role = 'student' AND u.is_admin = 0
        ");
        $added = 0;
        foreach ($studentIds as $studentId) {
            $stmt->bind_param('ii', $courseId, $studentId);
            $stmt->execute();
            $added += $stmt->affected_rows;
        }
        $stmt->close();
        $success = "Enrolled $added student" . ($added === 1 ? '' : 's') . '.';
    }
}

$courses = $conn->query('
    SELECT c.id, c.code, c.name, (SELECT COUNT(*) FROM enrolments e WHERE e.course_id = c.id) AS enrolled
    FROM courses c ORDER BY c.code
')->fetch_all(MYSQLI_ASSOC);

$enrolled = [];
$available = [];
if ($courseId > 0) {
    $stmt = $conn->prepare('
        SELECT e.id AS enrolment_id, e.created_at, u.id_number, u.name, u.email, u.faculty,
               (SELECT COUNT(*) FROM attendance a JOIN class_sessions s ON s.id = a.session_id
                WHERE a.user_id = u.id AND s.course_id = e.course_id) AS check_ins
        FROM enrolments e
        JOIN users u ON u.id = e.user_id
        WHERE e.course_id = ?
        ORDER BY u.name
    ');
    $stmt->bind_param('i', $courseId);
    $stmt->execute();
    $enrolled = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $stmt = $conn->prepare("
        SELECT u.id, u.id_number, u.name
        FROM users u
        WHERE u.role = 'student' AND u.is_admin = 0
          AND u.id NOT IN (SELECT user_id FROM enrolments WHERE course_id = ?)
        ORDER BY u.name
    ");
    $stmt->bind_param('i', $courseId);
    $stmt->execute();
    $available = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$pageTitle = 'Enrolments';
require 'partials/header.php';
?>
<h1>Enrolments</h1>
<p>Enrol students in courses. Students can only check in to sessions of courses they're enrolled in.</p>

<form method="get" class="filter-bar">
<label>Course
<select name="course_id" onchange="this.form.submit()">
<option value="0">-- Select a course --</option>
<?php foreach ($courses as $c): ?>
<option value="<?= (int)$c['id'] ?>" <?= (int)$c['id'] === $courseId ? 'selected' : '' ?>><?= htmlspecialchars($c['code']) ?> <?= htmlspecialchars($c['name']) ?> (<?= (int)$c['enrolled'] ?> enrolled)</option>
<?php endforeach; ?>
</select>
</label>
<noscript><button type="submit">Show</button></noscript>
</form>

<?php if ($courseId > 0): ?>
<?php if ($error): ?><p class="alert alert-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<?php if ($success): ?><p class="alert alert-success"><?= htmlspecialchars($success) ?></p><?php endif; ?>

<section>
<h2>Enrol Students</h2>
<?php if (empty($available)): ?>
<p>Every registered student is already enrolled in this course.</p>
<?php else: ?>
<form method="post" class="form-card" style="max-width:560px; margin:0 0 24px;">
<input type="hidden" name="course_id" value="<?= (int)$courseId ?>">
<label>Students
<select name="student_ids[]" multiple size="<?= min(10, max(4, count($available))) ?>" required>
<?php foreach ($available as $s): ?>
<option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['name']) ?><?= $s['id_number'] ? ' (' . htmlspecialchars($s['id_number']) . ')' : '' ?></option>
<?php endforeach; ?>
</select>
</label>
<p class="form-hint">Hold Ctrl (Cmd on Mac) to select several students at once.</p>
<button type="submit">Enrol Selected</button>
</form>
<?php endif; ?>
</section>

<section>
<h2>Enrolled Students</h2>
<?php if (empty($enrolled)): ?>
<div class="empty-state">
<div class="empty-state-icon">&#128101;</div>
<p>No students are enrolled in this course yet.</p>
</div>
<?php else: ?>
<table>
<tr><th>Student ID</th><th>Name</th><th>Email</th><th>Faculty</th><th>Enrolled On</th><th>Check-Ins</th><th>Actions</th></tr>
<?php foreach ($enrolled as $e): ?>
<tr>
<td><?= htmlspecialchars($e['id_number'] ?? '-') ?></td>
<td><?= htmlspecialchars($e['name']) ?></td>
<td><?= htmlspecialchars($e['email']) ?></td>
<td><?= htmlspecialchars($e['faculty'] ?? '-') ?></td>
<td><?= htmlspecialchars(date('d M Y', strtotime($e['created_at']))) ?></td>
<td><?= (int)$e['check_ins'] ?></td>
<td>
<form action="enrolment_delete.php" method="post" style="display:inline" onsubmit="return confirm('Remove <?= htmlspecialchars(addslashes($e['name'])) ?> from this course? Their past attendance records are kept.');">
<input type="hidden" name="id" value="<?= (int)$e['enrolment_id'] ?>">
<input type="hidden" name="course_id" value="<?= (int)$courseId ?>">
<button type="submit" class="btn-small btn-danger">Remove</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
</section>
<?php endif; ?>
<?php require 'partials/footer.php'; ?>
