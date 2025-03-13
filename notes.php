<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
require_once 'db.php';

if (!isset($_SESSION['messages'])) {
    $_SESSION['messages'] = [];
}
$messages = &$_SESSION['messages'];

$user_id = $_SESSION['user_id'];

// Handle both save and update
if (isset($_POST['save_note'])) {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $note_id = isset($_POST['note_id']) ? (int)$_POST['note_id'] : null;

    if (!empty($title) && !empty($content)) {
        if ($note_id) {
            // Update existing note
            $stmt = $conn->prepare("UPDATE notes SET title = ?, content = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
            if ($stmt) {
                $stmt->bind_param("ssii", $title, $content, $note_id, $user_id);
                if ($stmt->execute()) {
                    $messages['success'] = "Note '$title' updated successfully!";
                    header("Location: notes.php?page=notes");
                    exit();
                } else {
                    $messages['error'] = "Failed to update note: " . $conn->error;
                }
                $stmt->close();
            }
        } else {
            // Save new note
            $stmt = $conn->prepare("INSERT INTO notes (user_id, title, content) VALUES (?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("iss", $user_id, $title, $content);
                if ($stmt->execute()) {
                    $messages['success'] = "Note '$title' saved successfully!";
                    header("Location: notes.php?page=notes");
                    exit();
                } else {
                    $messages['error'] = "Failed to save note: " . $conn->error;
                }
                $stmt->close();
            }
        }
    } else {
        $messages['error'] = "Title and content cannot be empty!";
    }
}

if (isset($_POST['delete_note'])) {
    $note_id = (int)$_POST['note_id'];
    $stmt = $conn->prepare("DELETE FROM notes WHERE id = ? AND user_id = ?");
    if ($stmt) {
        $stmt->bind_param("ii", $note_id, $user_id);
        if ($stmt->execute()) {
            $messages['success'] = "Note deleted successfully!";
            header("Location: notes.php?page=notes");
            exit();
        } else {
            $messages['error'] = "Failed to delete note: " . $conn->error;
        }
        $stmt->close();
    }
}

$stmt = $conn->prepare("SELECT * FROM notes WHERE user_id = ? ORDER BY created_at DESC");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $notes = $stmt->get_result();
} else {
    $messages['error'] = "Error fetching notes: " . $conn->error;
    $notes = null;
}

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
    <title>White Room - Notes</title>
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
        .note-editor {
            background-color: #212829;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            border: 1px solid #3a3f41;
        }
        .note-editor input {
            width: 100%;
            background-color: #252b2d;
            border: 1px solid #3a3f41;
            border-radius: 8px;
            padding: 12px 15px;
            color: #e0e0e0;
            font-size: 14px;
            margin-bottom: 15px;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .note-editor input:focus {
            border-color: #8ab4f8;
            box-shadow: 0 0 0 3px rgba(138, 180, 248, 0.2);
            outline: none;
        }
        .note-editor .toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 15px;
            padding: 10px;
            background-color: #252b2d;
            border-radius: 8px;
            border: 1px solid #3a3f41;
        }
        .note-editor .toolbar button,
        .note-editor .toolbar select,
        .note-editor .toolbar input[type="color"] {
            background-color: #3a3f41;
            border: 1px solid #4a4f51;
            border-radius: 6px;
            padding: 8px 12px;
            color: #e0e0e0;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s ease, transform 0.1s ease;
        }
        .note-editor .toolbar button:hover,
        .note-editor .toolbar select:hover,
        .note-editor .toolbar input[type="color"]:hover {
            background-color: #4a4f51;
            transform: translateY(-1px);
        }
        .note-editor .toolbar button:active,
        .note-editor .toolbar select:active,
        .note-editor .toolbar input[type="color"]:active {
            transform: translateY(1px);
        }
        .note-editor .toolbar button.active {
            background-color: #8ab4f8;
            color: #1c2526;
        }
        .note-editor .toolbar select {
            padding: 8px;
            appearance: none;
            background-image: url('data:image/svg+xml;utf8,<svg fill="white" height="24" viewBox="0 0 24 24" width="24" xmlns="http://www.w3.org/2000/svg"><path d="M7 10l5 5 5-5z"/></svg>');
            background-repeat: no-repeat;
            background-position: right 8px center;
            background-size: 16px;
            min-width: 100px;
        }
        .note-editor .toolbar input[type="color"] {
            padding: 2px;
            width: 40px;
            height: 34px;
        }
        .note-editor .paper-container {
            background-color: #ffffff;
            border: 1px solid #d3d3d3;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            padding: 20px;
            width: 100%;
            max-width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            position: relative;
            overflow: hidden;
        }
        .note-editor #note-content {
            width: 100%;
            min-height: 297mm;
            background: transparent;
            border: none;
            padding: 0;
            color: #000000;
            resize: none;
            font-size: 14px;
            outline: none;
            line-height: 1.6;
        }
        .note-editor #note-content:focus {
            border: none;
            box-shadow: none;
        }
        .note-editor #note-content:empty::before {
            content: "Start typing your note here...";
            color: #6b6b6b;
            font-style: italic;
        }
        .note-editor #note-content ul,
        .note-editor #note-content ol {
            margin: 0 0 0 20px;
            padding: 0 0 0 20px;
            list-style-position: inside;
        }
        .note-editor #note-content li {
            margin: 5px 0;
            padding-left: 5px;
        }
        .note-editor .save-btn {
            background-color: #8ab4f8;
            border: none;
            border-radius: 8px;
            padding: 12px 20px;
            color: #1c2526;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            margin-top: 15px;
            transition: background-color 0.3s ease, transform 0.1s ease;
        }
        .note-editor .save-btn:hover {
            background-color: #6b9eff;
            transform: translateY(-1px);
        }
        .note-editor .save-btn:active {
            transform: translateY(1px);
        }
        .note {
            background-color: #252b2d;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .note:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }
        .note:last-child {
            margin-bottom: 0;
        }
        .note h3 {
            font-size: 18px;
            color: #8ab4f8;
            font-weight: 500;
            flex-grow: 1;
            margin-right: 15px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .note .actions {
            display: flex;
            gap: 10px;
            flex-shrink: 0;
        }
        .note .actions button,
        .note .actions a {
            padding: 10px 15px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            transition: background-color 0.3s ease, transform 0.1s ease;
        }
        .note .quick-view-btn {
            background-color: #8ab4f8;
            border: none;
            color: #1c2526;
        }
        .note .quick-view-btn:hover {
            background-color: #6b9eff;
            transform: translateY(-1px);
        }
        .note .quick-view-btn:active {
            transform: translateY(1px);
        }
        .note .edit-btn {
            background-color: #ffa726;
            border: none;
            color: #ffffff;
        }
        .note .edit-btn:hover {
            background-color: #fb8c00;
            transform: translateY(-1px);
        }
        .note .edit-btn:active {
            transform: translateY(1px);
        }
        .note .view-btn {
            background-color: #4CAF50;
            color: #ffffff;
        }
        .note .view-btn:hover {
            background-color: #388e3c;
            transform: translateY(-1px);
        }
        .note .view-btn:active {
            transform: translateY(1px);
        }
        .note .delete-btn {
            background-color: #ff6b6b;
            border: none;
            color: #ffffff;
        }
        .note .delete-btn:hover {
            background-color: #e55a5a;
            transform: translateY(-1px);
        }
        .note .delete-btn:active {
            transform: translateY(1px);
        }
        .delete-form {
            display: inline-block;
            margin: 0;
        }
        .modal {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0);
            background-color: #ffffff;
            color: #000000;
            padding: 0;
            border-radius: 12px;
            z-index: 1000;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
            border: 1px solid #e0e0e0;
            width: 90%;
            max-width: 800px;
            max-height: 85vh;
            flex-direction: column;
            animation: popup 0.3s ease-out forwards;
        }
        .modal.show {
            display: flex;
            transform: translate(-50%, -50%) scale(1);
        }
        @keyframes popup {
            from {
                transform: translate(-50%, -50%) scale(0.9);
                opacity: 0;
            }
            to {
                transform: translate(-50%, -50%) scale(1);
                opacity: 1;
            }
        }
        .modal-header {
            background-color: #f5f5f5;
            padding: 15px 20px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top-left-radius: 12px;
            border-top-right-radius: 12px;
        }
        .modal-header h3 {
            font-size: 20px;
            font-weight: 600;
            color: #333333;
            margin: 0;
        }
        .modal-header .close-btn {
            background: none;
            border: none;
            font-size: 24px;
            color: #666666;
            cursor: pointer;
            padding: 0;
            transition: color 0.3s ease;
        }
        .modal-header .close-btn:hover {
            color: #ff6b6b;
        }
        .modal-content {
            padding: 20px;
            flex-grow: 1;
            overflow-y: auto;
            background-color: #ffffff;
            border-bottom-left-radius: 12px;
            border-bottom-right-radius: 12px;
        }
        .modal-content .note-preview {
            font-size: 14px;
            line-height: 1.6;
            color: #000000;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .modal-content .note-preview ul,
        .modal-content .note-preview ol {
            margin: 0 0 0 20px;
            padding: 0 0 0 20px;
            list-style-position: inside;
        }
        .modal-content .note-preview li {
            margin: 5px 0;
            padding-left: 5px;
        }
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
            .note-editor {
                padding: 15px;
            }
            .note-editor .toolbar {
                gap: 6px;
                padding: 8px;
            }
            .note-editor .toolbar button,
            .note-editor .toolbar select,
            .note-editor .toolbar input[type="color"] {
                padding: 6px 10px;
                font-size: 12px;
            }
            .note-editor .paper-container {
                padding: 15px;
                max-width: 100%;
                min-height: 200px;
            }
            .note-editor #note-content {
                min-height: 200px;
            }
            .note {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            .note h3 {
                font-size: 16px;
            }
            .note .actions {
                width: 100%;
                justify-content: space-between;
            }
            .note .actions button,
            .note .actions a {
                flex: 1;
                padding: 10px;
                font-size: 12px;
            }
            .modal {
                width: 90%;
                max-width: 500px;
            }
            .modal-header h3 {
                font-size: 18px;
            }
            .modal-content .note-preview {
                font-size: 12px;
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
            .note-editor .toolbar {
                gap: 4px;
            }
            .note-editor .toolbar button,
            .note-editor .toolbar select,
            .note-editor .toolbar input[type="color"] {
                padding: 6px 8px;
                font-size: 10px;
            }
            .note-editor .paper-container {
                padding: 10px;
                min-height: 150px;
            }
            .note-editor #note-content {
                min-height: 150px;
                font-size: 12px;
            }
            .note-editor .save-btn {
                padding: 10px 15px;
                font-size: 12px;
            }
            .note h3 {
                font-size: 14px;
            }
            .note .actions button,
            .note .actions a {
                padding: 8px;
                font-size: 10px;
            }
            .modal {
                max-width: 350px;
            }
            .modal-header h3 {
                font-size: 16px;
            }
            .modal-content .note-preview {
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
            <button class="new-btn" onclick="document.getElementById('note-editor-form').scrollIntoView({behavior: 'smooth'})">New Note</button>
            <div class="menu-item my-drive" title="Lessons">Lessons</div>
            <ul class="sub-menu">
                <?php
                function renderFolderTree($folders, $conn, $user_id) {
                    foreach ($folders as $folder) {
                        $is_active = '';
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
            <a href="timer.php" class="<?php echo isset($_GET['page']) && $_GET['page'] == 'timer' ? 'active' : ''; ?>">Timer</a>
            <a href="notes.php?page=notes" class="active">Notes</a>
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
                    <a href="notes.php?page=notes">Notes</a>
                </div>
                <div class="actions">
                    <button class="menu-btn" title="Menu">☰</button>
                    <div class="dropdown">
                        <a href="logout.php">Logout</a>
                        <a href="change_account.php">Change Account</a>
                        <a href="index.php?new_login=true">Sign in to Another Account</a>
                    </div>
                    <button class="share-btn" title="Images">🖼️</button>
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
                <?php unset($_SESSION['messages']); ?>
            <?php endif; ?>

            <div class="note-editor" id="note-editor-form">
                <form method="POST">
                    <input type="text" name="title" id="note-title" placeholder="Note Title" required>
                    <input type="hidden" name="note_id" id="note-id">
                    <div class="toolbar">
                        <button type="button" onclick="document.execCommand('bold', false, null);" title="Bold"><b>B</b></button>
                        <button type="button" onclick="document.execCommand('italic', false, null);" title="Italic"><i>I</i></button>
                        <button type="button" onclick="document.execCommand('underline', false, null);" title="Underline"><u>U</u></button>
                        <button type="button" onclick="document.execCommand('strikeThrough', false, null);" title="Strikethrough"><s>S</s></button>
                        <button type="button" onclick="document.execCommand('superscript', false, null);" title="Superscript">x<sup>2</sup></button>
                        <button type="button" onclick="document.execCommand('subscript', false, null);" title="Subscript">x<sub>2</sub></button>
                        <button type="button" onclick="insertTab();" title="Tab">↹</button>
                        <button type="button" onclick="document.execCommand('insertUnorderedList', false, null);" title="Bullets">•</button>
                        <button type="button" onclick="document.execCommand('insertOrderedList', false, null);" title="Numbering">1.</button>
                        <button type="button" onclick="document.execCommand('indent', false, null);" title="Indent">→</button>
                        <button type="button" onclick="document.execCommand('outdent', false, null);" title="Outdent">←</button>
                        <button type="button" onclick="document.execCommand('justifyLeft', false, null);" title="Align Left">↶</button>
                        <button type="button" onclick="document.execCommand('justifyCenter', false, null);" title="Align Center">↔</button>
                        <button type="button" onclick="document.execCommand('justifyRight', false, null);" title="Align Right">↷</button>
                        <button type="button" onclick="document.execCommand('justifyFull', false, null);" title="Justify">≡</button>
                        <select onchange="document.execCommand('fontSize', false, this.value); this.selectedIndex = 0;">
                            <option value="">Size</option>
                            <option value="1">8pt</option>
                            <option value="2">10pt</option>
                            <option value="3">12pt</option>
                            <option value="4">14pt</option>
                            <option value="5">18pt</option>
                            <option value="6">24pt</option>
                            <option value="7">36pt</option>
                        </select>
                        <select onchange="document.execCommand('fontName', false, this.value); this.selectedIndex = 0;">
                            <option value="">Font</option>
                            <option value="Arial">Arial</option>
                            <option value="Times New Roman">Times New Roman</option>
                            <option value="Courier New">Courier New</option>
                            <option value="Georgia">Georgia</option>
                            <option value="Verdana">Verdana</option>
                            <option value="Calibri">Calibri</option>
                            <option value="Roboto">Roboto</option>
                        </select>
                        <select onchange="document.execCommand('formatBlock', false, this.value); this.selectedIndex = 0;">
                            <option value="">Style</option>
                            <option value="h1">Heading 1</option>
                            <option value="h2">Heading 2</option>
                            <option value="h3">Heading 3</option>
                            <option value="p">Paragraph</option>
                            <option value="pre">Preformatted</option>
                        </select>
                        <input type="color" onchange="document.execCommand('foreColor', false, this.value);" title="Text Color" value="#000000">
                        <button type="button" onclick="document.execCommand('hiliteColor', false, '#FFFF00');" style="background-color: #FFFF00;" title="Yellow Highlight"></button>
                        <button type="button" onclick="document.execCommand('hiliteColor', false, '#00FF00');" style="background-color: #00FF00;" title="Green Highlight"></button>
                        <button type="button" onclick="document.execCommand('hiliteColor', false, '#FF00FF');" style="background-color: #FF00FF;" title="Pink Highlight"></button>
                        <button type="button" onclick="document.execCommand('removeFormat', false, null);" title="Clear Formatting">✗</button>
                    </div>
                    <div class="paper-container">
                        <div contenteditable="true" id="note-content" oninput="document.getElementById('hidden-content').value = this.innerHTML;"></div>
                    </div>
                    <input type="hidden" name="content" id="hidden-content">
                    <button type="submit" name="save_note" class="save-btn" id="save-btn">Save Note</button>
                </form>
            </div>

            <?php if ($notes && $notes->num_rows > 0): ?>
                <div class="section-title">Saved Notes</div>
                <?php while ($note = $notes->fetch_assoc()): ?>
                    <div class="note">
                        <h3><?php echo htmlspecialchars($note['title']); ?></h3>
                        <div class="actions">
                            <button class="quick-view-btn" 
                                    data-note-id="<?php echo $note['id']; ?>" 
                                    data-title="<?php echo htmlspecialchars($note['title'], ENT_QUOTES); ?>" 
                                    data-content="<?php echo htmlspecialchars($note['content'], ENT_QUOTES); ?>">
                                Quick View
                            </button>
                            <button class="edit-btn" 
                                    data-note-id="<?php echo $note['id']; ?>" 
                                    data-title="<?php echo htmlspecialchars($note['title'], ENT_QUOTES); ?>" 
                                    data-content="<?php echo htmlspecialchars($note['content'], ENT_QUOTES); ?>">
                                Edit
                            </button>
                            <a href="reviewer_notes.php?page=reviewer_notes&note_id=<?php echo $note['id']; ?>" 
                               class="view-btn">View</a>
                            <form method="POST" class="delete-form">
                                <input type="hidden" name="note_id" value="<?php echo $note['id']; ?>">
                                <button type="submit" name="delete_note" 
                                        class="delete-btn" 
                                        onclick="return confirm('Are you sure you want to delete this note?');">
                                    Delete
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="note">
                    <p>No notes available.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div id="note-modal" class="modal">
        <div class="modal-header">
            <h3 id="modal-title"></h3>
            <button class="close-btn" title="Close">×</button>
        </div>
        <div class="modal-content">
            <div id="modal-content" class="note-preview"></div>
        </div>
    </div>

    <script>
        function showModal(noteId, title, content) {
            const modal = document.getElementById('note-modal');
            const modalTitle = document.getElementById('modal-title');
            const modalContent = document.getElementById('modal-content');

            modalTitle.textContent = title;
            modalContent.innerHTML = content;
            modal.classList.add('show');
        }

        function closeModal() {
            const modal = document.getElementById('note-modal');
            modal.classList.remove('show');
            setTimeout(() => {
                if (!modal.classList.contains('show')) {
                    document.getElementById('modal-title').textContent = '';
                    document.getElementById('modal-content').innerHTML = '';
                }
            }, 300);
        }

        function insertTab() {
            document.execCommand('insertHTML', false, '    ');
        }

        function loadNoteForEditing(noteId, title, content) {
            const titleInput = document.getElementById('note-title');
            const contentDiv = document.getElementById('note-content');
            const noteIdInput = document.getElementById('note-id');
            const saveBtn = document.getElementById('save-btn');

            titleInput.value = title;
            contentDiv.innerHTML = content;
            noteIdInput.value = noteId;
            saveBtn.textContent = 'Update Note';

            document.getElementById('note-editor-form').scrollIntoView({ behavior: 'smooth' });
        }

        document.addEventListener('DOMContentLoaded', function() {
            const editor = document.getElementById('note-content');
            
            editor.addEventListener('paste', function(e) {
                e.preventDefault();
                const html = (e.originalEvent || e).clipboardData.getData('text/html') || 
                            (e.originalEvent || e).clipboardData.getData('text/plain');
                document.execCommand('insertHTML', false, html);
            });

            editor.addEventListener('keydown', function(e) {
                if (e.key === 'Tab') {
                    e.preventDefault();
                    insertTab();
                }
            });

            const modal = document.getElementById('note-modal');
            const closeBtn = modal.querySelector('.close-btn');
            closeBtn.onclick = closeModal;

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && modal.classList.contains('show')) {
                    closeModal();
                }
            });

            document.querySelectorAll('.quick-view-btn').forEach(button => {
                button.removeEventListener('click', handleQuickView);
                button.addEventListener('click', handleQuickView);
            });

            function handleQuickView() {
                const noteId = this.getAttribute('data-note-id');
                const title = this.getAttribute('data-title');
                const content = this.getAttribute('data-content');
                showModal(noteId, title, content);
            }

            document.querySelectorAll('.edit-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const noteId = this.getAttribute('data-note-id');
                    const title = this.getAttribute('data-title');
                    const content = this.getAttribute('data-content');
                    loadNoteForEditing(noteId, title, content);
                });
            });

            document.querySelector('.new-btn').addEventListener('click', function() {
                const titleInput = document.getElementById('note-title');
                const contentDiv = document.getElementById('note-content');
                const noteIdInput = document.getElementById('note-id');
                const saveBtn = document.getElementById('save-btn');

                titleInput.value = '';
                contentDiv.innerHTML = '';
                noteIdInput.value = '';
                saveBtn.textContent = 'Save Note';
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
                const shareLink = 'https://drive.google.com/share/notes-repository';
                alert('Notes list shared to Google Drive! Shareable link: ' + shareLink);
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
if (isset($stmt)) $stmt->close();
$conn->close();
?>