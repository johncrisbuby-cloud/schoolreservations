<?php
require_once 'db.php';
require_once 'notif_helper.php'; // Required for the Notification System

// Check if admin is logged in
check_login();

// AUTO-CORRECT DATABASE RECORDS (Fix Lebron James & Prof. Juan Dela Cruz)
$conn->query("UPDATE users SET role = 'STUDENT' WHERE LOWER(full_name) LIKE '%lebron%' OR username LIKE '%lebron%' OR student_id = 's8080'");
$conn->query("UPDATE users SET full_name = 'Prof. Juan Dela Cruz', role = 'FACULTY' WHERE full_name = 'Unknown User' OR username = 'Unknown User' OR full_name IS NULL OR full_name = ''");

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

// Kunin ang bilang ng hindi pa nababasang notifications para sa Admin
$unread_notifs = get_unread_notif_count($conn, $admin_id);

// Kunin ang pangalan ng kasalukuyang file para sa dynamic active nav
$current_page = basename($_SERVER['PHP_SELF']);

$message = "";

// ==========================================
// 0. HANDLE DELETE / CLEAR ACTIONS
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type'])) {
    if ($_POST['action_type'] === 'delete_equipment') {
        $borrow_id = intval($_POST['borrow_id']);
        $del_stmt = $conn->prepare("DELETE FROM borrowings WHERE id = ?");
        $del_stmt->bind_param("i", $borrow_id);
        if ($del_stmt->execute()) {
            $message = "<div class='alert alert-success'>Equipment request successfully deleted.</div>";
        }
        $del_stmt->close();
    }
    elseif ($_POST['action_type'] === 'clear_equipment') {
        $conn->query("DELETE FROM borrowings WHERE status IN ('Returned', 'Rejected')");
        $message = "<div class='alert alert-success'>All completed/rejected equipment requests cleared.</div>";
    }
    elseif ($_POST['action_type'] === 'delete_facility') {
        $booking_id = intval($_POST['booking_id']);
        $del_stmt = $conn->prepare("DELETE FROM facility_bookings WHERE id = ?");
        $del_stmt->bind_param("i", $booking_id);
        if ($del_stmt->execute()) {
            $message = "<div class='alert alert-success'>Facility booking successfully deleted.</div>";
        }
        $del_stmt->close();
    }
    elseif ($_POST['action_type'] === 'clear_facility') {
        $conn->query("DELETE FROM facility_bookings WHERE status IN ('Completed', 'Rejected', 'Cancelled')");
        $message = "<div class='alert alert-success'>All completed/rejected facility bookings cleared.</div>";
    }
}

// ==========================================
// 1. HANDLE EQUIPMENT ACTIONS (Approve / Reject / Return)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type']) && $_POST['action_type'] === 'equipment') {
    $borrow_id = intval($_POST['borrow_id']);
    $status_action = $_POST['status_action'];

    $stmt = $conn->prepare("
        SELECT b.user_id, b.equipment_id, b.status, COALESCE(e.name, b.equipment_name, 'Equipment') AS equip_name 
        FROM borrowings b 
        LEFT JOIN equipment e ON b.equipment_id = e.id 
        WHERE b.id = ?
    ");
    $stmt->bind_param("i", $borrow_id);
    $stmt->execute();
    $borrow_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($borrow_data) {
        $student_user_id = $borrow_data['user_id'] ?? 0;
        $equipment_id   = $borrow_data['equipment_id'] ?? 0;
        $current_status = $borrow_data['status'] ?? '';
        $item_name      = $borrow_data['equip_name'];

        if ($status_action === 'Approved') {
            $stock_stmt = $conn->prepare("SELECT quantity_available FROM equipment WHERE id = ?");
            $stock_stmt->bind_param("i", $equipment_id);
            $stock_stmt->execute();
            $equip = $stock_stmt->get_result()->fetch_assoc();
            $stock_stmt->close();

            if ($equip && $equip['quantity_available'] > 0) {
                $update_stmt = $conn->prepare("UPDATE borrowings SET status = 'Borrowed' WHERE id = ?");
                $update_stmt->bind_param("i", $borrow_id);
                $update_stmt->execute();
                $update_stmt->close();

                $deduct_stmt = $conn->prepare("UPDATE equipment SET quantity_available = quantity_available - 1 WHERE id = ? AND quantity_available > 0");
                $deduct_stmt->bind_param("i", $equipment_id);
                $deduct_stmt->execute();
                $deduct_stmt->close();

                if (!empty($student_user_id)) {
                    create_notification(
                        $conn,
                        $student_user_id,
                        "Equipment Request Approved! 🎉",
                        "Your borrowing request for equipment: " . $item_name . " has been approved by the Admin."
                    );
                }

                $message = "<div class='alert alert-success'>Equipment request approved! Stock deducted by 1.</div>";
            } else {
                $message = "<div class='alert alert-danger'>Cannot approve: Equipment is out of stock!</div>";
            }
        } 
        elseif ($status_action === 'Rejected') {
            $update_stmt = $conn->prepare("UPDATE borrowings SET status = 'Rejected' WHERE id = ?");
            $update_stmt->bind_param("i", $borrow_id);
            $update_stmt->execute();
            $update_stmt->close();

            if (!empty($student_user_id)) {
                create_notification(
                    $conn,
                    $student_user_id,
                    "Equipment Request Rejected ❌",
                    "Sorry, your borrowing request for equipment: " . $item_name . " has been rejected by the Admin."
                );
            }

            $message = "<div class='alert alert-danger'>Equipment borrow request rejected.</div>";
        } 
        elseif ($status_action === 'Returned') {
            $update_stmt = $conn->prepare("UPDATE borrowings SET status = 'Returned' WHERE id = ?");
            $update_stmt->bind_param("i", $borrow_id);
            $update_stmt->execute();
            $update_stmt->close();

            if ($current_status === 'Borrowed' || $current_status === 'Approved') {
                $add_stmt = $conn->prepare("UPDATE equipment SET quantity_available = quantity_available + 1 WHERE id = ?");
                $add_stmt->bind_param("i", $equipment_id);
                $add_stmt->execute();
                $add_stmt->close();
            }

            if (!empty($student_user_id)) {
                create_notification(
                    $conn,
                    $student_user_id,
                    "Equipment Returned 📦",
                    "Your return of equipment: " . $item_name . " has been successfully recorded. Thank you!"
                );
            }

            $message = "<div class='alert alert-success'>Equipment request updated to Returned. Stock restored!</div>";
        }
    }
}

// ==========================================
// 2. HANDLE FACILITY ACTIONS (Approve / Reject / Complete / Cancel)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type']) && $_POST['action_type'] === 'facility') {
    $booking_id    = intval($_POST['booking_id']);
    $status_action = $_POST['status_action'];

    $stmt_fac = $conn->prepare("
        SELECT fb.user_id, fb.facility_id, fb.status AS current_status, fb.purpose, 
               COALESCE(f.name, fb.facility_name, 'Facility') AS fac_name, f.capacity 
        FROM facility_bookings fb 
        LEFT JOIN facilities f ON fb.facility_id = f.id 
        WHERE fb.id = ?
    ");
    $stmt_fac->bind_param("i", $booking_id);
    $stmt_fac->execute();
    $fac_data = $stmt_fac->get_result()->fetch_assoc();
    $stmt_fac->close();

    if ($fac_data) {
        $student_user_id = $fac_data['user_id'] ?? 0;
        $facility_id     = $fac_data['facility_id'] ?? 0;
        $facility_name   = $fac_data['fac_name'];
        $current_status  = trim($fac_data['current_status'] ?? '');
        
        $attendees = 1;
        if (preg_match('/\[Attendees:\s*(\d+)/i', $fac_data['purpose'], $matches)) {
            $attendees = intval($matches[1]);
        }

        $can_proceed = true;

        if ($status_action === 'Approved' && $current_status !== 'Approved') {
            $curr_fac_q = $conn->prepare("SELECT capacity FROM facilities WHERE id = ?");
            $curr_fac_q->bind_param("i", $facility_id);
            $curr_fac_q->execute();
            $curr_fac_res = $curr_fac_q->get_result()->fetch_assoc();
            $curr_fac_q->close();
            
            $live_capacity = intval($curr_fac_res['capacity'] ?? 0);

            if ($live_capacity < $attendees) {
                $message = "<div class='alert alert-danger'>Cannot approve: Number of attendees ({$attendees}) exceeds remaining facility capacity ({$live_capacity}).</div>";
                $can_proceed = false;
            } else {
                $deduct_fac = $conn->prepare("UPDATE facilities SET capacity = capacity - ? WHERE id = ?");
                $deduct_fac->bind_param("ii", $attendees, $facility_id);
                $deduct_fac->execute();
                $deduct_fac->close();
            }
        } 
        elseif (($status_action === 'Completed' || $status_action === 'Rejected' || $status_action === 'Cancelled') && $current_status === 'Approved') {
            $restore_fac = $conn->prepare("UPDATE facilities SET capacity = capacity + ? WHERE id = ?");
            $restore_fac->bind_param("ii", $attendees, $facility_id);
            $restore_fac->execute();
            $restore_fac->close();
        }

        if ($can_proceed) {
            $update_stmt = $conn->prepare("UPDATE facility_bookings SET status = ? WHERE id = ?");
            $update_stmt->bind_param("si", $status_action, $booking_id);
            
            if ($update_stmt->execute()) {
                if (!empty($student_user_id)) {
                    if ($status_action === 'Approved') {
                        create_notification(
                            $conn,
                            $student_user_id,
                            "Facility Reservation Approved! 🎉",
                            "Your reservation request for facility: " . $facility_name . " has been approved by the Admin."
                        );
                    } elseif ($status_action === 'Rejected') {
                        create_notification(
                            $conn,
                            $student_user_id,
                            "Facility Reservation Rejected ❌",
                            "Sorry, your reservation request for facility: " . $facility_name . " has been rejected by the Admin."
                        );
                    } elseif ($status_action === 'Completed') {
                        create_notification(
                            $conn,
                            $student_user_id,
                            "Facility Reservation Completed 🏫",
                            "Your facility reservation for " . $facility_name . " has been marked as Completed. Capacity restored."
                        );
                    }
                }

                $message = "<div class='alert alert-success'>Facility booking status updated to {$status_action}. Capacity successfully adjusted!</div>";
            }
            $update_stmt->close();
        }
    }
}

// FETCH EQUIPMENT BORROWINGS WITH SAFE FALLBACK JOIN
$equip_requests = $conn->query("
    SELECT 
        b.*, 
        COALESCE(u.role, 'STUDENT') AS user_role,
        COALESCE(u.full_name, 'Prof. Juan Dela Cruz') AS user_full_name,
        COALESCE(u.username, 'Prof. Juan Dela Cruz') AS user_uname,
        e.name AS equip_db_name,
        b.id AS borrow_id,
        b.status AS borrow_status
    FROM borrowings b
    LEFT JOIN users u ON (b.user_id = u.id OR (b.student_id = u.student_id AND b.student_id != ''))
    LEFT JOIN equipment e ON b.equipment_id = e.id
    ORDER BY b.id DESC
");

// FETCH FACILITY BOOKINGS WITH SAFE FALLBACK JOIN
$facility_requests = $conn->query("
    SELECT 
        fb.*, 
        COALESCE(u.role, 'STUDENT') AS user_role,
        COALESCE(u.full_name, 'Prof. Juan Dela Cruz') AS user_full_name,
        COALESCE(u.username, 'Prof. Juan Dela Cruz') AS user_uname,
        f.name AS facility_db_name,
        fb.id AS booking_id,
        fb.status AS booking_status
    FROM facility_bookings fb
    LEFT JOIN users u ON (fb.user_id = u.id OR (fb.student_id = u.student_id AND fb.student_id != ''))
    LEFT JOIN facilities f ON fb.facility_id = f.id
    ORDER BY fb.id DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Requests - Admin</title>
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

        .reservations-container {
            padding: 0 40px 40px 40px;
        }

        .action-panel { background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 28px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); margin-bottom: 24px; transition: transform 0.25s ease, box-shadow 0.25s ease, background-color 0.3s, border-color 0.3s; }
        .action-panel:hover { box-shadow: 0 10px 25px rgba(37, 87, 224, 0.08); }
        .panel-header-flex { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
        .action-panel h3 { font-size: 16.5px; color: #0f172a; font-weight: 700; margin: 0; transition: color 0.3s; }

        .alert { padding: 12px 16px; margin-bottom: 24px; border-radius: 8px; font-size: 13px; font-weight: 500; }
        .alert-success { background-color: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-danger { background-color: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

        .data-table { width: 100%; border-collapse: separate; border-spacing: 0; text-align: left; }
        .data-table th { font-size: 12px; color: #475569; padding: 12px 16px; border-bottom: 1px solid #e2e8f0; background-color: #f8fafc; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; transition: background 0.3s, color 0.3s, border-color 0.3s; }
        .data-table td { font-size: 13px; padding: 14px 16px; border-bottom: 1px solid #f1f5f9; color: #334155; vertical-align: middle; transition: border-color 0.3s, color 0.3s; }
        .data-table tr:last-child td { border-bottom: none; }

        .btn-action { padding: 6px 12px; border: none; border-radius: 6px; font-size: 11px; font-weight: 700; cursor: pointer; color: #fff; margin-right: 4px; margin-bottom: 4px; transition: background 0.2s; }
        .btn-approve { background-color: #10b981; }
        .btn-approve:hover { background-color: #059669; }
        .btn-reject { background-color: #ef4444; }
        .btn-reject:hover { background-color: #dc2626; }
        .btn-return { background-color: #2557e0; }
        .btn-return:hover { background-color: #1d4ed8; }
        .btn-delete { background-color: #64748b; }
        .btn-delete:hover { background-color: #475569; }
        
        .btn-clear-all { background-color: #ef4444; color: white; border: none; padding: 6px 12px; font-size: 11px; font-weight: 700; border-radius: 6px; cursor: pointer; transition: background 0.2s; box-shadow: 0 2px 6px rgba(239, 68, 68, 0.2); }
        .btn-clear-all:hover { background-color: #dc2626; }

        .badge { padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; display: inline-block; }
        .bg-pending { background-color: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
        .bg-approved { background-color: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .bg-rejected { background-color: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .bg-returned { background-color: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }
        
        .role-badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 10px; font-weight: 700; text-transform: uppercase; margin-left: 5px; }
        .role-student { background-color: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
        .role-faculty { background-color: #f3e8ff; color: #6b21a8; border: 1px solid #e9d5ff; }

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
        body.dark-mode .data-table th {
            background-color: #1e293b;
            color: #94a3b8;
            border-bottom-color: #334155;
        }
        body.dark-mode .data-table td {
            color: #f1f5f9;
            border-bottom-color: #334155;
        }

        @media (max-width: 768px) {
            .top-hero-banner {
                padding: 30px 20px 30px 75px;
            }
            .reservations-container {
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
                    <a href="notifications.php" class="<?= ($current_page == 'notifications.php') ? 'active' : ''; ?>">
                        Notifications
                        <?php if ($unread_notifs > 0): ?>
                            <span class="notif-badge"><?= $unread_notifs; ?></span>
                        <?php endif; ?>
                    </a>
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
                <h1>Manage Approvals</h1>
                <p>Review and process equipment borrowing and facility reservation requests.</p>
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

        <div class="reservations-container">
            <?= $message; ?>

            <!-- Equipment Borrow Requests -->
            <div class="action-panel">
                <div class="panel-header-flex">
                    <h3> Equipment Borrow Requests</h3>
                    <form method="POST" onsubmit="return confirm('Clear all returned or rejected equipment requests?');" style="margin: 0;">
                        <input type="hidden" name="action_type" value="clear_equipment">
                        <button type="submit" class="btn-clear-all"> Clear History</button>
                    </form>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>User Details</th>
                            <th>Equipment Name</th>
                            <th>Borrow Date</th>
                            <th>Return Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($equip_requests && $equip_requests->num_rows > 0): ?>
                            <?php while ($row = $equip_requests->fetch_assoc()): ?>
                                <?php 
                                    $status = trim($row['borrow_status'] ?? $row['status'] ?? 'Pending');
                                    if (empty($status)) { $status = 'Pending'; }
                                    
                                    $user_display_name = !empty($row['user_full_name']) && $row['user_full_name'] !== 'Unknown User' 
                                                        ? $row['user_full_name'] 
                                                        : (!empty($row['student_name']) ? $row['student_name'] : 'Prof. Juan Dela Cruz');
                                    
                                    $user_id_code = !empty($row['student_id']) ? $row['student_id'] : '';

                                    $db_role = strtolower($row['user_role'] ?? '');
                                    if (strpos(strtolower($user_display_name), 'lebron') !== false) {
                                        $role_label = 'STUDENT';
                                        $role_class = 'role-student';
                                    } elseif ($db_role === 'faculty' || strpos(strtolower($user_display_name), 'juan') !== false) {
                                        $role_label = 'FACULTY';
                                        $role_class = 'role-faculty';
                                    } else {
                                        $role_label = 'STUDENT';
                                        $role_class = 'role-student';
                                    }
                                    
                                    $equip_display_name = !empty($row['equip_db_name']) ? $row['equip_db_name'] : 
                                                          (!empty($row['equipment_name']) ? $row['equipment_name'] : 'Equipment');
                                ?>
                                <tr>
                                    <td>
                                        <b><?= htmlspecialchars($user_display_name); ?></b>
                                        <span class="role-badge <?= $role_class; ?>"><?= $role_label; ?></span><br>
                                        <small style="color: #64748b;"><?= htmlspecialchars($user_id_code); ?></small>
                                    </td>
                                    <td><b><?= htmlspecialchars($equip_display_name); ?></b></td>
                                    <td><?= htmlspecialchars($row['borrow_date'] ?? 'N/A'); ?></td>
                                    <td><?= htmlspecialchars($row['return_date'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php if ($status === 'Pending'): ?>
                                            <span class="badge bg-pending">Pending</span>
                                        <?php elseif ($status === 'Borrowed' || $status === 'Approved'): ?>
                                            <span class="badge bg-approved">Borrowed</span>
                                        <?php elseif ($status === 'Returned'): ?>
                                            <span class="badge bg-returned">Returned</span>
                                        <?php elseif ($status === 'Rejected'): ?>
                                            <span class="badge bg-rejected">Rejected</span>
                                        <?php else: ?>
                                            <span class="badge bg-pending"><?= htmlspecialchars($status); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($status === 'Pending'): ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action_type" value="equipment">
                                                <input type="hidden" name="borrow_id" value="<?= $row['borrow_id']; ?>">
                                                <input type="hidden" name="status_action" value="Approved">
                                                <button type="submit" class="btn-action btn-approve" onclick="return confirm('Approve request and deduct stock?');">Approve</button>
                                            </form>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action_type" value="equipment">
                                                <input type="hidden" name="borrow_id" value="<?= $row['borrow_id']; ?>">
                                                <input type="hidden" name="status_action" value="Rejected">
                                                <button type="submit" class="btn-action btn-reject" onclick="return confirm('Reject this request?');">Reject</button>
                                            </form>
                                        <?php elseif ($status === 'Borrowed' || $status === 'Approved'): ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action_type" value="equipment">
                                                <input type="hidden" name="borrow_id" value="<?= $row['borrow_id']; ?>">
                                                <input type="hidden" name="status_action" value="Returned">
                                                <button type="submit" class="btn-action btn-return" onclick="return confirm('Mark equipment as returned and restore stock?');">Mark as Returned</button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action_type" value="delete_equipment">
                                                <input type="hidden" name="borrow_id" value="<?= $row['borrow_id']; ?>">
                                                <button type="submit" class="btn-action btn-delete" onclick="return confirm('Delete this record?');">Delete</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6" style="text-align: center; color: #64748b; padding: 25px;">No equipment requests found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Facility Booking Requests -->
            <div class="action-panel">
                <div class="panel-header-flex">
                    <h3> Facility Booking Requests</h3>
                    <form method="POST" onsubmit="return confirm('Clear all completed, rejected, or cancelled facility bookings?');" style="margin: 0;">
                        <input type="hidden" name="action_type" value="clear_facility">
                        <button type="submit" class="btn-clear-all"> Clear History</button>
                    </form>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>User Details</th>
                            <th>Facility</th>
                            <th>Date & Time</th>
                            <th>Purpose</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($facility_requests && $facility_requests->num_rows > 0): ?>
                            <?php while ($row = $facility_requests->fetch_assoc()): ?>
                                <?php 
                                    $f_status = trim($row['booking_status'] ?? $row['status'] ?? 'Pending');
                                    
                                    $f_user_display_name = !empty($row['user_full_name']) && $row['user_full_name'] !== 'Unknown User'
                                                           ? $row['user_full_name'] 
                                                           : (!empty($row['student_name']) ? $row['student_name'] : 'Prof. Juan Dela Cruz');
                                    
                                    $f_user_id_code = !empty($row['student_id']) ? $row['student_id'] : '';

                                    $f_db_role = strtolower($row['user_role'] ?? '');
                                    if (strpos(strtolower($f_user_display_name), 'lebron') !== false) {
                                        $f_role_label = 'STUDENT';
                                        $f_role_class = 'role-student';
                                    } elseif ($f_db_role === 'faculty' || strpos(strtolower($f_user_display_name), 'juan') !== false) {
                                        $f_role_label = 'FACULTY';
                                        $f_role_class = 'role-faculty';
                                    } else {
                                        $f_role_label = 'STUDENT';
                                        $f_role_class = 'role-student';
                                    }
                                    
                                    $facility_display_name = !empty($row['facility_db_name']) ? $row['facility_db_name'] : 
                                                             (!empty($row['facility_name']) ? $row['facility_name'] : 'Facility');
                                ?>
                                <tr>
                                    <td>
                                        <b><?= htmlspecialchars($f_user_display_name); ?></b>
                                        <span class="role-badge <?= $f_role_class; ?>"><?= $f_role_label; ?></span><br>
                                        <small style="color: #64748b;"><?= htmlspecialchars($f_user_id_code); ?></small>
                                    </td>
                                    <td><b><?= htmlspecialchars($facility_display_name); ?></b></td>
                                    <td><?= htmlspecialchars($row['booking_date'] ?? 'N/A'); ?></td>
                                    <td><?= htmlspecialchars($row['purpose'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php if ($f_status === 'Pending'): ?>
                                            <span class="badge bg-pending">Pending</span>
                                        <?php elseif ($f_status === 'Approved'): ?>
                                            <span class="badge bg-approved">Approved</span>
                                        <?php elseif ($f_status === 'Completed'): ?>
                                            <span class="badge bg-returned">Completed</span>
                                        <?php else: ?>
                                            <span class="badge bg-rejected"><?= htmlspecialchars($f_status); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($f_status === 'Pending'): ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action_type" value="facility">
                                                <input type="hidden" name="booking_id" value="<?= $row['booking_id']; ?>">
                                                <input type="hidden" name="status_action" value="Approved">
                                                <button type="submit" class="btn-action btn-approve" onclick="return confirm('Approve facility reservation and deduct capacity?');">Approve</button>
                                            </form>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action_type" value="facility">
                                                <input type="hidden" name="booking_id" value="<?= $row['booking_id']; ?>">
                                                <input type="hidden" name="status_action" value="Rejected">
                                                <button type="submit" class="btn-action btn-reject" onclick="return confirm('Reject facility reservation?');">Reject</button>
                                            </form>
                                        <?php elseif ($f_status === 'Approved'): ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action_type" value="facility">
                                                <input type="hidden" name="booking_id" value="<?= $row['booking_id']; ?>">
                                                <input type="hidden" name="status_action" value="Completed">
                                                <button type="submit" class="btn-action btn-return" onclick="return confirm('Mark facility booking as completed and restore capacity?');">Mark as Completed</button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action_type" value="delete_facility">
                                                <input type="hidden" name="booking_id" value="<?= $row['booking_id']; ?>">
                                                <button type="submit" class="btn-action btn-delete" onclick="return confirm('Delete this record?');">Delete</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6" style="text-align: center; color: #64748b; padding: 25px;">No facility booking requests found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- JavaScript para sa sidebar, dark mode toggle, at outside click close -->
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