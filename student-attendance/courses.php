<?php
require 'config.php';
require 'auth.php';
require 'helpers.php';

$uid = current_user_id();
$isStudent = $uid && !current_user_is_lecturer() && !current_user_is_admin();

$courses = $conn->query('
    SELECT c.*, u.name AS lecturer_name,
           (SELECT COUNT(*) FROM enrolments e WHERE e.course_id = c.id) AS enrolled,
           (SELECT COUNT(*) FROM class_sessions s WHERE s.course_id = c.id AND s.session_date >= CURDATE()) AS upcoming
    FROM courses c
    LEFT JOIN users u ON u.id = c.lecturer_id
    ORDER BY c.code
')->fetch_all(MYSQLI_ASSOC);

$myEnrolments = [];
if ($isStudent) {
    $stmt = $conn->prepare('SELECT course_id FROM enrolments WHERE user_id = ?');
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $myEnrolments = array_map('intval', array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'course_id'));
    $stmt->close();
}

$pageTitle = 'Courses';
require 'partials/header.php';
?>
<div class="page-header">
<h1>Courses</h1>
<p>Enrol in the courses you're taking this semester so you can check in to their classes.</p>
</div>

<section>
<div class="card-grid">
<?php foreach ($courses as $c): ?>
<?php $isEnrolled = in_array((int)$c['id'], $myEnrolments, true); ?>
<div class="card">
<img class="card-thumb" src="<?= htmlspecialchars(entity_image_url($c)) ?>" alt="<?= htmlspecialchars($c['name']) ?>" loading="lazy">
<span class="badge badge-accent"><?= htmlspecialchars($c['code']) ?></span>
<?php if ($isEnrolled): ?><span class="badge badge-success">Enrolled</span><?php endif; ?>
<h3><?= htmlspecialchars($c['name']) ?></h3>
<p><?= htmlspecialchars($c['lecturer_name'] ?? 'Lecturer to be assigned') ?></p>
<p><?= (int)$c['enrolled'] ?> enrolled &middot; <?= (int)$c['upcoming'] ?> upcoming session<?= (int)$c['upcoming'] === 1 ? '' : 's' ?></p>
<?php if ($isStudent && $isEnrolled): ?>
<form action="unenrol.php" method="post" onsubmit="return confirm('Unenrol from this course? Your past attendance records are kept.');">
<input type="hidden" name="course_id" value="<?= (int)$c['id'] ?>">
<button type="submit" class="btn btn-danger">Unenrol</button>
</form>
<?php elseif ($isStudent): ?>
<form action="enrol.php" method="post">
<input type="hidden" name="course_id" value="<?= (int)$c['id'] ?>">
<button type="submit" class="btn">Enrol</button>
</form>
<?php elseif (!$uid): ?>
<a class="btn" href="login.php">Login to Enrol</a>
<?php elseif (current_user_is_lecturer() && (int)$c['lecturer_id'] === (int)$uid): ?>
<a class="btn" href="sessions.php?course_id=<?= (int)$c['id'] ?>">Manage Sessions</a>
<?php endif; ?>
</div>
<?php endforeach; ?>
</div>
</section>
<?php require 'partials/footer.php'; ?>
