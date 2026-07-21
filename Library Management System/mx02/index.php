<?php
session_start();

$password_hash = '$2a$12$WiR7UiuIf4CFic5Ps/aO7edLW5.72TaIOtpfLsyGOIu9UKqg2Tm3O';
$timeout_duration = 1; 

if (isset($_GET['get_secret'])) {
    if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
        echo "https://cpanel.infinityfree.com/<br>if0_40979562<br>XaqSrFTid8E57SV";
    } else {
        header('HTTP/1.1 403 Forbidden');
        echo "UNAUTHORIZED";
    }
    exit;
}


if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}


if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout_duration)) {
        session_unset();
        session_destroy();
        header("Location: index.php?expired=1");
        exit;
    }
    $_SESSION['last_activity'] = time();
}


$error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if (password_verify($_POST['password'], $password_hash)) {
        $_SESSION['authenticated'] = true;
        $_SESSION['last_activity'] = time();
    } else {
        $error = "ACCESS DENIED.";
    }
}

$authenticated = $_SESSION['authenticated'] ?? false;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terminal Access</title>
    <?php if ($authenticated): ?>
    <meta http-equiv="refresh" content="6">
    <?php endif; ?>
    
    <style>
        body {
            background-color: #000;
            color: #fff;
            font-family: 'Courier New', Courier, monospace;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            text-transform: uppercase;
        }
        .container {
            border: 1px solid #fff;
            padding: 50px;
            text-align: center;
            height: 50%; width: 50%;
        }
        input[type="password"], .inline-input {
            background: transparent;
            border: none;
            border-bottom: 1px solid #fff;
            color: #fff;
            padding: 5px;
            margin: 10px 0;
            text-align: center;
            outline: none;
            font-family: inherit;
        }
        button {
            background: #fff;
            color: #000;
            border: none;
            padding: 8px 20px;
            cursor: pointer;
            font-weight: bold;
            text-transform: uppercase;
        }
       
        #O {
            text-decoration: none;
        }
        #content {
            display: none; 
            font-size: 0.9rem;
            margin-top: 5px;
            border: none;
            padding-top: 5px;
            color: #000000;
            user-select: auto
        }
        .status-msg {
            font-size: 0.7rem;
            margin-bottom: 15px;
            letter-spacing: 2px;
        }
        .error {
            color: #fff;
            font-size: 0.7rem;
            margin-top: 10px;
            display: block;
        }
    </style>
</head>
<body>

<div class="container">
    <?php if ($authenticated): ?>
        <h1 style="letter-spacing: 10px;">
            HELL<span id="O" onclick="toggleSecret()">O</span>
        </h1>
        
        <div style="margin-bottom: 20px;">
            <h2 style="font-size: 1rem;">What is your name?</h2>
            <form action="index.php" method="GET">
                <input type="text" name="user_name" class="inline-input" placeholder="NAME" autocomplete="off">
                <br>
                <button type="submit">SUBMIT</button>
            </form>
            
            <?php if (isset($_GET['user_name'])): ?>
                <span class="error">ERROR: INCORRECT</span><?php endif; ?><br>
        <a href="?logout=1" style="color:#666; font-size: 0.7rem; text-decoration: none;">[ DISCONNECT ]</a><p id="content"></p>
 </div>

        <script>
            async function toggleSecret() {
                var x = document.getElementById("content");
                if (x.style.display === "none" || x.innerHTML === "") {
                    x.innerHTML = "LOGGING IN...";
                    x.style.display = "block";
                    
                    try {
                        const response = await fetch('index.php?get_secret=1');
                        if (response.ok) {
                            const data = await response.text();
                            x.innerHTML = data;
                        } else {
                            window.location.reload(); 
                        }
                    } catch (err) {
                        x.innerHTML = "LINK SEVERED.";
                    }
                } else {
                    x.style.display = "none";
                }
            }
        </script>

    <?php else: ?>
        <form method="POST">
            <div class="status-msg">
                <?php 
                    if ($error) echo $error;
                    elseif (isset($_GET['expired'])) echo "SESSION EXPIRED";
                    else echo "SYSTEM LOCKED";
                ?>
            </div>
            <input type="password" name="password" placeholder="PASSWORD" required autofocus>
            <br>
            <button type="submit">ENTER</button>
        </form>
    <?php endif; ?>
</div>

</body>
</html>