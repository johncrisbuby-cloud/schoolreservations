<?php
session_start();
require_once 'db.php';
require_once 'notif_helper.php'; // Required for the Notification System

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'Admin') {
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

// Get unread notification count for the Admin
$unread_notifs = get_unread_notif_count($conn, $admin_id);

// Kunin ang pangalan ng kasalukuyang file para sa active nav state
$current_page = basename($_SERVER['PHP_SELF']);

$message = "";

// Update Status Process
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $report_id = intval($_POST['report_id']);
    $new_status = $_POST['status'];

    // 1. Fetch the user_id and target_name of the report before updating to notify the owner
    $owner_stmt = $conn->prepare("SELECT user_id, target_name FROM reports WHERE id = ? LIMIT 1");
    $owner_stmt->bind_param("i", $report_id);
    $owner_stmt->execute();
    $owner_res = $owner_stmt->get_result()->fetch_assoc();
    $owner_stmt->close();

    // 2. Update the report status in the database
    $stmt_update = $conn->prepare("UPDATE reports SET status = ? WHERE id = ?");
    $stmt_update->bind_param("si", $new_status, $report_id);

    if ($stmt_update->execute()) {
        $message = "<div class='alert ok'>Report #$report_id status updated to <b>$new_status</b>.</div>";

        // 3. Send a notification to the report owner (Student or Faculty)
        if ($owner_res && !empty($owner_res['user_id'])) {
            $report_owner_id = $owner_res['user_id'];
            $item_name = $owner_res['target_name'];
            
            $notif_title = "Report Status Updated 📋";
            $notif_msg = "Admin has updated the status of your report for ($item_name) to: $new_status.";
            
            create_notification($conn, $report_owner_id, $notif_title, $notif_msg);
        }

    } else {
        $message = "<div class='alert bad'>Failed to update report status.</div>";
    }
    $stmt_update->close();
}

// Delete Report Process
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_report'])) {
    $report_id = intval($_POST['report_id']);
    $stmt_del = $conn->prepare("DELETE FROM reports WHERE id = ?");
    $stmt_del->bind_param("i", $report_id);

    if ($stmt_del->execute()) {
        $message = "<div class='alert ok'>Report #$report_id has been deleted successfully.</div>";
    } else {
        $message = "<div class='alert bad'>Failed to delete report.</div>";
    }
    $stmt_del->close();
}

// Query including u.username to display alongside the full name
$query = "SELECT r.*, u.full_name, u.username 
          FROM reports r 
          LEFT JOIN users u ON r.user_id = u.id 
          ORDER BY r.created_at DESC";
$all_reports = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Reports - Admin Portal</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f1f5f9;
            display: flex;
            height: 100vh;
            overflow: hidden;
            color: #334155;
            transition: background-color 0.3s, color 0.3s;
        }

        /* Hamburger 3-Line Menu Button */
        .menu-toggle {
            position: fixed;
            top: 22px;
            left: 22px;
            background: linear-gradient(135deg, #2557e0 0%, #1a44b8 100%);
            border: none;
            border-radius: 10px;
            width: 44px;
            height: 44px;
            cursor: pointer;
            z-index: 1100;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(37, 87, 224, 0.3);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .menu-toggle:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 16px rgba(37, 87, 224, 0.4);
        }

        .menu-toggle span {
            display: block;
            width: 22px;
            height: 2px;
            background-color: #ffffff;
            position: relative;
        }

        .menu-toggle span::before,
        .menu-toggle span::after {
            content: '';
            position: absolute;
            width: 22px;
            height: 2px;
            background-color: #ffffff;
            left: 0;
        }

        .menu-toggle span::before { top: -7px; }
        .menu-toggle span::after { top: 7px; }

        /* Sidebar Styling (Collapsible) */
        .sidebar { 
            width: 260px; 
            background-color: #ffffff; 
            border-right: 1px solid #e2e8f0; 
            display: flex; 
            flex-direction: column; 
            justify-content: space-between; 
            padding: 24px 18px; 
            box-shadow: 5px 0 25px rgba(0,0,0,0.06);
            position: fixed;
            left: -280px;
            height: 100%;
            top: 0;
            transition: left 0.35s cubic-bezier(0.16, 1, 0.3, 1), background-color 0.3s, border-color 0.3s;
            z-index: 1000;
        }

        .sidebar.active {
            left: 0;
        }

        .logo-container { text-align: center; margin-bottom: 25px; margin-top: 35px; }
        .logo-placeholder a img { 
            width: 60px; height: 60px; 
            background-color: #2557e0; color: #fff; 
            font-weight: bold; font-size: 24px; 
            border-radius: 14px; display: flex; 
            align-items: center; justify-content: center; 
            margin: 0 auto 10px auto; 
            box-shadow: 0 4px 12px rgba(37, 87, 224, 0.25);
            object-fit: cover;
        }
        .logo-title { font-size: 11px; font-weight: 700; color: #1e293b; text-transform: uppercase; letter-spacing: 0.6px; transition: color 0.3s; }

        .nav-list { list-style: none; display: flex; flex-direction: column; gap: 10px; }
        
        .nav-item a { 
            display: block; padding: 12px 18px; 
            background-color: transparent; color: #2557e0 !important; 
            border: 1.5px solid #2557e0; text-decoration: none; 
            font-size: 13px; font-weight: 600; border-radius: 22px; 
            text-align: center; transition: all 0.25s ease; 
            position: relative;
        }
        
        .nav-item a:hover, 
        .nav-item a:focus, 
        .nav-item a:active,
        .nav-item a.active { 
            background-color: #2557e0 !important; 
            color: #ffffff !important; 
            box-shadow: 0 4px 15px rgba(37, 87, 224, 0.3);
            transform: translateY(-1px);
        }

        .notif-badge {
            background-color: #ef4444;
            color: #ffffff;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 10px;
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            box-shadow: 0 2px 5px rgba(239, 68, 68, 0.3);
        }

        /* User Profile & Clickable Link Styling */
        .user-profile { display: flex; align-items: center; gap: 10px; padding: 14px 8px; border-top: 1px solid #f1f5f9; transition: border-color 0.3s; }
        .profile-link { display: flex; align-items: center; gap: 10px; text-decoration: none; color: inherit; flex: 1; overflow: hidden; padding: 6px; border-radius: 8px; transition: background 0.2s; min-width: 0; }
        .profile-link:hover { background-color: #f1f5f9; }
        .user-avatar { width: 38px; height: 38px; border-radius: 50%; border: 1.5px solid #cbd5e1; display: flex; align-items: center; justify-content: center; font-size: 16px; background: #fff; flex-shrink: 0; box-shadow: 0 2px 4px rgba(0,0,0,0.02); object-fit: cover; }
        .user-info { font-size: 12px; flex: 1; overflow: hidden; min-width: 0; }
        .user-info .name { font-weight: 700; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; transition: color 0.3s; }
        .user-info .role { font-size: 10px; color: #64748b; font-weight: 500; }

        .btn-logout { color: #ef4444; text-decoration: none; font-size: 12px; font-weight: 700; padding: 7px 12px; border-radius: 8px; border: 1px solid #fee2e2; background-color: #fef2f2; transition: all 0.2s; white-space: nowrap; flex-shrink: 0; }
        .btn-logout:hover { background-color: #ef4444; color: #ffffff; border-color: #ef4444; box-shadow: 0 2px 8px rgba(239, 68, 68, 0.25); }

        /* Main Section */
        .main-content { flex: 1; overflow-y: auto; background: #f8fafc; width: 100%; transition: background 0.3s; }

        /* Modern Blue & White Hero Header Banner */
        .top-hero-banner {
            background: linear-gradient(135deg, #2557e0 0%, #1a44b8 100%);
            color: #ffffff;
            padding: 40px 50px 35px 85px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 6px 25px rgba(37, 87, 224, 0.18);
            border-bottom-left-radius: 24px;
            border-bottom-right-radius: 24px;
            margin-bottom: 35px;
            position: relative;
            z-index: 10;
        }

        .hero-title h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 6px;
            letter-spacing: 0.3px;
        }

        .hero-title p {
            font-size: 13.5px;
            opacity: 0.9;
            font-weight: 400;
        }

        /* Header Right Actions Container (Dark Mode & Notif Buttons) */
        .header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            z-index: 20;
        }

        .top-notif-btn, .dark-mode-btn {
            position: relative;
            background-color: rgba(255, 255, 255, 0.15);
            border: 1.5px solid rgba(255, 255, 255, 0.3);
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            backdrop-filter: blur(6px);
            transition: all 0.25s ease;
            flex-shrink: 0;
            cursor: pointer;
            color: #fff;
        }

        .top-notif-btn:hover, .dark-mode-btn:hover {
            background-color: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .dark-mode-btn svg, .top-notif-btn svg {
            width: 24px;
            height: 24px;
            fill: #ffffff;
            transition: transform 0.3s ease;
            pointer-events: none;
        }
        
        .dark-mode-btn:hover svg {
            transform: rotate(180deg);
        }

        .top-notif-badge {
            position: absolute;
            top: -6px;
            right: -6px;
            background-color: #ef4444;
            color: #ffffff;
            font-size: 10px;
            font-weight: 700;
            padding: 2.5px 6.5px;
            border-radius: 10px;
            box-shadow: 0 2px 6px rgba(239, 68, 68, 0.4);
            pointer-events: none;
        }

        .reports-container {
            padding: 0 40px 40px 40px;
        }

        .card { 
            background: #ffffff; 
            padding: 28px; 
            border-radius: 14px; 
            border: 1px solid #e2e8f0; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.03); 
            transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.3s, border-color 0.3s;
        }
        
        .card:hover {
            box-shadow: 0 8px 20px rgba(0,0,0,0.06);
        }

        .card h2 { font-size: 16.5px; color: #0f172a; font-weight: 700; margin-bottom: 4px; transition: color 0.3s; }
        
        .data-table { width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 15px; text-align: left; }
        .data-table th, .data-table td { border-bottom: 1px solid #f1f5f9; padding: 14px 16px; font-size: 13px; color: #334155; transition: border-color 0.3s, color 0.3s; }
        .data-table th { background-color: #f8fafc; color: #475569; font-weight: 700; border-bottom: 1px solid #e2e8f0; text-transform: uppercase; letter-spacing: 0.5px; transition: background 0.3s, color 0.3s, border-color 0.3s; }
        .data-table tr:last-child td { border-bottom: none; }

        .badge { padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; display: inline-block; }
        .b-pending { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
        .b-in-review { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
        .b-resolved { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }

        .alert { padding: 12px 16px; border-radius: 8px; font-size: 13px; font-weight: 500; margin-bottom: 24px; }
        .alert.ok { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert.bad { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

        select.status-select { padding: 6px 10px; border-radius: 6px; border: 1.5px solid #cbd5e1; font-size: 12px; outline: none; background: #fff; color: #1e293b; transition: border-color 0.2s, background-color 0.3s, color 0.3s; }
        select.status-select:focus { border-color: #2557e0; box-shadow: 0 0 0 2px rgba(37, 87, 224, 0.15); }

        .btn-update { background: #2557e0; color: #fff; border: none; padding: 6px 14px; border-radius: 6px; font-size: 12px; cursor: pointer; font-weight: 700; transition: all 0.2s ease; box-shadow: 0 2px 6px rgba(37, 87, 224, 0.2); }
        .btn-update:hover { background: #1d4ed8; box-shadow: 0 4px 10px rgba(37, 87, 224, 0.3); transform: translateY(-1px); }

        .btn-delete { background: #ef4444; color: #fff; border: none; padding: 6px 12px; border-radius: 6px; font-size: 12px; cursor: pointer; font-weight: 700; transition: all 0.2s ease; box-shadow: 0 2px 6px rgba(239, 68, 68, 0.2); text-decoration: none; display: inline-flex; align-items: center; }
        .btn-delete:hover { background: #dc2626; box-shadow: 0 4px 10px rgba(239, 68, 68, 0.3); transform: translateY(-1px); }

        /* DARK MODE STYLES */
        body.dark-mode {
            background-color: #0f172a;
            color: #f1f5f9;
        }
        body.dark-mode .sidebar {
            background-color: #1e293b;
            border-right-color: #334155;
        }
        body.dark-mode .logo-title {
            color: #f1f5f9;
        }
        body.dark-mode .user-profile {
            border-top-color: #334155;
        }
        body.dark-mode .profile-link:hover {
            background-color: #334155;
        }
        body.dark-mode .user-avatar {
            background: #0f172a;
            border-color: #475569;
            color: #f1f5f9;
        }
        body.dark-mode .user-info .name {
            color: #f1f5f9;
        }
        body.dark-mode .user-info .role {
            color: #94a3b8;
        }
        body.dark-mode .main-content {
            background: #0f172a;
        }
        body.dark-mode .card {
            background-color: #1e293b;
            border-color: #334155;
        }
        body.dark-mode .card h2 {
            color: #f1f5f9;
        }
        body.dark-mode .data-table th {
            background-color: #1e293b;
            color: #94a3b8;
            border-bottom-color: #334155;
        }
        body.dark-mode .data-table td {
            color: #f1f5f9;
            border-bottom-color: #334155;
        }
        body.dark-mode select.status-select {
            background-color: #0f172a;
            border-color: #475569;
            color: #f1f5f9;
        }

        @media (max-width: 768px) {
            .top-hero-banner {
                padding: 30px 20px 30px 75px;
            }
            .reports-container {
                padding: 0 20px 20px 20px;
            }
        }
    </style>
</head>
<body>

    <!-- Hamburger 3-Line Menu Button -->
    <button class="menu-toggle" id="menuToggle" aria-label="Toggle Menu">
        <span></span>
    </button>

    <aside class="sidebar" id="sidebar">
        <div>
            <div class="logo-container">
                <div class="logo-placeholder">
                    <a href="admin_dashboard.php"><img src="logo.webp" alt="logo" onerror="this.src='logo.png';"></a>
                </div>
                <div class="logo-title">Admin Panel<br>Facility System</div>
            </div>
            <ul class="nav-list">
                <li class="nav-item">
                    <a href="admin_dashboard.php" class="<?= ($current_page == 'admin_dashboard.php') ? 'active' : ''; ?>">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a href="admin_reservations.php" class="<?= ($current_page == 'admin_reservations.php') ? 'active' : ''; ?>">Manage Approvals</a>
                </li>
                <li class="nav-item">
                    <a href="admin_facilities.php" class="<?= ($current_page == 'admin_facilities.php') ? 'active' : ''; ?>">Facilities Inventory</a>
                </li>
                <li class="nav-item">
                    <a href="admin_equipment.php" class="<?= ($current_page == 'admin_equipment.php') ? 'active' : ''; ?>">Equipment Inventory</a>
                </li>
                <li class="nav-item">
                    <a href="admin_calendar.php" class="<?= ($current_page == 'admin_calendar.php') ? 'active' : ''; ?>">Calendar</a>
                </li>
                <li class="nav-item">
                    <a href="admin_user_approvals.php" class="<?= ($current_page == 'admin_user_approvals.php') ? 'active' : ''; ?>">Users</a>
                </li>
              
              

                <li class="nav-item">
                    <a href="admin_reports.php" class="<?= ($current_page == 'admin_reports.php') ? 'active' : ''; ?>">Reports List</a>
                </li>
            </ul>
        </div>

        <!-- User Profile Section with Clickable Profile Link & Real-time Profile Picture Support -->
        <div class="user-profile">
            <a href="profile.php" class="profile-link" title="Edit Profile">
                <?php if (!empty($user_profile_pic) && file_exists($user_profile_pic)): ?>
                    <img src="<?= htmlspecialchars($user_profile_pic); ?>" alt="Avatar" class="user-avatar">
                <?php else: ?>
                    <div class="user-avatar">⚙️</div>
                <?php endif; ?>
                <div class="user-info">
                    <div class="name" title="<?= htmlspecialchars($user_name); ?>"><?= htmlspecialchars($user_name); ?></div>
                    <div class="role"><?= htmlspecialchars($user_role); ?></div>
                </div>
            </a>
            <a href="logout.php" class="btn-logout" onclick="return confirm('Logout as Admin?');">Logout</a>
        </div>
    </aside>

    <main class="main-content">
        <!-- Blue & White Top Hero Banner with Dark Mode & Updated Notif Icon -->
        <div class="top-hero-banner">
            <div class="hero-title">
                <h1>Reports Management</h1>
                <p>Review and process facility or equipment issues reported by students and faculty.</p>
            </div>
            <div class="header-actions">
                <!-- Dark Mode Button with Custom Icon -->
                <button type="button" class="dark-mode-btn" id="darkModeToggle" title="Toggle Dark Mode">
                    <svg viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg">
                        <path d="M256 512C114.6 512 0 397.4 0 256S114.6 0 256 0s256 114.6 256 256-114.6 256-256 256zm0-464v416c114.9 0 208-93.1 208-208S370.9 48 256 48zm0 144a64 64 0 1 0 0 128V192z"/>
                    </svg>
                </button>

                <!-- Notification Button with Updated Notification Bell Icon -->
                <a href="notifications.php" class="top-notif-btn" title="View Notifications">
                    <svg viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg">
                        <path d="M256 32C150.1 32 64 118.1 64 224v61.5c0 32.7-13 64.1-36.1 87.2l-15.1 15.1C-1.8 403.2 8.3 432 32 432h448c23.7 0 33.8-28.8 19.2-43.2l-15.1-15.1C461 349.6 448 318.2 448 285.5V224c0-105.9-86.1-192-192-192zm64 416h-128c0 35.3 28.7 64 64 64s64-28.7 64-64z"/>
                    </svg>
                    <?php if ($unread_notifs > 0): ?>
                        <span class="top-notif-badge"><?= $unread_notifs; ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </div>

        <div class="reports-container">
            <div class="card">
                <h2>Submitted Incident Reports</h2>
                <p style="font-size: 13px; color: #64748b; margin-top: 4px; margin-bottom: 20px;">Monitor active issues and update statuses as they get resolved.</p>

                <?= $message; ?>

                <div style="overflow-x: auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Reported By</th>
                                <th>Target Type</th>
                                <th>Item Name</th>
                                <th>Category</th>
                                <th>Description</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($all_reports && $all_reports->num_rows > 0): ?>
                                <?php while ($r = $all_reports->fetch_assoc()): 
                                    $status_val = !empty($r['status']) ? $r['status'] : 'Pending';
                                    $cls = 'b-' . strtolower(str_replace(' ', '-', $status_val)); ?>
                                    <tr>
                                        <td>
                                            <b><?= htmlspecialchars($r['full_name'] ?? 'N/A') ?></b><br>
                                            <small style="color:#64748b;">@<?= htmlspecialchars($r['username'] ?? 'user') ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($r['target_type']) ?></td>
                                        <td><strong><?= htmlspecialchars($r['target_name']) ?></strong></td>
                                        <td><?= htmlspecialchars($r['category']) ?></td>
                                        <td><?= htmlspecialchars($r['description']) ?></td>
                                        <td><?= date('Y-m-d H:i', strtotime($r['created_at'])) ?></td>
                                        <td><span class="badge <?= $cls ?>"><?= htmlspecialchars($status_val) ?></span></td>
                                        <td>
                                            <div style="display:flex; gap: 8px; align-items: center;">
                                                <!-- Form for Status Update & Save Button -->
                                                <form method="POST" style="display:flex; gap: 6px; align-items: center; margin: 0;">
                                                    <input type="hidden" name="report_id" value="<?= $r['id'] ?>">
                                                    <select name="status" class="status-select">
                                                        <option value="Pending" <?= $status_val === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                                        <option value="In Review" <?= $status_val === 'In Review' ? 'selected' : '' ?>>In Review</option>
                                                        <option value="Resolved" <?= $status_val === 'Resolved' ? 'selected' : '' ?>>Resolved</option>
                                                    </select>
                                                    <button type="submit" name="update_status" class="btn-update">Save</button>
                                                </form>

                                                <!-- Form for Delete Button (Placed right beside Save) -->
                                                <form method="POST" style="margin: 0;" onsubmit="return confirm('Are you sure you want to delete this report?');">
                                                    <input type="hidden" name="report_id" value="<?= $r['id'] ?>">
                                                    <button type="submit" name="delete_report" class="btn-delete">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="8" style="text-align:center; padding: 25px; color: #64748b;">No submitted reports found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- JavaScript para sa sidebar at dark mode toggle -->
    <script>
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');

        menuToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            sidebar.classList.toggle('active');
        });

        document.addEventListener('click', (event) => {
            if (!sidebar.contains(event.target) && !menuToggle.contains(event.target)) {
                sidebar.classList.remove('active');
            }
        });

        // Dark Mode Toggle Script with LocalStorage Support
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