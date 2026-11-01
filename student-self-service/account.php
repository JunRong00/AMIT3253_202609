<?php
require 'config.php';
require 'auth.php';
require 'helpers.php';
require_login();

$uid = current_user_id();
$uploadDir = __DIR__ . '/uploads';
$profileError = '';
$profileSuccess = '';
$passwordError = '';
$passwordSuccess = '';

$stmt = $conn->prepare('SELECT name, email, id_number, faculty, programme, date_of_birth, image_url FROM users WHERE id = ?');
$stmt->bind_param('i', $uid);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'profile') {
    $name          = trim($_POST['name'] ?? '');
    $date_of_birth = trim($_POST['date_of_birth'] ?? '');

    [$newImageUrl, $uploadError] = handle_image_upload($_FILES['image'] ?? null, $uploadDir, 'profile');

    if ($name === '' || $date_of_birth === '') {
        $profileError = 'All fields are required.';
    } elseif ($uploadError) {
        $profileError = $uploadError;
    } else {
        $image_url = $newImageUrl ?? $user['image_url'];
        if ($newImageUrl) {
            delete_image_file($user['image_url'], $uploadDir);
        }

        $stmt = $conn->prepare('UPDATE users SET name = ?, date_of_birth = ?, image_url = ? WHERE id = ?');
        $stmt->bind_param('sssi', $name, $date_of_birth, $image_url, $uid);
        $stmt->execute();
        $stmt->close();
        $_SESSION['user_name'] = $name;
        $user = array_merge($user, compact('name', 'date_of_birth', 'image_url'));
        $profileSuccess = 'Profile updated.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'password') {
    $current = $_POST['current_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    $stmt = $conn->prepare('SELECT password_hash FROM users WHERE id = ?');
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!password_verify($current, $row['password_hash'])) {
        $passwordError = 'Current password is incorrect.';
    } elseif (strlen($new) < 6) {
        $passwordError = 'New password must be at least 6 characters.';
    } elseif ($new !== $confirm) {
        $passwordError = 'New passwords do not match.';
    } else {
        $newHash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $conn->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $stmt->bind_param('si', $newHash, $uid);
        $stmt->execute();
        $stmt->close();
        $passwordSuccess = 'Password changed.';
    }
}

$pageTitle = 'My Account';
require 'partials/header.php';
?>
<div class="page-header">
<h1>My Account</h1>
<p>Manage your profile, photo and password.</p>
</div>

<div class="form-card" style="margin-bottom:24px;">
<h2>Profile</h2>
<?php if ($profileError): ?><p class="alert alert-error"><?= htmlspecialchars($profileError) ?></p><?php endif; ?>
<?php if ($profileSuccess): ?><p class="alert alert-success"><?= htmlspecialchars($profileSuccess) ?></p><?php endif; ?>
<form method="post" enctype="multipart/form-data">
<input type="hidden" name="form" value="profile">
<label>Profile Photo
<img class="profile-photo card-thumb" style="width:96px;height:96px;" src="<?= htmlspecialchars(entity_image_url($user)) ?>" alt="<?= htmlspecialchars($user['name']) ?>">
</label>
<label>Upload New Photo <input type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp"></label>
<label>Full Name <span class="required-mark">*</span> <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" required></label>
<label>Email <input type="email" value="<?= htmlspecialchars($user['email']) ?>" disabled></label>
<label>Student ID <input type="text" value="<?= htmlspecialchars($user['id_number'] ?? '') ?>" disabled></label>
<label>Faculty <input type="text" value="<?= htmlspecialchars($user['faculty'] ?? '') ?>" disabled></label>
<label>Programme <input type="text" value="<?= htmlspecialchars($user['programme'] ?? '') ?>" disabled></label>
<p class="form-hint">Student ID, faculty and programme are kept by the Registry. Contact them if any of these are wrong.</p>
<label>Date of Birth <span class="required-mark">*</span> <input type="date" name="date_of_birth" value="<?= htmlspecialchars($user['date_of_birth'] ?? '') ?>" required></label>
<button type="submit">Save Changes</button>
</form>
</div>

<div class="form-card">
<h2>Change Password</h2>
<?php if ($passwordError): ?><p class="alert alert-error"><?= htmlspecialchars($passwordError) ?></p><?php endif; ?>
<?php if ($passwordSuccess): ?><p class="alert alert-success"><?= htmlspecialchars($passwordSuccess) ?></p><?php endif; ?>
<form method="post">
<input type="hidden" name="form" value="password">
<label>Current Password <span class="required-mark">*</span>
<div class="password-field">
<input type="password" name="current_password" required>
<button type="button" class="password-toggle" tabindex="-1" aria-label="Show password"></button>
</div>
</label>
<label>New Password <span class="required-mark">*</span>
<div class="password-field">
<input type="password" name="new_password" required>
<button type="button" class="password-toggle" tabindex="-1" aria-label="Show password"></button>
</div>
</label>
<label>Confirm New Password <span class="required-mark">*</span>
<div class="password-field">
<input type="password" name="confirm_password" required>
<button type="button" class="password-toggle" tabindex="-1" aria-label="Show password"></button>
</div>
</label>
<button type="submit">Change Password</button>
</form>
</div>
<?php require 'partials/footer.php'; ?>
