<?php
require '../config.php';
require '../auth.php';
require '../helpers.php';
require_admin();

$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_error']);

$openings = $conn->query("
    SELECT o.*,
           (SELECT COUNT(*) FROM applications a WHERE a.opening_id = o.id) AS applicants,
           (SELECT COUNT(*) FROM applications a WHERE a.opening_id = o.id AND a.status = 'Accepted') AS filled
    FROM openings o
    ORDER BY o.deadline DESC
");

$pageTitle = 'Manage Openings';
require 'partials/header.php';
?>
<h1>Openings</h1>
<p><a class="btn btn-small" href="opening_create.php">+ Add Opening</a></p>
<?php if ($flashError): ?><p class="alert alert-error"><?= htmlspecialchars($flashError) ?></p><?php endif; ?>
<table>
<tr><th>Photo</th><th>Opening</th><th>Category</th><th>Deadline</th><th>Filled</th><th>Applicants</th><th>Actions</th></tr>
<?php while ($o = $openings->fetch_assoc()): ?>
<tr>
<td><img class="table-thumb" src="<?= htmlspecialchars(entity_image_url($o)) ?>" alt="<?= htmlspecialchars($o['title']) ?>" loading="lazy"></td>
<td><strong><?= htmlspecialchars($o['title']) ?></strong><br><span class="stat-label"><?= htmlspecialchars($o['organisation']) ?></span></td>
<td><?= htmlspecialchars($o['category']) ?></td>
<td><?= htmlspecialchars(date('d M Y', strtotime($o['deadline']))) ?><?= is_deadline_passed($o['deadline']) ? ' <span class="badge badge-neutral">Closed</span>' : '' ?></td>
<td><?= (int)$o['filled'] ?> / <?= (int)$o['vacancies'] ?></td>
<td><a href="applications.php?opening_id=<?= (int)$o['id'] ?>"><?= (int)$o['applicants'] ?></a></td>
<td>
<a class="btn btn-secondary btn-small" href="opening_edit.php?id=<?= (int)$o['id'] ?>">Edit</a>
<form action="opening_delete.php" method="post" style="display:inline" onsubmit="return confirm('Delete this opening? Any applications for it must be removed first.');">
<input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
<button type="submit" class="btn-small btn-danger">Delete</button>
</form>
</td>
</tr>
<?php endwhile; ?>
</table>
<?php require 'partials/footer.php'; ?>
