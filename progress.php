<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
require_once 'db.php';

// Success/Error messages (stored in session for PRG pattern)
if (!isset($_SESSION['messages'])) {
    $_SESSION['messages'] = [];
}
$messages = &$_SESSION['messages'];

$user_id = $_SESSION['user_id'];

// Handle adding a lesson
if (isset($_POST['add_lesson'])) {
    $lesson_name = trim($_POST['lesson_name']);
    $total_chapters = (int)$_POST['total_chapters'];
    if (!empty($lesson_name) && $total_chapters > 0) {
        $stmt = $conn->prepare("INSERT INTO lessons (user_id, lesson_name, total_chapters) VALUES (?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("isi", $user_id, $lesson_name, $total_chapters);
            if ($stmt->execute()) {
                $messages['success'] = "Lesson '$lesson_name' added successfully!";
                header("Location: progress.php?page=progress");
                exit();
            } else {
                $messages['error'] = "Failed to add lesson: " . $conn->error;
            }
            $stmt->close();
        }
    } else {
        $messages['error'] = "Lesson name is required and total chapters must be greater than 0!";
    }
}

// Handle updating progress
if (isset($_POST['update_progress'])) {
    $lesson_id = (int)$_POST['lesson_id'];
    $chapter_number = (int)$_POST['chapter_number'];
    $status = $_POST['status'];
    $stmt = $conn->prepare("INSERT INTO progress_logs (user_id, lesson_id, chapter_number, status, completed_at) VALUES (?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE status = ?, completed_at = NOW()");
    if ($stmt) {
        $stmt->bind_param("iiiss", $user_id, $lesson_id, $chapter_number, $status, $status);
        if ($stmt->execute()) {
            $messages['success'] = "Progress updated successfully!";
            header("Location: progress.php?page=progress");
            exit();
        } else {
            $messages['error'] = "Failed to update progress: " . $conn->error;
        }
        $stmt->close();
    }
}

// Handle deleting a lesson
if (isset($_POST['delete_lesson'])) {
    $lesson_id = (int)$_POST['lesson_id'];
    $stmt = $conn->prepare("DELETE FROM progress_logs WHERE lesson_id = ? AND user_id = ?");
    if ($stmt) {
        $stmt->bind_param("ii", $lesson_id, $user_id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM lessons WHERE id = ? AND user_id = ?");
        if ($stmt) {
            $stmt->bind_param("ii", $lesson_id, $user_id);
            if ($stmt->execute()) {
                $messages['success'] = "Lesson deleted successfully!";
            } else {
                $messages['error'] = "Failed to delete lesson: " . $conn->error;
            }
            $stmt->close();
        }
        header("Location: progress.php?page=progress");
        exit();
    } else {
        $messages['error'] = "Error preparing delete statement: " . $conn->error;
    }
}

// Fetch lessons and their progress
$stmt = $conn->prepare("SELECT l.*, 
    (SELECT COUNT(*) FROM progress_logs pl WHERE pl.lesson_id = l.id AND pl.user_id = ? AND pl.status = 'completed') as completed_chapters 
    FROM lessons l WHERE l.user_id = ? ORDER BY l.created_at DESC");
if ($stmt) {
    $stmt->bind_param("ii", $user_id, $user_id);
    $stmt->execute();
    $lessons = $stmt->get_result();
} else {
    $messages['error'] = "Error fetching lessons: " . $conn->error;
    $lessons = null;
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
    <title>White Room - Progress Tracker</title>
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
            margin-right: 5px;
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
            color: #8bc34a;
            background-color: rgba(139, 195, 74, 0.1);
            padding: 15px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
        }
        .messages .error {
            color: #ef5350;
            background-color: rgba(239, 83, 80, 0.1);
            padding: 15px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
        }
        .progress-form {
            background-color: #212829;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s ease;
        }
        .progress-form:hover {
            transform: translateY(-2px);
        }
        .progress-form input {
            background-color: #2d2e30;
            border: 1px solid #3a3f41;
            border-radius: 8px;
            padding: 12px;
            color: #e0e0e0;
            margin-bottom: 12px;
            width: 100%;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }
        .progress-form input:focus {
            border-color: #8ab4f8;
            outline: none;
        }
        .progress-form button {
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
        .progress-form button:hover {
            background-color: #6b9eff;
            transform: translateY(-1px);
        }
        .progress-form button:active {
            transform: translateY(1px);
        }
        .lesson {
            background-color: #212829;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 15px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s ease;
        }
        .lesson:hover {
            transform: translateY(-2px);
        }
        .lesson h3 {
            font-size: 18px;
            font-weight: 600;
            color: #e0e0e0;
            margin-bottom: 10px;
        }
        .lesson p {
            font-size: 14px;
            color: #b0b0b0;
            margin-bottom: 10px;
        }
        .progress-bar {
            background-color: #2d2e30;
            height: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            overflow: hidden;
            transition: transform 0.3s ease;
        }
        .progress-bar:hover {
            transform: scaleY(1.1);
        }
        .progress-bar-fill {
            background-color: #8ab4f8;
            height: 100%;
            border-radius: 5px;
            transition: width 0.3s ease-in-out;
        }
        .lesson-form {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }
        .lesson-form select {
            background-color: #2d2e30;
            border: 1px solid #3a3f41;
            border-radius: 8px;
            padding: 12px;
            color: #e0e0e0;
            font-size: 14px;
            width: auto;
            flex: 1;
            min-width: 150px;
            transition: border-color 0.3s ease;
        }
        .lesson-form select:focus {
            border-color: #8ab4f8;
            outline: none;
        }
        .lesson-form button {
            background-color: #4CAF50;
            border: none;
            border-radius: 8px;
            padding: 12px 20px;
            color: #ffffff;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.1s ease;
        }
        .lesson-form button:hover {
            background-color: #388e3c;
            transform: translateY(-1px);
        }
        .lesson-form button:active {
            transform: translateY(1px);
        }
        .delete-form {
            display: inline-block;
        }
        .delete-btn {
            background-color: #ff6b6b;
            border: none;
            border-radius: 8px;
            padding: 10px 15px;
            color: #ffffff;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.1s ease;
        }
        .delete-btn:hover {
            background-color: #e55a5a;
            transform: translateY(-1px);
        }
        .delete-btn:active {
            transform: translateY(1px);
        }
        .no-lessons {
            text-align: center;
            color: #b0b0b0;
            font-size: 18px;
            font-weight: 500;
            padding: 20px;
            background-color: #212829;
            border-radius: 12px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
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
            .progress-form {
                padding: 15px;
                margin-bottom: 15px;
            }
            .progress-form input {
                padding: 10px;
                margin-bottom: 10px;
                font-size: 12px;
            }
            .progress-form button {
                padding: 10px 15px;
                font-size: 14px;
            }
            .lesson {
                padding: 15px;
                margin-bottom: 10px;
            }
            .lesson h3 {
                font-size: 16px;
                margin-bottom: 8px;
            }
            .lesson p {
                font-size: 12px;
                margin-bottom: 8px;
            }
            .progress-bar {
                height: 8px;
            }
            .lesson-form {
                gap: 8px;
            }
            .lesson-form select {
                padding: 10px;
                min-width: 120px;
                font-size: 12px;
            }
            .lesson-form button {
                padding: 10px 15px;
                font-size: 14px;
            }
            .delete-btn {
                padding: 8px 12px;
                font-size: 12px;
            }
            .no-lessons {
                font-size: 16px;
                padding: 15px;
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
            .progress-form {
                padding: 10px;
            }
            .progress-form input {
                padding: 8px;
                margin-bottom: 8px;
                font-size: 10px;
            }
            .progress-form button {
                padding: 8px 12px;
                font-size: 12px;
            }
            .lesson {
                padding: 10px;
            }
            .lesson h3 {
                font-size: 14px;
                margin-bottom: 6px;
            }
            .lesson p {
                font-size: 10px;
                margin-bottom: 6px;
            }
            .progress-bar {
                height: 6px;
            }
            .lesson-form {
                gap: 6px;
            }
            .lesson-form select {
                padding: 8px;
                min-width: 100px;
                font-size: 10px;
            }
            .lesson-form button {
                padding: 8px 12px;
                font-size: 12px;
            }
            .delete-btn {
                padding: 6px 10px;
                font-size: 10px;
            }
            .no-lessons {
                font-size: 14px;
                padding: 10px;
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
            <button class="new-btn" onclick="document.getElementById('progress-form').scrollIntoView()">New Lesson</button>
            <div class="menu-item my-drive" title="Lessons">Lessons</div>
            <ul class="sub-menu">
                <?php
                function renderFolderTree($folders, $conn, $user_id) {
                    foreach ($folders as $folder) {
                        $has_subfolders = !empty($folder['subfolders']);
                        echo "<li class='folder-item " . ($has_subfolders ? 'has-subfolders' : '') . "'>";
                        echo "<a href='review.php?page=review&folder_id={$folder['id']}' class='folder-link'>" . htmlspecialchars($folder['folder_name']) . "</a>";
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
            <a href="timer.php?page=timer" class="<?php echo isset($_GET['page']) && $_GET['page'] == 'timer' ? 'active' : ''; ?>">Timer</a>
            <a href="notes.php?page=notes" class="<?php echo isset($_GET['page']) && $_GET['page'] == 'notes' ? 'active' : ''; ?>">Notes</a>
            <a href="review.php?page=review" class="<?php echo isset($_GET['page']) && $_GET['page'] == 'review' ? 'active' : ''; ?>">Review</a>
            <a href="reviewer_notes.php?page=reviewer_notes" class="<?php echo isset($_GET['page']) && $_GET['page'] == 'reviewer_notes' ? 'active' : ''; ?>">Reviewer Notes</a>
            <a href="schedule.php?page=schedule" class="<?php echo isset($_GET['page']) && $_GET['page'] == 'schedule' ? 'active' : ''; ?>">Schedule</a>
            <a href="progress.php?page=progress" class="active">Progress Tracker</a>
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
                    <a href="progress.php?page=progress">Progress Tracker</a>
                </div>
                <div class="actions">
                    <button class="menu-btn" title="Menu">☰</button>
                    <div class="dropdown">
                        <a href="logout.php">Logout</a>
                        <a href="change_account.php">Change Account</a>
                        <a href="index.php?new_login=true">Sign in to Another Account</a>
                    </div>
                    <button class="share-btn" title="Share">🖼️</button>
                    <button class="edit-profile-btn" title="Info">ℹ️</button>
                </div>
            </div>

            <?php if (!empty($messages)): ?>
                <div class="messages">
                    <?php if (isset($messages['success'])): ?>
                        <div class="success"><?php echo $messages['success']; ?></div>
                    <?php endif; ?>
                    <?php if (isset($messages['error'])): ?>
                        <div class="error"><?php echo $messages['error']; ?></div>
                    <?php endif; ?>
                </div>
                <?php unset($_SESSION['messages']); // Clear messages after display ?>
            <?php endif; ?>

            <div class="progress-form" id="progress-form">
                <form method="POST">
                    <input type="text" name="lesson_name" placeholder="Lesson Name" required>
                    <input type="number" name="total_chapters" placeholder="Total Chapters" required min="1">
                    <button type="submit" name="add_lesson">Add Lesson</button>
                </form>
            </div>

            <?php if ($lessons && $lessons->num_rows > 0): ?>
                <?php while ($lesson = $lessons->fetch_assoc()): ?>
                    <?php
                    $completed_chapters = (int)$lesson['completed_chapters'];
                    $total_chapters = (int)$lesson['total_chapters'];
                    $progress_percentage = $total_chapters > 0 ? ($completed_chapters / $total_chapters) * 100 : 0;
                    ?>
                    <div class="lesson">
                        <h3><?php echo htmlspecialchars($lesson['lesson_name']); ?></h3>
                        <p>Total Chapters: <?php echo htmlspecialchars($lesson['total_chapters']); ?> | Completed: <?php echo $completed_chapters; ?></p>
                        <div class="progress-bar">
                            <div class="progress-bar-fill" style="width: <?php echo $progress_percentage; ?>%;"></div>
                        </div>
                        <form method="POST" class="lesson-form">
                            <input type="hidden" name="lesson_id" value="<?php echo $lesson['id']; ?>">
                            <select name="chapter_number">
                                <?php for ($i = 1; $i <= $lesson['total_chapters']; $i++): ?>
                                    <option value="<?php echo $i; ?>">Chapter <?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                            <select name="status">
                                <option value="not_started">Not Started</option>
                                <option value="in_progress">In Progress</option>
                                <option value="completed">Completed</option>
                            </select>
                            <button type="submit" name="update_progress">Update Progress</button>
                        </form>
                        <form method="POST" class="delete-form">
                            <input type="hidden" name="lesson_id" value="<?php echo $lesson['id']; ?>">
                            <button type="submit" name="delete_lesson" class="delete-btn" onclick="return confirm('Are you sure you want to delete this lesson?');">Delete Lesson</button>
                        </form>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-lessons">
                    <p>No lessons available.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
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
                const shareLink = 'https://drive.google.com/share/progress-tracker';
                alert('Progress tracker shared to Google Drive! Shareable link: ' + shareLink);
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

<?php
if (isset($stmt)) $stmt->close();
$conn->close();
?>