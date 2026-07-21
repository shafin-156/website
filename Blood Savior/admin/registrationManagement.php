<?php
// PHP Script for registrationManagement.php

// Start the session to manage login state
session_start();

// Load configuration and utility functions from parent directory
require_once '../protected_data/config.php';

// --- Logging Function (Conditional Definition for Safety) ---
if (!function_exists('logSecurityEvent')) {
    /**
     * Logs a security event message to the defined log file (LOG_FILE from config.php).
     */
    function logSecurityEvent(string $message, string $user_context = 'System') {
        // LOG_FILE is defined in config.php, which is required
        if (defined('LOG_FILE')) {
            $timestamp = date('Y-m-d H:i:s');
            // Sanitize message to prevent line breaks/control chars from external input
            $safe_message = str_replace(["\r", "\n"], ' ', $message); 
            $log_entry = sprintf("[%s] [CONTEXT: %s] %s\n", $timestamp, $user_context, $safe_message);
            
            // Use FILE_APPEND and LOCK_EX to safely add to the end of the log file
            @file_put_contents(LOG_FILE, $log_entry, FILE_APPEND | LOCK_EX);
        }
    }
}
// ------------------------------------

// --- File Paths ---
$requests_file = '../user/registration/registrationRequest.json';
$donors_file = '../protected_data/donors.json';

// --- ADDED: Auth File Paths ---
$auth_req_file = '../user/registration/authDataRequest.json';
$auth_main_file = '../user/login/authData.json';

// --- Utility Functions for this file ---

function load_requests() {
    global $requests_file;
    if (!file_exists($requests_file)) {
        return [];
    }
    $json_data = @file_get_contents($requests_file);
    return json_decode($json_data, true) ?: [];
}

function save_requests(array $requests) {
    global $requests_file;
    $json_data = json_encode(array_values($requests), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return file_put_contents($requests_file, $json_data) !== false;
}

function load_approved_donors() {
    global $donors_file;
    if (!file_exists($donors_file)) {
        @file_put_contents($donors_file, '[]');
        return [];
    }
    $json_data = @file_get_contents($donors_file);
    return json_decode($json_data, true) ?: [];
}

function save_approved_donors(array $donors) {
    global $donors_file;
    $json_data = json_encode(array_values($donors), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return file_put_contents($donors_file, $json_data) !== false;
}

// --- ADDED: Auth Helper Functions ---
function get_json_data($filepath) {
    if (!file_exists($filepath)) return [];
    $data = json_decode(@file_get_contents($filepath), true);
    return is_array($data) ? $data : [];
}

function save_json_data($filepath, $data) {
    return file_put_contents($filepath, json_encode($data, JSON_PRETTY_PRINT));
}

// --- ADDED: Duplicate Check Function ---
function is_duplicate_contact(string $contact, array $approved_donors): bool {
    // Normalize contact for comparison (assuming consistent format, trimming whitespace)
    $contact = trim($contact); 
    
    foreach ($approved_donors as $donor) {
        if (isset($donor['contact']) && $donor['contact'] === $contact) {
            return true;
        }
    }
    return false;
}
// ----------------------------------------

// --- Security Headers ---
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Check if the user is authenticated for the admin panel
$is_admin = isset($_SESSION['admin_logged_in']) && 
            $_SESSION['admin_logged_in'] === true &&
            ($_SESSION['admin_ip'] ?? '') === $_SERVER['REMOTE_ADDR'];

// Check session timeout
if ($is_admin && isset($_SESSION['admin_login_time']) && time() - $_SESSION['admin_login_time'] > SESSION_TIMEOUT) {
    session_destroy();
    $is_admin = false;
    header('Location: index.php?timeout=1'); 
    exit;
}

// --- ADDED: Auto-Redirect when no "Admin auth Access" ---
if (!$is_admin) {
    header('Location: ../admin');
    exit;
}

// --- Action Handlers (Only execute if admin is logged in) ---

if ($is_admin) {
    // Log out action (copied from index.php for consistency)
    if (isset($_GET['action']) && $_GET['action'] === 'logout') {
        logSecurityEvent("Admin logout", $_SESSION['admin_ip'] ?? 'unknown');
        session_destroy();
        header('Location: index.php');
        exit;
    }
    
    $requests = load_requests();
    $error_message = null; 

    // 1. Handle Approve Request
    if (isset($_GET['action']) && $_GET['action'] === 'approve' && isset($_GET['id'])) {
        $request_id_to_approve = $_GET['id'];
        $approved_request = null;
        
        $new_requests = [];
        foreach ($requests as $request) {
            if ($request['id'] === $request_id_to_approve) {
                $approved_request = $request;
            } else {
                $new_requests[] = $request;
            }
        }

        if ($approved_request) {
            
            $approved_donors = load_approved_donors();
            
            // --- ADDED: Duplicate Check Before Approval ---
            if (is_duplicate_contact($approved_request['contact'], $approved_donors)) {
                $error_message = "ERROR: Cannot approve. Donor with contact **" . htmlspecialchars($approved_request['contact']) . "** already exists in the approved list.";
                logSecurityEvent("Approval failed - Duplicate contact: " . $approved_request['name'], "admin");
                // Re-load requests to ensure current view is correct
                $requests = load_requests();
            } else {
            // --- End Duplicate Check ---

                $new_donor = [
                    'id'            => $approved_request['id'],
                    'name'          => htmlspecialchars($approved_request['name']),
                    'contact'       => htmlspecialchars($approved_request['contact']),
                    'location'      => htmlspecialchars($approved_request['location']),
                    'group'         => htmlspecialchars($approved_request['group']),
                    'last_donation' => $approved_request['last_donation'] && $approved_request['last_donation'] !== '1111-11-11' ? $approved_request['last_donation'] : date('Y-m-d'),
                    'date'          => date('Y-m-d H:i:s'), 
                    'added_by'      => 'admin_approved',
                    'ip'            => $_SERVER['REMOTE_ADDR'], 
                ];
                
                $approved_donors[] = $new_donor;

                // --- ADDED: Handle Auth Data Movement ---
                $auth_success = true;
                $contact_key = $approved_request['contact']; 
                
                $auth_requests_data = get_json_data($auth_req_file);
                $auth_main_data = get_json_data($auth_main_file);

                if (isset($auth_requests_data[$contact_key])) {
                    $auth_main_data[$contact_key] = $auth_requests_data[$contact_key];
                    unset($auth_requests_data[$contact_key]);

                    if (!save_json_data($auth_main_file, $auth_main_data) || !save_json_data($auth_req_file, $auth_requests_data)) {
                        $auth_success = false;
                    }
                }
                // ----------------------------------------

                if ($auth_success && save_approved_donors($approved_donors) && save_requests($new_requests)) {
                    logSecurityEvent("Registration approved: " . $approved_request['name'], "admin");
                    header('Location: registrationManagement.php?approved=1');
                    exit;
                } else {
                    $error_message = "CRITICAL ERROR: Failed to save donor, auth, or request data. Check file permissions.";
                    logSecurityEvent("CRITICAL: Failed to save after approval for " . $approved_request['name'], "admin");
                    $requests = load_requests();
                }
            } // End of Duplicate Check Else
        } else {
            $error_message = "Request ID not found.";
        }
    }

    // 2. Handle Reject Request
    if (isset($_GET['action']) && $_GET['action'] === 'reject' && isset($_GET['id'])) {
        $request_id_to_reject = $_GET['id'];
        $rejected_name = '';
        $rejected_contact = ''; 
        $found = false;
        
        $new_requests = [];
        foreach ($requests as $request) {
            if ($request['id'] === $request_id_to_reject) {
                $rejected_name = $request['name'];
                $rejected_contact = $request['contact']; 
                $found = true;
            } else {
                $new_requests[] = $request;
            }
        }
        
        if (!$found) {
            $error_message = "Request ID not found for rejection.";
        } else {
            // --- ADDED: Delete Auth Request Data ---
            $auth_req_data = get_json_data($auth_req_file);
            if (isset($auth_req_data[$rejected_contact])) {
                unset($auth_req_data[$rejected_contact]);
                save_json_data($auth_req_file, $auth_req_data);
            }
            // ---------------------------------------

            if (save_requests($new_requests)) {
                logSecurityEvent("Registration rejected: " . $rejected_name, "admin");
                header('Location: registrationManagement.php?rejected=1');
                exit;
            } else {
                $error_message = "Failed to delete request data. Check file permissions on `$requests_file`.";
                $requests = load_requests();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" type="image/svg+xml" href="../assets/images/favicon.ico">
    <title>Registration Management - <?php echo SITE_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../assets/fontawesome7/css/all.min.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    
    <style>
        /* CSS now aligned with report.php's styles */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap');
        body { font-family: 'Inter', sans-serif; background-color: #f0f4f8; }
        
        .admin-card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid #e5e7eb;
            transition: all 0.3s ease;
        }
        
        .admin-card:hover {
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
        }
        
        .blood-group-display {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.25rem;
            color: white;
            flex-shrink: 0;
        }
        
        .universal-bg { background: linear-gradient(135deg, #7c3aed 0%, #5b21b6 100%); }
        .regular-bg { background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%); }
        
        .request-item { transition: all 0.2s ease; border: 1px solid #e5e7eb; }
        .request-item:hover { border-color: #3b82f6; transform: translateY(-2px); }
        
        .action-btn {
            padding: 8px 12px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            font-size: 0.875rem;
            font-weight: 600;
        }
        
        .approve-btn { background-color: #10b981; color: white; }
        .approve-btn:hover { background-color: #059669; }
        
        .reject-btn { background-color: #fca5a5; color: #b91c1c; }
        .reject-btn:hover { background-color: #f87171; color: white; }
        
        .pending-badge {
            background-color: #f59e0b;
            color: white;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        /* ADDED: Duplicate Badge Style */
        .duplicate-badge {
            background-color: #ef4444; /* Red */
            color: white;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .fa-icon { margin-right: 8px; }
        .fa-icon-sm { font-size: 0.875rem; margin-right: 4px; }
        
        @media (max-width: 768px) {
            .admin-card { margin: 0.5rem; }
            .blood-group-display { width: 40px; height: 40px; font-size: 1rem; }
            .truncate-mobile { max-width: 100%; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
            .action-btn { padding: 6px 10px; font-size: 0.75rem; }
            
            /* MODIFIED: Replacing the min-height rule with report.php's mobile navigation optimization */
            .mobile-touch-friendly { 
                font-size: 0.75rem;
                padding: 0.5rem; 
            }
        }
    </style>
</head>
<body class="text-gray-900">

    <header class="bg-red-700 text-white shadow-xl">
        <div class="max-w-7xl mx-auto flex flex-col md:flex-row justify-between items-center p-4 space-y-4 md:space-y-0">
            <h1 class="text-xl md:text-2xl font-extrabold tracking-tight">
                <i class="fas fa-user-clock fa-icon"></i> Registration Requests
            </h1>
            <?php if ($is_admin): ?>
                <div class="flex flex-wrap justify-center items-center gap-2 md:gap-3">
                    
                    <a href="index.php" class="px-3 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition duration-150 text-sm font-medium mobile-touch-friendly">
                        <i class="fas fa-home fa-icon-sm"></i>Home
                    </a>

                    <a href="registrationManagement.php" class="px-3 py-2 bg-blue-800 text-white rounded-lg border border-blue-400 transition duration-150 text-sm font-medium mobile-touch-friendly">
                        <i class="fas fa-user-clock fa-icon-sm"></i>Requests
                    </a>

                    <a href="report.php" class="px-3 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition duration-150 text-sm font-medium mobile-touch-friendly">
                        <i class="fas fa-chart-bar fa-icon-sm"></i>Report
                    </a>

                    <a href="userManagement.php" class="px-3 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition duration-150 text-sm font-medium mobile-touch-friendly">
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

    <main class="max-w-7xl mx-auto py-8 p-4">

        <?php if (!$is_admin): ?>
            <div class="flex justify-center items-center min-h-[50vh]">
                <div class="admin-card p-6 md:p-8 max-w-sm w-full">
                    <h2 class="text-xl md:text-2xl font-bold text-red-600 mb-6 text-center">
                        <i class="fas fa-lock fa-icon"></i>Admin Access Required
                    </h2>
                    <p class="text-center text-red-500 mb-4">Please log in via the main admin page.</p>
                    <a href="index.php" class="w-full py-3 bg-red-600 text-white font-semibold rounded-lg hover:bg-red-700 transition duration-150 flex items-center justify-center mobile-touch-friendly">
                        <i class="fas fa-sign-in-alt fa-icon"></i>Go to Login
                    </a>
                </div>
            </div>
        <?php else: ?>
            <h2 class="text-2xl md:text-3xl font-bold text-blue-700 mb-8">
                <i class="fas fa-user-clock fa-icon"></i>Pending Donor Registration Requests
            </h2>
            
            <?php if (isset($_GET['approved'])): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6 flex items-center">
                    <i class="fas fa-check-circle fa-icon"></i> Donor registration approved and added to main list.
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['rejected'])): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6 flex items-center">
                    <i class="fas fa-trash fa-icon"></i> Donor registration request rejected and deleted.
                </div>
            <?php endif; ?>
            <?php if (isset($error_message)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6 flex items-center">
                    <i class="fas fa-exclamation-circle fa-icon"></i> <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <div class="space-y-4">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4">
                    <h3 class="text-lg md:text-xl font-semibold text-gray-800 flex items-center">
                        <i class="fas fa-list-ul fa-icon"></i>Requests to Review (<?php echo count($requests); ?>)
                    </h3>
                    <div class="text-sm text-gray-600 flex items-center mt-2 sm:mt-0">
                        <i class="fas fa-clock fa-icon-sm"></i>Last checked: <?php echo date('g:i A'); ?>
                    </div>
                </div>
                
                <div class="space-y-3 max-h-[80vh] overflow-y-auto pr-2">
                    <?php if (empty($requests)): ?>
                        <div class="admin-card p-8 text-center">
                            <div class="text-gray-400 text-4xl mb-4">
                                <i class="fas fa-box-open"></i>
                            </div>
                            <p class="text-gray-500">No pending donor registration requests found.</p>
                        </div>
                    <?php else: ?>
                        <?php 
                        // Load approved donors once for the loop
                        $approved_donors_list = load_approved_donors();
                        ?>
                        <?php foreach ($requests as $request): ?>
                            <?php 
                            $is_universal = str_contains($request['group'], '-'); 
                            // ADDED: Check for duplicate contact
                            $is_duplicate = is_duplicate_contact($request['contact'], $approved_donors_list);
                            ?>
                            
                            <div class="request-item admin-card p-4">
                                <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center space-y-3 lg:space-y-0">
                                    <div class="flex items-center space-x-3 md:space-x-4 flex-grow">
                                        <div class="blood-group-display <?php echo $is_universal ? 'universal-bg' : 'regular-bg'; ?>">
                                            <?php echo htmlspecialchars($request['group']); ?>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="flex flex-col sm:flex-row sm:items-center space-y-1 sm:space-y-0 sm:space-x-2">
                                                <p class="font-medium text-gray-900 truncate-mobile"><?php echo htmlspecialchars($request['name']); ?></p>
                                                <?php if ($is_duplicate): ?>
                                                    <span class="duplicate-badge flex items-center">
                                                        <i class="fas fa-exclamation-triangle fa-icon-sm"></i>Duplicate Contact
                                                    </span>
                                                <?php else: ?>
                                                    <span class="pending-badge flex items-center">
                                                        <i class="fas fa-hourglass-half fa-icon-sm"></i>Pending
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="flex flex-wrap gap-2 text-sm text-gray-500 mt-1">
                                                <span class="flex items-center">
                                                    <i class="fas fa-map-marker-alt fa-icon-sm"></i> <span class="truncate-mobile"><?php echo htmlspecialchars($request['location']); ?></span>
                                                </span>
                                                <span class="flex items-center">
                                                    <i class="fas fa-phone fa-icon-sm"></i> <span class="truncate-mobile"><?php echo htmlspecialchars($request['contact']); ?></span>
                                                </span>
                                                <span class="flex items-center">
                                                    <i class="far fa-calendar-alt fa-icon-sm"></i> <span title="Registration Date">Reg: <?php echo date('j M, Y | g:i:s', strtotime($request['date'])); ?></span>
                                                </span>
                                                <span class="flex items-center">
                                                     <i class="fa-solid fa-droplet fa-icon-sm"></i> <span title="Last Donation Date">Last Donated: <?php echo $request['last_donation'] && $request['last_donation'] !== '1111-11-11' ? date('j M, Y', strtotime($request['last_donation'])) : 'N/A'; ?></span>
                                                </span>
                                                <span class="text-xs flex items-center" title="Requester IP Address">
                                                    <i class="fas fa-network-wired fa-icon-sm"></i> IP: <?php echo htmlspecialchars($request['ip']); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-center space-x-3 self-end lg:self-auto flex-shrink-0">
                                        <a href="?action=approve&id=<?php echo htmlspecialchars($request['id']); ?>"
                                           class="action-btn mobile-touch-friendly 
                                           <?php echo $is_duplicate ? 'bg-gray-400 hover:bg-gray-500 text-gray-700 cursor-not-allowed' : 'approve-btn'; ?>"
                                           onclick="return <?php echo $is_duplicate ? 'false' : "confirm('Approve ".addslashes($request['name'])." ? This will add them to the main donor list.');"; ?>">
                                            <i class="fas fa-check fa-icon-sm"></i><?php echo $is_duplicate ? 'Duplicate' : 'Approve'; ?>
                                        </a>
                                        <a href="?action=reject&id=<?php echo htmlspecialchars($request['id']); ?>"
                                           class="action-btn reject-btn mobile-touch-friendly"
                                           onclick="return confirm('Reject <?php echo addslashes($request['name']); ?>? This will permanently delete the request.');">
                                            <i class="fas fa-times fa-icon-sm"></i>Reject
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </main>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Apply mobile-touch-friendly class to all interactive elements
            const interactiveEls = document.querySelectorAll('input, select, button, a');
            interactiveEls.forEach(el => {
                el.classList.add('mobile-touch-friendly');
            });
            
            // This prevents viewport zooming on mobile when an input field is focused
            document.addEventListener('touchstart', function(event) {
                if (event.target.tagName === 'INPUT' || event.target.tagName === 'SELECT' || event.target.tagName === 'TEXTAREA') {
                    event.target.style.fontSize = '16px';
                }
            }, false);
        });
    </script>
</body>
</html>