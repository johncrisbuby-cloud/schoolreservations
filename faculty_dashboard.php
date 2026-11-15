<?php
require_once 'db.php';
require_once 'notif_helper.php'; // Required for the Notifications system

check_login();

// Dynamic user session variables para sa Faculty
$user_id    = $_SESSION['user_id'] ?? 0;
$user_name  = $_SESSION['full_name'] ?? 'Faculty Member';
$user_id_no = $_SESSION['faculty_id'] ?? $_SESSION['student_id'] ?? $_SESSION['user_id'] ?? '';
$user_role  = $_SESSION['role'] ?? 'Faculty';
$user_profile_pic = '';

// SYNC: Kunin ang pinakabagong pangalan at profile picture direkta mula sa database
$stmt_sync = $conn->prepare("SELECT full_name, profile_pic FROM users WHERE id = ?");
$stmt_sync->bind_param("i", $user_id);
$stmt_sync->execute();
$sync_res = $stmt_sync->get_result()->fetch_assoc();
$stmt_sync->close();

if ($sync_res) {
    $user_name = $sync_res['full_name'];
    $_SESSION['full_name'] = $user_name;
    $user_profile_pic = $sync_res['profile_pic'] ?? '';
}

// Kunin ang bilang ng hindi pa nababasang notifications
$unread_notifs = get_unread_notif_count($conn, $user_id);
$current_page  = basename($_SERVER['PHP_SELF']);

// Fetch summary metrics para sa Equipment Inventory
$total_items = 0;
$total_stock = 0;
$total_available = 0;
$out_of_stock = 0;

$query = "SELECT * FROM equipment";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $total_items++;
        $total_stock += intval($row['total_quantity']);
        $total_available += intval($row['quantity_available']);
        if (intval($row['quantity_available']) <= 0) {
            $out_of_stock++;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Dashboard - School Facility System</title>
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

        /* Badge Counter styling */
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

        .dashboard-container {
            padding: 0 40px 40px 40px;
        }

        /* Dashboard Metric Cards Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 22px;
            margin-bottom: 35px;
        }

        .dash-card {
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            border-left: 5px solid #2557e0;
            border-radius: 14px;
            padding: 24px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
            transition: transform 0.25s ease, box-shadow 0.25s ease, background-color 0.3s, border-color 0.3s;
        }

        .dash-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 25px rgba(37, 87, 224, 0.08);
        }

        .dash-card.card-success { border-left-color: #10b981; }
        .dash-card.card-danger { border-left-color: #ef4444; }
        .dash-card.card-info { border-left-color: #0ea5e9; }

        .dash-card .card-title {
            font-size: 11px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 10px;
            transition: color 0.3s;
        }

        .dash-card .card-value {
            font-size: 30px;
            font-weight: 800;
            color: #0f172a;
            transition: color 0.3s;
        }

        /* Quick Action Panels */
        .panel-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
        }

        .action-panel {
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 28px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
            transition: transform 0.25s ease, box-shadow 0.25s ease, background-color 0.3s, border-color 0.3s;
        }

        .action-panel:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(37, 87, 224, 0.08);
        }

        .action-panel h3 {
            font-size: 16.5px;
            color: #0f172a;
            margin-bottom: 10px;
            font-weight: 700;
            transition: color 0.3s;
        }

        .action-panel p {
            font-size: 13px;
            color: #64748b;
            margin-bottom: 22px;
            line-height: 1.6;
            transition: color 0.3s;
        }

        .btn-panel {
            display: inline-block;
            background: linear-gradient(135deg, #2557e0 0%, #1a44b8 100%);
            color: #fff;
            padding: 11px 22px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
            transition: all 0.25s ease;
            box-shadow: 0 4px 12px rgba(37, 87, 224, 0.25);
        }

        .btn-panel:hover { 
            background: linear-gradient(135deg, #1d4ed8, #1e40af);
            box-shadow: 0 6px 18px rgba(37, 87, 224, 0.35);
            transform: translateY(-1px);
        }

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
        body.dark-mode .dash-card,
        body.dark-mode .action-panel {
            background-color: #1e293b;
            border-color: #334155;
        }
        body.dark-mode .dash-card .card-title,
        body.dark-mode .action-panel p {
            color: #94a3b8;
        }
        body.dark-mode .dash-card .card-value,
        body.dark-mode .action-panel h3 {
            color: #f1f5f9;
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

    <!-- Sidebar Menu (Faculty Links) -->
    <aside class="sidebar" id="sidebar">
        <div>
            <div class="logo-container">
                <div class="logo-placeholder">
                    <a href="faculty_dashboard.php"><img src="logo.webp" alt="logo" onerror="this.src='logo.png';"></a>
                </div>
                <div class="logo-title">Faculty Portal<br>Facility System</div>
            </div>

            <ul class="nav-list">
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
                    <a href="reports_faculty.php" class="<?= ($current_page == 'reports_faculty.php') ? 'active' : ''; ?>">Reports</a>
                </li>
            </ul>
        </div>

        <!-- User Profile Section with Clickable Profile Link -->
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
        <!-- Blue & White Top Hero Banner with Dark Mode & Notification Icons -->
        <div class="top-hero-banner">
            <div class="hero-title">
                <h1>Welcome, <?= htmlspecialchars($user_name); ?>!</h1>
                <p>Overview of equipment inventory and facility reservations.</p>
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
                    <?php if ($unread_notifs > 0): ?>
                        <span class="top-notif-badge"><?= $unread_notifs; ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </div>

        <div class="dashboard-container">
            <!-- Metric Stat Cards -->
            <div class="dashboard-grid">
                <div class="dash-card">
                    <div class="card-title">Total Equipment Categories</div>
                    <div class="card-value"><?= $total_items; ?></div>
                </div>
                <div class="dash-card card-info">
                    <div class="card-title">Total Inventory Units</div>
                    <div class="card-value"><?= $total_stock; ?></div>
                </div>
                <div class="dash-card card-success">
                    <div class="card-title">Available Units</div>
                    <div class="card-value"><?= $total_available; ?></div>
                </div>
                <div class="dash-card card-danger">
                    <div class="card-title">Out of Stock Items</div>
                    <div class="card-value"><?= $out_of_stock; ?></div>
                </div>
            </div>

            <!-- Navigation Shortcut Panels -->
            <div class="panel-container">
                <div class="action-panel">
                    <h3>💻 Equipment Inventory</h3>
                    <p>Browse available lab laptops, audio gear, and AVR equipment ready to borrow.</p>
                    <a href="equipment_faculty.php" class="btn-panel">View & Borrow Equipment →</a>
                </div>

                <div class="action-panel">
                    <h3>🏫 Facility Reservations</h3>
                    <p>Check facility schedules and request reservations for school rooms and halls.</p>
                    <a href="facility_faculty.php" class="btn-panel">Reserve Facility →</a>
                </div>

                <div class="action-panel">
                    <h3>📋 My Bookings</h3>
                    <p>Track the status of your equipment borrowings and facility reservation requests.</p>
                    <a href="my_bookings_faculty.php" class="btn-panel">View My Bookings →</a>
                </div>

                <div class="action-panel">
                    <h3>📅 Reservation Calendar</h3>
                    <p>View scheduled events, approved reservations, and facility availability in real-time.</p>
                    <a href="calendar_faculty.php" class="btn-panel">Open Calendar →</a>
                </div>
            </div>
        </div>
    </main>

    <!-- JavaScript para sa Sidebar toggle at Dark Mode persistence -->
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
