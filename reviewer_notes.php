<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
require_once 'db.php';

$user_id = $_SESSION['user_id'];

// Fetch all notes for the user
$stmt = $conn->prepare("SELECT id, title FROM notes WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$notes = $stmt->get_result();
$stmt->close();

// Fetch the note if note_id is provided
if (isset($_GET['note_id'])) {
    $note_id = (int)$_GET['note_id'];
    $stmt = $conn->prepare("SELECT id, title, content, created_at FROM notes WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $note_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $note = $result->fetch_assoc();
    $stmt->close();
} else {
    $note = null;
}

// Fetch all folders for the sidebar
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
    <title>White Room - Reviewer Notes</title>
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
        /* Sidebar Styles (unchanged) */
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
        .notes-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        .note-item {
            background-color: #252b2d;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .note-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
        }
        .note-item .title {
            font-size: 18px;
            font-weight: 500;
            color: #8ab4f8;
            margin-bottom: 10px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .note-item .view-btn {
            background-color: #4CAF50;
            color: #ffffff;
            border: none;
            padding: 10px 15px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            transition: background-color 0.3s ease, transform 0.1s ease;
        }
        .note-item .view-btn:hover {
            background-color: #388e3c;
            transform: translateY(-2px);
        }
        .note-item .view-btn:active {
            transform: translateY(1px);
        }
        .note-viewer {
            background-color: #252b2d;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
            max-width: 1000px;
            margin: 40px auto;
            min-height: 80vh;
            display: flex;
            flex-direction: column;
            border: 1px solid #3a3f41;
            position: relative;
            overflow: hidden;
            transition: transform 0.3s ease;
        }
        .note-viewer:hover {
            transform: scale(1.01);
        }
        .note-viewer .header-section {
            padding-bottom: 20px;
            border-bottom: 2px solid #8ab4f8;
            margin-bottom: 30px;
        }
        .note-viewer h3 {
            font-size: 32px;
            font-weight: 700;
            color: #8ab4f8;
            text-align: center;
            margin-bottom: 10px;
            letter-spacing: 0.5px;
        }
        .note-viewer .metadata {
            font-size: 14px;
            color: #b0b0b0;
            text-align: center;
            font-style: italic;
        }
        .note-viewer .content-wrapper {
            flex: 1;
            background-color: #ffffff;
            border-radius: 12px;
            padding: 30px;
            overflow-y: auto;
            color: #1c2526;
            font-size: 16px;
            line-height: 1.8;
            box-shadow: inset 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        .note-viewer .content-wrapper p {
            margin-bottom: 20px;
        }
        .note-viewer .content-wrapper h1,
        .note-viewer .content-wrapper h2,
        .note-viewer .content-wrapper h3 {
            margin-bottom: 15px;
            color: #333;
        }
        .note-viewer .content-wrapper ul,
        .note-viewer .content-wrapper ol {
            margin: 0 0 20px 25px;
            padding-left: 0;
        }
        .note-viewer .content-wrapper li {
            margin-bottom: 10px;
        }
        .note-viewer .content-wrapper a {
            color: #007bff;
            text-decoration: none;
        }
        .note-viewer .content-wrapper a:hover {
            text-decoration: underline;
        }
        .note-viewer .actions-footer {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 30px;
        }
        .note-viewer .back-btn {
            background-color: #ff6b6b;
            color: #ffffff;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: background-color 0.3s ease, transform 0.1s ease;
        }
        .note-viewer .back-btn:hover {
            background-color: #e55a5a;
            transform: translateY(-2px);
        }
        .note-viewer .back-btn:active {
            transform: translateY(1px);
        }
        .no-note {
            text-align: center;
            color: #b0b0b0;
            font-size: 20px;
            font-weight: 500;
            margin: 50px auto;
            background-color: #252b2d;
            padding: 20px;
            border-radius: 12px;
            max-width: 400px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
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
            .notes-list {
                grid-template-columns: 1fr;
            }
            .note-item {
                padding: 15px;
            }
            .note-item .title {
                font-size: 16px;
            }
            .note-item .view-btn {
                padding: 8px 12px;
                font-size: 12px;
            }
            .note-viewer {
                padding: 20px;
                margin: 20px auto;
                min-height: 60vh;
            }
            .note-viewer h3 {
                font-size: 24px;
            }
            .note-viewer .metadata {
                font-size: 12px;
            }
            .note-viewer .content-wrapper {
                padding: 20px;
                font-size: 14px;
                line-height: 1.6;
            }
            .note-viewer .back-btn {
                padding: 10px 20px;
                font-size: 14px;
            }
            .no-note {
                font-size: 18px;
                margin: 30px auto;
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
            .note-item {
                padding: 12px;
            }
            .note-item .title {
                font-size: 14px;
            }
            .note-item .view-btn {
                padding: 6px 10px;
                font-size: 10px;
            }
            .note-viewer {
                padding: 15px;
                margin: 15px auto;
            }
            .note-viewer h3 {
                font-size: 20px;
            }
            .note-viewer .metadata {
                font-size: 11px;
            }
            .note-viewer .content-wrapper {
                padding: 15px;
                font-size: 12px;
                line-height: 1.5;
            }
            .note-viewer .back-btn {
                padding: 8px 15px;
                font-size: 12px;
            }
            .no-note {
                font-size: 16px;
                margin: 20px auto;
                padding: 12px;
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
            <button class="new-btn" onclick="alert('Feature to create new notes or folders not implemented here. Use Notes page to create notes.')">New</button>
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
            <a href="reviewer_notes.php?page=reviewer_notes" class="active">Reviewer Notes</a>
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
                    <a href="notes.php?page=notes">Notes</a> > Reviewer Notes
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

            <?php if (!isset($note)): ?>
                <div class="notes-list">
                    <?php if ($notes && $notes->num_rows > 0): ?>
                        <?php while ($note_row = $notes->fetch_assoc()): ?>
                            <div class="note-item">
                                <span class="title"><?php echo htmlspecialchars($note_row['title']); ?></span>
                                <a href="reviewer_notes.php?page=reviewer_notes&note_id=<?php echo $note_row['id']; ?>" class="view-btn">View Note</a>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="no-note">No notes available yet.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (isset($note)): ?>
                <div class="note-viewer">
                    <div class="header-section">
                        <h3><?php echo htmlspecialchars($note['title']); ?></h3>
                        <div class="metadata">
                            Created on: <?php echo date('F j, Y, g:i a', strtotime($note['created_at'])); ?>
                        </div>
                    </div>
                    <div class="content-wrapper">
                        <?php echo $note['content']; ?>
                    </div>
                    <div class="actions-footer">
                        <a href="reviewer_notes.php?page=reviewer_notes" class="back-btn">Back to List</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
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
                const shareLink = 'https://drive.google.com/share/reviewer-notes';
                alert('Reviewer notes shared to Google Drive! Shareable link: ' + shareLink);
                navigator.clipboard.writeText(shareLink).then(() => {
                    console.log('Share link copied to clipboard');
                });
            });

            const editProfileButton = document.querySelector('.actions .edit-profile-btn');
            editProfileButton.addEventListener('click', function() {
                window.location.href = 'edit_profile.php';
            });

            const myDriveItem = document.querySelector('.menu-item.my-drive');
            const subMenu = document.querySelector('.sub-menu');
            myDriveItem.addEventListener('click', (e) => {
                e.preventDefault();
                subMenu.classList.toggle('show');
                myDriveItem.classList.toggle('active');
            });

            document.querySelectorAll('.folder-item.has-subfolders').forEach(item => {
                const folderLink = item.querySelector('.folder-link');
                folderLink.addEventListener('click', (e) => {
                    e.preventDefault();
                    const subfolderList = item.querySelector('.subfolder-list');
                    subfolderList.classList.toggle('show');
                    item.classList.toggle('active');
                });
            });

            const mobileMenuToggle = document.querySelector('.mobile-menu-toggle');
            const sidebar = document.querySelector('.sidebar');
            mobileMenuToggle.addEventListener('click', () => {
                sidebar.classList.toggle('active');
            });

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
$conn->close();
?>