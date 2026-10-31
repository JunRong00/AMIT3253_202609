<?php
require 'config.php';
require 'auth.php';
require 'helpers.php';

require_login();

[$visibleSql, $types, $params] = replacement_visibility_sql();
$stmt = $conn->prepare("
    SELECT r.*, c.code, c.name AS course_name, v.name AS venue_name, v.building, ts.start_time, ts.end_time, u.name AS lecturer_name
    FROM replacement_classes r
    JOIN courses c ON c.id = r.course_id
    JOIN venues v ON v.id = r.venue_id
    JOIN time_slots ts ON ts.id = r.time_slot_id
    JOIN users u ON u.id = r.user_id
    WHERE r.status = 'Approved' AND r.replacement_date >= CURDATE() AND $visibleSql
    ORDER BY r.replacement_date, ts.start_time
");
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$replacements = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$pageTitle = 'Replacement Classes';
require 'partials/header.php';
?>
<div class="page-header">
<div class="page-header-top">
<div>
<h1>Replacement Classes</h1>
<p>Approved replacement classes from today onwards<?= current_user_is_admin() ? '' : (current_user_is_lecturer() ? ' for the courses you teach' : ' for your enrolled courses') ?>.</p>
</div>
<?php if (current_user_is_lecturer()): ?>
<a class="btn" href="create.php">+ Request Replacement</a>
<?php endif; ?>
</div>
</div>

<?php if (empty($replacements)): ?>
<div class="empty-state">
<div class="empty-state-icon">&#128197;</div>
<p>No replacement classes are scheduled.</p>
</div>
<?php else: ?>
<table>
<tr><th>Date</th><th>Time</th><th>Course</th><th>Venue</th><th>Lecturer</th><th>Replaces Class On</th></tr>
<?php foreach ($replacements as $r): ?>
<tr>
<td><?= htmlspecialchars(date('D, d M Y', strtotime($r['replacement_date']))) ?></td>
<td><?= htmlspecialchars(time_slot_label($r)) ?></td>
<td><strong><?= htmlspecialchars($r['code']) ?></strong> <?= htmlspecialchars($r['course_name']) ?><br><span class="stat-label"><?= htmlspecialchars($r['class_type']) ?></span></td>
<td><?= htmlspecialchars($r['venue_name']) ?><br><span class="stat-label"><?= htmlspecialchars($r['building']) ?></span></td>
<td><?= htmlspecialchars($r['lecturer_name']) ?></td>
<td><?= htmlspecialchars(date('D, d M Y', strtotime($r['original_date']))) ?></td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
<?php require 'partials/footer.php'; ?>
