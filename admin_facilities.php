<?php
require_once 'db.php';
require_once 'notif_helper.php';

check_login();

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

$unread_notifs = get_unread_notif_count($conn, $admin_id);
$message = "";

$current_page = basename($_SERVER['PHP_SELF']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_facility') {
    $name        = trim($_POST['name']);
    $location    = trim($_POST['location']);
    $capacity    = intval($_POST['capacity']);
    $description = trim($_POST['description']);
    $status      = trim($_POST['status'] ?? 'Available');

    if (!empty($name) && !empty($location)) {
        $stmt = $conn->prepare("INSERT INTO facilities (name, location, capacity, description, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssiss", $name, $location, $capacity, $description, $status);

        if ($stmt->execute()) {
            $message = "<div class='alert alert-success'>Successfully added <b>" . htmlspecialchars($name) . "</b> to facilities!</div>";

            $notif_title = "New Facility Added 🏫";
            $notif_msg   = "A new facility is available: " . htmlspecialchars($name) . " (" . htmlspecialchars($location) . ") with a capacity of " . $capacity . " pax.";
            $users_res   = $conn->query("SELECT id FROM users WHERE role IN ('Student', 'Faculty')");
            if ($users_res) {
                while ($u = $users_res->fetch_assoc()) {
                    $u_id   = $u['id'];
                    $n_stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
                    $n_stmt->bind_param("iss", $u_id, $notif_title, $notif_msg);
                    $n_stmt->execute();
                    $n_stmt->close();
                }
            }
        } else {
            $message = "<div class='alert alert-danger'>Error adding facility: " . htmlspecialchars($conn->error) . "</div>";
        }
    } else {
        $message = "<div class='alert alert-danger'>Please fill in all required fields properly.</div>";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_facility') {
    $id          = intval($_POST['id']);
    $name        = trim($_POST['name']);
    $location    = trim($_POST['location']);
    $capacity    = intval($_POST['capacity']);
    $description = trim($_POST['description']);
    $status      = trim($_POST['status']);

    if ($id > 0 && !empty($name)) {
        $stmt = $conn->prepare("UPDATE facilities SET name = ?, location = ?, capacity = ?, description = ?, status = ? WHERE id = ?");
        $stmt->bind_param("ssissi", $name, $location, $capacity, $description, $status, $id);

        if ($stmt->execute()) {
            $message = "<div class='alert alert-success'>Successfully updated facility <b>" . htmlspecialchars($name) . "</b>!</div>";

            $notif_title = "Facility Updated 🏛️";
            $notif_msg   = "Facility details have been updated: " . htmlspecialchars($name) . " (Capacity: " . $capacity . " pax).";
            $users_res   = $conn->query("SELECT id FROM users WHERE role IN ('Student', 'Faculty')");
            if ($users_res) {
                while ($u = $users_res->fetch_assoc()) {
                    $u_id   = $u['id'];
                    $n_stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
                    $n_stmt->bind_param("iss", $u_id, $notif_title, $notif_msg);
                    $n_stmt->execute();
                    $n_stmt->close();
                }
            }
        } else {
            $message = "<div class='alert alert-danger'>Error updating facility: " . htmlspecialchars($conn->error) . "</div>";
        }
    } else {
        $message = "<div class='alert alert-danger'>Invalid data provided for update.</div>";
    }
}

if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    if ($delete_id > 0) {
        $fac_name_q = $conn->prepare("SELECT name FROM facilities WHERE id = ?");
        $fac_name_q->bind_param("i", $delete_id);
        $fac_name_q->execute();
        $fac_res          = $fac_name_q->get_result()->fetch_assoc();
        $deleted_fac_name = $fac_res['name'] ?? 'Facility Item';
        $fac_name_q->close();

        $stmt = $conn->prepare("DELETE FROM facilities WHERE id = ?");
        $stmt->bind_param("i", $delete_id);
        if ($stmt->execute()) {
            $message = "<div class='alert alert-danger'>Facility deleted from system.</div>";

            $notif_title = "Facility Removed ❌";
            $notif_msg   = "The facility '" . htmlspecialchars($deleted_fac_name) . "' has been removed.";
            $users_res   = $conn->query("SELECT id FROM users WHERE role IN ('Student', 'Faculty')");
            if ($users_res) {
                while ($u = $users_res->fetch_assoc()) {
                    $u_id   = $u['id'];
                    $n_stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
                    $n_stmt->bind_param("iss", $u_id, $notif_title, $notif_msg);
                    $n_stmt->execute();
                    $n_stmt->close();
                }
            }
        } else {
            $message = "<div class='alert alert-danger'>Error deleting facility: " . htmlspecialchars($conn->error) . "</div>";
        }
    }
}

$query         = "SELECT * FROM facilities ORDER BY id DESC";
$facility_list = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Facilities - Admin</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #f1f5f9; display: flex; height: 100vh; overflow: hidden; color: #334155; transition: background-color 0.3s, color 0.3s; }

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
        .logo-placeholder img { 
            width: 60px; height: 60px; background-color: #2557e0; color: #fff;
            border-radius: 14px; display: block; margin: 0 auto 10px auto; object-fit: cover;
            box-shadow: 0 4px 12px rgba(37, 87, 224, 0.25);
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
            background-color: #ef4444; color: #ffffff; font-size: 10px; font-weight: 700; 
            padding: 2px 7px; border-radius: 10px; position: absolute; right: 14px; 
            top: 50%; transform: translateY(-50%); box-shadow: 0 2px 5px rgba(239, 68, 68, 0.3); 
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

        .facilities-container {
            padding: 0 40px 40px 40px;
        }

        .inventory-box { background-color: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 28px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); transition: background-color 0.3s, border-color 0.3s; }
        .data-table { width: 100%; border-collapse: separate; border-spacing: 0; text-align: left; }
        .data-table th { font-size: 12px; color: #475569; padding: 12px 16px; border-bottom: 1px solid #e2e8f0; background-color: #f8fafc; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; transition: background 0.3s, color 0.3s, border-color 0.3s; }
        .data-table td { font-size: 13px; padding: 14px 16px; border-bottom: 1px solid #f1f5f9; color: #334155; transition: border-color 0.3s, color 0.3s; }
        .data-table tr:last-child td { border-bottom: none; }

        .btn-add { background: linear-gradient(135deg, #2557e0 0%, #1a44b8 100%); color: #fff; border: none; padding: 11px 22px; border-radius: 10px; font-weight: 700; font-size: 13px; cursor: pointer; margin-bottom: 24px; transition: all 0.25s ease; box-shadow: 0 4px 12px rgba(37, 87, 224, 0.25); }
        .btn-add:hover { background: linear-gradient(135deg, #1d4ed8, #1e40af); box-shadow: 0 6px 18px rgba(37, 87, 224, 0.35); transform: translateY(-1px); }
        
        .btn-edit { background-color: #64748b; color: #fff; border: none; padding: 6px 12px; border-radius: 6px; font-weight: 700; font-size: 11px; cursor: pointer; text-decoration: none; margin-right: 4px; transition: background 0.2s; }
        .btn-edit:hover { background-color: #475569; }

        .btn-delete { background-color: #ef4444; color: #fff; border: none; padding: 6px 12px; border-radius: 6px; font-weight: 700; font-size: 11px; cursor: pointer; text-decoration: none; transition: background 0.2s; }
        .btn-delete:hover { background-color: #dc2626; }

        .alert { padding: 12px 16px; margin-bottom: 24px; border-radius: 8px; font-size: 13px; font-weight: 500; }
        .alert-success { background-color: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-danger { background-color: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

        .modal { display: none; position: fixed; z-index: 100; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); justify-content: center; align-items: center; backdrop-filter: blur(4px); }
        .modal-content { background-color: #fff; padding: 28px; border-radius: 14px; width: 100%; max-width: 420px; box-shadow: 0 10px 25px rgba(0,0,0,0.15); transition: background-color 0.3s; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155; transition: color 0.3s; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px 14px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 13px; outline: none; background: #fff; color: #1e293b; transition: border-color 0.2s, background-color 0.3s, color 0.3s; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color: #2557e0; box-shadow: 0 0 0 3px rgba(37, 87, 224, 0.15); }
        
        .btn-cancel { background-color: #e2e8f0; color: #334155; border: none; padding: 10px 16px; border-radius: 8px; font-weight: 600; font-size: 13px; cursor: pointer; transition: background 0.2s; }
        .btn-cancel:hover { background-color: #cbd5e1; }

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
        body.dark-mode .inventory-box {
            background-color: #1e293b;
            border-color: #334155;
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
        body.dark-mode .modal-content {
            background-color: #1e293b;
            color: #f1f5f9;
        }
        body.dark-mode .form-group label {
            color: #cbd5e1;
        }
        body.dark-mode .form-group input,
        body.dark-mode .form-group select,
        body.dark-mode .form-group textarea {
            background-color: #0f172a;
            border-color: #475569;
            color: #f1f5f9;
        }
        body.dark-mode .btn-cancel {
            background-color: #334155;
            color: #f1f5f9;
        }
        body.dark-mode .btn-cancel:hover {
            background-color: #475569;
        }

        @media (max-width: 768px) {
            .top-hero-banner {
                padding: 30px 20px 30px 75px;
            }
            .facilities-container {
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
                <h1>Facility Inventory Management</h1>
                <p>Manage and configure campus facilities, rooms, availability status, and capacities.</p>
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

        <div class="facilities-container">
            <?= $message; ?>

            <button class="btn-add" onclick="document.getElementById('addModal').style.display='flex'">+ Add New Facility</button>

            <div class="inventory-box">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Facility Name</th>
                            <th>Location</th>
                            <th>Capacity</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($facility_list && $facility_list->num_rows > 0): ?>
                            <?php while ($row = $facility_list->fetch_assoc()): ?>
                                <tr>
                                    <td><b><?= htmlspecialchars($row['id']); ?></b></td>
                                    <td><?= htmlspecialchars($row['name'] ?? 'N/A'); ?></td>
                                    <td><?= htmlspecialchars($row['location'] ?? 'Main Campus'); ?></td>
                                    <td><b><?= htmlspecialchars($row['capacity'] ?? 0); ?> pax</b></td>
                                    <td><?= htmlspecialchars($row['description'] ?? ''); ?></td>
                                    <td><?= htmlspecialchars($row['status'] ?? 'Available'); ?></td>
                                    <td>
                                        <button class="btn-edit" onclick='openEditModal(<?= json_encode($row); ?>)'>Edit</button>
                                        <a href="admin_facilities.php?delete_id=<?= $row['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this facility?');">Delete</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; color: #64748b; padding: 25px;">No facilities found in inventory.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- ADD MODAL -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <h3 style="margin-bottom: 15px; font-weight: 700;">Add Facility Item</h3>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_facility">
                <div class="form-group">
                    <label>Facility Name</label>
                    <input type="text" name="name" required placeholder="e.g. Audio Visual Room (AVR)">
                </div>
                <div class="form-group">
                    <label>Location / Building</label>
                    <input type="text" name="location" required placeholder="e.g. Building A - 3rd Floor">
                </div>
                <div class="form-group">
                    <label>Capacity (Persons)</label>
                    <input type="number" name="capacity" min="1" required value="50">
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" rows="2" placeholder="Brief description..."></textarea>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="Available">Available</option>
                        <option value="Unavailable">Unavailable</option>
                        <option value="Under Maintenance">Under Maintenance</option>
                    </select>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                    <button type="button" class="btn-cancel" onclick="document.getElementById('addModal').style.display='none'">Cancel</button>
                    <button type="submit" class="btn-add" style="margin-bottom: 0;">Save Facility</button>
                </div>
            </form>
        </div>
    </div>

    <!-- EDIT MODAL -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <h3 style="margin-bottom: 15px; font-weight: 700;">Edit Facility Item</h3>
            <form method="POST" action="">
                <input type="hidden" name="action" value="edit_facility">
                <input type="hidden" name="id" id="edit_id">

                <div class="form-group">
                    <label>Facility Name</label>
                    <input type="text" name="name" id="edit_name" required>
                </div>
                <div class="form-group">
                    <label>Location / Building</label>
                    <input type="text" name="location" id="edit_location" required>
                </div>
                <div class="form-group">
                    <label>Capacity (Persons)</label>
                    <input type="number" name="capacity" id="edit_capacity" min="1" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="edit_description" rows="2"></textarea>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="edit_status">
                        <option value="Available">Available</option>
                        <option value="Unavailable">Unavailable</option>
                        <option value="Under Maintenance">Under Maintenance</option>
                    </select>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                    <button type="button" class="btn-cancel" onclick="document.getElementById('editModal').style.display='none'">Cancel</button>
                    <button type="submit" class="btn-add" style="margin-bottom: 0; background-color: #64748b;">Update Facility</button>
                </div>
            </form>
        </div>
    </div>

    <!-- JavaScript para sa sidebar, dark mode, at modals -->
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

        function openEditModal(data) {
            document.getElementById('edit_id').value = data.id;
            document.getElementById('edit_name').value = data.name || '';
            document.getElementById('edit_location').value = data.location || '';
            document.getElementById('edit_capacity').value = data.capacity || 1;
            document.getElementById('edit_description').value = data.description || '';
            document.getElementById('edit_status').value = data.status || 'Available';

            document.getElementById('editModal').style.display = 'flex';
        }
    </script>
</body>
</html>