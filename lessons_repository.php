<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'db.php'; // Include the database connection

$user_id = $_SESSION['user_id'];

// Ensure uploads directory exists
$upload_dir = 'uploads/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Success/Error messages (stored in session temporarily)
if (!isset($_SESSION['messages'])) {
    $_SESSION['messages'] = [];
}
$messages = &$_SESSION['messages'];
$max_file_size = 5 * 1024 * 1024 * 1024; // 5GB in bytes

// Get current folder ID from URL (if any)
$current_folder_id = isset($_GET['folder_id']) && $_GET['folder_id'] !== '' ? (int)$_GET['folder_id'] : null;

// Get sort option from URL (default to 'last_modified')
$sort_option = isset($_GET['sort']) ? $_GET['sort'] : 'last_modified';
$valid_sort_options = ['last_modified', 'alphabetical', 'upload_date'];
if (!in_array($sort_option, $valid_sort_options)) {
    $sort_option = 'last_modified'; // Fallback to default
}

// Function to redirect to 404 page
function redirectTo404() {
    header("HTTP/1.0 404 Not Found");
    header("Location: 404.php");
    exit();
}

// Function to delete files and folders recursively
function deleteItem($conn, $user_id, $type, $id) {
    global $messages;
    
    try {
        if ($type === 'file') {
            $stmt = $conn->prepare("SELECT filepath FROM lesson_files WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows == 1) {
                $file = $result->fetch_assoc();
                $stmt = $conn->prepare("DELETE FROM lesson_files WHERE id = ? AND user_id = ?");
                $stmt->bind_param("ii", $id, $user_id);
                if ($stmt->execute() && unlink($file['filepath'])) {
                    $messages['success'] = "File deleted successfully!";
                } else {
                    $messages['error'] = "Failed to delete file!";
                }
            } else {
                redirectTo404();
            }
            $stmt->close();
        } elseif ($type === 'folder') {
            $stmt = $conn->prepare("SELECT id FROM lesson_files WHERE folder_id = ? AND user_id = ?");
            $stmt->bind_param("ii", $id, $user_id);
            $stmt->execute();
            $files = $stmt->get_result();
            while ($file = $files->fetch_assoc()) {
                deleteItem($conn, $user_id, 'file', $file['id']);
            }
            $stmt->close();

            $stmt = $conn->prepare("SELECT id FROM lesson_folders WHERE parent_folder_id = ? AND user_id = ?");
            $stmt->bind_param("ii", $id, $user_id);
            $stmt->execute();
            $subfolders = $stmt->get_result();
            while ($folder = $subfolders->fetch_assoc()) {
                deleteItem($conn, $user_id, 'folder', $folder['id']);
            }
            $stmt->close();

            $stmt = $conn->prepare("DELETE FROM lesson_folders WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $id, $user_id);
            if ($stmt->execute()) {
                $messages['success'] = "Folder deleted successfully!";
            } else {
                $messages['error'] = "Failed to delete folder!";
            }
            $stmt->close();
        }
    } catch (Exception $e) {
        $messages['error'] = "Error during deletion: " . $e->getMessage();
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $redirect_url = "?page=lessons" . ($current_folder_id ? "&folder_id=$current_folder_id" : "") . "&sort=$sort_option";

    if (isset($_POST['create_folder']) && isset($_POST['folder_name'])) {
        $folder_name = trim($_POST['folder_name']);
        if (!empty($folder_name)) {
            $stmt = $conn->prepare("INSERT INTO lesson_folders (user_id, folder_name, parent_folder_id) VALUES (?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("isi", $user_id, $folder_name, $current_folder_id);
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

    // Handle single file upload
    if (isset($_FILES['file']) && !empty($_FILES['file']['name']) && !isset($_FILES['folder_files'])) {
        $file_size = $_FILES['file']['size'];

        if ($file_size > $max_file_size) {
            $messages['error'] = "File size exceeds the maximum limit of 5GB!";
        } else {
            $filename = $_FILES['file']['name'];
            $file_ext = pathinfo($filename, PATHINFO_EXTENSION);
            $filepath = $upload_dir . uniqid() . '_' . $filename;
            if (move_uploaded_file($_FILES['file']['tmp_name'], $filepath)) {
                $folder_id = $current_folder_id;
                $stmt = $conn->prepare("INSERT INTO lesson_files (user_id, folder_id, filename, filepath, file_type) VALUES (?, ?, ?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param("iisss", $user_id, $folder_id, $filename, $filepath, $file_ext);
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
        }
    }

    // Handle folder upload (multiple files)
    if (isset($_FILES['folder_files']) && !empty($_FILES['folder_files']['name'][0])) {
        $file_count = count($_FILES['folder_files']['name']);
        $success_count = 0;

        for ($i = 0; $i < $file_count; $i++) {
            if ($_FILES['folder_files']['error'][$i] == UPLOAD_ERR_OK) {
                $file_size = $_FILES['folder_files']['size'][$i];

                if ($file_size > $max_file_size) {
                    $messages['error'] .= "File '{$_FILES['folder_files']['name'][$i]}' exceeds the maximum limit of 5GB!<br>";
                    continue;
                }

                $filename = $_FILES['folder_files']['name'][$i];
                $file_ext = pathinfo($filename, PATHINFO_EXTENSION);
                $filepath = $upload_dir . uniqid() . '_' . $filename;

                if (move_uploaded_file($_FILES['folder_files']['tmp_name'][$i], $filepath)) {
                    $folder_id = $current_folder_id;
                    $stmt = $conn->prepare("INSERT INTO lesson_files (user_id, folder_id, filename, filepath, file_type) VALUES (?, ?, ?, ?, ?)");
                    if ($stmt) {
                        $stmt->bind_param("iisss", $user_id, $folder_id, $filename, $filepath, $file_ext);
                        if ($stmt->execute()) {
                            $success_count++;
                        } else {
                            $messages['error'] .= "Failed to upload file '$filename': " . $conn->error . "<br>";
                            unlink($filepath); // Clean up if DB insert fails
                        }
                        $stmt->close();
                    }
                } else {
                    $messages['error'] .= "Failed to move file '$filename'!<br>";
                }
            }
        }

        if ($success_count > 0) {
            $messages['success'] = "Successfully uploaded $success_count file(s) from folder!";
        }
        header("Location: $redirect_url");
        exit();
    }

    if (isset($_POST['delete_item']) && isset($_POST['item_type']) && isset($_POST['item_id'])) {
        deleteItem($conn, $user_id, $_POST['item_type'], $_POST['item_id']);
        header("Location: $redirect_url");
        exit();
    }
}

// Determine sort column and order
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
        $folder_sort = "ORDER BY created_at DESC"; // Assuming last modified is same as created_at for folders
        $file_sort = "ORDER BY upload_date DESC"; // Assuming last modified is same as upload_date for files
        break;
}

// Fetch folders and files with 404 check
$folder_query = "SELECT * FROM lesson_folders WHERE user_id = ? AND parent_folder_id " . ($current_folder_id ? "= ?" : "IS NULL") . " $folder_sort";
$stmt = $conn->prepare($folder_query);
if ($stmt) {
    if ($current_folder_id) {
        $stmt->bind_param("ii", $user_id, $current_folder_id);
    } else {
        $stmt->bind_param("i", $user_id);
    }
    $stmt->execute();
    $folders = $stmt->get_result();
    if ($current_folder_id && $folders->num_rows == 0) {
        $check_stmt = $conn->prepare("SELECT id FROM lesson_folders WHERE id = ? AND user_id = ?");
        $check_stmt->bind_param("ii", $current_folder_id, $user_id);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows == 0) {
            redirectTo404();
        }
        $check_stmt->close();
    }
} else {
    redirectTo404();
}

$file_query = "SELECT f.*, lf.folder_name FROM lesson_files f LEFT JOIN lesson_folders lf ON f.folder_id = lf.id WHERE f.user_id = ? AND f.folder_id " . ($current_folder_id ? "= ?" : "IS NULL") . " $file_sort";
$stmt = $conn->prepare($file_query);
if ($stmt) {
    if ($current_folder_id) {
        $stmt->bind_param("ii", $user_id, $current_folder_id);
    } else {
        $stmt->bind_param("i", $user_id);
    }
    $stmt->execute();
    $files = $stmt->get_result();
} else {
    redirectTo404();
}

// Build breadcrumb with 404 check
$breadcrumb = [];
if ($current_folder_id) {
    $folder_id = $current_folder_id;
    while ($folder_id !== null) {
        $stmt = $conn->prepare("SELECT id, folder_name, parent_folder_id FROM lesson_folders WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $folder_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $breadcrumb[] = ['id' => $row['id'], 'name' => $row['folder_name']];
            $folder_id = $row['parent_folder_id'];
        } else {
            redirectTo404();
            break;
        }
        $stmt->close();
    }
    $breadcrumb = array_reverse($breadcrumb);
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
    <title>Lessons Repository - White Room</title>
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
        .delete-btn {
            background-color: #ff6b6b;
            border: none;
            border-radius: 8px;
            padding: 8px 15px;
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
            .delete-btn {
                padding: 6px 12px;
                font-size: 12px;
            }
            .file-viewer-content iframe {
                height: 300px;
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
                function renderFolderTree($folders, $conn, $user_id, $current_folder_id) {
                    foreach ($folders as $folder) {
                        $is_active = $current_folder_id === $folder['id'] ? 'active' : '';
                        $has_subfolders = !empty($folder['subfolders']);
                        echo "<li class='folder-item {$is_active} " . ($has_subfolders ? 'has-subfolders' : '') . "'>";
                        echo "<a href='?page=lessons&folder_id={$folder['id']}' class='folder-link'>" . htmlspecialchars($folder['folder_name']) . "</a>";
                        if ($has_subfolders) {
                            echo "<ul class='subfolder-list'>";
                            renderFolderTree($folder['subfolders'], $conn, $user_id, $current_folder_id);
                            echo "</ul>";
                        }
                        echo "</li>";
                    }
                }
                renderFolderTree($all_folders, $conn, $user_id, $current_folder_id);
                ?>
            </ul>
            <a href="dashboard.php">Home</a>
            <a href="lessons_repository.php" class="active">Lessons Repository</a>
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
                    <a href="?page=lessons">Lessons Repository</a>
                    <?php foreach ($breadcrumb as $crumb): ?>
                        <span>></span>
                        <a href="?page=lessons&folder_id=<?php echo $crumb['id']; ?>"><?php echo htmlspecialchars($crumb['name']); ?></a>
                    <?php endforeach; ?>
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

            <div class="sort-container">
                <label for="sort">Sort by:</label>
                <select id="sort" onchange="window.location.href='?page=lessons<?php echo $current_folder_id ? "&folder_id=$current_folder_id" : ""; ?>&sort=' + this.value">
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
            <div class="form-container">
                <form method="POST" enctype="multipart/form-data">
                    <input type="file" name="file" accept="*/*">
                    <button type="submit" name="upload">Upload File</button>
                </form>
            </div>
            <div class="form-container">
                <form method="POST" enctype="multipart/form-data">
                    <input type="file" name="folder_files[]" webkitdirectory mozdirectory directory multiple>
                    <button type="submit" name="upload_folder">Upload Folder</button>
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
                    <div class="folder"><a href="?page=lessons&folder_id=<?php echo $folder['id']; ?>&sort=<?php echo $sort_option; ?>"><?php echo htmlspecialchars($folder['folder_name']); ?></a></div>
                    <div>me</div>
                    <div><?php echo date('Y-m-d H:i', strtotime($folder['created_at'])); ?></div>
                    <div>-</div>
                    <div>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this folder and all its contents?');">
                            <input type="hidden" name="item_type" value="folder">
                            <input type="hidden" name="item_id" value="<?php echo $folder['id']; ?>">
                            <button type="submit" name="delete_item" class="delete-btn">Delete</button>
                        </form>
                    </div>
                </div>
            <?php endwhile; ?>
            <?php while ($file = $files->fetch_assoc()): ?>
                <div class="table-row">
                    <div class="file"><a href="?page=lessons&view_file=<?php echo $file['id']; ?><?php echo $current_folder_id ? '&folder_id=' . $current_folder_id : ''; ?>&sort=<?php echo $sort_option; ?>"><?php echo htmlspecialchars($file['filename']); ?></a></div>
                    <div>me</div>
                    <div><?php echo date('Y-m-d H:i', strtotime($file['upload_date'])); ?></div>
                    <div><?php echo file_exists($file['filepath']) ? round(filesize($file['filepath']) / 1024, 2) . ' KB' : '-'; ?></div>
                    <div>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this file?');">
                            <input type="hidden" name="item_type" value="file">
                            <input type="hidden" name="item_id" value="<?php echo $file['id']; ?>">
                            <button type="submit" name="delete_item" class="delete-btn">Delete</button>
                        </form>
                    </div>
                </div>
            <?php endwhile; ?>

            <?php
            if (isset($_GET['view_file'])) {
                $file_id = $_GET['view_file'];
                $stmt = $conn->prepare("SELECT filename, filepath, file_type FROM lesson_files WHERE id = ? AND user_id = ?");
                if ($stmt) {
                    $stmt->bind_param("ii", $file_id, $user_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result->num_rows == 1) {
                        $file = $result->fetch_assoc();
                        $return_url = "?page=lessons" . ($current_folder_id ? "&folder_id=$current_folder_id" : "") . "&sort=$sort_option";
                        $file_type = strtolower($file['file_type']); // Ensure case-insensitive comparison
                        $file_url = urlencode('http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/' . $file['filepath']);

                        echo "<div class='file-viewer'>";
                        echo "<div class='file-viewer-header'>";
                        echo "<h3>" . htmlspecialchars($file['filename']) . "</h3>";
                        echo "<div class='file-viewer-actions'>";
                        echo "<a href='{$file['filepath']}' download>Download</a>";
                        echo "<button onclick=\"window.location.href='$return_url'\">Close</button>";
                        echo "</div>";
                        echo "</div>";
                        echo "<div class='file-viewer-content'>";

                        // Handle different file types
                        if ($file_type === 'pdf') {
                            echo "<iframe src='{$file['filepath']}'></iframe>";
                        } elseif (in_array($file_type, ['ppt', 'pptx'])) {
                            // Use Microsoft Office Viewer for PowerPoint files
                            $office_viewer_url = "https://view.officeapps.live.com/op/embed.aspx?src={$file_url}";
                            // Check if running on localhost
                            if (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false) {
                                echo "<p>PowerPoint preview is not available locally. To view this file in the browser, host it on a public server or use a tool like <a href='https://ngrok.com/' target='_blank'>ngrok</a> to expose your local server. Alternatively, download the file and open it in PowerPoint.</p>";
                                echo "<a href='{$file['filepath']}' download>Download {$file['filename']}</a>";
                            } else {
                                echo "<iframe src='{$office_viewer_url}'></iframe>";
                            }
                        } else {
                            echo "<p>Preview not available for this file type ({$file['file_type']}). <a href='{$file['filepath']}' download>Download</a></p>";
                        }

                        echo "</div>";
                        echo "</div>";
                    } else {
                        redirectTo404();
                    }
                    $stmt->close();
                } else {
                    redirectTo404();
                }
            }
            ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const fileInput = document.querySelector('input[name="file"]');
            const folderInput = document.querySelector('input[name="folder_files[]"]');
            const maxSize = <?php echo $max_file_size; ?>;

            fileInput.addEventListener('change', function() {
                if (this.files[0] && this.files[0].size > maxSize) {
                    alert('File size exceeds the maximum limit of 5GB!');
                    this.value = '';
                }
            });

            folderInput.addEventListener('change', function() {
                for (let i = 0; i < this.files.length; i++) {
                    if (this.files[i].size > maxSize) {
                        alert(`File "${this.files[i].name}" exceeds the maximum limit of 5GB!`);
                        this.value = '';
                        break;
                    }
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
                const folderId = '<?php echo $current_folder_id ? $current_folder_id : "root"; ?>';
                const shareLink = 'https://drive.google.com/share/lessons-repository-' + folderId;
                alert('Lessons Repository shared to Google Drive! Shareable link: ' + shareLink);
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

<?php $conn->close(); ?>