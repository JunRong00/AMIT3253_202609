<?php
require '../config.php';
require '../auth.php';
require '../helpers.php';
require_admin();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $course_id    = (int)($_POST['course_id'] ?? 0);
    $day_of_week  = (int)($_POST['day_of_week'] ?? 0);
    $time_slot_id = (int)($_POST['time_slot_id'] ?? 0);
    $venue_id     = (int)($_POST['venue_id'] ?? 0);
    $class_type   = $_POST['class_type'] ?? '';

    if (!isset(weekdays()[$day_of_week]) || !in_array($class_type, class_types(), true)) {
        $error = 'Please choose a valid day and class type.';
    } else {
        // The uniq_venue_slot key rejects a second class in the same venue,
        // day and slot - no separate check needed, and it's race-free.
        $stmt = $conn->prepare('INSERT INTO timetable_entries (course_id, day_of_week, time_slot_id, venue_id, class_type) VALUES (?, ?, ?, ?, ?)');
        $stmt->bind_param('iiiis', $course_id, $day_of_week, $time_slot_id, $venue_id, $class_type);
        if ($stmt->execute()) {
            $success = 'Class added to the timetable.';
        } else {
            $error = 'That venue already has a class on ' . weekdays()[$day_of_week] . ' at this time.';
        }
        $stmt->close();
    }
}

$entries = $conn->query('
    SELECT t.*, c.code, c.name AS course_name, v.name AS venue_name, ts.start_time, ts.end_time
    FROM timetable_entries t
    JOIN courses c ON c.id = t.course_id
    JOIN venues v ON v.id = t.venue_id
    JOIN time_slots ts ON ts.id = t.time_slot_id
    ORDER BY t.day_of_week, ts.start_time, v.name
')->fetch_all(MYSQLI_ASSOC);

$courses = $conn->query('SELECT id, code, name FROM courses ORDER BY code')->fetch_all(MYSQLI_ASSOC);
$slots = $conn->query('SELECT * FROM time_slots ORDER BY start_time')->fetch_all(MYSQLI_ASSOC);
$venues = $conn->query('SELECT id, name FROM venues ORDER BY name')->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Weekly Timetable';
require 'partials/header.php';
?>
<h1>Weekly Timetable</h1>
<p>The regular classes that repeat every week. Each venue can hold one class per day and time slot.</p>
<?php if ($error): ?><p class="alert alert-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<?php if ($success): ?><p class="alert alert-success"><?= htmlspecialchars($success) ?></p><?php endif; ?>

<form method="post" class="filter-bar">
<label>Course
<select name="course_id" required>
<?php foreach ($courses as $c): ?>
<option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['code']) ?> <?= htmlspecialchars($c['name']) ?></option>
<?php endforeach; ?>
</select>
</label>
<label>Day
<select name="day_of_week" required>
<?php foreach (weekdays() as $dow => $day): ?>
<option value="<?= $dow ?>"><?= $day ?></option>
<?php endforeach; ?>
</select>
</label>
<label>Time Slot
<select name="time_slot_id" required>
<?php foreach ($slots as $s): ?>
<option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars(time_slot_label($s)) ?></option>
<?php endforeach; ?>
</select>
</label>
<label>Venue
<select name="venue_id" required>
<?php foreach ($venues as $v): ?>
<option value="<?= (int)$v['id'] ?>"><?= htmlspecialchars($v['name']) ?></option>
<?php endforeach; ?>
</select>
</label>
<label>Type
<select name="class_type" required>
<?php foreach (class_types() as $t): ?>
<option value="<?= $t ?>"><?= $t ?></option>
<?php endforeach; ?>
</select>
</label>
<button type="submit">+ Add Class</button>
</form>

<?php if (empty($entries)): ?>
<div class="empty-state">
<div class="empty-state-icon">&#128197;</div>
<p>No classes timetabled yet.</p>
</div>
<?php else: ?>
<table>
<tr><th>Day</th><th>Time</th><th>Course</th><th>Type</th><th>Venue</th><th>Actions</th></tr>
<?php foreach ($entries as $e): ?>
<tr>
<td><?= htmlspecialchars(weekdays()[(int)$e['day_of_week']] ?? '-') ?></td>
<td><?= htmlspecialchars(time_slot_label($e)) ?></td>
<td><strong><?= htmlspecialchars($e['code']) ?></strong> <?= htmlspecialchars($e['course_name']) ?></td>
<td><?= htmlspecialchars($e['class_type']) ?></td>
<td><?= htmlspecialchars($e['venue_name']) ?></td>
<td>
<form action="entry_delete.php" method="post" style="display:inline" onsubmit="return confirm('Remove this class from the weekly timetable?');">
<input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
<button type="submit" class="btn-small btn-danger">Remove</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
<?php require 'partials/footer.php'; ?>
