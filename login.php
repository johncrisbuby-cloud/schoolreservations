<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db.php';

$error = "";
$success_msg = "";
$unread_count = 0;

// KUNIN ANG PINAKAHULING NA-APPROVE O NA-REJECT NA STUDENT MULA SA DATABASE PARA SA BELL NOTIFICATION
$notif_stmt = $conn->prepare("SELECT full_name, status FROM users WHERE role = 'student' AND (status = 'Approved' OR status = 'Rejected') ORDER BY id DESC LIMIT 1");
if ($notif_stmt) {
    $notif_stmt->execute();
    $notif_res = $notif_stmt->get_result()->fetch_assoc();
    $notif_stmt->close();

    if ($notif_res) {
        $u_name = htmlspecialchars($notif_res['full_name']);
        if ($notif_res['status'] === 'Approved') {
            $success_msg = "Great news, <b>{$u_name}</b>! Your account has been approved by the Admin. You can now log in.";
            $unread_count = 1;
        } else {
            $success_msg = "Hello <b>{$u_name}</b>, we regret to inform you that your account registration was rejected by the Admin.";
            $unread_count = 1;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_input = trim($_POST['username'] ?? '');
    $password    = trim($_POST['password'] ?? '');

    if (!empty($login_input) && !empty($password)) {
        // Query using username, student_id, or email
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? OR student_id = ? OR email = ? LIMIT 1");
        
        if ($stmt) {
            $stmt->bind_param("sss", $login_input, $login_input, $login_input);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($user = $result->fetch_assoc()) {
                if ($password === $user['password'] || password_verify($password, $user['password'])) {
                    
                    $user_status = $user['status'] ?? 'Approved'; 
                    $role        = strtolower(trim($user['role'] ?? 'student'));
                    $full_name   = $user['full_name'] ?? 'User';

                    // APPROVAL CHECK: Only students require Admin approval
                    if ($role === 'student' && $user_status === 'Pending') {
                        $error = "Hello <b>" . htmlspecialchars($full_name) . "</b>, your account is still pending and awaiting Admin approval.";
                    } elseif ($role === 'student' && $user_status === 'Rejected') {
                        $error = "Hello <b>" . htmlspecialchars($full_name) . "</b>, your account registration has been rejected by the Admin.";
                    } else {
                        // Allow user login (Approved Student, Faculty, or Admin)
                        $_SESSION['user_id']    = $user['id'];
                        $_SESSION['student_id'] = $user['student_id'] ?? $user['faculty_id'] ?? '';
                        $_SESSION['username']   = $user['username'];
                        $_SESSION['full_name']  = $user['full_name'];
                        $_SESSION['role']       = $user['role'];

                        // ROLE-BASED REDIRECTION
                        if ($role === 'admin') { 
                            header("Location: admin_dashboard.php"); 
                        } elseif ($role === 'faculty') {
                            header("Location: faculty_dashboard.php");
                        } else {
                            header("Location: student_dashboard.php");
                        }
                        exit();
                    }
                } else {
                    $error = "Incorrect password.";
                }
            } else {
                $error = "Account not found.";
            }
            $stmt->close();
        } else {
            $error = "Database Error: Please ensure the 'users' table and columns exist.";
        }
    } else {
        $error = "Please fill in all required fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - School Facility Reservation System</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            transition: background-color 0.4s ease, color 0.4s ease, box-shadow 0.4s ease;
        }

        body {
            background: linear-gradient(135deg, #e0eafc 0%, #cfdef3 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            overflow: hidden;
        }

        body.dark-mode {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        }

        .main-container {
            position: relative;
            width: 850px;
            max-width: 95%;
            min-height: 520px;
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(37, 87, 224, 0.15);
            overflow: hidden;
            display: flex;
            animation: fadeIn 0.8s ease-in-out;
        }

        body.dark-mode .main-container {
            background: #1e293b;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.5);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .form-side {
            width: 55%;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            z-index: 1;
        }

        .overlay-side {
            width: 45%;
            background: linear-gradient(135deg, #2557e0 0%, #1a44b8 100%);
            color: #ffffff;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px;
            text-align: center;
            position: relative;
        }

        .overlay-side::before {
            content: '';
            position: absolute;
            width: 200px;
            height: 200px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.08);
            top: -50px;
            right: -50px;
        }

        .overlay-side::after {
            content: '';
            position: absolute;
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.05);
            bottom: -30px;
            left: -30px;
        }

        /* --- NOTIFICATION BELL ICON --- */
        .notif-bell-container {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 48px;
            height: 48px;
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(6px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            z-index: 100;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .notif-bell-container:hover {
            background: rgba(255, 255, 255, 0.35);
            transform: translateY(-2px);
        }

        .notif-bell-container svg {
            width: 22px;
            height: 22px;
            fill: #ffffff;
            pointer-events: none;
        }

        .notif-badge-count {
            position: absolute;
            top: -6px;
            right: -6px;
            background-color: #ef4444;
            color: #ffffff;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 50%;
            box-shadow: 0 2px 6px rgba(239, 68, 68, 0.5);
            pointer-events: none;
        }

        /* --- FLOATING TOAST BOX STYLING --- */
        .toast-box {
            position: fixed;
            top: 30px;
            right: 30px;
            background-color: #ffffff;
            color: #1e293b;
            padding: 16px 22px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            border-left: 5px solid #2557e0;
            z-index: 9999;
            font-size: 13.5px;
            font-weight: 600;
            display: none;
            max-width: 350px;
            animation: slideIn 0.3s ease;
        }

        body.dark-mode .toast-box {
            background-color: #1e293b;
            color: #f1f5f9;
            border-left-color: #3b82f6;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.6);
        }

        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        .logo-placeholder a img {
            width: 70px;
            height: 70px;
            border-radius: 12px;
            margin-bottom: 12px;
            object-fit: contain;
        }

        .logo-title {
            font-size: 16px;
            font-weight: 700;
            color: #333;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 25px;
        }

        body.dark-mode .logo-title {
            color: #f1f5f9;
        }

        .form-group {
            margin-bottom: 18px;
            position: relative;
        }

        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 6px;
            color: #555;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        body.dark-mode .form-group label {
            color: #cbd5e1;
        }

        .form-group input {
            width: 100%;
            padding: 12px 14px;
            font-size: 14px;
            border: 2px solid #e1e6f0;
            border-radius: 10px;
            outline: none;
            transition: all 0.3s ease;
            background-color: #f8fafc;
            color: #1e293b;
        }

        body.dark-mode .form-group input {
            background-color: #0f172a;
            border-color: #334155;
            color: #f1f5f9;
        }

        .form-group input:focus {
            border-color: #2557e0;
            background-color: #ffffff;
            box-shadow: 0 0 0 4px rgba(37, 87, 224, 0.1);
        }

        body.dark-mode .form-group input:focus {
            background-color: #0f172a;
            box-shadow: 0 0 0 4px rgba(37, 87, 224, 0.25);
        }

        .password-container {
            position: relative;
            display: flex;
            align-items: center;
        }

        .password-container input {
            padding-right: 42px;
        }

        .toggle-password {
            position: absolute;
            right: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            color: #888;
            transition: color 0.2s;
        }

        .toggle-password:hover {
            color: #2557e0;
        }

        .btn-submit {
            width: 100%;
            background: linear-gradient(135deg, #2557e0 0%, #1a44b8 100%);
            color: white;
            border: none;
            padding: 13px;
            font-size: 15px;
            font-weight: 700;
            border-radius: 10px;
            cursor: pointer;
            margin-top: 10px;
            box-shadow: 0 4px 12px rgba(37, 87, 224, 0.3);
            transition: all 0.3s ease;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(37, 87, 224, 0.4);
        }

        .btn-overlay {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 30px;
            border: 2px solid #ffffff;
            color: #ffffff;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 700;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .btn-overlay:hover {
            background-color: #ffffff;
            color: #2557e0;
            transform: scale(1.05);
        }

        .overlay-side h2 {
            font-size: 26px;
            margin-bottom: 12px;
            font-weight: 700;
        }

        .overlay-side p {
            font-size: 14px;
            line-height: 1.6;
            opacity: 0.9;
        }

        .auth-footer {
            text-align: center;
            font-size: 13px;
            color: #666;
            margin-top: 20px;
        }

        body.dark-mode .auth-footer {
            color: #94a3b8;
        }

        .auth-footer a {
            color: #2557e0;
            text-decoration: none;
            font-weight: 700;
        }

        .auth-footer a:hover {
            text-decoration: underline;
        }

        .alert {
            padding: 12px 16px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-size: 13px;
            text-align: center;
        }

        .alert-danger {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        /* --- LIGHT / DARK MODE TOGGLE SWITCH --- */
        .theme-switch-container {
            position: absolute;
            top: 20px;
            left: 20px;
            z-index: 10;
        }

        .theme-switch {
            position: relative;
            display: inline-block;
            width: 54px;
            height: 28px;
        }

        .theme-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: rgba(255, 255, 255, 0.3);
            transition: .4s;
            border-radius: 34px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s cubic-bezier(0.68, -0.55, 0.27, 1.55);
            border-radius: 50%;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }

        input:checked + .slider {
            background-color: #ffffff;
            box-shadow: 0 0 15px rgba(255, 255, 255, 0.8);
        }

        input:checked + .slider:before {
            transform: translateX(26px);
            background-color: #2557e0;
        }

        @media (max-width: 768px) {
            .main-container {
                flex-direction: column;
                min-height: auto;
            }
            .form-side, .overlay-side {
                width: 100%;
            }
            .overlay-side {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>

    <!-- Floating Notification Toast Box -->
    <div id="toastNotification" class="toast-box">
        <span id="toastMessage">Walang bagong notification.</span>
    </div>

    <div class="main-container">
        <!-- Form Left Side -->
        <div class="form-side">
            <div class="logo-placeholder" style="text-align: center;">
                <a href=""><img src="logo.webp" alt="logo"></a>
            </div>
            <div class="logo-title" style="text-align: center;">School Facility<br>Reservation System</div>

            <!-- Note: Tinanggal na ang naka-display na success alert dito para malinis ang form -->

            <!-- Error Notification Message (Para sa wrong password o pending status kapag nag-login) -->
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= $error; ?></div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <div class="form-group">
                    <label>Email / User ID</label>
                    <input type="text" name="username" required placeholder="Enter username or ID">
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <div class="password-container">
                        <input type="password" name="password" id="password" required placeholder="Enter password">
                        <span class="toggle-password" id="togglePassword" title="Show/Hide Password">
                            <!-- SVG Open Eye Icon -->
                            <svg id="eyeOpen" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                            <!-- SVG Closed Eye Icon -->
                            <svg id="eyeClosed" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none;">
                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                <line x1="1" y1="1" x2="23" y2="23"></line>
                            </svg>
                        </span>
                    </div>
                </div>

                <button type="submit" class="btn-submit">Log In</button>
            </form>

            <div class="auth-footer">
                <a href="contact_admin.php">Need help? Contact Admin</a>
            </div>
        </div>

        <!-- Overlay Right Side -->
        <div class="overlay-side">
            <!-- Clickable Notification Bell Icon with Red Counter Badge -->
            <div class="notif-bell-container" id="notifBellBtn" title="Click to view notification">
                <svg viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg">
                    <path d="M256 32C150.1 32 64 118.1 64 224v61.5c0 32.7-13 64.1-36.1 87.2l-15.1 15.1C-1.8 403.2 8.3 432 32 432h448c23.7 0 33.8-28.8 19.2-43.2l-15.1-15.1C461 349.6 448 318.2 448 285.5V224c0-105.9-86.1-192-192-192zm64 416h-128c0 35.3 28.7 64 64 64s64-28.7 64-64z"/>
                </svg>
                <?php if ($unread_count > 0): ?>
                    <span class="notif-badge-count" id="notifBadge"><?= $unread_count; ?></span>
                <?php endif; ?>
            </div>

            <!-- Light/Dark Mode Switcher -->
            <div class="theme-switch-container">
                <label class="theme-switch" title="Toggle Light/Dark Mode">
                    <input type="checkbox" id="themeToggleCheckbox">
                    <span class="slider"></span>
                </label>
            </div>

            <h2>Hello, Friend!</h2>
            <p>Don't have an account yet? Register now and start reserving facilities with ease.</p>
            <a href="register.php" class="btn-overlay">Register Here</a>
        </div>
    </div>

    <script>
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        const eyeOpen = document.getElementById('eyeOpen');
        const eyeClosed = document.getElementById('eyeClosed');

        togglePassword.addEventListener('click', function () {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            if (type === 'password') {
                eyeOpen.style.display = 'block';
                eyeClosed.style.display = 'none';
            } else {
                eyeOpen.style.display = 'none';
                eyeClosed.style.display = 'block';
            }
        });

        const themeToggleCheckbox = document.getElementById('themeToggleCheckbox');
        const currentTheme = localStorage.getItem('theme');
        if (currentTheme === 'dark') {
            document.body.classList.add('dark-mode');
            themeToggleCheckbox.checked = true;
        }

        themeToggleCheckbox.addEventListener('change', function () {
            if (this.checked) {
                document.body.classList.add('dark-mode');
                localStorage.setItem('theme', 'dark');
            } else {
                document.body.classList.remove('dark-mode');
                localStorage.setItem('theme', 'light');
            }
        });

        // --- JavaScript para sa Toggle/Auto-Close ng Toast Box at pag-alis ng Badge pagka-click ---
        const notifBellBtn = document.getElementById('notifBellBtn');
        const toastNotification = document.getElementById('toastNotification');
        const toastMessage = document.getElementById('toastMessage');
        const notifBadge = document.getElementById('notifBadge');

        const notificationText = "<?php echo !empty($success_msg) ? addslashes($success_msg) : 'No pending approval notifications at this moment.'; ?>";

        let hideTimeout;

        notifBellBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Kung nakabukas na ang toast box, isara ito (toggle). Kung nakasara, buksan.
            if (toastNotification.style.display === 'block') {
                toastNotification.style.display = 'none';
                clearTimeout(hideTimeout);
                return;
            }

            // Ilagay ang text at ipakita ang toast box
            toastMessage.innerHTML = notificationText;
            toastNotification.style.display = 'block';

            // Automatic na tanggalin/itago ang pulang badge count kapag na-click na ang bell
            if (notifBadge) {
                notifBadge.style.display = 'none';
            }

            // I-clear ang lumang timer para hindi magpatong-patong
            clearTimeout(hideTimeout);

            // Awtomatikong mawawala pagkalipas ng 20 segundo (20000 ms)
            hideTimeout = setTimeout(function() {
                toastNotification.style.display = 'none';
            }, 30000);
        });
    </script>

</body>
</html>