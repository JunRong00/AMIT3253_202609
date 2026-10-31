<?php
require 'config.php';
require 'auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id  = (int)$_POST['id'];
    $uid = current_user_id();

    // Only an Open report can be withdrawn - once the Estate Office has
    // started on it, it stays on record.
    $stmt = $conn->prepare("DELETE FROM issues WHERE id = ? AND user_id = ? AND status = 'Open'");
    $stmt->bind_param('ii', $id, $uid);
    $stmt->execute();
    $stmt->close();
}

header('Location: index.php');
exit;
