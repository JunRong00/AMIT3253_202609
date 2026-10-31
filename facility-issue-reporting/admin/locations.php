<?php
require '../config.php';
require '../auth.php';
require '../helpers.php';
require_admin();

$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_error']);

$locations = $conn->query("
    SELECT l.*, COUNT(i.id) AS active_issues
    FROM locations l
    LEFT JOIN issues i ON i.location_id = l.id AND i.status IN ('Open', 'In Progress')
    GROUP BY l.id
    ORDER BY l.building, l.name
");

$pageTitle = 'Manage Locations';
require 'partials/header.php';
?>
<h1>Locations</h1>
<p><a class="btn btn-small" href="location_create.php">+ Add Location</a></p>
<?php if ($flashError): ?><p class="alert alert-error"><?= htmlspecialchars($flashError) ?></p><?php endif; ?>
<table>
<tr><th>Photo</th><th>Building</th><th>Location</th><th>Floor</th><th>Active Issues</th><th>Actions</th></tr>
<?php while ($l = $locations->fetch_assoc()): ?>
<tr>
<td><img class="table-thumb" src="<?= htmlspecialchars(entity_image_url($l)) ?>" alt="<?= htmlspecialchars($l['name']) ?>" loading="lazy"></td>
<td><?= htmlspecialchars($l['building']) ?></td>
<td><?= htmlspecialchars($l['name']) ?></td>
<td><?= htmlspecialchars($l['floor']) ?></td>
<td><?= (int)$l['active_issues'] ?></td>
<td>
<a class="btn btn-secondary btn-small" href="location_edit.php?id=<?= (int)$l['id'] ?>">Edit</a>
<form action="location_delete.php" method="post" style="display:inline" onsubmit="return confirm('Delete this location? Any issues reported there must be removed first.');">
<input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
<button type="submit" class="btn-small btn-danger">Delete</button>
</form>
</td>
</tr>
<?php endwhile; ?>
</table>
<?php require 'partials/footer.php'; ?>
