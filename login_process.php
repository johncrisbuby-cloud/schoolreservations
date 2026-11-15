<?php
session_start();
require_once('config.php');

// Tiyaking POST request lamang ang papasok para maiwasan ang direktang pagbukas o loop
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userinput = trim($_POST['userinput'] ?? '');
    $password  = trim($_POST['password'] ?? '');

    if (empty($userinput) || empty($password)) {
        echo "empty_fields";
        exit;
    }

    try {
        $sql = "SELECT * FROM users WHERE username = ? OR student = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$userinput, $userinput]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Secure na pagsusuri ng password gamit ang password_verify
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['firstname'] = $user['firstname'] ?? '';
                $_SESSION['role']      = $user['role'] ?? 'User'; // Mahalaga para sa access control (Admin/User)
                
                echo "success";
                exit;
            } else {
                echo "invalid_password";
                exit;
            }
        } else {
            echo "user_not_found";
            exit;
        }
    } catch (PDOException $e) {
        echo "Database Error: " . $e->getMessage();
        exit;
    }
} else {
    // Kung sakaling direktang binuksan ang file na ito sa browser, i-redirect pabalik sa login page
    header("Location: login.php");
    exit();
}
?>
```[cite: 3]