<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
require_once 'db.php';

// Google Gemini API Key
$api_key = 'AIzaSyA2ktTvXtNDxDaIHRPGLyV-uai2Z8Koz-0'; // Your provided API key
$api_url = "https://generativelanguage.googleapis.com/v1/models/gemini-pro:generateContent?key=$api_key";

// Success/Error messages
if (!isset($_SESSION['messages'])) {
    $_SESSION['messages'] = [];
}
$messages = &$_SESSION['messages'];

$user_id = $_SESSION['user_id'];

// Check if chat_messages table exists, create it if not
$check_table = $conn->query("SHOW TABLES LIKE 'chat_messages'");
if ($check_table->num_rows == 0) {
    $create_table_sql = "
        CREATE TABLE chat_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            message TEXT NOT NULL,
            sender ENUM('user', 'bot') NOT NULL,
            timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )";
    if ($conn->query($create_table_sql) === TRUE) {
        $messages['success'] = "Chat messages table created successfully!";
    } else {
        $messages['error'] = "Failed to create chat_messages table: " . $conn->error;
    }
}

// Handle chat message submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $user_message = trim($_POST['message']);
    if (!empty($user_message)) {
        // Save user message to database
        $stmt = $conn->prepare("INSERT INTO chat_messages (user_id, message, sender) VALUES (?, ?, 'user')");
        if ($stmt) {
            $stmt->bind_param("is", $user_id, $user_message);
            $stmt->execute();
            $stmt->close();

            // Call Google Gemini API
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $api_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'contents' => [
                    ['role' => 'user', 'parts' => [['text' => $user_message]]]
                ]
            ]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json'
            ]);

            $response = curl_exec($ch);
            if (curl_errno($ch)) {
                $messages['error'] = 'API Error: ' . curl_error($ch);
            } else {
                file_put_contents('gemini_response.log', date('Y-m-d H:i:s') . " - " . $response . PHP_EOL, FILE_APPEND);
                $data = json_decode($response, true);
                if (isset($data['error'])) {
                    $messages['error'] = 'API Error: ' . $data['error']['message'];
                } elseif (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                    $bot_response = trim($data['candidates'][0]['content']['parts'][0]['text']);
                    $stmt = $conn->prepare("INSERT INTO chat_messages (user_id, message, sender) VALUES (?, ?, 'bot')");
                    $stmt->bind_param("is", $user_id, $bot_response);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    $messages['error'] = 'API Error: Invalid response structure';
                }
            }
            curl_close($ch);
        }
        header("Location: chat.php");
        exit();
    }
}

// Fetch chat history
$stmt = $conn->prepare("SELECT * FROM chat_messages WHERE user_id = ? ORDER BY timestamp ASC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$chat_history = $stmt->get_result();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>White Room - Chatbot (Gemini)</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css">
    <style>
        /* Original White Room Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Roboto', Arial, sans-serif;
        }
        body {
            background-color: #202124;
            color: #e8eaed;
            font-size: 14px;
        }
        .container {
            display: flex;
            height: 100vh;
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
        .sidebar .new-btn {
            background-color: #3c4043;
            color: #e8eaed;
            border: 1px solid #5f6368;
            border-radius: 24px;
            padding: 10px 16px;
            width: 100%;
            text-align: left;
            font-size: 14px;
            cursor: pointer;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
        }
        .sidebar .new-btn::before {
            content: '+';
            font-size: 18px;
            margin-right: 8px;
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
        .sidebar .storage {
            margin-top: 24px;
            font-size: 12px;
            color: #bdc1c6;
        }
        .sidebar .storage-bar {
            background-color: #3c4043;
            height: 4px;
            border-radius: 2px;
            margin: 8px 0;
        }
        .sidebar .storage-bar-fill {
            background-color: #8ab4f8;
            height: 100%;
            width: 15%;
            border-radius: 2px;
        }
        .sidebar .storage-btn {
            background-color: transparent;
            border: 1px solid #5f6368;
            border-radius: 24px;
            padding: 8px 16px;
            color: #8ab4f8;
            font-size: 14px;
            cursor: pointer;
            width: 100%;
            text-align: center;
        }
        .main-content {
            flex: 1;
            padding: 16px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
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
        .messages {
            margin-bottom: 15px;
        }
        .messages .success {
            color: #8bc34a;
        }
        .messages .error {
            color: #ef5350;
        }
        .chat-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            background-color: #303134;
            border-radius: 8px;
            padding: 16px;
            overflow-y: auto;
        }
        .chat-message {
            margin-bottom: 12px;
            padding: 8px 12px;
            border-radius: 4px;
            max-width: 70%;
            position: relative;
        }
        .chat-message.user {
            background-color: #8ab4f8;
            color: #202124;
            align-self: flex-end;
        }
        .chat-message.bot {
            background-color: #3c4043;
            color: #e8eaed;
            align-self: flex-start;
        }
        .chat-message .timestamp {
            font-size: 10px;
            color: #bdc1c6;
            margin-top: 4px;
        }
        .chat-input {
            margin-top: 16px;
            display: flex;
            gap: 8px;
            position: relative;
        }

        /* Gemini Clone Styles Adapted */
        .prompt__form-input {
            height: 4rem;
            width: 100%;
            border: none;
            font-size: 1rem;
            color: #e8eaed;
            padding: 1rem 3.5rem 1rem 1.75rem;
            border-radius: 100px;
            background: #3c4043;
            transition: background 0.3s ease;
        }
        .prompt__form-input:focus {
            background: #282A2C;
        }
        .prompt__form-input::placeholder {
            color: #ABAFB3;
        }
        .prompt__form-button {
            position: absolute;
            right: 0.5rem;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            width: 48px;
            height: 48px;
            cursor: pointer;
            border-radius: 50%;
            font-size: 1.4rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #e8eaed;
            background: transparent;
            transition: all 0.3s ease;
        }
        .prompt__form-button:hover {
            background: #2f3030;
        }
        .prompt__disclaim {
            text-align: center;
            color: #ABAFB3;
            font-size: 0.85rem;
            margin-top: 1rem;
        }
        .suggests {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 1rem;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .suggests__item {
            background: #3c4043;
            color: #D8D8D8;
            padding: 1rem;
            width: 12.5rem;
            border-radius: 0.75rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        .suggests__item:hover {
            background: #333537;
        }
        .suggests__item-text {
            font-weight: 500;
            line-height: 1.375rem;
        }
        .suggests__item-icon i {
            font-size: 1.5rem;
            background: #202124;
            padding: 0.5rem;
            border-radius: 50%;
        }
        .message__icon {
            position: absolute;
            right: 8px;
            top: 8px;
            color: #e8eaed;
            cursor: pointer;
            height: 35px;
            width: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: none;
            font-size: 1.25rem;
            transition: background 0.3s ease;
        }
        .message__icon:hover {
            background: #333537;
        }
        .hide {
            display: none !important;
        }
        pre {
            position: relative;
            background-color: #3c4043;
            padding: 10px;
            font-family: monospace;
            font-size: 14px;
            border-radius: 10px;
            margin: 10px 0;
            overflow-x: auto;
        }
        .code__copy-btn {
            background-color: transparent;
            border: none;
            color: #e8eaed;
            border-radius: 5px;
            cursor: pointer;
            position: absolute;
            right: 10px;
            top: 12px;
            z-index: 10;
            font-size: 18px;
        }
        .code__language-label {
            position: absolute;
            font-weight: bold;
            top: 10px;
            left: 12px;
            color: #ABAFB3;
            font-size: 14px;
            text-transform: capitalize;
        }
        /* Light Mode */
        body.light_mode {
            background-color: #FFFFFF;
            color: #000;
        }
        body.light_mode .sidebar {
            background-color: #F0F4F9;
            border-right: 1px solid #DDE3EA;
        }
        body.light_mode .sidebar a,
        body.light_mode .sidebar .logo span,
        body.light_mode .sidebar .new-btn {
            color: #4D4D4D;
        }
        body.light_mode .sidebar a:hover,
        body.light_mode .sidebar a.active,
        body.light_mode .sidebar .new-btn:hover {
            background-color: #DDE3EA;
        }
        body.light_mode .chat-container {
            background-color: #E9EEF6;
        }
        body.light_mode .chat-message.user {
            background-color: #8ab4f8;
            color: #202124;
        }
        body.light_mode .chat-message.bot {
            background-color: #E1E6ED;
            color: #000;
        }
        body.light_mode .prompt__form-input {
            background: #F0F4F9;
            color: #000;
        }
        body.light_mode .prompt__form-input:focus {
            background: #E9EEF6;
        }
        body.light_mode .suggests__item {
            background: #F0F4F9;
            color: #4D4D4D;
        }
        body.light_mode .suggests__item:hover {
            background: #DDE3EA;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <div class="logo">
                <span>WHITE ROOM</span>
            </div>
            <button class="new-btn" onclick="document.querySelector('.prompt__form-input').focus()">New Chat</button>
            <a href="dashboard.php">Home</a>
            <a href="lessons_repository.php?page=lessons">Lessons Repository</a>
            <a href="timer.php" class="<?php echo isset($_GET['page']) && $_GET['page'] == 'timer' ? 'active' : ''; ?>">Timer</a>
            <a href="notes.php?page=notes" class="<?php echo isset($_GET['page']) && $_GET['page'] == 'notes' ? 'active' : ''; ?>">Notes</a>
            <a href="review.php" class="<?php echo isset($_GET['page']) && $_GET['page'] == 'review' ? 'active' : ''; ?>">Review</a>
            <a href="reviewer_notes.php" class="<?php echo isset($_GET['page']) && $_GET['page'] == 'reviewer_notes' ? 'active' : ''; ?>">Reviewer Notes</a>
            <a href="schedule.php" class="<?php echo isset($_GET['page']) && $_GET['page'] == 'schedule' ? 'active' : ''; ?>">Schedule</a>
            <a href="progress.php?page=progress" class="<?php echo isset($_GET['page']) && $_GET['page'] == 'progress' ? 'active' : ''; ?>">Progress Tracker</a>
            <a href="chat.php" class="active">Chatbot</a>
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
                    <a href="chat.php">Chatbot (Gemini)</a>
                </div>
                <div class="actions">
                    <button id="themeToggler"><i class='bx bx-sun'></i></button>
                    <button>🖼️</button>
                    <button>ℹ️</button>
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

            <?php if ($chat_history->num_rows == 0): ?>
                <div class="suggests">
                    <div class="suggests__item">
                        <p class="suggests__item-text">Give tips on helping kids finish their homework on time</p>
                        <div class="suggests__item-icon"><i class='bx bx-stopwatch'></i></div>
                    </div>
                    <div class="suggests__item">
                        <p class="suggests__item-text">Help me write an out-of-office email</p>
                        <div class="suggests__item-icon"><i class='bx bx-edit-alt'></i></div>
                    </div>
                    <div class="suggests__item">
                        <p class="suggests__item-text">Give me phrases to learn a new language</p>
                        <div class="suggests__item-icon"><i class='bx bx-compass'></i></div>
                    </div>
                    <div class="suggests__item">
                        <p class="suggests__item-text">Show me how to build something by hand</p>
                        <div class="suggests__item-icon"><i class='bx bx-wrench'></i></div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="chat-container">
                <?php if ($chat_history->num_rows > 0): ?>
                    <?php while ($message = $chat_history->fetch_assoc()): ?>
                        <div class="chat-message <?php echo $message['sender']; ?>">
                            <?php echo htmlspecialchars($message['message']); ?>
                            <div class="timestamp"><?php echo date('Y-m-d H:i', strtotime($message['timestamp'])); ?></div>
                            <span onclick="copyMessageToClipboard(this)" class="message__icon"><i class='bx bx-copy-alt'></i></span>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="chat-message bot">
                        Hello! I'm your Gemini-powered assistant. How can I help you today?
                        <div class="timestamp"><?php echo date('Y-m-d H:i'); ?></div>
                        <span onclick="copyMessageToClipboard(this)" class="message__icon"><i class='bx bx-copy-alt'></i></span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="chat-input">
                <form method="POST" class="prompt__form" style="display: flex; width: 100%; position: relative;">
                    <input type="text" name="message" placeholder="Enter a prompt here" class="prompt__form-input" required>
                    <button type="submit" class="prompt__form-button" id="sendButton"><i class='bx bx-send'></i></button>
                    <button type="button" class="prompt__form-button" id="deleteButton"><i class='bx bx-trash'></i></button>
                </form>
                <p class="prompt__disclaim">Gemini may display inaccurate info, so double-check its responses.</p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
    <script>
        const messageForm = document.querySelector(".prompt__form");
        const chatHistoryContainer = document.querySelector(".chat-container");
        const suggestionItems = document.querySelectorAll(".suggests__item");
        const themeToggleButton = document.getElementById("themeToggler");
        const clearChatButton = document.getElementById("deleteButton");

        // State variables
        let isGeneratingResponse = false;

        // Auto-scroll to bottom on page load
        document.addEventListener('DOMContentLoaded', function() {
            chatHistoryContainer.scrollTop = chatHistoryContainer.scrollHeight;
            loadTheme();
            applyMarkdownAndHighlighting();
        });

        // Load theme from local storage
        const loadTheme = () => {
            const isLightTheme = localStorage.getItem("themeColor") === "light_mode";
            document.body.classList.toggle("light_mode", isLightTheme);
            themeToggleButton.innerHTML = isLightTheme ? '<i class="bx bx-moon"></i>' : '<i class="bx bx-sun"></i>';
        };

        // Apply Markdown and code highlighting to existing messages
        const applyMarkdownAndHighlighting = () => {
            const messages = chatHistoryContainer.querySelectorAll(".chat-message");
            messages.forEach(message => {
                const text = message.childNodes[0].nodeValue.trim();
                message.innerHTML = marked.parse(text) + '<div class="timestamp">' + message.querySelector(".timestamp").innerText + '</div>';
                message.innerHTML += '<span onclick="copyMessageToClipboard(this)" class="message__icon"><i class="bx bx-copy-alt"></i></span>';
                hljs.highlightAll();
                addCopyButtonToCodeBlocks();
            });
        };

        // Create a new chat message element
        const createChatMessageElement = (text, ...cssClasses) => {
            const messageElement = document.createElement("div");
            messageElement.classList.add("chat-message", ...cssClasses);
            messageElement.innerHTML = text + '<div class="timestamp">' + new Date().toLocaleString('en-US', { year: 'numeric', month: 'numeric', day: 'numeric', hour: '2-digit', minute: '2-digit' }) + '</div>' +
                '<span onclick="copyMessageToClipboard(this)" class="message__icon"><i class="bx bx-copy-alt"></i></span>';
            return messageElement;
        };

        // Show typing effect (for future AJAX integration if desired)
        const showTypingEffect = (rawText, htmlText, messageElement, skipEffect = false) => {
            const copyIconElement = messageElement.querySelector(".message__icon");
            copyIconElement.classList.add("hide");

            if (skipEffect) {
                messageElement.innerHTML = htmlText + '<div class="timestamp">' + messageElement.querySelector(".timestamp").innerText + '</div>' +
                    '<span onclick="copyMessageToClipboard(this)" class="message__icon"><i class="bx bx-copy-alt"></i></span>';
                hljs.highlightAll();
                addCopyButtonToCodeBlocks();
                copyIconElement.classList.remove("hide");
                isGeneratingResponse = false;
                return;
            }

            const wordsArray = rawText.split(' ');
            let wordIndex = 0;
            messageElement.innerText = '';

            const typingInterval = setInterval(() => {
                messageElement.innerText += (wordIndex === 0 ? '' : ' ') + wordsArray[wordIndex++];
                if (wordIndex === wordsArray.length) {
                    clearInterval(typingInterval);
                    messageElement.innerHTML = htmlText + '<div class="timestamp">' + messageElement.querySelector(".timestamp").innerText + '</div>' +
                        '<span onclick="copyMessageToClipboard(this)" class="message__icon"><i class="bx bx-copy-alt"></i></span>';
                    hljs.highlightAll();
                    addCopyButtonToCodeBlocks();
                    copyIconElement.classList.remove("hide");
                    isGeneratingResponse = false;
                }
            }, 75);
        };

        // Add copy button to code blocks
        const addCopyButtonToCodeBlocks = () => {
            const codeBlocks = document.querySelectorAll('pre');
            codeBlocks.forEach((block) => {
                const codeElement = block.querySelector('code');
                if (!codeElement) return;

                let language = [...codeElement.classList].find(cls => cls.startsWith('林uage-'))?.replace('language-', '') || 'Text';
                const languageLabel = document.createElement('div');
                languageLabel.innerText = language.charAt(0).toUpperCase() + language.slice(1);
                languageLabel.classList.add('code__language-label');
                block.appendChild(languageLabel);

                if (!block.querySelector('.code__copy-btn')) {
                    const copyButton = document.createElement('button');
                    copyButton.innerHTML = `<i class='bx bx-copy'></i>`;
                    copyButton.classList.add('code__copy-btn');
                    block.appendChild(copyButton);

                    copyButton.addEventListener('click', () => {
                        navigator.clipboard.writeText(codeElement.innerText).then(() => {
                            copyButton.innerHTML = `<i class='bx bx-check'></i>`;
                            setTimeout(() => copyButton.innerHTML = `<i class='bx bx-copy'></i>`, 2000);
                        }).catch(err => {
                            console.error("Copy failed:", err);
                            alert("Unable to copy text!");
                        });
                    });
                }
            });
        };

        // Copy message to clipboard
        const copyMessageToClipboard = (copyButton) => {
            const messageContent = copyButton.parentElement.childNodes[0].textContent.trim();
            navigator.clipboard.writeText(messageContent);
            copyButton.innerHTML = `<i class='bx bx-check'></i>`;
            setTimeout(() => copyButton.innerHTML = `<i class='bx bx-copy-alt'></i>`, 1000);
        };

        // Handle sending chat messages (client-side preview before server submission)
        const handleOutgoingMessage = () => {
            const messageInput = messageForm.querySelector(".prompt__form-input");
            const userMessage = messageInput.value.trim();
            if (!userMessage || isGeneratingResponse) return;

            isGeneratingResponse = true;
            const outgoingMessageElement = createChatMessageElement(userMessage, "user");
            chatHistoryContainer.appendChild(outgoingMessageElement);
            messageForm.submit(); // Trigger server-side submission
        };

        // Toggle between light and dark themes
        themeToggleButton.addEventListener('click', () => {
            const isLightTheme = document.body.classList.toggle("light_mode");
            localStorage.setItem("themeColor", isLightTheme ? "light_mode" : "dark_mode");
            themeToggleButton.innerHTML = isLightTheme ? '<i class="bx bx-moon"></i>' : '<i class="bx bx-sun"></i>';
        });

        // Clear chat history (client-side confirmation, server-side action needed)
        clearChatButton.addEventListener('click', () => {
            if (confirm("Are you sure you want to delete all chat history?")) {
                // For now, just reload the page; server-side clearing would require an endpoint
                window.location.reload();
                // TODO: Add AJAX call to a PHP endpoint to clear chat_messages table
            }
        });

        // Handle suggestion clicks
        suggestionItems.forEach(suggestion => {
            suggestion.addEventListener('click', () => {
                const messageInput = messageForm.querySelector(".prompt__form-input");
                messageInput.value = suggestion.querySelector(".suggests__item-text").innerText;
                handleOutgoingMessage();
            });
        });

        // Prevent default form submission and handle outgoing message
        messageForm.addEventListener('submit', (e) => {
            e.preventDefault();
            handleOutgoingMessage();
        });
    </script>
</body>
</html>

<?php $conn->close(); ?>