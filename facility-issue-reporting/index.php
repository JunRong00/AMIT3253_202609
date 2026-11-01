<?php
require 'config.php';
require 'auth.php';
require 'helpers.php';

$search = trim($_GET['q'] ?? '');

$locationSql = "
    SELECT l.*, COUNT(i.id) AS active_issues
    FROM locations l
    LEFT JOIN issues i ON i.location_id = l.id AND i.status IN ('Open', 'In Progress')
";
if ($search !== '') {
    $stmt = $conn->prepare($locationSql . ' WHERE l.building LIKE ? OR l.name LIKE ? GROUP BY l.id ORDER BY l.building, l.name');
    $likeSearch = '%' . $search . '%';
    $stmt->bind_param('ss', $likeSearch, $likeSearch);
    $stmt->execute();
    $locations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $locations = $conn->query($locationSql . ' GROUP BY l.id ORDER BY l.building, l.name')->fetch_all(MYSQLI_ASSOC);
}

$myIssues = [];
if ($uid = current_user_id()) {
    $stmt = $conn->prepare('
        SELECT i.id, i.title, i.category, i.priority, i.status, i.admin_note, i.created_at, l.building, l.name
        FROM issues i
        JOIN locations l ON l.id = i.location_id
        WHERE i.user_id = ?
        ORDER BY i.created_at DESC
    ');
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $myIssues = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$pageTitle = 'Campus Facility Issue Reporting';
require 'partials/header.php';
?>
<section class="hero">
<h1>Campus Facility Issue Reporting</h1>
<p>Spotted something broken on campus? Report it here and follow the repair status online.</p>
</section>

<section>
<h2>Campus Locations</h2>
<form method="get" class="filter-bar" id="location-filter-form">
<label>Search <input type="text" name="q" id="location-search" placeholder="Building or room..." value="<?= htmlspecialchars($search) ?>" autocomplete="off"></label>
<button type="submit">Search</button>
<?php if ($search !== ''): ?><a class="btn btn-secondary" href="index.php">Clear</a><?php endif; ?>
</form>
<script>
(function () {
    var input = document.getElementById('location-search');
    var form = document.getElementById('location-filter-form');
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

<?php if (empty($locations)): ?>
<div class="empty-state">
<div class="empty-state-icon">&#128269;</div>
<p>No locations match your search.</p>
<a class="btn btn-small btn-secondary" href="index.php">Clear filters</a>
</div>
<?php else: ?>
<div class="card-grid">
<?php foreach ($locations as $l): ?>
<div class="card">
<img class="card-thumb" src="<?= htmlspecialchars(entity_image_url($l)) ?>" alt="<?= htmlspecialchars($l['name']) ?>" loading="lazy">
<?php if ((int)$l['active_issues'] > 0): ?>
<span class="badge badge-warning"><?= (int)$l['active_issues'] ?> active issue<?= (int)$l['active_issues'] === 1 ? '' : 's' ?></span>
<?php else: ?>
<span class="badge badge-success">No active issues</span>
<?php endif; ?>
<h3><?= htmlspecialchars($l['name']) ?></h3>
<p><?= htmlspecialchars($l['building']) ?> &middot; <?= htmlspecialchars($l['floor']) ?></p>
<?php if (current_user_id()): ?>
<a class="btn" href="create.php?location_id=<?= (int)$l['id'] ?>">Report an Issue</a>
<?php else: ?>
<a class="btn" href="login.php">Login to Report</a>
<?php endif; ?>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</section>

<section>
<h2>My Reports</h2>
<?php if (!current_user_id()): ?>
<p><a href="login.php">Login</a> or <a href="register.php">register</a> to report issues and follow their status.</p>
<?php elseif (empty($myIssues)): ?>
<div class="empty-state">
<div class="empty-state-icon">&#128295;</div>
<p>You haven't reported any issues yet.</p>
</div>
<?php else: ?>
<table>
<tr><th>Reported</th><th>Location</th><th>Issue</th><th>Priority</th><th>Status</th><th>Update from Estate Office</th><th>Actions</th></tr>
<?php foreach ($myIssues as $i): ?>
<tr>
<td><?= htmlspecialchars(date('d M Y', strtotime($i['created_at']))) ?></td>
<td><?= htmlspecialchars(location_label($i)) ?></td>
<td><strong><?= htmlspecialchars($i['title']) ?></strong><br><span class="stat-label"><?= htmlspecialchars($i['category']) ?></span></td>
<td><span class="badge <?= priority_badge_class($i['priority']) ?>"><?= htmlspecialchars($i['priority']) ?></span></td>
<td><span class="badge <?= status_badge_class($i['status']) ?>"><?= htmlspecialchars($i['status']) ?></span></td>
<td><?= $i['admin_note'] !== null && $i['admin_note'] !== '' ? htmlspecialchars($i['admin_note']) : '<span class="stat-label">&mdash;</span>' ?></td>
<td>
<?php if ($i['status'] === 'Open'): ?>
<a class="btn btn-secondary btn-small" href="edit.php?id=<?= (int)$i['id'] ?>">Edit</a>
<form action="delete.php" method="post" style="display:inline" onsubmit="return confirm('Withdraw this report?');">
<input type="hidden" name="id" value="<?= (int)$i['id'] ?>">
<button type="submit" class="btn-small btn-danger">Withdraw</button>
</form>
<?php else: ?>
<span class="stat-label">Locked</span>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</table>
<p class="form-hint">Reports can only be edited or withdrawn while they are still <strong>Open</strong> &mdash; once the Estate Office starts work they are locked.</p>
<?php endif; ?>
</section>
<?php require 'partials/footer.php'; ?>
