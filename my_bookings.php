<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db.php';
require_once 'notif_helper.php';

check_login();

// Dynamic user session variables
$user_id    = $_SESSION['user_id'] ?? 0;
$user_name  = $_SESSION['full_name'] ?? 'Student User';
$user_id_no = $_SESSION['student_id'] ?? '';
$user_role  = $_SESSION['role'] ?? 'Student';
$user_profile_pic = '';

// SYNC: Kunin ang pinakabagong pangalan, student_id, role, at profile picture direkta mula sa database
$stmt_sync = $conn->prepare("SELECT full_name, student_id, role, profile_pic FROM users WHERE id = ?");
$stmt_sync->bind_param("i", $user_id);
$stmt_sync->execute();
$sync_res = $stmt_sync->get_result()->fetch_assoc();
$stmt_sync->close();

if ($sync_res) {
    $user_name = $sync_res['full_name'];
    $_SESSION['full_name'] = $user_name;
    
    if (!empty($sync_res['student_id'])) {
        $user_id_no = $sync_res['student_id'];
        $_SESSION['student_id'] = $user_id_no;
    }
    
    if (!empty($sync_res['role'])) {
        $user_role = $sync_res['role'];
        $_SESSION['role'] = $user_role;
    }
    
    $user_profile_pic = $sync_res['profile_pic'] ?? '';
}

$unread_notifs = get_unread_notif_count($conn, $user_id);
$current_page  = basename($_SERVER['PHP_SELF']);
$message       = "";

// Handle Actions (Return Equipment, Cancel Facility, Delete History)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'return_equipment') {
        $booking_id   = intval($_POST['booking_id']);
        $equipment_id = intval($_POST['equipment_id']);

        if (!empty($booking_id)) {
            $conn->query("UPDATE borrowings SET status = 'Returned' WHERE id = $booking_id");
            if (!empty($equipment_id)) {
                $conn->query("UPDATE equipment SET quantity_available = quantity_available + 1 WHERE id = $equipment_id");
            }
            $message = "<div class='alert alert-success'>Equipment successfully returned! Stock restored.</div>";
        }
    }
    // Kapag kinansela ang facility booking (Pending pa lang)
    elseif ($_POST['action'] === 'cancel_facility') {
        $facility_booking_id = intval($_POST['booking_id']);

        if (!empty($facility_booking_id)) {
            $chk_q = $conn->prepare("SELECT facility_id, status, purpose FROM facility_bookings WHERE id = ?");
            $chk_q->bind_param("i", $facility_booking_id);
            $chk_q->execute();
            $b_data = $chk_q->get_result()->fetch_assoc();
            $chk_q->close();

            if ($b_data && $b_data['status'] === 'Pending') {
                $fac_id      = intval($b_data['facility_id']);
                $prev_status = $b_data['status'];
                
                $up_stmt = $conn->prepare("UPDATE facility_bookings SET status = 'Cancelled' WHERE id = ?");
                $up_stmt->bind_param("i", $facility_booking_id);
                $up_stmt->execute();
                $up_stmt->close();

                if ($prev_status === 'Approved') {
                    $attendees = 1;
                    if (preg_match('/\[Attendees:\s*(\d+)/i', $b_data['purpose'], $matches)) {
                        $attendees = intval($matches[1]);
                    }

                    $restore_fac = $conn->prepare("UPDATE facilities SET capacity = capacity + ? WHERE id = ?");
                    $restore_fac->bind_param("ii", $attendees, $fac_id);
                    $restore_fac->execute();
                    $restore_fac->close();
                }

                $message = "<div class='alert alert-success'>Facility booking request cancelled.</div>";
            }
        }
    }
    // Kapag binura ang history ng tapos/saradong booking
    elseif ($_POST['action'] === 'delete_facility_history') {
        $facility_booking_id = intval($_POST['booking_id']);
        if (!empty($facility_booking_id)) {
            $del_stmt = $conn->prepare("DELETE FROM facility_bookings WHERE id = ? AND status IN ('Cancelled', 'Rejected', 'Completed')");
            $del_stmt->bind_param("i", $facility_booking_id);
            $del_stmt->execute();
            $del_stmt->close();
            $message = "<div class='alert alert-success'>Booking history record deleted.</div>";
        }
    }
}

// Fetch Equipment Borrowings
$equip_stmt = $conn->prepare("SELECT * FROM borrowings WHERE student_id = ? ORDER BY id DESC");
$equip_stmt->bind_param("s", $user_id_no);
$equip_stmt->execute();
$equip_result = $equip_stmt->get_result();

// Fetch Facility Bookings
$facility_stmt = $conn->prepare("SELECT * FROM facility_bookings WHERE student_id = ? ORDER BY id DESC");
$facility_stmt->bind_param("s", $user_id_no);
$facility_stmt->execute();
$facility_result = $facility_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings - Student Portal</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #f8fafc; display: flex; height: 100vh; overflow: hidden; color: #334155; transition: background-color 0.3s, color 0.3s; }
        
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
        .logo-placeholder a img { width: 60px; height: 60px; background-color: #2557e0; color: #fff; font-weight: bold; font-size: 24px; border-radius: 14px; display: flex; align-items: center; justify-content: center; margin: 0 auto 10px auto; box-shadow: 0 4px 12px rgba(37, 87, 224, 0.25); object-fit: cover; }
        .logo-title { font-size: 11px; font-weight: 700; color: #1e293b; text-transform: uppercase; letter-spacing: 0.6px; transition: color 0.3s; }
        .nav-list { list-style: none; display: flex; flex-direction: column; gap: 10px; }
        
        .nav-item a { display: flex; align-items: center; justify-content: center; gap: 8px; padding: 12px 18px; background-color: transparent; color: #2557e0 !important; border: 1.5px solid #2557e0; text-decoration: none; font-size: 13px; font-weight: 600; border-radius: 22px; text-align: center; transition: all 0.25s ease; position: relative; }
        .nav-item a:hover, .nav-item a:focus, .nav-item a.active { background-color: #2557e0 !important; color: #ffffff !important; box-shadow: 0 4px 15px rgba(37, 87, 224, 0.3); transform: translateY(-1px); }
        
        .nav-item a svg { width: 15px; height: 15px; fill: #2557e0; transition: fill 0.25s ease; }
        .nav-item a:hover svg, .nav-item a.active svg { fill: #ffffff; }

        .notif-badge { background-color: #ef4444; color: #ffffff; font-size: 10px; font-weight: 700; padding: 2px 7px; border-radius: 10px; position: absolute; right: 14px; top: 50%; transform: translateY(-50%); box-shadow: 0 2px 5px rgba(239, 68, 68, 0.3); pointer-events: none; }
        
        .user-profile { display: flex; align-items: center; gap: 10px; padding: 14px 8px; border-top: 1px solid #f1f5f9; transition: border-color 0.3s; }
        .profile-link { display: flex; align-items: center; gap: 10px; text-decoration: none; color: inherit; flex: 1; overflow: hidden; padding: 6px; border-radius: 8px; transition: background 0.2s; min-width: 0; }
        .profile-link:hover { background-color: #f1f5f9; }
        .user-avatar { width: 38px; height: 38px; border-radius: 50%; border: 1.5px solid #cbd5e1; display: flex; align-items: center; justify-content: center; font-size: 16px; background: #fff; flex-shrink: 0; box-shadow: 0 2px 4px rgba(0,0,0,0.02); object-fit: cover; }
        .user-info { font-size: 12px; flex: 1; overflow: hidden; min-width: 0; }
        .user-info .name { font-weight: 700; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; transition: color 0.3s; }
        .user-info .role { font-size: 10px; color: #64748b; font-weight: 500; }
        .btn-logout { color: #ef4444; text-decoration: none; font-size: 12px; font-weight: 700; padding: 7px 12px; border-radius: 8px; border: 1px solid #fee2e2; background-color: #fef2f2; transition: all 0.2s; white-space: nowrap; flex-shrink: 0; }
        .btn-logout:hover { background-color: #ef4444; color: #ffffff; border-color: #ef4444; box-shadow: 0 2px 8px rgba(239, 68, 68, 0.25); }
        
        .main-content { flex: 1; overflow-y: auto; background: #f8fafc; width: 100%; transition: background 0.3s; }
        
        /* Top Banner & Header Actions */
        .top-hero-banner {
            background: linear-gradient(135deg, #2557e0 0%, #1a44b8 100%);
            color: #ffffff; padding: 40px 50px 35px 85px;
            display: flex; justify-content: space-between; align-items: center;
            box-shadow: 0 6px 25px rgba(37, 87, 224, 0.18);
            border-bottom-left-radius: 24px;
            border-bottom-right-radius: 24px;
            margin-bottom: 35px;
            position: relative;
            z-index: 10;
        }
        .header-actions { display: flex; align-items: center; gap: 12px; z-index: 20; }
        .top-notif-btn, .dark-mode-btn {
            position: relative; background-color: rgba(255, 255, 255, 0.15);
            border: 1.5px solid rgba(255, 255, 255, 0.3); width: 48px; height: 48px;
            border-radius: 14px; display: flex; align-items: center; justify-content: center;
            text-decoration: none; backdrop-filter: blur(6px); transition: all 0.25s ease; flex-shrink: 0; cursor: pointer; color: #fff;
        }
        .top-notif-btn:hover, .dark-mode-btn:hover { background-color: rgba(255, 255, 255, 0.25); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .dark-mode-btn svg, .top-notif-btn svg { width: 24px; height: 24px; fill: #ffffff; transition: transform 0.3s ease; pointer-events: none; }
        .dark-mode-btn:hover svg { transform: rotate(180deg); }
        .top-notif-badge { position: absolute; top: -6px; right: -6px; background-color: #ef4444; color: #ffffff; font-size: 10px; font-weight: 700; padding: 2.5px 6.5px; border-radius: 10px; box-shadow: 0 2px 6px rgba(239, 68, 68, 0.4); pointer-events: none; }

        .page-container { padding: 0 40px 40px 40px; }
        .header-title { margin-bottom: 28px; }
        .header-title h1 { font-size: 24px; color: #0f172a; font-weight: 700; transition: color 0.3s; }
        .header-title p { font-size: 13px; color: #64748b; margin-top: 5px; font-weight: 400; }
        .alert { padding: 12px 16px; margin-bottom: 24px; border-radius: 8px; font-size: 13px; font-weight: 500; }
        .alert-success { background-color: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .inventory-box { background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; box-shadow: 0 4px 12px rgba(0,0,0,0.03); margin-bottom: 28px; transition: background-color 0.3s, border-color 0.3s; }
        .table-title { font-size: 16px; font-weight: 700; color: #0f172a; margin-bottom: 16px; transition: color 0.3s; }
        .data-table { width: 100%; border-collapse: separate; border-spacing: 0; text-align: left; }
        .data-table th { font-size: 12px; font-weight: 700; color: #475569; padding: 12px 16px; border-bottom: 1px solid #e2e8f0; background-color: #f8fafc; text-transform: uppercase; letter-spacing: 0.5px; transition: background-color 0.3s, color 0.3s, border-color 0.3s; }
        .data-table td { font-size: 13px; padding: 14px 16px; color: #334155; vertical-align: middle; border-bottom: 1px solid #f1f5f9; transition: color 0.3s, border-color 0.3s; }
        .badge-status { display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; }
        .status-borrowed, .status-pending { background-color: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
        .status-returned, .status-approved { background-color: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .status-cancelled, .status-rejected { background-color: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .btn-danger { background-color: #ef4444; color: white; border: none; padding: 6px 14px; font-size: 12px; font-weight: 700; border-radius: 6px; cursor: pointer; transition: all 0.2s ease; box-shadow: 0 2px 6px rgba(239, 68, 68, 0.2); }
        .btn-danger:hover:not(:disabled) { background-color: #dc2626; }
        .btn-danger:disabled { background-color: #cbd5e1 !important; color: #64748b !important; cursor: not-allowed; box-shadow: none; }

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
        body.dark-mode .header-title h1, body.dark-mode .table-title { color: #f1f5f9; }
        body.dark-mode .inventory-box { background-color: #1e293b; border-color: #334155; }
        body.dark-mode .data-table th { background-color: #0f172a; color: #cbd5e1; border-bottom-color: #334155; }
        body.dark-mode .data-table td { color: #cbd5e1; border-bottom-color: #334155; }

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

    <aside class="sidebar" id="sidebar">
        <div>
            <div class="logo-container">
                <div class="logo-placeholder">
                    <a href="student_dashboard.php"><img src="logo.webp" alt="logo" onerror="this.src='logo.png';"></a>
                </div>
                <div class="logo-title">School Facility<br>Reservation System</div>
            </div>

            <ul class="nav-list">
                <li class="nav-item"><a href="student_dashboard.php" class="<?= ($current_page == 'student_dashboard.php') ? 'active' : ''; ?>">Dashboard</a></li>
                <li class="nav-item"><a href="equipment_student.php" class="<?= ($current_page == 'equipment_student.php') ? 'active' : ''; ?>">Equipment</a></li>
                <li class="nav-item"><a href="facility_student.php" class="<?= ($current_page == 'facility_student.php') ? 'active' : ''; ?>">Facility</a></li>
                <li class="nav-item"><a href="calendar_student.php" class="<?= ($current_page == 'calendar_student.php') ? 'active' : ''; ?>">Calendar</a></li>
                <li class="nav-item"><a href="my_bookings.php" class="<?= ($current_page == 'my_bookings.php') ? 'active' : ''; ?>">My Bookings</a></li>
                <li class="nav-item"><a href="reports.php" class="<?= ($current_page == 'reports.php') ? 'active' : ''; ?>">Reports</a></li>
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
        <!-- Top Hero Banner with Dark Mode and Notification Buttons -->
        <div class="top-hero-banner">
            <div class="hero-title" style="margin-bottom: 0;">
                <h1 style="font-size: 24px; color: #ffffff; font-weight: 700;">My Bookings & Borrowings</h1>
                <p style="font-size: 13px; color: rgba(255,255,255,0.9); margin-top: 5px;">Track your active equipment borrowings and venue reservations.</p>
            </div>
            <div class="header-actions">
                <!-- Dark Mode Button with Custom Icon -->
                <button type="button" class="dark-mode-btn" id="darkModeToggle" title="Toggle Dark Mode">
                    <svg viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg">
                        <path d="M256 512C114.6 512 0 397.4 0 256S114.6 0 256 0s256 114.6 256 256-114.6 256-256 256zm0-464v416c114.9 0 208-93.1 208-208S370.9 48 256 48zm0 144a64 64 0 1 0 0 128V192z"/>
                    </svg>
                </button>

                <!-- Notification Button with Custom Notification Bell Icon -->
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

        <div class="page-container">
            <?= $message; ?>

            <!-- 1. FACILITY RESERVATIONS TABLE -->
            <div class="inventory-box">
                <div class="table-title">🏫 Facility Reservations</div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Facility</th>
                            <th>Booking Date</th>
                            <th>Time Schedule</th>
                            <th>Purpose</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($facility_result && $facility_result->num_rows > 0): ?>
                            <?php while ($row = $facility_result->fetch_assoc()): ?>
                                <tr>
                                    <td><b><?= htmlspecialchars($row['facility_name']); ?></b></td>
                                    <td><?= htmlspecialchars($row['booking_date']); ?></td>
                                    <td><?= date('h:i A', strtotime($row['start_time'])) . ' - ' . date('h:i A', strtotime($row['end_time'])); ?></td>
                                    <td><?= htmlspecialchars($row['purpose']); ?></td>
                                    <td>
                                        <span class="badge-status status-<?= strtolower($row['status']); ?>">
                                            <?= htmlspecialchars($row['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($row['status'] === 'Pending'): ?>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Cancel this reservation?');">
                                                <input type="hidden" name="action" value="cancel_facility">
                                                <input type="hidden" name="booking_id" value="<?= $row['id']; ?>">
                                                <button type="submit" class="btn-danger">Cancel</button>
                                            </form>
                                        <?php elseif ($row['status'] === 'Approved'): ?>
                                            <span style="font-size: 11px; color: #059669; font-weight: 700;">Active / Approved</span>
                                        <?php else: ?>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this record from history?');">
                                                <input type="hidden" name="action" value="delete_facility_history">
                                                <input type="hidden" name="booking_id" value="<?= $row['id']; ?>">
                                                <button type="submit" class="btn-danger" style="background-color: #64748b;">Delete</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6" style="text-align: center; color: #64748b;">No facility reservations found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- 2. EQUIPMENT BORROWINGS TABLE -->
            <div class="inventory-box">
                <div class="table-title">💻 Equipment Borrowings</div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Equipment Item</th>
                            <th>Borrow Date</th>
                            <th>Return Date</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($equip_result && $equip_result->num_rows > 0): ?>
                            <?php while ($row = $equip_result->fetch_assoc()): ?>
                                <tr>
                                    <td><b><?= htmlspecialchars($row['equipment_name']); ?></b></td>
                                    <td><?= htmlspecialchars($row['borrow_date']); ?></td>
                                    <td><?= htmlspecialchars($row['return_date']); ?></td>
                                    <td>
                                        <span class="badge-status status-<?= strtolower($row['status']); ?>">
                                            <?= htmlspecialchars($row['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($row['status'] === 'Borrowed' || $row['status'] === 'Approved'): ?>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Confirm returning this item?');">
                                                <input type="hidden" name="action" value="return_equipment">
                                                <input type="hidden" name="booking_id" value="<?= $row['id']; ?>">
                                                <input type="hidden" name="equipment_id" value="<?= $row['equipment_id']; ?>">
                                                <button type="submit" class="btn-danger">Return Item</button>
                                            </form>
                                        <?php else: ?>
                                            <button class="btn-danger" disabled>Returned</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" style="text-align: center; color: #64748b;">No equipment borrowings found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
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