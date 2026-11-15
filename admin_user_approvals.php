<?php
require_once 'db.php';
require_once 'notif_helper.php'; // Required for the Notification System

check_login();

// Siguraduhing Admin lamang ang nakaka-access
if (($_SESSION['role'] ?? '') !== 'Admin') {
    header('Location: login.php');
    exit;
}

// Admin details
$admin_id  = $_SESSION['user_id'] ?? 0;
$user_name = $_SESSION['full_name'] ?? 'Admin User';
$user_role = $_SESSION['role'] ?? 'Admin';
$user_profile_pic = '';

// SYNC: Kunin ang pinakabagong pangalan at profile picture direkta mula sa database
$stmt_sync = $conn->prepare("SELECT full_name, profile_pic FROM users WHERE id = ?");
$stmt_sync->bind_param("i", $admin_id);
$stmt_sync->execute();
$sync_res = $stmt_sync->get_result()->fetch_assoc();
$stmt_sync->close();

if ($sync_res) {
    $user_name = $sync_res['full_name'];
    $_SESSION['full_name'] = $user_name;
    $user_profile_pic = $sync_res['profile_pic'] ?? '';
}

// Kunin ang bilang ng unread notifications para sa Admin
$unread_notifs = get_unread_notif_count($conn, $admin_id);

// Kunin ang pangalan ng kasalukuyang file para sa dynamic active nav
$current_page = basename($_SERVER['PHP_SELF']);

$message = "";

// HANDLE ACCOUNT APPROVAL / REJECTION / DELETE / EDIT ACTIONS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $target_user_id = intval($_POST['user_id']);
    $action_type    = $_POST['action'];

    if ($target_user_id > 0) {
        // Kunin ang buong pangalan at username ng user
        $u_stmt = $conn->prepare("SELECT full_name, username, role FROM users WHERE id = ?");
        $u_stmt->bind_param("i", $target_user_id);
        $u_stmt->execute();
        $target_user = $u_stmt->get_result()->fetch_assoc();
        $u_stmt->close();

        if ($target_user || $action_type === 'edit') {
            $target_name  = $target_user['full_name'] ?? '';
            $target_email = $target_user['username'] ?? '';

            if ($action_type === 'approve') {
                $stmt = $conn->prepare("UPDATE users SET status = 'Approved' WHERE id = ?");
                $stmt->bind_param("i", $target_user_id);
                
                if ($stmt->execute()) {
                    create_notification(
                        $conn,
                        $target_user_id,
                        "Account Approved! 🎉",
                        "Hello {$target_name}! Your account registration has been approved by the Admin. You can now use the system."
                    );

                    if (!empty($target_email) && filter_var($target_email, FILTER_VALIDATE_EMAIL)) {
                        $to      = $target_email;
                        $subject = "Account Approved - Facility System";
                        $email_message = "Hello " . $target_name . ",\n\nGood news! Your account registration has been approved by the Admin. You can now log in and access the system.\n\nThank you!";
                        $headers = "From: no-reply@facilitysystem.com\r\n" .
                                   "Reply-to: support@facilitysystem.com\r\n" .
                                   "X-Mailer: PHP/" . phpversion();
                        @mail($to, $subject, $email_message, $headers);
                    }

                    $message = "<div class='alert alert-success'>Account for <b>" . htmlspecialchars($target_name) . "</b> has been approved successfully and notification sent!</div>";
                } else {
                    $message = "<div class='alert alert-danger'>Error approving user: " . htmlspecialchars($conn->error) . "</div>";
                }
                $stmt->close();

            } elseif ($action_type === 'reject') {
                $stmt = $conn->prepare("UPDATE users SET status = 'Rejected' WHERE id = ?");
                $stmt->bind_param("i", $target_user_id);
                
                if ($stmt->execute()) {
                    create_notification(
                        $conn,
                        $target_user_id,
                        "Account Registration Status ⚠️",
                        "We regret to inform you that your account registration request has been rejected by the Admin."
                    );

                    $message = "<div class='alert alert-danger'>Account for <b>" . htmlspecialchars($target_name) . "</b> has been rejected.</div>";
                } else {
                    $message = "<div class='alert alert-danger'>Error rejecting user: " . htmlspecialchars($conn->error) . "</div>";
                }
                $stmt->close();

            } elseif ($action_type === 'delete') {
                $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                $stmt->bind_param("i", $target_user_id);
                
                if ($stmt->execute()) {
                    $message = "<div class='alert alert-danger'>User account for <b>" . htmlspecialchars($target_name) . "</b> has been permanently deleted from the system.</div>";
                } else {
                    $message = "<div class='alert alert-danger'>Error deleting user: " . htmlspecialchars($conn->error) . "</div>";
                }
                $stmt->close();

            } elseif ($action_type === 'edit') {
                // EDIT USER DETAILS LOGIC
                $new_full_name = trim($_POST['full_name'] ?? '');
                $new_username  = trim($_POST['username'] ?? '');
                $new_role      = trim($_POST['role'] ?? '');
                $new_password  = trim($_POST['password'] ?? '');

                if (!empty($new_full_name) && !empty($new_username) && !empty($new_role)) {
                    if (!empty($new_password)) {
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("UPDATE users SET full_name = ?, username = ?, role = ?, password = ? WHERE id = ?");
                        $stmt->bind_param("ssssi", $new_full_name, $new_username, $new_role, $hashed_password, $target_user_id);
                    } else {
                        // Kung blangko ang password, panatilihin o i-update ang mga detalye nang hindi sinisira ang password
                        $stmt = $conn->prepare("UPDATE users SET full_name = ?, username = ?, role = ? WHERE id = ?");
                        $stmt->bind_param("sssi", $new_full_name, $new_username, $new_role, $target_user_id);
                    }

                    if ($stmt->execute()) {
                        $message = "<div class='alert alert-success'>User details for <b>" . htmlspecialchars($new_full_name) . "</b> have been updated successfully!</div>";
                    } else {
                        $message = "<div class='alert alert-danger'>Error updating user details: " . htmlspecialchars($conn->error) . "</div>";
                    }
                    $stmt->close();
                } else {
                    $message = "<div class='alert alert-danger'>Please fill in all required fields (Full Name, Username, Role).</div>";
                }
            }
        }
    }
}

// Fetch Pending Account Requests
$pending_query = "SELECT * FROM users WHERE status = 'Pending' ORDER BY id DESC";
$pending_users = $conn->query($pending_query);

// Fetch Approved Active Users
$approved_query = "SELECT * FROM users WHERE status = 'Approved' OR status = 'Active' ORDER BY id DESC";
$approved_users = $conn->query($approved_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Approvals - Admin Panel</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #f1f5f9; display: flex; height: 100vh; overflow: hidden; color: #334155; transition: background-color 0.3s, color 0.3s; }
        .menu-toggle { position: fixed; top: 22px; left: 22px; background: linear-gradient(135deg, #2557e0 0%, #1a44b8 100%); border: none; border-radius: 10px; width: 44px; height: 44px; cursor: pointer; z-index: 1100; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(37, 87, 224, 0.3); }
        .menu-toggle span { display: block; width: 22px; height: 2px; background-color: #ffffff; position: relative; }
        .menu-toggle span::before, .menu-toggle span::after { content: ''; position: absolute; width: 22px; height: 2px; background-color: #ffffff; left: 0; }
        .menu-toggle span::before { top: -7px; } .menu-toggle span::after { top: 7px; }
        .sidebar { width: 260px; background-color: #ffffff; border-right: 1px solid #e2e8f0; display: flex; flex-direction: column; justify-content: space-between; padding: 24px 18px; position: fixed; left: -280px; height: 100%; top: 0; transition: left 0.35s ease; z-index: 1000; }
        .sidebar.active { left: 0; }
        .logo-container { text-align: center; margin-bottom: 25px; margin-top: 35px; }
        .logo-placeholder a img { width: 60px; height: 60px; background-color: #2557e0; color: #fff; font-weight: bold; font-size: 24px; border-radius: 14px; display: flex; align-items: center; justify-content: center; margin: 0 auto 10px auto; object-fit: cover; }
        .logo-title { font-size: 11px; font-weight: 700; color: #1e293b; text-transform: uppercase; letter-spacing: 0.6px; }
        .nav-list { list-style: none; display: flex; flex-direction: column; gap: 10px; }
        .nav-item a { display: block; padding: 12px 18px; color: #2557e0 !important; border: 1.5px solid #2557e0; text-decoration: none; font-size: 13px; font-weight: 600; border-radius: 22px; text-align: center; transition: all 0.25s ease; }
        .nav-item a:hover, .nav-item a.active { background-color: #2557e0 !important; color: #ffffff !important; }
        .user-profile { display: flex; align-items: center; gap: 10px; padding: 14px 8px; border-top: 1px solid #f1f5f9; }
        .profile-link { display: flex; align-items: center; gap: 10px; text-decoration: none; color: inherit; flex: 1; overflow: hidden; padding: 6px; border-radius: 8px; }
        .user-avatar { width: 38px; height: 38px; border-radius: 50%; border: 1.5px solid #cbd5e1; display: flex; align-items: center; justify-content: center; object-fit: cover; }
        .user-info { font-size: 12px; flex: 1; overflow: hidden; }
        .user-info .name { font-weight: 700; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .user-info .role { font-size: 10px; color: #64748b; }
        .btn-logout { color: #ef4444; text-decoration: none; font-size: 12px; font-weight: 700; padding: 7px 12px; border-radius: 8px; border: 1px solid #fee2e2; background-color: #fef2f2; }
        .main-content { flex: 1; overflow-y: auto; background: #f8fafc; width: 100%; }
        .top-hero-banner { background: linear-gradient(135deg, #2557e0 0%, #1a44b8 100%); color: #ffffff; padding: 40px 50px 35px 85px; display: flex; justify-content: space-between; align-items: center; border-bottom-left-radius: 24px; border-bottom-right-radius: 24px; margin-bottom: 35px; }
        .hero-title h1 { font-size: 28px; font-weight: 700; margin-bottom: 6px; }
        .hero-title p { font-size: 13.5px; opacity: 0.9; }
        .header-actions { display: flex; align-items: center; gap: 12px; }
        .top-notif-btn, .dark-mode-btn { position: relative; background-color: rgba(255, 255, 255, 0.15); border: 1.5px solid rgba(255, 255, 255, 0.3); width: 48px; height: 48px; border-radius: 14px; display: flex; align-items: center; justify-content: center; text-decoration: none; color: #fff; cursor: pointer; }
        .top-notif-btn svg, .dark-mode-btn svg { width: 22px; height: 22px; fill: #ffffff; pointer-events: none; }
        .top-notif-badge { position: absolute; top: -6px; right: -6px; background-color: #ef4444; color: #ffffff; font-size: 10px; font-weight: 700; padding: 2.5px 6.5px; border-radius: 10px; }
        .user-approvals-container { padding: 0 40px 40px 40px; }
        .alert { padding: 12px 16px; margin-bottom: 24px; border-radius: 8px; font-size: 13px; font-weight: 500; }
        .alert-success { background-color: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-danger { background-color: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .card-panel { background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 28px; margin-bottom: 28px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); }
        .card-panel h3 { font-size: 16.5px; color: #0f172a; font-weight: 700; margin-bottom: 18px; }
        .data-table { width: 100%; border-collapse: separate; border-spacing: 0; text-align: left; }
        .data-table th { font-size: 12px; color: #475569; padding: 12px 16px; border-bottom: 1px solid #e2e8f0; background-color: #f8fafc; font-weight: 700; text-transform: uppercase; }
        .data-table td { font-size: 13px; padding: 14px 16px; border-bottom: 1px solid #f1f5f9; color: #334155; vertical-align: middle; }
        .badge { padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; display: inline-block; }
        .bg-pending { background-color: #fef3c7; color: #92400e; }
        .bg-approved { background-color: #d1fae5; color: #065f46; }
        .btn-approve, .btn-reject, .btn-edit, .btn-delete { padding: 6px 14px; border-radius: 6px; font-size: 12px; font-weight: 700; cursor: pointer; border: none; color: #fff; }
        .btn-approve { background-color: #10b981; } .btn-reject, .btn-delete { background-color: #ef4444; } .btn-edit { background-color: #2557e0; margin-right: 4px; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); justify-content: center; align-items: center; }
        .modal-content { background-color: #fff; padding: 28px; border-radius: 14px; width: 420px; box-shadow: 0 10px 25px rgba(0,0,0,0.15); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px; }
        .close-btn { cursor: pointer; font-size: 20px; font-weight: bold; color: #64748b; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 6px; }
        .form-group input, .form-group select { width: 100%; padding: 10px 14px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 13px; outline: none; }
        .modal-footer { text-align: right; margin-top: 20px; }
        .btn-cancel { background-color: #e2e8f0; color: #334155; border: none; padding: 9px 18px; border-radius: 8px; font-weight: 600; cursor: pointer; margin-right: 8px; }
        .btn-save { background-color: #10b981; color: #fff; border: none; padding: 9px 18px; border-radius: 8px; font-weight: 700; cursor: pointer; }
        body.dark-mode { background-color: #0f172a; color: #f1f5f9; }
        body.dark-mode .sidebar, body.dark-mode .card-panel, body.dark-mode .modal-content { background-color: #1e293b; border-color: #334155; color: #f1f5f9; }
        body.dark-mode .data-table th { background-color: #1e293b; color: #94a3b8; border-bottom-color: #334155; }
        body.dark-mode .data-table td { color: #f1f5f9; border-bottom-color: #334155; }
        body.dark-mode .form-group input, body.dark-mode .form-group select { background-color: #0f172a; border-color: #475569; color: #f1f5f9; }
    </style>
</head>
<body>

    <button class="menu-toggle" id="menuToggle" aria-label="Toggle Menu"><span></span></button>

    <aside class="sidebar" id="sidebar">
        <div>
            <div class="logo-container">
                <div class="logo-placeholder">
                    <a href="admin_dashboard.php"><img src="logo.webp" alt="logo" onerror="this.src='logo.png';"></a>
                </div>
                <div class="logo-title">Admin Panel<br>Facility System</div>
            </div>
            <ul class="nav-list">
                <li class="nav-item"><a href="admin_dashboard.php">Dashboard</a></li>
                <li class="nav-item"><a href="admin_reservations.php">Manage Approvals</a></li>
                <li class="nav-item"><a href="admin_facilities.php">Facilities Inventory</a></li>
                <li class="nav-item"><a href="admin_equipment.php">Equipment Inventory</a></li>
                <li class="nav-item"><a href="admin_calendar.php">Calendar</a></li>
                <li class="nav-item"><a href="admin_user_approvals.php" class="active">Users</a></li>
                <li class="nav-item"><a href="admin_reports.php">Reports List</a></li>
            </ul>
        </div>
        <div class="user-profile">
            <a href="profile.php" class="profile-link">
                <?php if (!empty($user_profile_pic) && file_exists($user_profile_pic)): ?>
                    <img src="<?= htmlspecialchars($user_profile_pic); ?>" alt="Avatar" class="user-avatar">
                <?php else: ?>
                    <div class="user-avatar">⚙️</div>
                <?php endif; ?>
                <div class="user-info">
                    <div class="name"><?= htmlspecialchars($user_name); ?></div>
                    <div class="role"><?= htmlspecialchars($user_role); ?></div>
                </div>
            </a>
            <a href="logout.php" class="btn-logout" onclick="return confirm('Logout as Admin?');">Logout</a>
        </div>
    </aside>

    <main class="main-content">
        <div class="top-hero-banner">
            <div class="hero-title">
                <h1>User Account Approvals</h1>
                <p>Review new account registration requests and manage existing members.</p>
            </div>
            <div class="header-actions">
                <button type="button" class="dark-mode-btn" id="darkModeToggle" title="Toggle Dark Mode">
                    <svg viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg"><path d="M256 512C114.6 512 0 397.4 0 256S114.6 0 256 0s256 114.6 256 256-114.6 256-256 256zm0-464v416c114.9 0 208-93.1 208-208S370.9 48 256 48zm0 144a64 64 0 1 0 0 128V192z"/></svg>
                </button>
                <a href="notifications.php" class="top-notif-btn" title="View Notifications">
                    <svg viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg"><path d="M256 32C150.1 32 64 118.1 64 224v61.5c0 32.7-13 64.1-36.1 87.2l-15.1 15.1C-1.8 403.2 8.3 432 32 432h448c23.7 0 33.8-28.8 19.2-43.2l-15.1-15.1C461 349.6 448 318.2 448 285.5V224c0-105.9-86.1-192-192-192zm64 416h-128c0 35.3 28.7 64 64 64s64-28.7 64-64z"/></svg>
                    <?php if ($unread_notifs > 0): ?><span class="top-notif-badge"><?= $unread_notifs; ?></span><?php endif; ?>
                </a>
            </div>
        </div>

        <div class="user-approvals-container">
            <?= $message; ?>

            <div class="card-panel">
                <h3>⏳ Pending Registration Requests</h3>
                <table class="data-table">
                    <thead>
                        <tr><th>Full Name</th><th>Username / Email</th><th>Password</th><th>Role</th><th>Status</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php if ($pending_users && $pending_users->num_rows > 0): ?>
                            <?php while ($user = $pending_users->fetch_assoc()): ?>
                                <tr>
                                    <td><b><?= htmlspecialchars($user['full_name'] ?? 'User'); ?></b></td>
                                    <td><?= htmlspecialchars($user['username'] ?? ''); ?></td>
                                    <td><span style="font-family: monospace;"><?= htmlspecialchars($user['password'] ?? 'N/A'); ?></span></td>
                                    <td><?= htmlspecialchars($user['role'] ?? 'Student'); ?></td>
                                    <td><span class="badge bg-pending">Pending</span></td>
                                    <td>
                                        <form method="POST" style="display:inline-block;"><input type="hidden" name="user_id" value="<?= $user['id']; ?>"><input type="hidden" name="action" value="approve"><button type="submit" class="btn-approve">Approve</button></form>
                                        <form method="POST" style="display:inline-block; margin-left:5px;"><input type="hidden" name="user_id" value="<?= $user['id']; ?>"><input type="hidden" name="action" value="reject"><button type="submit" class="btn-reject">Reject</button></form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6" style="text-align: center; color: #64748b; padding: 25px;">No pending applications.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="card-panel">
                <h3>👥 Registered & Approved Users</h3>
                <table class="data-table">
                    <thead>
                        <tr><th>Full Name</th><th>Username / Email</th><th>Password</th><th>Role</th><th>Status</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php if ($approved_users && $approved_users->num_rows > 0): ?>
                            <?php while ($a_user = $approved_users->fetch_assoc()): ?>
                                <?php 
                                    $fname = htmlspecialchars($a_user['full_name'] ?? 'User', ENT_QUOTES);
                                    $uname = htmlspecialchars($a_user['username'] ?? '', ENT_QUOTES);
                                    $upass = htmlspecialchars($a_user['password'] ?? 'N/A', ENT_QUOTES);
                                    $urole = htmlspecialchars($a_user['role'] ?? 'Student', ENT_QUOTES);
                                ?>
                                <tr>
                                    <td><b><?= $fname; ?></b></td>
                                    <td><?= $uname; ?></td>
                                    <td><span style="font-family: monospace;"><?= $upass; ?></span></td>
                                    <td><?= $urole; ?></td>
                                    <td><span class="badge bg-approved"><?= htmlspecialchars($a_user['status']); ?></span></td>
                                    <td>
                                        <button type="button" class="btn-edit" onclick="openEditModal(<?= $a_user['id']; ?>, '<?= $fname; ?>', '<?= $uname; ?>', '<?= $urole; ?>')">Edit</button>
                                        <form method="POST" style="display:inline-block;" onsubmit="return confirm('Delete this user?');"><input type="hidden" name="user_id" value="<?= $a_user['id']; ?>"><input type="hidden" name="action" value="delete"><button type="submit" class="btn-delete">Delete</button></form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6" style="text-align: center; color: #64748b; padding: 25px;">No approved users found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <div id="editUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>✏️ Edit User Details</h3>
                <span class="close-btn" onclick="closeEditModal()">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="user_id" id="edit_user_id">
                <div class="form-group"><label>Full Name</label><input type="text" id="edit_full_name" name="full_name" required></div>
                <div class="form-group"><label>Username / Email</label><input type="text" id="edit_username" name="username" required></div>
                <div class="form-group"><label>Role</label><select id="edit_role" name="role" required><option value="Admin">Admin</option><option value="Faculty">Faculty</option><option value="Student">Student</option></select></div>
                <div class="form-group"><label>New Password</label><input type="password" id="edit_password" name="password" placeholder="Leave blank to keep current password"></div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn-save">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        menuToggle.addEventListener('click', () => sidebar.classList.toggle('active'));

        const darkModeToggle = document.getElementById('darkModeToggle');
        if (localStorage.getItem('theme') === 'dark') document.body.classList.add('dark-mode');
        darkModeToggle.addEventListener('click', () => {
            document.body.classList.toggle('dark-mode');
            localStorage.setItem('theme', document.body.classList.contains('dark-mode') ? 'dark' : 'light');
        });

        function openEditModal(id, fullName, username, role) {
            document.getElementById('edit_user_id').value = id;
            document.getElementById('edit_full_name').value = fullName;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_role').value = role;
            document.getElementById('edit_password').value = '';
            document.getElementById('editUserModal').style.display = 'flex';
        }
        function closeEditModal() { document.getElementById('editUserModal').style.display = 'none'; }
    </script>
</body>
</html>