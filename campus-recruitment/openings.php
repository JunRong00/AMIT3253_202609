<?php
require 'config.php';
require 'auth.php';
require 'helpers.php';

$openings = $conn->query("
    SELECT o.*,
           (SELECT COUNT(*) FROM applications a WHERE a.opening_id = o.id) AS applicants,
           (SELECT COUNT(*) FROM applications a WHERE a.opening_id = o.id AND a.status = 'Accepted') AS filled
    FROM openings o
    ORDER BY o.deadline
")->fetch_all(MYSQLI_ASSOC);

$byCategory = [];
foreach ($openings as $o) {
    $byCategory[$o['category']][] = $o;
}

$openCount = count(array_filter($openings, fn($o) => !is_deadline_passed($o['deadline'])));
$totalVacancies = array_sum(array_map(
    fn($o) => is_deadline_passed($o['deadline']) ? 0 : (int)$o['vacancies'],
    $openings
));

$pageTitle = 'All Openings';
require 'partials/header.php';
?>
<div class="page-header">
<h1>All Openings</h1>
<p>Every opportunity on campus, grouped by category, with full details.</p>
</div>

<section>
<div class="card-grid">
<div class="card"><h3><?= (int)$openCount ?></h3><p>Openings accepting applications</p></div>
<div class="card"><h3><?= (int)$totalVacancies ?></h3><p>Vacancies on offer</p></div>
</div>
</section>

<?php foreach (opening_categories() as $category): ?>
<?php if (empty($byCategory[$category])) continue; ?>
<section>
<h2><?= htmlspecialchars($category) ?></h2>
<?php foreach ($byCategory[$category] as $o): ?>
<?php $closed = is_deadline_passed($o['deadline']); $isFilled = (int)$o['filled'] >= (int)$o['vacancies']; ?>
<div class="card" style="max-width:720px; margin-bottom:20px;">
<img class="card-thumb" src="<?= htmlspecialchars(entity_image_url($o)) ?>" alt="<?= htmlspecialchars($o['title']) ?>" loading="lazy">
<?php if ($closed): ?>
<span class="badge badge-neutral">Closed</span>
<?php elseif ($isFilled): ?>
<span class="badge badge-neutral">All vacancies filled</span>
<?php else: ?>
<span class="badge badge-success">Accepting applications</span>
<?php endif; ?>
<h3><?= htmlspecialchars($o['title']) ?></h3>
<p><strong><?= htmlspecialchars($o['organisation']) ?></strong> &middot; &#128205; <?= htmlspecialchars($o['location']) ?></p>
<p><?= nl2br(htmlspecialchars($o['description'])) ?></p>
<p><?= (int)$o['vacancies'] ?> vacanc<?= (int)$o['vacancies'] === 1 ? 'y' : 'ies' ?> &middot;
<?= (int)$o['applicants'] ?> applicant<?= (int)$o['applicants'] === 1 ? '' : 's' ?> so far &middot;
deadline <?= htmlspecialchars(date('d M Y', strtotime($o['deadline']))) ?></p>
<?php if (!$closed && !$isFilled): ?>
<?php if (current_user_id()): ?>
<a class="btn" href="create.php?opening_id=<?= (int)$o['id'] ?>">Apply Now</a>
<?php else: ?>
<a class="btn" href="login.php">Login to Apply</a>
<?php endif; ?>
<?php endif; ?>
</div>
<?php endforeach; ?>
</section>
<?php endforeach; ?>
<?php require 'partials/footer.php'; ?>
