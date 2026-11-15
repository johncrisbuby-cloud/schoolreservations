<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
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

// Kunin ang pangalan ng kasalukuyang file para sa dynamic active nav
$current_page = basename($_SERVER['PHP_SELF']);

$events = [];

// Fetch Facility Bookings
$facility_query = "SELECT id, facility_name, student_name, booking_date, start_time, end_time, status FROM facility_bookings";
$facility_res = $conn->query($facility_query);

if ($facility_res && $facility_res->num_rows > 0) {
    while ($row = $facility_res->fetch_assoc()) {
        $status = strtolower(trim($row['status'] ?? 'pending'));
        
        $color = '#f59e0b'; 
        $textColor = '#ffffff';

        if ($status === 'approved' || $status === 'active') {
            $color = '#10b981'; 
            $textColor = '#ffffff';
        } elseif ($status === 'rejected') {
            $color = '#ef4444'; 
            $textColor = '#ffffff';
        }

        $events[] = [
            'id'              => 'facility_' . $row['id'],
            'db_id'           => $row['id'],
            'type'            => 'facility',
            'title'           => '🏫 [Facility] ' . ($row['facility_name'] ?? 'Facility') . ' - ' . ($row['student_name'] ?? 'Student') . ' (' . ucfirst($status) . ')',
            'start'           => $row['booking_date'],
            'allDay'          => true,
            'display'         => 'block',
            'backgroundColor' => $color,
            'borderColor'     => $color,
            'textColor'       => $textColor,
            'details'         => "Facility: " . ($row['facility_name'] ?? '') . "\nBorrower/Student: " . ($row['student_name'] ?? '') . "\nDate: " . $row['booking_date'] . "\nStatus: " . ucfirst($status)
        ];
    }
}

// Fetch Equipment Borrowings
$equipment_query = "SELECT b.*, e.name AS equipment_real_name FROM borrowings b LEFT JOIN equipment e ON b.equipment_id = e.id";
$equipment_res = $conn->query($equipment_query);

if ($equipment_res && $equipment_res->num_rows > 0) {
    while ($row = $equipment_res->fetch_assoc()) {
        $status = strtolower(trim($row['status'] ?? 'pending'));
        
        $color = '#f59e0b'; 
        $textColor = '#ffffff';

        if ($status === 'returned') {
            $color = '#64748b'; 
            $textColor = '#ffffff';
        } elseif ($status === 'borrowed' || $status === 'approved' || $status === 'active') {
            $color = '#10b981'; 
            $textColor = '#ffffff';
        } elseif ($status === 'rejected') {
            $color = '#ef4444'; 
            $textColor = '#ffffff';
        }

        $equip_name = $row['equipment_real_name'] ?? $row['equipment_name'] ?? ('Equipment #' . $row['equipment_id']);
        $borrower = $row['student_name'] ?? $row['faculty_name'] ?? 'Student';

        $b_date = $row['borrow_date'];
        $r_date = $row['return_date'] ?? $b_date;
        $extended_return_date = date('Y-m-d', strtotime($r_date . ' +1 day'));

        $events[] = [
            'id'              => 'equipment_' . $row['id'],
            'db_id'           => $row['id'],
            'type'            => 'equipment',
            'title'           => '💻 [Equipment] ' . $equip_name . ' (' . $borrower . ') - ' . ucfirst($status),
            'start'           => $b_date,
            'end'             => $extended_return_date,
            'allDay'          => true,
            'display'         => 'block',
            'backgroundColor' => $color,
            'borderColor'     => $color,
            'textColor'       => $textColor,
            'details'         => "Equipment: " . $equip_name . "\nBorrower/Student: " . $borrower . "\nBorrow Date: " . $b_date . "\nReturn Date: " . $r_date . "\nStatus: " . ucfirst($status)
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student and Faculty Reservation Calendar - Admin Panel</title>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
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
        .logo-placeholder a img { 
            width: 60px; height: 60px; background-color: #2557e0; color: #fff; 
            font-weight: bold; font-size: 24px; border-radius: 14px; 
            display: flex; align-items: center; justify-content: center; 
            margin: 0 auto 10px auto; object-fit: cover;
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

        .calendar-container {
            padding: 0 40px 40px 40px;
        }

        .calendar-box { background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 28px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); transition: background-color 0.3s, border-color 0.3s; }
        .legend-bar { display: flex; gap: 20px; margin-bottom: 20px; font-size: 12px; font-weight: 700; flex-wrap: wrap; color: #475569; align-items: center; transition: color 0.3s; }
        .legend-item { display: flex; align-items: center; gap: 8px; }
        .dot { width: 14px; height: 14px; border-radius: 4px; display: inline-block; }
        
        .fc .fc-toolbar-title { font-size: 18px; font-weight: 700; color: #0f172a; transition: color 0.3s; }
        .fc .fc-button-primary { background-color: #2557e0; border-color: #2557e0; font-size: 12px; font-weight: 600; text-transform: capitalize; border-radius: 8px; }
        .fc .fc-button-primary:hover { background-color: #1d4ed8; border-color: #1d4ed8; }
        .fc .fc-button-active { background-color: #1e40af !important; border-color: #1e40af !important; }

        .alert-success { background-color: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; padding: 12px 16px; border-radius: 8px; font-size: 13px; margin-bottom: 24px; font-weight: 500; }
        
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); align-items: center; justify-content: center; backdrop-filter: blur(4px); }
        .modal-content { background-color: #fff; padding: 28px; border-radius: 14px; width: 380px; box-shadow: 0 10px 25px rgba(0,0,0,0.15); border: 1px solid #e2e8f0; transition: background-color 0.3s, border-color 0.3s, color 0.3s; }
        .modal-content h3 { margin-bottom: 12px; font-size: 16.5px; color: #0f172a; font-weight: 700; transition: color 0.3s; }
        .modal-content p { font-size: 13px; color: #475569; white-space: pre-line; margin-bottom: 20px; line-height: 1.6; transition: color 0.3s; }
        .modal-buttons { display: flex; justify-content: flex-end; gap: 10px; }
        .btn-close { background-color: #64748b; color: white; border: none; padding: 9px 18px; border-radius: 8px; cursor: pointer; font-size: 12px; font-weight: 700; transition: background 0.2s; }
        .btn-close:hover { background-color: #475569; }

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
        body.dark-mode .calendar-box {
            background-color: #1e293b;
            border-color: #334155;
        }
        body.dark-mode .legend-bar {
            color: #94a3b8;
        }
        body.dark-mode .fc .fc-toolbar-title {
            color: #f1f5f9;
        }
        body.dark-mode .fc .fc-col-header-cell-cushion,
        body.dark-mode .fc .fc-daygrid-day-number {
            color: #cbd5e1 !important;
        }
        body.dark-mode .fc .fc-scrollgrid,
        body.dark-mode .fc .fc-scrollgrid td,
        body.dark-mode .fc .fc-scrollgrid th {
            border-color: #334155 !important;
        }
        body.dark-mode .fc .fc-daygrid-day {
            background-color: #1e293b;
        }
        body.dark-mode .fc .fc-day-today {
            background-color: rgba(37, 87, 224, 0.15) !important;
        }
        body.dark-mode .modal-content {
            background-color: #1e293b;
            border-color: #334155;
            color: #f1f5f9;
        }
        body.dark-mode .modal-content h3 {
            color: #f1f5f9;
        }
        body.dark-mode .modal-content p {
            color: #94a3b8;
        }

        @media (max-width: 768px) {
            .top-hero-banner {
                padding: 30px 20px 30px 75px;
            }
            .calendar-container {
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
                <div class="logo-placeholder"><a href="admin_dashboard.php"><img src="logo.webp" alt="logo" onerror="this.src='logo.png';"></a></div>
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
                <h1>Student and Faculty Reservation Calendar</h1>
                <p>Overview of all scheduled facility bookings and equipment borrowings.</p>
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

        <div class="calendar-container">
            <?php if (!empty($message)): ?>
                <div class="alert-success">✅ <?= htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <div class="calendar-box">
                <div class="legend-bar">
                    <div class="legend-item"><span class="dot" style="background:#10b981;"></span> Approved / Active</div>
                    <div class="legend-item"><span class="dot" style="background:#f59e0b;"></span> Pending</div>
                    <div class="legend-item"><span class="dot" style="background:#ef4444;"></span> Rejected</div>
                    <div class="legend-item"><span class="dot" style="background:#64748b;"></span> Returned</div>
                </div>
                <div id="calendar"></div>
            </div>
        </div>
    </main>

    <div id="eventModal" class="modal">
        <div class="modal-content">
            <h3 id="modalTitle">Event Details</h3>
            <p id="modalBody"></p>
            <div class="modal-buttons">
                <button type="button" class="btn-close" onclick="closeModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- JavaScript para sa sidebar, dark mode, at FullCalendar -->
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

        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('calendar');
            var eventData = <?= json_encode($events); ?>;
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,listWeek' },
                eventDisplay: 'block',
                height: 'auto',
                events: eventData,
                eventClick: function(info) {
                    var eventObj = info.event;
                    var props = eventObj.extendedProps;
                    document.getElementById('modalTitle').innerText = eventObj.title;
                    document.getElementById('modalBody').innerText = props.details;
                    document.getElementById('eventModal').style.display = 'flex';
                }
            });
            calendar.render();
        });

        function closeModal() { 
            document.getElementById('eventModal').style.display = 'none'; 
        }

        window.onclick = function(event) {
            var modal = document.getElementById('eventModal');
            if (event.target == modal) { 
                modal.style.display = 'none'; 
            }
        }
    </script>
</body>
</html>