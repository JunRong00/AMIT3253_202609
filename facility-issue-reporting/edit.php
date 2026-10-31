<?php
require 'config.php';
require 'auth.php';
require 'helpers.php';
require_login();

$id  = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$uid = current_user_id();
$error = '';

$stmt = $conn->prepare('SELECT i.*, l.building, l.name, l.floor FROM issues i JOIN locations l ON l.id = i.location_id WHERE i.id = ? AND i.user_id = ?');
$stmt->bind_param('ii', $id, $uid);
$stmt->execute();
$issue = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$issue) {
    die('Report not found or you do not have permission to edit it.');
}
if ($issue['status'] !== 'Open') {
    die('This report is already being handled by the Estate Office and can no longer be edited.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category    = $_POST['category'] ?? '';
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $priority    = $_POST['priority'] ?? '';

    if ($title === '' || $description === '') {
        $error = 'Please give the issue a short title and a description.';
    } elseif (!in_array($category, issue_categories(), true) || !in_array($priority, issue_priorities(), true)) {
        $error = 'Please choose a valid category and priority.';
    } else {
        $conn->begin_transaction();

        $location_id = (int)$issue['location_id'];
        $stmt = $conn->prepare('SELECT id FROM locations WHERE id = ? FOR UPDATE');
        $stmt->bind_param('i', $location_id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM issues WHERE location_id = ? AND category = ? AND status IN ('Open', 'In Progress') AND id != ?");
        $stmt->bind_param('isi', $location_id, $category, $id);
        $stmt->execute();
        $duplicates = (int)$stmt->get_result()->fetch_assoc()['c'];
        $stmt->close();

        if ($duplicates > 0) {
            $error = 'A "' . $category . '" issue at this location has already been reported and is being handled.';
            $conn->rollback();
        } else {
            $stmt = $conn->prepare("UPDATE issues SET category=?, title=?, description=?, priority=? WHERE id=? AND user_id=? AND status='Open'");
            $stmt->bind_param('ssssii', $category, $title, $description, $priority, $id, $uid);
            $stmt->execute();
            $stmt->close();
            $conn->commit();
            header('Location: index.php');
            exit;
        }
    }
    $issue = array_merge($issue, compact('category', 'title', 'description', 'priority'));
}

$pageTitle = 'Edit Report';
require 'partials/header.php';
?>
<div class="form-card">
<h1>Edit Report</h1>
<?php if ($error): ?><p class="alert alert-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form method="post">
<input type="hidden" name="id" value="<?= (int)$issue['id'] ?>">
<label>Location <input type="text" value="<?= htmlspecialchars(location_label($issue)) ?> (<?= htmlspecialchars($issue['floor']) ?>)" disabled></label>
<label>Category <span class="required-mark">*</span>
<select name="category" id="category-select" required>
<?php foreach (issue_categories() as $c): ?>
<option value="<?= htmlspecialchars($c) ?>" <?= $issue['category'] === $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
<?php endforeach; ?>
</select>
</label>
<p class="form-hint" id="category-hint"></p>
<label>Title <span class="required-mark">*</span> <input type="text" name="title" maxlength="150" value="<?= htmlspecialchars($issue['title']) ?>" required></label>
<label>Description <span class="required-mark">*</span> <textarea name="description" rows="4" required><?= htmlspecialchars($issue['description']) ?></textarea></label>
<label>Priority <span class="required-mark">*</span>
<select name="priority" required>
<?php foreach (issue_priorities() as $p): ?>
<option value="<?= $p ?>" <?= $issue['priority'] === $p ? 'selected' : '' ?>><?= $p ?></option>
<?php endforeach; ?>
</select>
</label>
<button type="submit">Update Report</button>
</form>
<script>
(function () {
    var categorySelect = document.getElementById('category-select');
    var hint = document.getElementById('category-hint');

    fetch('issue_availability.php?location_id=<?= (int)$issue['location_id'] ?>&exclude_issue_id=<?= (int)$issue['id'] ?>')
        .then(function (res) { return res.json(); })
        .then(function (data) {
            var reported = data.active_categories || [];
            Array.prototype.forEach.call(categorySelect.options, function (opt) {
                if (reported.indexOf(opt.value) !== -1) {
                    opt.textContent = opt.textContent + ' (Already reported)';
                    opt.disabled = true;
                }
            });
            if (reported.length > 0) {
                hint.textContent = 'Greyed-out categories already have an active report at this location.';
            }
        })
        .catch(function () { /* availability check is a convenience, not required to save */ });
})();
</script>
<p><a class="btn btn-secondary btn-small" href="index.php">Back to home</a></p>
</div>
<?php require 'partials/footer.php'; ?>
