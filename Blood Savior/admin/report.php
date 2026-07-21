<?php
// report.php - Admin Security Log Viewer

// Start the session
session_start();

// Load configuration
require_once '../protected_data/config.php';

// --- Security Headers ---
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");

// --- Define Current Admin IP for Analysis ---
// Capture the current administrator's IP address for comparison against log entries.
$current_admin_ip = $_SERVER['REMOTE_ADDR']; 

// --- Authentication Check ---
// Ensure the user is logged in as admin
$is_admin = isset($_SESSION['admin_logged_in']) && 
            $_SESSION['admin_logged_in'] === true &&
            ($_SESSION['admin_ip'] ?? '') === $current_admin_ip; // Use current IP for check

// Check session timeout
if ($is_admin && isset($_SESSION['admin_login_time']) && time() - $_SESSION['admin_login_time'] > SESSION_TIMEOUT) {
    session_destroy();
    header('Location: index.php?timeout=1');
    exit;
}

// Redirect to login if not authenticated
if (!$is_admin) {
    header('Location: index.php');
    exit;
}

// --- Log Processing ---
$log_files = [
    'Security' => '../protected_data/security.log',
    'Public' => '../user/publicUser.log' // NEW: Added public user log file
];

$all_log_entries = [];

/**
 * Parses a log file into an array of structured entries.
 * @param string $file_path The path to the log file.
 * @return array An array of parsed log entries.
 */
function parse_log_file($file_path, $default_user_prefix) {
    $entries = [];
    
    if (file_exists($file_path)) {
        // Read file into an array
        $lines = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Expected format: [YYYY-MM-DD HH:MM:SS] | User: ... | Event: ... | IP: ...
            if (preg_match('/^\[(.*?)\] \| User: (.*?) \| Event: (.*?) \| IP: (.*?)$/', $line, $matches)) {
                $entries[] = [
                    'date' => $matches[1],
                    'user' => $matches[2],
                    'event' => $matches[3],
                    'ip' => $matches[4]
                ];
            } else {
                // Handle malformed lines professionally: log the raw line as the event.
                $entries[] = [
                    'date' => 'Malformed',
                    'user' => $default_user_prefix . '/Unknown',
                    'event' => '[PARSE ERROR] ' . htmlspecialchars(trim($line)),
                    'ip' => '-'
                ];
            }
        }
    }
    return $entries;
}

// Process all log files
foreach ($log_files as $prefix => $path) {
    $all_log_entries = array_merge($all_log_entries, parse_log_file($path, $prefix));
}

// Sort the combined array by date, newest first (descending)
// This uses the 'date' field which is in a sortable YYYY-MM-DD HH:MM:SS format
usort($all_log_entries, function($a, $b) {
    // Treat 'Malformed' entries as the oldest to push them to the bottom
    if ($a['date'] === 'Malformed') return 1;
    if ($b['date'] === 'Malformed') return -1;
    
    // Standard descending sort
    return strtotime($b['date']) - strtotime($a['date']);
});

$log_entries = $all_log_entries; // Use the combined and sorted array

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" type="image/svg+xml" href="../assets/images/favicon.ico">
    <title>Security Report - <?php echo defined('SITE_NAME') ? SITE_NAME : 'Admin Panel'; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../assets/fontawesome7/css/all.min.css">
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap');
        body { font-family: 'Inter', sans-serif; background-color: #f0f4f8; }
        
        .fa-icon { margin-right: 8px; } /* Added from index.php */
        .fa-icon-sm { font-size: 0.875rem; margin-right: 4px; } /* Added from index.php */

        .admin-card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid #e5e7eb;
        }
        
        /* Status colors for specific events */
        .event-login { color: #166534; background-color: #dcfce7; } /* Green */
        .event-fail { color: #991b1b; background-color: #fee2e2; } /* Red */
        .event-update { color: #1e40af; background-color: #dbeafe; } /* Blue */
        .event-default { color: #374151; background-color: #f3f4f6; } /* Gray */

        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
        }

        /* PROFESSIONAL ADDITION: Style for IP addresses that don't match the current admin */
        .ip-mismatch-row {
            background-color: #fef2f2 !important; /* Light red background */
            border-left: 5px solid #ef4444; /* Solid red border on the left */
            font-weight: 600; /* Bold for attention */
        }
        .ip-mismatch-row:hover {
            background-color: #fee2e2 !important; /* Slightly darker red on hover */
        }
        .ip-mismatch-row td {
            color: #b91c1c !important; /* Dark red text color for contrast */
        }

        /* Mobile Optimization for buttons */
        @media (max-width: 768px) {
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
                <i class="fas fa-chart-bar fa-icon"></i> Security Report
            </h1>
            
            <div class="flex flex-wrap justify-center items-center gap-2 md:gap-3">
                <a href="index.php" class="px-3 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition duration-150 text-sm font-medium mobile-touch-friendly">
                    <i class="fas fa-home fa-icon-sm"></i>Home
                </a>

                <a href="registrationManagement.php" class="px-3 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition duration-150 text-sm font-medium mobile-touch-friendly">
                    <i class="fas fa-user-clock fa-icon-sm"></i>Requests
                </a>

                <a href="report.php" class="px-3 py-2 bg-orange-800 text-white rounded-lg border border-orange-400 transition duration-150 text-sm font-medium mobile-touch-friendly">
                    <i class="fas fa-chart-bar fa-icon-sm"></i>Report
                </a>

                <a href="userManagement.php" class="px-3 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition duration-150 text-sm font-medium mobile-touch-friendly">
                    <i class="fas fa-users-cog fa-icon-sm"></i>Users
                </a>

                <a href="index.php?action=logout" class="px-4 py-2 bg-red-800 text-white rounded-lg hover:bg-red-900 transition duration-150 text-sm font-medium mobile-touch-friendly">
                    <i class="fas fa-sign-out-alt fa-icon"></i>Logout
                </a>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto py-8 p-4">
        
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-800">
                <i class="fas fa-shield-alt text-red-600 fa-icon"></i>Activity Log
            </h2>
            <div class="text-sm text-gray-500 bg-white px-4 py-2 rounded-lg shadow-sm border border-gray-200">
                Total Records: <strong><?php echo count($log_entries); ?></strong> | Your IP: <strong><?php echo htmlspecialchars($current_admin_ip); ?></strong>
            </div>
        </div>

        <div class="admin-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">
                                Date & Time
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">
                                User
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">
                                Event Description
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">
                                IP Address
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($log_entries)): ?>
                            <tr>
                                <td colspan="4" class="px-6 py-12 text-center text-gray-500">
                                    <i class="fas fa-clipboard-check text-4xl mb-3 text-gray-300"></i>
                                    <p>No logs found or log file is empty.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($log_entries as $entry): ?>
                                <?php 
                                    // Determine styling based on event content
                                    $event_lower = strtolower($entry['event']);
                                    $badge_class = 'event-default';
                                    
                                    if (strpos($event_lower, 'success') !== false || strpos($event_lower, 'admin login') !== false) {
                                        $badge_class = 'event-login';
                                    } elseif (strpos($event_lower, 'failed') !== false || strpos($event_lower, 'rejected') !== false || strpos($event_lower, 'error') !== false || strpos($event_lower, 'parse error') !== false) {
                                        $badge_class = 'event-fail';
                                    } elseif (strpos($event_lower, 'updated') !== false || strpos($event_lower, 'added') !== false || strpos($event_lower, 'deleted') !== false) {
                                        $badge_class = 'event-update';
                                    }

                                    // NEW: Determine if the log IP is different from the current admin's IP
                                    $row_class = '';
                                    // Only mark if the IP is known (not '-') and does not match the current admin's IP
                                    if ($entry['ip'] !== '-' && $entry['ip'] !== $current_admin_ip) {
                                        $row_class = 'ip-mismatch-row';
                                    }
                                ?>
                                <tr class="hover:bg-gray-50 transition-colors duration-150 <?php echo $row_class; ?>">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                        <i class="far fa-clock mr-1 text-gray-400"></i>
                                        <?php echo htmlspecialchars($entry['date']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($entry['user']); ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        <span class="badge <?php echo $badge_class; ?>">
                                            <?php echo htmlspecialchars($entry['event']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 font-mono">
                                        <?php 
                                            echo htmlspecialchars($entry['ip']); 
                                            // Add an indicator if the IP is flagged
                                            if ($row_class === 'ip-mismatch-row') {
                                                echo ' <i class="fas fa-exclamation-triangle ml-1" title="IP does not match your current session IP"></i>';
                                            }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
    </main>
    <script>
        // Add touch-friendly class to interactive elements for better mobile experience
        document.addEventListener('DOMContentLoaded', function() {
            const interactiveEls = document.querySelectorAll('a, button');
            interactiveEls.forEach(el => {
                el.classList.add('mobile-touch-friendly');
            });
        });
    </script>
</body>
</html>