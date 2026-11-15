<?php
require_once 'db.php';
require_once 'notif_helper.php'; // Required for sending notifications to admins

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name  = trim($_POST['full_name'] ?? '');
    $student_id = trim($_POST['student_id'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $password   = $_POST['password'] ?? '';
    $confirm_pw = $_POST['confirm_password'] ?? '';

    // Use student_id as username
    $username   = $student_id; 

    if (!empty($full_name) && !empty($student_id) && !empty($email) && !empty($password)) {
        if ($password !== $confirm_pw) {
            $message = "<div class='alert alert-danger'>Passwords do not match.</div>";
        } else {
            // Check if email, student_id, or username already exists
            $check = $conn->prepare("SELECT id FROM users WHERE email = ? OR student_id = ? OR username = ?");
            
            if (!$check) {
                $message = "<div class='alert alert-danger'>Database Error (Check Query): " . htmlspecialchars($conn->error) . "</div>";
            } else {
                $check->bind_param("sss", $email, $student_id, $username);
                $check->execute();
                
                if ($check->get_result()->num_rows > 0) {
                    $message = "<div class='alert alert-danger'>Student ID, Email is already registered.</div>";
                } else {
                    // Hash password
                    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                    
                    // Insert with role='Student' and status='Pending'
                    $stmt = $conn->prepare("INSERT INTO users (full_name, student_id, username, email, password, role, status) VALUES (?, ?, ?, ?, ?, 'Student', 'Pending')");
                    
                    if (!$stmt) {
                        $message = "<div class='alert alert-danger'>Database Error (Insert Query): " . htmlspecialchars($conn->error) . "</div>";
                    } else {
                        $stmt->bind_param("sssss", $full_name, $student_id, $username, $email, $hashed_password);

                        if ($stmt->execute()) {
                            // NOTIFY ALL ADMINS: Send notification alert in English when a new user registers
                            notify_all_admins(
                                $conn,
                                "New User Registration 👤",
                                "{$full_name} has registered a new student account and is awaiting approval."
                            );

                            $message = "<div class='alert alert-success'>Registration successful! Please wait for admin approval before logging in. <a href='login.php'>Go to Login</a></div>";
                        } else {
                            $message = "<div class='alert alert-danger'>Error during registration: " . htmlspecialchars($stmt->error) . "</div>";
                        }
                        $stmt->close();
                    }
                }
                $check->close();
            }
        }
    } else {
        $message = "<div class='alert alert-danger'>Please fill in all required fields.</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - School Facility Reservation System</title>
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
            padding: 20px 0;
        }

        /* Dark mode body state */
        body.dark-mode {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        }

        /* Container & Animation Wrapper */
        .main-container {
            position: relative;
            width: 850px;
            max-width: 95%;
            min-height: 560px;
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

        /* Decorative Background Elements */
        .overlay-side::before {
            content: '';
            position: absolute;
            width: 200px;
            height: 200px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.08);
            top: -50px;
            left: -50px;
        }

        .overlay-side::after {
            content: '';
            position: absolute;
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.05);
            bottom: -30px;
            right: -30px;
        }

        .form-side {
            width: 55%;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            z-index: 1;
        }

        .logo-placeholder a img {
            width: 65px;
            height: 65px;
            border-radius: 12px;
            margin-bottom: 10px;
            object-fit: contain;
        }

        .logo-title {
            font-size: 15px;
            font-weight: 700;
            color: #333;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 20px;
        }

        body.dark-mode .logo-title {
            color: #f1f5f9;
        }

        .form-group {
            margin-bottom: 14px;
        }

        .form-group label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            margin-bottom: 5px;
            color: #555;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        body.dark-mode .form-group label {
            color: #cbd5e1;
        }

        .form-group input {
            width: 100%;
            padding: 10px 12px;
            font-size: 13px;
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

        .btn-submit {
            width: 100%;
            background: linear-gradient(135deg, #2557e0 0%, #1a44b8 100%);
            color: white;
            border: none;
            padding: 12px;
            font-size: 14px;
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

        .btn-submit:active {
            transform: translateY(0);
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

        .alert {
            padding: 10px 14px;
            margin-bottom: 15px;
            border-radius: 8px;
            font-size: 13px;
            text-align: center;
        }

        .alert-success {
            background-color: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-danger {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        /* --- LIGHT / DARK MODE TOGGLE SWITCH WITH GLOW ANIMATION (Nasa Kaliwa/Overlay Side na) --- */
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
            background-color: rgba(255, 255, 255, 0.4);
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

        /* Light Mode On Glow Animation (Kulay Puti na may Glowing Aura) */
        input:checked + .slider {
            background-color: #ffffff;
            box-shadow: 0 0 15px rgba(255, 255, 255, 0.9), 0 0 30px rgba(37, 87, 224, 0.8);
            animation: lightGlow 1.5s infinite alternate;
        }

        input:checked + .slider:before {
            transform: translateX(26px);
            background-color: #2557e0;
            box-shadow: 0 0 10px #ffffff, 0 0 20px #ffffff;
        }

        @keyframes lightGlow {
            0% {
                box-shadow: 0 0 10px rgba(255, 255, 255, 0.6), 0 0 20px rgba(37, 87, 224, 0.6);
            }
            100% {
                box-shadow: 0 0 20px rgba(255, 255, 255, 1), 0 0 35px rgba(37, 87, 224, 1);
            }
        }

        @media (max-width: 768px) {
            .main-container {
                flex-direction: column-reverse;
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

    <div class="main-container">
        <!-- Overlay Left Side (Inilipat dito ang Switcher) -->
        <div class="overlay-side">
            <div class="theme-switch-container">
                <label class="theme-switch" title="Toggle Light/Dark Mode">
                    <input type="checkbox" id="themeToggleCheckbox">
                    <span class="slider"></span>
                </label>
            </div>

            <h2>Welcome Back!</h2>
            <p>To stay connected with us, please log in with your personal account details.</p>
            <a href="login.php" class="btn-overlay">Log In Here</a>
        </div>

        <!-- Form Right Side -->
        <div class="form-side">
            <div class="logo-placeholder" style="text-align: center;">
                <a href=""><img src="logo.webp" alt="logo"></a>
            </div>
            <div class="logo-title" style="text-align: center;">School Facility<br>Reservation System</div>

            <?= $message; ?>

            <form action="register.php" method="POST">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" required placeholder="e.g. Juan Dela Cruz">
                </div>

                <div class="form-group">
                    <label>Student ID Number</label>
                    <input type="text" name="student_id" required placeholder="e.g. 2026-00123">
                </div>

                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" required placeholder="e.g. student@student.edu">
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required placeholder="Create password">
                </div>

                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm_password" required placeholder="Re-enter password">
                </div>

                <button type="submit" class="btn-submit">Register Account</button>
            </form>
        </div>
    </div>

    <script>
        // Dark Mode & Light Glowing Switch Logic
        const themeToggleCheckbox = document.getElementById('themeToggleCheckbox');
        
        // Check saved preference from localStorage
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
    </script>

</body>
</html>
