<?php
require '../config.php';
require '../auth.php';
require '../helpers.php';
require_admin();

$courseId = (int)($_GET['course_id'] ?? 0);
$flashSuccess = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_success']);
$semesters = semester_options($conn);
$semester = selected_semester($conn, $semesters);
$semesterId = (int)($semester['id'] ?? 0);
$semesterStart = $semester['start_date'] ?? '1970-01-01';
$semesterEnd = $semester ? semester_end_date($semester) : '1970-01-01';

$sql = '
    SELECT s.*, c.code, c.name AS course_name, u.name AS lecturer_name,
           (SELECT COUNT(*) FROM attendance a WHERE a.session_id = s.id) AS checked_in,
           (SELECT COUNT(*) FROM enrolments e WHERE e.course_id = s.course_id AND e.semester_id = ' . semester_id_sql('s.session_date') . ') AS enrolled
    FROM class_sessions s
    JOIN courses c ON c.id = s.course_id
    LEFT JOIN users u ON u.id = c.lecturer_id
';
if ($courseId > 0) {
    $stmt = $conn->prepare($sql . ' WHERE s.session_date BETWEEN ? AND ? AND s.course_id = ? ORDER BY s.session_date DESC, s.start_time DESC');
    $stmt->bind_param('ssi', $semesterStart, $semesterEnd, $courseId);
} else {
    $stmt = $conn->prepare($sql . ' WHERE s.session_date BETWEEN ? AND ? ORDER BY s.session_date DESC, s.start_time DESC');
    $stmt->bind_param('ss', $semesterStart, $semesterEnd);
}
$stmt->execute();
$sessions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$courses = $conn->query('SELECT id, code, name FROM courses ORDER BY code')->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'All Classes';
require 'partials/header.php';
?>
<h1>All Classes</h1>
<p>Every class across all courses. Set up each semester's regular classes here with <strong>Repeat weekly</strong>;
lecturers add one-off sessions for their own courses and open check-in from the public site.</p>
<p><a class="btn btn-small" href="session_create.php<?= $courseId ? '?course_id=' . $courseId : '' ?>">+ Schedule Class</a></p>
<?php if ($flashSuccess): ?><p class="alert alert-success"><?= htmlspecialchars($flashSuccess) ?></p><?php endif; ?>

<form method="get" class="filter-bar">
<label>Semester
<select name="semester_id" onchange="this.form.submit()">
<?php foreach ($semesters as $sm): ?>
<option value="<?= (int)$sm['id'] ?>" <?= (int)$sm['id'] === $semesterId ? 'selected' : '' ?>><?= htmlspecialchars(semester_label($sm)) ?></option>
<?php endforeach; ?>
</select>
</label>
<label>Course
<select name="course_id" onchange="this.form.submit()">
<option value="0">All courses</option>
<?php foreach ($courses as $c): ?>
<option value="<?= (int)$c['id'] ?>" <?= (int)$c['id'] === $courseId ? 'selected' : '' ?>><?= htmlspecialchars($c['code']) ?> <?= htmlspecialchars($c['name']) ?></option>
<?php endforeach; ?>
</select>
</label>
<noscript><button type="submit">Filter</button></noscript>
</form>

<?php if (empty($sessions)): ?>
<div class="empty-state">
<div class="empty-state-icon">&#128197;</div>
<p>No classes scheduled yet.</p>
</div>
<?php else: ?>
<table>
<tr><th>Date</th><th>Time</th><th>Course</th><th>Lecturer</th><th>Venue</th><th>Checked In</th><th>Check-In</th><th>Actions</th></tr>
<?php foreach ($sessions as $s): ?>
<tr>
<td><?= htmlspecialchars(date('D, d M Y', strtotime($s['session_date']))) ?></td>
<td><?= htmlspecialchars(format_time_range($s['start_time'], $s['end_time'])) ?></td>
<td><strong><?= htmlspecialchars($s['code']) ?></strong> <?= htmlspecialchars($s['course_name']) ?><br><span class="stat-label"><?= htmlspecialchars($s['class_type']) ?></span></td>
<td><?= htmlspecialchars($s['lecturer_name'] ?? '-') ?></td>
<td><?= htmlspecialchars($s['venue']) ?></td>
<td><?= (int)$s['checked_in'] ?> / <?= (int)$s['enrolled'] ?></td>
<td><?= session_is_open($s) ? '<span class="badge badge-success">Open</span> <code>' . htmlspecialchars($s['check_in_code']) . '</code>' : '<span class="badge badge-neutral">Closed</span>' ?></td>
<td>
<a class="btn btn-secondary btn-small" href="session_view.php?id=<?= (int)$s['id'] ?>">Attendance</a>
<form action="session_delete.php" method="post" style="display:inline" onsubmit="return confirm('Delete this class AND all <?= (int)$s['checked_in'] ?> attendance record(s) for it? This cannot be undone.');">
<input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
<button type="submit" class="btn-small btn-danger">Delete</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
<?php require 'partials/footer.php'; ?>
