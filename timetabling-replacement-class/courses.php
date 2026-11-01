<?php
require 'config.php';
require 'auth.php';
require 'helpers.php';

$semester = default_semester($conn);
$semesterId = (int)($semester['id'] ?? 0);

$courses = $conn->query('
    SELECT c.*, u.name AS lecturer_name
    FROM courses c LEFT JOIN users u ON u.id = c.lecturer_id
    ORDER BY c.code
')->fetch_all(MYSQLI_ASSOC);

$entriesByCourse = [];
$stmt = $conn->prepare('
    SELECT t.course_id, t.day_of_week, t.class_type, ts.start_time, ts.end_time, v.name AS venue_name
    FROM timetable_entries t
    JOIN time_slots ts ON ts.id = t.time_slot_id
    JOIN venues v ON v.id = t.venue_id
    WHERE t.semester_id = ?
    ORDER BY t.day_of_week, ts.start_time
');
$stmt->bind_param('i', $semesterId);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
foreach ($rows as $row) {
    $entriesByCourse[(int)$row['course_id']][] = $row;
}

$totalCourses = count($courses);
$totalClasses = count($rows);
$venueCount = (int)$conn->query('SELECT COUNT(*) AS c FROM venues')->fetch_assoc()['c'];

$pageTitle = 'Courses';
require 'partials/header.php';
?>
<div class="page-header">
<h1>Courses</h1>
<p>Every course, with its regular weekly classes for <?= $semester ? htmlspecialchars(semester_label($semester)) : 'the current semester' ?>.</p>
</div>

<section>
<div class="card-grid">
<div class="card"><h3><?= (int)$totalCourses ?></h3><p>Courses</p></div>
<div class="card"><h3><?= (int)$totalClasses ?></h3><p>Classes per week</p></div>
<div class="card"><h3><?= (int)$venueCount ?></h3><p>Teaching venues</p></div>
</div>
</section>

<?php foreach ($courses as $c): ?>
<section>
<div class="card" style="max-width:720px;">
<span class="badge badge-accent"><?= htmlspecialchars($c['code']) ?></span>
<h3><?= htmlspecialchars($c['name']) ?></h3>
<p>&#128100; <?= htmlspecialchars($c['lecturer_name'] ?? 'Lecturer to be assigned') ?></p>
<?php if (empty($entriesByCourse[(int)$c['id']])): ?>
<p>No classes timetabled yet.</p>
<?php else: ?>
<?php foreach ($entriesByCourse[(int)$c['id']] as $e): ?>
<p>&#128197; <?= htmlspecialchars(weekdays()[(int)$e['day_of_week']]) ?> &middot; <?= htmlspecialchars(time_slot_label($e)) ?> 
&middot; <?= htmlspecialchars($e['class_type']) ?> &middot; <?= htmlspecialchars($e['venue_name']) ?></p>
<?php endforeach; ?>
<?php endif; ?>
<a class="btn" href="timetable.php?course_id=<?= (int)$c['id'] ?>">View Timetable</a>
</div>
</section>
<?php endforeach; ?>
<?php require 'partials/footer.php'; ?>
