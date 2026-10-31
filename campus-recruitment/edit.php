<?php
require 'config.php';
require 'auth.php';
require 'helpers.php';
require_login();

$id  = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$uid = current_user_id();
$error = '';

$stmt = $conn->prepare('SELECT a.*, o.title, o.organisation, o.deadline FROM applications a JOIN openings o ON o.id = a.opening_id WHERE a.id = ? AND a.user_id = ?');
$stmt->bind_param('ii', $id, $uid);
$stmt->execute();
$application = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$application) {
    die('Application not found or you do not have permission to edit it.');
}
if ($application['status'] !== 'Submitted' || is_deadline_passed($application['deadline'])) {
    die('This application can no longer be edited - it has already been reviewed, or the deadline has passed.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone      = trim($_POST['phone'] ?? '');
    $cover_note = trim($_POST['cover_note'] ?? '');

    if ($phone === '' || $cover_note === '') {
        $error = 'Please enter a contact number and a short cover note.';
    } elseif (!is_valid_phone($phone)) {
        $error = 'Please enter a valid contact number, e.g. 012-345 6789.';
    } else {
        $stmt = $conn->prepare("UPDATE applications SET phone=?, cover_note=? WHERE id=? AND user_id=? AND status='Submitted'");
        $stmt->bind_param('ssii', $phone, $cover_note, $id, $uid);
        $stmt->execute();
        $stmt->close();
        header('Location: index.php');
        exit;
    }
    $application = array_merge($application, compact('phone', 'cover_note'));
}

$pageTitle = 'Edit Application';
require 'partials/header.php';
?>
<div class="form-card">
<h1>Edit Application</h1>
<?php if ($error): ?><p class="alert alert-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form method="post">
<input type="hidden" name="id" value="<?= (int)$application['id'] ?>">
<label>Opening <input type="text" value="<?= htmlspecialchars($application['title']) ?> - <?= htmlspecialchars($application['organisation']) ?>" disabled></label>
<label>Contact Number <span class="required-mark">*</span> <input type="tel" name="phone" value="<?= htmlspecialchars($application['phone']) ?>" required></label>
<label>Cover Note <span class="required-mark">*</span> <textarea name="cover_note" rows="5" required><?= htmlspecialchars($application['cover_note']) ?></textarea></label>
<p class="form-hint">You can edit your application until it is reviewed or the deadline (<?= htmlspecialchars(date('d M Y', strtotime($application['deadline']))) ?>) passes.</p>
<button type="submit">Update Application</button>
</form>
<p><a class="btn btn-secondary btn-small" href="index.php">Back to home</a></p>
</div>
<?php require 'partials/footer.php'; ?>
