<?php
require 'config.php';
require 'auth.php';
require 'helpers.php';

$search = trim($_GET['q'] ?? '');

if ($search !== '') {
    $stmt = $conn->prepare('SELECT * FROM document_types WHERE name LIKE ? ORDER BY name');
    $likeSearch = '%' . $search . '%';
    $stmt->bind_param('s', $likeSearch);
    $stmt->execute();
    $documentTypes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $documentTypes = $conn->query('SELECT * FROM document_types ORDER BY name')->fetch_all(MYSQLI_ASSOC);
}

$profile = null;
$latestResults = [];
$latestSemester = null;
$myRequests = [];
if ($uid = current_user_id()) {
    $stmt = $conn->prepare('SELECT name, email, id_number, faculty, programme, image_url FROM users WHERE id = ?');
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $profile = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $stmt = $conn->prepare('
        SELECT r.semester, r.grade, r.grade_point, c.code, c.name, c.credit_hours
        FROM results r
        JOIN courses c ON c.id = r.course_id
        WHERE r.user_id = ? AND r.semester = (SELECT MAX(semester) FROM results WHERE user_id = ?)
        ORDER BY c.code
    ');
    $stmt->bind_param('ii', $uid, $uid);
    $stmt->execute();
    $latestResults = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $latestSemester = $latestResults[0]['semester'] ?? null;

    $stmt = $conn->prepare('
        SELECT dr.*, dt.name AS document_name, dt.processing_days
        FROM document_requests dr
        JOIN document_types dt ON dt.id = dr.document_type_id
        WHERE dr.user_id = ?
        ORDER BY dr.created_at DESC
    ');
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $myRequests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$pageTitle = 'Student Self-Service Portal';
require 'partials/header.php';
?>
<section class="hero">
<h1>Student Self-Service Portal</h1>
<p>Check your results and request official documents &mdash; no queue at the Registry counter.</p>
</section>

<?php if ($profile): ?>
<section>
<h2>My Profile</h2>
<div class="card" style="display:flex; gap:20px; align-items:center; flex-wrap:wrap;">
<img class="profile-photo card-thumb" style="width:96px;height:96px;margin:0;" src="<?= htmlspecialchars(entity_image_url($profile)) ?>" alt="<?= htmlspecialchars($profile['name']) ?>">
<div>
<h3><?= htmlspecialchars($profile['name']) ?></h3>
<p><?= htmlspecialchars($profile['id_number'] ?? '-') ?> &middot; <?= htmlspecialchars($profile['email']) ?></p>
<p><?= htmlspecialchars($profile['programme'] ?: 'Programme not set') ?> &middot; <?= htmlspecialchars($profile['faculty'] ?? '') ?></p>
<a class="btn btn-secondary btn-small" href="account.php">Edit Profile</a>
</div>
</div>
</section>

<section>
<h2>Latest Results<?= $latestSemester ? ' &middot; ' . htmlspecialchars(semester_label($latestSemester)) : '' ?></h2>
<?php if (empty($latestResults)): ?>
<div class="empty-state">
<div class="empty-state-icon">&#128202;</div>
<p>No results have been released for you yet.</p>
</div>
<?php else: ?>
<table>
<tr><th>Course Code</th><th>Course</th><th>Credit Hours</th><th>Grade</th><th>Grade Point</th></tr>
<?php foreach ($latestResults as $r): ?>
<tr>
<td><?= htmlspecialchars($r['code']) ?></td>
<td><?= htmlspecialchars($r['name']) ?></td>
<td><?= (int)$r['credit_hours'] ?></td>
<td><strong><?= htmlspecialchars($r['grade']) ?></strong></td>
<td><?= number_format($r['grade_point'], 2) ?></td>
</tr>
<?php endforeach; ?>
</table>
<p><a class="btn btn-secondary btn-small" href="results.php">View all semesters</a></p>
<?php endif; ?>
</section>
<?php endif; ?>

<section>
<h2>Request a Document</h2>
<form method="get" class="filter-bar" id="service-filter-form">
<label>Search <input type="text" name="q" id="service-search" placeholder="Document name..." value="<?= htmlspecialchars($search) ?>" autocomplete="off"></label>
<button type="submit">Search</button>
<?php if ($search !== ''): ?><a class="btn btn-secondary" href="index.php">Clear</a><?php endif; ?>
</form>
<script>
(function () {
    var input = document.getElementById('service-search');
    var form = document.getElementById('service-filter-form');
    if (!input || !form) return;
    var timer;
    input.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(function () {
            form.submit();
        }, 500);
    });
})();
</script>

<?php if (empty($documentTypes)): ?>
<div class="empty-state">
<div class="empty-state-icon">&#128269;</div>
<p>No documents match your search.</p>
<a class="btn btn-small btn-secondary" href="index.php">Clear filters</a>
</div>
<?php else: ?>
<div class="card-grid">
<?php foreach ($documentTypes as $d): ?>
<div class="card">
<img class="card-thumb" src="<?= htmlspecialchars(entity_image_url($d)) ?>" alt="<?= htmlspecialchars($d['name']) ?>" loading="lazy">
<h3><?= htmlspecialchars($d['name']) ?></h3>
<p><?= (float)$d['fee'] > 0 ? 'RM' . number_format($d['fee'], 2) . ' per copy' : 'Free' ?> &middot; ready in <?= (int)$d['processing_days'] ?> working day<?= (int)$d['processing_days'] === 1 ? '' : 's' ?></p>
<?php if (current_user_id()): ?>
<a class="btn" href="create.php?document_type_id=<?= (int)$d['id'] ?>">Request</a>
<?php else: ?>
<a class="btn" href="login.php">Login to Request</a>
<?php endif; ?>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</section>

<section>
<h2>My Requests</h2>
<?php if (!current_user_id()): ?>
<p><a href="login.php">Login</a> or <a href="register.php">register</a> to see your results and request documents.</p>
<?php elseif (empty($myRequests)): ?>
<div class="empty-state">
<div class="empty-state-icon">&#128196;</div>
<p>You haven't requested any documents yet.</p>
</div>
<?php else: ?>
<table>
<tr><th>Requested</th><th>Document</th><th>Copies</th><th>Delivery</th><th>Fee (RM)</th><th>Expected Ready</th><th>Status</th><th>Actions</th></tr>
<?php foreach ($myRequests as $r): ?>
<tr>
<td><?= htmlspecialchars(date('d M Y', strtotime($r['created_at']))) ?></td>
<td><strong><?= htmlspecialchars($r['document_name']) ?></strong><br><span class="stat-label"><?= htmlspecialchars($r['purpose']) ?></span></td>
<td><?= (int)$r['copies'] ?></td>
<td><?= htmlspecialchars($r['delivery_method']) ?></td>
<td><?= number_format($r['total_fee'], 2) ?></td>
<td><?= request_is_active($r['status']) ? htmlspecialchars(date('d M Y', strtotime(expected_ready_date($r['created_at'], (int)$r['processing_days'])))) : '&mdash;' ?></td>
<td><span class="badge <?= status_badge_class($r['status']) ?>"><?= htmlspecialchars($r['status']) ?></span>
<?php if ($r['admin_note']): ?><br><span class="stat-label"><?= htmlspecialchars($r['admin_note']) ?></span><?php endif; ?></td>
<td>
<?php if ($r['status'] === 'Pending'): ?>
<a class="btn btn-secondary btn-small" href="edit.php?id=<?= (int)$r['id'] ?>">Edit</a>
<form action="delete.php" method="post" style="display:inline" onsubmit="return confirm('Cancel this request?');">
<input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
<button type="submit" class="btn-small btn-danger">Cancel</button>
</form>
<?php else: ?>
<span class="stat-label">Locked</span>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</table>
<p class="form-hint">Requests can only be edited or cancelled while they are still <strong>Pending</strong>.</p>
<?php endif; ?>
</section>
<?php require 'partials/footer.php'; ?>
