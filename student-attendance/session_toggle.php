<?php
require 'config.php';
require 'auth.php';
require 'helpers.php';
require_lecturer();

$id = (int)($_POST['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $session = lecturer_session($conn, current_user_id(), $id);

    if ($session && session_is_open($session)) {
        // Close early.
        $stmt = $conn->prepare('UPDATE class_sessions SET open_until = NULL WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    } elseif ($session && $session['session_date'] !== date('Y-m-d')) {
        $_SESSION['flash_error'] = 'Check-in can only be opened on the day of the class.';
    } elseif ($session) {
        // Open until the class ends. If the class has already ended, this is
        // a reopen for latecomers, so allow a short REOPEN_MINUTES window.
        $end = session_end_timestamp($session);
        $openUntil = date('Y-m-d H:i:s', $end > time() ? $end : time() + REOPEN_MINUTES * 60);

        // Fresh code every time check-in is opened, so a code shared in a
        // previous class can't be reused. Retry on the (unlikely) chance it
        // matches another session that's open right now.
        do {
            $code = generate_check_in_code();
            $stmt = $conn->prepare('SELECT 1 FROM class_sessions WHERE check_in_code = ? AND open_until > NOW()');
            $stmt->bind_param('s', $code);
            $stmt->execute();
            $taken = (bool)$stmt->get_result()->fetch_assoc();
            $stmt->close();
        } while ($taken);

        $stmt = $conn->prepare('UPDATE class_sessions SET open_until = ?, check_in_code = ? WHERE id = ?');
        $stmt->bind_param('ssi', $openUntil, $code, $id);
        $stmt->execute();
        $stmt->close();
    }
}

header('Location: session_view.php?id=' . $id);
exit;
