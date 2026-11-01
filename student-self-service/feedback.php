<?php
require 'config.php';
require 'auth.php';
require 'helpers.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    $topic   = trim($_POST['topic'] ?? '');
    $comment = trim($_POST['comment'] ?? '');
    $rating  = (int)($_POST['rating'] ?? 0);
    $uid     = current_user_id();

    if (!in_array($topic, feedback_topics(), true) || $comment === '' || $rating < 1 || $rating > 5) {
        $error = 'Please choose a topic, write a comment, and pick a rating between 1 and 5.';
    } else {
        $stmt = $conn->prepare('INSERT INTO feedback (user_id, topic, comment, rating) VALUES (?, ?, ?, ?)');
        $stmt->bind_param('issi', $uid, $topic, $comment, $rating);
        if ($stmt->execute()) {
            $stmt->close();
            header('Location: feedback.php');
            exit;
        }
        $error = 'Could not post your feedback. Please try again.';
        $stmt->close();
    }
}

$feedback = $conn->query('
    SELECT f.id, f.topic, f.comment, f.rating, f.created_at, f.user_id, u.name AS user_name
    FROM feedback f
    JOIN users u ON u.id = f.user_id
    ORDER BY f.created_at DESC
')->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Feedback';
require 'partials/header.php';
?>
<div class="page-header">
<h1>Feedback</h1>
<p>What students and staff are saying about Student Self-Service Portal.</p>
</div>

<?php if (current_user_id()): ?>
<div class="form-card" style="margin-bottom:24px; max-width:600px;">
<h2>Leave Feedback</h2>
<?php if ($error): ?><p class="alert alert-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form method="post">
<label>Topic
<select name="topic" required>
<option value="">-- Select a topic --</option>
<?php foreach (feedback_topics() as $t): ?>
<option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option>
<?php endforeach; ?>
</select>
</label>
<label>Rating
<select name="rating" required>
<option value="5">&#9733;&#9733;&#9733;&#9733;&#9733; Excellent</option>
<option value="4">&#9733;&#9733;&#9733;&#9733;&#9734; Good</option>
<option value="3">&#9733;&#9733;&#9733;&#9734;&#9734; Okay</option>
<option value="2">&#9733;&#9733;&#9734;&#9734;&#9734; Poor</option>
<option value="1">&#9733;&#9734;&#9734;&#9734;&#9734; Bad</option>
</select>
</label>
<label>Comment <textarea name="comment" rows="3" required></textarea></label>
<button type="submit">Post Feedback</button>
</form>
</div>
<?php else: ?>
<p><a href="login.php">Login</a> to leave feedback.</p>
<?php endif; ?>

<?php if (empty($feedback)): ?>
<div class="empty-state">
<div class="empty-state-icon">&#128172;</div>
<p>No feedback yet. Be the first to share your experience.</p>
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
<?php if (current_user_id() == $f['user_id']): ?>
<form action="feedback_delete.php" method="post" style="display:inline" onsubmit="return confirm('Delete this feedback?');">
<input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
<button type="submit" class="btn-small btn-danger">Delete</button>
</form>
<?php endif; ?>
</div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?php require 'partials/footer.php'; ?>
