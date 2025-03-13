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

// Success/Error messages
$messages = [];
$max_file_size = 128 * 1024 * 1024; // 128MB in bytes

$user_id = $_SESSION['user_id'];

// Get current folder ID from URL (if any)
$current_folder_id = isset($_GET['folder_id']) ? (int)$_GET['folder_id'] : null;

// Get sort option from URL (default to 'last_modified')
$sort_option = isset($_GET['sort']) ? $_GET['sort'] : 'last_modified';
$valid_sort_options = ['last_modified', 'alphabetical', 'upload_date'];
if (!in_array($sort_option, $valid_sort_options)) {
    $sort_option = 'last_modified'; // Fallback to default
}

// Handle folder creation
if (isset($_POST['create_folder'])) {
    $folder_name = trim($_POST['folder_name']);
    if (!empty($folder_name)) {
        $stmt = $conn->prepare("INSERT INTO lesson_folders (user_id, folder_name, parent_folder_id) VALUES (?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("isi", $user_id, $folder_name, $current_folder_id);
            if ($stmt->execute()) {
                $messages['success'] = "Folder '$folder_name' created successfully!";
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
        'application/vnd.ms-powerpoint' => 'ppt',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx'
    ];
    $file_type = $_FILES['file']['type'];
    $file_size = $_FILES['file']['size'];

    if ($file_size > $max_file_size) {
        $messages['error'] = "File size exceeds the maximum limit of 128MB!";
    } elseif (array_key_exists($file_type, $allowed_types)) {
        $filename = $_FILES['file']['name'];
        $filepath = $upload_dir . uniqid() . '_' . $filename;
        if (move_uploaded_file($_FILES['file']['tmp_name'], $filepath)) {
            $folder_id = $current_folder_id;
            $file_ext = $allowed_types[$file_type];
            $stmt = $conn->prepare("INSERT INTO lesson_files (user_id, folder_id, filename, filepath, file_type) VALUES (?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("iisss", $user_id, $folder_id, $filename, $filepath, $file_ext);
                if ($stmt->execute()) {
                    $messages['success'] = "File '$filename' uploaded successfully!";
                } else {
                    $messages['error'] = "Failed to upload file: " . $conn->error;
                }
                $stmt->close();
            }
        } else {
            $messages['error'] = "Failed to move uploaded file!";
        }
    } else {
        $messages['error'] = "Only PDF, DOCX, PPT, and PPTX files are allowed!";
    }
}

// Handle file deletion
if (isset($_POST['delete_file'])) {
    $file_id = $_POST['file_id'];
    $stmt = $conn->prepare("SELECT filepath FROM lesson_files WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $file_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows == 1) {
        $file = $result->fetch_assoc();
        $stmt = $conn->prepare("DELETE FROM lesson_files WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $file_id, $user_id);
        if ($stmt->execute() && unlink($file['filepath'])) {
            $messages['success'] = "File deleted successfully!";
        } else {
            $messages['error'] = "Failed to delete file!";
        }
    }
    $stmt->close();
}

// Handle review submission
if (isset($_POST['save_review']) && isset($_POST['file_id']) && isset($_POST['comments'])) {
    $file_id = (int)$_POST['file_id'];
    $comments = trim($_POST['comments']);
    $stmt = $conn->prepare("INSERT INTO reviews (user_id, file_id, highlights, comments) VALUES (?, ?, '{}', ?)");
    if ($stmt) {
        $stmt->bind_param("iis", $user_id, $file_id, $comments);
        if ($stmt->execute()) {
            $messages['success'] = "Review saved successfully!";
        } else {
            $messages['error'] = "Failed to save review: " . $conn->error;
        }
        $stmt->close();
    }
}

// Fetch all folders for the sidebar (nested structure)
function fetchFolders($conn, $user_id, $parent_id = null, $sort_option = 'last_modified') {
    $folders = [];
    $sort_column = ($sort_option === 'alphabetical') ? "folder_name ASC" : "created_at DESC";
    $stmt = $conn->prepare("SELECT id, folder_name, parent_folder_id FROM lesson_folders WHERE user_id = ? AND parent_folder_id " . ($parent_id !== null ? "= ?" : "IS NULL") . " ORDER BY $sort_column");
    if ($parent_id !== null) {
        $stmt->bind_param("ii", $user_id, $parent_id);
    } else {
        $stmt->bind_param("i", $user_id);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $row['subfolders'] = fetchFolders($conn, $user_id, $row['id'], $sort_option);
        $folders[] = $row;
    }
    $stmt->close();
    return $folders;
}

$all_folders = fetchFolders($conn, $user_id, null, $sort_option);

// Determine sort column and order for folders and files
$folder_sort = '';
$file_sort = '';
switch ($sort_option) {
    case 'alphabetical':
        $folder_sort = "ORDER BY folder_name ASC";
        $file_sort = "ORDER BY filename ASC";
        break;
    case 'upload_date':
        $folder_sort = "ORDER BY created_at DESC";
        $file_sort = "ORDER BY upload_date DESC";
        break;
    case 'last_modified':
    default:
        $folder_sort = "ORDER BY created_at DESC";
        $file_sort = "ORDER BY upload_date DESC";
        break;
}

// Fetch folders and files for the current view
$folder_query = "SELECT * FROM lesson_folders WHERE user_id = ? AND parent_folder_id " . ($current_folder_id ? "= ?" : "IS NULL") . " $folder_sort";
$stmt = $conn->prepare($folder_query);
if ($current_folder_id) {
    $stmt->bind_param("ii", $user_id, $current_folder_id);
} else {
    $stmt->bind_param("i", $user_id);
}
$stmt->execute();
$folders = $stmt->get_result();

$file_query = "SELECT f.*, lf.folder_name FROM lesson_files f LEFT JOIN lesson_folders lf ON f.folder_id = lf.id WHERE f.user_id = ? AND f.folder_id " . ($current_folder_id ? "= ?" : "IS NULL") . " $file_sort";
$stmt = $conn->prepare($file_query);
if ($current_folder_id) {
    $stmt->bind_param("ii", $user_id, $current_folder_id);
} else {
    $stmt->bind_param("i", $user_id);
}
$stmt->execute();
$files = $stmt->get_result();

// Build breadcrumb
$breadcrumb = [];
if ($current_folder_id) {
    $folder_id = $current_folder_id;
    while ($folder_id) {
        $stmt = $conn->prepare("SELECT id, folder_name, parent_folder_id FROM lesson_folders WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $folder_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $breadcrumb[] = ['id' => $row['id'], 'name' => $row['folder_name']];
            $folder_id = $row['parent_folder_id'];
        } else {
            break;
        }
        $stmt->close();
    }
    $breadcrumb = array_reverse($breadcrumb);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>White Room - Review</title>
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
            margin-right: 8px;
            transition: color 0.3s ease;
        }
        .header .breadcrumb a:hover {
            color: #6b9eff;
            text-decoration: underline;
        }
        .header .breadcrumb span {
            color: #b0b0b0;
            margin-right: 8px;
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
        .sort-container {
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .sort-container label {
            font-size: 14px;
            font-weight: 500;
            color: #b0b0b0;
        }
        .sort-container select {
            background-color: #252b2d;
            border: 1px solid #3a3f41;
            border-radius: 8px;
            padding: 10px 15px;
            color: #e0e0e0;
            font-size: 14px;
            cursor: pointer;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .sort-container select:focus {
            border-color: #8ab4f8;
            box-shadow: 0 0 0 3px rgba(138, 180, 248, 0.2);
            outline: none;
        }
        .form-container {
            margin-bottom: 30px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        .form-container input,
        .form-container select {
            background-color: #252b2d;
            border: 1px solid #3a3f41;
            border-radius: 8px;
            padding: 12px 15px;
            color: #e0e0e0;
            font-size: 14px;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .form-container input:focus,
        .form-container select:focus {
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
        .table-row div:first-child.file::before {
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
        .table-row .actions {
            display: flex;
            gap: 10px;
        }
        .table-row .actions button,
        .table-row .actions a {
            padding: 8px 15px;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            transition: background-color 0.3s ease, transform 0.1s ease;
        }
        .table-row .actions .highlight-btn {
            background-color: #8ab4f8;
            color: #1c2526;
            border: none;
        }
        .table-row .actions .highlight-btn:hover {
            background-color: #6b9eff;
            transform: translateY(-1px);
        }
        .table-row .actions .highlight-btn:active {
            transform: translateY(1px);
        }
        .table-row .actions .save-btn {
            background-color: #4CAF50;
            color: #ffffff;
            border: none;
        }
        .table-row .actions .save-btn:hover {
            background-color: #388e3c;
            transform: translateY(-1px);
        }
        .table-row .actions .save-btn:active {
            transform: translateY(1px);
        }
        .table-row .actions .comment-btn {
            background-color: #ff9800;
            color: #ffffff;
            border: none;
        }
        .table-row .actions .comment-btn:hover {
            background-color: #f57c00;
            transform: translateY(-1px);
        }
        .table-row .actions .comment-btn:active {
            transform: translateY(1px);
        }
        .table-row .actions .delete-btn {
            background-color: #ff6b6b;
            color: #ffffff;
            border: none;
        }
        .table-row .actions .delete-btn:hover {
            background-color: #e55a5a;
            transform: translateY(-1px);
        }
        .table-row .actions .delete-btn:active {
            transform: translateY(1px);
        }
        /* Enhanced File Viewer Styles */
        .file-viewer {
            margin-top: 30px;
            background-color: #252b2d;
            border: 1px solid #3a3f41;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
            overflow: hidden;
            display: none;
        }
        .file-viewer-header {
            padding: 15px 20px;
            background-color: #2d3233;
            border-bottom: 1px solid #3a3f41;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .file-viewer-header h3 {
            font-size: 18px;
            font-weight: 500;
            color: #e0e0e0;
        }
        .file-viewer-actions {
            display: flex;
            gap: 10px;
        }
        .file-viewer-actions button,
        .file-viewer-actions a {
            background-color: #8ab4f8;
            border: none;
            border-radius: 5px;
            padding: 8px 15px;
            color: #1c2526;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.1s ease;
        }
        .file-viewer-actions button:hover,
        .file-viewer-actions a:hover {
            background-color: #6b9eff;
            transform: translateY(-1px);
        }
        .file-viewer-actions button:active,
        .file-viewer-actions a:active {
            transform: translateY(1px);
        }
        .file-viewer-content {
            padding: 20px;
            background-color: #ffffff;
            max-height: 700px;
            overflow-y: auto;
        }
        .file-viewer-content iframe {
            border: none;
            width: 100%;
            height: 650px;
            background-color: #ffffff;
        }
        .file-viewer-content p {
            font-size: 14px;
            color: #333333;
            margin-bottom: 10px;
        }
        .file-viewer-content a {
            color: #8ab4f8;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        .file-viewer-content a:hover {
            color: #6b9eff;
            text-decoration: underline;
        }
        .highlight-tools {
            margin: 15px 0;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .highlight-tools button {
            background-color: #8ab4f8;
            border: none;
            border-radius: 5px;
            padding: 8px 15px;
            color: #1c2526;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.1s ease;
        }
        .highlight-tools button:hover {
            background-color: #6b9eff;
            transform: translateY(-1px);
        }
        .highlight-tools button:active {
            transform: translateY(1px);
        }
        .comment-box {
            margin-top: 15px;
            background-color: #252b2d;
            padding: 15px;
            border-radius: 8px;
            display: none;
        }
        .comment-box textarea {
            width: 100%;
            background-color: #3a3f41;
            border: 1px solid #4a4f51;
            border-radius: 8px;
            padding: 12px;
            color: #e0e0e0;
            resize: vertical;
            font-size: 14px;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .comment-box textarea:focus {
            border-color: #8ab4f8;
            box-shadow: 0 0 0 3px rgba(138, 180, 248, 0.2);
            outline: none;
        }
        .comment-box button {
            margin-top: 10px;
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
        .comment-box button:hover {
            background-color: #6b9eff;
            transform: translateY(-1px);
        }
        .comment-box button:active {
            transform: translateY(1px);
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
            .form-container select,
            .form-container button {
                width: 100%;
            }
            .file-viewer {
                max-width: 100%;
            }
            .file-viewer-content iframe {
                height: 400px;
            }
            .file-viewer-header h3 {
                font-size: 16px;
            }
            .file-viewer-actions button,
            .file-viewer-actions a {
                padding: 6px 12px;
                font-size: 12px;
            }
            .table-row .actions {
                flex-wrap: wrap;
                justify-content: space-between;
            }
            .table-row .actions button,
            .table-row .actions a {
                padding: 6px 12px;
                font-size: 12px;
            }
            .sort-container {
                flex-direction: column;
                align-items: flex-start;
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
            .file-viewer-content iframe {
                height: 300px;
            }
            .table-row .actions button,
            .table-row .actions a {
                padding: 5px 10px;
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
            <button class="new-btn" onclick="document.getElementById('create-folder-form').style.display='block'; document.getElementById('upload-file-form').style.display='block'">New</button>
            <div class="menu-item my-drive" title="Lessons">Lessons</div>
            <ul class="sub-menu">
                <?php
                function renderFolderTree($folders, $conn, $user_id, $current_folder_id = null, $sort_option) {
                    foreach ($folders as $folder) {
                        $is_active = ($folder['id'] == $current_folder_id) ? 'active' : '';
                        $has_subfolders = !empty($folder['subfolders']);
                        echo "<li class='folder-item {$is_active} " . ($has_subfolders ? 'has-subfolders' : '') . "'>";
                        echo "<a href='?page=review&folder_id={$folder['id']}&sort={$sort_option}' class='folder-link'>" . htmlspecialchars($folder['folder_name']) . "</a>";
                        if ($has_subfolders) {
                            echo "<ul class='subfolder-list'>";
                            renderFolderTree($folder['subfolders'], $conn, $user_id, $current_folder_id, $sort_option);
                            echo "</ul>";
                        }
                        echo "</li>";
                    }
                }
                renderFolderTree($all_folders, $conn, $user_id, $current_folder_id, $sort_option);
                ?>
            </ul>
            <a href="dashboard.php?page=dashboard">Home</a>
            <a href="lessons_repository.php?page=lessons">Lessons Repository</a>
            <a href="timer.php?page=timer">Timer</a>
            <a href="notes.php?page=notes">Notes</a>
            <a href="review.php?page=review" class="active">Review</a>
            <a href="reviewer_notes.php?page=reviewer_notes">Reviewer Notes</a>
            <a href="schedule.php?page=schedule">Schedule</a>
            <a href="progress.php?page=progress">Progress Tracker</a>
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
                    <a href="?page=review">Review</a>
                    <?php foreach ($breadcrumb as $crumb): ?>
                        <span>></span>
                        <a href="?page=review&folder_id=<?php echo $crumb['id']; ?>&sort=<?php echo $sort_option; ?>"><?php echo htmlspecialchars($crumb['name']); ?></a>
                    <?php endforeach; ?>
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
            <?php endif; ?>

            <div class="sort-container">
                <label for="sort">Sort by:</label>
                <select id="sort" onchange="window.location.href='?page=review<?php echo $current_folder_id ? "&folder_id=$current_folder_id" : ""; ?>&sort=' + this.value">
                    <option value="last_modified" <?php echo $sort_option == 'last_modified' ? 'selected' : ''; ?>>Last Modified</option>
                    <option value="alphabetical" <?php echo $sort_option == 'alphabetical' ? 'selected' : ''; ?>>Alphabetical</option>
                    <option value="upload_date" <?php echo $sort_option == 'upload_date' ? 'selected' : ''; ?>>Upload Date</option>
                </select>
            </div>

            <div class="form-container" id="create-folder-form" style="display: none;">
                <form method="POST">
                    <input type="text" name="folder_name" placeholder="New Folder Name" required maxlength="100">
                    <button type="submit" name="create_folder">Create Folder</button>
                </form>
            </div>
            <div class="form-container" id="upload-file-form" style="display: none;">
                <form method="POST" enctype="multipart/form-data">
                    <input type="file" name="file" required>
                    <button type="submit" name="upload">Upload File</button>
                </form>
            </div>

            <div class="table-header">
                <div>Name</div>
                <div>Owner</div>
                <div>Last Modified</div>
                <div>File Size</div>
                <div>Actions</div>
            </div>
            <?php while ($folder = $folders->fetch_assoc()): ?>
                <div class="table-row">
                    <div class="folder"><a href="?page=review&folder_id=<?php echo $folder['id']; ?>&sort=<?php echo $sort_option; ?>"><?php echo htmlspecialchars($folder['folder_name']); ?></a></div>
                    <div>me</div>
                    <div><?php echo date('Y-m-d H:i', strtotime($folder['created_at'])); ?></div>
                    <div>-</div>
                    <div></div>
                </div>
            <?php endwhile; ?>
            <?php while ($file = $files->fetch_assoc()): ?>
                <div class="table-row">
                    <div class="file">
                        <a href="#" onclick="loadFileViewer(<?php echo $file['id']; ?>, '<?php echo $file['filepath']; ?>', '<?php echo htmlspecialchars($file['filename']); ?>', '<?php echo $file['file_type']; ?>'); return false;"><?php echo htmlspecialchars($file['filename']); ?></a>
                    </div>
                    <div>me</div>
                    <div><?php echo date('Y-m-d H:i', strtotime($file['upload_date'])); ?></div>
                    <div><?php echo file_exists($file['filepath']) ? round(filesize($file['filepath']) / 1024, 2) . ' KB' : '-'; ?></div>
                    <div class="actions">
                        <?php if ($file['file_type'] == 'pdf'): ?>
                            <button class="highlight-btn" onclick="toggleHighlightTools(<?php echo $file['id']; ?>)">Highlight</button>
                        <?php endif; ?>
                        <button class="save-btn" onclick="saveAsPDF(<?php echo $file['id']; ?>)">Save</button>
                        <button class="comment-btn" onclick="toggleCommentBox(<?php echo $file['id']; ?>)">Comment</button>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this file?');">
                            <input type="hidden" name="file_id" value="<?php echo $file['id']; ?>">
                            <button type="submit" name="delete_file" class="delete-btn">Delete</button>
                        </form>
                    </div>
                </div>
                <div class="file-viewer" id="file-viewer-<?php echo $file['id']; ?>">
                    <div class="file-viewer-header">
                        <h3><?php echo htmlspecialchars($file['filename']); ?></h3>
                        <div class="file-viewer-actions">
                            <a href="<?php echo $file['filepath']; ?>" download>Download</a>
                            <button onclick="document.getElementById('file-viewer-<?php echo $file['id']; ?>').style.display='none'">Close</button>
                        </div>
                    </div>
                    <div class="file-viewer-content">
                        <?php if ($file['file_type'] == 'pdf'): ?>
                            <div class="highlight-tools" id="highlight-tools-<?php echo $file['id']; ?>" style="display: none;">
                                <button onclick="setHighlightColor('yellow', <?php echo $file['id']; ?>)">Yellow</button>
                                <button onclick="setHighlightColor('green', <?php echo $file['id']; ?>)">Green</button>
                                <button onclick="setHighlightColor('blue', <?php echo $file['id']; ?>)">Blue</button>
                            </div>
                            <iframe id="pdf-iframe-<?php echo $file['id']; ?>" src="<?php echo $file['filepath']; ?>"></iframe>
                        <?php elseif (in_array($file['file_type'], ['ppt', 'pptx'])): 
                            $file_url = urlencode('http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/' . $file['filepath']);
                            $office_viewer_url = "https://view.officeapps.live.com/op/embed.aspx?src={$file_url}";
                        ?>
                            <?php if (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false): ?>
                                <p>PowerPoint preview is not available locally. To view this file in the browser, host it on a public server or use a tool like <a href="https://ngrok.com/" target="_blank">ngrok</a> to expose your local server. Alternatively, download the file and open it in PowerPoint.</p>
                                <a href="<?php echo $file['filepath']; ?>" download>Download <?php echo htmlspecialchars($file['filename']); ?></a>
                            <?php else: ?>
                                <iframe id="ppt-iframe-<?php echo $file['id']; ?>" src="<?php echo $office_viewer_url; ?>"></iframe>
                            <?php endif; ?>
                        <?php else: ?>
                            <p>Preview not available for <?php echo $file['file_type']; ?> files. <a href="<?php echo $file['filepath']; ?>" download>Download</a></p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="comment-box" id="comment-box-<?php echo $file['id']; ?>">
                    <form method="POST">
                        <textarea name="comments" placeholder="Add a comment..."></textarea>
                        <input type="hidden" name="file_id" value="<?php echo $file['id']; ?>">
                        <button type="submit" name="save_review">Save Comment</button>
                    </form>
                </div>
            <?php endwhile; ?>
            <?php if ($files->num_rows == 0 && $folders->num_rows == 0): ?>
                <div class="table-row">
                    <div>No files or folders available.</div>
                    <div></div>
                    <div></div>
                    <div></div>
                    <div></div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.9.359/pdf.min.js"></script>
    <script>
        let currentHighlightColor = 'yellow';
        let highlights = {};
        let currentFileId = null;

        document.addEventListener('DOMContentLoaded', function() {
            const fileInput = document.querySelector('input[type="file"]');
            const maxSize = <?php echo $max_file_size; ?>;

            fileInput.addEventListener('change', function() {
                if (this.files[0].size > maxSize) {
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
                const folderId = <?php echo json_encode($current_folder_id); ?>;
                const shareLink = folderId ? 
                    `https://drive.google.com/share/review-folder-${folderId}` : 
                    'https://drive.google.com/share/review-root';
                alert('Review files shared to Google Drive! Shareable link: ' + shareLink);
                navigator.clipboard.writeText(shareLink);
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

        function loadFileViewer(fileId, filePath, filename, fileType) {
            currentFileId = fileId;
            const viewer = document.getElementById(`file-viewer-${fileId}`);
            viewer.style.display = 'block';
            if (fileType === 'pdf') {
                const iframe = document.getElementById(`pdf-iframe-${fileId}`);
                if (iframe) iframe.src = filePath;
            } else if (fileType === 'ppt' || fileType === 'pptx') {
                const iframe = document.getElementById(`ppt-iframe-${fileId}`);
                if (iframe) iframe.src = iframe.src; // Refresh iframe if already set
            }
        }

        function toggleHighlightTools(fileId) {
            const tools = document.getElementById(`highlight-tools-${fileId}`);
            tools.style.display = tools.style.display === 'flex' ? 'none' : 'flex';
        }

        function toggleCommentBox(fileId) {
            const commentBox = document.getElementById(`comment-box-${fileId}`);
            commentBox.style.display = commentBox.style.display === 'block' ? 'none' : 'block';
        }

        function setHighlightColor(color, fileId) {
            currentHighlightColor = color;
            alert(`Highlighting set to ${color}. Note: Full highlighting functionality requires additional backend integration.`);
            // Implement client-side highlighting here if needed
        }

        function saveAsPDF(fileId) {
            if (!highlights[fileId] || highlights[fileId].length === 0) {
                alert("No highlights to save. Highlight functionality requires additional backend setup.");
                return;
            }
            // Add save logic here if backend is fully implemented
        }
    </script>
</body>
</html>

<?php
$conn->close();
?>