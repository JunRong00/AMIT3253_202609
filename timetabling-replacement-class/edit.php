<?php
require 'config.php';
require 'auth.php';
require 'helpers.php';
require_lecturer();

$id  = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$uid = current_user_id();
$error = '';

$stmt = $conn->prepare('SELECT r.*, c.code, c.name AS course_name FROM replacement_classes r JOIN courses c ON c.id = r.course_id WHERE r.id = ? AND r.user_id = ?');
$stmt->bind_param('ii', $id, $uid);
$stmt->execute();
$request = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$request) {
    die('Request not found or you do not have permission to edit it.');
}
if ($request['status'] !== 'Pending') {
    die('This request has already been ' . strtolower($request['status']) . ' and can no longer be edited.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $original_entry_id = (int)($_POST['original_entry_id'] ?? 0);
    $original_date    = $_POST['original_date'] ?? '';
    $replacement_date = $_POST['replacement_date'] ?? '';
    $time_slot_id     = (int)($_POST['time_slot_id'] ?? 0);
    $venue_id         = (int)($_POST['venue_id'] ?? 0);
    $class_type       = $_POST['class_type'] ?? '';
    $reason           = trim($_POST['reason'] ?? '');

    if ($reason === '') {
        $error = 'Please give a reason for the replacement class.';
    } elseif (!in_array($class_type, class_types(), true)) {
        $error = 'Please choose a valid class type.';
    } else {
        $conn->begin_transaction();

        $stmt = $conn->prepare('SELECT id FROM venues WHERE id = ? FOR UPDATE');
        $stmt->bind_param('i', $venue_id);
        $stmt->execute();
        $venue = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $error = $venue
            ? replacement_error($conn, (int)$request['course_id'], $original_entry_id, $original_date, $replacement_date, $time_slot_id, $venue_id, $id)
            : 'Please choose a valid venue.';

        if ($error !== '') {
            $conn->rollback();
        } else {
            $stmt = $conn->prepare("UPDATE replacement_classes SET original_entry_id=?, original_date=?, replacement_date=?, time_slot_id=?, venue_id=?, class_type=?, reason=? WHERE id=? AND user_id=? AND status='Pending'");
            $stmt->bind_param('issiissii', $original_entry_id, $original_date, $replacement_date, $time_slot_id, $venue_id, $class_type, $reason, $id, $uid);
            $stmt->execute();
            $stmt->close();
            $conn->commit();
            header('Location: index.php#my-requests');
            exit;
        }
    }
    $request = array_merge($request, compact('original_entry_id', 'original_date', 'replacement_date', 'time_slot_id', 'venue_id', 'class_type', 'reason'));
}

$slots = $conn->query('SELECT * FROM time_slots ORDER BY start_time')->fetch_all(MYSQLI_ASSOC);
$originalClasses = regular_classes_on($conn, (int)$request['course_id'], $request['original_date']);
$venues = $conn->query('SELECT * FROM venues ORDER BY name')->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Edit Replacement Request';
require 'partials/header.php';
?>
<div class="form-card">
<h1>Edit Replacement Request</h1>
<?php if ($error): ?><p class="alert alert-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form method="post">
<input type="hidden" name="id" value="<?= (int)$request['id'] ?>">
<input type="hidden" id="course-select" value="<?= (int)$request['course_id'] ?>">
<label>Course <input type="text" value="<?= htmlspecialchars($request['code']) ?> <?= htmlspecialchars($request['course_name']) ?>" disabled></label>
<label>Original Class Date <span class="required-mark">*</span> <input type="date" name="original_date" id="original-date" value="<?= htmlspecialchars($request['original_date']) ?>" required></label>
<label>Original Class <span class="required-mark">*</span>
<select name="original_entry_id" id="original-entry" required>
<?php if (empty($originalClasses)): ?><option value="">-- Pick the original class date first --</option><?php endif; ?>
<?php foreach ($originalClasses as $oc): ?>
<option value="<?= (int)$oc['id'] ?>" data-type="<?= htmlspecialchars($oc['class_type']) ?>" <?= (int)$oc['id'] === (int)$request['original_entry_id'] ? 'selected' : '' ?>><?= htmlspecialchars(original_class_label($oc)) ?></option>
<?php endforeach; ?>
</select>
</label>
<label>Class Type <span class="required-mark">*</span>
<select name="class_type" required>
<?php foreach (class_types() as $t): ?>
<option value="<?= $t ?>" <?= $request['class_type'] === $t ? 'selected' : '' ?>><?= $t ?></option>
<?php endforeach; ?>
</select>
</label>
<label>Replacement Date <span class="required-mark">*</span> <input type="date" name="replacement_date" id="replacement-date" min="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($request['replacement_date']) ?>" required></label>
<label>Time Slot <span class="required-mark">*</span>
<select name="time_slot_id" id="slot-select" required>
<?php foreach ($slots as $s): ?>
<option value="<?= (int)$s['id'] ?>" data-start="<?= htmlspecialchars(substr($s['start_time'], 0, 5)) ?>" <?= (int)$s['id'] === (int)$request['time_slot_id'] ? 'selected' : '' ?>><?= htmlspecialchars(time_slot_label($s)) ?></option>
<?php endforeach; ?>
</select>
</label>
<label>Venue <span class="required-mark">*</span>
<select name="venue_id" id="venue-select" required>
<?php foreach ($venues as $v): ?>
<option value="<?= (int)$v['id'] ?>" <?= (int)$v['id'] === (int)$request['venue_id'] ? 'selected' : '' ?>><?= htmlspecialchars($v['name']) ?> (<?= htmlspecialchars($v['building']) ?>, <?= (int)$v['capacity'] ?> seats)</option>
<?php endforeach; ?>
</select>
</label>
<p class="form-hint" id="availability-hint"></p>
<label>Reason <span class="required-mark">*</span> <textarea name="reason" rows="3" maxlength="500" required><?= htmlspecialchars($request['reason']) ?></textarea></label>
<button type="submit">Update Request</button>
</form>
<?php $excludeReplacementId = (int)$request['id']; require 'partials/replacement_form_script.php'; ?>
<p><a class="btn btn-secondary btn-small" href="index.php#my-requests">Back to home</a></p>
</div>
<?php require 'partials/footer.php'; ?>
