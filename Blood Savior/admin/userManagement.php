<?php
// userManagement.php - Admin Panel for Managing User Credentials

session_start();

// Load configuration
require_once '../protected_data/config.php';
// ADDED: Include logging/utility functions if they exist (assuming it defines logSecurityEvent)
// require_once '../protected_data/security_utils.php'; 

// --- File Paths ---
$auth_file = '../user/login/authData.json';
$donors_file = '../protected_data/donors.json';

// --- Security Headers ---
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");

// --- Admin Authentication Check ---
$is_admin = isset($_SESSION['admin_logged_in']) && 
            $_SESSION['admin_logged_in'] === true &&
            ($_SESSION['admin_ip'] ?? '') === $_SERVER['REMOTE_ADDR'];

// ADDED: Initialize CSRF Token for logged-in admins
if ($is_admin && empty($_SESSION['csrf_token'])) {
    // Generate a secure, 32-byte (64 char hex) token
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); 
}
$csrf_token = $_SESSION['csrf_token'] ?? ''; // Get the expected token

// Check session timeout
if ($is_admin && isset($_SESSION['admin_login_time']) && time() - $_SESSION['admin_login_time'] > SESSION_TIMEOUT) {
    session_destroy();
    header('Location: index.php?timeout=1'); 
    exit;
}

// --- Handle Logout ---
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    if (function_exists('logSecurityEvent')) {
        logSecurityEvent("Admin logout", $_SESSION['admin_ip'] ?? 'unknown');
    }
    session_destroy();
    // Redirect to the login page
    header('Location: index.php'); 
    exit;
}
// --------------------------------------------------------

// Redirect if not admin
if (!$is_admin) {
    header('Location: ../admin'); 
    exit;
}

// --- Helper Functions ---
function get_json_data($filepath) {
    if (!file_exists($filepath)) return [];
    $content = @file_get_contents($filepath);
    // Add better check for content reading failure
    if ($content === false) return []; 
    return json_decode($content, true) ?: [];
}

function save_json_data($filepath, $data) {
    $json_data = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return file_put_contents($filepath, $json_data) !== false;
}

// --- Load Data for Display (Moved to BEFORE POST handler for consistency) ---
$auth_data = get_json_data($auth_file); 
$donors_data = get_json_data($donors_file); 

// Create a lookup map for donors by contact
$donor_map = [];
foreach ($donors_data as $donor) {
    if (isset($donor['contact'])) {
        $donor_map[$donor['contact']] = $donor;
    }
}

// --- Handle Form Submission (Password Change / Delete User / ADD USER) ---
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // CRITICAL FIX: CSRF Token Verification
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrf_token) {
        // Log the failure and exit/redirect to prevent session fixation/CSRF exploitation
        if (function_exists('logSecurityEvent')) {
            logSecurityEvent("CSRF token mismatch on POST attempt", $_SERVER['REMOTE_ADDR']);
        }
        $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4'><i class='fas fa-exclamation-triangle mr-2'></i>Error: Security token mismatch. Action prevented.</div>";
    } else {
        // Token is valid, proceed with action
        $action = $_POST['action'] ?? '';
        $target_contact = $_POST['contact'] ?? '';

        if (!empty($target_contact)) {
            // $auth_data is already loaded above
            
            // Basic Input Validation: Ensure contact format (e.g., numeric, if required) and length are acceptable
            // if (!preg_match('/^\d{10}$/', $target_contact)) {
            //     $message = "<div class='bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-4'><i class='fas fa-exclamation-circle mr-2'></i>Error: Invalid contact format.</div>";
            //     goto skip_action;
            // }


            if ($action === 'change_password') {
                $new_password = $_POST['new_password'] ?? '';
                
                if (isset($auth_data[$target_contact]) && !empty($new_password)) {
                    // Hash the new password using Bcrypt (default)
                    $auth_data[$target_contact] = password_hash($new_password, PASSWORD_DEFAULT);

                    // Save back to file
                    if (save_json_data($auth_file, $auth_data)) {
                        $message = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4'><i class='fas fa-check-circle mr-2'></i>Password successfully updated for user <strong>" . htmlspecialchars($target_contact) . "</strong>.</div>";
                        if (function_exists('logSecurityEvent')) {
                            logSecurityEvent("Password changed for user $target_contact", "admin");
                        }
                    } else {
                        $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4'><i class='fas fa-exclamation-triangle mr-2'></i>Error: Could not save data. Check file permissions.</div>";
                    }
                } else {
                    $message = "<div class='bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-4'><i class='fas fa-exclamation-circle mr-2'></i>Error: User not found or password empty.</div>";
                }
            } elseif ($action === 'delete_user') {
                if (isset($auth_data[$target_contact])) {
                    // FIX: $donor_map is now available due to reordering
                    $name = $donor_map[$target_contact]['name'] ?? 'Unknown User'; 
                    unset($auth_data[$target_contact]); // Remove the user from auth data

                    if (save_json_data($auth_file, $auth_data)) {
                        $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4'><i class='fas fa-trash-alt mr-2'></i>User <strong>" . htmlspecialchars($name) . "</strong> (" . htmlspecialchars($target_contact) . ") has been permanently deleted from authentication records.</div>";
                        if (function_exists('logSecurityEvent')) {
                            logSecurityEvent("User $target_contact deleted from authentication records", "admin");
                        }
                    } else {
                        $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4'><i class='fas fa-exclamation-triangle mr-2'></i>Error: Could not save data after deletion. Check file permissions.</div>";
                    }
                } else {
                    $message = "<div class='bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-4'><i class='fas fa-exclamation-circle mr-2'></i>Error: User not found in database for deletion.</div>";
                }
            } elseif ($action === 'add_user') {
                $new_password = $_POST['new_password'] ?? '';
                
                if (empty($target_contact) || empty($new_password)) {
                    $message = "<div class='bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-4'><i class='fas fa-exclamation-circle mr-2'></i>Error: Contact and New Password fields must not be empty.</div>";
                } elseif (isset($auth_data[$target_contact])) {
                     $message = "<div class='bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-4'><i class='fas fa-exclamation-circle mr-2'></i>Error: User <strong>" . htmlspecialchars($target_contact) . "</strong> already exists. Use the Update button to change their password.</div>";
                } else {
                    // Add the new user
                    $auth_data[$target_contact] = password_hash($new_password, PASSWORD_DEFAULT);
                    
                    if (save_json_data($auth_file, $auth_data)) {
                        $message = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4'><i class='fas fa-user-plus mr-2'></i>New user <strong>" . htmlspecialchars($target_contact) . "</strong> successfully added.</div>";
                        if (function_exists('logSecurityEvent')) {
                            logSecurityEvent("New user $target_contact added to authentication records", "admin");
                        }
                    } else {
                        $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4'><i class='fas fa-exclamation-triangle mr-2'></i>Error: Could not save data after adding user. Check file permissions.</div>";
                    }
                }
            }
        }
    }
}


// RE-LOAD AUTH DATA if a successful action modified it during the POST request lifecycle
$auth_data = get_json_data($auth_file); 
// Note: $donors_data and $donor_map are already loaded/created above the POST block

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../assets/images/favicon.ico">
    <title>User Management - <?php echo defined('SITE_NAME') ? SITE_NAME : 'Admin Panel'; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../assets/fontawesome7/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* CSS now aligned with report.php's styles */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap');
        body { font-family: 'Inter', sans-serif; background-color: #f0f4f8; } /* Matched report.php background */
        
        .fa-icon { margin-right: 8px; }
        .fa-icon-sm { font-size: 0.875rem; margin-right: 4px; }
        
        /* Removed min-height from touch-target/mobile-touch-friendly to fix height issue */

        @media (max-width: 768px) {
            /* Applying report.php's mobile button optimization for navigation links */
            .mobile-touch-friendly { 
                font-size: 0.75rem;
                padding: 0.5rem; 
            } 
            
            /* Keeping the necessary mobile optimizations for table/form actions */
            .action-buttons-group { flex-direction: column; align-items: stretch; }
            .action-buttons-group > * { width: 100%; }
            .actions-single-line { flex-direction: column; align-items: stretch; }
            .actions-single-line .form-inline { width: 100%; display: flex; flex-direction: row; gap: 8px; }
            .actions-single-line input[type="text"] { flex-grow: 1; }
            .actions-single-line button { flex-shrink: 0; }
        }
    </style>
</head>
<body class="text-gray-900">

    <header class="bg-red-700 text-white shadow-xl">
        <div class="max-w-7xl mx-auto flex flex-col md:flex-row justify-between items-center p-4 space-y-4 md:space-y-0">
            <h1 class="text-xl md:text-2xl font-extrabold tracking-tight">
                <i class="fas fa-users-cog fa-icon"></i> User Management
            </h1>
            <?php if ($is_admin): ?>
                <div class="flex flex-wrap justify-center items-center gap-2 md:gap-3">
                    
                    <a href="index.php" class="px-3 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition duration-150 text-sm font-medium mobile-touch-friendly">
                        <i class="fas fa-home fa-icon-sm"></i>Home
                    </a>

                    <a href="registrationManagement.php" class="px-3 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition duration-150 text-sm font-medium mobile-touch-friendly">
                        <i class="fas fa-user-clock fa-icon-sm"></i>Requests
                    </a>

                    <a href="report.php" class="px-3 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition duration-150 text-sm font-medium mobile-touch-friendly">
                        <i class="fas fa-chart-bar fa-icon-sm"></i>Report
                    </a>

                    <a href="userManagement.php" class="px-3 py-2 bg-purple-800 text-white rounded-lg border border-purple-400 transition duration-150 text-sm font-medium mobile-touch-friendly">
                        <i class="fas fa-users-cog fa-icon-sm"></i>Users
                    </a>

                    <a href="?action=logout" class="px-4 py-2 bg-red-800 text-white rounded-lg hover:bg-red-900 transition duration-150 text-sm font-medium mobile-touch-friendly">
                        <i class="fas fa-sign-out-alt fa-icon"></i>Logout
                    </a>
                </div>
            <?php else: ?>
                <a href="../index.php" class="px-4 py-2 bg-red-800 text-white rounded-lg hover:bg-red-900 transition duration-150 text-sm font-medium mobile-touch-friendly">
                    <i class="fas fa-arrow-left fa-icon"></i>Public Site
                </a>
            <?php endif; ?>
        </div>
    </header>

    <main class="max-w-7xl mx-auto p-4 py-8">
        
        <div class="mb-6 flex flex-col sm:flex-row sm:items-end justify-between">
            <div>
                <h2 class="text-2xl font-bold text-gray-800 flex items-center">
                    <i class="fas fa-users-cog fa-icon text-purple-700"></i> Active System Users
                </h2>
                <p class="text-gray-600 text-sm mt-1">Manage passwords and delete user authentication records.</p>
            </div>
            <div class="mt-4 sm:mt-0 text-sm font-medium bg-white px-4 py-2 rounded-lg shadow-sm border border-gray-200 text-gray-600">
                Total Users: <span class="text-purple-700 font-bold"><?php echo count($auth_data); ?></span>
            </div>
        </div>

        <?php echo $message; ?>

        <div class="bg-white rounded-xl shadow-md border border-purple-200 overflow-hidden p-5 mb-8">
            <h3 class="text-xl font-semibold text-purple-700 flex items-center mb-4">
                <i class="fas fa-user-plus fa-icon text-purple-500"></i> Add New User
            </h3>
            <form method="POST" class="flex flex-col md:flex-row items-stretch md:items-center gap-4">
                <input type="hidden" name="action" value="add_user">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                
                <input type="text" 
                       name="contact" 
                       placeholder="User Contact/ID (e.g., phone number)" 
                       required
                       class="w-full md:w-56 pl-3 pr-2 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none transition touch-target flex-shrink-0"
                       title="Contact/ID is usually the user's phone number or login identifier.">
                
                <input type="text" 
                       name="new_password" 
                       placeholder="New Password" 
                       required
                       class="w-full md:w-56 pl-3 pr-2 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none transition touch-target flex-shrink-0"
                       title="Set a temporary password for the new user.">
                
                <button type="submit" 
                        class="px-4 py-2 bg-purple-600 text-white text-sm font-medium rounded-lg hover:bg-purple-700 focus:ring-4 focus:ring-purple-200 transition flex items-center justify-center touch-target flex-shrink-0 w-full md:w-auto">
                    <i class="fas fa-plus mr-2"></i> Create User
                </button>
            </form>
            <p class="text-xs text-gray-500 mt-3 italic">
                Note: Adding a user here only creates the login credentials. The user's donor profile must be added separately via the registration form if they are a new donor.
            </p>
        </div>
        <div class="bg-white rounded-xl shadow-md border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200 text-xs uppercase text-gray-500 font-semibold tracking-wider">
                            <th class="p-3 min-w-[150px]">User Details</th>
                            <th class="p-3 min-w-[120px]">Contact (ID)</th>
                            <th class="p-3 min-w-[100px]">Last Modified</th>
                            <th class="p-3 min-w-[350px]">Actions (Password & Delete)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (empty($auth_data)): ?>
                            <tr>
                                <td colspan="4" class="p-8 text-center text-gray-500">
                                    <i class="fas fa-user-slash text-4xl mb-3 text-gray-300"></i>
                                    <p>No active users found in authData.json</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($auth_data as $contact => $hash): ?>
                                <?php 
                                    // Find donor details
                                    $donor = $donor_map[$contact] ?? null;
                                    $name = $donor ? $donor['name'] : '<span class="text-red-500 italic">Unknown (Orphaned Auth)</span>';
                                    
                                    // Determine "Last Modify" 
                                    $date_display = 'N/A';
                                    if ($donor && isset($donor['date'])) {
                                        $date_display = date('M j, Y | g:i:s', strtotime($donor['date']));
                                    } elseif ($donor && isset($donor['last_donation'])) {
                                         $date_display = date('M j, Y', strtotime($donor['last_donation']));
                                    }
                                ?>
                                <tr class="hover:bg-red-200 transition-colors">
                                    <td class="p-3 align-middle">
                                        <div class="font-bold text-gray-900"><?php echo htmlspecialchars(strip_tags($name)); ?></div>
                                        <?php if ($donor): ?>
                                            <div class="text-xs text-gray-500 mt-1">
                                                <i class="fas fa-map-marker-alt mr-1"></i> <?php echo htmlspecialchars($donor['location'] ?? ''); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td class="p-3 align-middle">
                                        <div class="flex items-center text-sm font-sans-serif text-black-500 bg-gray-100 px-2 py-1 rounded w-fit">
                                            <?php echo htmlspecialchars($contact); ?>
                                        </div>
                                    </td>
                                    
                                    <td class="p-3 align-middle text-sm text-gray-600">
                                        <i class="far fa-calendar-alt mr-1 text-gray-400"></i>
                                        <?php echo $date_display; ?>
                                    </td>
                                    
                                    <td class="p-3 align-middle">
                                        <div class="flex flex-row items-center gap-3 actions-single-line">
                                            <form method="POST" class="form-inline flex items-center gap-2">
                                                <input type="hidden" name="action" value="change_password">
                                                <input type="hidden" name="contact" value="<?php echo htmlspecialchars($contact); ?>">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                                
                                                <input type="text" 
                                                       name="new_password" 
                                                       placeholder="New Password" 
                                                       required
                                                       class="w-32 pl-3 pr-2 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none transition touch-target flex-shrink-0">
                                                
                                                <button type="submit" 
                                                        onclick="return confirm('Are you sure you want to change the password for <?php echo $contact; ?>?')"
                                                        class="px-3 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 focus:ring-4 focus:ring-indigo-200 transition flex items-center justify-center touch-target flex-shrink-0">
                                                    <i class="fas fa-key mr-2"></i> Update
                                                </button>
                                            </form>
                                            
                                            <form method="POST" class="form-inline">
                                                <input type="hidden" name="action" value="delete_user">
                                                <input type="hidden" name="contact" value="<?php echo htmlspecialchars($contact); ?>">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                                <button type="submit" onclick="return confirm('WARNING: Are you absolutely sure you want to PERMANENTLY DELETE the user <?php echo $contact; ?>? This action cannot be undone.')" class="px-3 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 focus:ring-4 focus:ring-red-200 transition flex items-center justify-center touch-target flex-shrink-0">
                                                    <i class="fas fa-trash-alt mr-2"></i> Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="bg-gray-50 px-4 py-3 border-t border-gray-200 text-xs text-gray-500">
                Note: Deleting a user here only removes their login credentials. Not Their donor profile.
            </div>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Updated to use 'a' for navigation links, consistent with report.php
            const interactiveEls = document.querySelectorAll('a, input, select, button'); 
            interactiveEls.forEach(el => {
                el.classList.add('mobile-touch-friendly');
            });
            
            // This is primarily for iOS to prevent viewport zoom on input focus
            document.addEventListener('touchstart', function(event) {
                if (event.target.tagName === 'INPUT' || event.target.tagName === 'SELECT' || event.target.tagName === 'TEXTAREA') {
                    event.target.style.fontSize = '16px';
                }
            }, false);
        });
    </script>
</body>
</html>