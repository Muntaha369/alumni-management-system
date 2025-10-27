<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: index.html");
    exit();
}

$user_role = $_SESSION['user_role'] ?? 'guest';
$display_name = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));
if (empty($display_name)) {
    $display_name = $_SESSION['user_email'];
}
$avatar_image = $_SESSION['profile_image_url'] ?? 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#ccc"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>');
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alumni Chat Room</title>
    <style>
        /* ... All your existing CSS ... */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        :root {
            --primary-color: #0d6efd;
            --background-color: #f8f9fa;
            --card-bg-color: #ffffff;
            --text-color: #212529;
            --nav-link-color: #495057;
            --border-color: #dee2e6;
            --sent-message-bg: #0d6efd;
            --received-message-bg: #e9ecef;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background-color: var(--background-color); color: var(--text-color); display: flex; flex-direction: column; height: 100vh; }
        .navbar { background-color: var(--card-bg-color); border-bottom: 1px solid var(--border-color); padding: 0.8rem 2rem; display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; }
        .navbar-left { display: flex; align-items: center; gap: 2rem; }
        .navbar-logo { font-size: 1.5rem; font-weight: 700; color: var(--primary-color); text-decoration: none; }
        .nav-links { list-style: none; display: flex; gap: 1.5rem; }
        .nav-links a { text-decoration: none; color: var(--nav-link-color); font-weight: 500; padding: 0.5rem 0; border-bottom: 2px solid transparent; }
        .nav-links a.active { color: var(--primary-color); border-bottom-color: var(--primary-color); }
        .profile-dropdown { position: relative; display: flex; align-items: center; cursor: pointer; }
        .profile-dropdown img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
        .profile-dropdown span { margin-left: 0.75rem; font-weight: 600; font-size: 0.9rem; }
        .dropdown-menu { display: none; position: absolute; top: 140%; right: 0; background-color: var(--card-bg-color); border: 1px solid var(--border-color); border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); width: 180px; z-index: 100; }
        .profile-dropdown:hover .dropdown-menu { display: block; }
        .dropdown-menu a { display: block; padding: 0.75rem 1rem; text-decoration: none; color: var(--text-color); }
        .dropdown-menu a:hover { background-color: #f8f9fa; }
        .dropdown-menu a.logout { color: #dc3545; }
        .chat-container {
            max-width: 900px;
            width: 100%;
            margin: 1rem auto;
            display: flex;
            flex-direction: column;
            background: var(--card-bg-color);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            overflow: hidden;
            flex-grow: 1;
            max-height: calc(100vh - 100px);
        }
        #chat-box {
            flex-grow: 1;
            padding: 1.5rem;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .message-bubble {
            padding: 0.75rem 1rem;
            border-radius: 18px;
            max-width: 70%;
            line-height: 1.5;
        }
        .message-bubble .sender-name {
            font-weight: 600;
            font-size: 0.8rem;
            margin-bottom: 0.25rem;
        }
        .sent {
            background-color: var(--sent-message-bg);
            color: white;
            align-self: flex-end;
            border-bottom-right-radius: 4px;
        }
        .received {
            background-color: var(--received-message-bg);
            color: var(--text-color);
            align-self: flex-start;
            border-bottom-left-radius: 4px;
        }
        #message-form {
            display: flex;
            padding: 1rem;
            border-top: 1px solid var(--border-color);
            flex-shrink: 0;
        }
        #message-input {
            flex-grow: 1;
            border: 1px solid var(--border-color);
            padding: 0.75rem;
            border-radius: 20px;
            margin-right: 1rem;
        }
        #message-input:focus {
            outline: none;
            border-color: var(--primary-color);
        }
        #message-form button {
            background-color: var(--primary-color);
            border: none;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 20px;
            cursor: pointer;
            font-weight: 600;
        }
    </style>
</head>
<body>

    <nav class="navbar">
        <div class="navbar-left">
            <a href="index.php" class="navbar-logo">AlumniHub</a>
            <ul class="nav-links">
                <li><a href="index.php" class="<?php if($current_page == 'index.php') echo 'active'; ?>">Home</a></li>
                <li><a href="directory.php" class="<?php if($current_page == 'directory.php') echo 'active'; ?>">Directory</a></li>
                <li><a href="events.php" class="<?php if($current_page == 'events.php') echo 'active'; ?>">Events</a></li>
                <li><a href="donate.php" class="<?php if($current_page == 'donate.php') echo 'active'; ?>">Donate</a></li>
                <?php if ($user_role === 'alumni'): ?>
                    <li><a href="chat.php" class="<?php if($current_page == 'chat.php') echo 'active'; ?>">Chat Room</a></li>
                <?php endif; ?>
                <?php if ($user_role === 'admin'): ?>
                    <li><a href="admin.php" class="<?php if($current_page == 'admin.php') echo 'active'; ?>">Admin</a></li>
                <?php endif; ?>
            </ul>
        </div>
        <div class="profile-dropdown">
            <img src="<?php echo htmlspecialchars($avatar_image); ?>" alt="Profile Picture">
            <span><?php echo htmlspecialchars($display_name); ?></span>
            <div class="dropdown-menu">
                <a href="profile.php">My Profile</a>
                <a href="settings.php">Settings</a>
                <hr>
                <a href="api/logout.php" class="logout">Logout</a>
            </div>
        </div>
    </nav>

    <div class="chat-container">
        <div id="chat-box">
            <p style="text-align: center; color: #999; padding-top: 2rem;">No messages yet. Be the first to say hi!</p>
        </div>
        <form id="message-form">
            <input type="text" id="message-input" placeholder="Type a message..." autocomplete="off" required>
            <button type="submit">Send</button>
        </form>
    </div>

    <script>
        // ... (Your existing chat JS is unchanged) ...
        const chatBox = document.getElementById('chat-box');
        const messageForm = document.getElementById('message-form');
        const messageInput = document.getElementById('message-input');
        const currentUserId = <?php echo $_SESSION['user_id']; ?>;

        async function fetchMessages() {
            try {
                const response = await fetch('api/get_messages.php');
                const messages = await response.json();
                
                chatBox.innerHTML = ''; 

                if (messages.length === 0) {
                    chatBox.innerHTML = '<p style="text-align: center; color: #999; padding-top: 2rem;">No messages yet. Be the first to say hi!</p>';
                    return; 
                }

                messages.forEach(msg => {
                    const messageBubble = document.createElement('div');
                    messageBubble.classList.add('message-bubble');

                    const senderName = document.createElement('div');
                    senderName.classList.add('sender-name');
                    
                    if (msg.user_id == currentUserId) {
                        messageBubble.classList.add('sent');
                        senderName.textContent = 'You';
                    } else {
                        messageBubble.classList.add('received');
                        senderName.textContent = (msg.first_name && msg.last_name) 
                            ? `${msg.first_name} ${msg.last_name}` 
                            : msg.email;
                    }

                    const messageText = document.createElement('div');
                    messageText.textContent = msg.message_text;

                    messageBubble.appendChild(senderName);
                    messageBubble.appendChild(messageText);
                    chatBox.appendChild(messageBubble);
                });
                
                chatBox.scrollTop = chatBox.scrollHeight;

            } catch (error) {
                console.error('Error fetching messages:', error);
            }
        }

        messageForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const message = messageInput.value.trim();
            if (message === '') return;

            const formData = new FormData();
            formData.append('message', message);

            try {
                await fetch('api/send_message.php', {
                    method: 'POST',
                    body: formData
                });
                messageInput.value = ''; 
                fetchMessages(); 
            } catch (error) {
                console.error('Error sending message:', error);
            }
        });

        fetchMessages();
        setInterval(fetchMessages, 3000);
    </script>

</body>
</html>