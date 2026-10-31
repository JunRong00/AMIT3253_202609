<?php
require 'config.php';
require 'auth.php';
require 'helpers.php';

$locations = $conn->query('SELECT * FROM locations ORDER BY building, name')->fetch_all(MYSQLI_ASSOC);

$activeByLocation = [];
$rows = $conn->query("
    SELECT location_id, title, category, priority, status, created_at
    FROM issues
    WHERE status IN ('Open', 'In Progress')
    ORDER BY created_at DESC
")->fetch_all(MYSQLI_ASSOC);
foreach ($rows as $row) {
    $activeByLocation[(int)$row['location_id']][] = $row;
}

$totalLocations = count($locations);
$totalActive = count($rows);
$resolvedThisMonth = (int)$conn->query("
    SELECT COUNT(*) AS c FROM issues
    WHERE status IN ('Resolved', 'Closed') AND updated_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
")->fetch_assoc()['c'];

$pageTitle = 'Campus Locations';
require 'partials/header.php';
?>
<div class="page-header">
<h1>Campus Locations</h1>
<p>Every reportable location on campus, with the issues currently being worked on.</p>
</div>

<section>
<div class="card-grid">
<div class="card"><h3><?= (int)$totalLocations ?></h3><p>Locations</p></div>
<div class="card"><h3><?= (int)$totalActive ?></h3><p>Active issues</p></div>
<div class="card"><h3><?= (int)$resolvedThisMonth ?></h3><p>Resolved this month</p></div>
</div>
</section>

<?php foreach ($locations as $l): ?>
<?php $active = $activeByLocation[(int)$l['id']] ?? []; ?>
<section>
<div class="card" style="max-width:720px;">
<img class="card-thumb" src="<?= htmlspecialchars(entity_image_url($l)) ?>" alt="<?= htmlspecialchars($l['name']) ?>" loading="lazy">
<h3><?= htmlspecialchars($l['name']) ?></h3>
<p>&#128205; <?= htmlspecialchars($l['building']) ?> &middot; <?= htmlspecialchars($l['floor']) ?></p>
<?php if (empty($active)): ?>
<p><span class="badge badge-success">No active issues</span></p>
<?php else: ?>
<?php foreach ($active as $a): ?>
<p>
<span class="badge <?= status_badge_class($a['status']) ?>"><?= htmlspecialchars($a['status']) ?></span>
<strong><?= htmlspecialchars($a['title']) ?></strong> &middot; <?= htmlspecialchars($a['category']) ?>
&middot; reported <?= htmlspecialchars(date('d M', strtotime($a['created_at']))) ?>
</p>
<?php endforeach; ?>
<?php endif; ?>
<?php if (current_user_id()): ?>
<a class="btn" href="create.php?location_id=<?= (int)$l['id'] ?>">Report an Issue</a>
<?php else: ?>
<a class="btn" href="login.php">Login to Report</a>
<?php endif; ?>
</div>
</section>
<?php endforeach; ?>
<?php require 'partials/footer.php'; ?>
