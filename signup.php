<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>White Room - Sign Up</title>
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
        .signup-container {
            background-color: #212829;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 400px;
            text-align: center;
            transition: transform 0.2s ease;
        }
        .signup-container:hover {
            transform: translateY(-2px);
        }
        .signup-container h1 {
            font-size: 28px;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 20px;
            letter-spacing: 1px;
        }
        .signup-form input {
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
        .signup-form input:focus {
            border-color: #8ab4f8;
            outline: none;
        }
        .signup-form button {
            background-color: #8ab4f8;
            border: none;
            border-radius: 8px;
            padding: 12px 20px;
            color: #1c2526;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            width: 100%;
            transition: background-color 0.3s ease, transform 0.1s ease;
        }
        .signup-form button:hover {
            background-color: #6b9eff;
            transform: translateY(-1px);
        }
        .signup-form button:active {
            transform: translateY(1px);
        }
        .login-link {
            margin-top: 20px;
            font-size: 14px;
            color: #b0b0b0;
        }
        .login-link a {
            color: #8ab4f8;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        .login-link a:hover {
            color: #6b9eff;
            text-decoration: underline;
        }
        /* Responsive Design */
        @media (max-width: 480px) {
            .signup-container {
                padding: 20px;
                margin: 20px;
            }
            .signup-container h1 {
                font-size: 24px;
                margin-bottom: 15px;
            }
            .signup-form input {
                padding: 10px;
                margin-bottom: 12px;
                font-size: 12px;
            }
            .signup-form button {
                padding: 10px 15px;
                font-size: 14px;
            }
            .login-link {
                margin-top: 15px;
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="signup-container">
        <h1>WHITE ROOM</h1>
        <form class="signup-form" action="signup_process.php" method="POST">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Sign Up</button>
        </form>
        <div class="login-link">
            <p>Already have an account? <a href="index.php">Login</a></p>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Focus on username field on page load
            const usernameInput = document.querySelector('input[name="username"]');
            if (usernameInput) usernameInput.focus();
        });
    </script>
</body>
</html>