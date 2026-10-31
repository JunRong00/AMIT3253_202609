<?php
require 'config.php';
require 'auth.php';
require 'helpers.php';

$openings = $conn->query("
    SELECT o.*, (SELECT COUNT(*) FROM applications a WHERE a.opening_id = o.id AND a.status = 'Accepted') AS filled
    FROM openings o
    WHERE o.deadline >= CURDATE()
    ORDER BY o.deadline, o.title
")->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Upcoming Deadlines';
require 'partials/header.php';
?>
<div class="page-header">
<h1>Upcoming Deadlines</h1>
<p>Every opening still accepting applications, closing soonest first.</p>
</div>

<?php if (empty($openings)): ?>
<div class="empty-state">
<div class="empty-state-icon">&#128197;</div>
<p>No openings are accepting applications right now.</p>
</div>
<?php else: ?>
<table>
<tr><th>Deadline</th><th>Days Left</th><th>Opening</th><th>Category</th><th>Vacancies</th><th>Actions</th></tr>
<?php foreach ($openings as $o): ?>
<?php $daysLeft = days_until($o['deadline']); $isFilled = (int)$o['filled'] >= (int)$o['vacancies']; ?>
<tr>
<td><?= htmlspecialchars(date('D, d M Y', strtotime($o['deadline']))) ?></td>
<td><span class="badge <?= $daysLeft <= 3 ? 'badge-danger' : ($daysLeft <= 7 ? 'badge-warning' : 'badge-neutral') ?>"><?= $daysLeft === 0 ? 'Closes today' : $daysLeft . ' day' . ($daysLeft === 1 ? '' : 's') ?></span></td>
<td><strong><?= htmlspecialchars($o['title']) ?></strong><br><span class="stat-label"><?= htmlspecialchars($o['organisation']) ?></span></td>
<td><?= htmlspecialchars($o['category']) ?></td>
<td><?= (int)$o['filled'] ?> / <?= (int)$o['vacancies'] ?> filled</td>
<td>
<?php if ($isFilled): ?>
<span class="stat-label">Filled</span>
<?php elseif (current_user_id()): ?>
<a class="btn btn-small" href="create.php?opening_id=<?= (int)$o['id'] ?>">Apply</a>
<?php else: ?>
<a class="btn btn-small btn-secondary" href="login.php">Login to Apply</a>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
<?php require 'partials/footer.php'; ?>
