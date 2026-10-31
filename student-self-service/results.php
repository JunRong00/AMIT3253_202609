<?php
require 'config.php';
require 'auth.php';
require 'helpers.php';
require_login();

$uid = current_user_id();

$stmt = $conn->prepare('
    SELECT r.semester, r.grade, r.grade_point, c.code, c.name, c.credit_hours
    FROM results r
    JOIN courses c ON c.id = r.course_id
    WHERE r.user_id = ?
    ORDER BY r.semester DESC, c.code
');
$stmt->bind_param('i', $uid);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$bySemester = [];
foreach ($rows as $row) {
    $bySemester[$row['semester']][] = $row;
}

$coursesTaken = count($rows);
$creditsEarned = array_sum(array_map(fn($r) => $r['grade'] === 'F' ? 0 : (int)$r['credit_hours'], $rows));

$pageTitle = 'My Results';
require 'partials/header.php';
?>
<div class="page-header">
<h1>My Results</h1>
<p>Every examination result released to you, by semester.</p>
</div>

<section>
<div class="card-grid">
<div class="card"><h3><?= count($bySemester) ?></h3><p>Semesters completed</p></div>
<div class="card"><h3><?= (int)$coursesTaken ?></h3><p>Courses taken</p></div>
<div class="card"><h3><?= (int)$creditsEarned ?></h3><p>Credit hours earned</p></div>
</div>
</section>

<?php if (empty($bySemester)): ?>
<div class="empty-state">
<div class="empty-state-icon">&#128202;</div>
<p>No results have been released for you yet.</p>
</div>
<?php else: ?>
<?php foreach ($bySemester as $semester => $results): ?>
<section>
<h2><?= htmlspecialchars(semester_label($semester)) ?></h2>
<table>
<tr><th>Course Code</th><th>Course</th><th>Credit Hours</th><th>Grade</th><th>Grade Point</th></tr>
<?php foreach ($results as $r): ?>
<tr>
<td><?= htmlspecialchars($r['code']) ?></td>
<td><?= htmlspecialchars($r['name']) ?></td>
<td><?= (int)$r['credit_hours'] ?></td>
<td><strong><?= htmlspecialchars($r['grade']) ?></strong></td>
<td><?= number_format($r['grade_point'], 2) ?></td>
</tr>
<?php endforeach; ?>
</table>
</section>
<?php endforeach; ?>
<?php endif; ?>

<p class="form-hint">Need an official copy of your results? <a href="services.php">Request a transcript</a>.</p>
<?php require 'partials/footer.php'; ?>
