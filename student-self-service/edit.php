<?php
require 'config.php';
require 'auth.php';
require 'helpers.php';
require_login();

$id  = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$uid = current_user_id();
$error = '';

$stmt = $conn->prepare('SELECT dr.*, dt.name AS document_name, dt.fee, dt.max_copies FROM document_requests dr JOIN document_types dt ON dt.id = dr.document_type_id WHERE dr.id = ? AND dr.user_id = ?');
$stmt->bind_param('ii', $id, $uid);
$stmt->execute();
$request = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$request) {
    die('Request not found or you do not have permission to edit it.');
}
if ($request['status'] !== 'Pending') {
    die('This request is already being processed by the Registry and can no longer be edited.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $copies          = (int)($_POST['copies'] ?? 0);
    $purpose         = trim($_POST['purpose'] ?? '');
    $delivery_method = $_POST['delivery_method'] ?? '';

    if ($purpose === '' || $copies < 1) {
        $error = 'Please state the purpose and choose at least 1 copy.';
    } elseif ($copies > (int)$request['max_copies']) {
        $error = 'You can request at most ' . (int)$request['max_copies'] . ' copies of this document at a time.';
    } elseif (!in_array($delivery_method, delivery_methods(), true)) {
        $error = 'Please choose a valid delivery method.';
    } else {
        $total_fee = $request['fee'] * $copies;

        $stmt = $conn->prepare("UPDATE document_requests SET copies=?, purpose=?, delivery_method=?, total_fee=? WHERE id=? AND user_id=? AND status='Pending'");
        $stmt->bind_param('issdii', $copies, $purpose, $delivery_method, $total_fee, $id, $uid);
        $stmt->execute();
        $stmt->close();
        header('Location: index.php');
        exit;
    }
    $request = array_merge($request, compact('copies', 'purpose', 'delivery_method'));
}

$pageTitle = 'Edit Request';
require 'partials/header.php';
?>
<div class="form-card">
<h1>Edit Request</h1>
<?php if ($error): ?><p class="alert alert-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form method="post">
<input type="hidden" name="id" value="<?= (int)$request['id'] ?>">
<label>Document <input type="text" value="<?= htmlspecialchars($request['document_name']) ?>" disabled></label>
<label>Number of Copies <span class="required-mark">*</span> <input type="number" name="copies" id="copies-input" min="1" max="<?= (int)$request['max_copies'] ?>" value="<?= (int)$request['copies'] ?>" required></label>
<label>Purpose <span class="required-mark">*</span> <input type="text" name="purpose" maxlength="255" value="<?= htmlspecialchars($request['purpose']) ?>" required></label>
<label>Delivery Method <span class="required-mark">*</span>
<select name="delivery_method" required>
<?php foreach (delivery_methods() as $m): ?>
<option value="<?= htmlspecialchars($m) ?>" <?= $request['delivery_method'] === $m ? 'selected' : '' ?>><?= htmlspecialchars($m) ?></option>
<?php endforeach; ?>
</select>
</label>
<p><strong>Total fee: RM<span id="fee-total"><?= number_format($request['fee'] * (int)$request['copies'], 2) ?></span></strong></p>
<button type="submit">Update Request</button>
</form>
<script>
(function () {
    var copiesInput = document.getElementById('copies-input');
    var feeTotal = document.getElementById('fee-total');
    var fee = <?= json_encode((float)$request['fee']) ?>;
    copiesInput.addEventListener('input', function () {
        var copies = Math.max(0, parseInt(copiesInput.value, 10) || 0);
        feeTotal.textContent = (fee * copies).toFixed(2);
    });
})();
</script>
<p><a class="btn btn-secondary btn-small" href="index.php">Back to home</a></p>
</div>
<?php require 'partials/footer.php'; ?>
