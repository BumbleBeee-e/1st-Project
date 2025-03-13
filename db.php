<?php
$host = 'localhost';
$db = 'drive_clone';
$user = 'root'; // Change to your MySQL username
$pass = ''; // Change to your MySQL password

try {
    $conn = new mysqli($host, $user, $pass, $db);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>