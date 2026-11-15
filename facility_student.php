<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db.php';
require_once 'notif_helper.php';

// Verify if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id    = $_SESSION['user_id'] ?? 0;
$user_name  = $_SESSION['full_name'] ?? 'Student User';
$user_id_no = $_SESSION['student_id'] ?? $_SESSION['faculty_id'] ?? $_SESSION['user_id'] ?? '';
$user_role  = $_SESSION['role'] ?? 'Student';
$user_profile_pic = '';

// SYNC: Kunin ang pinakabagong pangalan, role, at profile picture direkta mula sa database
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

$unread_notifs = get_unread_notif_count($conn, $user_id);
$current_page  = basename($_SERVER['PHP_SELF']);
$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'book_facility') {
    $facility_id  = intval($_POST['facility_id']);
    $student_name = trim($_POST['student_name'] ?? $_POST['faculty_name'] ?? '');
    $student_id   = trim($_POST['student_id'] ?? $_POST['faculty_id'] ?? '');
    $booking_date = $_POST['booking_date'];
    $start_time   = $_POST['start_time'];
    $end_time     = $_POST['end_time'];
    $attendees    = intval($_POST['attendees'] ?? 1);
    $purpose_text = trim($_POST['purpose']);

    $full_purpose = "[Attendees: {$attendees}] " . $purpose_text;

    $today = date('Y-m-d');
    $max_allowed_time = "18:00";

    if ($booking_date < $today) {
        $message = "<div class='alert alert-danger'>Reservation denied! You cannot book a facility for past dates.</div>";
    } elseif ($start_time > $max_allowed_time || $end_time > $max_allowed_time) {
        $message = "<div class='alert alert-danger'>Reservation denied! Reservations are only allowed up to 6:00 PM.</div>";
    } elseif ($start_time >= $end_time) {
        $message = "<div class='alert alert-danger'>Invalid time range! Start Time must be earlier than End Time.</div>";
    } elseif ($attendees <= 0) {
        $message = "<div class='alert alert-danger'>Please enter a valid number of attendees.</div>";
    } elseif (!empty($facility_id) && !empty($student_name) && !empty($student_id) && !empty($booking_date) && !empty($start_time) && !empty($end_time)) {
        
        $check_stmt = $conn->prepare("SELECT name, capacity, status FROM facilities WHERE id = ?");
        $check_stmt->bind_param("i", $facility_id);
        $check_stmt->execute();
        $res = $check_stmt->get_result()->fetch_assoc();
        $check_stmt->close();

        if (!$res) {
            $message = "<div class='alert alert-danger'>Selected facility does not exist.</div>";
        } elseif ($res['status'] !== 'Available') {
            $message = "<div class='alert alert-danger'>This facility is currently unavailable.</div>";
        } elseif ($attendees > intval($res['capacity'])) {
            $message = "<div class='alert alert-danger'>Error: Number of attendees ({$attendees}) exceeds the maximum facility capacity (" . $res['capacity'] . " persons).</div>";
        } else {
            $conflict_stmt = $conn->prepare("SELECT id FROM facility_bookings WHERE facility_id = ? AND booking_date = ? AND status = 'Approved'");
            $conflict_stmt->bind_param("is", $facility_id, $booking_date);
            $conflict_stmt->execute();
            $conflict_res = $conflict_stmt->get_result();

            if ($conflict_res->num_rows > 0) {
                $message = "<div class='alert alert-danger'>Error: This facility is already approved and reserved on this date. Please choose another date or facility.</div>";
            } else {
                $facility_name = $res['name'];

                $stmt = $conn->prepare("INSERT INTO facility_bookings (user_id, facility_id, facility_name, student_name, student_id, booking_date, start_time, end_time, purpose, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')");
                $stmt->bind_param("iisssssss", $user_id, $facility_id, $facility_name, $student_name, $student_id, $booking_date, $start_time, $end_time, $full_purpose);
                
                if ($stmt->execute()) {
                    notify_all_admins(
                        $conn, 
                        "New Facility Reservation Request 🏛️", 
                        "Student " . htmlspecialchars($student_name) . " submitted a new reservation request for " . htmlspecialchars($facility_name) . " (" . $attendees . " attendees)."
                    );

                    $message = "<div class='alert alert-success'>Successfully submitted reservation request for <b>" . htmlspecialchars($facility_name) . "</b>! Check My Bookings for updates.</div>";
                } else {
                    $message = "<div class='alert alert-danger'>Database Error: " . htmlspecialchars($conn->error) . "</div>";
                }
                $stmt->close();
            }
            $conflict_stmt->close();
        }

    } else {
        $message = "<div class='alert alert-danger'>Please fill in all required fields.</div>";
    }
}

$query = "SELECT * FROM facilities ORDER BY name ASC";
$result = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facility Reservation - Student Portal</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #f1f5f9; display: flex; height: 100vh; overflow: hidden; color: #334155; transition: background-color 0.3s, color 0.3s; }
        
        .menu-toggle {
            position: fixed; top: 22px; left: 22px;
            background: linear-gradient(135deg, #2557e0 0%, #1a44b8 100%);
            border: none; border-radius: 10px; width: 44px; height: 44px;
            cursor: pointer; z-index: 1100; display: flex; align-items: center; justify-content: center;
            box-shadow: 0 4px 12px rgba(37, 87, 224, 0.3); transition: transform 0.2s, box-shadow 0.2s;
        }
        .menu-toggle:hover { transform: scale(1.05); box-shadow: 0 6px 16px rgba(37, 87, 224, 0.4); }
        .menu-toggle span { display: block; width: 22px; height: 2px; background-color: #ffffff; position: relative; }
        .menu-toggle span::before, .menu-toggle span::after { content: ''; position: absolute; width: 22px; height: 2px; background-color: #ffffff; left: 0; }
        .menu-toggle span::before { top: -7px; } .menu-toggle span::after { top: 7px; }

        .sidebar {
            width: 260px; background-color: #ffffff; border-right: 1px solid #e2e8f0;
            display: flex; flex-direction: column; justify-content: space-between;
            padding: 24px 18px; box-shadow: 5px 0 25px rgba(0,0,0,0.06);
            position: fixed; left: -280px; height: 100%; top: 0;
            transition: left 0.35s cubic-bezier(0.16, 1, 0.3, 1), background-color 0.3s, border-color 0.3s; z-index: 1000;
        }
        .sidebar.active { left: 0; }

        .logo-container { text-align: center; margin-bottom: 25px; margin-top: 35px; }
        .logo-placeholder a img { width: 60px; height: 60px; background-color: #2557e0; color: #fff; font-weight: bold; font-size: 24px; border-radius: 14px; display: flex; align-items: center; justify-content: center; margin: 0 auto 10px auto; box-shadow: 0 4px 12px rgba(37, 87, 224, 0.25); object-fit: cover; }
        .logo-title { font-size: 11px; font-weight: 700; color: #1e293b; text-transform: uppercase; letter-spacing: 0.6px; transition: color 0.3s; }
        
        .nav-list { list-style: none; display: flex; flex-direction: column; gap: 10px; }
        .nav-item a { display: block; padding: 12px 18px; background-color: transparent; color: #2557e0 !important; border: 1.5px solid #2557e0; text-decoration: none; font-size: 13px; font-weight: 600; border-radius: 22px; text-align: center; transition: all 0.25s ease; position: relative; }
        .nav-item a:hover, .nav-item a:focus, .nav-item a.active { background-color: #2557e0 !important; color: #ffffff !important; box-shadow: 0 4px 15px rgba(37, 87, 224, 0.3); transform: translateY(-1px); }

        .user-profile { display: flex; align-items: center; gap: 10px; padding: 14px 8px; border-top: 1px solid #f1f5f9; transition: border-color 0.3s; }
        .profile-link { display: flex; align-items: center; gap: 10px; text-decoration: none; color: inherit; flex: 1; overflow: hidden; padding: 6px; border-radius: 8px; transition: background 0.2s; min-width: 0; }
        .profile-link:hover { background-color: #f1f5f9; }
        .user-avatar { width: 38px; height: 38px; border-radius: 50%; border: 1.5px solid #cbd5e1; display: flex; align-items: center; justify-content: center; font-size: 16px; background: #fff; flex-shrink: 0; object-fit: cover; }
        .user-info { font-size: 12px; flex: 1; overflow: hidden; min-width: 0; }
        .user-info .name { font-weight: 700; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; transition: color 0.3s; }
        .user-info .role { font-size: 10px; color: #64748b; font-weight: 500; }
        .btn-logout { color: #ef4444; text-decoration: none; font-size: 12px; font-weight: 700; padding: 7px 12px; border-radius: 8px; border: 1px solid #fee2e2; background-color: #fef2f2; transition: all 0.2s; white-space: nowrap; flex-shrink: 0; }
        .btn-logout:hover { background-color: #ef4444; color: #ffffff; }

        .main-content { flex: 1; overflow-y: auto; background: #f8fafc; width: 100%; transition: background 0.3s; }

        .top-hero-banner {
            background: linear-gradient(135deg, #2557e0 0%, #1a44b8 100%);
            color: #ffffff; padding: 40px 50px 35px 85px;
            display: flex; justify-content: space-between; align-items: center;
            box-shadow: 0 6px 25px rgba(37, 87, 224, 0.18);
            border-bottom-left-radius: 24px; border-bottom-right-radius: 24px;
            margin-bottom: 35px; position: relative; z-index: 10;
        }

        .hero-title h1 { font-size: 28px; font-weight: 700; margin-bottom: 6px; letter-spacing: 0.3px; }
        .hero-title p { font-size: 13.5px; opacity: 0.9; font-weight: 400; }

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
        
        .action-panel { background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 28px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); margin-bottom: 25px; transition: transform 0.25s ease, box-shadow 0.2s ease, background-color 0.3s, border-color 0.3s; }
        .action-panel:hover { box-shadow: 0 10px 25px rgba(37, 87, 224, 0.08); }
        .action-panel h3 { font-size: 16.5px; color: #0f172a; margin-bottom: 18px; font-weight: 700; transition: color 0.3s; }
        
        .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 18px; }
        .full-width { grid-column: 1 / -1; }
        
        label { display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155; transition: color 0.3s; }
        select, input, textarea { width: 100%; padding: 11px 14px; font-size: 13px; border: 1.5px solid #cbd5e1; border-radius: 10px; outline: none; background-color: #f8fafc; color: #1e293b; transition: all 0.2s ease; }
        select:focus, input:focus, textarea:focus { border-color: #2557e0; background-color: #ffffff; box-shadow: 0 0 0 4px rgba(37, 87, 224, 0.12); }
        
        .btn-submit { background: linear-gradient(135deg, #2557e0 0%, #1a44b8 100%); color: white; border: none; padding: 12px 24px; font-size: 13.5px; font-weight: 700; border-radius: 10px; cursor: pointer; box-shadow: 0 4px 12px rgba(37, 87, 224, 0.25); transition: all 0.25s ease; }
        .btn-submit:hover { background: linear-gradient(135deg, #1d4ed8, #1e40af); box-shadow: 0 6px 18px rgba(37, 87, 224, 0.35); transform: translateY(-1px); }
        
        .alert { padding: 12px 16px; border-radius: 10px; font-size: 13px; font-weight: 500; margin-bottom: 24px; }
        .alert-success { background-color: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-danger { background-color: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; text-align: left; }
        th { font-size: 12px; font-weight: 700; color: #475569; padding: 12px 16px; border-bottom: 1px solid #e2e8f0; background-color: #f8fafc; text-transform: uppercase; transition: background-color 0.3s, color 0.3s, border-color 0.3s; }
        td { padding: 14px 16px; border-bottom: 1px solid #f1f5f9; color: #334155; transition: color 0.3s, border-color 0.3s; }
        
        .badge { display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; }
        .badge-available { background-color: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .badge-unavailable { background-color: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .empty { padding: 20px; color: #64748b; text-align: center; }

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
            .page-container { padding: 0 20px 20px 20px; }
            .form-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

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
        <div class="top-hero-banner">
            <div class="hero-title">
                <h1>Facility Reservation</h1>
                <p>Submit a request to reserve school facilities for student activities, projects, and events.</p>
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

        <div class="page-container">
            <?= $message; ?>

            <section class="action-panel">
                <h3>Facility Reservation Form</h3>

                <form action="facility_student.php" method="POST" class="form-grid">
                    <input type="hidden" name="action" value="book_facility">

                    <div class="full-width">
                        <label for="facility_id">Select Facility (with Max Capacity)</label>
                        <select name="facility_id" id="facility_id" required onchange="updateMaxCapacity(this)">
                            <option value="">-- Choose Facility --</option>
                            <?php
                            if ($result && $result->num_rows > 0) {
                                $result->data_seek(0);
                                while ($f = $result->fetch_assoc()) {
                                    $dis = ($f['status'] !== 'Available') ? 'disabled' : '';
                                    $extra = ($f['status'] !== 'Available') ? ' (Unavailable)' : ' (Max Capacity: ' . $f['capacity'] . ' pax)';
                                    echo "<option value='".$f['id']."' data-capacity='".$f['capacity']."' $dis>".htmlspecialchars($f['name'])." - ".htmlspecialchars($f['location'] ?? 'Main Campus')."$extra</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>

                    <div>
                        <label for="student_name">Student Name</label>
                        <input type="text" name="student_name" id="student_name" value="<?= htmlspecialchars($user_name); ?>" required>
                    </div>

                    <div>
                        <label for="student_id">Student ID / Number</label>
                        <input type="text" name="student_id" id="student_id" value="<?= htmlspecialchars($user_id_no); ?>" required>
                    </div>

                    <div>
                        <label for="attendees">Number of Attendees / Participants</label>
                        <input type="number" name="attendees" id="attendees" min="1" value="1" required placeholder="Enter attendees count">
                        <small id="capacityHint" style="color: #64748b; font-size: 11px; margin-top: 3px; display: block;"></small>
                    </div>

                    <div>
                        <label for="booking_date">Reservation Date</label>
                        <input type="date" name="booking_date" id="booking_date" value="<?= date('Y-m-d'); ?>" min="<?= date('Y-m-d'); ?>" required>
                    </div>

                    <div>
                        <label for="start_time">Start Time (Max 6:00 PM)</label>
                        <input type="time" name="start_time" id="start_time" max="18:00" required>
                    </div>

                    <div>
                        <label for="end_time">End Time (Max 6:00 PM)</label>
                        <input type="time" name="end_time" id="end_time" max="18:00" required>
                    </div>

                    <div class="full-width">
                        <label for="purpose">Purpose / Activity Details</label>
                        <textarea name="purpose" id="purpose" rows="2" placeholder="Specify activity or event reason..." required></textarea>
                    </div>

                    <div class="full-width">
                        <button type="submit" class="btn-submit">Submit Request for Approval</button>
                    </div>
                </form>
            </section>

            <section class="action-panel">
                <h3>Facility Availability List</h3>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Facility Name</th>
                                <th>Location</th>
                                <th>Max Capacity</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php 
                                $result->data_seek(0);
                                while ($row = $result->fetch_assoc()): 
                                ?>
                                    <tr>
                                        <td><b><?= htmlspecialchars($row['name']); ?></b></td>
                                        <td><?= htmlspecialchars($row['location'] ?? 'Main Campus'); ?></td>
                                        <td><b><?= htmlspecialchars($row['capacity'] ?? 'N/A'); ?> Persons</b></td>
                                        <td>
                                            <span class="badge <?= ($row['status'] === 'Available') ? 'badge-available' : 'badge-unavailable'; ?>">
                                                <?= htmlspecialchars($row['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="empty">No facilities found in the system.</td>
                                </tr>
                            <?php endif; ?>
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

        function updateMaxCapacity(selectObj) {
            var selectedOption = selectObj.options[selectObj.selectedIndex];
            var capacity = selectedOption.getAttribute('data-capacity');
            var hint = document.getElementById('capacityHint');
            var attendeesInput = document.getElementById('attendees');
            
            if (capacity) {
                attendeesInput.max = capacity;
                hint.innerText = "Max allowed capacity for this facility: " + capacity + " persons.";
            } else {
                attendeesInput.removeAttribute('max');
                hint.innerText = "";
            }
        }
    </script>
</body>
</html>