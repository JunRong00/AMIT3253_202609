<?php
require '../config.php';
require '../auth.php';
require '../helpers.php';
require_admin();

$status = $_GET['status'] ?? 'active';

$sql = '
    SELECT dr.*, dt.name AS document_name, u.name AS student_name, u.email AS student_email, u.id_number
    FROM document_requests dr
    JOIN document_types dt ON dt.id = dr.document_type_id
    JOIN users u ON u.id = dr.user_id
';
if ($status === 'active') {
    $requests = $conn->query($sql . " WHERE dr.status IN ('Pending', 'Processing') ORDER BY dr.created_at")->fetch_all(MYSQLI_ASSOC);
} elseif (in_array($status, request_statuses(), true)) {
    $stmt = $conn->prepare($sql . ' WHERE dr.status = ? ORDER BY dr.updated_at DESC');
    $stmt->bind_param('s', $status);
    $stmt->execute();
    $requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $requests = $conn->query($sql . ' ORDER BY dr.created_at DESC')->fetch_all(MYSQLI_ASSOC);
}

$pageTitle = 'Document Requests';
require 'partials/header.php';
?>
<h1>Document Requests</h1>
<p>Move each request through Pending &rarr; Processing &rarr; Ready &rarr; Collected (or Rejected). The note is shown to the student.</p>

<form method="get" class="filter-bar">
<label>Status
<select name="status" onchange="this.form.submit()">
<option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active (Pending + Processing)</option>
<option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All statuses</option>
<?php foreach (request_statuses() as $s): ?>
<option value="<?= htmlspecialchars($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
<?php endforeach; ?>
</select>
</label>
<noscript><button type="submit">Filter</button></noscript>
</form>

<?php if (empty($requests)): ?>
<div class="empty-state">
<div class="empty-state-icon">&#128196;</div>
<p>No requests match this filter.</p>
</div>
<?php else: ?>
<table>
<tr><th>Requested</th><th>Student</th><th>Document</th><th>Copies</th><th>Delivery</th><th>Fee (RM)</th><th>Status &amp; Note</th><th>Actions</th></tr>
<?php foreach ($requests as $r): ?>
<tr>
<td><?= htmlspecialchars(date('d M Y, g:i a', strtotime($r['created_at']))) ?></td>
<td><?= htmlspecialchars($r['student_name']) ?><br><span class="stat-label"><?= htmlspecialchars($r['id_number'] ?? '') ?> &middot; <?= htmlspecialchars($r['student_email']) ?></span></td>
<td><strong><?= htmlspecialchars($r['document_name']) ?></strong><br><span class="stat-label"><?= htmlspecialchars($r['purpose']) ?></span></td>
<td><?= (int)$r['copies'] ?></td>
<td><?= htmlspecialchars($r['delivery_method']) ?></td>
<td><?= number_format($r['total_fee'], 2) ?></td>
<td>
<form action="request_status.php" method="post" class="inline-form">
<input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
<select name="status">
<?php foreach (request_statuses() as $s): ?>
<option value="<?= htmlspecialchars($s) ?>" <?= $r['status'] === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
<?php endforeach; ?>
</select>
<input type="text" name="admin_note" maxlength="500" placeholder="Note to student" value="<?= htmlspecialchars($r['admin_note'] ?? '') ?>">
<button type="submit" class="btn-small">Save</button>
</form>
</td>
<td>
<form action="request_delete.php" method="post" style="display:inline" onsubmit="return confirm('Delete this request permanently?');">
<input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
<button type="submit" class="btn-small btn-danger">Delete</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
<?php require 'partials/footer.php'; ?>
