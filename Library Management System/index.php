<?php 
require_once 'data/subConfig.php'; 

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: admin/");
    } else {
        header("Location: user/");
    }
    exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $u = $_POST['username'];
    $p = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param("s", $u);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $now = new DateTime();
        $lockout = $row['lockout_time'] ? new DateTime($row['lockout_time']) : null;

       
        if ($row['login_attempts'] >= 3) {
            if ($lockout && $lockout > $now) {
                $diff = $now->diff($lockout);
                $error = "User Locked. Try Again Later";
                system_log("Login Blocked: Locked account $u attempted access.");
            } else {
                
                $reset_stmt = $conn->prepare("UPDATE users SET login_attempts = 0, lockout_time = NULL WHERE user_id = ?");
                $reset_stmt->bind_param("i", $row['user_id']);
                $reset_stmt->execute();
                $row['login_attempts'] = 0; 
            }
        }

        
        if (empty($error)) {
            if (password_verify($p, $row['password'])) {
                
                $update_stmt = $conn->prepare("UPDATE users SET login_attempts = 0, lockout_time = NULL WHERE user_id = ?");
                $update_stmt->bind_param("i", $row['user_id']);
                $update_stmt->execute();

                $_SESSION['user_id'] = $row['user_id'];
                $_SESSION['role'] = $row['role'];
                $_SESSION['name'] = $row['full_name'];
                $_SESSION['last_activity'] = time();

                system_log("Login Success: $u logged in.");
                
                header("Location: " . ($_SESSION['role'] === 'admin' ? "admin/" : "user/"));
                exit;
            } else {
                
                $attempts = $row['login_attempts'] + 1;
                $lock_time = ($attempts >= 3) ? date('Y-m-d H:i:s', strtotime('+1 hour')) : null;
                
                $stmt_up = $conn->prepare("UPDATE users SET login_attempts = ?, lockout_time = ? WHERE user_id = ?");
                $stmt_up->bind_param("isi", $attempts, $lock_time, $row['user_id']);
                $stmt_up->execute();
                
                if ($attempts >= 3) {
                    $error = "User Locked. 3 failures detected.";
                    system_log("Account Locked: $u exceeded 3 attempts.");
                } else {
                    $error = "Invalid Username or Password. Attempt $attempts of 3.";
                    system_log("Login Failure: $u (Attempt $attempts)");
                }
            }
        }
    } else { 
        $error = "User or Password Incorrect.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BIST Library | Login</title>
    <link rel="icon" type="image/png" href="assets/images/favicon.png">
    <link rel="stylesheet" href="assets/fontawesome7/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        #loader-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: #ffffff; display: flex; justify-content: center; align-items: center; z-index: 9999; transition: opacity 0.5s ease; }
        .loader-img { width: 100px; height: 100px; }
        body { background: url('assets/images/loginBackground.jpg') no-repeat center center fixed; background-size: 100% 100%; height: 100vh; display: flex; align-items: center; font-family: 'Inter', sans-serif; margin: 0; }
        .login-card { border: 1px solid rgba(255, 255, 255, 0.3); border-radius: 12px; box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4); overflow: hidden; max-width: 340px; margin: auto; background: rgba(255, 255, 255, 0.25); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); color: white; }
        .brand-header { background: rgba(0, 0, 0, 0.2); padding: 25px 20px; text-align: center; border-bottom: 1px solid rgba(255, 255, 255, 0.1); }
        .brand-header h4 { font-size: 1.1rem; letter-spacing: 1.5px; margin-top: 8px; text-shadow: 1px 1px 3px rgba(0,0,0,0.3); }
        .form-label { color: #ffffff; font-size: 0.7rem; letter-spacing: 0.5px; text-shadow: 1px 1px 2px rgba(0,0,0,0.3); }
        .form-control { background: rgba(255, 255, 255, 0.9) !important; border: none; color: #333 !important; border-radius: 6px; font-size: 0.9rem; }
        .form-control:focus { box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.5); }
        .btn-primary { background: #3b82f6; border: none; padding: 10px; border-radius: 6px; font-weight: 700; text-transform: uppercase; font-size: 0.85rem; margin-top: 5px; transition: all 0.2s; }
        .btn-primary:hover { background: #2563eb; transform: translateY(-1px); }
        .alert { background: #ff4d4d; border: none; color: white; font-size: 0.75rem; font-weight: 600; }
    </style>
</head>
<body>
    <div id="loader-overlay"><img src="/assets/images/loadingBook.gif" alt="Loading..." class="loader-img"></div>
    <div class="login-card w-100">
        <div class="brand-header"><i class="fa-solid fa-book-bookmark fa-2x"></i><h4 class="fw-bold mb-0">BIST LIBRARY</h4></div>
        <div class="p-4">
            <?php if($error) echo "<div class='alert py-2 px-3 text-center mb-3 rounded'><i class='fa-solid fa-triangle-exclamation me-2'></i>$error</div>"; ?>
            <form method="POST" id="loginForm">
                <div class="mb-3">
                    <label class="form-label fw-bold"><i class="fa-regular fa-user"></i> USERNAME</label>
                    <input type="text" name="username" class="form-control" placeholder="Enter username" required>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-bold"><i class="fa-solid fa-key"></i> PASSWORD</label>
                    <input type="password" name="password" class="form-control" placeholder="Enter password" required>
                </div>
                <button type="submit" class="btn btn-primary w-100 shadow-sm"><i class="fa-solid fa-right-to-bracket"></i> LOGIN</button>
            </form>
        </div>
    </div>
    <script>
        window.addEventListener('load', function() {
            const loader = document.getElementById('loader-overlay');
            loader.style.opacity = '0';
            setTimeout(() => { loader.style.display = 'none'; }, 500);
        });
        document.getElementById('loginForm').addEventListener('submit', function() {
            const loader = document.getElementById('loader-overlay');
            loader.style.display = 'flex';
            loader.style.opacity = '1';
        });
    </script>
</body>
</html>