<?php
// Set session lifetime to 5 minutes (300 seconds)
$session_timeout = 300; 
ini_set('session.gc_maxlifetime', $session_timeout);
session_set_cookie_params($session_timeout);

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
    // Ensure the log file path is correct relative to dashboard.php's location
    // FIX: Changed '../../publicUser.log' to '../publicUser.log'
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


// --- Inactivity Timeout Check ---
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > $session_timeout)) {
    // Last activity was more than 5 minutes ago
    // LOG: Session Timeout before destroying session
    if (isset($_SESSION['contract'])) {
        logEvent($_SESSION['contract'], 'Session Timeout', $_SERVER['REMOTE_ADDR'] ?? null);
    }
    
    session_unset();      // unset $_SESSION variable for the run-time  
    session_destroy();    // destroy session data in storage
    // Redirect to login page with a timeout message (optional, but good practice)
    header('Location: index.php?timeout=1');
    exit;
}

// --- Update last activity timestamp on every successful page load ---
$_SESSION['LAST_ACTIVITY'] = time();

// Check if config.php exists and include it, otherwise show an error
if (!file_exists('../config.php')) {
    die('<div style="color: red; padding: 20px; font-family: sans-serif;">FATAL ERROR: config.php is missing. Please ensure it is uploaded and correctly named.</div>');
}
// Include configuration file to load $blood_groups and $locations arrays
require_once '../config.php';  

// --- 1. Authentication Check ---
if (!isset($_SESSION['contract'])) {
    // User is not logged in, redirect to login page
    header('Location: index.php');
    exit;
}

$current_contract = $_SESSION['contract'];
$success_message = '';
$error_message = '';

// --- Helper Functions ---

/**
 * Reads donor data from donors.json. Includes error handling for file access and JSON decoding.
 * @return array The donor data array or an empty array on failure.
 */
function getDonorData() {
    $path = '../../protected_data/donors.json';
    if (!file_exists($path)) {
        error_log("donors.json file not found.");
        return [];
    }
    $content = file_get_contents($path);
    if ($content === false) {
        error_log("Failed to read donors.json.");
        return [];
    }
    $data = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON decoding error in donors.json: " . json_last_error_msg());
        return [];
    }
    return $data ?: [];
}

/**
 * Writes donor data to donors.json. Includes error handling for JSON encoding and file writing.
 * @param array $data The data array to save.
 * @return bool True on success, false on failure.
 */
function saveDonorData($data) {
    $path = '../../protected_data/donors.json';
    $json_content = json_encode($data, JSON_PRETTY_PRINT);
    if ($json_content === false) {
        error_log("JSON encoding error when saving donors.json: " . json_last_error_msg());
        return false;
    }
    if (file_put_contents($path, $json_content) === false) {
        error_log("Failed to write to donors.json. Check file permissions.");
        return false;
    }
    return true;
}

/**
 * Reads authentication data from authData.json. Includes error handling.
 * @return array The authentication data array or an empty array on failure.
 */
function getAuthData() {
    $path = 'authData.json';
    if (!file_exists($path)) {
        error_log("authData.json file not found.");
        return [];
    }
    $content = file_get_contents($path);
    if ($content === false) {
        error_log("Failed to read authData.json.");
        return [];
    }
    $data = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON decoding error in authData.json: " . json_last_error_msg());
        return [];
    }
    return $data ?: [];
}

/**
 * Writes authentication data to authData.json. Includes error handling.
 * @param array $data The data array to save.
 * @return bool True on success, false on failure.
 */
function saveAuthData($data) {
    $path = 'authData.json';
    $json_content = json_encode($data, JSON_PRETTY_PRINT);
    if ($json_content === false) {
        error_log("JSON encoding error when saving authData.json: " . json_last_error_msg());
        return false;
    }
    if (file_put_contents($path, $json_content) === false) {
        error_log("Failed to write to authData.json. Check file permissions.");
        return false;
    }
    return true;
}

// --- 2. Action Handling ---

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'edit_data') {
        $donor_data = getDonorData();
        if (!is_array($donor_data)) {
            $error_message = "Database file (donors.json) could not be loaded or is corrupted.";
            logEvent($current_contract, 'Edit Data Failed: Database load error', $_SERVER['REMOTE_ADDR'] ?? null); // LOG
        } else {
            $is_updated = false;
            
            // Sanitize input from form
            $new_contact = filter_input(INPUT_POST, 'contact', FILTER_SANITIZE_STRING);
            $new_group = filter_input(INPUT_POST, 'group', FILTER_SANITIZE_STRING);
            $new_location = filter_input(INPUT_POST, 'location', FILTER_SANITIZE_STRING);

            // Find the current user's record
            foreach ($donor_data as $key => $donor) {
                // Match the session contract against the 'contact' key in the JSON file
                if ($donor['contact'] === $current_contract) {
                    
                    // --- Handle Contract/Login Update if the contact number has changed ---
                    if (trim($new_contact) && trim($new_contact) !== $current_contract) {
                        $auth_data = getAuthData();
                        
                        // 1. Check if the new contract is already in use by another user
                        if (isset($auth_data[$new_contact]) && $auth_data[$new_contact] !== ($auth_data[$current_contract] ?? null)) {
                            $error_message = "The new contract number is already registered by another user.";
                            logEvent($current_contract, 'Edit Data Failed: New contract already taken', $_SERVER['REMOTE_ADDR'] ?? null); // LOG
                            return;
                        }
                        
                        // 2. Transfer hash to new key and update session
                        if (isset($auth_data[$current_contract])) {
                            $hash_to_transfer = $auth_data[$current_contract];
                            unset($auth_data[$current_contract]); // Remove old key
                            $auth_data[$new_contact] = $hash_to_transfer; // Set new key
                            if (saveAuthData($auth_data)) {
                                $_SESSION['contract'] = $new_contact; // Update session
                                $current_contract = $new_contact; // Update local variable
                                $success_message .= " Your login contract number was also updated.";
                                logEvent($current_contract, 'Contact/Login Updated Successfully', $_SERVER['REMOTE_ADDR'] ?? null); // LOG for contact change
                            } else {
                                $error_message = "Could not update login contract number due to save error.";
                                logEvent($current_contract, 'Edit Data Failed: Auth data save error for contract change', $_SERVER['REMOTE_ADDR'] ?? null); // LOG
                                return;
                            }

                        } else {
                            $error_message = "Could not find your current contract in the authentication data to update.";
                            logEvent($current_contract, 'Edit Data Failed: Current contract not found in Auth data', $_SERVER['REMOTE_ADDR'] ?? null); // LOG
                            return;
                        }
                    }
                    // --- End Handle Contract/Login Update ---

                    // Update fields
                    $donor_data[$key]['name'] = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
                    $donor_data[$key]['group'] = $new_group;
                    $donor_data[$key]['location'] = $new_location;

                    // Update the contact number in donors.json with the (potentially new) contract number
                    $donor_data[$key]['contact'] = $current_contract; 
                    
                    $donor_data[$key]['last_donation'] = filter_input(INPUT_POST, 'last_donation', FILTER_SANITIZE_STRING);
                    
                    // Update modification timestamp
                    $donor_data[$key]['updated_at'] = date('Y-m-d H:i:s');  

                    if (saveDonorData($donor_data)) {
                        $success_message = "Your donor data has been successfully updated!" . ($success_message ? $success_message : '');
                        logEvent($current_contract, 'Donor Data Edited Successfully', $_SERVER['REMOTE_ADDR'] ?? null); // LOG for data edit
                    } else {
                        $error_message = "Failed to save donor data. Check server logs for details.";
                        logEvent($current_contract, 'Edit Data Failed: Donor data save error', $_SERVER['REMOTE_ADDR'] ?? null); // LOG
                    }
                    $is_updated = true;
                    break;
                }
            }
            if (!$is_updated) {
                $error_message = "Could not find your record to update.";
                logEvent($current_contract, 'Edit Data Failed: Record not found in donors.json', $_SERVER['REMOTE_ADDR'] ?? null); // LOG
            }
        }

    } elseif ($action === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error_message = "All password fields are required.";
        } elseif ($new_password !== $confirm_password) {
            $error_message = "New password and confirmation do not match.";
        } elseif (strlen($new_password) < 4) {
             $error_message = "New password must be at least 4 characters long.";
        } else {
            $auth_data = getAuthData();
            // Check if auth_data loaded successfully
            if (!is_array($auth_data)) {
                $error_message = "Authentication file (authData.json) could not be loaded or is corrupted.";
                logEvent($current_contract, 'Change Password Failed: Auth data load error', $_SERVER['REMOTE_ADDR'] ?? null); // LOG
            } else {
                $stored_hash = $auth_data[$current_contract] ?? null;

                if ($stored_hash && password_verify($current_password, $stored_hash)) {
                    // Current password is correct, hash and update the new password
                    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $auth_data[$current_contract] = $new_hash;
                    
                    if (saveAuthData($auth_data)) {
                        $success_message = "Your password has been successfully changed!";
                        logEvent($current_contract, 'Password Changed Successfully', $_SERVER['REMOTE_ADDR'] ?? null); // LOG
                    } else {
                        $error_message = "Failed to save new password. Check server logs for details.";
                        logEvent($current_contract, 'Change Password Failed: Auth data save error', $_SERVER['REMOTE_ADDR'] ?? null); // LOG
                    }
                } else {
                    $error_message = "Current password is incorrect.";
                    logEvent($current_contract, 'Change Password Failed: Incorrect current password', $_SERVER['REMOTE_ADDR'] ?? null); // LOG
                }
            }
        }
    } elseif ($action === 'logout') {
        // LOG: Logout before destroying session
        logEvent($current_contract, 'User Logout', $_SERVER['REMOTE_ADDR'] ?? null);
        
        // Clear session data
        $_SESSION = array();
        session_destroy();
        header('Location: index.php');
        exit;
    }
}

// --- 3. Load Current User Data for Display ---

$all_donor_data = getDonorData();
$user_data = null;

if (!is_array($all_donor_data)) {
    // If getDonorData failed, ensure a visible error
    if (!$error_message) {
        $error_message = "Critical: Donor database could not be loaded.";
    }
} else {
    foreach ($all_donor_data as $donor) {
        // Match the session contract against the 'contact' key in the JSON file
        if ($donor['contact'] === $current_contract) {
            $user_data = $donor;
            break;
        }
    }

    if (!$user_data) {
        // This usually means the user is logged in but their contact wasn't found in donors.json
        $error_message = "Your user data is pending for ADMIN APPROVAL.";
    }
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../../assets/images/favicon.ico">
    <title>Dashboard - Donor Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/fontawesome7/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .bg-blood-red { background-color: #cc0000; }
        .text-blood-red { color: #cc0000; }
        .border-blood-red { border-color: #cc0000; }
        
        /* 1. Base Dimensions & Look */
        .input-style {
            padding: 10px 15px; /* Comfortable vertical/horizontal padding */
            border: 1px solid #ccc; /* Thinner, softer border color */
            border-radius: 6px; /* Slightly rounded corners for a modern feel */
            width: 100%; /* Ensure it takes full width of its container */
            box-sizing: border-box; /* Ensures padding and border don't increase the total width/height */
            font-size: 16px; /* Standard readable text size */
            transition: all 0.2s ease-in-out; /* Smooth transitions for hover/focus effects */
            
            /* 2. Background and Text */
            background-color: #ffffff; /* Clean white background */
            color: #333; /* Dark gray text for readability */

            /* 3. Appearance Overrides (especially for <select>) */
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            outline: none; /* Remove default focus outline */
        }

        /* 4. Focus State (Crucial for professional design) */
        .input-style:focus {
            border-color: #007bff; /* Highlight border with a primary brand color */
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25); /* Subtle blue glow/shadow when active */
        }

        /* 5. Hover State (Good practice for desktop usability) */
        .input-style:hover {
            border-color: #999; /* Slightly darker border on hover */
        }
        
        /* New helper class for small form labels */
        .form-label {
            /* Making labels slightly larger (sm) and ensuring bold (700) is applied */
            /* Removed redundant font-medium, ensured font-bold is present */
            @apply block text-sm font-bold text-gray-700 mb-1 truncate;  
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">

    <nav class="bg-blood-red shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <h1 class="text-white text-2xl font-bold"><i class="fa-solid fa-database"></i> Donor Dashboard</h1>
                <div class="flex items-center space-x-4">
                    <span class="text-white text-sm" style="text-align: right;">
                        Welcome, 
                        <?php if ($user_data): ?>
                            <?= htmlspecialchars($user_data['name']) ?> <br> (<?= htmlspecialchars($current_contract) ?>)
                        <?php else: ?> 
                            <?= htmlspecialchars($current_contract) ?>
                        <?php endif; ?>
                    </span>
                    <form method="POST" action="dashboard.php" class="inline">
                        <input type="hidden" name="action" value="logout">
                        <button type="submit" class="bg-white text-blood-red hover:bg-gray-200 text-sm font-semibold py-1 px-3 rounded-full transition duration-150">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">

        <?php if ($success_message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6" role="alert">
                <p class="font-bold"><i class="fas fa-check-circle"></i> Success!</p>
                <p class="text-sm"><?= htmlspecialchars($success_message) ?></p>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6" role="alert">
                <p class="font-bold"><i class="fas fa-exclamation-triangle"></i> Error</p>
                <p class="text-sm"><?= htmlspecialchars($error_message) ?></p>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <div class="lg:col-span-1 bg-white p-6 rounded-xl shadow-lg border-t-4 border-blood-red h-fit">
                <h3 class="text-xl font-bold mb-4 text-blood-red border-b pb-2"><i class="fas fa-info-circle"></i> Your Present Information</h3>
                <?php if ($user_data): ?>
                    <div class="space-y-3 text-gray-700">
                        <p><strong><i class="fas fa-user"></i> Name:</strong> <?= htmlspecialchars($user_data['name']) ?></p>
                        <p><strong><i class="fas fa-tint"></i> Blood Group:</strong> <span class="font-bold text-blood-red text-lg"><?= htmlspecialchars($user_data['group']) ?></span></p>
                        <p><strong><i class="fas fa-map-marker-alt"></i> Location:</strong> <?= htmlspecialchars($user_data['location']) ?></p>
                        <p><strong><i class="fas fa-phone"></i> Contract:</strong> <?= htmlspecialchars($user_data['contact']) ?></p> 
                        <p><strong><i class="fas fa-calendar-alt"></i> Last Donation:</strong> <?= htmlspecialchars($user_data['last_donation']) ?></p>
                    </div>
                <?php else: ?>
                    <p class="text-red-500">User data could not be loaded.</p>
                <?php endif; ?>
            </div>

            <div class="lg:col-span-2 space-y-8">
                
                <div class="bg-white p-6 rounded-xl shadow-lg border-l-4 border-blood-red">
                    <h3 class="text-xl font-bold mb-6 text-blood-red border-b pb-2"><i class="fas fa-edit"></i> Edit Your Donor Data</h3>
                    <?php if ($user_data): // Only show the form if user data loaded successfully ?>
                        <form method="POST" action="dashboard.php">
                            <input type="hidden" name="action" value="edit_data">
                            
                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 border border-gray-300 divide-x divide-y divide-gray-300 rounded-lg overflow-hidden mb-6">
                                
                                <div class="p-3 bg-gray-50 sm:col-span-2 md:col-span-2">
                                    <label for="name" class="form-label font-bold"><i class="fas fa-user"></i> Name:</label>
                                    <input type="text" id="name" name="name" required class="input-style"
                                        value="<?= htmlspecialchars($user_data['name'] ?? '') ?>">
                                </div>
                                
                                <div class="p-3 bg-gray-50 sm:col-span-2 md:col-span-2">
                                    <label for="location" class="form-label font-bold"><i class="fas fa-map-marker-alt"></i> Location:</label>
                                    <select id="location" name="location" required class="input-style appearance-none bg-white">
                                        <option value="" disabled>Select Location</option>
                                        <?php 
                                            // Ensure $locations is an array before iterating (from config.php)
                                            if (isset($locations) && is_array($locations)): 
                                        ?>
                                            <?php foreach ($locations as $location): ?>
                                                <option value="<?= htmlspecialchars($location) ?>"
                                                    <?= (isset($user_data['location']) && $user_data['location'] === $location) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($location) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                
                                <div class="p-3 bg-gray-50">
                                    <label for="group" class="form-label font-bold"><i class="fas fa-tint"></i> Blood Group:</label>
                                    <select id="group" name="group" required class="input-style appearance-none bg-white">
                                        <option value="" disabled>Select Blood Group</option>
                                        <?php 
                                            // Ensure $blood_groups is an array before iterating (from config.php)
                                            if (isset($blood_groups) && is_array($blood_groups)): 
                                        ?>
                                            <?php foreach ($blood_groups as $group): ?>
                                                <option value="<?= htmlspecialchars($group) ?>"
                                                    <?= (isset($user_data['group']) && $user_data['group'] === $group) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($group) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                
                                <div class="p-3 bg-gray-50">
                                    <label for="contact" class="form-label font-bold"><i class="fas fa-phone"></i> Contract:</label>
                                    <input type="text" id="contact" name="contact" required class="input-style"
                                        value="<?= htmlspecialchars($user_data['contact'] ?? '') ?>">
                                </div>
                                
                                <div class="p-3 bg-gray-50 sm:col-span-2 md:col-span-2">
                                    <label for="last_donation" class="form-label font-bold"><i class="fas fa-calendar-alt"></i> Last Donation:</label>
                                    <input type="date" id="last_donation" name="last_donation" required class="input-style"
                                        value="<?= htmlspecialchars($user_data['last_donation'] ?? '') ?>">
                                </div>
                            </div>

                            <button type="submit" class="bg-blood-red hover:bg-red-800 text-white font-bold py-2 px-4 rounded-lg transition duration-200">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </form>
                    <?php else: ?>
                        <p class="text-red-500">Cannot load donor data.</p>
                    <?php endif; ?>
                </div>

                <div class="bg-white p-6 rounded-xl shadow-lg border-l-4 border-blood-red">
                    <h3 class="text-xl font-bold mb-6 text-blood-red border-b pb-2"><i class="fas fa-shield-alt"></i> Change Password</h3>
                    <form method="POST" action="dashboard.php">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 border border-gray-300 divide-x divide-y divide-gray-300 rounded-lg overflow-hidden">
                            
                            <div class="p-3 bg-gray-50">
                                <label for="current_password" class="form-label font-bold"><i class="fas fa-lock"></i> Current Password:</label>
                                <input type="password" id="current_password" name="current_password" required class="input-style">
                            </div>
                            
                            <div class="p-3 bg-gray-50">
                                <label for="new_password" class="form-label font-bold"><i class="fas fa-key"></i> New Password:</label>
                                <input type="password" id="new_password" name="new_password" required class="input-style">
                            </div>
                            
                            <div class="p-3 bg-gray-50">
                                <label for="confirm_password" class="form-label font-bold"><i class="fas fa-check-circle"></i> Confirm New Password:</label>
                                <input type="password" id="confirm_password" name="confirm_password" required class="input-style">
                            </div>
                        </div>

                        <button type="submit" class="mt-6 bg-blood-red hover:bg-red-800 text-white font-bold py-2 px-4 rounded-lg transition duration-200">
                            <i class="fas fa-exchange-alt"></i> Update Password
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </main>

</body>
</html>