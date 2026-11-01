<?php
require 'config.php';
require 'auth.php';
require 'helpers.php';
require_lecturer();

$uid = current_user_id();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$error = '';

$session = lecturer_session($conn, $uid, $id);
if (!$session) {
    die('Session not found or you do not teach this course.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $session_date = $_POST['session_date'] ?? '';
    $start_time   = $_POST['start_time'] ?? '';
    $end_time     = $_POST['end_time'] ?? '';
    $venue        = trim($_POST['venue'] ?? '');
    $class_type   = $_POST['class_type'] ?? '';

    if ($session_date === '' || $start_time === '' || $end_time === '' || $venue === '') {
        $error = 'All fields are required.';
    } elseif (!in_array($class_type, class_types(), true)) {
        $error = 'Please choose a valid class type.';
    } elseif ($end_time <= $start_time) {
        $error = 'End time must be after the start time.';
    } elseif (!semester_for_date($conn, $session_date)) {
        $error = 'That date is not within any semester.';
    } else {
        $stmt = $conn->prepare('UPDATE class_sessions SET session_date=?, start_time=?, end_time=?, venue=?, class_type=? WHERE id=?');
        $stmt->bind_param('sssssi', $session_date, $start_time, $end_time, $venue, $class_type, $id);
        $stmt->execute();
        $stmt->close();
        header('Location: session_view.php?id=' . $id);
        exit;
    }
    $session = array_merge($session, compact('session_date', 'start_time', 'end_time', 'venue', 'class_type'));
}

$pageTitle = 'Edit Session';
require 'partials/header.php';
?>
<div class="form-card">
<h1>Edit Session</h1>
<?php if ($error): ?><p class="alert alert-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form method="post">
<input type="hidden" name="id" value="<?= (int)$session['id'] ?>">
<label>Course <input type="text" value="<?= htmlspecialchars($session['code']) ?> <?= htmlspecialchars($session['course_name']) ?>" disabled></label>
<label>Class Type <span class="required-mark">*</span>
<select name="class_type" required>
<?php foreach (class_types() as $t): ?>
<option value="<?= $t ?>" <?= $session['class_type'] === $t ? 'selected' : '' ?>><?= $t ?></option>
<?php endforeach; ?>
</select>
</label>
<label>Date <span class="required-mark">*</span> <input type="date" name="session_date" value="<?= htmlspecialchars($session['session_date']) ?>" required></label>
<label>Start Time <span class="required-mark">*</span> <input type="time" name="start_time" value="<?= htmlspecialchars(substr($session['start_time'], 0, 5)) ?>" required></label>
<label>End Time <span class="required-mark">*</span> <input type="time" name="end_time" value="<?= htmlspecialchars(substr($session['end_time'], 0, 5)) ?>" required></label>
<label>Venue <span class="required-mark">*</span> <input type="text" name="venue" value="<?= htmlspecialchars($session['venue']) ?>" required></label>
<button type="submit">Update Session</button>
</form>
<p><a class="btn btn-secondary btn-small" href="session_view.php?id=<?= (int)$session['id'] ?>">Back to session</a></p>
</div>
<?php require 'partials/footer.php'; ?>
