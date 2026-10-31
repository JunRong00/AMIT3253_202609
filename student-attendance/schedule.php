<?php
require 'config.php';
require 'auth.php';
require 'helpers.php';

$uid = current_user_id();

// Students see their enrolled courses, lecturers the courses they teach,
// everyone else every course.
$sql = '
    SELECT s.*, c.code, c.name AS course_name, u.name AS lecturer_name
    FROM class_sessions s
    JOIN courses c ON c.id = s.course_id
    LEFT JOIN users u ON u.id = c.lecturer_id
';
if ($uid && current_user_is_lecturer()) {
    $stmt = $conn->prepare($sql . ' WHERE s.session_date >= CURDATE() AND c.lecturer_id = ? ORDER BY s.session_date, s.start_time');
    $stmt->bind_param('i', $uid);
    $scope = 'the courses you teach';
} elseif ($uid && !current_user_is_admin()) {
    $stmt = $conn->prepare($sql . ' JOIN enrolments e ON e.course_id = s.course_id AND e.user_id = ? WHERE s.session_date >= CURDATE() ORDER BY s.session_date, s.start_time');
    $stmt->bind_param('i', $uid);
    $scope = 'the courses you are enrolled in';
} else {
    $stmt = $conn->prepare($sql . ' WHERE s.session_date >= CURDATE() ORDER BY s.session_date, s.start_time');
    $scope = 'every course';
}
$stmt->execute();
$sessions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$pageTitle = 'Class Schedule';
require 'partials/header.php';
?>
<div class="page-header">
<h1>Class Schedule</h1>
<p>Upcoming class sessions for <?= htmlspecialchars($scope) ?>.</p>
</div>

<?php if (empty($sessions)): ?>
<div class="empty-state">
<div class="empty-state-icon">&#128197;</div>
<p>No upcoming sessions scheduled.</p>
</div>
<?php else: ?>
<table>
<tr><th>Date</th><th>Time</th><th>Course</th><th>Type</th><th>Venue</th><th>Lecturer</th><th>Check-In</th></tr>
<?php foreach ($sessions as $s): ?>
<tr>
<td><?= htmlspecialchars(date('D, d M Y', strtotime($s['session_date']))) ?></td>
<td><?= htmlspecialchars(format_time_range($s['start_time'], $s['end_time'])) ?></td>
<td><strong><?= htmlspecialchars($s['code']) ?></strong> <?= htmlspecialchars($s['course_name']) ?></td>
<td><?= htmlspecialchars($s['class_type']) ?></td>
<td><?= htmlspecialchars($s['venue']) ?></td>
<td><?= htmlspecialchars($s['lecturer_name'] ?? '-') ?></td>
<td><?= $s['is_open'] ? '<span class="badge badge-success">Open now</span>' : '<span class="badge badge-neutral">Closed</span>' ?></td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
<?php require 'partials/footer.php'; ?>
