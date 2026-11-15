<?php
require_once 'db.php';

// Function para magpadala ng notification sa isang tiyak na user (English default)
function create_notification($conn, $user_id, $title, $message) {
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("iss", $user_id, $title, $message);
        $stmt->execute();
        $stmt->close();
    }
}

// Function para magpadala ng notification sa LAHAT ng Admin kapag may bagong request
function notify_all_admins($conn, $title, $message) {
    $admins = $conn->query("SELECT id FROM users WHERE LOWER(role) = 'admin'");
    if ($admins && $admins->num_rows > 0) {
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
        while ($admin = $admins->fetch_assoc()) {
            $stmt->bind_param("iss", $admin['id'], $title, $message);
            $stmt->execute();
        }
        $stmt->close();
    }
}

// Function para kuhanin ang bilang ng hindi pa nababasang notifications (Unread Count)
function get_unread_notif_count($conn, $user_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM notifications WHERE user_id = ? AND is_read = 0");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $res['total'] ?? 0;
    }
    return 0;
}
?>