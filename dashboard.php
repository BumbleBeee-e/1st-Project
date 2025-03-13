<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
require_once 'db.php';

// Ensure uploads directory exists
$upload_dir = 'uploads/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Success/Error messages (stored in session for PRG pattern)
if (!isset($_SESSION['messages'])) {
    $_SESSION['messages'] = [];
}
$messages = &$_SESSION['messages'];
$max_file_size = 128 * 1024 * 1024; // 128MB in bytes

$user_id = $_SESSION['user_id'];

// Handle form submissions with POST-Redirect-GET pattern
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $redirect_url = "dashboard.php";

    // Handle folder creation
    if (isset($_POST['create_folder'])) {
        $folder_name = trim($_POST['folder_name']);
        if (!empty($folder_name)) {
            $stmt = $conn->prepare("INSERT INTO lesson_folders (user_id, folder_name) VALUES (?, ?)");
            if ($stmt) {
                $stmt->bind_param("is", $user_id, $folder_name);
                if ($stmt->execute()) {
                    $messages['success'] = "Folder '$folder_name' created successfully!";
                    $stmt->close();
                    header("Location: $redirect_url");
                    exit();
                } else {
                    $messages['error'] = "Failed to create folder: " . $conn->error;
                }
                $stmt->close();
            }
        } else {
            $messages['error'] = "Folder name cannot be empty!";
        }
    }

    // Handle file upload
    if (isset($_FILES['file']) && $_FILES['file']['error'] == UPLOAD_ERR_OK) {
        $allowed_types = [
            'application/pdf' => 'pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-powerpoint' => 'ppt'
        ];
        $file_type = $_FILES['file']['type'];
        $file_size = $_FILES['file']['size'];

        if ($file_size > $max_file_size) {
            $messages['error'] = "File size exceeds the maximum limit of 128MB!";
        } elseif (array_key_exists($file_type, $allowed_types)) {
            $filename = $_FILES['file']['name'];
            $filepath = $upload_dir . uniqid() . '_' . $filename;
            if (move_uploaded_file($_FILES['file']['tmp_name'], $filepath)) {
                $file_ext = $allowed_types[$file_type];
                $stmt = $conn->prepare("INSERT INTO lesson_files (user_id, filename, filepath, file_type) VALUES (?, ?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param("isss", $user_id, $filename, $filepath, $file_ext);
                    if ($stmt->execute()) {
                        $messages['success'] = "File '$filename' uploaded successfully!";
                        $stmt->close();
                        header("Location: $redirect_url");
                        exit();
                    } else {
                        $messages['error'] = "Failed to upload file: " . $conn->error;
                    }
                    $stmt->close();
                }
            } else {
                $messages['error'] = "Failed to move uploaded file!";
            }
        } else {
            $messages['error'] = "Only PDF, DOCX, and PPT files are allowed!";
        }
    }
}

// Check if last_accessed column exists, fallback to upload_date/created_at if not
$check_column = $conn->query("SHOW COLUMNS FROM lesson_files LIKE 'last_accessed'");
$files_order = ($check_column->num_rows > 0) ? "last_accessed" : "upload_date";
$check_column = $conn->query("SHOW COLUMNS FROM lesson_folders LIKE 'last_accessed'");
$folders_order = ($check_column->num_rows > 0) ? "last_accessed" : "created_at";

// Fetch recent files
$stmt = $conn->prepare("SELECT * FROM lesson_files WHERE user_id = ? " . ($files_order === 'last_accessed' ? "AND last_accessed IS NOT NULL " : "") . "ORDER BY $files_order DESC LIMIT 5");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_files = $stmt->get_result();
$stmt->close();

// Fetch recent folders
$stmt = $conn->prepare("SELECT * FROM lesson_folders WHERE user_id = ? " . ($folders_order === 'last_accessed' ? "AND last_accessed IS NOT NULL " : "") . "ORDER BY $folders_order DESC LIMIT 5");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_folders = $stmt->get_result();
$stmt->close();

// Fetch all schedules
$stmt = $conn->prepare("SELECT * FROM schedules WHERE user_id = ? ORDER BY schedule_date DESC, start_time DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$schedules = $stmt->get_result();
$stmt->close();

// Fetch all lessons and their progress
$stmt = $conn->prepare("SELECT l.*, 
    (SELECT COUNT(*) FROM progress_logs pl WHERE pl.lesson_id = l.id AND pl.user_id = ? AND pl.status = 'completed') as completed_chapters 
    FROM lessons l WHERE l.user_id = ? ORDER BY l.created_at DESC");
if ($stmt) {
    $stmt->bind_param("ii", $user_id, $user_id);
    $stmt->execute();
    $lessons = $stmt->get_result();
    $stmt->close();
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
    <title>White Room - Dashboard</title>
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
            font-size: 22px;
            font-weight: 500;
            color: #ffffff;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #3a3f41;
        }
        .table-header {
            display: flex;
            background-color: #252b2d;
            padding: 12px 20px;
            border-radius: 8px 8px 0 0;
            border-bottom: 1px solid #3a3f41;
        }
        .table-header div {
            flex: 1;
            font-weight: 500;
            color: #b0b0b0;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .table-header div:first-child {
            flex: 2;
        }
        .table-row {
            display: flex;
            padding: 15px 20px;
            border-bottom: 1px solid #3a3f41;
            align-items: center;
            background-color: #212829;
            transition: background-color 0.3s ease;
        }
        .table-row:hover {
            background-color: #2d3233;
        }
        .table-row:last-child {
            border-bottom: none;
            border-radius: 0 0 8px 8px;
        }
        .table-row div {
            flex: 1;
            color: #e0e0e0;
            font-size: 14px;
        }
        .table-row div:first-child {
            flex: 2;
            display: flex;
            align-items: center;
        }
        .table-row div:first-child a {
            color: #8ab4f8;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        .table-row div:first-child a:hover {
            color: #6b9eff;
            text-decoration: underline;
        }
        .table-row div:first-child.folder::before,
        .table-row div:first-child.file::before,
        .table-row div:first-child.schedule::before,
        .table-row div:first-child.lesson::before {
            content: '';
            display: inline-block;
            width: 24px;
            height: 24px;
            margin-right: 12px;
            background: url('https://via.placeholder.com/24?text=icon') no-repeat center;
            opacity: 0.7;
        }
        .table-row div:first-child.folder::before {
            background: url('https://via.placeholder.com/24?text=folder') no-repeat center;
        }
        .table-row div:first-child.file::before {
            background: url('https://via.placeholder.com/24?text=file') no-repeat center;
        }
        .table-row div:first-child.schedule::before {
            background: url('https://via.placeholder.com/24?text=schedule') no-repeat center;
        }
        .table-row div:first-child.lesson::before {
            background: url('https://via.placeholder.com/24?text=progress') no-repeat center;
        }
        .form-container {
            margin-bottom: 30px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        .form-container input[type="text"],
        .form-container input[type="file"] {
            background-color: #252b2d;
            border: 1px solid #3a3f41;
            border-radius: 8px;
            padding: 12px 15px;
            color: #e0e0e0;
            font-size: 14px;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .form-container input[type="text"]:focus,
        .form-container input[type="file"]:focus {
            border-color: #8ab4f8;
            box-shadow: 0 0 0 3px rgba(138, 180, 248, 0.2);
            outline: none;
        }
        .form-container button {
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
        .form-container button:hover {
            background-color: #6b9eff;
            transform: translateY(-1px);
        }
        .form-container button:active {
            transform: translateY(1px);
        }
        .progress-bar {
            background-color: #3a3f41;
            height: 10px;
            border-radius: 5px;
            overflow: hidden;
            width: 100%;
        }
        .progress-bar-fill {
            background-color: #8ab4f8;
            height: 100%;
            border-radius: 5px;
            transition: width 0.3s ease-in-out;
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
            .table-row {
                flex-wrap: wrap;
                gap: 10px;
            }
            .table-row div {
                flex: none;
                width: 100%;
            }
            .table-row div:first-child {
                width: 100%;
            }
            .table-header {
                display: none;
            }
            .form-container {
                flex-direction: column;
            }
            .form-container input,
            .form-container button {
                width: 100%;
            }
        }
        @media (max-width: 480px) {
            .section-title {
                font-size: 18px;
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
            <button class="new-btn" onclick="document.getElementById('create-folder-form').style.display='block'; document.getElementById('create-folder-form').scrollIntoView()">New Folder</button>
            <div class="menu-item my-drive" title="Lessons">Lessons</div>
            <ul class="sub-menu">
                <?php
                function renderFolderTree($folders, $conn, $user_id, $level = 0) {
                    foreach ($folders as $folder) {
                        $has_subfolders = !empty($folder['subfolders']);
                        echo "<li class='folder-item " . ($has_subfolders ? 'has-subfolders' : '') . "'>";
                        echo "<a href='lessons_repository.php?page=lessons&folder_id={$folder['id']}' class='folder-link'>" . htmlspecialchars($folder['folder_name']) . "</a>";
                        if ($has_subfolders) {
                            echo "<ul class='subfolder-list'>";
                            renderFolderTree($folder['subfolders'], $conn, $user_id, $level + 1);
                            echo "</ul>";
                        }
                        echo "</li>";
                    }
                }
                renderFolderTree($all_folders, $conn, $user_id);
                ?>
            </ul>
            <a href="dashboard.php" class="active">Home</a>
            <a href="lessons_repository.php?page=lessons">Lessons Repository</a>
            <a href="timer.php?page=timer" class="<?php echo isset($_GET['page']) && $_GET['page'] == 'timer' ? 'active' : ''; ?>">Timer</a>
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
                    <a href="dashboard.php">Home</a>
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

            <?php if (!empty($messages)): ?>
                <div class="messages">
                    <?php if (isset($messages['success'])): ?>
                        <div class="success"><?php echo $messages['success']; ?></div>
                    <?php endif; ?>
                    <?php if (isset($messages['error'])): ?>
                        <div class="error"><?php echo $messages['error']; ?></div>
                    <?php endif; ?>
                </div>
                <?php unset($_SESSION['messages']); ?>
            <?php endif; ?>

            <div class="form-container" id="create-folder-form" style="display: none;">
                <form method="POST">
                    <input type="text" name="folder_name" placeholder="New Folder Name" required maxlength="100">
                    <button type="submit" name="create_folder">Create Folder</button>
                </form>
            </div>
            <div class="form-container">
                <form method="POST" enctype="multipart/form-data">
                    <input type="file" name="file" required>
                    <button type="submit" name="upload">Upload File</button>
                </form>
            </div>

            <!-- Recent Files Section -->
            <div class="section-title">Recent Files</div>
            <div class="table-header">
                <div>Name</div>
                <div>Owner</div>
                <div><?php echo ($files_order === 'last_accessed') ? 'Last Accessed' : 'Uploaded'; ?></div>
                <div>File Size</div>
            </div>
            <?php if ($recent_files->num_rows > 0): ?>
                <?php while ($file = $recent_files->fetch_assoc()): ?>
                    <div class="table-row">
                        <div class="file"><a href="lessons_repository.php?page=lessons&view_file=<?php echo $file['id']; ?>" target="_blank"><?php echo htmlspecialchars($file['filename']); ?></a></div>
                        <div>me</div>
                        <div><?php echo ($files_order === 'last_accessed' && $file['last_accessed']) ? date('Y-m-d H:i', strtotime($file['last_accessed'])) : date('Y-m-d H:i', strtotime($file['upload_date'])); ?></div>
                        <div><?php echo file_exists($file['filepath']) ? round(filesize($file['filepath']) / 1024, 2) . ' KB' : '-'; ?></div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="table-row">
                    <div>No recent files available.</div>
                    <div></div>
                    <div></div>
                    <div></div>
                </div>
            <?php endif; ?>

            <!-- Recent Folders Section -->
            <div class="section-title" style="margin-top: 30px;">Recent Folders</div>
            <div class="table-header">
                <div>Name</div>
                <div>Owner</div>
                <div><?php echo ($folders_order === 'last_accessed') ? 'Last Accessed' : 'Created'; ?></div>
                <div></div>
            </div>
            <?php if ($recent_folders->num_rows > 0): ?>
                <?php while ($folder = $recent_folders->fetch_assoc()): ?>
                    <div class="table-row">
                        <div class="folder"><a href="lessons_repository.php?page=lessons&folder_id=<?php echo $folder['id']; ?>"><?php echo htmlspecialchars($folder['folder_name']); ?></a></div>
                        <div>me</div>
                        <div><?php echo ($folders_order === 'last_accessed' && $folder['last_accessed']) ? date('Y-m-d H:i', strtotime($folder['last_accessed'])) : date('Y-m-d H:i', strtotime($folder['created_at'])); ?></div>
                        <div></div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="table-row">
                    <div>No recent folders available.</div>
                    <div></div>
                    <div></div>
                    <div></div>
                </div>
            <?php endif; ?>

            <!-- Schedules Section -->
            <div class="section-title" style="margin-top: 30px;">Schedules</div>
            <div class="table-header">
                <div>Title</div>
                <div>Date</div>
                <div>Time</div>
                <div>Description</div>
            </div>
            <?php if ($schedules->num_rows > 0): ?>
                <?php while ($schedule = $schedules->fetch_assoc()): ?>
                    <div class="table-row">
                        <div class="schedule"><?php echo htmlspecialchars($schedule['title']); ?></div>
                        <div><?php echo htmlspecialchars($schedule['schedule_date']); ?></div>
                        <div><?php echo htmlspecialchars($schedule['start_time']) . ' - ' . htmlspecialchars($schedule['end_time']); ?></div>
                        <div><?php echo nl2br(htmlspecialchars($schedule['description'])); ?></div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="table-row">
                    <div>No schedules available.</div>
                    <div></div>
                    <div></div>
                    <div></div>
                </div>
            <?php endif; ?>

            <!-- Progress Tracker Section -->
            <div class="section-title" style="margin-top: 30px;">Progress Tracker</div>
            <div class="table-header">
                <div>Lesson Name</div>
                <div>Total Chapters</div>
                <div>Completed Chapters</div>
                <div>Progress</div>
            </div>
            <?php if ($lessons && $lessons->num_rows > 0): ?>
                <?php while ($lesson = $lessons->fetch_assoc()): ?>
                    <?php
                    $completed_chapters = (int)$lesson['completed_chapters'];
                    $total_chapters = (int)$lesson['total_chapters'];
                    $progress_percentage = $total_chapters > 0 ? ($completed_chapters / $total_chapters) * 100 : 0;
                    ?>
                    <div class="table-row">
                        <div class="lesson"><?php echo htmlspecialchars($lesson['lesson_name']); ?></div>
                        <div><?php echo htmlspecialchars($lesson['total_chapters']); ?></div>
                        <div><?php echo $completed_chapters; ?></div>
                        <div>
                            <div class="progress-bar">
                                <div class="progress-bar-fill" style="width: <?php echo $progress_percentage; ?>%;"></div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="table-row">
                    <div>No lessons available.</div>
                    <div></div>
                    <div></div>
                    <div></div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const fileInput = document.querySelector('input[type="file"]');
            const maxSize = <?php echo $max_file_size; ?>;

            fileInput.addEventListener('change', function() {
                if (this.files[0] && this.files[0].size > maxSize) {
                    alert('File size exceeds the maximum limit of 128MB!');
                    this.value = '';
                }
            });

            const menuButton = document.querySelector('.actions .menu-btn');
            const dropdown = document.querySelector('.dropdown');
            menuButton.addEventListener('click', function() {
                dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
            });

            document.addEventListener('click', function(event) {
                if (!menuButton.contains(event.target) && !dropdown.contains(event.target)) {
                    dropdown.style.display = 'none';
                }
            });

            const shareButton = document.querySelector('.actions .share-btn');
            shareButton.addEventListener('click', function() {
                const shareLink = 'https://drive.google.com/share/dummy-link-to-dashboard';
                alert('Dashboard shared to Google Drive! Shareable link: ' + shareLink);
                navigator.clipboard.writeText(shareLink).then(() => {
                    console.log('Share link copied to clipboard');
                });
            });

            const editProfileButton = document.querySelector('.actions .edit-profile-btn');
            editProfileButton.addEventListener('click', function() {
                window.location.href = 'edit_profile.php';
            });

            // Toggle "Lessons" dropdown
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