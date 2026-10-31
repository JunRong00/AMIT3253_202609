<?php
require '../config.php';
require '../auth.php';
require_admin();

$flashError = $_SESSION['flash_error'] ?? null;
$flashSuccess = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_error'], $_SESSION['flash_success']);

$myId = current_user_id();
$users = $conn->query('SELECT id, name, email, id_number, is_admin, role, created_at FROM users ORDER BY name')->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Manage Users';
require 'partials/header.php';
?>
<h1>Users</h1>
<p>Every account is created here &mdash; students, lecturers and admins. There is no public registration.</p>
<p><a class="btn btn-small" href="user_create.php">+ Add User</a></p>
<?php if ($flashError): ?><p class="alert alert-error"><?= htmlspecialchars($flashError) ?></p><?php endif; ?>
<?php if ($flashSuccess): ?><p class="alert alert-success"><?= htmlspecialchars($flashSuccess) ?></p><?php endif; ?>
<table>
<tr><th>Name</th><th>Student / Staff ID</th><th>Email</th><th>Role</th><th>Joined</th><th>Actions</th></tr>
<?php foreach ($users as $u): ?>
<tr>
<td><?= htmlspecialchars($u['name']) ?></td>
<td><?= htmlspecialchars($u['id_number'] ?? '-') ?></td>
<td><?= htmlspecialchars($u['email']) ?></td>
<td><?= $u['is_admin'] ? '<span class="badge badge-neutral">Admin</span>' : ($u['role'] === 'lecturer' ? '<span class="badge badge-accent">Lecturer</span>' : 'Student') ?></td>
<td><?= htmlspecialchars(date('d M Y', strtotime($u['created_at']))) ?></td>
<td>
<?php if ((int)$u['id'] === (int)$myId): ?>
<span class="stat-label">(you)</span>
<?php else: ?>
<form action="user_toggle_admin.php" method="post" style="display:inline" onsubmit="return confirm('<?= $u['is_admin'] ? 'Remove admin access from ' : 'Grant admin access to ' ?><?= htmlspecialchars($u['name']) ?>?');">
<input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
<button type="submit" class="btn btn-secondary btn-small"><?= $u['is_admin'] ? 'Revoke Admin' : 'Make Admin' ?></button>
</form>
<form action="user_delete.php" method="post" style="display:inline" onsubmit="return confirm('Delete this user? This will PERMANENTLY delete their account together with ALL of their enrolments, replacement class requests and feedback. This cannot be undone.');">
<input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
<button type="submit" class="btn-small btn-danger">Delete</button>
</form>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</table>
<?php require 'partials/footer.php'; ?>
