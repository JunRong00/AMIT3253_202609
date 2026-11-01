<?php
require 'config.php';
require 'auth.php';
require 'helpers.php';
require_lecturer();

$id = (int)($_GET['id'] ?? 0);
$session = lecturer_session($conn, current_user_id(), $id);
if (!$session) {
    die('Class not found or you do not teach this course.');
}

$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_error']);

// Students enrolled in this course for the semester this session falls in.
$semesterId = (int)(semester_for_date($conn, $session['session_date'])['id'] ?? 0);

// Enrolled students who haven't checked in yet.
$stmt = $conn->prepare('
    SELECT u.id_number, u.name, u.email
    FROM enrolments e
    JOIN users u ON u.id = e.user_id
    LEFT JOIN attendance a ON a.session_id = ? AND a.user_id = e.user_id
    WHERE e.course_id = ? AND e.semester_id = ? AND a.id IS NULL
    ORDER BY u.name
');
$courseId = (int)$session['course_id'];
$stmt->bind_param('iii', $id, $courseId, $semesterId);
$stmt->execute();
$notCheckedIn = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt = $conn->prepare('SELECT COUNT(*) AS c FROM enrolments WHERE course_id = ? AND semester_id = ?');
$stmt->bind_param('ii', $courseId, $semesterId);
$stmt->execute();
$enrolled = (int)$stmt->get_result()->fetch_assoc()['c'];
$stmt->close();

$isToday = $session['session_date'] === date('Y-m-d');

$pageTitle = 'Class - ' . $session['code'];
require 'partials/header.php';
?>
<div class="page-header">
<div class="page-header-top">
<div>
<h1><?= htmlspecialchars($session['code']) ?> <?= htmlspecialchars($session['course_name']) ?></h1>
<p><?= htmlspecialchars($session['class_type']) ?> &middot; <?= htmlspecialchars(date('D, d M Y', strtotime($session['session_date']))) ?> &middot; <?= htmlspecialchars(format_time_range($session['start_time'], $session['end_time'])) ?> &middot; <?= htmlspecialchars($session['venue']) ?></p>
</div>
<a class="btn btn-secondary" href="session_edit.php?id=<?= (int)$session['id'] ?>">Edit Class</a>
</div>
</div>
<?php if ($flashError): ?><p class="alert alert-error"><?= htmlspecialchars($flashError) ?></p><?php endif; ?>

<section>
<div class="card-grid">
<div class="card">
<h3>Check-In</h3>
<?php if (session_is_open($session)): ?>
<p><span class="badge badge-success">Open</span>
<?= strtotime($session['open_until']) > session_end_timestamp($session) ? 'Reopened' : 'Open' ?> until
<strong><?= htmlspecialchars(date('g:i a', strtotime($session['open_until']))) ?></strong>, then closes automatically. Show this code to the class:</p>
<div class="code-display"><?= htmlspecialchars($session['check_in_code']) ?></div>
<form action="session_toggle.php" method="post">
<input type="hidden" name="id" value="<?= (int)$session['id'] ?>">
<button type="submit" class="btn btn-danger">Close Check-In</button>
</form>
<?php else: ?>
<p><span class="badge badge-neutral">Closed</span></p>
<?php if ($isToday && session_end_timestamp($session) > time()): ?>
<p>Opening check-in generates a fresh six-character code. It closes automatically at
<?= htmlspecialchars(date('g:i a', session_end_timestamp($session))) ?>, when the class ends.</p>
<form action="session_toggle.php" method="post">
<input type="hidden" name="id" value="<?= (int)$session['id'] ?>">
<button type="submit">Open Check-In</button>
</form>
<?php elseif ($isToday): ?>
<p>This class has ended. You can reopen check-in for <?= REOPEN_MINUTES ?> minutes with a fresh code,
e.g. for a student who had trouble checking in.</p>
<form action="session_toggle.php" method="post">
<input type="hidden" name="id" value="<?= (int)$session['id'] ?>">
<button type="submit">Reopen for <?= REOPEN_MINUTES ?> Minutes</button>
</form>
<?php else: ?>
<p>Check-in can be opened on the day of the class.</p>
<?php endif; ?>
<?php endif; ?>
</div>
<div class="card">
<h3>Checked In</h3>
<p class="stat-value"><span id="checked-in-count">&hellip;</span> / <?= (int)$enrolled ?></p>
<p class="stat-label">Updates automatically every 5 seconds.</p>
</div>
</div>
</section>

<section>
<h2>Checked In</h2>
<table id="check-in-table">
<tr><th>Student ID</th><th>Name</th><th>Time</th><th>Status</th></tr>
</table>
<div class="empty-state" id="check-in-empty" style="display:none;">
<div class="empty-state-icon">&#9203;</div>
<p>No one has checked in yet.</p>
</div>
</section>

<section>
<h2>Not Yet Checked In</h2>
<?php if (empty($notCheckedIn)): ?>
<div class="empty-state">
<div class="empty-state-icon">&#127881;</div>
<p>Every enrolled student has checked in.</p>
</div>
<?php else: ?>
<table>
<tr><th>Student ID</th><th>Name</th><th>Email</th></tr>
<?php foreach ($notCheckedIn as $s): ?>
<tr>
<td><?= htmlspecialchars($s['id_number'] ?? '-') ?></td>
<td><?= htmlspecialchars($s['name']) ?></td>
<td><?= htmlspecialchars($s['email']) ?></td>
</tr>
<?php endforeach; ?>
</table>
<p class="form-hint">This list is as of page load &mdash; <a href="session_view.php?id=<?= (int)$session['id'] ?>">refresh</a> to update it.</p>
<?php endif; ?>
</section>

<script>
(function () {
    var table = document.getElementById('check-in-table');
    var empty = document.getElementById('check-in-empty');
    var count = document.getElementById('checked-in-count');
    var badgeClass = { Present: 'badge-success', Late: 'badge-warning' };

    function cell(text) {
        var td = document.createElement('td');
        td.textContent = text;
        return td;
    }

    function refresh() {
        fetch('session_status.php?id=<?= (int)$session['id'] ?>')
            .then(function (res) { return res.json(); })
            .then(function (data) {
                count.textContent = data.checked_in;
                while (table.rows.length > 1) {
                    table.deleteRow(1);
                }
                data.check_ins.forEach(function (c) {
                    var tr = document.createElement('tr');
                    tr.appendChild(cell(c.id_number || '-'));
                    tr.appendChild(cell(c.name));
                    tr.appendChild(cell(c.time));
                    var td = document.createElement('td');
                    var badge = document.createElement('span');
                    badge.className = 'badge ' + (badgeClass[c.status] || 'badge-neutral');
                    badge.textContent = c.status;
                    td.appendChild(badge);
                    tr.appendChild(td);
                    table.appendChild(tr);
                });
                table.style.display = data.check_ins.length ? '' : 'none';
                empty.style.display = data.check_ins.length ? 'none' : '';
            })
            .catch(function () { /* try again on the next tick */ });
    }

    refresh();
    setInterval(refresh, 5000);
})();
</script>
<p><a class="btn btn-secondary btn-small" href="sessions.php">Back to my classes</a></p>
<?php require 'partials/footer.php'; ?>
