<?php
require 'config.php';
require 'auth.php';
require 'helpers.php';

require_login();

$semesters = $conn->query('SELECT * FROM semesters ORDER BY start_date')->fetch_all(MYSQLI_ASSOC);
$semesterId = (int)($_GET['semester_id'] ?? 0);
$semester = null;
foreach ($semesters as $s) {
    if ((int)$s['id'] === $semesterId) {
        $semester = $s;
    }
}
$semester = $semester ?? default_semester($conn);

if (!$semester) {
    $pageTitle = 'Timetable';
    require 'partials/header.php';
    echo '<div class="empty-state"><div class="empty-state-icon">&#128197;</div><p>No semesters have been set up yet.</p></div>';
    require 'partials/footer.php';
    exit;
}
$semesterId = (int)$semester['id'];
$totalWeeks = (int)$semester['weeks'];
$today = date('Y-m-d');
$todayInSemester = $today >= $semester['start_date'] && $today <= semester_end_date($semester);
$weekParam = $_GET['week'] ?? '';
$week = $weekParam === '' ? ($todayInSemester ? semester_week($semester, $today) : 1) : (int)$weekParam;
$week = max(1, min($totalWeeks, $week));

$monday = date('Y-m-d', strtotime($semester['start_date'] . ' +' . (($week - 1) * 7) . ' days'));
$sunday = date('Y-m-d', strtotime($monday . ' +6 days'));

$dates = [];
foreach (weekdays() as $dow => $dayName) {
    $dates[$dow] = date('Y-m-d', strtotime($monday . ' +' . ($dow - 1) . ' days'));
}

// Students see only their enrolled courses, lecturers the courses they
// teach, admins everything.
$visible = visible_course_ids($conn, $semesterId);
// Course IDs come from the database, so they're safe to inline into the
// IN (...) list.
$courseFilter = fn($column) => $visible === null ? '' : ' AND ' . $column . ' IN (' . (implode(',', array_map('intval', $visible)) ?: '0') . ')';

// Hourly columns from the earliest slot start to the latest slot end.
$range = $conn->query('SELECT MIN(start_time) AS first_start, MAX(end_time) AS last_end FROM time_slots')->fetch_assoc();
$firstHour = (int)substr($range['first_start'] ?? '08:00:00', 0, 2);
$lastHour = (int)ceil(strtotime('1970-01-01 ' . ($range['last_end'] ?? '18:00:00') . ' UTC') / 3600);
$hours = range($firstHour, max($firstHour, $lastHour - 1));

$stmt = $conn->prepare('
    SELECT t.course_id, t.day_of_week, t.class_type, c.code, c.name AS course_name, v.name AS venue_name, v.building,
           u.name AS lecturer_name, ts.start_time, ts.end_time
    FROM timetable_entries t
    JOIN courses c ON c.id = t.course_id
    JOIN venues v ON v.id = t.venue_id
    JOIN time_slots ts ON ts.id = t.time_slot_id
    LEFT JOIN users u ON u.id = c.lecturer_id
    WHERE t.semester_id = ?' . $courseFilter('t.course_id'));
$stmt->bind_param('i', $semesterId);
$stmt->execute();
$entries = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt = $conn->prepare("
    SELECT r.course_id, r.replacement_date, r.class_type, c.code, c.name AS course_name, v.name AS venue_name, v.building,
           u.name AS lecturer_name, ts.start_time, ts.end_time
    FROM replacement_classes r
    JOIN courses c ON c.id = r.course_id
    JOIN venues v ON v.id = r.venue_id
    JOIN time_slots ts ON ts.id = r.time_slot_id
    LEFT JOIN users u ON u.id = c.lecturer_id
    WHERE r.status = 'Approved' AND r.replacement_date BETWEEN ? AND ?" . $courseFilter('r.course_id'));
$stmt->bind_param('ss', $monday, $sunday);
$stmt->execute();
$replacements = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// A regular class whose date this week has an approved replacement is shown
// as replaced, so students know not to turn up.
$stmt = $conn->prepare("SELECT course_id, original_date FROM replacement_classes WHERE status = 'Approved' AND original_date BETWEEN ? AND ?");
$stmt->bind_param('ss', $monday, $sunday);
$stmt->execute();
$replaced = [];
foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
    $replaced[$row['course_id'] . '|' . $row['original_date']] = true;
}
$stmt->close();

$stmt = $conn->prepare('SELECT holiday_date, name FROM public_holidays WHERE holiday_date BETWEEN ? AND ?');
$stmt->bind_param('ss', $monday, $sunday);
$stmt->execute();
$holidays = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'name', 'holiday_date');
$stmt->close();

// Build each day's list of class blocks with a kind: normal / replacement /
// holiday (a class that falls on a public holiday) / replaced.
$days = [];
foreach ($entries as $e) {
    $date = $dates[(int)$e['day_of_week']];
    if (isset($replaced[$e['course_id'] . '|' . $date])) {
        $e['kind'] = 'replaced';
    } elseif (isset($holidays[$date])) {
        $e['kind'] = 'holiday';
    } else {
        $e['kind'] = 'normal';
    }
    $days[(int)$e['day_of_week']][] = $e;
}
foreach ($replacements as $r) {
    $r['kind'] = 'replacement';
    $days[day_of_week($r['replacement_date'])][] = $r;
}

// Classes at overlapping times (e.g. two courses in different venues, when
// viewing all courses) go on separate lines within the same day.
$hourOf = fn($time) => (int)substr($time, 0, 2) + (int)substr($time, 3, 2) / 60;
$lanesByDay = [];
foreach (weekdays() as $dow => $dayName) {
    $items = $days[$dow] ?? [];
    usort($items, fn($a, $b) => strcmp($a['start_time'], $b['start_time']));
    $lanes = [];
    foreach ($items as $item) {
        $placed = false;
        foreach (array_keys($lanes) as $i) {
            if (end($lanes[$i])['end_time'] <= $item['start_time']) {
                $lanes[$i][] = $item;
                $placed = true;
                break;
            }
        }
        if (!$placed) {
            $lanes[] = [$item];
        }
    }
    $lanesByDay[$dow] = $lanes ?: [[]];
}

$query = fn($w) => 'timetable.php?' . http_build_query(['semester_id' => $semesterId, 'week' => $w]);
$kindLabel = ['normal' => 'Normal class', 'replacement' => 'Replacement class', 'holiday' => 'Class on public holiday', 'replaced' => 'Replaced - do not attend'];

$pageTitle = 'Semester Timetable';
require 'partials/header.php';
?>
<div class="page-header">
<h1>Semester Timetable</h1>
<p><?= htmlspecialchars(semester_label($semester)) ?> &middot; <strong>Week <?= $week ?> of <?= $totalWeeks ?></strong>
(<?= htmlspecialchars(date('d M', strtotime($monday))) ?> &ndash; <?= htmlspecialchars(date('d M Y', strtotime($sunday))) ?>).
<?= current_user_is_admin() ? 'Showing every course.' : (current_user_is_lecturer() ? 'Showing the courses you teach.' : 'Showing the courses you are enrolled in this semester.') ?> 
&middot; Hover over a class for details.</p>
</div>

<form method="get" class="filter-bar">
<label>Semester
<select name="semester_id" onchange="this.form.week.value=''; this.form.submit()">
<?php foreach ($semesters as $s): ?>
<option value="<?= (int)$s['id'] ?>" <?= (int)$s['id'] === $semesterId ? 'selected' : '' ?>><?= htmlspecialchars(semester_label($s)) ?> (<?= (int)$s['weeks'] ?> weeks)</option>
<?php endforeach; ?>
</select>
</label>
<label>Week
<select name="week" onchange="this.form.submit()">
<?php for ($w = 1; $w <= $totalWeeks; $w++): ?>
<option value="<?= $w ?>" <?= $w === $week ? 'selected' : '' ?>>Week <?= $w ?> (<?= htmlspecialchars(date('d M', strtotime($semester['start_date'] . ' +' . (($w - 1) * 7) . ' days'))) ?>)</option>
<?php endfor; ?>
</select>
</label>
<?php if ($week > 1): ?><a class="btn btn-secondary" href="<?= htmlspecialchars($query($week - 1)) ?>">&larr; Previous</a><?php endif; ?>
<?php if ($todayInSemester): ?><a class="btn btn-secondary" href="<?= htmlspecialchars($query(semester_week($semester, $today))) ?>">This Week</a><?php endif; ?>
<?php if ($week < $totalWeeks): ?><a class="btn btn-secondary" href="<?= htmlspecialchars($query($week + 1)) ?>">Next &rarr;</a><?php endif; ?>
</form>

<?php if ($visible !== null && empty($visible)): ?>
<p class="alert alert-error"><?= current_user_is_lecturer()
    ? 'No courses have been assigned to you yet. Ask the Academic Administration Office to assign you as a course lecturer.'
    : 'You are not enrolled in any courses for this semester. Contact the Academic Administration Office if this is wrong.' ?></p>
<?php endif; ?>

<div class="tt-scroll">
<table class="tt-table">
<tr>
<th rowspan="2" class="tt-day">Day / Time</th>
<?php foreach ($hours as $h): ?><th><?= sprintf('%02d:00', $h > 12 ? $h - 12 : $h) ?></th><?php endforeach; ?>
</tr>
<tr>
<?php foreach ($hours as $h): ?><th><?= sprintf('%02d:00', $h + 1 > 12 ? $h + 1 - 12 : $h + 1) ?></th><?php endforeach; ?>
</tr>
<?php foreach (weekdays() as $dow => $dayName): ?>
<?php $date = $dates[$dow]; $lanes = $lanesByDay[$dow]; ?>
<?php foreach ($lanes as $laneIndex => $lane): ?>
<tr class="<?= $date === date('Y-m-d') ? 'tt-today' : '' ?><?= in_array($dow, teaching_days(), true) ? '' : ' tt-off' ?>">
<?php if ($laneIndex === 0): ?>
<td class="tt-day" rowspan="<?= count($lanes) ?>">
<?= htmlspecialchars(substr($dayName, 0, 3)) ?><br><?= htmlspecialchars($date) ?>
<?php if (isset($holidays[$date])): ?><br><span class="tt-holiday-name"><?= htmlspecialchars($holidays[$date]) ?></span><?php endif; ?>
</td>
<?php endif; ?>
<?php
$col = $firstHour;
foreach ($lane as $cls):
    $start = (int)floor($hourOf($cls['start_time']));
    $span = max(1, (int)ceil($hourOf($cls['end_time']) - $hourOf($cls['start_time'])));
    for (; $col < $start; $col++) echo '<td></td>';
    $tip = $cls['course_name'] . ' (' . $cls['class_type'] . ') ' . ($cls['lecturer_name'] ?? 'Lecturer TBA')
         . ' (' . $cls['venue_name'] . ', ' . $cls['building'] . ') - ' . $kindLabel[$cls['kind']];
?>
<td colspan="<?= $span ?>" class="tt-cell">
<div class="tt-block tt-<?= $cls['kind'] ?>" data-tip="<?= htmlspecialchars($tip) ?>" tabindex="0">
<strong><?= htmlspecialchars($cls['code']) ?> (<?= htmlspecialchars(class_type_letter($cls['class_type'])) ?>)</strong><br>
<?= htmlspecialchars($cls['venue_name']) ?><br>
<?= htmlspecialchars(date('g:i A', strtotime($cls['start_time'])) . ' - ' . date('g:i A', strtotime($cls['end_time']))) ?>
<?php if ($cls['kind'] === 'replaced'): ?><br>Replaced<?php endif; ?>
</div>
</td>
<?php $col = $start + $span; endforeach; ?>
<?php for (; $col <= end($hours); $col++) echo '<td></td>'; ?>
</tr>
<?php endforeach; ?>
<?php endforeach; ?>
</table>
</div>

<div class="tt-legend">
<span><i class="tt-swatch tt-normal"></i> Normal Class</span>
<span><i class="tt-swatch tt-replacement"></i> Replacement Class</span>
<span><i class="tt-swatch tt-holiday"></i> Class on Public Holiday</span>
<span><i class="tt-swatch tt-replaced"></i> Replaced (do not attend)</span>
</div>
<div class="tt-tooltip" id="tt-tooltip"></div>
<script>
(function () {
    var tip = document.getElementById('tt-tooltip');
    function show(e) {
        var block = e.target.closest('.tt-block');
        if (!block) return;
        var rect = block.getBoundingClientRect();
        tip.textContent = block.dataset.tip;
        tip.style.display = 'block';
        var left = Math.min(window.innerWidth - tip.offsetWidth - 8, Math.max(8, rect.left + rect.width / 2 - tip.offsetWidth / 2));
        var top = rect.top - tip.offsetHeight - 8;
        tip.style.left = left + 'px';
        tip.style.top = (top < 8 ? rect.bottom + 8 : top) + 'px';
    }
    function hide(e) {
        if (e.target.closest('.tt-block')) tip.style.display = 'none';
    }
    document.addEventListener('mouseover', show);
    document.addEventListener('focusin', show);
    document.addEventListener('mouseout', hide);
    document.addEventListener('focusout', hide);
    window.addEventListener('scroll', function () { tip.style.display = 'none'; }, true);
})();
</script>
<?php require 'partials/footer.php'; ?>
