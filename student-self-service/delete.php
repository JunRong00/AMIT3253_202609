<?php
require 'config.php';
require 'auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id  = (int)$_POST['id'];
    $uid = current_user_id();

    // Only a Pending request can be cancelled - once the Registry starts
    // processing it, it stays on record.
    $stmt = $conn->prepare("DELETE FROM document_requests WHERE id = ? AND user_id = ? AND status = 'Pending'");
    $stmt->bind_param('ii', $id, $uid);
    $stmt->execute();
    $stmt->close();
}

header('Location: index.php');
exit;
