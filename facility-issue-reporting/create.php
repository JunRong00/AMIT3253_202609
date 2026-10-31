<?php
require 'config.php';
require 'auth.php';
require 'helpers.php';
require_login();

$error = '';
$selectedLocation = (int)($_GET['location_id'] ?? 0);
$form = ['category' => '', 'title' => '', 'description' => '', 'priority' => 'Medium'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $location_id = (int)($_POST['location_id'] ?? 0);
    $category    = $_POST['category'] ?? '';
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $priority    = $_POST['priority'] ?? '';
    $uid         = current_user_id();
    $selectedLocation = $location_id;
    $form = compact('category', 'title', 'description', 'priority');

    if ($title === '' || $description === '') {
        $error = 'Please give the issue a short title and a description.';
    } elseif (!in_array($category, issue_categories(), true) || !in_array($priority, issue_priorities(), true)) {
        $error = 'Please choose a valid category and priority.';
    } else {
        $conn->begin_transaction();

        // Lock the location row so two people reporting the same problem at
        // the same instant can't both slip past the duplicate check below.
        $stmt = $conn->prepare('SELECT id FROM locations WHERE id = ? FOR UPDATE');
        $stmt->bind_param('i', $location_id);
        $stmt->execute();
        $location = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$location) {
            $error = 'Location not found.';
            $conn->rollback();
        } else {
            $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM issues WHERE location_id = ? AND category = ? AND status IN ('Open', 'In Progress')");
            $stmt->bind_param('is', $location_id, $category);
            $stmt->execute();
            $duplicates = (int)$stmt->get_result()->fetch_assoc()['c'];
            $stmt->close();

            if ($duplicates > 0) {
                $error = 'A "' . $category . '" issue at this location has already been reported and is being handled. Check the Issue Board for its status.';
                $conn->rollback();
            } else {
                $stmt = $conn->prepare('INSERT INTO issues (user_id, location_id, category, title, description, priority) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->bind_param('iissss', $uid, $location_id, $category, $title, $description, $priority);
                $stmt->execute();
                $stmt->close();
                $conn->commit();
                header('Location: index.php');
                exit;
            }
        }
    }
}

$locations = $conn->query('SELECT * FROM locations ORDER BY building, name');

$pageTitle = 'Report an Issue';
require 'partials/header.php';
?>
<div class="form-card">
<h1>Report an Issue</h1>
<?php if ($error): ?><p class="alert alert-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form method="post">
<label>Location <span class="required-mark">*</span>
<select name="location_id" id="location-select" required>
<?php while ($l = $locations->fetch_assoc()): ?>
<option value="<?= (int)$l['id'] ?>" <?= $l['id'] == $selectedLocation ? 'selected' : '' ?>><?= htmlspecialchars(location_label($l)) ?> (<?= htmlspecialchars($l['floor']) ?>)</option>
<?php endwhile; ?>
</select>
</label>
<label>Category <span class="required-mark">*</span>
<select name="category" id="category-select" required>
<option value="">-- Select a category --</option>
<?php foreach (issue_categories() as $c): ?>
<option value="<?= htmlspecialchars($c) ?>" <?= $form['category'] === $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
<?php endforeach; ?>
</select>
</label>
<p class="form-hint" id="category-hint"></p>
<label>Title <span class="required-mark">*</span> <input type="text" name="title" maxlength="150" placeholder="e.g. Projector not turning on" value="<?= htmlspecialchars($form['title']) ?>" required></label>
<label>Description <span class="required-mark">*</span> <textarea name="description" rows="4" placeholder="What's wrong, and exactly where?" required><?= htmlspecialchars($form['description']) ?></textarea></label>
<label>Priority <span class="required-mark">*</span>
<select name="priority" required>
<?php foreach (issue_priorities() as $p): ?>
<option value="<?= $p ?>" <?= $form['priority'] === $p ? 'selected' : '' ?>><?= $p ?></option>
<?php endforeach; ?>
</select>
</label>
<button type="submit">Submit Report</button>
</form>
<script>
(function () {
    var locationSelect = document.getElementById('location-select');
    var categorySelect = document.getElementById('category-select');
    var hint = document.getElementById('category-hint');

    function refresh() {
        Array.prototype.forEach.call(categorySelect.options, function (opt) {
            if (opt.dataset.originalText) {
                opt.textContent = opt.dataset.originalText;
            }
            opt.disabled = false;
        });
        hint.textContent = '';

        fetch('issue_availability.php?location_id=' + encodeURIComponent(locationSelect.value))
            .then(function (res) { return res.json(); })
            .then(function (data) {
                var reported = data.active_categories || [];
                Array.prototype.forEach.call(categorySelect.options, function (opt) {
                    if (reported.indexOf(opt.value) !== -1) {
                        opt.dataset.originalText = opt.dataset.originalText || opt.textContent;
                        opt.textContent = opt.dataset.originalText + ' (Already reported)';
                        opt.disabled = true;
                        if (categorySelect.value === opt.value) {
                            categorySelect.value = '';
                        }
                    }
                });
                if (reported.length > 0) {
                    hint.textContent = 'Greyed-out categories already have an active report at this location.';
                }
            })
            .catch(function () { /* availability check is a convenience, not required to report */ });
    }

    locationSelect.addEventListener('change', refresh);
    refresh();
})();
</script>
<p><a class="btn btn-secondary btn-small" href="index.php">Back to home</a></p>
</div>
<?php require 'partials/footer.php'; ?>
