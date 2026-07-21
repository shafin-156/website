<?php
// Start the session to manage user state
session_start();

// --- NEW FUNCTION: Log Event ---
/**
 * Writes an event to the publicUser.log file, following the format.
 * @param string $user_id The identifier for the user (e.g., contract number or 'admin').
 * @param string $event The description of the event.
 * @param string $ip The IP address of the user.
 */
function logEvent($user_id, $event, $ip) {
    // Get current time in the required format
    $timestamp = date('Y-m-d H:i:s');
    // Ensure the log file path is correct relative to index.php's location
    $log_path = '../publicUser.log'; 
    
    // Check if IP is set, fall back to a default if not
    $ip_to_log = $ip ?: ($_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN_IP');

    $log_entry = "[$timestamp] | User: $user_id | Event: $event | IP: $ip_to_log\n";
    
    // Append the log entry to the file
    // FILE_APPEND ensures we don't overwrite existing logs
    // LOCK_EX prevents multiple writes at the same time
    file_put_contents($log_path, $log_entry, FILE_APPEND | LOCK_EX);
}
// --- END NEW FUNCTION ---

// Redirect to dashboard if already logged in
if (isset($_SESSION['contract'])) {
    header('Location: dashboard.php');
    exit;
}

// $error will store the message to be displayed on the page
$error = '';
// $redirect_error will be used to trigger the 3-second redirect
$redirect_error = false; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Get and sanitize input
    $contract = filter_input(INPUT_POST, 'contract', FILTER_SANITIZE_STRING);
    $password = $_POST['password'];
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null; // Get IP once

    if (empty($contract) || empty($password)) {
        // Basic validation failure (display error, no redirect)
        $error = "Please enter both contract number and password.";
        logEvent($contract ?: 'EMPTY_CONTRACT', 'Login Failed: Missing fields', $ip_address); // LOG
    } else {
        // 2. Load auth data
        $authDataPath = 'authData.json';
        if (!file_exists($authDataPath)) {
            $error = "Authentication data file not found.";
            logEvent($contract, 'Login Failed: Auth file missing', $ip_address); // LOG
        } else {
            $authData = json_decode(file_get_contents($authDataPath), true);

            // 3. Verify credentials
            if (isset($authData[$contract])) {
                // CONTRACT NUMBER MATCHES - Check password
                $storedHash = $authData[$contract];
                
                // Use password_verify to check the submitted password against the hash
                if (password_verify($password, $storedHash)) {
                    // Success! Store user contract in session
                    $_SESSION['contract'] = $contract;
                    logEvent($contract, 'User Login Successful', $ip_address); // LOG
                    header('Location: dashboard.php');
                    exit;
                } else {
                    // Password Mismatch (Contract found, but password wrong)
                    $error = "Contract or Password not match";
                    logEvent($contract, 'Failed login attempt: Incorrect password', $ip_address); // LOG
                }
            } else {
                // CONTRACT NUMBER NOT FOUND (Mismatch) - Display "User not register" AND Redirect
                $error = "User Not Register";
                $redirect_error = true;
                logEvent($contract, 'Failed login attempt: User Not Register', $ip_address); // LOG
            }
        }
    }
    
    // Clear POST data if an error occurred (either displayable or redirecting)
    if ($error || $redirect_error) {
        unset($_POST); 
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../../assets/images/favicon.ico">
    <title>Login - Donor Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/fontawesome7/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .bg-blood-red { background-color: #cc0000; }
        .text-blood-red { color: #cc0000; }
        .border-blood-red { border-color: #cc0000; }
        .nav-link { color: #374151; } /* Default text color for links */
        .nav-link:hover { color: #cc0000; } /* Hover color */
    </style>
    
    <?php if ($redirect_error): ?>
        <meta http-equiv="refresh" content="3;url=../registration/">
    <?php endif; ?>
    
</head>
<body class="bg-gray-100 min-h-screen">

    <header class="bg-white shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex-shrink-0">
                    <a href="../../" class="text-2xl font-bold text-blood-red flex items-center">
                        <i class="fa-solid fa-droplet mr-1 text-2xl"></i>
                        Blood Savior
                    </a>
                </div>
                
                <nav class="flex space-x-6">
                    <a href="../../" class="nav-link flex items-center text-sm font-medium">
                        <i class="fas fa-home mr-1.5"></i>
                        Home
                    </a>
                    <a href="../registration/" class="nav-link flex items-center text-sm font-medium">
                        <i class="fas fa-user-plus mr-1.5"></i>
                        Registration
                    </a>
                </nav>
            </div>
        </div>
    </header>
    
    <div class="flex items-center justify-center py-10">
        <div class="w-full max-w-sm">
            <div class="bg-white p-8 rounded-xl shadow-2xl border-t-8 border-blood-red">
                <h2 class="text-3xl font-bold mb-6 text-center text-blood-red">Donor Login</h2>
                
                <?php if ($error): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4" role="alert">
                        <p class="font-bold">Error</p>
                        <p class="text-sm">
                            <?= htmlspecialchars($error) ?>
                            <?php if ($redirect_error): ?>
                                ➤ Go to Regitration.
                            <?php endif; ?>
                        </p> 
                    </div>
                <?php endif; ?>

                <form action="index.php" method="POST">
                    <div class="mb-4">
                        <label for="contract" class="block text-gray-700 text-sm font-semibold mb-2"><i class="fas fa-phone mr-1"></i> Contract Number</label>
                        <input 
                            type="text" 
                            id="contract" 
                            name="contract" 
                            required 
                            placeholder="e.g., 01700000001"
                            class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blood-red focus:border-blood-red transition duration-150"
                            value="<?= htmlspecialchars($_POST['contract'] ?? '') ?>"
                        >
                    </div>
                    <div class="mb-6">
                        <label for="password" class="block text-gray-700 text-sm font-semibold mb-2">
    <i class="fas fa-key mr-1"></i> Password
    
    <span class="float-right">
        <a href="../../help" class="text-red-600 hover:text-blue-800 hover:underline text-xs">
            Forgot Password
        </a>
    </span>
</label>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            required 
                            placeholder="Enter your password"
                            class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 mb-3 leading-tight focus:outline-none focus:ring-2 focus:ring-blood-red focus:border-blood-red transition duration-150"
                        >
                    </div>
                    <div class="flex items-center justify-between">
                        
                        <button 
                            type="submit" 
                            class="bg-blood-red hover:bg-red-800 text-white font-bold py-3 px-4 rounded-lg focus:outline-none focus:shadow-outline w-full transition duration-200 ease-in-out transform hover:scale-105 shadow-md hover:shadow-lg"
                        >
                            <i class="fas fa-sign-in"></i> Log In
                        </button>
                    </div>
                </form>
                <p class="text-center text-xs text-gray-500 mt-6">
                    </p>
            </div>
        </div>
    </div>

</body>
</html>