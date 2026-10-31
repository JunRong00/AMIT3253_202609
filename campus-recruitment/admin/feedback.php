<?php
require '../config.php';
require '../auth.php';
require_admin();

$feedback = $conn->query('
    SELECT f.id, f.topic, f.comment, f.rating, f.created_at, u.name AS user_name
    FROM feedback f
    JOIN users u ON u.id = f.user_id
    ORDER BY f.created_at DESC
')->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Feedback';
require 'partials/header.php';
?>
<h1>Feedback</h1>
<?php if (empty($feedback)): ?>
<div class="empty-state">
<div class="empty-state-icon">&#128172;</div>
<p>No feedback yet.</p>
</div>
<?php else: ?>
<div class="testimonial-list">
<?php foreach ($feedback as $f): ?>
<div class="testimonial-card">
<span class="badge badge-accent"><?= htmlspecialchars($f['topic']) ?></span>
<div class="testimonial-stars"><?= str_repeat('&#9733;', $f['rating']) . str_repeat('&#9734;', 5 - $f['rating']) ?></div>
<p class="testimonial-comment"><?= nl2br(htmlspecialchars($f['comment'])) ?></p>
<div class="testimonial-meta">
<span><?= htmlspecialchars($f['user_name']) ?> &middot; <?= htmlspecialchars(date('d M Y', strtotime($f['created_at']))) ?></span>
<form action="feedback_delete.php" method="post" style="display:inline" onsubmit="return confirm('Delete this feedback?');">
<input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
<button type="submit" class="btn-small btn-danger">Delete</button>
</form>
</div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?php require 'partials/footer.php'; ?>
