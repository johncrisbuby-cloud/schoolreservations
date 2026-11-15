<?php
// Tanggalin ang session_start() kung active na ito sa db.php o notif_helper.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db.php';
require_once 'notif_helper.php';

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['full_name'] ?? $_SESSION['name'] ?? 'Faculty Member';
$user_role = $_SESSION['role'] ?? 'Faculty';
$user_profile_pic = '';

// SYNC: Kunin ang pinakabagong pangalan, role, at profile picture mula sa database
$stmt_sync = $conn->prepare("SELECT full_name, role, profile_pic FROM users WHERE id = ?");
$stmt_sync->bind_param("i", $user_id);
$stmt_sync->execute();
$sync_res = $stmt_sync->get_result()->fetch_assoc();
$stmt_sync->close();

if ($sync_res) {
    $user_name = $sync_res['full_name'];
    $_SESSION['full_name'] = $user_name;
    
    // Kunin ang totoong role mula sa database kung sakaling mali ang nasa session
    if (!empty($sync_res['role'])) {
        $user_role = $sync_res['role'];
        $_SESSION['role'] = $user_role;
    }
    
    $user_profile_pic = $sync_res['profile_pic'] ?? '';
}

$unread_notifs = get_unread_notif_count($conn, $user_id);
$current_page  = basename($_SERVER['PHP_SELF']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_report'])) {
    $target_type = $_POST['target_type'];
    $target_name = $_POST['target_name'];
    $category    = $_POST['category'];
    $description = $_POST['description'];

    $stmt = $conn->prepare(
        "INSERT INTO reports (user_id, target_type, target_name, category, description, status)
         VALUES (?, ?, ?, ?, ?, 'Pending')"
    );
    
    if ($stmt) {
        $stmt->bind_param("issss", $user_id, $target_type, $target_name, $category, $description);
        $ok = $stmt->execute();
        $stmt->close();

        if ($ok) {
            $notif_title = "New Incident Report 📋";
            $notif_msg = "Faculty member $user_name submitted a new report regarding $target_type ($target_name).";
            notify_all_admins($conn, $notif_title, $notif_msg);
        }
    } else {
        $ok = false;
    }

    header('Location: reports_faculty.php?' . ($ok ? 'sent=1' : 'error=1'));
    exit;
}

$stmt = $conn->prepare("SELECT * FROM reports WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$reports = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Faculty Portal</title>
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
            width: 55px;
            height: 55px;
            object-fit: cover;
            background-color: #2557e0;
            color: #fff;
            font-weight: bold;
            font-size: 24px;
            border-radius: 12px;
            display: block;
            margin: 0 auto 10px auto;
            box-shadow: 0 4px 12px rgba(37, 87, 224, 0.2);
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

        .action-panel {
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 28px;
            margin-bottom: 24px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
            transition: background-color 0.3s, border-color 0.3s;
        }
        .action-panel h3 { font-size: 16.5px; font-weight: 700; color: #0f172a; margin-bottom: 18px; transition: color 0.3s; }

        .report-form { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; }
        .full { grid-column: 1 / -1; }
        
        label { display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155; transition: color 0.3s; }
        select, input, textarea {
            width: 100%; padding: 11px 14px; font-size: 13px; border: 1.5px solid #cbd5e1; border-radius: 10px; outline: none; background-color: #f8fafc; color: #1e293b;
            transition: border-color 0.2s, box-shadow 0.2s, background-color 0.3s, color 0.3s;
        }
        select:focus, input:focus, textarea:focus { border-color: #2557e0; background-color: #fff; box-shadow: 0 0 0 4px rgba(37, 87, 224, 0.12); }
        textarea { min-height: 90px; resize: vertical; }

        .btn-panel {
            background: linear-gradient(135deg, #2557e0 0%, #1a44b8 100%); color: white; border: none; padding: 12px 24px; font-size: 13.5px; font-weight: 700; border-radius: 10px; cursor: pointer; transition: all 0.25s; box-shadow: 0 4px 12px rgba(37, 87, 224, 0.25);
        }
        .btn-panel:hover { background: linear-gradient(135deg, #1d4ed8, #1e40af); box-shadow: 0 6px 18px rgba(37, 87, 224, 0.35); transform: translateY(-1px); }

        .alert { padding: 12px 16px; margin-bottom: 20px; border-radius: 10px; font-size: 13px; font-weight: 500; }
        .alert.ok  { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert.bad { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; text-align: left; }
        th { padding: 12px 16px; border-bottom: 1px solid #e2e8f0; color: #475569; font-weight: 700; font-size: 12px; background: #f8fafc; text-transform: uppercase; letter-spacing: 0.5px; transition: background-color 0.3s, color 0.3s, border-color 0.3s; }
        td { padding: 14px 16px; border-bottom: 1px solid #f1f5f9; color: #334155; transition: color 0.3s, border-color 0.3s; }
        table tr:last-child td { border-bottom: none; }
        
        .badge { display: inline-block; padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; }
        .b-pending { background-color: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
        .b-in-review { background-color: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
        .b-resolved { background-color: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .empty { padding: 24px; color: #64748b; text-align: center; font-style: italic; }

        /* DARK MODE STYLES */
        body.dark-mode { background-color: #0f172a; color: #f1f5f9; }
        body.dark-mode .sidebar { background-color: #1e293b; border-right-color: #334155; }
        body.dark-mode .logo-title { color: #f1f5f9; }
        body.dark-mode .user-profile { border-top-color: #334155; }
        body.dark-mode .profile-link:hover { background-color: #334155; }
        body.dark-mode .user-avatar { background: #0f172a; border-color: #475569; color: #f1f5f9; }
        body.dark-mode .user-info .name { color: #f1f5f9; }
        body.dark-mode .user-info .role { color: #94a3b8; }
        body.dark-mode .main-content { background: #0f172a; }
        body.dark-mode .action-panel { background-color: #1e293b; border-color: #334155; }
        body.dark-mode .action-panel h3 { color: #f1f5f9; }
        body.dark-mode label { color: #cbd5e1; }
        body.dark-mode select, body.dark-mode input, body.dark-mode textarea { background-color: #0f172a; border-color: #334155; color: #f1f5f9; }
        body.dark-mode th { background-color: #1a2234; color: #94a3b8; border-bottom-color: #334155; }
        body.dark-mode td { color: #cbd5e1; border-bottom-color: #334155; }

        @media (max-width: 768px) {
            .top-hero-banner { padding: 30px 20px 30px 75px; }
            .dashboard-container { padding: 0 20px 20px 20px; }
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

    <main class="main-content">
        <!-- Top Hero Banner with Dark Mode & Notif Icons -->
        <div class="top-hero-banner">
            <div class="hero-title">
                <h1>Faculty Reports</h1>
                <p>Report a problem with a facility or equipment and track its status.</p>
            </div>
            <div class="header-actions">
                <button type="button" class="dark-mode-btn" id="darkModeToggle" title="Toggle Dark Mode">
                    <svg viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg">
                        <path d="M256 512C114.6 512 0 397.4 0 256S114.6 0 256 0s256 114.6 256 256-114.6 256-256 256zm0-464v416c114.9 0 208-93.1 208-208S370.9 48 256 48zm0 144a64 64 0 1 0 0 128V192z"/>
                    </svg>
                </button>
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
                <h3>Report an Issue</h3>
                <?php if (isset($_GET['sent'])): ?>
                    <div class="alert ok">Report submitted. An admin will review it soon.</div>
                <?php elseif (isset($_GET['error'])): ?>
                    <div class="alert bad">Couldn't submit your report. Try again.</div>
                <?php endif; ?>

                <form method="POST" class="report-form">
                    <div>
                        <label for="target_type">Report About</label>
                        <select name="target_type" id="target_type" required>
                            <option value="Facility">Facility</option>
                            <option value="Equipment">Equipment</option>
                        </select>
                    </div>
                    <div>
                        <label for="target_name">Facility / Item Name</label>
                        <input type="text" name="target_name" id="target_name" placeholder="Epson LCD Projector" required>
                    </div>
                    <div>
                        <label for="category">Issue Type</label>
                        <select name="category" id="category" required>
                            <option value="Damaged">Damaged</option>
                            <option value="Not working">Not working</option>
                            <option value="Missing parts">Missing parts</option>
                            <option value="Cleanliness">Cleanliness</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="full">
                        <label for="description">What Happened?</label>
                        <textarea name="description" id="description" required placeholder="Describe the problem so staff can fix it."></textarea>
                    </div>
                    <div class="full" style="margin-top: 4px;">
                        <button type="submit" name="submit_report" class="btn-panel">Submit Report</button>
                    </div>
                </form>
            </section>

            <section class="action-panel">
                <h3>My Reports</h3>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Item</th>
                                <th>Issue</th>
                                <th>Details</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($reports->num_rows === 0): ?>
                            <tr><td colspan="6" class="empty">No reports yet. Use the form above when something needs fixing.</td></tr>
                        <?php else: while ($r = $reports->fetch_assoc()):
                            $status_val = !empty($r['status']) ? $r['status'] : 'Pending';
                            $cls = 'b-' . strtolower(str_replace(' ', '-', $status_val)); ?>
                            <tr>
                                <td><?= htmlspecialchars($r['target_type']) ?></td>
                                <td><strong><?= htmlspecialchars($r['target_name']) ?></strong></td>
                                <td><?= htmlspecialchars($r['category']) ?></td>
                                <td><?= htmlspecialchars($r['description']) ?></td>
                                <td><?= date('Y-m-d', strtotime($r['created_at'])) ?></td>
                                <td><span class="badge <?= $cls ?>"><?= htmlspecialchars($status_val) ?></span></td>
                            </tr>
                        <?php endwhile; endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </main>

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