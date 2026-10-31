<?php
require 'config.php';
require 'auth.php';
require 'helpers.php';
require_lecturer();

$uid = current_user_id();
$courseId = (int)($_GET['course_id'] ?? 0);
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_error']);

$sql = '
    SELECT s.*, c.code, c.name AS course_name,
           (SELECT COUNT(*) FROM attendance a WHERE a.session_id = s.id) AS checked_in,
           (SELECT COUNT(*) FROM enrolments e WHERE e.course_id = s.course_id) AS enrolled
    FROM class_sessions s
    JOIN courses c ON c.id = s.course_id
    WHERE c.lecturer_id = ?
';
if ($courseId > 0) {
    $stmt = $conn->prepare($sql . ' AND s.course_id = ? ORDER BY s.session_date DESC, s.start_time DESC');
    $stmt->bind_param('ii', $uid, $courseId);
} else {
    $stmt = $conn->prepare($sql . ' ORDER BY s.session_date DESC, s.start_time DESC');
    $stmt->bind_param('i', $uid);
}
$stmt->execute();
$sessions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt = $conn->prepare('SELECT id, code, name FROM courses WHERE lecturer_id = ? ORDER BY code');
$stmt->bind_param('i', $uid);
$stmt->execute();
$myCourses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$pageTitle = 'My Sessions';
require 'partials/header.php';
?>
<div class="page-header">
<div class="page-header-top">
<div>
<h1>My Sessions</h1>
<p>Every class session for the courses you teach. Open a session to let students check in.</p>
</div>
<a class="btn" href="session_create.php<?= $courseId ? '?course_id=' . $courseId : '' ?>">+ Schedule Session</a>
</div>
</div>
<?php if ($flashError): ?><p class="alert alert-error"><?= htmlspecialchars($flashError) ?></p><?php endif; ?>

<form method="get" class="filter-bar">
<label>Course
<select name="course_id" onchange="this.form.submit()">
<option value="0">All my courses</option>
<?php foreach ($myCourses as $c): ?>
<option value="<?= (int)$c['id'] ?>" <?= (int)$c['id'] === $courseId ? 'selected' : '' ?>><?= htmlspecialchars($c['code']) ?> <?= htmlspecialchars($c['name']) ?></option>
<?php endforeach; ?>
</select>
</label>
<noscript><button type="submit">Filter</button></noscript>
</form>

<?php if (empty($sessions)): ?>
<div class="empty-state">
<div class="empty-state-icon">&#128197;</div>
<p>No sessions scheduled yet.</p>
</div>
<?php else: ?>
<table>
<tr><th>Date</th><th>Time</th><th>Course</th><th>Type</th><th>Venue</th><th>Checked In</th><th>Check-In</th><th>Actions</th></tr>
<?php foreach ($sessions as $s): ?>
<tr>
<td><?= htmlspecialchars(date('D, d M Y', strtotime($s['session_date']))) ?></td>
<td><?= htmlspecialchars(format_time_range($s['start_time'], $s['end_time'])) ?></td>
<td><strong><?= htmlspecialchars($s['code']) ?></strong> <?= htmlspecialchars($s['course_name']) ?></td>
<td><?= htmlspecialchars($s['class_type']) ?></td>
<td><?= htmlspecialchars($s['venue']) ?></td>
<td><?= (int)$s['checked_in'] ?> / <?= (int)$s['enrolled'] ?></td>
<td><?= $s['is_open'] ? '<span class="badge badge-success">Open</span>' : '<span class="badge badge-neutral">Closed</span>' ?></td>
<td>
<a class="btn btn-small" href="session_view.php?id=<?= (int)$s['id'] ?>">Manage</a>
<a class="btn btn-secondary btn-small" href="session_edit.php?id=<?= (int)$s['id'] ?>">Edit</a>
<?php if ((int)$s['checked_in'] === 0): ?>
<form action="session_delete.php" method="post" style="display:inline" onsubmit="return confirm('Delete this session?');">
<input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
<button type="submit" class="btn-small btn-danger">Delete</button>
</form>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
<?php require 'partials/footer.php'; ?>
