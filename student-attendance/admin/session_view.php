<?php
require '../config.php';
require '../auth.php';
require '../helpers.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare('
    SELECT s.*, c.code, c.name AS course_name
    FROM class_sessions s JOIN courses c ON c.id = s.course_id
    WHERE s.id = ?
');
$stmt->bind_param('i', $id);
$stmt->execute();
$session = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$session) {
    die('Session not found.');
}

// Full class roster: every enrolled student plus anyone who checked in and
// has since unenrolled, with their status for this session.
$stmt = $conn->prepare('
    SELECT u.id AS user_id, u.id_number, u.name, u.email, a.id AS attendance_id, a.status, a.checked_in_at
    FROM users u
    LEFT JOIN attendance a ON a.user_id = u.id AND a.session_id = ?
    WHERE u.id IN (SELECT user_id FROM enrolments WHERE course_id = ?)
       OR a.id IS NOT NULL
    ORDER BY u.name
');
$courseId = (int)$session['course_id'];
$stmt->bind_param('ii', $id, $courseId);
$stmt->execute();
$roster = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$pageTitle = 'Session Attendance';
require 'partials/header.php';
?>
<h1><?= htmlspecialchars($session['code']) ?> <?= htmlspecialchars($session['course_name']) ?></h1>
<p><?= htmlspecialchars($session['class_type']) ?> &middot; <?= htmlspecialchars(date('D, d M Y', strtotime($session['session_date']))) ?>
&middot; <?= htmlspecialchars(format_time_range($session['start_time'], $session['end_time'])) ?> &middot; <?= htmlspecialchars($session['venue']) ?></p>

<?php if (empty($roster)): ?>
<div class="empty-state">
<div class="empty-state-icon">&#128101;</div>
<p>No students are enrolled in this course.</p>
</div>
<?php else: ?>
<table>
<tr><th>Student ID</th><th>Name</th><th>Email</th><th>Checked In At</th><th>Status</th><th>Actions</th></tr>
<?php foreach ($roster as $r): ?>
<tr>
<td><?= htmlspecialchars($r['id_number'] ?? '-') ?></td>
<td><?= htmlspecialchars($r['name']) ?></td>
<td><?= htmlspecialchars($r['email']) ?></td>
<td><?= $r['checked_in_at'] ? htmlspecialchars(date('g:i:s a', strtotime($r['checked_in_at']))) : '&mdash;' ?></td>
<td><?= $r['status'] ? '<span class="badge ' . attendance_badge_class($r['status']) . '">' . htmlspecialchars($r['status']) . '</span>' : '<span class="stat-label">Not checked in</span>' ?></td>
<td>
<?php if ($r['attendance_id']): ?>
<form action="attendance_delete.php" method="post" style="display:inline" onsubmit="return confirm('Remove this check-in?');">
<input type="hidden" name="id" value="<?= (int)$r['attendance_id'] ?>">
<input type="hidden" name="session_id" value="<?= (int)$session['id'] ?>">
<button type="submit" class="btn-small btn-danger">Remove Check-In</button>
</form>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
<p><a class="btn btn-secondary btn-small" href="sessions.php">Back to sessions</a></p>
<?php require 'partials/footer.php'; ?>
