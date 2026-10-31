<?php
require 'config.php';
require 'auth.php';
require 'helpers.php';
require_login();

$uid = current_user_id();
$error = '';
$success = '';

if (current_user_is_lecturer()) {
    $error = 'Lecturers don\'t check in - open a session from My Sessions and share its code with your class.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = strtoupper(trim($_POST['code'] ?? ''));

    if (!preg_match('/^[A-Z0-9]{6}$/', $code)) {
        $error = 'Please enter the six-character code shown by your lecturer.';
    } else {
        $conn->begin_transaction();

        // Lock the session row so a lecturer closing check-in at the same
        // moment can't race a student's check-in.
        $stmt = $conn->prepare('
            SELECT s.id, s.course_id, s.start_time, s.open_until, c.code, c.name AS course_name
            FROM class_sessions s
            JOIN courses c ON c.id = s.course_id
            WHERE s.check_in_code = ? AND s.session_date = CURDATE()
            ORDER BY s.open_until DESC
            LIMIT 1
            FOR UPDATE
        ');
        $stmt->bind_param('s', $code);
        $stmt->execute();
        $session = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$session) {
            $error = 'That code doesn\'t match any class today. Check the code with your lecturer.';
            $conn->rollback();
        } elseif (!session_is_open($session)) {
            // Check-in closes automatically when the class ends; only the
            // lecturer can reopen it.
            $error = 'Check-in for ' . $session['code'] . ' ' . $session['course_name'] . ' has closed. Ask your lecturer to reopen it if you were in class.';
            $conn->rollback();
        } else {
            $courseId = (int)$session['course_id'];
            $sessionId = (int)$session['id'];

            $stmt = $conn->prepare('SELECT 1 FROM enrolments WHERE user_id = ? AND course_id = ?');
            $stmt->bind_param('ii', $uid, $courseId);
            $stmt->execute();
            $enrolled = (bool)$stmt->get_result()->fetch_assoc();
            $stmt->close();

            $stmt = $conn->prepare('SELECT status FROM attendance WHERE session_id = ? AND user_id = ?');
            $stmt->bind_param('ii', $sessionId, $uid);
            $stmt->execute();
            $existing = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$enrolled) {
                $error = 'You are not enrolled in ' . $session['code'] . ' ' . $session['course_name'] . '. Ask the Academic Administration Office to enrol you.';
                $conn->rollback();
            } elseif ($existing) {
                $success = 'You have already checked in to ' . $session['code'] . ' ' . $session['course_name'] . ' (' . $existing['status'] . ').';
                $conn->rollback();
            } else {
                $lateAfter = strtotime(date('Y-m-d') . ' ' . $session['start_time']) + LATE_AFTER_MINUTES * 60;
                $status = time() > $lateAfter ? 'Late' : 'Present';

                $stmt = $conn->prepare('INSERT INTO attendance (session_id, user_id, status) VALUES (?, ?, ?)');
                $stmt->bind_param('iis', $sessionId, $uid, $status);
                $stmt->execute();
                $stmt->close();
                $conn->commit();
                $success = 'Checked in to ' . $session['code'] . ' ' . $session['course_name'] . ' at ' . date('g:i a') . ' - marked ' . $status . '.';
            }
        }
    }
}

$pageTitle = 'Check In';
require 'partials/header.php';
?>
<div class="form-card">
<h1>Check In to Class</h1>
<p>Enter the six-character code your lecturer is showing in class.</p>
<?php if ($error): ?><p class="alert alert-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<?php if ($success): ?><p class="alert alert-success"><?= htmlspecialchars($success) ?></p><?php endif; ?>
<?php if (!current_user_is_lecturer()): ?>
<form method="post">
<label>Session Code <span class="required-mark">*</span>
<input type="text" name="code" maxlength="6" placeholder="e.g. DEMO42" autocomplete="off" autofocus class="code-input" required>
</label>
<button type="submit">Check In</button>
</form>
<?php endif; ?>
<p class="form-hint" style="margin-top:16px;">Checking in more than <?= LATE_AFTER_MINUTES ?> minutes after the class starts is recorded as Late.</p>
<p><a class="btn btn-secondary btn-small" href="index.php">Back to home</a></p>
</div>
<?php require 'partials/footer.php'; ?>
