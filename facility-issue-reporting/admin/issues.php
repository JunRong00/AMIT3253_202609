<?php
require '../config.php';
require '../auth.php';
require '../helpers.php';
require_admin();

$status = $_GET['status'] ?? 'active';

$sql = '
    SELECT i.*, l.building, l.name, u.name AS reporter_name, u.email AS reporter_email
    FROM issues i
    JOIN locations l ON l.id = i.location_id
    JOIN users u ON u.id = i.user_id
';
if ($status === 'active') {
    $sql .= " WHERE i.status IN ('Open', 'In Progress')";
    $issues = $conn->query($sql . " ORDER BY FIELD(i.priority, 'High', 'Medium', 'Low'), i.created_at")->fetch_all(MYSQLI_ASSOC);
} elseif (in_array($status, issue_statuses(), true)) {
    $stmt = $conn->prepare($sql . ' WHERE i.status = ? ORDER BY i.updated_at DESC');
    $stmt->bind_param('s', $status);
    $stmt->execute();
    $issues = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $issues = $conn->query($sql . ' ORDER BY i.created_at DESC')->fetch_all(MYSQLI_ASSOC);
}

$pageTitle = 'Manage Issues';
require 'partials/header.php';
?>
<h1>Issues</h1>
<p>Move each report through Open &rarr; In Progress &rarr; Resolved &rarr; Closed. The note is shown to the reporter on their homepage.</p>

<form method="get" class="filter-bar">
<label>Status
<select name="status" onchange="this.form.submit()">
<option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active (Open + In Progress)</option>
<option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All statuses</option>
<?php foreach (issue_statuses() as $s): ?>
<option value="<?= htmlspecialchars($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
<?php endforeach; ?>
</select>
</label>
<noscript><button type="submit">Filter</button></noscript>
</form>

<?php if (empty($issues)): ?>
<div class="empty-state">
<div class="empty-state-icon">&#9989;</div>
<p>No issues match this filter.</p>
</div>
<?php else: ?>
<table>
<tr><th>Reported</th><th>Location</th><th>Issue</th><th>Priority</th><th>Reported By</th><th>Status &amp; Note</th><th>Actions</th></tr>
<?php foreach ($issues as $i): ?>
<tr>
<td><?= htmlspecialchars(date('d M Y, g:i a', strtotime($i['created_at']))) ?></td>
<td><?= htmlspecialchars(location_label($i)) ?></td>
<td><strong><?= htmlspecialchars($i['title']) ?></strong><br><span class="stat-label"><?= htmlspecialchars($i['category']) ?></span><br><?= nl2br(htmlspecialchars($i['description'])) ?></td>
<td><span class="badge <?= priority_badge_class($i['priority']) ?>"><?= htmlspecialchars($i['priority']) ?></span></td>
<td><?= htmlspecialchars($i['reporter_name']) ?><br><span class="stat-label"><?= htmlspecialchars($i['reporter_email']) ?></span></td>
<td>
<form action="issue_update.php" method="post" class="inline-form">
<input type="hidden" name="id" value="<?= (int)$i['id'] ?>">
<select name="status">
<?php foreach (issue_statuses() as $s): ?>
<option value="<?= htmlspecialchars($s) ?>" <?= $i['status'] === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
<?php endforeach; ?>
</select>
<input type="text" name="admin_note" maxlength="500" placeholder="Note to reporter" value="<?= htmlspecialchars($i['admin_note'] ?? '') ?>">
<button type="submit" class="btn-small">Save</button>
</form>
</td>
<td>
<form action="issue_delete.php" method="post" style="display:inline" onsubmit="return confirm('Delete this report permanently?');">
<input type="hidden" name="id" value="<?= (int)$i['id'] ?>">
<button type="submit" class="btn-small btn-danger">Delete</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
<?php require 'partials/footer.php'; ?>
