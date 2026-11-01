<?php
require 'config.php';
require 'auth.php';
require 'helpers.php';

$uid = current_user_id();
$isLecturer = current_user_is_lecturer();
$isAdmin = current_user_is_admin();

$semesters = semester_options($conn);
$semester = selected_semester($conn, $semesters);
$semesterId = (int)($semester['id'] ?? 0);
$semesterStart = $semester['start_date'] ?? '1970-01-01';
$semesterEnd = $semester ? semester_end_date($semester) : '1970-01-01';

// Students see the courses they're enrolled in that semester, lecturers the
// courses they teach, admins every course.
$sql = '
    SELECT c.*, u.name AS lecturer_name,
           (SELECT COUNT(*) FROM enrolments e WHERE e.course_id = c.id AND e.semester_id = ?) AS enrolled,
           (SELECT COUNT(*) FROM class_sessions s WHERE s.course_id = c.id AND s.session_date BETWEEN ? AND ?) AS session_count
    FROM courses c
    LEFT JOIN users u ON u.id = c.lecturer_id
';
if ($isAdmin) {
    $stmt = $conn->prepare($sql . ' ORDER BY c.code');
    $stmt->bind_param('iss', $semesterId, $semesterStart, $semesterEnd);
} elseif ($isLecturer) {
    $stmt = $conn->prepare($sql . ' WHERE c.lecturer_id = ? ORDER BY c.code');
    $stmt->bind_param('issi', $semesterId, $semesterStart, $semesterEnd, $uid);
} else {
    $stmt = $conn->prepare($sql . ' WHERE EXISTS (SELECT 1 FROM enrolments e2 WHERE e2.course_id = c.id AND e2.user_id = ? AND e2.semester_id = ?) ORDER BY c.code');
    $stmt->bind_param('issii', $semesterId, $semesterStart, $semesterEnd, $uid, $semesterId);
}
$stmt->execute();
$courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$isCurrentSemester = $semester && date('Y-m-d') >= $semesterStart && date('Y-m-d') <= $semesterEnd;

$pageTitle = 'Courses';
require 'partials/header.php';
?>
<div class="page-header">
<h1><?= $isAdmin ? 'Courses' : 'My Courses' ?></h1>
<p><?= $isAdmin ? 'Every course' : ($isLecturer ? 'The courses you teach' : 'The courses you are enrolled in') ?> for the chosen semester.
<?= $isAdmin || $isLecturer ? '' : 'Enrolment is handled by the Academic Administration Office &mdash; contact them if a course is missing.' ?></p>
</div>

<?php if (!$semester): ?>
<div class="empty-state">
<div class="empty-state-icon">&#128197;</div>
<p>No semesters have been set up yet.</p>
</div>
<?php else: ?>
<form method="get" class="filter-bar">
<label>Semester
<select name="semester_id" onchange="this.form.submit()">
<?php foreach ($semesters as $sm): ?>
<option value="<?= (int)$sm['id'] ?>" <?= (int)$sm['id'] === $semesterId ? 'selected' : '' ?>><?= htmlspecialchars(semester_label($sm)) ?></option>
<?php endforeach; ?>
</select>
</label>
<noscript><button type="submit">Show</button></noscript>
</form>

<?php if (empty($courses)): ?>
<div class="empty-state">
<div class="empty-state-icon">&#128218;</div>
<p><?= $isLecturer ? 'No courses have been assigned to you yet.' : 'You are not enrolled in any courses for ' . htmlspecialchars(semester_label($semester)) . '.' ?></p>
</div>
<?php else: ?>
<section>
<div class="card-grid">
<?php foreach ($courses as $c): ?>
<div class="card">
<img class="card-thumb" src="<?= htmlspecialchars(entity_image_url($c)) ?>" alt="<?= htmlspecialchars($c['name']) ?>" loading="lazy">
<span class="badge badge-accent"><?= htmlspecialchars($c['code']) ?></span>
<h3><?= htmlspecialchars($c['name']) ?></h3>
<p><?= htmlspecialchars($c['lecturer_name'] ?? 'Lecturer to be assigned') ?></p>
<p><?= (int)$c['enrolled'] ?> enrolled &middot; <?= (int)$c['session_count'] ?> session<?= (int)$c['session_count'] === 1 ? '' : 's' ?> this semester</p>
<?php if ($isLecturer): ?>
<a class="btn" href="sessions.php?semester_id=<?= $semesterId ?>&amp;course_id=<?= (int)$c['id'] ?>">Manage Sessions</a>
<?php elseif (!$isAdmin && $isCurrentSemester): ?>
<a class="btn" href="checkin.php">Check In</a>
<?php endif; ?>
</div>
<?php endforeach; ?>
</div>
</section>
<?php endif; ?>
<?php endif; ?>
<?php require 'partials/footer.php'; ?>
