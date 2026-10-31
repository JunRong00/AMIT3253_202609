<?php
require '../config.php';
require '../auth.php';
require '../helpers.php';
require_admin();

$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_error']);

$venues = $conn->query('
    SELECT v.*, (SELECT COUNT(*) FROM timetable_entries t WHERE t.venue_id = v.id) AS classes_per_week
    FROM venues v
    ORDER BY v.name
');

$pageTitle = 'Manage Venues';
require 'partials/header.php';
?>
<h1>Venues</h1>
<p><a class="btn btn-small" href="venue_create.php">+ Add Venue</a></p>
<?php if ($flashError): ?><p class="alert alert-error"><?= htmlspecialchars($flashError) ?></p><?php endif; ?>
<table>
<tr><th>Photo</th><th>Venue</th><th>Building</th><th>Capacity</th><th>Classes / Week</th><th>Actions</th></tr>
<?php while ($v = $venues->fetch_assoc()): ?>
<tr>
<td><img class="table-thumb" src="<?= htmlspecialchars(entity_image_url($v)) ?>" alt="<?= htmlspecialchars($v['name']) ?>" loading="lazy"></td>
<td><?= htmlspecialchars($v['name']) ?></td>
<td><?= htmlspecialchars($v['building']) ?></td>
<td><?= (int)$v['capacity'] ?></td>
<td><?= (int)$v['classes_per_week'] ?></td>
<td>
<a class="btn btn-secondary btn-small" href="venue_edit.php?id=<?= (int)$v['id'] ?>">Edit</a>
<form action="venue_delete.php" method="post" style="display:inline" onsubmit="return confirm('Delete this venue? Any classes or replacement requests using it must be removed first.');">
<input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
<button type="submit" class="btn-small btn-danger">Delete</button>
</form>
</td>
</tr>
<?php endwhile; ?>
</table>
<?php require 'partials/footer.php'; ?>
