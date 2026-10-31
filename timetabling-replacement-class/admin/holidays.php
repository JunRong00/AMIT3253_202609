<?php
require '../config.php';
require '../auth.php';
require '../helpers.php';
require_admin();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $holiday_date = $_POST['holiday_date'] ?? '';
    $name = trim($_POST['name'] ?? '');

    if ($holiday_date === '' || !strtotime($holiday_date) || $name === '') {
        $error = 'Please enter a date and a holiday name.';
    } else {
        $stmt = $conn->prepare('INSERT INTO public_holidays (holiday_date, name) VALUES (?, ?)');
        $stmt->bind_param('ss', $holiday_date, $name);
        if ($stmt->execute()) {
            $success = 'Holiday added.';
        } else {
            $error = 'There is already a holiday on that date.';
        }
        $stmt->close();
    }
}

$holidays = $conn->query("
    SELECT h.*,
           (SELECT COUNT(*) FROM replacement_classes r
            WHERE r.replacement_date = h.holiday_date AND r.status IN ('Pending', 'Approved')) AS replacements
    FROM public_holidays h
    ORDER BY h.holiday_date
")->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Public Holidays';
require 'partials/header.php';
?>
<h1>Public Holidays</h1>
<p>Holidays are shown on the timetable; regular classes that fall on one are flagged in red, and
lecturers can't book a replacement class on one.</p>
<?php if ($error): ?><p class="alert alert-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<?php if ($success): ?><p class="alert alert-success"><?= htmlspecialchars($success) ?></p><?php endif; ?>

<form method="post" class="filter-bar">
<label>Date <input type="date" name="holiday_date" required></label>
<label>Holiday <input type="text" name="name" maxlength="100" placeholder="e.g. Deepavali" required></label>
<button type="submit">+ Add Holiday</button>
</form>

<?php if (empty($holidays)): ?>
<div class="empty-state">
<div class="empty-state-icon">&#127881;</div>
<p>No public holidays added yet.</p>
</div>
<?php else: ?>
<table>
<tr><th>Date</th><th>Day</th><th>Holiday</th><th>Actions</th></tr>
<?php foreach ($holidays as $h): ?>
<tr>
<td><?= htmlspecialchars(date('d M Y', strtotime($h['holiday_date']))) ?></td>
<td><?= htmlspecialchars(date('l', strtotime($h['holiday_date']))) ?></td>
<td><?= htmlspecialchars($h['name']) ?>
<?php if ((int)$h['replacements'] > 0): ?><br><span class="badge badge-danger"><?= (int)$h['replacements'] ?> replacement class(es) booked on this date</span><?php endif; ?></td>
<td>
<form action="holiday_delete.php" method="post" style="display:inline" onsubmit="return confirm('Remove this holiday?');">
<input type="hidden" name="id" value="<?= (int)$h['id'] ?>">
<button type="submit" class="btn-small btn-danger">Remove</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
<?php require 'partials/footer.php'; ?>
