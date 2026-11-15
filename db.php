<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');
$db   = getenv('DB_NAME');

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

function check_login() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: index.php");
        exit();
    }
}
?>
