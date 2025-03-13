<?php
// Start the session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php"); // Redirect to login if session not set
    exit();
}

// Include database connection
include 'db.php'; // Ensure this file defines $conn (e.g., mysqli connection)

// Assign user_id from session
$user_id = $_SESSION['user_id'];

// Handle folder creation
if (isset($_POST['create_folder'])) {
    $folder_name = $_POST['folder_name'];
    $stmt = $conn->prepare("INSERT INTO lesson_folders (user_id, folder_name) VALUES (?, ?)");
    $stmt->bind_param("is", $user_id, $folder_name);
    $stmt->execute();
    $stmt->close();
}

// Handle file upload
if (isset($_FILES['file'])) {
    $allowed_types = ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-powerpoint'];
    if (in_array($_FILES['file']['type'], $allowed_types)) {
        $filename = $_FILES['file']['name'];
        $filepath = 'uploads/' . uniqid() . '_' . $filename;
        move_uploaded_file($_FILES['file']['tmp_name'], $filepath);
        $folder_id = isset($_POST['folder_id']) ? $_POST['folder_id'] : null;
        $file_type = pathinfo($filename, PATHINFO_EXTENSION);
        $stmt = $conn->prepare("INSERT INTO lesson_files (user_id, folder_id, filename, filepath, file_type) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iisss", $user_id, $folder_id, $filename, $filepath, $file_type);
        $stmt->execute();
        $stmt->close();
    } else {
        echo "<div class='error'>Only PDF, DOCX, and PPT files are allowed!</div>";
    }
}

// Fetch folders and files
$folders = $conn->query("SELECT * FROM lesson_folders WHERE user_id = $user_id");
$files = $conn->query("SELECT f.*, lf.folder_name FROM lesson_files f LEFT JOIN lesson_folders lf ON f.folder_id = lf.id WHERE f.user_id = $user_id");
?>

<style>
    .table-header {
        display: flex;
        background-color: #303134;
        padding: 8px 16px;
        border-radius: 4px;
        margin-bottom: 8px;
    }
    .table-header div {
        flex: 1;
        font-weight: 500;
        color: #bdc1c6;
    }
    .table-header div:first-child {
        flex: 2;
    }
    .table-row {
        display: flex;
        padding: 8px 16px;
        border-bottom: 1px solid #5f6368;
        align-items: center;
    }
    .table-row:hover {
        background-color: #3c4043;
    }
    .table-row div {
        flex: 1;
        color: #e8eaed;
    }
    .table-row div:first-child {
        flex: 2;
        display: flex;
        align-items: center;
    }
    .table-row div:first-child::before {
        content: '';
        display: inline-block;
        width: 24px;
        height: 24px;
        margin-right: 8px;
        background: url('https://via.placeholder.com/24') no-repeat center;
    }
    .form-container {
        margin-bottom: 16px;
        display: flex;
        gap: 16px;
    }
    .form-container input[type="text"],
    .form-container input[type="file"] {
        background-color: #3c4043;
        border: 1px solid #5f6368;
        border-radius: 4px;
        padding: 8px;
        color: #e8eaed;
        font-size: 14px;
    }
    .form-container button {
        background-color: #8ab4f8;
        border: none;
        border-radius: 4px;
        padding: 8px 16px;
        color: #202124;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
    }
    .form-container button:hover {
        background-color: #6b9eff;
    }
    .file-viewer {
        margin-top: 16px;
        border: 1px solid #5f6368;
        border-radius: 4px;
        padding: 16px;
        background-color: #303134;
        max-height: 500px;
        overflow-y: auto;
    }
    .error {
        color: #ff6b6b;
        padding: 8px;
        background-color: rgba(239, 83, 80, 0.2);
        border-radius: 4px;
        margin-bottom: 16px;
    }
</style>

<div class="header">
    <div class="breadcrumb">
        <a href="?page=lessons">Lessons Repository</a>
    </div>
    <div class="actions">
        <button>☰</button>
        <button>🖼️</button>
        <button>ℹ️</button>
    </div>
</div>

<div class="form-container">
    <form method="POST">
        <input type="text" name="folder_name" placeholder="New Folder Name" required>
        <button type="submit" name="create_folder">Create Folder</button>
    </form>
</div>
<div class="form-container">
    <form method="POST" enctype="multipart/form-data">
        <input type="file" name="file" required>
        <select name="folder_id">
            <option value="">No Folder</option>
            <?php while ($folder = $folders->fetch_assoc()): ?>
                <option value="<?php echo $folder['id']; ?>"><?php echo htmlspecialchars($folder['folder_name']); ?></option>
            <?php endwhile; $folders->data_seek(0); ?>
        </select>
        <button type="submit" name="upload">Upload File</button>
    </form>
</div>

<div class="table-header">
    <div>Name</div>
    <div>Owner</div>
    <div>Last modified</div>
    <div>File size</div>
</div>
<?php while ($folder = $folders->fetch_assoc()): ?>
    <div class="table-row">
        <div><?php echo htmlspecialchars($folder['folder_name']); ?></div>
        <div>me</div>
        <div><?php echo $folder['created_at']; ?></div>
        <div>-</div>
    </div>
<?php endwhile; ?>
<?php while ($file = $files->fetch_assoc()): ?>
    <div class="table-row">
        <div><a href="?page=lessons&view_file=<?php echo $file['id']; ?>"><?php echo htmlspecialchars($file['filename']); ?></a></div>
        <div>me</div>
        <div><?php echo $file['upload_date']; ?></div>
        <div><?php echo file_exists($file['filepath']) ? round(filesize($file['filepath']) / 1024, 2) . ' KB' : '-'; ?></div>
    </div>
<?php endwhile; ?>

<?php
if (isset($_GET['view_file'])) {
    $file_id = $_GET['view_file'];
    $stmt = $conn->prepare("SELECT filepath, file_type FROM lesson_files WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $file_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows == 1) {
        $file = $result->fetch_assoc();
        echo "<div class='file-viewer'>";
        if ($file['file_type'] == 'pdf') {
            echo "<iframe src='{$file['filepath']}' width='100%' height='500px'></iframe>";
        } else {
            echo "<p>Preview not available for {$file['file_type']} files. <a href='{$file['filepath']}' download>Download</a></p>";
        }
        echo "</div>";
    }
    $stmt->close();
}

// Close the database connection
$conn->close();
?>