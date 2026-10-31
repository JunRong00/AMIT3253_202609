<?php
require 'config.php';
require 'auth.php';
require 'helpers.php';

$courseId = (int)($_GET['course_id'] ?? 0);
$week = $_GET['week'] ?? date('Y-m-d');
if (!strtotime($week)) {
    $week = date('Y-m-d');
}
$monday = monday_of($week);
$friday = date('Y-m-d', strtotime($monday . ' +4 days'));

$dates = [];
foreach (weekdays() as $dow => $dayName) {
    $dates[$dow] = date('Y-m-d', strtotime($monday . ' +' . ($dow - 1) . ' days'));
}

$slots = $conn->query('SELECT * FROM time_slots ORDER BY start_time')->fetch_all(MYSQLI_ASSOC);
$courses = $conn->query('SELECT id, code, name FROM courses ORDER BY code')->fetch_all(MYSQLI_ASSOC);

$entrySql = '
    SELECT t.*, c.code, c.name AS course_name, v.name AS venue_name
    FROM timetable_entries t
    JOIN courses c ON c.id = t.course_id
    JOIN venues v ON v.id = t.venue_id
';
$replacementSql = "
    SELECT r.*, c.code, c.name AS course_name, v.name AS venue_name
    FROM replacement_classes r
    JOIN courses c ON c.id = r.course_id
    JOIN venues v ON v.id = r.venue_id
    WHERE r.status = 'Approved' AND r.replacement_date BETWEEN ? AND ?
";
$cancelledSql = "
    SELECT course_id, original_date FROM replacement_classes
    WHERE status = 'Approved' AND original_date BETWEEN ? AND ?
";
if ($courseId > 0) {
    $stmt = $conn->prepare($entrySql . ' WHERE t.course_id = ?');
    $stmt->bind_param('i', $courseId);
    $stmt->execute();
    $entries = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $stmt = $conn->prepare($replacementSql . ' AND r.course_id = ?');
    $stmt->bind_param('ssi', $monday, $friday, $courseId);
} else {
    $entries = $conn->query($entrySql)->fetch_all(MYSQLI_ASSOC);
    $stmt = $conn->prepare($replacementSql);
    $stmt->bind_param('ss', $monday, $friday);
}
$stmt->execute();
$replacements = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// A regular class whose date this week has an approved replacement is
// shown struck through, so students know not to turn up.
$stmt = $conn->prepare($cancelledSql);
$stmt->bind_param('ss', $monday, $friday);
$stmt->execute();
$cancelled = [];
foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
    $cancelled[$row['course_id'] . '|' . $row['original_date']] = true;
}
$stmt->close();

$grid = [];
foreach ($entries as $e) {
    $date = $dates[(int)$e['day_of_week']] ?? null;
    if ($date === null) continue;
    $e['cancelled'] = isset($cancelled[$e['course_id'] . '|' . $date]);
    $e['replacement'] = false;
    $grid[(int)$e['time_slot_id']][(int)$e['day_of_week']][] = $e;
}
foreach ($replacements as $r) {
    $r['cancelled'] = false;
    $r['replacement'] = true;
    $grid[(int)$r['time_slot_id']][day_of_week($r['replacement_date'])][] = $r;
}

$prevWeek = date('Y-m-d', strtotime($monday . ' -7 days'));
$nextWeek = date('Y-m-d', strtotime($monday . ' +7 days'));
$query = fn($w) => 'timetable.php?' . http_build_query(['course_id' => $courseId, 'week' => $w]);

$pageTitle = 'Weekly Timetable';
require 'partials/header.php';
?>
<div class="page-header">
<h1>Weekly Timetable</h1>
<p>Week of <?= htmlspecialchars(date('d M', strtotime($monday))) ?> &ndash; <?= htmlspecialchars(date('d M Y', strtotime($friday))) ?>.
Approved replacement classes are shown in green; the classes they replace are struck through.</p>
</div>

<form method="get" class="filter-bar">
<label>Course
<select name="course_id" onchange="this.form.submit()">
<option value="0">All courses</option>
<?php foreach ($courses as $c): ?>
<option value="<?= (int)$c['id'] ?>" <?= (int)$c['id'] === $courseId ? 'selected' : '' ?>><?= htmlspecialchars($c['code']) ?> <?= htmlspecialchars($c['name']) ?></option>
<?php endforeach; ?>
</select>
</label>
<label>Week of <input type="date" name="week" value="<?= htmlspecialchars($monday) ?>" onchange="this.form.submit()"></label>
<a class="btn btn-secondary" href="<?= htmlspecialchars($query($prevWeek)) ?>">&larr; Previous</a>
<a class="btn btn-secondary" href="<?= htmlspecialchars($query(date('Y-m-d'))) ?>">This Week</a>
<a class="btn btn-secondary" href="<?= htmlspecialchars($query($nextWeek)) ?>">Next &rarr;</a>
</form>

<table class="grid-table">
<tr>
<th>Time</th>
<?php foreach (weekdays() as $dow => $dayName): ?>
<th><?= htmlspecialchars($dayName) ?><br><?= htmlspecialchars(date('d M', strtotime($dates[$dow]))) ?></th>
<?php endforeach; ?>
</tr>
<?php foreach ($slots as $slot): ?>
<tr>
<td><?= htmlspecialchars(time_slot_label($slot)) ?></td>
<?php foreach (weekdays() as $dow => $dayName): ?>
<td>
<?php foreach ($grid[(int)$slot['id']][$dow] ?? [] as $cls): ?>
<span class="grid-cell<?= $cls['replacement'] ? ' grid-cell-replacement' : '' ?><?= $cls['cancelled'] ? ' grid-cell-cancelled' : '' ?>">
<strong><?= htmlspecialchars($cls['code']) ?></strong>
<?= htmlspecialchars($cls['class_type']) ?> &middot; <?= htmlspecialchars($cls['venue_name']) ?>
<?php if ($cls['replacement']): ?><br>Replacement class<?php endif; ?>
<?php if ($cls['cancelled']): ?><br>Replaced<?php endif; ?>
</span>
<?php endforeach; ?>
</td>
<?php endforeach; ?>
</tr>
<?php endforeach; ?>
</table>
<?php require 'partials/footer.php'; ?>
