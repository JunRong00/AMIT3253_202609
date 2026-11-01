<?php
require 'config.php';
require 'auth.php';
require 'helpers.php';
require_lecturer();

$uid = current_user_id();
$error = '';
$form = [
    'course_id'        => (int)($_GET['course_id'] ?? 0),
    'original_entry_id' => 0,
    'original_date'    => '',
    'replacement_date' => date('N') === '7' ? date('Y-m-d', strtotime('+1 day')) : date('Y-m-d'),
    'time_slot_id'     => 0,
    'venue_id'         => 0,
    'class_type'       => 'Lecture',
    'reason'           => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $course_id        = (int)($_POST['course_id'] ?? 0);
    $original_entry_id = (int)($_POST['original_entry_id'] ?? 0);
    $original_date    = $_POST['original_date'] ?? '';
    $replacement_date = $_POST['replacement_date'] ?? '';
    $time_slot_id     = (int)($_POST['time_slot_id'] ?? 0);
    $venue_id         = (int)($_POST['venue_id'] ?? 0);
    $class_type       = $_POST['class_type'] ?? '';
    $reason           = trim($_POST['reason'] ?? '');
    $form = compact('course_id', 'original_entry_id', 'original_date', 'replacement_date', 'time_slot_id', 'venue_id', 'class_type', 'reason');

    if ($reason === '') {
        $error = 'Please give a reason for the replacement class.';
    } elseif (!in_array($class_type, class_types(), true)) {
        $error = 'Please choose a valid class type.';
    } elseif (!lecturer_owns_course($conn, $uid, $course_id)) {
        $error = 'You can only request replacement classes for courses you teach.';
    } else {
        $conn->begin_transaction();

        // Lock the venue row so two lecturers requesting the same venue and
        // slot at the same instant can't both pass the clash check below.
        $stmt = $conn->prepare('SELECT id FROM venues WHERE id = ? FOR UPDATE');
        $stmt->bind_param('i', $venue_id);
        $stmt->execute();
        $venue = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $error = $venue
            ? replacement_error($conn, $course_id, $original_entry_id, $original_date, $replacement_date, $time_slot_id, $venue_id)
            : 'Please choose a valid venue.';

        if ($error !== '') {
            $conn->rollback();
        } else {
            $stmt = $conn->prepare('INSERT INTO replacement_classes (user_id, course_id, original_entry_id, original_date, replacement_date, time_slot_id, venue_id, class_type, reason) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('iiissiiss', $uid, $course_id, $original_entry_id, $original_date, $replacement_date, $time_slot_id, $venue_id, $class_type, $reason);
            $stmt->execute();
            $stmt->close();
            $conn->commit();
            header('Location: index.php#my-requests');
            exit;
        }
    }
}

$stmt = $conn->prepare('SELECT id, code, name FROM courses WHERE lecturer_id = ? ORDER BY code');
$stmt->bind_param('i', $uid);
$stmt->execute();
$myCourses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$slots = $conn->query('SELECT * FROM time_slots ORDER BY start_time')->fetch_all(MYSQLI_ASSOC);
$selectedCourse = (int)$form['course_id'] ?: (int)($myCourses[0]['id'] ?? 0);
$originalClasses = $form['original_date'] !== '' ? regular_classes_on($conn, $selectedCourse, $form['original_date']) : [];
$venues = $conn->query('SELECT * FROM venues ORDER BY name')->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Request Replacement Class';
require 'partials/header.php';
?>
<div class="form-card">
<h1>Request a Replacement Class</h1>
<?php if ($error): ?><p class="alert alert-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<?php if (empty($myCourses)): ?>
<p class="alert alert-error">No courses have been assigned to you yet. Ask an admin to assign you as a course lecturer.</p>
<?php else: ?>
<form method="post">
<label>Course <span class="required-mark">*</span>
<select name="course_id" id="course-select" required>
<?php foreach ($myCourses as $c): ?>
<option value="<?= (int)$c['id'] ?>" <?= (int)$c['id'] === (int)$form['course_id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['code']) ?> <?= htmlspecialchars($c['name']) ?></option>
<?php endforeach; ?>
</select>
</label>
<label>Original Class Date <span class="required-mark">*</span> <input type="date" name="original_date" id="original-date" value="<?= htmlspecialchars($form['original_date']) ?>" required></label>
<label>Original Class <span class="required-mark">*</span>
<select name="original_entry_id" id="original-entry" required>
<?php if (empty($originalClasses)): ?><option value="">-- Pick the original class date first --</option><?php endif; ?>
<?php foreach ($originalClasses as $oc): ?>
<option value="<?= (int)$oc['id'] ?>" data-type="<?= htmlspecialchars($oc['class_type']) ?>" <?= (int)$oc['id'] === (int)$form['original_entry_id'] ? 'selected' : '' ?>><?= htmlspecialchars(original_class_label($oc)) ?></option>
<?php endforeach; ?>
</select>
</label>
<label>Class Type <span class="required-mark">*</span>
<select name="class_type" required>
<?php foreach (class_types() as $t): ?>
<option value="<?= $t ?>" <?= $form['class_type'] === $t ? 'selected' : '' ?>><?= $t ?></option>
<?php endforeach; ?>
</select>
</label>
<label>Replacement Date <span class="required-mark">*</span> <input type="date" name="replacement_date" id="replacement-date" min="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($form['replacement_date']) ?>" required></label>
<label>Time Slot <span class="required-mark">*</span>
<select name="time_slot_id" id="slot-select" required>
<?php foreach ($slots as $s): ?>
<option value="<?= (int)$s['id'] ?>" data-start="<?= htmlspecialchars(substr($s['start_time'], 0, 5)) ?>" <?= (int)$s['id'] === (int)$form['time_slot_id'] ? 'selected' : '' ?>><?= htmlspecialchars(time_slot_label($s)) ?></option>
<?php endforeach; ?>
</select>
</label>
<label>Venue <span class="required-mark">*</span>
<select name="venue_id" id="venue-select" required>
<?php foreach ($venues as $v): ?>
<option value="<?= (int)$v['id'] ?>" <?= (int)$v['id'] === (int)$form['venue_id'] ? 'selected' : '' ?>><?= htmlspecialchars($v['name']) ?> (<?= htmlspecialchars($v['building']) ?>, <?= (int)$v['capacity'] ?> seats)</option>
<?php endforeach; ?>
</select>
</label>
<p class="form-hint" id="availability-hint"></p>
<label>Reason <span class="required-mark">*</span> <textarea name="reason" rows="3" maxlength="500" placeholder="e.g. Attending a conference" required><?= htmlspecialchars($form['reason']) ?></textarea></label>
<button type="submit">Submit Request</button>
</form>
<?php require 'partials/replacement_form_script.php'; ?>
<?php endif; ?>
<p><a class="btn btn-secondary btn-small" href="index.php">Back to home</a></p>
</div>
<?php require 'partials/footer.php'; ?>
