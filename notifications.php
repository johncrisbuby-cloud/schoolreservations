<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db.php';
require_once 'notif_helper.php';

$user_id   = $_SESSION['user_id'] ?? 0;
$user_name = $_SESSION['full_name'] ?? 'System User';
$user_role = $_SESSION['role'] ?? 'Student';
$user_profile_pic = '';

if (!$user_id) {
    header("Location: login.php");
    exit();
}

// SYNC: Kunin ang pinakabagong pangalan, role, at profile picture direkta mula sa database para sa user na ito lang
$stmt_sync = $conn->prepare("SELECT full_name, role, profile_pic FROM users WHERE id = ?");
$stmt_sync->bind_param("i", $user_id);
$stmt_sync->execute();
$sync_res = $stmt_sync->get_result()->fetch_assoc();
$stmt_sync->close();

if ($sync_res) {
    $user_name = $sync_res['full_name'];
    $_SESSION['full_name'] = $user_name;
    
    if (!empty($sync_res['role'])) {
        $user_role = $sync_res['role'];
        $_SESSION['role'] = $user_role;
    }
    
    $user_profile_pic = $sync_res['profile_pic'] ?? '';
}

$current_page = basename($_SERVER['PHP_SELF']);

// Handle Clear/Delete Notifications action
if (isset($_POST['clear_notifications'])) {
    $clear_stmt = $conn->prepare("DELETE FROM notifications WHERE user_id = ?");
    if ($clear_stmt) {
        $clear_stmt->bind_param("i", $user_id);
        $clear_stmt->execute();
        $clear_stmt->close();
    }
    header("Location: notifications.php");
    exit();
}

// Mark all as read upon entering the page
$conn->query("UPDATE notifications SET is_read = 1 WHERE user_id = $user_id");

// Get the unread notification count after marking as read
$unread_notifs = get_unread_notif_count($conn, $user_id);

// Fetch notifications for the current user
$notif_stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$notif_stmt->bind_param("i", $user_id);
$notif_stmt->execute();
$notifications = $notif_stmt->get_result();

// Dynamic Header, Navigation, and Links based on Role
$role_lower = strtolower($user_role);

if ($role_lower === 'admin') {
    $portal_title = "Admin Panel<br>Facility System";
    $dash_link    = "admin_dashboard.php";
} elseif ($role_lower === 'faculty') {
    $portal_title = "Faculty Portal<br>Facility System";
    $dash_link    = "faculty_dashboard.php";
} else {
    $portal_title = "School Facility<br>Reservation System";
    $dash_link    = "student_dashboard.php";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - School Facility System</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f8fafc;
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

        .logo-container {
            text-align: center;
            margin-bottom: 25px;
            margin-top: 35px;
        }

        .logo-placeholder a img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            background-color: #2557e0;
            color: #fff;
            font-weight: bold;
            font-size: 24px;
            border-radius: 14px;
            display: block;
            margin: 0 auto 10px auto;
            box-shadow: 0 4px 12px rgba(37, 87, 224, 0.25);
        }

        .logo-title {
            font-size: 11px;
            font-weight: 700;
            color: #1e293b;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            transition: color 0.3s;
        }

        .nav-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .nav-item a {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 18px;
            background-color: transparent;
            color: #2557e0 !important;
            border: 1.5px solid #2557e0;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            border-radius: 22px;
            text-align: center;
            transition: all 0.25s ease;
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

        .nav-item a svg {
            width: 15px;
            height: 15px;
            fill: #2557e0;
            transition: fill 0.25s ease;
        }

        .nav-item a:hover svg,
        .nav-item a.active svg {
            fill: #ffffff;
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
            pointer-events: none;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px 8px;
            border-top: 1px solid #f1f5f9;
            transition: border-color 0.3s;
        }

        .profile-link {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: inherit;
            flex: 1;
            overflow: hidden;
            padding: 6px;
            border-radius: 8px;
            transition: background 0.2s;
            min-width: 0;
        }

        .profile-link:hover {
            background-color: #f1f5f9;
        }

        .user-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            border: 1.5px solid #cbd5e1;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            background: #fff;
            flex-shrink: 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
            object-fit: cover;
        }

        .user-info { 
            font-size: 12px; 
            flex: 1;
            overflow: hidden;
            min-width: 0;
        }
        
        .user-info .name { 
            font-weight: 700; 
            color: #1e293b; 
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            transition: color 0.3s;
        }

        .user-info .role { font-size: 10px; color: #64748b; font-weight: 500; }

        .btn-logout {
            color: #ef4444;
            text-decoration: none;
            font-size: 12px;
            font-weight: 700;
            padding: 7px 12px;
            border-radius: 8px;
            border: 1px solid #fee2e2;
            background-color: #fef2f2;
            transition: all 0.2s;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .btn-logout:hover {
            background-color: #ef4444;
            color: #ffffff;
            border-color: #ef4444;
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.25);
        }

        /* Main Section */
        .main-content {
            flex: 1;
            overflow-y: auto;
            background: #f8fafc;
            width: 100%;
            transition: background 0.3s;
        }

        /* Top Hero Banner */
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

        .header-title h1 { 
            font-size: 28px; 
            color: #ffffff; 
            font-weight: 700; 
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 6px;
        }

        .header-title p { font-size: 13.5px; opacity: 0.9; font-weight: 400; color: #ffffff; }

        /* Header Right Actions Container */
        .header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            z-index: 20;
        }

        .dark-mode-btn {
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

        .dark-mode-btn:hover {
            background-color: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .dark-mode-btn svg {
            width: 24px;
            height: 24px;
            fill: #ffffff;
            transition: transform 0.3s ease;
            pointer-events: none;
        }
        
        .dark-mode-btn:hover svg {
            transform: rotate(180deg);
        }

        .page-container {
            padding: 0 40px 40px 40px;
        }

        .btn-clear {
            background-color: #ef4444;
            color: white;
            border: none;
            width: 42px;
            height: 42px;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .btn-clear:hover { background-color: #dc2626; box-shadow: 0 6px 16px rgba(239, 68, 68, 0.3); transform: translateY(-1px); }

        .notif-card {
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            border-left: 5px solid #2557e0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 16px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
            transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.3s, border-color 0.3s;
        }

        .notif-card:hover {
            box-shadow: 0 8px 20px rgba(0,0,0,0.06);
        }

        .notif-title { font-size: 15px; font-weight: 700; color: #0f172a; margin-bottom: 6px; transition: color 0.3s; }
        .notif-msg { font-size: 13px; color: #475569; margin-bottom: 10px; line-height: 1.5; transition: color 0.3s; }
        .notif-date { font-size: 11px; color: #94a3b8; font-weight: 500; }

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
        body.dark-mode .notif-card {
            background-color: #1e293b;
            border-color: #334155;
            border-left-color: #2557e0;
        }
        body.dark-mode .notif-title {
            color: #f1f5f9;
        }
        body.dark-mode .notif-msg {
            color: #cbd5e1;
        }

        @media (max-width: 768px) {
            .top-hero-banner {
                padding: 30px 20px 30px 75px;
            }
            .page-container {
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

    <!-- Sidebar Menu -->
    <aside class="sidebar" id="sidebar">
        <div>
            <div class="logo-container">
                <div class="logo-placeholder">
                    <a href="<?= $dash_link; ?>"><img src="logo.webp" alt="logo" onerror="this.src='logo.png';"></a>
                </div>
                <div class="logo-title"><?= $portal_title; ?></div>
            </div>

            <ul class="nav-list">
                <?php if ($role_lower === 'admin'): ?>
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
                        <a href="notifications.php" class="<?= ($current_page == 'notifications.php') ? 'active' : ''; ?>">
                            <svg viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg"><path d="M256 32C150.1 32 64 118.1 64 224v61.5c0 32.7-13 64.1-36.1 87.2l-15.1 15.1C-1.8 403.2 8.3 432 32 432h448c23.7 0 33.8-28.8 19.2-43.2l-15.1-15.1C461 349.6 448 318.2 448 285.5V224c0-105.9-86.1-192-192-192zm64 416h-128c0 35.3 28.7 64 64 64s64-28.7 64-64z"/></svg>
                            Notifications
                            <?php if ($unread_notifs > 0): ?>
                                <span class="notif-badge"><?= $unread_notifs; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="admin_reports.php" class="<?= ($current_page == 'admin_reports.php') ? 'active' : ''; ?>">Reports List</a>
                    </li>
                
                <?php elseif ($role_lower === 'faculty'): ?>
                    <li class="nav-item">
                        <a href="faculty_dashboard.php" class="<?= ($current_page == 'faculty_dashboard.php') ? 'active' : ''; ?>">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a href="equipment_faculty.php" class="<?= ($current_page == 'equipment_faculty.php') ? 'active' : ''; ?>">Equipment</a>
                    </li>
                    <li class="nav-item">
                        <a href="facility_faculty.php" class="<?= ($current_page == 'facility_faculty.php') ? 'active' : ''; ?>">Facility</a>
                    </li>
                    <li class="nav-item">
                        <a href="calendar_faculty.php" class="<?= ($current_page == 'calendar_faculty.php') ? 'active' : ''; ?>">Calendar</a>
                    </li>
                    <li class="nav-item">
                        <a href="my_bookings_faculty.php" class="<?= ($current_page == 'my_bookings_faculty.php') ? 'active' : ''; ?>">My Bookings</a>
                    </li>
                    <li class="nav-item">
                        <a href="notifications.php" class="<?= ($current_page == 'notifications.php') ? 'active' : ''; ?>">
                            <svg viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg"><path d="M256 32C150.1 32 64 118.1 64 224v61.5c0 32.7-13 64.1-36.1 87.2l-15.1 15.1C-1.8 403.2 8.3 432 32 432h448c23.7 0 33.8-28.8 19.2-43.2l-15.1-15.1C461 349.6 448 318.2 448 285.5V224c0-105.9-86.1-192-192-192zm64 416h-128c0 35.3 28.7 64 64 64s64-28.7 64-64z"/></svg>
                            Notifications
                            <?php if ($unread_notifs > 0): ?>
                                <span class="notif-badge"><?= $unread_notifs; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="reports_faculty.php" class="<?= ($current_page == 'reports_faculty.php') ? 'active' : ''; ?>">Reports</a>
                    </li>

                <?php else: ?>
                    <li class="nav-item">
                        <a href="student_dashboard.php" class="<?= ($current_page == 'student_dashboard.php') ? 'active' : ''; ?>">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a href="equipment_student.php" class="<?= ($current_page == 'equipment_student.php') ? 'active' : ''; ?>">Equipment</a>
                    </li>
                    <li class="nav-item">
                        <a href="facility_student.php" class="<?= ($current_page == 'facility_student.php') ? 'active' : ''; ?>">Facility</a>
                    </li>
                    <li class="nav-item">
                        <a href="calendar_student.php" class="<?= ($current_page == 'calendar_student.php') ? 'active' : ''; ?>">Calendar</a>
                    </li>
                    <li class="nav-item">
                        <a href="my_bookings.php" class="<?= ($current_page == 'my_bookings.php') ? 'active' : ''; ?>">My Bookings</a>
                    </li>
                    <li class="nav-item">
                        <a href="notifications.php" class="<?= ($current_page == 'notifications.php') ? 'active' : ''; ?>">
                            <svg viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg"><path d="M256 32C150.1 32 64 118.1 64 224v61.5c0 32.7-13 64.1-36.1 87.2l-15.1 15.1C-1.8 403.2 8.3 432 32 432h448c23.7 0 33.8-28.8 19.2-43.2l-15.1-15.1C461 349.6 448 318.2 448 285.5V224c0-105.9-86.1-192-192-192zm64 416h-128c0 35.3 28.7 64 64 64s64-28.7 64-64z"/></svg>
                            Notifications
                            <?php if ($unread_notifs > 0): ?>
                                <span class="notif-badge"><?= $unread_notifs; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="reports.php" class="<?= ($current_page == 'reports.php') ? 'active' : ''; ?>">Reports</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>

        <!-- User Profile Section with Clickable Profile Link & Logout -->
        <div class="user-profile">
            <a href="profile.php" class="profile-link" title="Edit Profile">
                <?php if (!empty($user_profile_pic) && file_exists($user_profile_pic)): ?>
                    <img src="<?= htmlspecialchars($user_profile_pic); ?>" alt="Avatar" class="user-avatar">
                <?php else: ?>
                    <div class="user-avatar"><?= ($role_lower === 'admin') ? '⚙️' : '👤'; ?></div>
                <?php endif; ?>
                <div class="user-info">
                    <div class="name" title="<?= htmlspecialchars($user_name); ?>"><?= htmlspecialchars($user_name); ?></div>
                    <div class="role"><?= htmlspecialchars($user_role); ?></div>
                </div>
            </a>
            <a href="logout.php" class="btn-logout" onclick="return confirm('Are you sure you want to log out?');">Logout</a>
        </div>
    </aside>

    <!-- Main Content Area -->
    <main class="main-content">
        <!-- Top Hero Banner with Dark Mode Button Only -->
        <div class="top-hero-banner">
            <div class="header-title">
                <h1>Notifications</h1>
                <p>Notices and updates regarding your account and requests.</p>
            </div>
            <div class="header-actions">
                <!-- Dark Mode Button with Custom Icon -->
                <button type="button" class="dark-mode-btn" id="darkModeToggle" title="Toggle Dark Mode">
                    <svg viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg">
                        <path d="M256 512C114.6 512 0 397.4 0 256S114.6 0 256 0s256 114.6 256 256-114.6 256-256 256zm0-464v416c114.9 0 208-93.1 208-208S370.9 48 256 48zm0 144a64 64 0 1 0 0 128V192z"/>
                    </svg>
                </button>
            </div>
        </div>

        <div class="page-container">
            <div style="display: flex; justify-content: flex-end; margin-bottom: 20px;">
                <?php if ($notifications && $notifications->num_rows > 0): ?>
                    <form method="POST" onsubmit="return confirm('Are you sure you want to clear all notifications?');">
                        <button type="submit" name="clear_notifications" class="btn-clear" title="Clear All Notifications">
                            <svg style="width: 18px; height: 18px; fill: currentColor;" viewBox="0 0 448 512" xmlns="http://www.w3.org/2000/svg"><path d="M135.2 17.7L128 32H32C14.3 32 0 46.3 0 64S14.3 96 32 96H416c17.7 0 32-14.3 32-32s-14.3-32-32-32H320l-7.2-14.3C307.4 6.8 296.3 0 284.2 0H163.8c-12.1 0-23.2 6.8-28.6 17.7zM416 128H32L53.2 467c1.6 25.3 22.6 45 47.9 45H346.9c25.3 0 46.3-19.7 47.9-45L416 128z"/></svg>
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="notif-list">
                <?php if ($notifications && $notifications->num_rows > 0): ?>
                    <?php while ($row = $notifications->fetch_assoc()): ?>
                        <div class="notif-card">
                            <div class="notif-title"><?= htmlspecialchars($row['title']); ?></div>
                            <div class="notif-msg"><?= htmlspecialchars($row['message']); ?></div>
                            <div class="notif-date">⏱ <?= htmlspecialchars($row['created_at']); ?></div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="notif-card">
                        <div class="notif-msg">No notifications at the moment.</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        // Sidebar Toggle Script
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