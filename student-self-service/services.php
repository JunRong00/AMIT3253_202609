<?php
require 'config.php';
require 'auth.php';
require 'helpers.php';

$documentTypes = $conn->query('SELECT * FROM document_types ORDER BY name')->fetch_all(MYSQLI_ASSOC);

$totalTypes = count($documentTypes);
$requestsThisMonth = (int)$conn->query("
    SELECT COUNT(*) AS c FROM document_requests WHERE created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
")->fetch_assoc()['c'];

$pageTitle = 'Services';
require 'partials/header.php';
?>
<div class="page-header">
<h1>Document Services</h1>
<p>Official documents you can request from the Registry, with fees and processing times.</p>
</div>

<section>
<div class="card-grid">
<div class="card"><h3><?= (int)$totalTypes ?></h3><p>Documents available</p></div>
<div class="card"><h3><?= (int)$requestsThisMonth ?></h3><p>Requests this month</p></div>
</div>
</section>

<?php foreach ($documentTypes as $d): ?>
<section>
<div class="card" style="max-width:720px;">
<img class="card-thumb" src="<?= htmlspecialchars(entity_image_url($d)) ?>" alt="<?= htmlspecialchars($d['name']) ?>" loading="lazy">
<h3><?= htmlspecialchars($d['name']) ?></h3>
<p><?= htmlspecialchars($d['description']) ?></p>
<p><?= (float)$d['fee'] > 0 ? 'RM' . number_format($d['fee'], 2) . ' per copy' : 'Free of charge' ?> 
&middot; ready in <?= (int)$d['processing_days'] ?> working day<?= (int)$d['processing_days'] === 1 ? '' : 's' ?> 
&middot; up to <?= (int)$d['max_copies'] ?> cop<?= (int)$d['max_copies'] === 1 ? 'y' : 'ies' ?> per request</p>
<?php if (current_user_id()): ?>
<a class="btn" href="create.php?document_type_id=<?= (int)$d['id'] ?>">Request</a>
<?php else: ?>
<a class="btn" href="login.php">Login to Request</a>
<?php endif; ?>
</div>
</section>
<?php endforeach; ?>
<?php require 'partials/footer.php'; ?>
