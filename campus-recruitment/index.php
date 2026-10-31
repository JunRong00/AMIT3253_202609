<?php
require 'config.php';
require 'auth.php';
require 'helpers.php';

$search   = trim($_GET['q'] ?? '');
$category = $_GET['category'] ?? '';
if (!in_array($category, opening_categories(), true)) {
    $category = '';
}

$where = ['o.deadline >= CURDATE()'];
$types = '';
$params = [];
if ($search !== '') {
    $where[] = '(o.title LIKE ? OR o.organisation LIKE ?)';
    $likeSearch = '%' . $search . '%';
    $types .= 'ss';
    $params[] = $likeSearch;
    $params[] = $likeSearch;
}
if ($category !== '') {
    $where[] = 'o.category = ?';
    $types .= 's';
    $params[] = $category;
}
$stmt = $conn->prepare("
    SELECT o.*, (SELECT COUNT(*) FROM applications a WHERE a.opening_id = o.id AND a.status = 'Accepted') AS filled
    FROM openings o
    WHERE " . implode(' AND ', $where) . '
    ORDER BY o.deadline
');
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$openings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$myApplications = [];
if ($uid = current_user_id()) {
    $stmt = $conn->prepare('
        SELECT a.id, a.status, a.created_at, o.title, o.organisation, o.category, o.deadline
        FROM applications a
        JOIN openings o ON o.id = a.opening_id
        WHERE a.user_id = ?
        ORDER BY a.created_at DESC
    ');
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $myApplications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$pageTitle = 'Campus Recruitment & Applications';
require 'partials/header.php';
?>
<section class="hero">
<h1>Campus Recruitment &amp; Applications</h1>
<p>Campus jobs, internships, club positions and scholarships &mdash; browse and apply in one place.</p>
</section>

<section>
<h2>Open Opportunities</h2>
<form method="get" class="filter-bar" id="opening-filter-form">
<label>Search <input type="text" name="q" id="opening-search" placeholder="Title or organisation..." value="<?= htmlspecialchars($search) ?>" autocomplete="off"></label>
<label>Category
<select name="category" onchange="this.form.submit()">
<option value="">All categories</option>
<?php foreach (opening_categories() as $c): ?>
<option value="<?= htmlspecialchars($c) ?>" <?= $category === $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
<?php endforeach; ?>
</select>
</label>
<button type="submit">Search</button>
<?php if ($search !== '' || $category !== ''): ?><a class="btn btn-secondary" href="index.php">Clear</a><?php endif; ?>
</form>
<script>
(function () {
    var input = document.getElementById('opening-search');
    var form = document.getElementById('opening-filter-form');
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

<?php if (empty($openings)): ?>
<div class="empty-state">
<div class="empty-state-icon">&#128269;</div>
<p>No open opportunities match your search.</p>
<a class="btn btn-small btn-secondary" href="index.php">Clear filters</a>
</div>
<?php else: ?>
<div class="card-grid">
<?php foreach ($openings as $o): ?>
<?php $daysLeft = days_until($o['deadline']); $isFilled = (int)$o['filled'] >= (int)$o['vacancies']; ?>
<div class="card">
<img class="card-thumb" src="<?= htmlspecialchars(entity_image_url($o)) ?>" alt="<?= htmlspecialchars($o['title']) ?>" loading="lazy">
<span class="badge badge-accent"><?= htmlspecialchars($o['category']) ?></span>
<h3><?= htmlspecialchars($o['title']) ?></h3>
<p><?= htmlspecialchars($o['organisation']) ?></p>
<p><?= (int)$o['vacancies'] ?> vacanc<?= (int)$o['vacancies'] === 1 ? 'y' : 'ies' ?> &middot;
closes <?= htmlspecialchars(date('d M Y', strtotime($o['deadline']))) ?>
(<?= $daysLeft === 0 ? 'today' : $daysLeft . ' day' . ($daysLeft === 1 ? '' : 's') . ' left' ?>)</p>
<?php if ($isFilled): ?>
<span class="badge badge-neutral">All vacancies filled</span>
<?php elseif (current_user_id()): ?>
<a class="btn" href="create.php?opening_id=<?= (int)$o['id'] ?>">Apply Now</a>
<?php else: ?>
<a class="btn" href="login.php">Login to Apply</a>
<?php endif; ?>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</section>

<section>
<h2>My Applications</h2>
<?php if (!current_user_id()): ?>
<p><a href="login.php">Login</a> or <a href="register.php">register</a> to apply and track your applications.</p>
<?php elseif (empty($myApplications)): ?>
<div class="empty-state">
<div class="empty-state-icon">&#128196;</div>
<p>You haven't applied for anything yet.</p>
</div>
<?php else: ?>
<table>
<tr><th>Opening</th><th>Category</th><th>Applied On</th><th>Deadline</th><th>Status</th><th>Actions</th></tr>
<?php foreach ($myApplications as $a): ?>
<tr>
<td><strong><?= htmlspecialchars($a['title']) ?></strong><br><span class="stat-label"><?= htmlspecialchars($a['organisation']) ?></span></td>
<td><?= htmlspecialchars($a['category']) ?></td>
<td><?= htmlspecialchars(date('d M Y', strtotime($a['created_at']))) ?></td>
<td><?= htmlspecialchars(date('d M Y', strtotime($a['deadline']))) ?></td>
<td><span class="badge <?= status_badge_class($a['status']) ?>"><?= htmlspecialchars($a['status']) ?></span></td>
<td>
<?php if ($a['status'] === 'Submitted' && !is_deadline_passed($a['deadline'])): ?>
<a class="btn btn-secondary btn-small" href="edit.php?id=<?= (int)$a['id'] ?>">Edit</a>
<?php endif; ?>
<?php if ($a['status'] === 'Submitted' || $a['status'] === 'Shortlisted'): ?>
<form action="delete.php" method="post" style="display:inline" onsubmit="return confirm('Withdraw this application?');">
<input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
<button type="submit" class="btn-small btn-danger">Withdraw</button>
</form>
<?php else: ?>
<span class="stat-label">Final</span>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
</section>
<?php require 'partials/footer.php'; ?>
