<?php
require '../config.php';
require '../auth.php';
require '../helpers.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = (int)$_POST['id'];
    $status = $_POST['status'] ?? '';
    $note   = trim($_POST['admin_note'] ?? '');
    $note   = $note === '' ? null : $note;

    if (in_array($status, ['Pending', 'Approved', 'Rejected'], true)) {
        $conn->begin_transaction();

        $stmt = $conn->prepare('SELECT * FROM replacement_classes WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $request = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $clash = false;
        if ($request && $status !== 'Rejected' && $request['status'] === 'Rejected') {
            // A Rejected request released its venue - re-check it's still
            // free before putting it back on hold or approving it.
            $venueId = (int)$request['venue_id'];
            $stmt = $conn->prepare('SELECT id FROM venues WHERE id = ? FOR UPDATE');
            $stmt->bind_param('i', $venueId);
            $stmt->execute();
            $stmt->close();
            $clash = in_array($venueId, busy_venue_ids($conn, $request['replacement_date'], (int)$request['time_slot_id'], $id), true);
        }

        if ($clash) {
            $_SESSION['flash_error'] = 'Cannot change this request back: its venue has since been taken for that date and slot.';
            $conn->rollback();
        } elseif ($request) {
            $stmt = $conn->prepare('UPDATE replacement_classes SET status = ?, admin_note = ? WHERE id = ?');
            $stmt->bind_param('ssi', $status, $note, $id);
            $stmt->execute();
            $stmt->close();
            $conn->commit();
        } else {
            $conn->rollback();
        }
    }
}

header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'replacements.php'));
exit;
