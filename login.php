<?php
session_start();
require_once 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT id, password FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        if (password_verify($password, $row['password'])) {
            $_SESSION['user_id'] = $row['id'];
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Invalid password!";
        }
    } else {
        $error = "User not found!";
    }
    $stmt->close();
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>White Room - Login</title>
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
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            font-size: 16px;
            line-height: 1.6;
        }
        .login-container {
            background-color: #212829;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 400px;
            text-align: center;
            transition: transform 0.2s ease;
            position: relative;
        }
        .login-container:hover {
            transform: translateY(-2px);
        }
        .login-container h1 {
            font-size: 28px;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 20px;
            letter-spacing: 1px;
        }
        .login-form input {
            background-color: #2d2e30;
            border: 1px solid #3a3f41;
            border-radius: 8px;
            padding: 12px;
            color: #e0e0e0;
            width: 100%;
            margin-bottom: 15px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }
        .login-form input:focus {
            border-color: #8ab4f8;
            outline: none;
        }
        .login-form button {
            background-color: #8ab4f8;
            border: none;
            border-radius: 8px;
            padding: 12px 20px;
            color: #1c2526;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            width: 100%;
            transition: background-color 0.3s ease, transform 0.1s ease, opacity 0.3s ease;
        }
        .login-form button:hover {
            background-color: #6b9eff;
            transform: translateY(-1px);
        }
        .login-form button:active {
            transform: translateY(1px);
        }
        .login-form button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .error {
            color: #ef5350;
            background-color: rgba(239, 83, 80, 0.1);
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 14px;
            font-weight: 500;
        }
        .signup-link {
            margin-top: 20px;
            font-size: 14px;
            color: #b0b0b0;
        }
        .signup-link a {
            color: #8ab4f8;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        .signup-link a:hover {
            color: #6b9eff;
            text-decoration: underline;
        }
        /* Loading Animation Styles */
        .card {
            --bg-color: #212829;
            background-color: var(--bg-color);
            padding: 1rem 2rem;
            border-radius: 1.25rem;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            display: none;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            z-index: 10;
        }
        .card.active {
            display: block;
        }
        .loader {
            color: rgb(124, 124, 124);
            font-weight: 500;
            font-size: 20px;
            -webkit-box-sizing: content-box;
            box-sizing: content-box;
            height: 40px;
            padding: 10px 10px;
            display: flex;
            border-radius: 8px;
        }
        .words {
            overflow: hidden;
            position: relative;
        }
        .words::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(
                var(--bg-color) 10%,
                transparent 30%,
                transparent 70%,
                var(--bg-color) 90%
            );
            z-index: 20;
        }
        .word {
            display: block;
            height: 100%;
            padding-left: 6px;
            color: #8ab4f8;
            animation: spin_4991 4s infinite;
        }
        @keyframes spin_4991 {
            10% { transform: translateY(-102%); }
            25% { transform: translateY(-100%); }
            35% { transform: translateY(-202%); }
            50% { transform: translateY(-200%); }
            60% { transform: translateY(-302%); }
            75% { transform: translateY(-300%); }
            85% { transform: translateY(-402%); }
            100% { transform: translateY(-400%); }
        }
        /* Responsive Design */
        @media (max-width: 480px) {
            .login-container {
                padding: 20px;
                margin: 20px;
            }
            .login-container h1 {
                font-size: 24px;
                margin-bottom: 15px;
            }
            .login-form input {
                padding: 10px;
                margin-bottom: 12px;
                font-size: 12px;
            }
            .login-form button {
                padding: 10px 15px;
                font-size: 14px;
            }
            .error {
                padding: 8px;
                margin-bottom: 12px;
                font-size: 12px;
            }
            .signup-link {
                margin-top: 15px;
                font-size: 12px;
            }
            .loader {
                font-size: 18px;
                height: 35px;
                padding: 8px;
            }
            .card {
                padding: 0.8rem 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>WHITE ROOM</h1>
        <form class="login-form" method="POST" id="loginForm">
            <?php if (isset($error)): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit" id="loginButton">Login</button>
        </form>
        <div class="signup-link">
            Don't have an account? <a href="signup.php">Sign up</a>
        </div>
        <!-- Loading Animation -->
        <div class="card" id="loadingCard">
            <div class="loader">
                <p>loading</p>
                <div class="words">
                    <span class="word">data</span>
                    <span class="word">users</span>
                    <span class="word">sessions</span>
                    <span class="word">credentials</span>
                    <span class="word">data</span>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const usernameInput = document.querySelector('input[name="username"]');
            if (usernameInput) usernameInput.focus();

            const form = document.getElementById('loginForm');
            const loginButton = document.getElementById('loginButton');
            const loadingCard = document.getElementById('loadingCard');

            form.addEventListener('submit', function(e) {
                // Prevent immediate submission for demo (remove in production if not needed)
                // e.preventDefault();

                // Show loading animation
                loginButton.disabled = true;
                loadingCard.classList.add('active');

                // Simulate a delay for loading animation (remove or adjust in production)
                setTimeout(() => {
                    // In a real scenario, this would be handled by the form submission
                    // Here, we're just simulating the behavior
                    loadingCard.classList.remove('active');
                    loginButton.disabled = false;
                }, 2000); // 2 seconds delay for demo
            });
        });
    </script>
</body>
</html>