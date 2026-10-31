<?php
require '../config.php';
require '../auth.php';
require '../helpers.php';
require_admin();

$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_error']);

$types = $conn->query("
    SELECT dt.*, (SELECT COUNT(*) FROM document_requests dr WHERE dr.document_type_id = dt.id AND dr.status IN ('Pending', 'Processing')) AS active_requests
    FROM document_types dt
    ORDER BY dt.name
");

$pageTitle = 'Manage Documents';
require 'partials/header.php';
?>
<h1>Document Types</h1>
<p><a class="btn btn-small" href="document_type_create.php">+ Add Document Type</a></p>
<?php if ($flashError): ?><p class="alert alert-error"><?= htmlspecialchars($flashError) ?></p><?php endif; ?>
<table>
<tr><th>Photo</th><th>Document</th><th>Fee (RM/copy)</th><th>Processing Days</th><th>Max Copies</th><th>Active Requests</th><th>Actions</th></tr>
<?php while ($t = $types->fetch_assoc()): ?>
<tr>
<td><img class="table-thumb" src="<?= htmlspecialchars(entity_image_url($t)) ?>" alt="<?= htmlspecialchars($t['name']) ?>" loading="lazy"></td>
<td><strong><?= htmlspecialchars($t['name']) ?></strong><br><span class="stat-label"><?= htmlspecialchars($t['description']) ?></span></td>
<td><?= number_format($t['fee'], 2) ?></td>
<td><?= (int)$t['processing_days'] ?></td>
<td><?= (int)$t['max_copies'] ?></td>
<td><?= (int)$t['active_requests'] ?></td>
<td>
<a class="btn btn-secondary btn-small" href="document_type_edit.php?id=<?= (int)$t['id'] ?>">Edit</a>
<form action="document_type_delete.php" method="post" style="display:inline" onsubmit="return confirm('Delete this document type? Any requests for it must be removed first.');">
<input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
<button type="submit" class="btn-small btn-danger">Delete</button>
</form>
</td>
</tr>
<?php endwhile; ?>
</table>
<?php require 'partials/footer.php'; ?>
