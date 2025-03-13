<?php
session_start();
require_once 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Prepare and execute query to check user credentials
    $stmt = $conn->prepare("SELECT id, password FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        // Verify the password
        if (password_verify($password, $row['password'])) {
            // Set session variable and redirect to dashboard
            $_SESSION['user_id'] = $row['id'];
            $stmt->close();
            $conn->close();
            header("Location: dashboard.php");
            exit();
        } else {
            // Invalid password
            $error = "Invalid password!";
        }
    } else {
        // User not found
        $error = "User not found!";
    }
    $stmt->close();
    $conn->close();

    // If there's an error, redirect back to login with error message
    header("Location: index.php?error=" . urlencode($error));
    exit();
}
?>