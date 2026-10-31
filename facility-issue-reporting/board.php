<?php
require 'config.php';
require 'auth.php';
require 'helpers.php';

$status = $_GET['status'] ?? 'active';
$locationId = (int)($_GET['location_id'] ?? 0);

$where = [];
$types = '';
$params = [];
if ($status === 'active') {
    $where[] = "i.status IN ('Open', 'In Progress')";
} elseif (in_array($status, issue_statuses(), true)) {
    $where[] = 'i.status = ?';
    $types .= 's';
    $params[] = $status;
}
if ($locationId > 0) {
    $where[] = 'i.location_id = ?';
    $types .= 'i';
    $params[] = $locationId;
}

$sql = '
    SELECT i.id, i.title, i.category, i.priority, i.status, i.created_at, i.updated_at, l.building, l.name
    FROM issues i
    JOIN locations l ON l.id = i.location_id
' . (empty($where) ? '' : ' WHERE ' . implode(' AND ', $where)) . "
    ORDER BY FIELD(i.priority, 'High', 'Medium', 'Low'), i.created_at DESC
";
$stmt = $conn->prepare($sql);
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$issues = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$locations = $conn->query('SELECT id, building, name FROM locations ORDER BY building, name')->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Issue Board';
require 'partials/header.php';
?>
<div class="page-header">
<h1>Issue Board</h1>
<p>Every reported issue on campus and where it is in the repair process. Reporter names are never shown here.</p>
</div>

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
<label>Location
<select name="location_id" onchange="this.form.submit()">
<option value="0">All locations</option>
<?php foreach ($locations as $l): ?>
<option value="<?= (int)$l['id'] ?>" <?= (int)$l['id'] === $locationId ? 'selected' : '' ?>><?= htmlspecialchars(location_label($l)) ?></option>
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
<tr><th>Location</th><th>Issue</th><th>Category</th><th>Priority</th><th>Status</th><th>Reported</th><th>Last Update</th></tr>
<?php foreach ($issues as $i): ?>
<tr>
<td><?= htmlspecialchars(location_label($i)) ?></td>
<td><?= htmlspecialchars($i['title']) ?></td>
<td><?= htmlspecialchars($i['category']) ?></td>
<td><span class="badge <?= priority_badge_class($i['priority']) ?>"><?= htmlspecialchars($i['priority']) ?></span></td>
<td><span class="badge <?= status_badge_class($i['status']) ?>"><?= htmlspecialchars($i['status']) ?></span></td>
<td><?= htmlspecialchars(date('d M Y', strtotime($i['created_at']))) ?></td>
<td><?= htmlspecialchars(date('d M Y', strtotime($i['updated_at']))) ?></td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
<?php require 'partials/footer.php'; ?>
