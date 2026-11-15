<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db.php';

$message_status = "";
$default_subject = $_GET['subject'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name    = trim($_POST['full_name'] ?? '');
    $email_or_id  = trim($_POST['email_or_id'] ?? '');
    $issue_type   = trim($_POST['issue_type'] ?? '');
    $description  = trim($_POST['description'] ?? '');

    if (!empty($full_name) && !empty($email_or_id) && !empty($issue_type) && !empty($description)) {
        
        // 1. Find all Admin accounts in the users table
        $admin_query = $conn->query("SELECT id FROM users WHERE role = 'Admin'");

        if ($admin_query && $admin_query->num_rows > 0) {
            
            // Format Notification Title and Message for the Admin Dashboard
            $notif_title = "Account Support Request 📩";
            $notif_message = "New request from " . htmlspecialchars($full_name) . " (" . htmlspecialchars($email_or_id) . "):\n" .
                            "Issue: [" . htmlspecialchars($issue_type) . "]\n" .
                            "Details: " . htmlspecialchars($description);

            // 2. Insert notification into the notifications table for each Admin
            $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, is_read) VALUES (?, ?, ?, 0)");

            if ($stmt) {
                while ($admin = $admin_query->fetch_assoc()) {
                    $admin_id = $admin['id'];
                    $stmt->bind_param("iss", $admin_id, $notif_title, $notif_message);
                    $stmt->execute();
                }
                $stmt->close();

                $message_status = "<div class='alert alert-success'>Your message has been successfully sent to the Admin! They will reach out to your email shortly.</div>";
            } else {
                $message_status = "<div class='alert alert-danger'>Database error: Unable to send notification.</div>";
            }
        } else {
            $message_status = "<div class='alert alert-danger'>No Admin account found in the system.</div>";
        }
    } else {
        $message_status = "<div class='alert alert-danger'>Please fill in all required fields.</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Admin - Account Support</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        
        body { 
            background: linear-gradient(135deg, #f0f4ff 0%, #d9e4fc 100%); 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            min-height: 100vh; 
            padding: 20px; 
            overflow-x: hidden;
        }
        
        /* Entrance Animation matching Login */
        .auth-card {
            background-color: #ffffff;
            width: 100%;
            max-width: 450px;
            padding: 35px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(37, 87, 224, 0.12);
            border: 1px solid #dce4f5;
            animation: slideInUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            opacity: 0;
            transform: translateY(30px);
        }

        @keyframes slideInUp {
            0% {
                opacity: 0;
                transform: translateY(30px) scale(0.98);
            }
            100% {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .logo-placeholder a img {
            width: 60px;
            height: 60px;
            background-color: #2557e0;
            color: #fff;
            font-weight: bold;
            font-size: 24px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px auto;
        }

        .logo-title {
            text-align: center;
            font-size: 14px;
            font-weight: bold;
            color: #333;
            text-transform: uppercase;
            margin-bottom: 20px;
        }

        .form-group { margin-bottom: 14px; }
        .form-group label { display: block; font-size: 12px; font-weight: bold; margin-bottom: 5px; color: #444; }
        .form-group input, .form-group select, .form-group textarea { 
            width: 100%; 
            padding: 10px; 
            font-size: 13px; 
            border: 1px solid #ccc; 
            border-radius: 6px; 
            outline: none; 
            background-color: #fff;
            transition: border-color 0.2s, box-shadow 0.2s; 
        }
        .form-group textarea { resize: vertical; height: 100px; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { 
            border-color: #2557e0; 
            box-shadow: 0 0 0 3px rgba(37, 87, 224, 0.15); 
        }

        .btn-submit {
            width: 100%;
            background-color: #2557e0;
            color: white;
            border: none;
            padding: 11px;
            font-size: 14px;
            font-weight: bold;
            border-radius: 6px;
            cursor: pointer;
            margin-top: 10px;
            transition: background 0.2s, transform 0.1s;
        }
        .btn-submit:hover { background-color: #1a44b8; }
        .btn-submit:active { transform: scale(0.98); }

        .auth-footer { text-align: center; font-size: 12px; color: #666; margin-top: 18px; line-height: 1.6; }
        .auth-footer a { color: #2557e0; text-decoration: none; font-weight: bold; }
        .auth-footer a:hover { text-decoration: underline; }

        .alert { padding: 10px 14px; margin-bottom: 15px; border-radius: 6px; font-size: 13px; text-align: center; }
        .alert-danger { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    </style>
</head>
<body>

    <div class="auth-card">
        <div class="logo-placeholder">
            <a href="login.php"><img src="logo.webp" alt="logo"></a>
        </div>
        <div class="logo-title">Account Support / Contact Admin</div>

        <?= $message_status; ?>

        <form method="POST" action="contact_admin.php">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="full_name" required placeholder="Enter your full name">
            </div>

            <div class="form-group">
                <label>Student ID / Email / Username</label>
                <input type="text" name="email_or_id" required placeholder="Enter your ID or Email address">
            </div>

            <div class="form-group">
                <label>Type of Account Concern</label>
                <select name="issue_type" required>
                    <option value="" disabled selected>Select an issue...</option>
                    <option value="Forgot Password" <?= $default_subject === 'Forgot Password' ? 'selected' : '' ?>>Forgot Password</option>
                    <option value="Account Pending Approval">Account Pending Approval</option>
                    <option value="Rejected Account Appeal">Rejected Account Appeal</option>
                    <option value="Cannot Log In / Wrong Credentials">Cannot Log In / Wrong Credentials</option>
                    <option value="Other Account Issues">Other Concerns</option>
                </select>
            </div>

            <div class="form-group">
                <label>Problem Description</label>
                <textarea name="description" required placeholder="Please describe your account problem in detail..."></textarea>
            </div>

            <button type="submit" class="btn-submit">Submit to Admin</button>
        </form>

        <div class="auth-footer">
            Remembered your login? <a href="login.php">Back to Login</a>
        </div>
    </div>

</body>
</html>
