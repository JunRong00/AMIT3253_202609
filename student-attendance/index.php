<?php
require 'config.php';
require 'auth.php';
require 'helpers.php';

$uid = current_user_id();
$isLecturer = current_user_is_lecturer();

$todaySessions = [];
$myCourses = [];
$recentCheckIns = [];
$allCourses = [];

if ($uid && $isLecturer) {
    $stmt = $conn->prepare('
        SELECT s.*, c.code, c.name AS course_name,
               (SELECT COUNT(*) FROM attendance a WHERE a.session_id = s.id) AS checked_in,
               (SELECT COUNT(*) FROM enrolments e WHERE e.course_id = s.course_id) AS enrolled
        FROM class_sessions s
        JOIN courses c ON c.id = s.course_id
        WHERE c.lecturer_id = ? AND s.session_date = CURDATE()
        ORDER BY s.start_time
    ');
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $todaySessions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $stmt = $conn->prepare('
        SELECT c.*, (SELECT COUNT(*) FROM enrolments e WHERE e.course_id = c.id) AS enrolled
        FROM courses c WHERE c.lecturer_id = ? ORDER BY c.code
    ');
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $myCourses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} elseif ($uid) {
    $stmt = $conn->prepare('
        SELECT s.*, c.code, c.name AS course_name, a.status AS my_status
        FROM class_sessions s
        JOIN courses c ON c.id = s.course_id
        JOIN enrolments e ON e.course_id = s.course_id AND e.user_id = ?
        LEFT JOIN attendance a ON a.session_id = s.id AND a.user_id = ?
        WHERE s.session_date = CURDATE()
        ORDER BY s.start_time
    ');
    $stmt->bind_param('ii', $uid, $uid);
    $stmt->execute();
    $todaySessions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $stmt = $conn->prepare('
        SELECT a.status, a.checked_in_at, s.session_date, s.start_time, s.end_time, s.class_type, c.code, c.name AS course_name
        FROM attendance a
        JOIN class_sessions s ON s.id = a.session_id
        JOIN courses c ON c.id = s.course_id
        WHERE a.user_id = ?
        ORDER BY a.checked_in_at DESC
        LIMIT 5
    ');
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $recentCheckIns = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $allCourses = $conn->query('
        SELECT c.*, u.name AS lecturer_name
        FROM courses c LEFT JOIN users u ON u.id = c.lecturer_id
        ORDER BY c.code
    ')->fetch_all(MYSQLI_ASSOC);
}

$pageTitle = 'Student Attendance System';
require 'partials/header.php';
?>
<section class="hero">
<h1>Student Attendance System</h1>
<p>Check in to class with a six-character session code &mdash; no more passing a paper list around.</p>
</section>

<?php if (!$uid): ?>
<section>
<h2>Courses</h2>
<div class="card-grid">
<?php foreach ($allCourses as $c): ?>
<div class="card">
<img class="card-thumb" src="<?= htmlspecialchars(entity_image_url($c)) ?>" alt="<?= htmlspecialchars($c['name']) ?>" loading="lazy">
<span class="badge badge-accent"><?= htmlspecialchars($c['code']) ?></span>
<h3><?= htmlspecialchars($c['name']) ?></h3>
<p><?= htmlspecialchars($c['lecturer_name'] ?? 'Lecturer to be assigned') ?></p>
<a class="btn" href="login.php">Login to Check In</a>
</div>
<?php endforeach; ?>
</div>
<p><a href="login.php">Login</a> with the account given to you by the Academic Administration Office to check in to your classes.</p>
</section>

<?php elseif ($isLecturer): ?>
<section>
<h2>Today's Sessions</h2>
<?php if (empty($todaySessions)): ?>
<div class="empty-state">
<div class="empty-state-icon">&#128197;</div>
<p>You have no class sessions today.</p>
<a class="btn btn-small" href="session_create.php">+ Schedule a Session</a>
</div>
<?php else: ?>
<table>
<tr><th>Time</th><th>Course</th><th>Type</th><th>Venue</th><th>Checked In</th><th>Check-In</th><th>Actions</th></tr>
<?php foreach ($todaySessions as $s): ?>
<tr>
<td><?= htmlspecialchars(format_time_range($s['start_time'], $s['end_time'])) ?></td>
<td><strong><?= htmlspecialchars($s['code']) ?></strong> <?= htmlspecialchars($s['course_name']) ?></td>
<td><?= htmlspecialchars($s['class_type']) ?></td>
<td><?= htmlspecialchars($s['venue']) ?></td>
<td><?= (int)$s['checked_in'] ?> / <?= (int)$s['enrolled'] ?></td>
<td><?= session_is_open($s) ? '<span class="badge badge-success">Open</span>' : '<span class="badge badge-neutral">Closed</span>' ?></td>
<td><a class="btn btn-small" href="session_view.php?id=<?= (int)$s['id'] ?>">Manage</a></td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
</section>

<section>
<h2>My Courses</h2>
<?php if (empty($myCourses)): ?>
<div class="empty-state">
<div class="empty-state-icon">&#128218;</div>
<p>No courses have been assigned to you yet. Ask an admin to assign you as a course lecturer.</p>
</div>
<?php else: ?>
<div class="card-grid">
<?php foreach ($myCourses as $c): ?>
<div class="card">
<img class="card-thumb" src="<?= htmlspecialchars(entity_image_url($c)) ?>" alt="<?= htmlspecialchars($c['name']) ?>" loading="lazy">
<span class="badge badge-accent"><?= htmlspecialchars($c['code']) ?></span>
<h3><?= htmlspecialchars($c['name']) ?></h3>
<p><?= (int)$c['enrolled'] ?> student<?= (int)$c['enrolled'] === 1 ? '' : 's' ?> enrolled</p>
<a class="btn" href="session_create.php?course_id=<?= (int)$c['id'] ?>">+ Schedule Session</a>
</div>
<?php endforeach; ?>
</div>
<p><a class="btn btn-secondary btn-small" href="sessions.php">View all my sessions</a></p>
<?php endif; ?>
</section>

<?php else: ?>
<section>
<h2>Check In</h2>
<div class="form-card" style="max-width:560px; margin:0 0 24px;">
<form method="post" action="checkin.php">
<label>Session Code <span class="required-mark">*</span>
<input type="text" name="code" maxlength="6" placeholder="e.g. DEMO42" autocomplete="off" style="text-transform:uppercase; letter-spacing:0.2em; font-size:1.2rem;" required>
</label>
<button type="submit">Check In</button>
</form>
</div>
</section>

<section>
<h2>Today's Classes</h2>
<?php if (empty($todaySessions)): ?>
<div class="empty-state">
<div class="empty-state-icon">&#128197;</div>
<p>You have no classes today. Your enrolled courses are marked on the <a href="courses.php">Courses</a> page.</p>
</div>
<?php else: ?>
<table>
<tr><th>Time</th><th>Course</th><th>Type</th><th>Venue</th><th>Check-In</th><th>My Attendance</th></tr>
<?php foreach ($todaySessions as $s): ?>
<tr>
<td><?= htmlspecialchars(format_time_range($s['start_time'], $s['end_time'])) ?></td>
<td><strong><?= htmlspecialchars($s['code']) ?></strong> <?= htmlspecialchars($s['course_name']) ?></td>
<td><?= htmlspecialchars($s['class_type']) ?></td>
<td><?= htmlspecialchars($s['venue']) ?></td>
<td><?= session_is_open($s) ? '<span class="badge badge-success">Open now</span>' : '<span class="badge badge-neutral">Closed</span>' ?></td>
<td><?= $s['my_status'] ? '<span class="badge ' . attendance_badge_class($s['my_status']) . '">' . htmlspecialchars($s['my_status']) . '</span>' : '<span class="stat-label">Not checked in</span>' ?></td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
</section>

<section>
<h2>Recent Check-Ins</h2>
<?php if (empty($recentCheckIns)): ?>
<div class="empty-state">
<div class="empty-state-icon">&#9989;</div>
<p>You haven't checked in to any classes yet.</p>
</div>
<?php else: ?>
<table>
<tr><th>Date</th><th>Course</th><th>Type</th><th>Checked In At</th><th>Status</th></tr>
<?php foreach ($recentCheckIns as $r): ?>
<tr>
<td><?= htmlspecialchars(date('D, d M Y', strtotime($r['session_date']))) ?></td>
<td><strong><?= htmlspecialchars($r['code']) ?></strong> <?= htmlspecialchars($r['course_name']) ?></td>
<td><?= htmlspecialchars($r['class_type']) ?></td>
<td><?= htmlspecialchars(date('g:i a', strtotime($r['checked_in_at']))) ?></td>
<td><span class="badge <?= attendance_badge_class($r['status']) ?>"><?= htmlspecialchars($r['status']) ?></span></td>
</tr>
<?php endforeach; ?>
</table>
<p><a class="btn btn-secondary btn-small" href="my_attendance.php">View my full attendance record</a></p>
<?php endif; ?>
</section>
<?php endif; ?>
<?php require 'partials/footer.php'; ?>
