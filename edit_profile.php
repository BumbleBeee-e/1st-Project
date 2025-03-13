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

// Assuming $_SESSION['user_id'] is the 'id' column (integer). Adjust if it's different (e.g., email).
$user_id = $_SESSION['user_id'];

// Fetch current user data
$stmt = $conn->prepare("SELECT username FROM users WHERE id = ?"); // Changed 'user_id' to 'id'
if ($stmt) {
    $stmt->bind_param("i", $user_id); // 'i' for integer, change to 's' if it's a string (e.g., email)
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
} else {
    $messages['error'] = "Error fetching user data: " . $conn->error;
    $user = null;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_username = trim($_POST['username']);
    $new_password = !empty($_POST['password']) ? trim($_POST['password']) : null;
    $confirm_password = !empty($_POST['confirm_password']) ? trim($_POST['confirm_password']) : null;

    // Validate inputs
    if (empty($new_username)) {
        $messages['error'] = "Username cannot be empty!";
    } elseif (strlen($new_username) > 100) {
        $messages['error'] = "Username must be 100 characters or less!";
    } elseif ($new_password !== null && $new_password !== $confirm_password) {
        $messages['error'] = "Passwords do not match!";
    } elseif ($new_password !== null && strlen($new_password) < 6) {
        $messages['error'] = "Password must be at least 6 characters long!";
    } else {
        // Prepare update query
        $update_query = "UPDATE users SET username = ? WHERE id = ?"; // Changed 'user_id' to 'id'
        $params = [$new_username, $user_id];
        $types = "si"; // 's' for username, 'i' for id

        if ($new_password) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_query .= ", password = ?";
            $params[] = $hashed_password;
            $types .= "s";
        }

        $stmt = $conn->prepare($update_query);
        if ($stmt) {
            $stmt->bind_param($types, ...$params);
            if ($stmt->execute()) {
                $messages['success'] = "Profile updated successfully!";
                // Update session username if changed
                $_SESSION['username'] = $new_username;
                $stmt->close();
                header("Location: edit_profile.php");
                exit();
            } else {
                $messages['error'] = "Failed to update profile: " . $conn->error;
            }
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>White Room - Edit Profile</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            background-color: #202124;
            color: #e8eaed;
            font-family: 'Roboto', Arial, sans-serif;
            font-size: 14px;
            display: flex;
            height: 100vh;
        }
        .container {
            display: flex;
            width: 100%;
        }
        .sidebar {
            width: 240px;
            background-color: #202124;
            padding: 16px;
            border-right: 1px solid #5f6368;
            height: 100%;
            overflow-y: auto;
        }
        .sidebar .logo {
            display: flex;
            align-items: center;
            margin-bottom: 24px;
        }
        .sidebar .logo span {
            font-size: 22px;
            font-weight: 500;
            margin-left: 8px;
        }
        .sidebar a {
            color: #e8eaed;
            text-decoration: none;
            display: flex;
            align-items: center;
            padding: 8px 16px;
            border-radius: 4px;
            margin-bottom: 4px;
        }
        .sidebar a:hover {
            background-color: #3c4043;
        }
        .sidebar a.active {
            background-color: #3c4043;
        }
        .sidebar a::before {
            content: '';
            display: inline-block;
            width: 24px;
            height: 24px;
            margin-right: 8px;
            background: url('https://via.placeholder.com/24') no-repeat center;
        }
        .main-content {
            flex: 1;
            padding: 24px;
            overflow-y: auto;
            display: flex;
            justify-content: center;
            align-items: flex-start;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        .header .breadcrumb {
            font-size: 16px;
            color: #bdc1c6;
        }
        .header .breadcrumb a {
            color: #8ab4f8;
            text-decoration: none;
        }
        .header .breadcrumb a:hover {
            text-decoration: underline;
        }
        .header .actions {
            display: flex;
            align-items: center;
        }
        .header .actions button {
            background: none;
            border: none;
            color: #e8eaed;
            font-size: 24px;
            margin-left: 16px;
            cursor: pointer;
        }
        .card {
            background-color: #303134;
            border-radius: 8px;
            padding: 24px;
            width: 100%;
            max-width: 450px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }
        .card h2 {
            font-size: 20px;
            font-weight: 500;
            margin-bottom: 20px;
            color: #e8eaed;
        }
        .messages {
            margin-bottom: 20px;
        }
        .messages .success {
            color: #8bc34a;
            background-color: rgba(139, 195, 74, 0.1);
            padding: 10px;
            border-radius: 4px;
        }
        .messages .error {
            color: #ef5350;
            background-color: rgba(239, 83, 80, 0.1);
            padding: 10px;
            border-radius: 4px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: #bdc1c6;
            margin-bottom: 8px;
        }
        .form-group input[type="text"],
        .form-group input[type="password"] {
            background-color: #3c4043;
            border: 1px solid #5f6368;
            border-radius: 4px;
            padding: 10px;
            color: #e8eaed;
            font-size: 14px;
            width: 100%;
            transition: border-color 0.2s ease-in-out;
        }
        .form-group input:focus {
            border-color: #8ab4f8;
            outline: none;
        }
        .optional {
            font-size: 12px;
            color: #bdc1c6;
            margin-top: 5px;
        }
        .form-group button {
            background-color: #8ab4f8;
            border: none;
            border-radius: 4px;
            padding: 12px;
            color: #202124;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            width: 100%;
            transition: background-color 0.2s ease-in-out;
        }
        .form-group button:hover {
            background-color: #6b9eff;
        }
        @media (max-width: 768px) {
            .sidebar {
                width: 200px;
            }
            .card {
                padding: 16px;
                max-width: 100%;
            }
        }
        @media (max-width: 480px) {
            .container {
                flex-direction: column;
            }
            .sidebar {
                width: 100%;
                height: auto;
                border-right: none;
                border-bottom: 1px solid #5f6368;
            }
            .main-content {
                padding: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <div class="logo">
                <span>WHITE ROOM</span>
            </div>
            <a href="dashboard.php" class="<?php echo !isset($_GET['page']) || $_GET['page'] == 'dashboard' ? 'active' : ''; ?>">Home</a>
            <a href="lessons_repository.php?page=lessons" class="<?php echo isset($_GET['page']) && $_GET['page'] == 'lessons' ? 'active' : ''; ?>">Lessons Repository</a>
            <a href="timer.php" class="<?php echo isset($_GET['page']) && $_GET['page'] == 'timer' ? 'active' : ''; ?>">Timer</a>
            <a href="notes.php?page=notes" class="<?php echo isset($_GET['page']) && $_GET['page'] == 'notes' ? 'active' : ''; ?>">Notes</a>
            <a href="review.php" class="<?php echo isset($_GET['page']) && $_GET['page'] == 'review' ? 'active' : ''; ?>">Review</a>
            <a href="reviewer_notes.php" class="<?php echo isset($_GET['page']) && $_GET['page'] == 'reviewer_notes' ? 'active' : ''; ?>">Reviewer Notes</a>
            <a href="schedule.php" class="<?php echo isset($_GET['page']) && $_GET['page'] == 'schedule' ? 'active' : ''; ?>">Schedule</a>
            <a href="progress.php?page=progress" class="<?php echo isset($_GET['page']) && $_GET['page'] == 'progress' ? 'active' : ''; ?>">Progress Tracker</a>
            <a href="edit_profile.php" class="active">Edit Profile</a>
            <a href="logout.php">Logout</a>
        </div>
        <div class="main-content">
            <div class="card">
                <div class="header">
                    <div class="breadcrumb">
                        <a href="dashboard.php">Home</a> > Edit Profile
                    </div>
                    <div class="actions">
                        <button>☰</button>
                        <button>🖼️</button>
                        <button>ℹ️</button>
                    </div>
                </div>

                <h2>Edit Profile</h2>

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

                <form method="POST">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" name="username" id="username" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="password">New Password (Optional)</label>
                        <input type="password" name="password" id="password">
                        <div class="optional">Leave blank to keep current password</div>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" name="confirm_password" id="confirm_password">
                    </div>

                    <div class="form-group">
                        <button type="submit">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>

<?php $conn->close(); ?>