<?php
require '../config.php';
require '../auth.php';
require '../helpers.php';
require_admin();

// The admin sets up each semester's regular classes for any course;
// lecturers add one-off sessions for their own courses (../session_create.php).
$error = '';
$form = [
    'course_id'    => (int)($_GET['course_id'] ?? 0),
    'session_date' => date('Y-m-d'),
    'start_time'   => '',
    'end_time'     => '',
    'venue'        => '',
    'class_type'   => 'Lecture',
    'weeks'        => 1,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $course_id    = (int)($_POST['course_id'] ?? 0);
    $session_date = $_POST['session_date'] ?? '';
    $start_time   = $_POST['start_time'] ?? '';
    $end_time     = $_POST['end_time'] ?? '';
    $venue        = trim($_POST['venue'] ?? '');
    $class_type   = $_POST['class_type'] ?? '';
    $weeks        = max(1, min(14, (int)($_POST['weeks'] ?? 1)));
    $form = compact('course_id', 'session_date', 'start_time', 'end_time', 'venue', 'class_type', 'weeks');
    $semester     = $session_date !== '' ? semester_for_date($conn, $session_date) : null;

    if ($session_date === '' || $start_time === '' || $end_time === '' || $venue === '') {
        $error = 'All fields are required.';
    } elseif (!course_exists($conn, $course_id)) {
        $error = 'Please choose a valid course.';
    } elseif (!in_array($class_type, class_types(), true)) {
        $error = 'Please choose a valid class type.';
    } elseif ($session_date < date('Y-m-d')) {
        $error = 'Session date cannot be in the past.';
    } elseif ($end_time <= $start_time) {
        $error = 'End time must be after the start time.';
    } elseif (!$semester) {
        $error = 'That date is not within any semester.';
    } else {
        // Repeat on the same weekday for the chosen number of weeks, stopping
        // at the end of the semester.
        $lastDay = semester_end_date($semester);
        $conn->begin_transaction();
        $stmt = $conn->prepare('INSERT INTO class_sessions (course_id, session_date, start_time, end_time, venue, class_type) VALUES (?, ?, ?, ?, ?, ?)');
        $created = 0;
        for ($i = 0; $i < $weeks; $i++) {
            $date = date('Y-m-d', strtotime($session_date . ' +' . ($i * 7) . ' days'));
            if ($date > $lastDay) {
                break;
            }
            $stmt->bind_param('isssss', $course_id, $date, $start_time, $end_time, $venue, $class_type);
            $stmt->execute();
            $newId = $stmt->insert_id;
            $created++;
        }
        $stmt->close();
        $conn->commit();

        $_SESSION['flash_success'] = $created === 1 ? 'Session scheduled.' : "Scheduled $created weekly sessions" . ($created < $weeks ? ' (stopped at the end of ' . semester_label($semester) . ')' : '') . '.';
        header('Location: sessions.php?semester_id=' . (int)$semester['id'] . '&course_id=' . $course_id);
        exit;
    }
}

$myCourses = $conn->query('
    SELECT c.id, c.code, c.name, u.name AS lecturer_name
    FROM courses c LEFT JOIN users u ON u.id = c.lecturer_id
    ORDER BY c.code
')->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Schedule Session';
require 'partials/header.php';
?>
<div class="form-card">
<h1>Schedule a Session</h1>
<?php if ($error): ?><p class="alert alert-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<?php if (empty($myCourses)): ?>
<p class="alert alert-error">Add a course first from <a href="courses.php">Courses</a>.</p>
<?php else: ?>
<form method="post">
<label>Course <span class="required-mark">*</span>
<select name="course_id" required>
<?php foreach ($myCourses as $c): ?>
<option value="<?= (int)$c['id'] ?>" <?= (int)$c['id'] === (int)$form['course_id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['code']) ?> <?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['lecturer_name'] ?? 'no lecturer') ?>)</option>
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
<label>Repeat Weekly <span class="required-mark">*</span>
<select name="weeks">
<option value="1" <?= (int)$form['weeks'] === 1 ? 'selected' : '' ?>>Just this date</option>
<?php for ($w = 2; $w <= 14; $w++): ?>
<option value="<?= $w ?>" <?= (int)$form['weeks'] === $w ? 'selected' : '' ?>>Every week for <?= $w ?> weeks</option>
<?php endfor; ?>
</select>
</label>
<p class="form-hint">Repeats on the same weekday and time, stopping at the end of the semester. Each session can still be edited or deleted on its own.</p>
<button type="submit">Schedule Session</button>
</form>
<?php endif; ?>
<p><a class="btn btn-secondary btn-small" href="sessions.php">Back to sessions</a></p>
</div>
<?php require 'partials/footer.php'; ?>
