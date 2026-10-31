<?php
require 'config.php';
require 'auth.php';
require 'helpers.php';
require_login();

$error = '';
$uid = current_user_id();
$selectedType = (int)($_GET['document_type_id'] ?? 0);
$form = ['copies' => 1, 'purpose' => '', 'delivery_method' => delivery_methods()[0]];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $document_type_id = (int)($_POST['document_type_id'] ?? 0);
    $copies           = (int)($_POST['copies'] ?? 0);
    $purpose          = trim($_POST['purpose'] ?? '');
    $delivery_method  = $_POST['delivery_method'] ?? '';
    $selectedType = $document_type_id;
    $form = compact('copies', 'purpose', 'delivery_method');

    if ($purpose === '' || $copies < 1) {
        $error = 'Please state the purpose and choose at least 1 copy.';
    } elseif (!in_array($delivery_method, delivery_methods(), true)) {
        $error = 'Please choose a valid delivery method.';
    } else {
        $conn->begin_transaction();

        // Lock this student's own row so a double-click (or two browser tabs)
        // can't create two active requests for the same document at once.
        $stmt = $conn->prepare('SELECT id FROM users WHERE id = ? FOR UPDATE');
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare('SELECT name, fee, max_copies FROM document_types WHERE id = ?');
        $stmt->bind_param('i', $document_type_id);
        $stmt->execute();
        $type = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$type) {
            $error = 'Document type not found.';
            $conn->rollback();
        } elseif ($copies > (int)$type['max_copies']) {
            $error = 'You can request at most ' . (int)$type['max_copies'] . ' copies of this document at a time.';
            $conn->rollback();
        } else {
            $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM document_requests WHERE user_id = ? AND document_type_id = ? AND status IN ('Pending', 'Processing')");
            $stmt->bind_param('ii', $uid, $document_type_id);
            $stmt->execute();
            $active = (int)$stmt->get_result()->fetch_assoc()['c'];
            $stmt->close();

            if ($active > 0) {
                $error = 'You already have a ' . $type['name'] . ' request in progress. You can edit it from your homepage while it is still Pending.';
                $conn->rollback();
            } else {
                $total_fee = $type['fee'] * $copies;

                $stmt = $conn->prepare('INSERT INTO document_requests (user_id, document_type_id, copies, purpose, delivery_method, total_fee) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->bind_param('iiissd', $uid, $document_type_id, $copies, $purpose, $delivery_method, $total_fee);
                $stmt->execute();
                $stmt->close();
                $conn->commit();
                header('Location: index.php');
                exit;
            }
        }
    }
}

$types = $conn->query('SELECT * FROM document_types ORDER BY name');

$pageTitle = 'Request a Document';
require 'partials/header.php';
?>
<div class="form-card">
<h1>Request a Document</h1>
<?php if ($error): ?><p class="alert alert-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form method="post">
<label>Document <span class="required-mark">*</span>
<select name="document_type_id" id="type-select" required>
<?php while ($t = $types->fetch_assoc()): ?>
<option value="<?= (int)$t['id'] ?>" data-fee="<?= htmlspecialchars($t['fee']) ?>" data-max="<?= (int)$t['max_copies'] ?>" <?= $t['id'] == $selectedType ? 'selected' : '' ?>><?= htmlspecialchars($t['name']) ?> - <?= (float)$t['fee'] > 0 ? 'RM' . number_format($t['fee'], 2) . '/copy' : 'Free' ?></option>
<?php endwhile; ?>
</select>
</label>
<p class="form-hint" id="type-hint"></p>
<label>Number of Copies <span class="required-mark">*</span> <input type="number" name="copies" id="copies-input" min="1" value="<?= (int)$form['copies'] ?>" required></label>
<label>Purpose <span class="required-mark">*</span> <input type="text" name="purpose" maxlength="255" placeholder="e.g. Internship application" value="<?= htmlspecialchars($form['purpose']) ?>" required></label>
<label>Delivery Method <span class="required-mark">*</span>
<select name="delivery_method" required>
<?php foreach (delivery_methods() as $m): ?>
<option value="<?= htmlspecialchars($m) ?>" <?= $form['delivery_method'] === $m ? 'selected' : '' ?>><?= htmlspecialchars($m) ?></option>
<?php endforeach; ?>
</select>
</label>
<p><strong>Total fee: RM<span id="fee-total">0.00</span></strong> <span class="stat-label">(pay at the Registry counter or online banking)</span></p>
<button type="submit">Submit Request</button>
</form>
<script>
(function () {
    var typeSelect = document.getElementById('type-select');
    var copiesInput = document.getElementById('copies-input');
    var feeTotal = document.getElementById('fee-total');
    var hint = document.getElementById('type-hint');

    function updateFee() {
        var opt = typeSelect.options[typeSelect.selectedIndex];
        if (!opt) { feeTotal.textContent = '0.00'; return; }
        copiesInput.max = opt.dataset.max;
        var copies = Math.max(0, parseInt(copiesInput.value, 10) || 0);
        feeTotal.textContent = (parseFloat(opt.dataset.fee) * copies).toFixed(2);
    }

    typeSelect.addEventListener('change', updateFee);
    copiesInput.addEventListener('input', updateFee);
    updateFee();

    fetch('request_availability.php')
        .then(function (res) { return res.json(); })
        .then(function (data) {
            var active = (data.active_type_ids || []).map(String);
            Array.prototype.forEach.call(typeSelect.options, function (opt) {
                if (active.indexOf(opt.value) !== -1) {
                    opt.textContent = opt.textContent + ' (Request in progress)';
                    opt.disabled = true;
                    if (typeSelect.value === opt.value) {
                        typeSelect.value = '';
                    }
                }
            });
            if (active.length) {
                hint.textContent = 'Greyed-out documents already have a request of yours in progress.';
            }
            updateFee();
        })
        .catch(function () { /* availability check is a convenience, not required to request */ });
})();
</script>
<p><a class="btn btn-secondary btn-small" href="index.php">Back to home</a></p>
</div>
<?php require 'partials/footer.php'; ?>
