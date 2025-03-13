<?php
ob_start(); // Start output buffering to prevent any unintended output
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

require_once 'db.php'; // Include database connection

$user_id = $_SESSION['user_id'];

// Ensure uploads directory exists (optional for timer.php, but kept for consistency)
$upload_dir = 'uploads/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Success/Error messages
$messages = [];

// Detect AJAX request with a fallback using a custom POST parameter 'ajax'
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') || (isset($_POST['ajax']) && $_POST['ajax'] == 'true');

// Enable error reporting for debugging (set display_errors to 0 in production)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Handle timer creation (both from modal and new section)
if (isset($_POST['create_timer'])) {
    $timer_name = trim($_POST['timer_name']);
    $study_duration = (int)$_POST['study_duration'] * 60; // Convert minutes to seconds
    $rest_duration = (int)$_POST['rest_duration'] * 60; // Convert minutes to seconds

    if (!empty($timer_name)) {
        $stmt = null; // Initialize $stmt to avoid undefined variable in finally
        try {
            $stmt = $conn->prepare("INSERT INTO timers (user_id, timer_name, study_duration, rest_duration) VALUES (?, ?, ?, ?)");
            if ($stmt === false) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("isii", $user_id, $timer_name, $study_duration, $rest_duration);
            if ($stmt->execute()) {
                $new_timer_id = $conn->insert_id; // Get the ID of the newly inserted timer
                $messages['success'] = "Timer '$timer_name' created successfully!";

                // Return JSON for AJAX requests
                if ($isAjax) {
                    ob_end_clean(); // Clear any previous output
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'timer' => [
                            'id' => $new_timer_id,
                            'timer_name' => $timer_name,
                            'study_duration' => $study_duration,
                            'rest_duration' => $rest_duration
                        ],
                        'message' => $messages['success']
                    ]);
                    exit();
                }
            } else {
                $messages['error'] = "Error creating timer: " . $conn->error;
                if ($isAjax) {
                    ob_end_clean(); // Clear any previous output
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => false,
                        'message' => $messages['error']
                    ]);
                    exit();
                }
            }
        } catch (Exception $e) {
            $messages['error'] = "An error occurred: " . $e->getMessage();
            if ($isAjax) {
                ob_end_clean(); // Clear any previous output
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => $messages['error']
                ]);
                exit();
            }
        } finally {
            if (isset($stmt) && $stmt) {
                $stmt->close();
            }
        }
    } else {
        $messages['error'] = "Timer name cannot be empty!";
        if ($isAjax) {
            ob_end_clean(); // Clear any previous output
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => $messages['error']
            ]);
            exit();
        }
    }
}

// Handle timer deletion
if (isset($_POST['delete_timer'])) {
    $timer_id = (int)$_POST['timer_id'];
    $stmt = $conn->prepare("DELETE FROM timers WHERE id = ? AND user_id = ?");
    if ($stmt) {
        $stmt->bind_param("ii", $timer_id, $user_id);
        if ($stmt->execute()) {
            $messages['success'] = "Timer deleted successfully!";
        } else {
            $messages['error'] = "Error deleting timer: " . $conn->error;
        }
        $stmt->close();
    }
}

// Fetch timers into an array
$timers_array = [];
$stmt = $conn->prepare("SELECT * FROM timers WHERE user_id = ? ORDER BY created_at DESC");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $timers_result = $stmt->get_result();
    while ($timer = $timers_result->fetch_assoc()) {
        $timers_array[] = $timer;
    }
    $stmt->close();
}

// Fetch all folders for the sidebar (nested structure)
function fetchFolders($conn, $user_id, $parent_id = null) {
    $folders = [];
    $stmt = $conn->prepare("SELECT id, folder_name, parent_folder_id FROM lesson_folders WHERE user_id = ? AND parent_folder_id " . ($parent_id !== null ? "= ?" : "IS NULL") . " ORDER BY created_at DESC");
    if ($parent_id !== null) {
        $stmt->bind_param("ii", $user_id, $parent_id);
    } else {
        $stmt->bind_param("i", $user_id);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $row['subfolders'] = fetchFolders($conn, $user_id, $row['id']);
        $folders[] = $row;
    }
    $stmt->close();
    return $folders;
}

$all_folders = fetchFolders($conn, $user_id);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>White Room - Timer</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Roboto', sans-serif;
        }
        body {
            background-color: #1c2526;
            color: #e0e0e0;
            font-size: 16px;
            line-height: 1.6;
        }
        .container {
            display: flex;
            min-height: 100vh;
            width: 100%;
        }
        /* Sidebar Styles */
        .sidebar {
            width: 250px;
            background-color: #252b2d;
            padding: 20px 0;
            border-right: 1px solid #3a3f41;
            height: 100vh;
            position: fixed;
            overflow-y: auto;
            transition: transform 0.3s ease;
            z-index: 1000;
        }
        .sidebar .logo {
            padding: 20px;
            border-bottom: 1px solid #3a3f41;
            margin-bottom: 20px;
        }
        .sidebar .logo span {
            font-size: 24px;
            font-weight: 700;
            color: #ffffff;
            letter-spacing: 1px;
        }
        .sidebar .new-btn {
            background-color: #3a3f41;
            color: #ffffff;
            border: 1px solid #4a4f51;
            border-radius: 25px;
            padding: 10px 20px;
            margin: 0 20px 20px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            transition: background-color 0.3s ease, transform 0.1s ease;
        }
        .sidebar .new-btn:hover {
            background-color: #4a4f51;
            transform: translateY(-1px);
        }
        .sidebar .new-btn:active {
            transform: translateY(1px);
        }
        .sidebar .new-btn::before {
            content: '+';
            font-size: 18px;
            margin-right: 10px;
        }
        .sidebar .menu-item {
            padding: 12px 20px;
            cursor: pointer;
            display: flex;
            align-items: center;
            position: relative;
            color: #b0b0b0;
            font-weight: 400;
            transition: background-color 0.3s ease, color 0.3s ease;
        }
        .sidebar .menu-item:hover {
            background-color: #3a3f41;
            color: #ffffff;
        }
        .sidebar .menu-item.active {
            background-color: #3a3f41;
            color: #8ab4f8;
            font-weight: 500;
        }
        .sidebar .menu-item::before {
            content: '';
            display: inline-block;
            width: 20px;
            height: 20px;
            margin-right: 12px;
            background: url('https://via.placeholder.com/20?text=icon') no-repeat center;
            opacity: 0.7;
        }
        .sidebar .menu-item.my-drive {
            border-bottom: 1px solid #3a3f41;
            font-weight: 500;
            color: #ffffff;
        }
        .sidebar .menu-item.my-drive:hover {
            background-color: #3a3f41;
        }
        .sidebar .menu-item.my-drive.active {
            background-color: #3a3f41;
        }
        .sidebar .menu-item.my-drive::before {
            background: url('https://via.placeholder.com/20?text=drive') no-repeat center;
        }
        .sidebar .menu-item.my-drive::after {
            content: '▼';
            position: absolute;
            right: 20px;
            font-size: 12px;
            transition: transform 0.3s ease;
        }
        .sidebar .menu-item.my-drive.active::after {
            transform: rotate(180deg);
        }
        .sidebar .sub-menu {
            display: none;
            list-style: none;
            margin: 0;
            padding: 0;
            background-color: #2d2e30;
        }
        .sidebar .sub-menu.show {
            display: block;
        }
        .sidebar .sub-menu .folder-item {
            position: relative;
        }
        .sidebar .sub-menu .folder-item .folder-link {
            padding: 10px 20px;
            display: flex;
            align-items: center;
            color: #b0b0b0;
            text-decoration: none;
            font-weight: 400;
            font-size: 14px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            transition: background-color 0.3s ease, color 0.3s ease;
        }
        .sidebar .sub-menu .folder-item .folder-link:hover {
            background-color: #3a3f41;
            color: #ffffff;
        }
        .sidebar .sub-menu .folder-item.active .folder-link {
            background-color: #3a3f41;
            color: #8ab4f8;
        }
        .sidebar .sub-menu .folder-item .folder-link::before {
            content: '';
            display: inline-block;
            width: 16px;
            height: 16px;
            margin-right: 10px;
            background: url('https://via.placeholder.com/20?text=folder') no-repeat center;
            background-size: contain;
            opacity: 0.7;
        }
        .sidebar .sub-menu .folder-item.has-subfolders .folder-link::after {
            content: none;
        }
        .sidebar .sub-menu .subfolder-list {
            display: none;
            list-style: none;
            margin: 0;
            padding: 0;
            background-color: #353638;
        }
        .sidebar .sub-menu .subfolder-list.show {
            display: block;
        }
        .sidebar .sub-menu .subfolder-list .folder-item .folder-link {
            padding-left: calc(20px + 16px);
            font-size: 12px;
        }
        .sidebar .sub-menu .subfolder-list .folder-item .folder-link::before {
            width: 14px;
            height: 14px;
        }
        .sidebar .sub-menu .subfolder-list .folder-item.has-subfolders .folder-link::after {
            content: none;
        }
        .sidebar .sub-menu .subfolder-list .subfolder-list .folder-item .folder-link {
            padding-left: calc(20px + 32px);
        }
        .sidebar .sub-menu .subfolder-list .subfolder-list .subfolder-list .folder-item .folder-link {
            padding-left: calc(20px + 48px);
        }
        .sidebar a {
            color: #b0b0b0;
            text-decoration: none;
            display: flex;
            align-items: center;
            padding: 12px 20px;
            font-weight: 400;
            transition: background-color 0.3s ease, color 0.3s ease;
        }
        .sidebar a:hover {
            background-color: #3a3f41;
            color: #ffffff;
        }
        .sidebar a.active {
            background-color: #3a3f41;
            color: #8ab4f8;
            font-weight: 500;
        }
        .sidebar a::before {
            content: '';
            display: inline-block;
            width: 20px;
            height: 20px;
            margin-right: 12px;
            background: url('https://via.placeholder.com/20?text=icon') no-repeat center;
            opacity: 0.7;
        }
        .sidebar .storage {
            margin-top: 30px;
            padding: 20px;
            font-size: 14px;
            color: #b0b0b0;
        }
        .sidebar .storage-bar {
            background-color: #3a3f41;
            height: 6px;
            border-radius: 3px;
            margin: 10px 0;
            overflow: hidden;
        }
        .sidebar .storage-bar-fill {
            background-color: #8ab4f8;
            height: 100%;
            width: 15%;
            border-radius: 3px;
            transition: width 0.3s ease;
        }
        .sidebar .storage-btn {
            background-color: transparent;
            border: 1px solid #4a4f51;
            border-radius: 25px;
            padding: 10px 20px;
            color: #8ab4f8;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            width: 100%;
            text-align: center;
            transition: background-color 0.3s ease, transform 0.1s ease;
        }
        .sidebar .storage-btn:hover {
            background-color: #4a4f51;
            transform: translateY(-1px);
        }
        .sidebar .storage-btn:active {
            transform: translateY(1px);
        }
        /* Main Content Styles */
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 30px;
            overflow-y: auto;
            background-color: #1c2526;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #3a3f41;
        }
        .header .breadcrumb {
            font-size: 20px;
            font-weight: 500;
            color: #e0e0e0;
        }
        .header .breadcrumb a {
            color: #8ab4f8;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        .header .breadcrumb a:hover {
            color: #6b9eff;
            text-decoration: underline;
        }
        .header .actions {
            display: flex;
            align-items: center;
            gap: 15px;
            position: relative;
        }
        .header .actions button {
            background: none;
            border: none;
            color: #e0e0e0;
            font-size: 24px;
            cursor: pointer;
            padding: 8px;
            border-radius: 50%;
            transition: background-color 0.3s ease, color 0.3s ease;
        }
        .header .actions button:hover {
            background-color: #3a3f41;
            color: #8ab4f8;
        }
        .dropdown {
            display: none;
            position: absolute;
            top: 50px;
            right: 0;
            background-color: #252b2d;
            border: 1px solid #3a3f41;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            z-index: 1000;
            min-width: 200px;
        }
        .dropdown a {
            display: block;
            padding: 12px 20px;
            color: #e0e0e0;
            text-decoration: none;
            font-size: 14px;
            transition: background-color 0.3s ease, color 0.3s ease;
        }
        .dropdown a:hover {
            background-color: #3a3f41;
            color: #8ab4f8;
        }
        .messages {
            margin-bottom: 20px;
        }
        .messages .success {
            background-color: rgba(139, 195, 74, 0.2);
            color: #a5d610;
            padding: 15px;
            border-radius: 8px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .messages .success::before {
            content: '✓';
            font-size: 18px;
        }
        .messages .error {
            background-color: rgba(239, 83, 80, 0.2);
            color: #ff6b6b;
            padding: 15px;
            border-radius: 8px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .messages .error::before {
            content: '✗';
            font-size: 18px;
        }
        .section-title {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #8ab4f8;
        }
        .add-timer-section {
            background-color: #212829;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            border: 1px solid #3a3f41;
        }
        .add-timer-section form {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
        }
        .add-timer-section input[type="text"],
        .add-timer-section input[type="number"] {
            background-color: #252b2d;
            border: 1px solid #3a3f41;
            border-radius: 8px;
            padding: 12px 15px;
            color: #e0e0e0;
            font-size: 14px;
            width: 200px;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .add-timer-section input[type="text"]:focus,
        .add-timer-section input[type="number"]:focus {
            border-color: #8ab4f8;
            box-shadow: 0 0 0 3px rgba(138, 180, 248, 0.2);
            outline: none;
        }
        .add-timer-section button {
            background-color: #8ab4f8;
            border: none;
            border-radius: 8px;
            padding: 12px 20px;
            color: #1c2526;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.1s ease;
        }
        .add-timer-section button:hover {
            background-color: #6b9eff;
            transform: translateY(-1px);
        }
        .add-timer-section button:active {
            transform: translateY(1px);
        }
        .timer-container {
            background-color: #212829;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            border: 1px solid #3a3f41;
        }
        .timer {
            background-color: #252b2d;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .timer:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }
        .timer:last-child {
            margin-bottom: 0;
        }
        .timer h3 {
            font-size: 18px;
            margin-bottom: 8px;
            color: #8ab4f8;
            font-weight: 500;
        }
        .timer p {
            margin: 4px 0;
            font-size: 14px;
            color: #b0b0b0;
        }
        .timer .timer-actions {
            display: flex;
            gap: 10px;
        }
        .timer .timer-actions button {
            border: none;
            border-radius: 8px;
            padding: 10px 15px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.1s ease;
        }
        .timer .timer-actions button:first-child {
            background-color: #8ab4f8;
            color: #1c2526;
        }
        .timer .timer-actions button:first-child:hover {
            background-color: #6b9eff;
            transform: translateY(-1px);
        }
        .timer .timer-actions button:first-child:active {
            transform: translateY(1px);
        }
        .timer .timer-actions button:nth-child(2) {
            background-color: #b0b0b0;
            color: #1c2526;
        }
        .timer .timer-actions button:nth-child(2):hover {
            background-color: #9a9a9a;
            transform: translateY(-1px);
        }
        .timer .timer-actions button:nth-child(2):active {
            transform: translateY(1px);
        }
        .timer .timer-actions button.delete {
            background-color: #ff6b6b;
            color: #ffffff;
        }
        .timer .timer-actions button.delete:hover {
            background-color: #e55a5a;
            transform: translateY(-1px);
        }
        .timer .timer-actions button.delete:active {
            transform: translateY(1px);
        }
        .timer-clock {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: #212829;
            padding: 30px;
            border-radius: 12px;
            text-align: center;
            z-index: 1000;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.4);
            border: 1px solid #3a3f41;
            width: 90%;
            max-width: 600px;
        }
        .timer-clock.active {
            display: block;
        }
        .timer-clock h2 {
            font-size: 28px;
            margin-bottom: 20px;
            color: #8ab4f8;
            font-weight: 500;
        }
        .timer-clock .time-display {
            font-size: 64px;
            font-weight: 700;
            color: #e0e0e0;
            margin-bottom: 20px;
            line-height: 1;
        }
        .timer-clock .glow-blue {
            color: #00bfff;
            text-shadow: 0 0 10px rgba(0, 191, 255, 0.5);
        }
        .timer-clock .glow-green {
            color: #32cd32;
            text-shadow: 0 0 10px rgba(50, 205, 50, 0.5);
        }
        .timer-clock .controls {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-bottom: 20px;
        }
        .timer-clock .controls button {
            border: none;
            border-radius: 8px;
            padding: 12px 20px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.1s ease;
        }
        .timer-clock .controls button#pause-btn {
            background-color: #8ab4f8;
            color: #1c2526;
        }
        .timer-clock .controls button#pause-btn:hover {
            background-color: #6b9eff;
            transform: translateY(-1px);
        }
        .timer-clock .controls button#pause-btn:active {
            transform: translateY(1px);
        }
        .timer-clock .controls button.reset {
            background-color: #b0b0b0;
            color: #1c2526;
        }
        .timer-clock .controls button.reset:hover {
            background-color: #9a9a9a;
            transform: translateY(-1px);
        }
        .timer-clock .controls button.reset:active {
            transform: translateY(1px);
        }
        .timer-clock .controls button.back {
            background-color: #ff6b6b;
            color: #ffffff;
        }
        .timer-clock .controls button.back:hover {
            background-color: #e55a5a;
            transform: translateY(-1px);
        }
        .timer-clock .controls button.back:active {
            transform: translateY(1px);
        }
        .timer-clock .preset-buttons {
            margin-top: 15px;
            display: flex;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .timer-clock .preset-buttons button {
            background-color: #4CAF50;
            border: none;
            border-radius: 8px;
            padding: 8px 15px;
            color: #ffffff;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.1s ease;
        }
        .timer-clock .preset-buttons button:hover {
            background-color: #388e3c;
            transform: translateY(-1px);
        }
        .timer-clock .preset-buttons button:active {
            transform: translateY(1px);
        }
        .modal {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0);
            background-color: #212829;
            color: #e0e0e0;
            padding: 30px;
            border-radius: 12px;
            z-index: 2000;
            text-align: center;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.4);
            border: 1px solid #3a3f41;
            width: 90%;
            max-width: 400px;
            animation: popup 0.5s ease-out forwards;
        }
        .modal.active {
            display: block;
            transform: translate(-50%, -50%) scale(1);
        }
        @keyframes popup {
            from {
                transform: translate(-50%, -50%) scale(0);
                opacity: 0;
            }
            to {
                transform: translate(-50%, -50%) scale(1);
                opacity: 1;
            }
        }
        @keyframes flicker {
            0%, 100% { text-shadow: 0 0 10px #00ffcc, 0 0 20px #00ffcc, 0 0 30px #ff00cc; }
            50% { text-shadow: 0 0 5px #00ffcc, 0 0 15px #00ffcc, 0 0 25px #ff00cc; }
        }
        .modal h3 {
            font-size: 24px;
            margin-bottom: 20px;
            color: #8ab4f8;
            font-weight: 500;
        }
        .modal button {
            background-color: #8ab4f8;
            border: none;
            border-radius: 8px;
            padding: 12px 20px;
            color: #1c2526;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.1s ease;
        }
        .modal button:hover {
            background-color: #6b9eff;
            transform: translateY(-1px);
        }
        .modal button:active {
            transform: translateY(1px);
        }
        .create-timer-modal {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0);
            background-color: #212829;
            color: #e0e0e0;
            padding: 30px;
            border-radius: 12px;
            z-index: 1500;
            text-align: center;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.4);
            border: 1px solid #3a3f41;
            width: 90%;
            max-width: 400px;
            animation: popup 0.5s ease-out forwards;
        }
        .create-timer-modal.active {
            display: block;
            transform: translate(-50%, -50%) scale(1);
        }
        .create-timer-modal h2 {
            font-size: 24px;
            margin-bottom: 20px;
            color: #8ab4f8;
            font-weight: 500;
        }
        .create-timer-modal form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .create-timer-modal input[type="text"],
        .create-timer-modal input[type="number"] {
            background-color: #252b2d;
            border: 1px solid #3a3f41;
            border-radius: 8px;
            padding: 12px 15px;
            color: #e0e0e0;
            font-size: 14px;
            width: 100%;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .create-timer-modal input[type="text"]:focus,
        .create-timer-modal input[type="number"]:focus {
            border-color: #8ab4f8;
            box-shadow: 0 0 0 3px rgba(138, 180, 248, 0.2);
            outline: none;
        }
        .create-timer-modal button {
            background-color: #8ab4f8;
            border: none;
            border-radius: 8px;
            padding: 12px;
            color: #1c2526;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.1s ease;
        }
        .create-timer-modal button:hover {
            background-color: #6b9eff;
            transform: translateY(-1px);
        }
        .create-timer-modal button:active {
            transform: translateY(1px);
        }
        .create-timer-modal .add-another {
            background-color: #4CAF50;
            color: #ffffff;
        }
        .create-timer-modal .add-another:hover {
            background-color: #388e3c;
            transform: translateY(-1px);
        }
        .create-timer-modal .add-another:active {
            transform: translateY(1px);
        }
        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 1400;
        }
        .overlay.active {
            display: block;
        }
        /* Responsive Design */
        .mobile-menu-toggle {
            display: none;
            background: none;
            border: none;
            color: #e0e0e0;
            font-size: 24px;
            cursor: pointer;
            padding: 10px;
        }
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                width: 250px;
            }
            .sidebar.active {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            .mobile-menu-toggle {
                display: block;
                position: fixed;
                top: 20px;
                left: 20px;
                z-index: 1001;
            }
            .header .breadcrumb {
                font-size: 18px;
            }
            .add-timer-section form {
                flex-direction: column;
            }
            .add-timer-section input[type="text"],
            .add-timer-section input[type="number"] {
                width: 100%;
            }
            .add-timer-section button {
                width: 100%;
            }
            .timer {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            .timer .timer-actions {
                width: 100%;
                justify-content: space-between;
            }
            .timer .timer-actions button {
                flex: 1;
                padding: 10px;
            }
            .timer-clock {
                padding: 20px;
            }
            .timer-clock .time-display {
                font-size: 48px;
            }
            .timer-clock .controls button {
                padding: 10px 15px;
                font-size: 14px;
            }
            .timer-clock .preset-buttons button {
                padding: 6px 12px;
                font-size: 12px;
            }
            .modal,
            .create-timer-modal {
                padding: 20px;
                width: 90%;
                max-width: 350px;
            }
            .modal h3,
            .create-timer-modal h2 {
                font-size: 20px;
            }
        }
        @media (max-width: 480px) {
            .header .breadcrumb {
                font-size: 16px;
            }
            .header .actions button {
                font-size: 20px;
                padding: 6px;
            }
            .dropdown {
                top: 40px;
                min-width: 150px;
            }
            .dropdown a {
                padding: 10px 15px;
                font-size: 12px;
            }
            .section-title {
                font-size: 20px;
            }
            .timer h3 {
                font-size: 16px;
            }
            .timer p {
                font-size: 12px;
            }
            .timer .timer-actions button {
                padding: 8px;
                font-size: 12px;
            }
            .timer-clock .time-display {
                font-size: 36px;
            }
            .timer-clock .controls button {
                padding: 8px 12px;
                font-size: 12px;
            }
            .timer-clock .preset-buttons button {
                padding: 6px 10px;
                font-size: 10px;
            }
        }
    </style>
</head>
<body>
    <button class="mobile-menu-toggle">☰</button>
    <div class="container">
        <div class="sidebar">
            <div class="logo">
                <span>WHITE ROOM</span>
            </div>
            <button class="new-btn" onclick="showCreateTimerModal()">New Timer</button>
            <div class="menu-item my-drive" title="Lessons">Lessons</div>
            <ul class="sub-menu">
                <?php
                function renderFolderTree($folders, $conn, $user_id) {
                    foreach ($folders as $folder) {
                        $is_active = ''; // No active folder highlighting in timer sidebar
                        $has_subfolders = !empty($folder['subfolders']);
                        echo "<li class='folder-item {$is_active} " . ($has_subfolders ? 'has-subfolders' : '') . "'>";
                        echo "<a href='lessons_repository.php?page=lessons&folder_id={$folder['id']}' class='folder-link'>" . htmlspecialchars($folder['folder_name']) . "</a>";
                        if ($has_subfolders) {
                            echo "<ul class='subfolder-list'>";
                            renderFolderTree($folder['subfolders'], $conn, $user_id);
                            echo "</ul>";
                        }
                        echo "</li>";
                    }
                }
                renderFolderTree($all_folders, $conn, $user_id);
                ?>
            </ul>
            <a href="dashboard.php?page=dashboard" class="<?php echo isset($_GET['page']) && $_GET['page'] == 'dashboard' ? 'active' : ''; ?>">Home</a>
            <a href="lessons_repository.php?page=lessons" class="<?php echo isset($_GET['page']) && $_GET['page'] == 'lessons' ? 'active' : ''; ?>">Lessons Repository</a>
            <a href="timer.php" class="active">Timer</a>
            <a href="notes.php?page=notes" class="<?php echo isset($_GET['page']) && $_GET['page'] == 'notes' ? 'active' : ''; ?>">Notes</a>
            <a href="review.php?page=review" class="<?php echo isset($_GET['page']) && $_GET['page'] == 'review' ? 'active' : ''; ?>">Review</a>
            <a href="reviewer_notes.php?page=reviewer_notes" class="<?php echo isset($_GET['page']) && $_GET['page'] == 'reviewer_notes' ? 'active' : ''; ?>">Reviewer Notes</a>
            <a href="schedule.php?page=schedule" class="<?php echo isset($_GET['page']) && $_GET['page'] == 'schedule' ? 'active' : ''; ?>">Schedule</a>
            <a href="progress.php?page=progress" class="<?php echo isset($_GET['page']) && $_GET['page'] == 'progress' ? 'active' : ''; ?>">Progress Tracker</a>
            <a href="logout.php">Logout</a>
            <div class="storage">
                <p>2.24 GB of 15 GB used</p>
                <div class="storage-bar">
                    <div class="storage-bar-fill"></div>
                </div>
                <button class="storage-btn">Get more storage</button>
            </div>
        </div>
        <div class="main-content">
            <div class="header">
                <div class="breadcrumb">
                    <a href="timer.php">Timer</a>
                </div>
                <div class="actions">
                    <button class="menu-btn">☰</button>
                    <div class="dropdown">
                        <a href="logout.php">Logout</a>
                        <a href="change_account.php">Change Account</a>
                        <a href="index.php?new_login=true">Sign in to Another Account</a>
                    </div>
                    <button class="share-btn">🖼️</button>
                    <button class="edit-profile-btn">ℹ️</button>
                </div>
            </div>

            <?php if (!empty($messages) && !$isAjax): ?>
                <div class="messages">
                    <?php if (isset($messages['success'])): ?>
                        <div class="success"><?php echo $messages['success']; ?></div>
                    <?php endif; ?>
                    <?php if (isset($messages['error'])): ?>
                        <div class="error"><?php echo $messages['error']; ?></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Add Timer Section -->
            <div class="section-title">Add a New Timer</div>
            <div class="add-timer-section">
                <form id="add-timer-form" method="POST">
                    <input type="text" name="timer_name" placeholder="Timer Title" required>
                    <input type="number" name="study_duration" placeholder="Study Duration (minutes)" required min="1">
                    <input type="number" name="rest_duration" placeholder="Rest Duration (minutes)" required min="1">
                    <button type="submit" name="create_timer">Add Timer</button>
                </form>
            </div>

            <!-- Timers Section -->
            <div class="section-title">Timers</div>
            <div class="timer-container" id="timer-container">
                <?php if (count($timers_array) > 0): ?>
                    <?php foreach ($timers_array as $timer): ?>
                        <div class="timer" id="timer-<?php echo $timer['id']; ?>">
                            <div>
                                <h3><?php echo htmlspecialchars($timer['timer_name']); ?></h3>
                                <p>Study: <span id="study-time-<?php echo $timer['id']; ?>"><?php echo $timer['study_duration']; ?></span> seconds</p>
                                <p>Rest: <span id="rest-time-<?php echo $timer['id']; ?>"><?php echo $timer['rest_duration']; ?></span> seconds</p>
                            </div>
                            <div class="timer-actions">
                                <button onclick="startTimer(<?php echo $timer['id']; ?>, <?php echo $timer['study_duration']; ?>, <?php echo $timer['rest_duration']; ?>, '<?php echo htmlspecialchars($timer['timer_name']); ?>')">Start</button>
                                <button onclick="stopTimer(<?php echo $timer['id']; ?>)">Stop</button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this timer?');">
                                    <input type="hidden" name="timer_id" value="<?php echo $timer['id']; ?>">
                                    <button type="submit" name="delete_timer" class="delete">Delete</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="timer">
                        <div>No timers created yet.</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Timer Clock UI -->
    <div class="timer-clock" id="timer-clock">
        <h2 id="timer-title"></h2>
        <div class="time-display" id="clock-time"></div>
        <div class="controls">
            <button onclick="pauseTimer()" id="pause-btn">Pause</button>
            <button class="reset" onclick="resetTimer()">Reset</button>
            <button class="back" onclick="hideClock()">Back</button>
        </div>
        <div class="preset-buttons">
            <button onclick="setPreset(1)">1s</button>
            <button onclick="setPreset(5)">5s</button>
            <button onclick="setPreset(10)">10s</button>
            <button onclick="setPreset(60)">1m</button>
        </div>
    </div>

    <!-- Modal for Pomodoro Switch -->
    <div class="modal" id="pomodoro-modal">
        <h3 id="modal-message"></h3>
        <button onclick="closeModal()">OK</button>
    </div>

    <!-- Create Timer Modal -->
    <div class="create-timer-modal" id="create-timer-modal">
        <h2>Create New Timer</h2>
        <form id="create-timer-form" method="POST">
            <input type="text" name="timer_name" placeholder="Timer Title" required>
            <input type="number" name="study_duration" placeholder="Study Duration (minutes)" required min="1">
            <input type="number" name="rest_duration" placeholder="Rest Duration (minutes)" required min="1">
            <button type="submit" name="create_timer">Create Timer</button>
            <button type="button" class="add-another" onclick="addAnotherTimer()">Add Another Timer</button>
        </form>
    </div>

    <!-- Overlay for Modals -->
    <div class="overlay" id="overlay"></div>

    <script>
        let timers = {};
        let activeTimerId = null;
        let isPaused = false;
        let isStudying = true;
        let originalStudyTime = 0;
        let originalRestTime = 0;

        function startTimer(id, studyDuration, restDuration, timerName) {
            if (timers[id]) clearInterval(timers[id]);
            let studyTime = studyDuration;
            let restTime = restDuration;
            activeTimerId = id;
            originalStudyTime = studyDuration;
            originalRestTime = restDuration;

            const clock = document.getElementById('timer-clock');
            const title = document.getElementById('timer-title');
            const timeDisplay = document.getElementById('clock-time');
            const pauseBtn = document.getElementById('pause-btn');
            clock.classList.add('active');
            title.textContent = timerName || 'Timer';
            pauseBtn.textContent = 'Pause';

            function updateDisplay(time) {
                const hours = Math.floor(time / 3600);
                const minutes = Math.floor((time % 3600) / 60);
                const seconds = time % 60;
                return `${String(hours).padStart(2, '0')}h ${String(minutes).padStart(2, '0')}m ${String(seconds).padStart(2, '0')}s`;
            }

            timers[id] = setInterval(() => {
                if (!isPaused) {
                    if (isStudying) {
                        timeDisplay.textContent = updateDisplay(studyTime);
                        timeDisplay.classList.remove('glow-green');
                        timeDisplay.classList.add('glow-blue');
                        studyTime--;
                        if (studyTime < 0) {
                            clearInterval(timers[id]);
                            showModal("REST time", () => {
                                isStudying = false;
                                restTime = originalRestTime;
                                timeDisplay.classList.remove('glow-blue');
                                timeDisplay.classList.add('glow-green');
                                timers[id] = setInterval(() => {
                                    if (!isPaused) {
                                        timeDisplay.textContent = updateDisplay(restTime);
                                        restTime--;
                                        if (restTime < 0) {
                                            clearInterval(timers[id]);
                                            showModal("Back to Study", () => {
                                                isStudying = true;
                                                studyTime = originalStudyTime;
                                                timeDisplay.classList.remove('glow-green');
                                                timeDisplay.classList.add('glow-blue');
                                                startTimer(id, studyTime, restTime, timerName);
                                            });
                                        }
                                        document.getElementById(`rest-time-${id}`).innerText = restTime;
                                    }
                                }, 1000);
                            });
                        }
                        document.getElementById(`study-time-${id}`).innerText = studyTime;
                    }
                }
            }, 1000);
        }

        function stopTimer(id) {
            if (timers[id]) {
                clearInterval(timers[id]);
                delete timers[id];
                const clock = document.getElementById('timer-clock');
                clock.classList.remove('active');
                isPaused = false;
            }
            document.getElementById(`study-time-${id}`).innerText = originalStudyTime;
            document.getElementById(`rest-time-${id}`).innerText = originalRestTime;
        }

        function pauseTimer() {
            isPaused = !isPaused;
            const pauseBtn = document.getElementById('pause-btn');
            pauseBtn.textContent = isPaused ? 'Resume' : 'Pause';
        }

        function resetTimer() {
            if (activeTimerId) {
                stopTimer(activeTimerId);
                startTimer(activeTimerId, originalStudyTime, originalRestTime, document.getElementById('timer-title').textContent);
            }
        }

        function setPreset(seconds) {
            if (activeTimerId) {
                const studyDuration = Math.floor(seconds / 2);
                const restDuration = seconds - studyDuration;
                stopTimer(activeTimerId);
                startTimer(activeTimerId, studyDuration, restDuration, document.getElementById('timer-title').textContent);
                document.getElementById(`study-time-${activeTimerId}`).innerText = studyDuration;
                document.getElementById(`rest-time-${activeTimerId}`).innerText = restDuration;
                originalStudyTime = studyDuration;
                originalRestTime = restDuration;
            }
        }

        function hideClock() {
            const clock = document.getElementById('timer-clock');
            clock.classList.remove('active');
        }

        function showModal(message, callback) {
            const modal = document.getElementById('pomodoro-modal');
            const modalMessage = document.getElementById('modal-message');
            modalMessage.textContent = message;
            modal.classList.add('active');
            setTimeout(() => {
                modal.classList.remove('active');
                if (callback) callback();
            }, 2000);
        }

        function closeModal() {
            const modal = document.getElementById('pomodoro-modal');
            modal.classList.remove('active');
        }

        function showCreateTimerModal() {
            const modal = document.getElementById('create-timer-modal');
            const overlay = document.getElementById('overlay');
            modal.classList.add('active');
            overlay.classList.add('active');
            document.getElementById('create-timer-form').reset(); // Reset form fields
        }

        function hideCreateTimerModal() {
            const modal = document.getElementById('create-timer-modal');
            const overlay = document.getElementById('overlay');
            modal.classList.remove('active');
            overlay.classList.remove('active');
        }

        document.getElementById('overlay').addEventListener('click', hideCreateTimerModal);

        function addAnotherTimer() {
            const form = document.getElementById('create-timer-form');
            const formData = new FormData(form);
            formData.append('ajax', 'true'); // Add custom AJAX indicator
            fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest' // Indicate AJAX request
                }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok ' + response.statusText);
                }
                return response.text(); // Get raw response for debugging
            })
            .then(text => {
                console.log('Raw response:', text); // Debug raw response
                const data = JSON.parse(text); // Parse JSON
                if (data.success) {
                    appendTimer(data.timer); // Append the new timer
                    updateMessages(data.message, 'success');
                } else {
                    updateMessages(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                updateMessages('An error occurred while adding the timer: ' + error.message, 'error');
            });
        }

        // Function to append a new timer to the DOM
        function appendTimer(timer) {
            const timerContainer = document.getElementById('timer-container');
            const noTimersMessage = timerContainer.querySelector('.timer div')?.textContent === 'No timers created yet';

            if (noTimersMessage) {
                timerContainer.innerHTML = ''; // Clear "No timers" message
            }

            const timerDiv = document.createElement('div');
            timerDiv.className = 'timer';
            timerDiv.id = `timer-${timer.id}`;
            timerDiv.innerHTML = `
                <div>
                    <h3>${timer.timer_name}</h3>
                    <p>Study: <span id="study-time-${timer.id}">${timer.study_duration}</span> seconds</p>
                    <p>Rest: <span id="rest-time-${timer.id}">${timer.rest_duration}</span> seconds</p>
                </div>
                <div class="timer-actions">
                    <button onclick="startTimer(${timer.id}, ${timer.study_duration}, ${timer.rest_duration}, '${timer.timer_name}')">Start</button>
                    <button onclick="stopTimer(${timer.id})">Stop</button>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this timer?');">
                        <input type="hidden" name="timer_id" value="${timer.id}">
                        <button type="submit" name="delete_timer" class="delete">Delete</button>
                    </form>
                </div>
            `;
            timerContainer.insertBefore(timerDiv, timerContainer.firstChild); // Add new timer at the top
        }

        // Function to update success/error messages
        function updateMessages(message, type) {
            let messagesDiv = document.querySelector('.messages');
            if (!messagesDiv) {
                messagesDiv = document.createElement('div');
                messagesDiv.className = 'messages';
                document.querySelector('.main-content').insertBefore(messagesDiv, document.querySelector('.section-title'));
            }
            messagesDiv.innerHTML = `<div class="${type}">${message}</div>`;
        }

        // Submit form via AJAX to avoid page reload (for both modal and new section)
        function handleFormSubmit(formId) {
            const form = document.getElementById(formId);
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                formData.append('ajax', 'true'); // Add custom AJAX indicator
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest' // Indicate AJAX request
                    }
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok ' + response.statusText);
                    }
                    return response.text(); // Get raw response for debugging
                })
                .then(text => {
                    console.log('Raw response:', text); // Debug raw response
                    const data = JSON.parse(text); // Parse JSON
                    if (data.success) {
                        appendTimer(data.timer); // Append the new timer
                        updateMessages(data.message, 'success');
                        this.reset(); // Reset form fields
                        if (formId === 'create-timer-form') {
                            hideCreateTimerModal(); // Close modal if using modal form
                        }
                    } else {
                        updateMessages(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    updateMessages('An error occurred while adding the timer: ' + error.message, 'error');
                });
            });
        }

        // Apply form submission handling to both forms
        handleFormSubmit('create-timer-form'); // Modal form
        handleFormSubmit('add-timer-form');    // New section form

        document.addEventListener('DOMContentLoaded', function() {
            // Toggle dropdown menu for the three lines button
            const menuButton = document.querySelector('.actions .menu-btn');
            const dropdown = document.querySelector('.dropdown');
            menuButton.addEventListener('click', function() {
                dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
            });

            // Close dropdown when clicking outside
            document.addEventListener('click', function(event) {
                if (!menuButton.contains(event.target) && !dropdown.contains(event.target)) {
                    dropdown.style.display = 'none';
                }
            });

            // Share button functionality (placeholder for Google Drive sharing)
            const shareButton = document.querySelector('.actions .share-btn');
            shareButton.addEventListener('click', function() {
                const shareLink = 'https://drive.google.com/share/timer-repository';
                alert('Timer list shared to Google Drive! Shareable link: ' + shareLink);
                navigator.clipboard.writeText(shareLink).then(() => {
                    console.log('Share link copied to clipboard');
                });
            });

            // Edit profile button functionality
            const editProfileButton = document.querySelector('.actions .edit-profile-btn');
            editProfileButton.addEventListener('click', function() {
                window.location.href = 'edit_profile.php';
            });

            // My Drive dropdown functionality
            const myDriveItem = document.querySelector('.menu-item.my-drive');
            const subMenu = document.querySelector('.sub-menu');
            myDriveItem.addEventListener('click', (e) => {
                e.preventDefault();
                subMenu.classList.toggle('show');
                myDriveItem.classList.toggle('active');
            });

            // Toggle subfolder dropdowns
            document.querySelectorAll('.folder-item.has-subfolders').forEach(item => {
                const folderLink = item.querySelector('.folder-link');
                folderLink.addEventListener('click', (e) => {
                    e.preventDefault();
                    const subfolderList = item.querySelector('.subfolder-list');
                    subfolderList.classList.toggle('show');
                    item.classList.toggle('active');
                });
            });

            // Mobile menu toggle
            const mobileMenuToggle = document.querySelector('.mobile-menu-toggle');
            const sidebar = document.querySelector('.sidebar');
            mobileMenuToggle.addEventListener('click', () => {
                sidebar.classList.toggle('active');
            });

            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', (event) => {
                if (!sidebar.contains(event.target) && !mobileMenuToggle.contains(event.target) && sidebar.classList.contains('active')) {
                    sidebar.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>

<?php $conn->close(); ?>