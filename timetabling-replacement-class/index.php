<?php
require 'config.php';
require 'auth.php';
require 'helpers.php';

$uid = current_user_id();
$isLecturer = current_user_is_lecturer();
$search = trim($_GET['q'] ?? '');

// Courses relevant to this user in the current semester: a student's
// enrolled courses, a lecturer's own courses with classes this semester,
// or (for admins) every course with classes this semester. The Courses page
// is the full catalogue.
$semester = default_semester($conn);
$semesterId = (int)($semester['id'] ?? 0);

$where = ['EXISTS (SELECT 1 FROM timetable_entries t WHERE t.course_id = c.id AND t.semester_id = ?)'];
$types = 'ii';
$params = [$semesterId, $semesterId];
if ($isLecturer) {
    $where[] = 'c.lecturer_id = ?';
    $types .= 'i';
    $params[] = $uid;
} elseif (!current_user_is_admin()) {
    $where = ['EXISTS (SELECT 1 FROM enrolments e WHERE e.course_id = c.id AND e.user_id = ? AND e.semester_id = ?)'];
    $params = [$uid, $semesterId];
    $types = 'iii';
    array_unshift($params, $semesterId);
}
if ($search !== '') {
    $where[] = '(c.code LIKE ? OR c.name LIKE ?)';
    $likeSearch = '%' . $search . '%';
    $types .= 'ss';
    $params[] = $likeSearch;
    $params[] = $likeSearch;
}
$stmt = $conn->prepare('
    SELECT c.*, u.name AS lecturer_name,
           (SELECT COUNT(*) FROM timetable_entries t WHERE t.course_id = c.id AND t.semester_id = ?) AS classes_per_week
    FROM courses c
    LEFT JOIN users u ON u.id = c.lecturer_id
    WHERE ' . implode(' AND ', $where) . '
    ORDER BY c.code
');
$stmt->bind_param($types, ...$params);
$stmt->execute();
$courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$replacementSql = '
    SELECT r.*, c.code, c.name AS course_name, v.name AS venue_name, ts.start_time, ts.end_time
    FROM replacement_classes r
    JOIN courses c ON c.id = r.course_id
    JOIN venues v ON v.id = r.venue_id
    JOIN time_slots ts ON ts.id = r.time_slot_id
';
$myRequests = [];
$upcoming = [];
if ($uid && $isLecturer) {
    $stmt = $conn->prepare($replacementSql . ' WHERE r.user_id = ? ORDER BY r.replacement_date DESC, ts.start_time');
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $myRequests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} elseif ($uid) {
    [$visibleSql, $types, $params] = replacement_visibility_sql();
    $stmt = $conn->prepare($replacementSql . " WHERE r.status = 'Approved' AND r.replacement_date >= CURDATE() AND $visibleSql ORDER BY r.replacement_date, ts.start_time");
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $upcoming = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$pageTitle = 'Timetabling & Replacement Class';
require 'partials/header.php';
?>
<section class="hero">
<h1>Timetabling &amp; Replacement Class</h1>
<p>Check your weekly timetable and find out about replacement classes as soon as they're approved.</p>
</section>

<section>
<h2><?= current_user_is_admin() ? 'Courses This Semester' : 'My Courses' ?><?= $semester ? ' &middot; ' . htmlspecialchars(semester_label($semester)) : '' ?></h2>
<form method="get" class="filter-bar" id="course-filter-form">
<label>Search <input type="text" name="q" id="course-search" placeholder="Course code or name..." value="<?= htmlspecialchars($search) ?>" autocomplete="off"></label>
<button type="submit">Search</button>
<?php if ($search !== ''): ?><a class="btn btn-secondary" href="index.php">Clear</a><?php endif; ?>
</form>
<script>
(function () {
    var input = document.getElementById('course-search');
    var form = document.getElementById('course-filter-form');
    if (!input || !form) return;
    var timer;
    input.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(function () {
            form.submit();
        }, 500);
    });
})();
</script>

<?php if (empty($courses)): ?>
<div class="empty-state">
<div class="empty-state-icon">&#128269;</div>
<?php if ($search !== ''): ?>
<p>No courses match your search.</p>
<a class="btn btn-small btn-secondary" href="index.php">Clear filters</a>
<?php elseif ($isLecturer): ?>
<p>You have no classes this semester. Ask the Academic Administration Office to assign you as a course lecturer.</p>
<?php elseif (!current_user_is_admin()): ?>
<p>You are not enrolled in any courses this semester. Contact the Academic Administration Office if this is wrong.</p>
<?php else: ?>
<p>No classes are timetabled for this semester yet.</p>
<?php endif; ?>
</div>
<?php else: ?>
<div class="card-grid">
<?php foreach ($courses as $c): ?>
<div class="card">
<span class="badge badge-accent"><?= htmlspecialchars($c['code']) ?></span>
<h3><?= htmlspecialchars($c['name']) ?></h3>
<p><?= htmlspecialchars($c['lecturer_name'] ?? 'Lecturer to be assigned') ?></p>
<p><?= (int)$c['classes_per_week'] ?> class<?= (int)$c['classes_per_week'] === 1 ? '' : 'es' ?> per week this semester</p>
<a class="btn" href="timetable.php?semester_id=<?= $semesterId ?>&amp;course_id=<?= (int)$c['id'] ?>">View Timetable</a>
<?php if ($isLecturer && (int)$c['lecturer_id'] === (int)$uid): ?>
<a class="btn btn-secondary" href="create.php?course_id=<?= (int)$c['id'] ?>">Request Replacement</a>
<?php endif; ?>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</section>

<?php if ($isLecturer): ?>
<section id="my-requests">
<h2>My Replacement Requests</h2>
<?php if (empty($myRequests)): ?>
<div class="empty-state">
<div class="empty-state-icon">&#128197;</div>
<p>You haven't requested any replacement classes yet.</p>
</div>
<?php else: ?>
<table>
<tr><th>Course</th><th>Replaces Class On</th><th>Replacement</th><th>Venue</th><th>Status</th><th>Actions</th></tr>
<?php foreach ($myRequests as $r): ?>
<tr>
<td><strong><?= htmlspecialchars($r['code']) ?></strong> <?= htmlspecialchars($r['course_name']) ?><br><span class="stat-label"><?= htmlspecialchars($r['class_type']) ?></span></td>
<td><?= htmlspecialchars(date('D, d M Y', strtotime($r['original_date']))) ?></td>
<td><?= htmlspecialchars(date('D, d M Y', strtotime($r['replacement_date']))) ?><br><span class="stat-label"><?= htmlspecialchars(time_slot_label($r)) ?></span></td>
<td><?= htmlspecialchars($r['venue_name']) ?></td>
<td><span class="badge <?= status_badge_class($r['status']) ?>"><?= htmlspecialchars($r['status']) ?></span>
<?php if ($r['admin_note']): ?><br><span class="stat-label"><?= htmlspecialchars($r['admin_note']) ?></span><?php endif; ?></td>
<td>
<?php if ($r['status'] === 'Pending'): ?>
<a class="btn btn-secondary btn-small" href="edit.php?id=<?= (int)$r['id'] ?>">Edit</a>
<?php endif; ?>
<?php if ($r['status'] !== 'Approved'): ?>
<form action="delete.php" method="post" style="display:inline" onsubmit="return confirm('Withdraw this request?');">
<input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
<button type="submit" class="btn-small btn-danger">Withdraw</button>
</form>
<?php else: ?>
<span class="stat-label">Contact admin to cancel</span>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
</section>
<?php elseif ($uid): ?>
<section>
<h2>Upcoming Replacement Classes</h2>
<?php if (empty($upcoming)): ?>
<div class="empty-state">
<div class="empty-state-icon">&#128197;</div>
<p>No replacement classes are scheduled.</p>
</div>
<?php else: ?>
<table>
<tr><th>Date</th><th>Time</th><th>Course</th><th>Venue</th><th>Replaces Class On</th></tr>
<?php foreach ($upcoming as $r): ?>
<tr>
<td><?= htmlspecialchars(date('D, d M Y', strtotime($r['replacement_date']))) ?></td>
<td><?= htmlspecialchars(time_slot_label($r)) ?></td>
<td><strong><?= htmlspecialchars($r['code']) ?></strong> <?= htmlspecialchars($r['course_name']) ?><br><span class="stat-label"><?= htmlspecialchars($r['class_type']) ?></span></td>
<td><?= htmlspecialchars($r['venue_name']) ?></td>
<td><?= htmlspecialchars(date('D, d M Y', strtotime($r['original_date']))) ?></td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
</section>
<?php else: ?>
<section>
<p><a href="login.php">Login</a> with the account given to you by the Academic Administration Office to see your timetable and replacement classes.</p>
</section>
<?php endif; ?>
<?php require 'partials/footer.php'; ?>
