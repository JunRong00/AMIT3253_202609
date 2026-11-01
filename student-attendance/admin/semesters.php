<?php
require '../config.php';
require '../auth.php';
require '../helpers.php';
require_admin();

$error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_error']);
$success = '';
// A year has two long semesters and one short one.
$semesterTypes = ['Long Semester' => 14, 'Short Semester' => 7];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code       = trim($_POST['code'] ?? '');
    $name       = $_POST['name'] ?? '';
    $start_date = $_POST['start_date'] ?? '';

    if (!preg_match('/^\d{4}(0[1-9]|1[0-2])$/', $code)) {
        $error = 'Semester code must be a YYYYMM intake code, e.g. 202609.';
    } elseif (!isset($semesterTypes[$name])) {
        $error = 'Please choose a long or short semester.';
    } elseif ($start_date === '' || date('N', strtotime($start_date)) !== '1') {
        $error = 'The start date must be a Monday (the first day of week 1).';
    } else {
        $weeks = $semesterTypes[$name];
        $end_date = date('Y-m-d', strtotime($start_date . ' +' . ($weeks * 7 - 1) . ' days'));

        $stmt = $conn->prepare('
            SELECT code FROM semesters
            WHERE start_date <= ? AND DATE_ADD(start_date, INTERVAL weeks * 7 - 1 DAY) >= ?
        ');
        $stmt->bind_param('ss', $end_date, $start_date);
        $stmt->execute();
        $overlap = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($overlap) {
            $error = 'These dates overlap semester ' . $overlap['code'] . '.';
        } else {
            $stmt = $conn->prepare('INSERT INTO semesters (code, name, start_date, weeks) VALUES (?, ?, ?, ?)');
            $stmt->bind_param('sssi', $code, $name, $start_date, $weeks);
            if ($stmt->execute()) {
                $success = 'Semester ' . $code . ' added.';
            } else {
                $error = 'A semester with code ' . $code . ' already exists.';
            }
            $stmt->close();
        }
    }
}

$semesters = $conn->query('
    SELECT s.*,
           (SELECT COUNT(*) FROM class_sessions cs WHERE cs.session_date BETWEEN s.start_date AND DATE_ADD(s.start_date, INTERVAL s.weeks * 7 - 1 DAY)) AS classes,
           (SELECT COUNT(*) FROM enrolments e WHERE e.semester_id = s.id) AS enrolments
    FROM semesters s
    ORDER BY s.start_date
')->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Semesters';
require 'partials/header.php';
?>
<h1>Semesters</h1>
<p>Each year has two long semesters (14 weeks) and one short semester (7 weeks). Students are enrolled per semester, and class sessions must fall within a semester.</p>
<?php if ($error): ?><p class="alert alert-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<?php if ($success): ?><p class="alert alert-success"><?= htmlspecialchars($success) ?></p><?php endif; ?>

<form method="post" class="filter-bar">
<label>Code <input type="text" name="code" placeholder="e.g. 202609" pattern="\d{6}" required></label>
<label>Type
<select name="name" required>
<?php foreach ($semesterTypes as $type => $weeks): ?>
<option value="<?= $type ?>"><?= $type ?> (<?= $weeks ?> weeks)</option>
<?php endforeach; ?>
</select>
</label>
<label>Week 1 Starts (Monday) <input type="date" name="start_date" required></label>
<button type="submit">+ Add Semester</button>
</form>

<?php if (empty($semesters)): ?>
<div class="empty-state">
<div class="empty-state-icon">&#128197;</div>
<p>No semesters yet.</p>
</div>
<?php else: ?>
<table>
<tr><th>Code</th><th>Type</th><th>Starts</th><th>Ends</th><th>Weeks</th><th>Sessions</th><th>Enrolments</th><th>Actions</th></tr>
<?php foreach ($semesters as $s): ?>
<?php $isCurrent = date('Y-m-d') >= $s['start_date'] && date('Y-m-d') <= semester_end_date($s); ?>
<tr>
<td><strong><?= htmlspecialchars($s['code']) ?></strong><?= $isCurrent ? ' <span class="badge badge-success">Current</span>' : '' ?></td>
<td><?= htmlspecialchars($s['name']) ?></td>
<td><?= htmlspecialchars(date('D, d M Y', strtotime($s['start_date']))) ?></td>
<td><?= htmlspecialchars(date('D, d M Y', strtotime(semester_end_date($s)))) ?></td>
<td><?= (int)$s['weeks'] ?></td>
<td><a href="sessions.php?semester_id=<?= (int)$s['id'] ?>"><?= (int)$s['classes'] ?></a></td>
<td><a href="enrolments.php?semester_id=<?= (int)$s['id'] ?>"><?= (int)$s['enrolments'] ?></a></td>
<td>
<form action="semester_delete.php" method="post" style="display:inline" onsubmit="return confirm('Delete this semester? Its enrolments must be removed first.');">
<input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
<button type="submit" class="btn-small btn-danger">Delete</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
<?php require 'partials/footer.php'; ?>
