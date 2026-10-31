<?php
require 'config.php';
require 'auth.php';
require 'helpers.php';
require_login();

$error = '';
$uid = current_user_id();
$selectedOpening = (int)($_GET['opening_id'] ?? 0);
$form = ['phone' => '', 'cover_note' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $opening_id = (int)($_POST['opening_id'] ?? 0);
    $phone      = trim($_POST['phone'] ?? '');
    $cover_note = trim($_POST['cover_note'] ?? '');
    $selectedOpening = $opening_id;
    $form = compact('phone', 'cover_note');

    if ($phone === '' || $cover_note === '') {
        $error = 'Please enter a contact number and a short cover note.';
    } elseif (!is_valid_phone($phone)) {
        $error = 'Please enter a valid contact number, e.g. 012-345 6789.';
    } else {
        $conn->begin_transaction();

        // Lock the opening row so the deadline/vacancy/duplicate checks below
        // and the insert happen as one step, even under heavy concurrent load.
        $stmt = $conn->prepare('SELECT title, deadline, vacancies FROM openings WHERE id = ? FOR UPDATE');
        $stmt->bind_param('i', $opening_id);
        $stmt->execute();
        $opening = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$opening) {
            $error = 'Opening not found.';
            $conn->rollback();
        } elseif (is_deadline_passed($opening['deadline'])) {
            $error = 'Applications for this opening have closed.';
            $conn->rollback();
        } else {
            $stmt = $conn->prepare("
                SELECT
                    SUM(status = 'Accepted') AS filled,
                    SUM(user_id = ?) AS mine
                FROM applications WHERE opening_id = ?
            ");
            $stmt->bind_param('ii', $uid, $opening_id);
            $stmt->execute();
            $counts = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ((int)$counts['mine'] > 0) {
                $error = 'You have already applied for this opening. You can edit your application from your homepage.';
                $conn->rollback();
            } elseif ((int)$counts['filled'] >= (int)$opening['vacancies']) {
                $error = 'All vacancies for this opening have already been filled.';
                $conn->rollback();
            } else {
                $stmt = $conn->prepare('INSERT INTO applications (user_id, opening_id, phone, cover_note) VALUES (?, ?, ?, ?)');
                $stmt->bind_param('iiss', $uid, $opening_id, $phone, $cover_note);
                $stmt->execute();
                $stmt->close();
                $conn->commit();
                header('Location: index.php');
                exit;
            }
        }
    }
}

$openings = $conn->query('SELECT id, title, organisation, deadline FROM openings WHERE deadline >= CURDATE() ORDER BY title');

$pageTitle = 'Apply';
require 'partials/header.php';
?>
<div class="form-card">
<h1>Apply for an Opening</h1>
<?php if ($error): ?><p class="alert alert-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form method="post">
<label>Opening <span class="required-mark">*</span>
<select name="opening_id" id="opening-select" required>
<?php while ($o = $openings->fetch_assoc()): ?>
<option value="<?= (int)$o['id'] ?>" <?= $o['id'] == $selectedOpening ? 'selected' : '' ?>><?= htmlspecialchars($o['title']) ?> - <?= htmlspecialchars($o['organisation']) ?> (closes <?= htmlspecialchars(date('d M', strtotime($o['deadline']))) ?>)</option>
<?php endwhile; ?>
</select>
</label>
<p class="form-hint" id="opening-hint"></p>
<label>Contact Number <span class="required-mark">*</span> <input type="tel" name="phone" placeholder="e.g. 012-345 6789" value="<?= htmlspecialchars($form['phone']) ?>" required></label>
<label>Cover Note <span class="required-mark">*</span> <textarea name="cover_note" rows="5" placeholder="Why are you a good fit for this opening?" required><?= htmlspecialchars($form['cover_note']) ?></textarea></label>
<button type="submit">Submit Application</button>
</form>
<script>
(function () {
    var select = document.getElementById('opening-select');
    var hint = document.getElementById('opening-hint');

    fetch('opening_availability.php')
        .then(function (res) { return res.json(); })
        .then(function (data) {
            var applied = (data.applied_opening_ids || []).map(String);
            var filled = (data.filled_opening_ids || []).map(String);
            Array.prototype.forEach.call(select.options, function (opt) {
                var label = applied.indexOf(opt.value) !== -1 ? 'Already applied'
                          : filled.indexOf(opt.value) !== -1 ? 'Filled' : null;
                if (label) {
                    opt.textContent = opt.textContent + ' (' + label + ')';
                    opt.disabled = true;
                    if (select.value === opt.value) {
                        select.value = '';
                    }
                }
            });
            if (applied.length || filled.length) {
                hint.textContent = 'Greyed-out openings are ones you have already applied for, or whose vacancies are all filled.';
            }
        })
        .catch(function () { /* availability check is a convenience, not required to apply */ });
})();
</script>
<p><a class="btn btn-secondary btn-small" href="index.php">Back to home</a></p>
</div>
<?php require 'partials/footer.php'; ?>
