<?php
require '../config.php';
require '../auth.php';
require '../helpers.php';
require_admin();

$error = '';
$accountTypes = ['student' => 'Student', 'lecturer' => 'Lecturer', 'admin' => 'Admin'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $account_type = $_POST['account_type'] ?? 'student';
    $name         = trim($_POST['name'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $id_number    = trim($_POST['id_number'] ?? '');
    $faculty      = trim($_POST['faculty'] ?? '');
    $password     = $_POST['password'] ?? '';
    $confirm      = $_POST['confirm_password'] ?? '';
    $courseIds    = array_map('intval', (array)($_POST['course_ids'] ?? []));
    $needsProfile = $account_type !== 'admin';

    if (!isset($accountTypes[$account_type])) {
        $error = 'Please choose a valid account type.';
    } elseif ($name === '' || $email === '' || $password === '') {
        $error = 'Name, email and password are required.';
    } elseif ($needsProfile && ($id_number === '' || $faculty === '')) {
        $error = 'Student/Staff ID and faculty are required for student and lecturer accounts.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        $stmt = $conn->prepare("SELECT email FROM users WHERE email = ? OR (id_number = ? AND id_number <> '')");
        $stmt->bind_param('ss', $email, $id_number);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($exists && $exists['email'] === $email) {
            $error = 'An account with this email already exists.';
        } elseif ($exists) {
            $error = 'An account with this Student/Staff ID already exists.';
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $is_admin = $account_type === 'admin' ? 1 : 0;
            $role = $account_type === 'lecturer' ? 'lecturer' : 'student';
            $id_number = $id_number === '' ? null : $id_number;
            $faculty = $faculty === '' ? null : $faculty;

            $conn->begin_transaction();
            $stmt = $conn->prepare('INSERT INTO users (name, email, password_hash, id_number, faculty, is_admin, role) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('sssssis', $name, $email, $password_hash, $id_number, $faculty, $is_admin, $role);
            $stmt->execute();
            $newId = $stmt->insert_id;
            $stmt->close();

            // Students can be enrolled in their courses straight away;
            // more can be added later from the Enrolments page.
            if ($account_type === 'student') {
                $stmt = $conn->prepare('INSERT IGNORE INTO enrolments (user_id, course_id) SELECT ?, id FROM courses WHERE id = ?');
                foreach ($courseIds as $courseId) {
                    $stmt->bind_param('ii', $newId, $courseId);
                    $stmt->execute();
                }
                $stmt->close();
            }
            $conn->commit();

            $_SESSION['flash_success'] = $accountTypes[$account_type] . ' account created for ' . $name . '.';
            header('Location: users.php');
            exit;
        }
    }
}

$courses = $conn->query('SELECT id, code, name FROM courses ORDER BY code')->fetch_all(MYSQLI_ASSOC);
$selectedType = $_POST['account_type'] ?? 'student';
$selectedCourses = array_map('intval', (array)($_POST['course_ids'] ?? []));

$pageTitle = 'Add User';
require 'partials/header.php';
?>
<div class="form-card">
<h1>Add User</h1>
<p>Students, lecturers and admins are all created here &mdash; there is no public registration.
Give the new user their email and password so they can log in, and ask them to change the
password from My Account.</p>
<?php if ($error): ?><p class="alert alert-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form method="post">
<label>Account Type
<select name="account_type" id="account-type">
<?php foreach ($accountTypes as $value => $label): ?>
<option value="<?= $value ?>" <?= $selectedType === $value ? 'selected' : '' ?>><?= $label ?></option>
<?php endforeach; ?>
</select>
</label>
<label>Full Name <span class="required-mark">*</span> <input type="text" name="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required></label>
<label>Email <span class="required-mark">*</span> <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required></label>
<div id="profile-fields">
<label>Student / Staff ID <span class="required-mark">*</span> <input type="text" name="id_number" value="<?= htmlspecialchars($_POST['id_number'] ?? '') ?>"></label>
<label>Faculty <span class="required-mark">*</span>
<select name="faculty">
<option value="">-- Select Faculty / Centre --</option>
<?php foreach (tarumt_faculties() as $f): ?>
<option value="<?= htmlspecialchars($f) ?>" <?= ($_POST['faculty'] ?? '') === $f ? 'selected' : '' ?>><?= htmlspecialchars($f) ?></option>
<?php endforeach; ?>
</select>
</label>
</div>
<div id="course-fields">
<label>Enrol in Courses
<select name="course_ids[]" multiple size="<?= min(6, max(3, count($courses))) ?>">
<?php foreach ($courses as $c): ?>
<option value="<?= (int)$c['id'] ?>" <?= in_array((int)$c['id'], $selectedCourses, true) ? 'selected' : '' ?>><?= htmlspecialchars($c['code']) ?> <?= htmlspecialchars($c['name']) ?></option>
<?php endforeach; ?>
</select>
</label>
<p class="form-hint">Optional. Hold Ctrl (Cmd on Mac) to select several. You can also enrol students later from Enrolments.</p>
</div>
<label>Password <span class="required-mark">*</span>
<div class="password-field">
<input type="password" name="password" required>
<button type="button" class="password-toggle" tabindex="-1" aria-label="Show password"></button>
</div>
</label>
<label>Confirm Password <span class="required-mark">*</span>
<div class="password-field">
<input type="password" name="confirm_password" required>
<button type="button" class="password-toggle" tabindex="-1" aria-label="Show password"></button>
</div>
</label>
<button type="submit">Create Account</button>
</form>
<script>
(function () {
    var type = document.getElementById('account-type');
    var profile = document.getElementById('profile-fields');
    var courses = document.getElementById('course-fields');
    function refresh() {
        profile.style.display = type.value === 'admin' ? 'none' : '';
        courses.style.display = type.value === 'student' ? '' : 'none';
    }
    type.addEventListener('change', refresh);
    refresh();
})();
</script>
<p><a class="btn btn-secondary btn-small" href="users.php">Back to users</a></p>
</div>
<?php require 'partials/footer.php'; ?>
