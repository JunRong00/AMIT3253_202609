<?php
require '../config.php';
require '../auth.php';
require_admin();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';
    $account_type = $_POST['account_type'] ?? 'admin';

    if ($name === '' || $email === '' || $password === '') {
        $error = 'Name, email and password are required.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        $stmt = $conn->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($exists) {
            $error = 'An account with this email already exists.';
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $is_admin = $account_type === 'lecturer' ? 0 : 1;
            $role = $account_type === 'lecturer' ? 'lecturer' : 'student';
            $stmt = $conn->prepare('INSERT INTO users (name, email, password_hash, is_admin, role) VALUES (?, ?, ?, ?, ?)');
            $stmt->bind_param('sssis', $name, $email, $password_hash, $is_admin, $role);
            $stmt->execute();
            $stmt->close();
            header('Location: users.php');
            exit;
        }
    }
}

$pageTitle = 'Add Admin / Lecturer';
require 'partials/header.php';
?>
<div class="form-card">
<h1>Add Admin / Lecturer</h1>
<p>Creates a new admin or lecturer account directly. Students register themselves
through the public Register page.</p>
<?php if ($error): ?><p class="alert alert-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form method="post">
<label>Account Type
<select name="account_type">
<option value="lecturer" <?= ($_POST['account_type'] ?? '') === 'lecturer' ? 'selected' : '' ?>>Lecturer</option>
<option value="admin" <?= ($_POST['account_type'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
</select>
</label>
<label>Full Name <input type="text" name="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required></label>
<label>Email <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required></label>
<label>Password
<div class="password-field">
<input type="password" name="password" required>
<button type="button" class="password-toggle" tabindex="-1" aria-label="Show password"></button>
</div>
</label>
<label>Confirm Password
<div class="password-field">
<input type="password" name="confirm_password" required>
<button type="button" class="password-toggle" tabindex="-1" aria-label="Show password"></button>
</div>
</label>
<button type="submit">Create Account</button>
</form>
<p><a class="btn btn-secondary btn-small" href="users.php">Back to users</a></p>
</div>
<?php require 'partials/footer.php'; ?>
