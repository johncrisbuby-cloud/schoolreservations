<?php
session_start();
require_once 'db.php';
require_once 'notif_helper.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['full_name'] ?? 'User';
$user_role = $_SESSION['role'] ?? 'Student';
$message   = "";

// Kunin ang kasalukuyang impormasyon ng naka-log in na user mula sa database
$stmt = $conn->prepare("SELECT full_name, username, profile_pic FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$current_user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Handle Profile Update Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $new_name     = trim($_POST['full_name']);
    $new_username = trim($_POST['username']);
    $old_password = $_POST['old_password'];
    $new_password = $_POST['new_password'];

    if (!empty($new_name) && !empty($new_username)) {
        // 1. Suriin kung ang username ay ginagamit na ng ibang account
        $chk_stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $chk_stmt->bind_param("si", $new_username, $user_id);
        $chk_stmt->execute();
        $chk_res = $chk_stmt->get_result();
        $chk_stmt->close();

        if ($chk_res->num_rows > 0) {
            $message = "<div class='alert alert-danger'>This username is already taken by another account.</div>";
        } else {
            $changes_made = [];
            if ($current_user['full_name'] !== $new_name) { $changes_made[] = "Full Name"; }
            if ($current_user['username'] !== $new_username) { $changes_made[] = "Username"; }

            // 2. Pamahalaan ang pag-upload ng Profile Picture para sa kasalukuyang user lamang
            $profile_pic_path = $current_user['profile_pic'];
            if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
                $file_tmp  = $_FILES['profile_pic']['tmp_name'];
                $file_name = $_FILES['profile_pic']['name'];
                $file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                
                $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
                if (in_array($file_ext, $allowed_exts)) {
                    $upload_dir = 'uploads/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }

                    // Gumamit ng natatanging pangalan gamit ang user_id para hindi magkatalo ang mga litrato
                    $new_file_name = 'user_' . $user_id . '_' . time() . '.' . $file_ext;
                    $destination = $upload_dir . $new_file_name;

                    if (move_uploaded_file($file_tmp, $destination)) {
                        if (!empty($current_user['profile_pic']) && file_exists($current_user['profile_pic'])) {
                            @unlink($current_user['profile_pic']);
                        }
                        $profile_pic_path = $destination;
                        $changes_made[] = "Profile Picture";
                    }
                } else {
                    $message = "<div class='alert alert-danger'>Invalid image format. Allowed: JPG, JPEG, PNG, WEBP.</div>";
                }
            }

            // 3. Password Update Logic
            if (!empty($new_password)) {
                $pass_stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
                $pass_stmt->bind_param("i", $user_id);
                $pass_stmt->execute();
                $user_pwd_data = $pass_stmt->get_result()->fetch_assoc();
                $pass_stmt->close();

                if (password_verify($old_password, $user_pwd_data['password']) || $old_password === $user_pwd_data['password']) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

                    $up_stmt = $conn->prepare("UPDATE users SET full_name = ?, username = ?, password = ?, profile_pic = ? WHERE id = ?");
                    $up_stmt->bind_param("ssssi", $new_name, $new_username, $hashed_password, $profile_pic_path, $user_id);
                    if ($up_stmt->execute()) {
                        // UPDATE SESSION Kaya magbabago na rin sa buong sistema
                        $_SESSION['full_name'] = $new_name;
                        $_SESSION['username']  = $new_username;
                        $_SESSION['profile_pic'] = $profile_pic_path;

                        $changes_made[] = "Password";
                        $message = "<div class='alert alert-success'>Profile and password updated successfully!</div>";
                    }
                    $up_stmt->close();
                } else {
                    $message = "<div class='alert alert-danger'>Your current password is incorrect!</div>";
                }
            } else {
                // I-update ang pangalan, username, at profile picture nang walang password change
                $up_stmt = $conn->prepare("UPDATE users SET full_name = ?, username = ?, profile_pic = ? WHERE id = ?");
                $up_stmt->bind_param("sssi", $new_name, $new_username, $profile_pic_path, $user_id);
                if ($up_stmt->execute()) {
                    // UPDATE SESSION Kaya magbabago na rin sa buong sistema
                    $_SESSION['full_name'] = $new_name;
                    $_SESSION['username']  = $new_username;
                    $_SESSION['profile_pic'] = $profile_pic_path;

                    $message = "<div class='alert alert-success'>Profile details updated successfully!</div>";
                }
                $up_stmt->close();
            }

            // Magpadala ng notification kung sakaling may binagong detalye
            if (!empty($changes_made) && strpos($message, 'success') !== false) {
                $details_str = implode(", ", $changes_made);
                $notif_title = "User Profile Updated 📝";
                $notif_msg = "User " . htmlspecialchars($new_name) . " updated their account details: " . $details_str . ".";

                $admin_query = $conn->query("SELECT id FROM users WHERE role = 'Admin'");
                if ($admin_query) {
                    while ($admin_row = $admin_query->fetch_assoc()) {
                        create_notification($conn, $admin_row['id'], $notif_title, $notif_msg);
                    }
                }
                create_notification($conn, $user_id, "Profile Settings Changed ⚙️", "You successfully updated your account settings (" . $details_str . ").");
            }

            // I-refresh ang data ng kasalukuyang user
            $stmt = $conn->prepare("SELECT full_name, username, profile_pic FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $current_user = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }
    } else {
        $message = "<div class='alert alert-danger'>Full Name and Username cannot be blank.</div>";
    }
}

// Pagtukoy kung saang dashboard ibabalik ang user batay sa kanilang role
$back_dashboard = "student_dashboard.php";
if (strtolower($user_role) === 'admin') {
    $back_dashboard = "admin_dashboard.php";
} elseif (strtolower($user_role) === 'faculty') {
    $back_dashboard = "faculty_dashboard.php";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - Facility System</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { 
            background-color: #f1f5f9; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            min-height: 100vh; 
            padding: 20px; 
            color: #334155; 
            transition: background-color 0.3s, color 0.3s; 
        }

        .profile-card { 
            background: #ffffff; 
            width: 100%; 
            max-width: 480px; 
            padding: 30px; 
            border-radius: 12px; 
            border: 1px solid #e2e8f0; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.03); 
            transition: transform 0.25s ease, box-shadow 0.25s ease, background-color 0.3s, border-color 0.3s; 
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
        }

        .profile-card h3 { font-size: 18px; color: #0f172a; font-weight: 700; transition: color 0.3s; }
        .profile-card p { font-size: 13px; color: #64748b; margin-bottom: 20px; transition: color 0.3s; }

        .dark-mode-btn {
            background: transparent;
            border: 1.5px solid #cbd5e1;
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .dark-mode-btn:hover { background-color: #f1f5f9; border-color: #94a3b8; }
        .dark-mode-btn svg { width: 18px; height: 18px; fill: #334155; transition: transform 0.3s ease, fill 0.3s ease; }
        .dark-mode-btn:hover svg { transform: rotate(180deg); }

        .avatar-upload-container {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 20px;
            background: #f8fafc;
            padding: 12px;
            border-radius: 10px;
            border: 1.5px solid #e2e8f0;
            transition: background-color 0.3s, border-color 0.3s;
        }
        .avatar-preview {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #2557e0;
            background: #fff;
        }
        .avatar-upload-info label {
            display: inline-block;
            background: #2557e0;
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .avatar-upload-info label:hover { background: #1a44b8; }
        .avatar-upload-info input[type="file"] { display: none; }
        .avatar-upload-info span { display: block; font-size: 11px; color: #64748b; margin-top: 4px; }

        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 6px; transition: color 0.3s; }
        .form-group input[type="text"], .form-group input[type="password"] { width: 100%; padding: 11px 14px; border: 1.5px solid #cbd5e1; border-radius: 10px; font-size: 13px; background-color: #f8fafc; color: #1e293b; transition: all 0.2s ease, background-color 0.3s, color 0.3s, border-color 0.3s; }
        .form-group input:focus { border-color: #2557e0; background-color: #ffffff; outline: none; box-shadow: 0 0 0 4px rgba(37, 87, 224, 0.12); }

        .divider { border-top: 1.5px dashed #cbd5e1; margin: 20px 0; transition: border-color 0.3s; }
        .sub-title { font-size: 13px; font-weight: 700; color: #2557e0; margin-bottom: 10px; }

        .btn-submit { width: 100%; background: linear-gradient(135deg, #2557e0 0%, #1a44b8 100%); color: #fff; border: none; padding: 12px; border-radius: 10px; font-weight: 700; cursor: pointer; font-size: 13.5px; box-shadow: 0 4px 12px rgba(37, 87, 224, 0.25); transition: all 0.25s ease; }
        .btn-submit:hover { background: linear-gradient(135deg, #1d4ed8, #1e40af); box-shadow: 0 6px 18px rgba(37, 87, 224, 0.35); transform: translateY(-1px); }

        .btn-back { display: block; text-align: center; margin-top: 16px; color: #64748b; text-decoration: none; font-size: 13px; font-weight: 600; transition: color 0.2s; }
        .btn-back:hover { color: #2557e0; text-decoration: underline; }

        .alert { padding: 12px 16px; margin-bottom: 20px; border-radius: 10px; font-size: 13px; font-weight: 500; }
        .alert-success { background-color: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-danger { background-color: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

        /* DARK MODE STYLES */
        body.dark-mode { background-color: #0f172a; color: #f1f5f9; }
        body.dark-mode .profile-card { background-color: #1e293b; border-color: #334155; }
        body.dark-mode .profile-card h3 { color: #f1f5f9; }
        body.dark-mode .profile-card p, body.dark-mode .btn-back { color: #94a3b8; }
        body.dark-mode .dark-mode-btn { border-color: #475569; background-color: #0f172a; }
        body.dark-mode .dark-mode-btn:hover { background-color: #334155; }
        body.dark-mode .dark-mode-btn svg { fill: #f1f5f9; }
        body.dark-mode .avatar-upload-container { background: #0f172a; border-color: #334155; }
        body.dark-mode .avatar-upload-info span { color: #94a3b8; }
        body.dark-mode .form-group label { color: #cbd5e1; }
        body.dark-mode .form-group input[type="text"], body.dark-mode .form-group input[type="password"] { background-color: #0f172a; border-color: #334155; color: #f1f5f9; }
        body.dark-mode .divider { border-top-color: #334155; }
    </style>
</head>
<body>

    <div class="profile-card">
        <div class="card-header">
            <h3>Account Information ⚙️</h3>
            <button type="button" class="dark-mode-btn" id="darkModeToggle" title="Toggle Dark Mode">
                <svg viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg">
                    <path d="M256 512C114.6 512 0 397.4 0 256S114.6 0 256 0s256 114.6 256 256-114.6 256-256 256zm0-464v416c114.9 0 208-93.1 208-208S370.9 48 256 48zm0 144a64 64 0 1 0 0 128V192z"/>
                </svg>
            </button>
        </div>
        <p>Update your personal information or change your account password.</p>

        <?= $message; ?>

        <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="update_profile" value="1">

            <div class="avatar-upload-container">
                <?php 
                    $pic_src = (!empty($current_user['profile_pic']) && file_exists($current_user['profile_pic'])) 
                        ? $current_user['profile_pic'] 
                        : 'https://cdn.jsdelivr.net/npm/@tabler/icons@latest/icons/user.svg';
                ?>
                <img src="<?= $pic_src; ?>" alt="Avatar" class="avatar-preview" id="avatarPreview">
                <div class="avatar-upload-info">
                    <label for="profile_pic">Change Photo</label>
                    <input type="file" name="profile_pic" id="profile_pic" accept="image/*" onchange="previewImage(event)">
                    <span>JPG, PNG or WEBP (Max 2MB)</span>
                </div>
            </div>

            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="full_name" value="<?= htmlspecialchars($current_user['full_name'] ?? ''); ?>" required>
            </div>

            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" value="<?= htmlspecialchars($current_user['username'] ?? ''); ?>" required>
            </div>

            <div class="divider"></div>
            <div class="sub-title">Change Password (Optional)</div>

            <div class="form-group">
                <label>Current Password</label>
                <input type="password" name="old_password" placeholder="Enter current password if changing">
            </div>

            <div class="form-group">
                <label>New Password</label>
                <input type="password" name="new_password" placeholder="Leave blank to keep current password">
            </div>

            <button type="submit" class="btn-submit">Save Changes</button>
            <a href="<?= $back_dashboard; ?>" class="btn-back">← Back to Dashboard</a>
        </form>
    </div>

    <script>
        function previewImage(event) {
            const reader = new FileReader();
            reader.onload = function(){
                const output = document.getElementById('avatarPreview');
                output.src = reader.result;
            };
            reader.readAsDataURL(event.target.files[0]);
        }

        const darkModeToggle = document.getElementById('darkModeToggle');
        const currentTheme = localStorage.getItem('theme');

        if (currentTheme === 'dark') {
            document.body.classList.add('dark-mode');
        }

        darkModeToggle.addEventListener('click', function() {
            document.body.classList.toggle('dark-mode');
            let theme = 'light';
            if (document.body.classList.contains('dark-mode')) {
                theme = 'dark';
            }
            localStorage.setItem('theme', theme);
        });
    </script>
</body>
</html>