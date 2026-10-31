<?php
require 'config.php';
require 'auth.php';
require 'helpers.php';
require_login();

$uid = current_user_id();

// Every session so far (today included) for the courses this student is
// enrolled in, with their check-in if any. A past session with no check-in
// counts as Absent; today's session still open is "Pending".
$stmt = $conn->prepare('
    SELECT s.session_date, s.start_time, s.end_time, s.class_type, s.venue, s.is_open,
           c.code, c.name AS course_name, a.status, a.checked_in_at
    FROM class_sessions s
    JOIN courses c ON c.id = s.course_id
    JOIN enrolments e ON e.course_id = s.course_id AND e.user_id = ?
    LEFT JOIN attendance a ON a.session_id = s.id AND a.user_id = ?
    WHERE s.session_date <= CURDATE()
    ORDER BY s.session_date DESC, s.start_time DESC
');
$stmt->bind_param('ii', $uid, $uid);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

foreach ($rows as &$row) {
    if ($row['status'] === null) {
        $isToday = $row['session_date'] === date('Y-m-d');
        $row['status'] = $isToday && ($row['is_open'] || $row['end_time'] > date('H:i:s')) ? 'Pending' : 'Absent';
    }
}
unset($row);

$pageTitle = 'My Attendance';
require 'partials/header.php';
?>
<div class="page-header">
<h1>My Attendance</h1>
<p>Every class session so far for the courses you are enrolled in.</p>
</div>

<?php if (empty($rows)): ?>
<div class="empty-state">
<div class="empty-state-icon">&#128203;</div>
<p>No class sessions yet. <a href="courses.php">Enrol in a course</a> to see its sessions here.</p>
</div>
<?php else: ?>
<table>
<tr><th>Date</th><th>Time</th><th>Course</th><th>Type</th><th>Venue</th><th>Checked In At</th><th>Status</th></tr>
<?php foreach ($rows as $r): ?>
<tr>
<td><?= htmlspecialchars(date('D, d M Y', strtotime($r['session_date']))) ?></td>
<td><?= htmlspecialchars(format_time_range($r['start_time'], $r['end_time'])) ?></td>
<td><strong><?= htmlspecialchars($r['code']) ?></strong> <?= htmlspecialchars($r['course_name']) ?></td>
<td><?= htmlspecialchars($r['class_type']) ?></td>
<td><?= htmlspecialchars($r['venue']) ?></td>
<td><?= $r['checked_in_at'] ? htmlspecialchars(date('g:i a', strtotime($r['checked_in_at']))) : '&mdash;' ?></td>
<td><span class="badge <?= attendance_badge_class($r['status']) ?>"><?= htmlspecialchars($r['status']) ?></span></td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
<?php require 'partials/footer.php'; ?>
