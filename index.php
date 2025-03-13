<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}
$error = isset($_GET['error']) ? $_GET['error'] : '';
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
        .login-container .error {
            color: #ef5350;
            background-color: rgba(239, 83, 80, 0.1);
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 14px;
            font-weight: 500;
            display: <?php echo $error ? 'block' : 'none'; ?>;
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
        /* Combined Loading Animation Container */
        .loading-container {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            display: none;
            z-index: 10;
            text-align: center;
        }
        .loading-container.active {
            display: block;
        }
        /* Book Loading Animation Styles */
        .book-loader {
            --background: linear-gradient(135deg, #8ab4f8, #275EFE);
            --shadow: rgba(39, 94, 254, 0.28);
            --text: #b0b0b0;
            --page: rgba(255, 255, 255, 0.36);
            --page-fold: rgba(255, 255, 255, 0.52);
            --duration: 3s;
            width: 200px;
            height: 140px;
            position: relative;
            margin: 0 auto;
        }
        .book-loader:before, .book-loader:after {
            --r: -6deg;
            content: "";
            position: absolute;
            bottom: 8px;
            width: 120px;
            top: 80%;
            box-shadow: 0 16px 12px var(--shadow);
            transform: rotate(var(--r));
        }
        .book-loader:before {
            left: 4px;
        }
        .book-loader:after {
            --r: 6deg;
            right: 4px;
        }
        .book-loader div {
            width: 100%;
            height: 100%;
            border-radius: 13px;
            position: relative;
            z-index: 1;
            perspective: 600px;
            box-shadow: 0 4px 6px var(--shadow);
            background-image: var(--background);
        }
        .book-loader div ul {
            margin: 0;
            padding: 0;
            list-style: none;
            position: relative;
        }
        .book-loader div ul li {
            --r: 180deg;
            --o: 0;
            --c: var(--page);
            position: absolute;
            top: 10px;
            left: 10px;
            transform-origin: 100% 50%;
            color: var(--c);
            opacity: var(--o);
            transform: rotateY(var(--r));
            -webkit-animation: var(--duration) ease infinite;
            animation: var(--duration) ease infinite;
        }
        .book-loader div ul li:nth-child(2) {
            --c: var(--page-fold);
            -webkit-animation-name: page-2;
            animation-name: page-2;
        }
        .book-loader div ul li:nth-child(3) {
            --c: var(--page-fold);
            -webkit-animation-name: page-3;
            animation-name: page-3;
        }
        .book-loader div ul li:nth-child(4) {
            --c: var(--page-fold);
            -webkit-animation-name: page-4;
            animation-name: page-4;
        }
        .book-loader div ul li:nth-child(5) {
            --c: var(--page-fold);
            -webkit-animation-name: page-5;
            animation-name: page-5;
        }
        .book-loader div ul li svg {
            width: 90px;
            height: 120px;
            display: block;
        }
        .book-loader div ul li:first-child {
            --r: 0deg;
            --o: 1;
        }
        .book-loader div ul li:last-child {
            --o: 1;
        }
        @keyframes page-2 {
            0% { transform: rotateY(180deg); opacity: 0; }
            20% { opacity: 1; }
            35%, 100% { opacity: 0; }
            50%, 100% { transform: rotateY(0deg); }
        }
        @keyframes page-3 {
            15% { transform: rotateY(180deg); opacity: 0; }
            35% { opacity: 1; }
            50%, 100% { opacity: 0; }
            65%, 100% { transform: rotateY(0deg); }
        }
        @keyframes page-4 {
            30% { transform: rotateY(180deg); opacity: 0; }
            50% { opacity: 1; }
            65%, 100% { opacity: 0; }
            80%, 100% { transform: rotateY(0deg); }
        }
        @keyframes page-5 {
            45% { transform: rotateY(180deg); opacity: 0; }
            65% { opacity: 1; }
            80%, 100% { opacity: 0; }
            95%, 100% { transform: rotateY(0deg); }
        }
        /* Text Loading Animation Styles */
        .text-loader {
            --bg-color: #212829;
            background-color: var(--bg-color);
            padding: 1rem 2rem;
            border-radius: 1.25rem;
            margin-top: 20px;
        }
        .text-loader .loader {
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
        .text-loader .words {
            overflow: hidden;
            position: relative;
        }
        .text-loader .words::after {
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
        .text-loader .word {
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
        /* Hide content when loading */
        .content-hidden {
            display: none;
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
            .login-container .error {
                padding: 8px;
                margin-bottom: 12px;
                font-size: 12px;
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
            .signup-link {
                margin-top: 15px;
                font-size: 12px;
            }
            .book-loader {
                width: 160px;
                height: 112px;
            }
            .book-loader div ul li svg {
                width: 72px;
                height: 96px;
            }
            .book-loader:before, .book-loader:after {
                width: 96px;
            }
            .text-loader {
                padding: 0.8rem 1.5rem;
            }
            .text-loader .loader {
                font-size: 18px;
                height: 35px;
                padding: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container" id="loginContainer">
        <div id="loginContent">
            <h1>WHITE ROOM</h1>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <form class="login-form" action="login_process.php" method="POST" id="loginForm">
                <input type="text" name="username" placeholder="Username" required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit" id="loginButton">Login</button>
            </form>
            <div class="signup-link">
                <p>Don't have an account? <a href="signup.php">Sign Up</a></p>
            </div>
        </div>
        <!-- Combined Loading Animation -->
        <div class="loading-container" id="loadingCard">
            <div class="book-loader">
                <div>
                    <ul>
                        <li>
                            <svg fill="currentColor" viewBox="0 0 90 120">
                                <path d="M90,0 L90,120 L11,120 C4.92486775,120 0,115.075132 0,109 L0,11 C0,4.92486775 4.92486775,0 11,0 L90,0 Z M71.5,81 L18.5,81 C17.1192881,81 16,82.1192881 16,83.5 C16,84.8254834 17.0315359,85.9100387 18.3356243,85.9946823 L18.5,86 L71.5,86 C72.8807119,86 74,84.8807119 74,83.5 C74,82.1745166 72.9684641,81.0899613 71.6643757,81.0053177 L71.5,81 Z M71.5,57 L18.5,57 C17.1192881,57 16,58.1192881 16,59.5 C16,60.8254834 17.0315359,61.9100387 18.3356243,61.9946823 L18.5,62 L71.5,62 C72.8807119,62 74,60.8807119 74,59.5 C74,58.1192881 72.8807119,57 71.5,57 Z M71.5,33 L18.5,33 C17.1192881,33 16,34.1192881 16,35.5 C16,36.8254834 17.0315359,37.9100387 18.3356243,37.9946823 L18.5,38 L71.5,38 C72.8807119,38 74,36.8807119 74,35.5 C74,34.1192881 72.8807119,33 71.5,33 Z"></path>
                            </svg>
                        </li>
                        <li>
                            <svg fill="currentColor" viewBox="0 0 90 120">
                                <path d="M90,0 L90,120 L11,120 C4.92486775,120 0,115.075132 0,109 L0,11 C0,4.92486775 4.92486775,0 11,0 L90,0 Z M71.5,81 L18.5,81 C17.1192881,81 16,82.1192881 16,83.5 C16,84.8254834 17.0315359,85.9100387 18.3356243,85.9946823 L18.5,86 L71.5,86 C72.8807119,86 74,84.8807119 74,83.5 C74,82.1745166 72.9684641,81.0899613 71.6643757,81.0053177 L71.5,81 Z M71.5,57 L18.5,57 C17.1192881,57 16,58.1192881 16,59.5 C16,60.8254834 17.0315359,61.9100387 18.3356243,61.9946823 L18.5,62 L71.5,62 C72.8807119,62 74,60.8807119 74,59.5 C74,58.1192881 72.8807119,57 71.5,57 Z M71.5,33 L18.5,33 C17.1192881,33 16,34.1192881 16,35.5 C16,36.8254834 17.0315359,37.9100387 18.3356243,37.9946823 L18.5,38 L71.5,38 C72.8807119,38 74,36.8807119 74,35.5 C74,34.1192881 72.8807119,33 71.5,33 Z"></path>
                            </svg>
                        </li>
                        <li>
                            <svg fill="currentColor" viewBox="0 0 90 120">
                                <path d="M90,0 L90,120 L11,120 C4.92486775,120 0,115.075132 0,109 L0,11 C0,4.92486775 4.92486775,0 11,0 L90,0 Z M71.5,81 L18.5,81 C17.1192881,81 16,82.1192881 16,83.5 C16,84.8254834 17.0315359,85.9100387 18.3356243,85.9946823 L18.5,86 L71.5,86 C72.8807119,86 74,84.8807119 74,83.5 C74,82.1745166 72.9684641,81.0899613 71.6643757,81.0053177 L71.5,81 Z M71.5,57 L18.5,57 C17.1192881,57 16,58.1192881 16,59.5 C16,60.8254834 17.0315359,61.9100387 18.3356243,61.9946823 L18.5,62 L71.5,62 C72.8807119,62 74,60.8807119 74,59.5 C74,58.1192881 72.8807119,57 71.5,57 Z M71.5,33 L18.5,33 C17.1192881,33 16,34.1192881 16,35.5 C16,36.8254834 17.0315359,37.9100387 18.3356243,37.9946823 L18.5,38 L71.5,38 C72.8807119,38 74,36.8807119 74,35.5 C74,34.1192881 72.8807119,33 71.5,33 Z"></path>
                            </svg>
                        </li>
                        <li>
                            <svg fill="currentColor" viewBox="0 0 90 120">
                                <path d="M90,0 L90,120 L11,120 C4.92486775,120 0,115.075132 0,109 L0,11 C0,4.92486775 4.92486775,0 11,0 L90,0 Z M71.5,81 L18.5,81 C17.1192881,81 16,82.1192881 16,83.5 C16,84.8254834 17.0315359,85.9100387 18.3356243,85.9946823 L18.5,86 L71.5,86 C72.8807119,86 74,84.8807119 74,83.5 C74,82.1745166 72.9684641,81.0899613 71.6643757,81.0053177 L71.5,81 Z M71.5,57 L18.5,57 C17.1192881,57 16,58.1192881 16,59.5 C16,60.8254834 17.0315359,61.9100387 18.3356243,61.9946823 L18.5,62 L71.5,62 C72.8807119,62 74,60.8807119 74,59.5 C74,58.1192881 72.8807119,57 71.5,57 Z M71.5,33 L18.5,33 C17.1192881,33 16,34.1192881 16,35.5 C16,36.8254834 17.0315359,37.9100387 18.3356243,37.9946823 L18.5,38 L71.5,38 C72.8807119,38 74,36.8807119 74,35.5 C74,34.1192881 72.8807119,33 71.5,33 Z"></path>
                            </svg>
                        </li>
                        <li>
                            <svg fill="currentColor" viewBox="0 0 90 120">
                                <path d="M90,0 L90,120 L11,120 C4.92486775,120 0,115.075132 0,109 L0,11 C0,4.92486775 4.92486775,0 11,0 L90,0 Z M71.5,81 L18.5,81 C17.1192881,81 16,82.1192881 16,83.5 C16,84.8254834 17.0315359,85.9100387 18.3356243,85.9946823 L18.5,86 L71.5,86 C72.8807119,86 74,84.8807119 74,83.5 C74,82.1745166 72.9684641,81.0899613 71.6643757,81.0053177 L71.5,81 Z M71.5,57 L18.5,57 C17.1192881,57 16,58.1192881 16,59.5 C16,60.8254834 17.0315359,61.9100387 18.3356243,61.9946823 L18.5,62 L71.5,62 C72.8807119,62 74,60.8807119 74,59.5 C74,58.1192881 72.8807119,57 71.5,57 Z M71.5,33 L18.5,33 C17.1192881,33 16,34.1192881 16,35.5 C16,36.8254834 17.0315359,37.9100387 18.3356243,37.9946823 L18.5,38 L71.5,38 C72.8807119,38 74,36.8807119 74,35.5 C74,34.1192881 72.8807119,33 71.5,33 Z"></path>
                            </svg>
                        </li>
                        <li>
                            <svg fill="currentColor" viewBox="0 0 90 120">
                                <path d="M90,0 L90,120 L11,120 C4.92486775,120 0,115.075132 0,109 L0,11 C0,4.92486775 4.92486775,0 11,0 L90,0 Z M71.5,81 L18.5,81 C17.1192881,81 16,82.1192881 16,83.5 C16,84.8254834 17.0315359,85.9100387 18.3356243,85.9946823 L18.5,86 L71.5,86 C72.8807119,86 74,84.8807119 74,83.5 C74,82.1745166 72.9684641,81.0899613 71.6643757,81.0053177 L71.5,81 Z M71.5,57 L18.5,57 C17.1192881,57 16,58.1192881 16,59.5 C16,60.8254834 17.0315359,61.9100387 18.3356243,61.9946823 L18.5,62 L71.5,62 C72.8807119,62 74,60.8807119 74,59.5 C74,58.1192881 72.8807119,57 71.5,57 Z M71.5,33 L18.5,33 C17.1192881,33 16,34.1192881 16,35.5 C16,36.8254834 17.0315359,37.9100387 18.3356243,37.9946823 L18.5,38 L71.5,38 C72.8807119,38 74,36.8807119 74,35.5 C74,34.1192881 72.8807119,33 71.5,33 Z"></path>
                            </svg>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="text-loader">
                <div class="loader">
                    <p>loading</p>
                    <div class="words">
                        <span class="word">Lessons</span>
                        <span class="word">Forms</span>
                        <span class="word">Reviewers</span>
                        <span class="word">Data</span>
                        <span class="word">Discussions</span>
                    </div>
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
            const loginContent = document.getElementById('loginContent');

            form.addEventListener('submit', function(e) {
                e.preventDefault(); // Prevent form submission for the 5-second demo

                // Hide all content except loading animation
                loginButton.disabled = true;
                loginContent.classList.add('content-hidden');
                loadingCard.classList.add('active');

                // Simulate 5-second loading
                setTimeout(() => {
                    // Show content again and hide loading animation
                    loginContent.classList.remove('content-hidden');
                    loadingCard.classList.remove('active');
                    loginButton.disabled = false;

                    // Submit the form after the animation
                    form.submit();
                }, 5000); // 5 seconds
            });
        });
    </script>
</body>
</html>