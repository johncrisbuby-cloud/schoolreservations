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
$user_id_no = $_SESSION['student_id'] ?? '';
$user_role  = $_SESSION['role'] ?? 'Student';
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
    
    if (!empty($sync_res['role'])) {
        $user_role = $sync_res['role'];
        $_SESSION['role'] = $user_role;
    }
    
    $user_profile_pic = $sync_res['profile_pic'] ?? '';
}

$unread_notifs = get_unread_notif_count($conn, $user_id);
$current_page = basename($_SERVER['PHP_SELF']);

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'borrow') {
    $equipment_id   = intval($_POST['equipment_id']);
    $student_name   = trim($_POST['student_name']);
    $student_id     = trim($_POST['student_id']);
    $borrow_date    = $_POST['borrow_date'];
    $return_date    = $_POST['return_date'];
    $today          = date('Y-m-d');

    if (!empty($equipment_id) && !empty($student_name) && !empty($student_id) && !empty($borrow_date) && !empty($return_date)) {
        if ($borrow_date < $today || $return_date < $today) {
            $message = "<div class='alert alert-danger'>Reservation denied! Hindi ka maaaring mag-reserve para sa nakalipas na petsa.</div>";
        } elseif ($return_date < $borrow_date) {
            $message = "<div class='alert alert-danger'>Invalid date range! Ang return date ay dapat kapareho o pagkatapos ng borrow date.</div>";
        } else {
            $check_stmt = $conn->prepare("SELECT name, quantity_available FROM equipment WHERE id = ?");
            $check_stmt->bind_param("i", $equipment_id);
            $check_stmt->execute();
            $res = $check_stmt->get_result()->fetch_assoc();

            if ($res && $res['quantity_available'] > 0) {
                $equipment_name = $res['name'];

                $stmt = $conn->prepare("INSERT INTO borrowings (user_id, equipment_id, equipment_name, student_name, student_id, borrow_date, return_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')");
                
                if (!$stmt) {
                    $stmt = $conn->prepare("INSERT INTO borrowings (equipment_id, equipment_name, student_name, student_id, borrow_date, return_date, status) VALUES (?, ?, ?, ?, ?, ?, 'Pending')");
                    $stmt->bind_param("isssss", $equipment_id, $equipment_name, $student_name, $student_id, $borrow_date, $return_date);
                } else {
                    $stmt->bind_param("iisssss", $user_id, $equipment_id, $equipment_name, $student_name, $student_id, $borrow_date, $return_date);
                }
                
                if ($stmt->execute()) {
                    notify_all_admins(
                        $conn, 
                        "New Equipment Request", 
                        "{$student_name} submitted a borrowing request for the equipment: {$equipment_name}."
                    );

                    $message = "<div class='alert alert-success'>Borrow request for <b>" . htmlspecialchars($equipment_name) . "</b> submitted! Please wait for Admin approval.</div>";
                } else {
                    $message = "<div class='alert alert-danger'>Database Error: " . htmlspecialchars($conn->error) . "</div>";
                }
            } else {
                $message = "<div class='alert alert-danger'>Sorry, this item is currently out of stock.</div>";
            }
        }
    } else {
        $message = "<div class='alert alert-danger'>Please fill in all required fields properly.</div>";
    }
}

$query = "SELECT * FROM equipment ORDER BY id ASC";
$result = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Equipment Borrowing - Student Portal</title>
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
        .nav-item a { display: block; padding: 12px 18px; background-color: transparent; color: #2557e0 !important; border: 1.5px solid #2557e0; text-decoration: none; font-size: 13px; font-weight: 600; border-radius: 22px; text-align: center; transition: all 0.25s ease; }
        .nav-item a:hover, .nav-item a:focus, .nav-item a:active, .nav-item a.active { background-color: #2557e0 !important; color: #ffffff !important; box-shadow: 0 4px 15px rgba(37, 87, 224, 0.3); transform: translateY(-1px); }

        .user-profile { display: flex; align-items: center; gap: 10px; padding: 14px 8px; border-top: 1px solid #f1f5f9; transition: border-color 0.3s; }
        .profile-link { display: flex; align-items: center; gap: 10px; text-decoration: none; color: inherit; flex: 1; overflow: hidden; padding: 6px; border-radius: 8px; transition: background 0.2s; min-width: 0; }
        .profile-link:hover { background-color: #f1f5f9; }
        .user-avatar { width: 38px; height: 38px; border-radius: 50%; border: 1.5px solid #cbd5e1; display: flex; align-items: center; justify-content: center; font-size: 16px; background: #fff; flex-shrink: 0; object-fit: cover; }
        .user-info { font-size: 12px; flex: 1; overflow: hidden; min-width: 0; }
        .user-info .name { font-weight: 700; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; transition: color 0.3s; }
        .user-info .role { font-size: 10px; color: #64748b; font-weight: 500; }
        .btn-logout { color: #ef4444; text-decoration: none; font-size: 12px; font-weight: 700; padding: 7px 12px; border-radius: 8px; border: 1px solid #fee2e2; background-color: #fef2f2; transition: all 0.2s; white-space: nowrap; flex-shrink: 0; }
        .btn-logout:hover { background-color: #ef4444; color: #ffffff; border-color: #ef4444; box-shadow: 0 2px 8px rgba(239, 68, 68, 0.25); }

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
        .action-panel { background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 28px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); margin-bottom: 25px; transition: transform 0.25s ease, box-shadow 0.25s ease, background-color 0.3s, border-color 0.3s; }
        .action-panel:hover { box-shadow: 0 10px 25px rgba(37, 87, 224, 0.08); }
        .action-panel h3 { font-size: 16.5px; color: #0f172a; margin-bottom: 18px; font-weight: 700; transition: color 0.3s; }

        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155; transition: color 0.3s; }
        .form-control { width: 100%; padding: 11px 14px; font-size: 13px; border: 1.5px solid #cbd5e1; border-radius: 10px; outline: none; background-color: #f8fafc; color: #1e293b; transition: all 0.2s ease, background-color 0.3s, color 0.3s, border-color 0.3s; }
        .form-control:focus { border-color: #2557e0; background-color: #ffffff; box-shadow: 0 0 0 4px rgba(37, 87, 224, 0.12); }

        .btn-panel { display: inline-block; background: linear-gradient(135deg, #2557e0 0%, #1a44b8 100%); color: #fff; padding: 11px 22px; border: none; border-radius: 10px; text-decoration: none; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.25s ease; box-shadow: 0 4px 12px rgba(37, 87, 224, 0.25); }
        .btn-panel:hover { background: linear-gradient(135deg, #1d4ed8, #1e40af); box-shadow: 0 6px 18px rgba(37, 87, 224, 0.35); transform: translateY(-1px); }

        .alert { padding: 12px 16px; margin-bottom: 24px; border-radius: 10px; font-size: 13px; font-weight: 500; }
        .alert-success { background-color: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-danger { background-color: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

        .table-wrap { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: separate; border-spacing: 0; text-align: left; }
        .data-table th { font-size: 12px; font-weight: 700; color: #475569; padding: 12px 16px; border-bottom: 1px solid #e2e8f0; background-color: #f8fafc; text-transform: uppercase; letter-spacing: 0.5px; transition: background-color 0.3s, color 0.3s, border-color 0.3s; }
        .data-table td { font-size: 13px; padding: 14px 16px; color: #334155; border-bottom: 1px solid #f1f5f9; transition: color 0.3s, border-color 0.3s; }
        
        .badge { padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; display: inline-block; }
        .bg-available { background-color: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .bg-out { background-color: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

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
        body.dark-mode .form-group label { color: #cbd5e1; }
        body.dark-mode .form-control { background-color: #0f172a; border-color: #334155; color: #f1f5f9; }
        body.dark-mode .data-table th { background-color: #0f172a; color: #cbd5e1; border-bottom-color: #334155; }
        body.dark-mode .data-table td { color: #cbd5e1; border-bottom-color: #334155; }

        @media (max-width: 768px) {
            .top-hero-banner { padding: 30px 20px 30px 75px; }
            .page-container { padding: 0 20px 20px 20px; }
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
                <h1>Equipment Borrowing</h1>
                <p>Submit a request to borrow school equipment for academic use.</p>
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

            <div class="action-panel">
                <h3>Borrowing Request Form</h3>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="borrow">

                    <div class="form-group">
                        <label for="equipment_id">Select Equipment</label>
                        <select name="equipment_id" id="equipment_id" class="form-control" required>
                            <option value="">-- Choose Equipment --</option>
                            <?php 
                            if ($result && $result->num_rows > 0) {
                                $result->data_seek(0);
                                while ($item = $result->fetch_assoc()) {
                                    $disabled = ($item['quantity_available'] <= 0) ? 'disabled' : '';
                                    $stock_info = ($item['quantity_available'] > 0) ? " (Available: {$item['quantity_available']})" : " (Out of Stock)";
                                    echo "<option value='{$item['id']}' {$disabled}>" . htmlspecialchars($item['name']) . "{$stock_info}</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>

                    <div style="display: flex; gap: 16px; flex-wrap: wrap;">
                        <div class="form-group" style="flex: 1; min-width: 240px;">
                            <label for="student_name">Borrower Full Name</label>
                            <input type="text" name="student_name" id="student_name" class="form-control" value="<?= htmlspecialchars($user_name); ?>" required>
                        </div>
                        <div class="form-group" style="flex: 1; min-width: 240px;">
                            <label for="student_id">Student ID / Number</label>
                            <input type="text" name="student_id" id="student_id" class="form-control" value="<?= htmlspecialchars($user_id_no); ?>" required>
                        </div>
                    </div>

                    <div style="display: flex; gap: 16px; flex-wrap: wrap;">
                        <div class="form-group" style="flex: 1; min-width: 240px;">
                            <label for="borrow_date">Borrow Date</label>
                            <input type="date" name="borrow_date" id="borrow_date" class="form-control" value="<?= date('Y-m-d'); ?>" min="<?= date('Y-m-d'); ?>" required>
                        </div>
                        <div class="form-group" style="flex: 1; min-width: 240px;">
                            <label for="return_date">Expected Return Date</label>
                            <input type="date" name="return_date" id="return_date" class="form-control" value="<?= date('Y-m-d'); ?>" min="<?= date('Y-m-d'); ?>" required>
                        </div>
                    </div>

                    <button type="submit" class="btn-panel">Submit Request for Approval</button>
                </form>
            </div>

            <div class="action-panel">
                <h3>Equipment Availability List</h3>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Equipment Name</th>
                                <th>Category</th>
                                <th>Available Stock</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if ($result && $result->num_rows > 0): 
                                $result->data_seek(0);
                                while ($item = $result->fetch_assoc()):
                            ?>
                                <tr>
                                    <td><b><?= htmlspecialchars($item['name']); ?></b></td>
                                    <td><?= htmlspecialchars($item['category'] ?? 'General'); ?></td>
                                    <td><?= htmlspecialchars($item['quantity_available']); ?></td>
                                    <td>
                                        <?php if ($item['quantity_available'] > 0): ?>
                                            <span class="badge bg-available">Available</span>
                                        <?php else: ?>
                                            <span class="badge bg-out">Out of Stock</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php 
                                endwhile; 
                            else: 
                            ?>
                                <tr><td colspan="4" style="text-align: center; color: #64748b;">No equipment records found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
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