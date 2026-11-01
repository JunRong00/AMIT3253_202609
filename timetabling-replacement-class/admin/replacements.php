<?php
require '../config.php';
require '../auth.php';
require '../helpers.php';
require_admin();

$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_error']);

$status = $_GET['status'] ?? 'Pending';
$validStatuses = ['Pending', 'Approved', 'Rejected'];

$sql = '
    SELECT r.*, c.code, c.name AS course_name, v.name AS venue_name, ts.start_time, ts.end_time, ots.start_time AS orig_start, ots.end_time AS orig_end,
           u.name AS lecturer_name, u.email AS lecturer_email
    FROM replacement_classes r
    JOIN courses c ON c.id = r.course_id
    JOIN venues v ON v.id = r.venue_id
    JOIN time_slots ts ON ts.id = r.time_slot_id
    JOIN timetable_entries oe ON oe.id = r.original_entry_id
    JOIN time_slots ots ON ots.id = oe.time_slot_id
    JOIN users u ON u.id = r.user_id
';
if (in_array($status, $validStatuses, true)) {
    $stmt = $conn->prepare($sql . ' WHERE r.status = ? ORDER BY r.replacement_date, ts.start_time');
    $stmt->bind_param('s', $status);
    $stmt->execute();
    $requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $requests = $conn->query($sql . ' ORDER BY r.replacement_date DESC, ts.start_time')->fetch_all(MYSQLI_ASSOC);
}

$pageTitle = 'Replacement Requests';
require 'partials/header.php';
?>
<h1>Replacement Requests</h1>
<p>Approve or reject lecturers' replacement class requests. Approved classes appear on enrolled students' timetables immediately; the note is shown to the lecturer.</p>
<?php if ($flashError): ?><p class="alert alert-error"><?= htmlspecialchars($flashError) ?></p><?php endif; ?>

<form method="get" class="filter-bar">
<label>Status
<select name="status" onchange="this.form.submit()">
<?php foreach ($validStatuses as $s): ?>
<option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= $s ?></option>
<?php endforeach; ?>
<option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All statuses</option>
</select>
</label>
<noscript><button type="submit">Filter</button></noscript>
</form>

<?php if (empty($requests)): ?>
<div class="empty-state">
<div class="empty-state-icon">&#128197;</div>
<p>No requests match this filter.</p>
</div>
<?php else: ?>
<table>
<tr><th>Course</th><th>Lecturer</th><th>Replaces</th><th>Replacement</th><th>Reason</th><th>Status &amp; Note</th><th>Actions</th></tr>
<?php foreach ($requests as $r): ?>
<tr>
<td><strong><?= htmlspecialchars($r['code']) ?></strong> <?= htmlspecialchars($r['course_name']) ?><br><span class="stat-label"><?= htmlspecialchars($r['class_type']) ?></span></td>
<td><?= htmlspecialchars($r['lecturer_name']) ?><br><span class="stat-label"><?= htmlspecialchars($r['lecturer_email']) ?></span></td>
<td><?= htmlspecialchars(date('D, d M Y', strtotime($r['original_date']))) ?><br><span class="stat-label"><?= htmlspecialchars(time_slot_label(['start_time' => $r['orig_start'], 'end_time' => $r['orig_end']])) ?></span></td>
<td><?= htmlspecialchars(date('D, d M Y', strtotime($r['replacement_date']))) ?><br><span class="stat-label"><?= htmlspecialchars(time_slot_label($r)) ?> &middot; <?= htmlspecialchars($r['venue_name']) ?></span></td>
<td><?= nl2br(htmlspecialchars($r['reason'])) ?></td>
<td>
<form action="replacement_status.php" method="post" class="inline-form">
<input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
<select name="status">
<?php foreach ($validStatuses as $s): ?>
<option value="<?= $s ?>" <?= $r['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
<?php endforeach; ?>
</select>
<input type="text" name="admin_note" maxlength="500" placeholder="Note to lecturer" value="<?= htmlspecialchars($r['admin_note'] ?? '') ?>">
<button type="submit" class="btn-small">Save</button>
</form>
</td>
<td>
<form action="replacement_delete.php" method="post" style="display:inline" onsubmit="return confirm('Delete this request permanently? If it was approved, it disappears from the timetable.');">
<input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
<button type="submit" class="btn-small btn-danger">Delete</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
<?php require 'partials/footer.php'; ?>
