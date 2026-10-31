<?php
require 'config.php';
require 'auth.php';
require 'helpers.php';
require_lecturer();

$uid = current_user_id();
$error = '';
$form = [
    'course_id'    => (int)($_GET['course_id'] ?? 0),
    'session_date' => date('Y-m-d'),
    'start_time'   => '',
    'end_time'     => '',
    'venue'        => '',
    'class_type'   => 'Lecture',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $course_id    = (int)($_POST['course_id'] ?? 0);
    $session_date = $_POST['session_date'] ?? '';
    $start_time   = $_POST['start_time'] ?? '';
    $end_time     = $_POST['end_time'] ?? '';
    $venue        = trim($_POST['venue'] ?? '');
    $class_type   = $_POST['class_type'] ?? '';
    $form = compact('course_id', 'session_date', 'start_time', 'end_time', 'venue', 'class_type');

    if ($session_date === '' || $start_time === '' || $end_time === '' || $venue === '') {
        $error = 'All fields are required.';
    } elseif (!lecturer_owns_course($conn, $uid, $course_id)) {
        $error = 'You can only schedule sessions for courses you teach.';
    } elseif (!in_array($class_type, class_types(), true)) {
        $error = 'Please choose a valid class type.';
    } elseif ($session_date < date('Y-m-d')) {
        $error = 'Session date cannot be in the past.';
    } elseif ($end_time <= $start_time) {
        $error = 'End time must be after the start time.';
    } else {
        $stmt = $conn->prepare('INSERT INTO class_sessions (course_id, session_date, start_time, end_time, venue, class_type) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('isssss', $course_id, $session_date, $start_time, $end_time, $venue, $class_type);
        $stmt->execute();
        $newId = $stmt->insert_id;
        $stmt->close();
        header('Location: session_view.php?id=' . $newId);
        exit;
    }
}

$stmt = $conn->prepare('SELECT id, code, name FROM courses WHERE lecturer_id = ? ORDER BY code');
$stmt->bind_param('i', $uid);
$stmt->execute();
$myCourses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$pageTitle = 'Schedule Session';
require 'partials/header.php';
?>
<div class="form-card">
<h1>Schedule a Session</h1>
<?php if ($error): ?><p class="alert alert-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<?php if (empty($myCourses)): ?>
<p class="alert alert-error">No courses have been assigned to you yet. Ask an admin to assign you as a course lecturer.</p>
<?php else: ?>
<form method="post">
<label>Course <span class="required-mark">*</span>
<select name="course_id" required>
<?php foreach ($myCourses as $c): ?>
<option value="<?= (int)$c['id'] ?>" <?= (int)$c['id'] === (int)$form['course_id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['code']) ?> <?= htmlspecialchars($c['name']) ?></option>
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
<label>Date <span class="required-mark">*</span> <input type="date" name="session_date" min="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($form['session_date']) ?>" required></label>
<label>Start Time <span class="required-mark">*</span> <input type="time" name="start_time" value="<?= htmlspecialchars($form['start_time']) ?>" required></label>
<label>End Time <span class="required-mark">*</span> <input type="time" name="end_time" value="<?= htmlspecialchars($form['end_time']) ?>" required></label>
<label>Venue <span class="required-mark">*</span> <input type="text" name="venue" placeholder="e.g. Lecture Hall DK A" value="<?= htmlspecialchars($form['venue']) ?>" required></label>
<button type="submit">Schedule Session</button>
</form>
<?php endif; ?>
<p><a class="btn btn-secondary btn-small" href="sessions.php">Back to my sessions</a></p>
</div>
<?php require 'partials/footer.php'; ?>
