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
$sunday = date('Y-m-d', strtotime($monday . ' +6 days'));

$dates = [];
foreach (weekdays() as $dow => $dayName) {
    $dates[$dow] = date('Y-m-d', strtotime($monday . ' +' . ($dow - 1) . ' days'));
}

$courses = $conn->query('SELECT id, code, name FROM courses ORDER BY code')->fetch_all(MYSQLI_ASSOC);

// Hourly columns from the earliest slot start to the latest slot end.
$range = $conn->query('SELECT MIN(start_time) AS first_start, MAX(end_time) AS last_end FROM time_slots')->fetch_assoc();
$firstHour = (int)substr($range['first_start'] ?? '08:00:00', 0, 2);
$lastHour = (int)ceil(strtotime('1970-01-01 ' . ($range['last_end'] ?? '18:00:00') . ' UTC') / 3600);
$hours = range($firstHour, max($firstHour, $lastHour - 1));

$entrySql = '
    SELECT t.course_id, t.day_of_week, t.class_type, c.code, c.name AS course_name, v.name AS venue_name, v.building,
           u.name AS lecturer_name, ts.start_time, ts.end_time
    FROM timetable_entries t
    JOIN courses c ON c.id = t.course_id
    JOIN venues v ON v.id = t.venue_id
    JOIN time_slots ts ON ts.id = t.time_slot_id
    LEFT JOIN users u ON u.id = c.lecturer_id
';
$replacementSql = "
    SELECT r.course_id, r.replacement_date, r.class_type, c.code, c.name AS course_name, v.name AS venue_name, v.building,
           u.name AS lecturer_name, ts.start_time, ts.end_time
    FROM replacement_classes r
    JOIN courses c ON c.id = r.course_id
    JOIN venues v ON v.id = r.venue_id
    JOIN time_slots ts ON ts.id = r.time_slot_id
    LEFT JOIN users u ON u.id = c.lecturer_id
    WHERE r.status = 'Approved' AND r.replacement_date BETWEEN ? AND ?
";
if ($courseId > 0) {
    $stmt = $conn->prepare($entrySql . ' WHERE t.course_id = ?');
    $stmt->bind_param('i', $courseId);
    $stmt->execute();
    $entries = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $stmt = $conn->prepare($replacementSql . ' AND r.course_id = ?');
    $stmt->bind_param('ssi', $monday, $sunday, $courseId);
} else {
    $entries = $conn->query($entrySql)->fetch_all(MYSQLI_ASSOC);
    $stmt = $conn->prepare($replacementSql);
    $stmt->bind_param('ss', $monday, $sunday);
}
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

$prevWeek = date('Y-m-d', strtotime($monday . ' -7 days'));
$nextWeek = date('Y-m-d', strtotime($monday . ' +7 days'));
$query = fn($w) => 'timetable.php?' . http_build_query(['course_id' => $courseId, 'week' => $w]);
$kindLabel = ['normal' => 'Normal class', 'replacement' => 'Replacement class', 'holiday' => 'Class on public holiday', 'replaced' => 'Replaced - do not attend'];

$pageTitle = 'Weekly Timetable';
require 'partials/header.php';
?>
<div class="page-header">
<h1>Weekly Timetable</h1>
<p>Week of <?= htmlspecialchars(date('d M', strtotime($monday))) ?> &ndash; <?= htmlspecialchars(date('d M Y', strtotime($sunday))) ?>.
Hover over a class for details.</p>
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
