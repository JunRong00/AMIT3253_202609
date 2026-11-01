<?php
require 'config.php';
require 'auth.php';
require 'helpers.php';

// The course catalogue for one semester: every course with classes that
// semester, its lecturer and its regular weekly classes. Everyone sees the
// whole catalogue; each user's own timetable is on timetable.php.
$semesters = $conn->query('SELECT * FROM semesters ORDER BY start_date')->fetch_all(MYSQLI_ASSOC);
$semesterId = (int)($_GET['semester_id'] ?? 0);
$semester = null;
foreach ($semesters as $s) {
    if ((int)$s['id'] === $semesterId) {
        $semester = $s;
    }
}
$semester = $semester ?? default_semester($conn);
$semesterId = (int)($semester['id'] ?? 0);

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

$entriesByCourse = [];
foreach ($rows as $row) {
    $entriesByCourse[(int)$row['course_id']][] = $row;
}

$stmt = $conn->prepare('
    SELECT c.*, u.name AS lecturer_name
    FROM courses c LEFT JOIN users u ON u.id = c.lecturer_id
    WHERE EXISTS (SELECT 1 FROM timetable_entries t WHERE t.course_id = c.id AND t.semester_id = ?)
    ORDER BY c.code
');
$stmt->bind_param('i', $semesterId);
$stmt->execute();
$courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$venuesInUse = count(array_unique(array_column($rows, 'venue_name')));

$pageTitle = 'Courses';
require 'partials/header.php';
?>
<div class="page-header">
<h1>Courses</h1>
<p>The course catalogue for a semester, with each course's lecturer and regular weekly classes.
Your own timetable is on the <a href="timetable.php<?= $semesterId ? '?semester_id=' . $semesterId : '' ?>">Timetable</a> page.</p>
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
<?php foreach ($semesters as $s): ?>
<option value="<?= (int)$s['id'] ?>" <?= (int)$s['id'] === $semesterId ? 'selected' : '' ?>><?= htmlspecialchars(semester_label($s)) ?> (<?= (int)$s['weeks'] ?> weeks)</option>
<?php endforeach; ?>
</select>
</label>
<noscript><button type="submit">Show</button></noscript>
</form>

<section>
<div class="card-grid">
<div class="card"><h3><?= count($courses) ?></h3><p>Courses offered</p></div>
<div class="card"><h3><?= count($rows) ?></h3><p>Classes per week</p></div>
<div class="card"><h3><?= (int)$venuesInUse ?></h3><p>Venues in use</p></div>
</div>
</section>

<?php if (empty($courses)): ?>
<div class="empty-state">
<div class="empty-state-icon">&#128218;</div>
<p>No classes have been timetabled for <?= htmlspecialchars(semester_label($semester)) ?> yet.</p>
</div>
<?php else: ?>
<section>
<div class="card-grid" style="grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));">
<?php foreach ($courses as $c): ?>
<div class="card">
<span class="badge badge-accent"><?= htmlspecialchars($c['code']) ?></span>
<h3><?= htmlspecialchars($c['name']) ?></h3>
<p>&#128100; <?= htmlspecialchars($c['lecturer_name'] ?? 'Lecturer to be assigned') ?></p>
<?php foreach ($entriesByCourse[(int)$c['id']] as $e): ?>
<p>&#128197; <?= htmlspecialchars(substr(weekdays()[(int)$e['day_of_week']], 0, 3)) ?> &middot; <?= htmlspecialchars(time_slot_label($e)) ?> &middot; <?= htmlspecialchars($e['class_type']) ?> &middot; <?= htmlspecialchars($e['venue_name']) ?></p>
<?php endforeach; ?>
</div>
<?php endforeach; ?>
</div>
</section>
<?php endif; ?>
<?php endif; ?>
<?php require 'partials/footer.php'; ?>
