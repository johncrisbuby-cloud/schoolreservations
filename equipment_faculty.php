<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db.php';
require_once 'notif_helper.php'; // Required for the Notifications system

// Verify if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_name  = $_SESSION['full_name'] ?? 'Faculty Member';
$user_id    = $_SESSION['user_id'] ?? 0;
$user_role  = $_SESSION['role'] ?? 'Faculty';
$user_profile_pic = '';

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

// Automatically set faculty identifier from session for database record
$faculty_id = $_SESSION['faculty_id'] ?? $_SESSION['student_id'] ?? $_SESSION['user_id'] ?? '';

// Get unread notification count
$unread_notifs = get_unread_notif_count($conn, $user_id);

// Get current page name for dynamic navigation highlighting
$current_page = basename($_SERVER['PHP_SELF']);

$error = '';

// Check actual column names of 'borrowings' table
$borrowings_columns = [];
$col_check = $conn->query("SHOW COLUMNS FROM borrowings");
if ($col_check) {
    while ($c = $col_check->fetch_assoc()) {
        $borrowings_columns[] = strtolower($c['Field']);
    }
}

// Handling Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {
    $equipment_id = intval($_POST['equipment_id'] ?? 0);
    $quantity     = intval($_POST['quantity'] ?? 1);
    $borrow_date  = trim($_POST['borrow_date'] ?? '');
    $return_date  = trim($_POST['return_date'] ?? '');
    $department   = trim($_POST['department'] ?? '');
    $purpose      = trim($_POST['purpose'] ?? '');
    $faculty_name = trim($_POST['faculty_name'] ?? $user_name);

    $today = date('Y-m-d');

    // Date validation: Check if borrow or return date is in the past
    if ($borrow_date < $today || $return_date < $today) {
        $error = "Reservation denied! You cannot borrow equipment for past dates.";
    } elseif ($return_date < $borrow_date) {
        $error = "Invalid date range! Return date must be on or after the borrow date.";
    } elseif ($equipment_id > 0 && !empty($borrow_date) && !empty($return_date)) {
        
        $fields = ['equipment_id', 'borrow_date', 'return_date', 'status'];
        $values = [$equipment_id, $borrow_date, $return_date, 'Pending'];
        $types  = "isss";

        if (in_array('user_id', $borrowings_columns)) {
            array_unshift($fields, 'user_id');
            array_unshift($values, $user_id);
            $types = "i" . $types;
        } elseif (in_array('faculty_id', $borrowings_columns)) {
            array_unshift($fields, 'faculty_id');
            array_unshift($values, $faculty_id);
            $types = "s" . $types;
        } elseif (in_array('student_id', $borrowings_columns)) {
            array_unshift($fields, 'student_id');
            array_unshift($values, $faculty_id);
            $types = "s" . $types;
        }

        if (in_array('faculty_name', $borrowings_columns)) {
            $fields[] = 'faculty_name';
            $values[] = $faculty_name;
            $types .= "s";
        } elseif (in_array('student_name', $borrowings_columns)) {
            $fields[] = 'student_name';
            $values[] = $faculty_name;
            $types .= "s";
        }

        if (in_array('quantity', $borrowings_columns) || in_array('qty', $borrowings_columns)) {
            $q_col = in_array('quantity', $borrowings_columns) ? 'quantity' : 'qty';
            $fields[] = $q_col;
            $values[] = $quantity;
            $types .= "i";
        }

        if (in_array('department', $borrowings_columns)) {
            $fields[] = 'department';
            $values[] = $department;
            $types .= "s";
        }
        if (in_array('purpose', $borrowings_columns)) {
            $fields[] = 'purpose';
            $values[] = $purpose;
            $types .= "s";
        }

        $placeholders = implode(',', array_fill(0, count($fields), '?'));
        $sql = "INSERT INTO borrowings (" . implode(',', $fields) . ") VALUES ($placeholders)";
        
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param($types, ...$values);
            if ($stmt->execute()) {
                notify_all_admins(
                    $conn, 
                    "Faculty Equipment Borrow Request 🏛️", 
                    "Prof. " . htmlspecialchars($faculty_name) . " submitted an equipment borrow request for their class/activity."
                );

                header("Location: equipment_faculty.php?sent=1");
                exit();
            } else {
                $error = "Error submitting request: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $error = "Database Query Error: " . $conn->error;
        }
    } else {
        $error = "Please select equipment and complete the dates.";
    }
}

// SMART QUERY FOR EQUIPMENT DROPDOWN
$equipment_list = $conn->query("SELECT * FROM equipment ORDER BY 1 ASC");

// Fetch Borrow History with JOIN to display equipment name properly
$history_result = $conn->query("
    SELECT b.*, COALESCE(NULLIF(b.equipment_name, ''), e.name) AS computed_equipment_name 
    FROM borrowings b 
    LEFT JOIN equipment e ON b.equipment_id = e.id 
    ORDER BY b.id DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Equipment Borrowing - School Facility System</title>
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

        .logo-container {
            text-align: center;
            margin-bottom: 25px;
            margin-top: 35px;
        }

        .logo-placeholder a img {
            width: 60px;
            height: 60px;
            background-color: #2557e0;
            color: #fff;
            font-weight: bold;
            font-size: 24px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px auto;
            box-shadow: 0 4px 12px rgba(37, 87, 224, 0.25);
            object-fit: cover;
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
            display: block;
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

        /* User Profile & Logout Styling */
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

        /* Container para sa loob ng faculty body */
        .dashboard-container {
            padding: 0 40px 40px 40px;
        }

        /* Panels & Cards */
        .action-panel {
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 28px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
            margin-bottom: 24px;
            transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.3s, border-color 0.3s;
        }

        .action-panel:hover {
            box-shadow: 0 8px 20px rgba(0,0,0,0.06);
        }

        .action-panel h3 {
            font-size: 16.5px;
            color: #0f172a;
            margin-bottom: 18px;
            font-weight: 700;
            transition: color 0.3s;
        }

        .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
        .full-width { grid-column: 1 / -1; }

        label { display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155; transition: color 0.3s; }
        select, input {
            width: 100%;
            padding: 10px 14px;
            font-size: 13px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            outline: none;
            background-color: #fff;
            color: #1e293b;
            transition: border-color 0.2s, box-shadow 0.2s, background-color 0.3s, color 0.3s;
        }
        
        select:focus, input:focus {
            border-color: #2557e0;
            box-shadow: 0 0 0 3px rgba(37, 87, 224, 0.15);
        }

        .btn-submit {
            display: inline-block;
            background: linear-gradient(135deg, #2557e0 0%, #1a44b8 100%);
            color: #fff;
            padding: 11px 22px;
            border: none;
            border-radius: 10px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(37, 87, 224, 0.2);
        }

        .btn-submit:hover {
            background: linear-gradient(135deg, #1d4ed8, #1e40af);
            box-shadow: 0 6px 16px rgba(37, 87, 224, 0.3);
            transform: translateY(-1px);
        }

        /* Alert Box */
        .alert { padding: 12px 16px; margin-bottom: 24px; border-radius: 8px; font-size: 13px; font-weight: 500; }
        .alert-success { background-color: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-danger { background-color: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

        /* Tables */
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: separate; border-spacing: 0; text-align: left; }
        th {
            font-size: 12px;
            font-weight: 700;
            color: #475569;
            padding: 12px 16px;
            border-bottom: 1px solid #e2e8f0;
            background-color: #f8fafc;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: background-color 0.3s, color 0.3s, border-color 0.3s;
        }
        td {
            font-size: 13px;
            padding: 14px 16px;
            color: #334155;
            border-bottom: 1px solid #f1f5f9;
            transition: color 0.3s, border-color 0.3s;
        }
        table tr:last-child td { border-bottom: none; }

        .badge { display: inline-block; padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; }
        .badge-pending { background-color: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
        .badge-approved { background-color: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .badge-rejected { background-color: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .empty { padding: 24px; color: #64748b; text-align: center; font-style: italic; }

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
        body.dark-mode .action-panel {
            background-color: #1e293b;
            border-color: #334155;
        }
        body.dark-mode .action-panel h3 {
            color: #f1f5f9;
        }
        body.dark-mode label {
            color: #cbd5e1;
        }
        body.dark-mode select, 
        body.dark-mode input {
            background-color: #0f172a;
            border-color: #334155;
            color: #f1f5f9;
        }
        body.dark-mode th {
            background-color: #1a2234;
            color: #94a3b8;
            border-bottom-color: #334155;
        }
        body.dark-mode td {
            color: #cbd5e1;
            border-bottom-color: #334155;
        }

        @media (max-width: 768px) {
            .top-hero-banner {
                padding: 30px 20px 30px 75px;
            }
            .dashboard-container {
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

    <!-- Sidebar Menu (Collapsible) -->
    <aside class="sidebar" id="sidebar">
        <div>
            <div class="logo-container">
                <div class="logo-placeholder">
                    <a href="faculty_dashboard.php"><img src="logo.webp" alt="logo" onerror="this.src='logo.png';"></a>
                </div>
                <div class="logo-title">Faculty Portal<br>Facility System</div>
            </div>

            <ul class="nav-list">
                <li class="nav-item"><a href="faculty_dashboard.php" class="<?= ($current_page == 'faculty_dashboard.php') ? 'active' : ''; ?>">Dashboard</a></li>
                <li class="nav-item"><a href="equipment_faculty.php" class="<?= ($current_page == 'equipment_faculty.php') ? 'active' : ''; ?>">Equipment</a></li>
                <li class="nav-item"><a href="facility_faculty.php" class="<?= ($current_page == 'facility_faculty.php') ? 'active' : ''; ?>">Facility</a></li>
                <li class="nav-item"><a href="calendar_faculty.php" class="<?= ($current_page == 'calendar_faculty.php') ? 'active' : ''; ?>">Calendar</a></li>
                <li class="nav-item"><a href="my_bookings_faculty.php" class="<?= ($current_page == 'my_bookings_faculty.php') ? 'active' : ''; ?>">My Bookings</a></li>
                <li class="nav-item"><a href="reports_faculty.php" class="<?= ($current_page == 'reports_faculty.php') ? 'active' : ''; ?>">Reports</a></li>
            </ul>
        </div>

        <div class="user-profile">
            <a href="profile.php" class="profile-link" title="Edit Profile">
                <?php if (!empty($user_profile_pic) && file_exists($user_profile_pic)): ?>
                    <img src="<?= htmlspecialchars($user_profile_pic); ?>" alt="Avatar" class="user-avatar">
                <?php else: ?>
                    <div class="user-avatar">👤</div>
                <?php endif; ?>
                <div class="user-info">
                    <div class="name" title="<?= htmlspecialchars($user_name); ?>"><?= htmlspecialchars($user_name); ?></div>
                    <div class="role"><?= htmlspecialchars($user_role); ?></div>
                </div>
            </a>
            <a href="logout.php" class="btn-logout" onclick="return confirm('Are you sure you want to log out?');">Logout</a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Top Hero Banner with Dark Mode & Notif Icons -->
        <div class="top-hero-banner">
            <div class="hero-title">
                <h1>Faculty Equipment Borrowing</h1>
                <p>Request equipment for academic lectures, departmental activities, and campus events.</p>
            </div>
            <div class="header-actions">
                <!-- Dark Mode Button with Icon -->
                <button type="button" class="dark-mode-btn" id="darkModeToggle" title="Toggle Dark Mode">
                    <svg viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg">
                        <path d="M256 512C114.6 512 0 397.4 0 256S114.6 0 256 0s256 114.6 256 256-114.6 256-256 256zm0-464v416c114.9 0 208-93.1 208-208S370.9 48 256 48zm0 144a64 64 0 1 0 0 128V192z"/>
                    </svg>
                </button>

                <!-- Notification Button with Bell Icon -->
                <a href="notifications.php" class="top-notif-btn" title="View Notifications">
                    <svg viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg">
                        <path d="M256 32C150.1 32 64 118.1 64 224v61.5c0 32.7-13 64.1-36.1 87.2l-15.1 15.1C-1.8 403.2 8.3 432 32 432h448c23.7 0 33.8-28.8 19.2-43.2l-15.1-15.1C461 349.6 448 318.2 448 285.5V224c0-105.9-86.1-192-192-192zm64 416h-128c0 35.3 28.7 64 64 64s64-28.7 64-64z"/>
                    </svg>
                    <?php if (isset($unread_notifs) && $unread_notifs > 0): ?>
                        <span class="top-notif-badge"><?= $unread_notifs; ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </div>

        <div class="dashboard-container">
            <section class="action-panel">
                <h3>Request Equipment </h3>

                <?php if (isset($_GET['sent'])): ?>
                    <div class="alert alert-success">Equipment borrow request submitted successfully! An administrator will review it soon.</div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="POST" class="form-grid">
                    <div class="full-width">
                        <label for="faculty_name">Faculty Name</label>
                        <input type="text" name="faculty_name" id="faculty_name" value="<?= htmlspecialchars($user_name); ?>" required>
                    </div>

                    <div>
                        <label for="equipment_id">Select Equipment</label>
                        <select name="equipment_id" id="equipment_id" required>
                            <option value="">-- Choose Equipment --</option>
                            <?php if ($equipment_list && $equipment_list->num_rows > 0): ?>
                                <?php while ($eq = $equipment_list->fetch_assoc()): 
                                    $eq_id        = $eq['id'] ?? $eq['equipment_id'] ?? 0;
                                    $eq_name      = $eq['name'] ?? $eq['equipment_name'] ?? 'Equipment #' . $eq_id;
                                    $available    = intval($eq['quantity_available'] ?? 0);
                                ?>
                                    <option value="<?= htmlspecialchars($eq_id); ?>">
                                        <?= htmlspecialchars($eq_name); ?> (Available Stock: <?= $available; ?>)
                                    </option>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <option value="">-- No Equipment Available --</option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div>
                        <label for="quantity">Quantity Needed</label>
                        <input type="number" name="quantity" id="quantity" value="1" min="1" required>
                    </div>

                    <div>
                        <label for="borrow_date">Borrow Date</label>
                        <input type="date" name="borrow_date" id="borrow_date" value="<?= date('Y-m-d'); ?>" min="<?= date('Y-m-d'); ?>" required>
                    </div>

                    <div>
                        <label for="return_date">Return Date</label>
                        <input type="date" name="return_date" id="return_date" value="<?= date('Y-m-d'); ?>" min="<?= date('Y-m-d'); ?>" required>
                    </div>

                    <div>
                        <label for="department">Department / Office</label>
                        <input type="text" name="department" id="department" placeholder="e.g. BSIT / College of Computer Studies">
                    </div>

                    <div>
                        <label for="purpose">Subject / Class / Activity Purpose</label>
                        <input type="text" name="purpose" id="purpose" placeholder="e.g. IT 101 Laboratory Work / Department Meeting">
                    </div>

                    <div class="full-width" style="margin-top: 8px;">
                        <button type="submit" name="submit_request" class="btn-submit">Submit Borrow Request</button>
                    </div>
                </form>
            </section>

            <section class="action-panel">
                <h3>Faculty Equipment Borrowed</h3>
                <div class="table-wrap" style="margin-top: 12px;">
                    <table>
                        <thead>
                            <tr>
                                <th>Equipment / Details</th>
                                <th>Borrow Date</th>
                                <th>Return Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($history_result && $history_result->num_rows > 0): ?>
                                <?php while ($row = $history_result->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <b><?= htmlspecialchars($row['computed_equipment_name'] ?? 'Equipment #' . $row['equipment_id']); ?></b>
                                            <?= isset($row['quantity']) ? '(Qty: ' . intval($row['quantity']) . ')' : ''; ?>
                                        </td>
                                        <td><?= htmlspecialchars($row['borrow_date'] ?? 'N/A'); ?></td>
                                        <td><?= htmlspecialchars($row['return_date'] ?? 'N/A'); ?></td>
                                        <td>
                                            <?php
                                            $st = $row['status'] ?? 'Pending';
                                            $cls = ($st === 'Approved' || $st === 'Borrowed') ? 'badge-approved' : (($st === 'Rejected') ? 'badge-rejected' : 'badge-pending');
                                            ?>
                                            <span class="badge <?= $cls; ?>"><?= htmlspecialchars($st); ?></span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="empty">No equipment borrow requests found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </main>

    <!-- JavaScript para sa Hamburger Sidebar toggle at Dark Mode persistence -->
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