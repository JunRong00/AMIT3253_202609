<?php
require '../config.php';
require '../auth.php';
require '../helpers.php';
require_admin();

$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_error']);

$openingId = (int)($_GET['opening_id'] ?? 0);
$status    = $_GET['status'] ?? '';
if (!in_array($status, application_statuses(), true)) {
    $status = '';
}

$where = [];
$types = '';
$params = [];
if ($openingId > 0) {
    $where[] = 'a.opening_id = ?';
    $types .= 'i';
    $params[] = $openingId;
}
if ($status !== '') {
    $where[] = 'a.status = ?';
    $types .= 's';
    $params[] = $status;
}
$stmt = $conn->prepare('
    SELECT a.*, o.title, o.organisation, u.name AS applicant_name, u.email AS applicant_email, u.id_number, u.faculty
    FROM applications a
    JOIN openings o ON o.id = a.opening_id
    JOIN users u ON u.id = a.user_id
' . (empty($where) ? '' : ' WHERE ' . implode(' AND ', $where)) . '
    ORDER BY a.created_at DESC
');
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$applications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$openings = $conn->query('SELECT id, title FROM openings ORDER BY title')->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Applications';
require 'partials/header.php';
?>
<h1>Applications</h1>
<p>Review applications and move them through Submitted &rarr; Shortlisted &rarr; Accepted / Rejected. An opening can't accept more applicants than it has vacancies.</p>
<?php if ($flashError): ?><p class="alert alert-error"><?= htmlspecialchars($flashError) ?></p><?php endif; ?>

<form method="get" class="filter-bar">
<label>Opening
<select name="opening_id" onchange="this.form.submit()">
<option value="0">All openings</option>
<?php foreach ($openings as $o): ?>
<option value="<?= (int)$o['id'] ?>" <?= (int)$o['id'] === $openingId ? 'selected' : '' ?>><?= htmlspecialchars($o['title']) ?></option>
<?php endforeach; ?>
</select>
</label>
<label>Status
<select name="status" onchange="this.form.submit()">
<option value="">All statuses</option>
<?php foreach (application_statuses() as $s): ?>
<option value="<?= htmlspecialchars($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
<?php endforeach; ?>
</select>
</label>
<noscript><button type="submit">Filter</button></noscript>
</form>

<?php if (empty($applications)): ?>
<div class="empty-state">
<div class="empty-state-icon">&#128196;</div>
<p>No applications match this filter.</p>
</div>
<?php else: ?>
<table>
<tr><th>Applied</th><th>Opening</th><th>Applicant</th><th>Cover Note</th><th>Status</th><th>Actions</th></tr>
<?php foreach ($applications as $a): ?>
<tr>
<td><?= htmlspecialchars(date('d M Y', strtotime($a['created_at']))) ?></td>
<td><strong><?= htmlspecialchars($a['title']) ?></strong><br><span class="stat-label"><?= htmlspecialchars($a['organisation']) ?></span></td>
<td><?= htmlspecialchars($a['applicant_name']) ?><br>
<span class="stat-label"><?= htmlspecialchars($a['applicant_email']) ?> &middot; <?= htmlspecialchars($a['phone']) ?></span><br>
<span class="stat-label"><?= htmlspecialchars($a['id_number'] ?? '') ?> <?= htmlspecialchars($a['faculty'] ?? '') ?></span></td>
<td><?= nl2br(htmlspecialchars($a['cover_note'])) ?></td>
<td>
<form action="application_status.php" method="post" class="inline-form">
<input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
<select name="status">
<?php foreach (application_statuses() as $s): ?>
<option value="<?= htmlspecialchars($s) ?>" <?= $a['status'] === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
<?php endforeach; ?>
</select>
<button type="submit" class="btn-small">Save</button>
</form>
</td>
<td>
<form action="application_delete.php" method="post" style="display:inline" onsubmit="return confirm('Delete this application permanently?');">
<input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
<button type="submit" class="btn-small btn-danger">Delete</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
<?php require 'partials/footer.php'; ?>
