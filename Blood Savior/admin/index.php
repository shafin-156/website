<?php
// PHP Script for admin.php (now admin/index.php)

// Start the session to manage login state
session_start();

// Load configuration from parent directory
require_once '../protected_data/config.php';

// --- Utility Functions ---

function generateShortId() {
    $datePart = date('Ymd');
    $uniquePart = substr(uniqid(), -5); 
    return $datePart . $uniquePart;
}

// --- NEW AUTH DATA MANAGEMENT FUNCTIONS ---

const AUTH_DATA_FILE = '../user/login/authData.json';

function loadAuthData() {
    if (!file_exists(AUTH_DATA_FILE)) {
        return []; 
    }
    $data = file_get_contents(AUTH_DATA_FILE);
    return $data ? json_decode($data, true) : [];
}

function saveAuthData(array $data) {
    $json_data = json_encode($data, JSON_PRETTY_PRINT); 
    return (bool)file_put_contents(AUTH_DATA_FILE, $json_data);
}

function updateAuthUserContact(string $old_contact, ?string $new_contact) {
    $auth_data = loadAuthData();
    
    if (!isset($auth_data[$old_contact])) {
        return true; 
    }
    
    $password_hash = $auth_data[$old_contact]; 
    unset($auth_data[$old_contact]);
    
    if ($new_contact !== null && $new_contact !== '') {
        $auth_data[$new_contact] = $password_hash;
    }
    
    return saveAuthData($auth_data);
}

function deleteAuthUser(string $contact) {
    return updateAuthUserContact($contact, null);
}

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

// --- Action Handlers ---

// 1. Handle Login Attempt
if (isset($_POST['password']) && isset($_POST['action']) && $_POST['action'] === 'login') {
    $client_ip = $_SERVER['REMOTE_ADDR'];
    
    // Check rate limiting
    if (!checkRateLimit($client_ip)) {
        $error_message = "Too many failed attempts. Please wait and try again.";
        logSecurityEvent("Rate limit exceeded", $client_ip);
    } else {
        // Validate password using hash
        if (password_verify($_POST['password'], $admin_password_hash)) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_login_time'] = time();
            $_SESSION['admin_ip'] = $client_ip;
            
            // Call function to update last login time in admin_users.json
            updateAdminLoginTime('admin');
            
            logSecurityEvent("Admin login successful", "admin");
            header('Location: index.php');
            exit;
        } else {
            logFailedAttempt($client_ip);
            $error_message = "Invalid password. Access denied.";
            logSecurityEvent("Failed login attempt", $client_ip);
        }
    }
}

// 2. Handle Logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    logSecurityEvent("Admin logout", $_SESSION['admin_ip'] ?? 'unknown');
    session_destroy();
    header('Location: index.php');
    exit;
}

// If logged in, handle data manipulation
if ($is_admin) {
    $donors = load_donors();
    $edit_donor = null;
    $is_editing = false;

    // Handle Edit Donor
    if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
        $edit_donor = find_donor_by_id($_GET['id']);
        $is_editing = true;
    }

    // Handle Update Donor
    if (isset($_POST['action']) && $_POST['action'] === 'update_donor') {
        $donor_id = $_POST['id'];
        $donors = load_donors();
        
        // Sanitize new inputs
        $new_name = sanitizeInput($_POST['name']);
        $new_contact = sanitizeInput($_POST['contact']);
        $new_location = sanitizeInput($_POST['location']);
        $new_group = sanitizeInput($_POST['group']);
        $last_donation = sanitizeInput($_POST['last_donation'] ?? ''); 
        
        $update_successful = false;
        $old_contact = null;

        foreach ($donors as &$donor) {
            if ($donor['id'] === $donor_id) {
                // Store old contact before updating for authData.json sync
                $old_contact = $donor['contact'];
                
                // Update donor record
                $donor['name'] = $new_name;
                $donor['contact'] = $new_contact;
                $donor['location'] = $new_location;
                $donor['group'] = $new_group;
                $donor['last_donation'] = $last_donation;
                $donor['date'] = date('Y-m-d H:i:s');
                $donor['updated_by'] = 'admin';
                $donor['updated_at'] = date('Y-m-d H:i:s');
                
                $update_successful = true;
                break;
            }
        }
        
        if ($update_successful) {
            
            // --- MODIFICATION START: Sync with authData.json on contact change ---
            if ($old_contact !== null && $old_contact !== $new_contact) {
                if (!updateAuthUserContact($old_contact, $new_contact)) {
                    // Log or handle error if auth data sync fails
                    logSecurityEvent("ERROR: Failed to update authData.json contact for donor ID: " . $donor_id, "admin");
                }
            }
            // --- MODIFICATION END ---
            
            if (save_donors($donors)) {
                logSecurityEvent("Donor updated: " . $new_name, "admin");
                header('Location: index.php?updated=1');
                exit;
            } else {
                $error_message = "Failed to update donor data.";
            }
        }
    }

    // Handle Add Donor
    if (isset($_POST['action']) && $_POST['action'] === 'add_donor') {
        // MODIFIED: Use new short ID function
        $new_id = generateShortId(); 
        
        // NEW: Sanitize last_donation input
        $last_donation = sanitizeInput($_POST['last_donation'] ?? '');

        $new_donor = [
            'id' => $new_id, // Now a short ID
            'name' => sanitizeInput($_POST['name']),
            'contact' => sanitizeInput($_POST['contact']),
            'location' => sanitizeInput($_POST['location']),
            'group' => sanitizeInput($_POST['group']),
            // NEW: Add last_donation field
            'last_donation' => $last_donation,
            'date' => date('Y-m-d H:i:s'),
            'added_by' => 'admin',
            'ip' => $_SERVER['REMOTE_ADDR']
        ];
        
        $donors[] = $new_donor;
        if (save_donors($donors)) {
            logSecurityEvent("Donor added by admin: " . $new_donor['name'], "admin");
            header('Location: index.php?added=1');
            exit;
        } else {
            $error_message = "Failed to save donor data.";
        }
    }

    // Handle Delete Donor
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
        $donor_id_to_delete = $_GET['id'];
        $donor_name = '';
        $donor_contact = ''; // Store contact for authData.json sync
        
        foreach ($donors as $donor) {
            if ($donor['id'] === $donor_id_to_delete) {
                $donor_name = $donor['name'];
                $donor_contact = $donor['contact'];
                break;
            }
        }
        
        $donors = array_filter($donors, function($donor) use ($donor_id_to_delete) {
            return $donor['id'] !== $donor_id_to_delete;
        });
        
        if (save_donors($donors)) {
            
            // --- MODIFICATION START: Sync with authData.json on delete ---
            if ($donor_contact) {
                if (!deleteAuthUser($donor_contact)) {
                    logSecurityEvent("ERROR: Failed to delete authData.json user for donor ID: " . $donor_id_to_delete, "admin");
                }
            }
            // --- MODIFICATION END ---
            
            logSecurityEvent("Donor deleted by admin: " . $donor_name, "admin");
            header('Location: index.php?deleted=1');
            exit;
        } else {
            $error_message = "Failed to delete donor data.";
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
    <title>Admin Panel - <?php echo SITE_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../assets/fontawesome7/css/all.min.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    
    <style>
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
        }
        
        .universal-bg { background: linear-gradient(135deg, #7c3aed 0%, #5b21b6 100%); }
        .regular-bg { background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%); }
        
        .donor-item { transition: all 0.2s ease; border: 1px solid #e5e7eb; }
        .donor-item:hover { border-color: #dc2626; transform: translateY(-2px); }
        
        .action-btn {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }
        
        .edit-btn:hover { background-color: #dbeafe; color: #1d4ed8; }
        .delete-btn:hover { background-color: #fee2e2; color: #dc2626; }
        
        .success-badge {
            background-color: #10b981;
            color: white;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .admin-badge {
            background-color: #3b82f6;
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
            .grid { grid-template-columns: 1fr !important; }
            .lg\\:col-span-1, .lg\\:col-span-2 { grid-column: span 1 !important; }
            .action-btn { width: 32px; height: 32px; }
        }
    </style>
</head>
<body class="text-gray-900">

    <header class="bg-red-700 text-white shadow-xl">
        <div class="max-w-7xl mx-auto flex flex-col md:flex-row justify-between items-center p-4 space-y-4 md:space-y-0">
            <h1 class="text-xl md:text-2xl font-extrabold tracking-tight">
                <i class="fas fa-crown fa-icon"></i> Admin Panel
            </h1>
            <?php if ($is_admin): ?>
                <div class="flex flex-wrap justify-center items-center gap-2 md:gap-3">
                    
                    <a href="index.php" class="px-3 py-2 bg-green-800 text-white rounded-lg border border-green-400 transition duration-150 text-sm font-medium mobile-touch-friendly">
                        <i class="fas fa-home fa-icon-sm"></i>Home
                    </a>

                    <a href="registrationManagement.php" class="px-3 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition duration-150 text-sm font-medium mobile-touch-friendly">
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
                    <form action="index.php" method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="login">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-key fa-icon"></i> Admin Password
                            </label>
                            <input
                                type="password"
                                name="password"
                                placeholder="Enter Admin Password"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg text-center focus:ring-red-500 focus:border-red-500 mobile-touch-friendly"
                                required
                            />
                        </div>
                        <?php if (isset($error_message)): ?>
                            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg">
                                <div class="flex items-center">
                                    <i class="fas fa-exclamation-circle fa-icon"></i> <?php echo $error_message; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <button
                            type="submit"
                            class="w-full py-3 bg-red-600 text-white font-semibold rounded-lg hover:bg-red-700 transition duration-150 flex items-center justify-center mobile-touch-friendly"
                        >
                            <i class="fas fa-sign-in-alt fa-icon"></i>Log In
                        </button>
                    </form>
                    <p class="mt-6 text-sm text-gray-500 text-center">
                        <i class="fas fa-shield-alt fa-icon-sm"></i><strong>Note:</strong> Maximum login attempts is 3.
                    </p>
                </div>
            </div>
        <?php else: ?>
            <h2 class="text-2xl md:text-3xl font-bold text-red-700 mb-8">
                <i class="fas fa-tachometer-alt fa-icon"></i>Donor Management
            </h2>
            
            <?php if (isset($_GET['added'])): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6 flex items-center">
                    <i class="fas fa-check-circle fa-icon"></i> Donor added successfully.
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['updated'])): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6 flex items-center">
                    <i class="fas fa-check-circle fa-icon"></i> Donor data updated successfully.
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['deleted'])): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6 flex items-center">
                    <i class="fas fa-check-circle fa-icon"></i> Full donor data deleted successfully.
                </div>
            <?php endif; ?>
            <?php if (isset($error_message)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6 flex items-center">
                    <i class="fas fa-exclamation-circle fa-icon"></i> <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 md:gap-8">
                <div class="lg:col-span-1 admin-card p-4 md:p-6">
                    <?php if ($is_editing && $edit_donor): ?>
                        <h3 class="text-lg md:text-xl font-semibold text-red-700 mb-4 border-b pb-2 flex items-center">
                            <i class="fas fa-edit fa-icon"></i>Edit Donor Record
                        </h3>
                        <form action="index.php" method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="update_donor">
                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($edit_donor['id']); ?>">
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1 flex items-center">
                                    <i class="fas fa-user fa-icon-sm"></i>Donor Name
                                </label>
                                <input type="text" name="name" required 
                                       value="<?php echo htmlspecialchars($edit_donor['name']); ?>"
                                       class="w-full px-3 py-3 border border-gray-300 rounded-lg focus:ring-red-500 focus:border-red-500 mobile-touch-friendly" 
                                       placeholder="Full Name"/>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1 flex items-center">
                                    <i class="fas fa-tint fa-icon-sm"></i>Blood Type
                                </label>
                                <select name="group" required 
                                        class="w-full px-3 py-3 border border-gray-300 rounded-lg bg-white focus:ring-red-500 focus:border-red-500 mobile-touch-friendly">
                                    <?php foreach ($blood_groups as $group): ?>
                                        <option value="<?php echo $group; ?>"
                                            <?php echo $edit_donor['group'] === $group ? 'selected' : ''; ?>>
                                            <?php echo $group; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1 flex items-center">
                                    <i class="fas fa-phone fa-icon-sm"></i>Contact Info
                                </label>
                                <input type="text" name="contact" required 
                                       value="<?php echo htmlspecialchars($edit_donor['contact']); ?>"
                                       class="w-full px-3 py-3 border border-gray-300 rounded-lg focus:ring-red-500 focus:border-red-500 mobile-touch-friendly" 
                                       placeholder="Phone or Email"/>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1 flex items-center">
                                    <i class="fas fa-map-marker-alt fa-icon-sm"></i>Location
                                </label>
                                <select name="location" required 
                                        class="w-full px-3 py-3 border border-gray-300 rounded-lg bg-white focus:ring-red-500 focus:border-red-500 mobile-touch-friendly">
                                    <?php foreach ($locations as $location): ?>
                                        <option value="<?php echo htmlspecialchars($location); ?>"
                                            <?php echo $edit_donor['location'] === $location ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($location); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1 flex items-center">
                                    <i class="far fa-calendar-alt fa-icon-sm"></i>Last Donation Date
                                </label>
                                <input type="date" name="last_donation" required 
                                       value="<?php echo htmlspecialchars($edit_donor['last_donation'] ?? ''); ?>"
                                       class="w-full px-3 py-3 border border-gray-300 rounded-lg focus:ring-red-500 focus:border-red-500 mobile-touch-friendly" 
                                       max="<?php echo date('Y-m-d'); ?>"
                                       title="Date of the donor's last blood donation"/>
                            </div>
                            
                            <div class="flex flex-col sm:flex-row space-y-3 sm:space-y-0 sm:space-x-3">
                                <button type="submit" class="flex-1 py-3 bg-red-600 text-white font-semibold rounded-lg hover:bg-red-700 transition duration-150 flex items-center justify-center mobile-touch-friendly">
                                    <i class="fas fa-save fa-icon"></i>Save Changes
                                </button>
                                <a href="index.php" class="px-6 py-3 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition duration-200 flex items-center justify-center mobile-touch-friendly">
                                    <i class="fas fa-times fa-icon"></i>Cancel
                                </a>
                            </div>
                        </form>
                    <?php else: ?>
                        <h3 class="text-lg md:text-xl font-semibold text-red-700 mb-4 border-b pb-2 flex items-center">
                            <i class="fas fa-user-plus fa-icon"></i>Add New Donor Record
                        </h3>
                        <form action="index.php" method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="add_donor">
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1 flex items-center">
                                    <i class="fas fa-user fa-icon-sm"></i>Donor Name
                                </label>
                                <input type="text" name="name" required 
                                       class="w-full px-3 py-3 border border-gray-300 rounded-lg focus:ring-red-500 focus:border-red-500 mobile-touch-friendly" 
                                       placeholder="Full Name"/>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1 flex items-center">
                                    <i class="fas fa-tint fa-icon-sm"></i>Blood Type
                                </label>
                                <select name="group" required 
                                        class="w-full px-3 py-3 border border-gray-300 rounded-lg bg-white focus:ring-red-500 focus:border-red-500 mobile-touch-friendly">
                                    <?php foreach ($blood_groups as $group): ?>
                                        <option value="<?php echo $group; ?>"><?php echo $group; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1 flex items-center">
                                    <i class="fas fa-phone fa-icon-sm"></i>Contact Info
                                </label>
                                <input type="text" name="contact" required 
                                       class="w-full px-3 py-3 border border-gray-300 rounded-lg focus:ring-red-500 focus:border-red-500 mobile-touch-friendly" 
                                       placeholder="Phone or Email"/>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1 flex items-center">
                                    <i class="fas fa-map-marker-alt fa-icon-sm"></i>Location
                                </label>
                                <select name="location" required 
                                        class="w-full px-3 py-3 border border-gray-300 rounded-lg bg-white focus:ring-red-500 focus:border-red-500 mobile-touch-friendly">
                                    <option value="">-- Select Location --</option>
                                    <?php foreach ($locations as $location): ?>
                                        <option value="<?php echo htmlspecialchars($location); ?>">
                                            <?php echo htmlspecialchars($location); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1 flex items-center">
                                    <i class="far fa-calendar-alt fa-icon-sm"></i>Last Donation Date
                                </label>
                                <input type="date" name="last_donation" required 
                                       class="w-full px-3 py-3 border border-gray-300 rounded-lg focus:ring-red-500 focus:border-red-500 mobile-touch-friendly" 
                                       max="<?php echo date('Y-m-d'); ?>"
                                       title="Date of the donor's last blood donation"/>
                            </div>

                            <button type="submit" class="w-full py-3 bg-red-600 text-white font-semibold rounded-lg hover:bg-red-700 transition duration-150 flex items-center justify-center mobile-touch-friendly">
                                <i class="fas fa-save fa-icon"></i>Save Donor Record
                            </button>
                        </form>
                    <?php endif; ?>
                </div>

                <div class="lg:col-span-2 space-y-4">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4">
                        <h3 class="text-lg md:text-xl font-semibold text-gray-800 flex items-center">
                            <i class="fas fa-users fa-icon"></i>All Donors (<?php echo count($donors); ?>)
                        </h3>
                        <div class="text-sm text-gray-600 flex items-center mt-2 sm:mt-0">
                            <i class="fas fa-clock fa-icon-sm"></i>Last updated: <?php echo date('g:i A'); ?>
                        </div>
                    </div>
                    
                    <div class="space-y-3 max-h-[100vh] overflow-y-auto pr-2">
                        <?php if (empty($donors)): ?>
                            <div class="admin-card p-8 text-center">
                                <div class="text-gray-400 text-4xl mb-4">
                                    <i class="fas fa-user-slash"></i>
                                </div>
                                <p class="text-gray-500">No donor records found. Add one using the form.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($donors as $donor): ?>
                                <?php $is_universal = str_contains($donor['group'], '-'); ?>
                                
                                <div class="donor-item admin-card p-4">
                                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center space-y-3 sm:space-y-0">
                                        <div class="flex items-center space-x-3 md:space-x-4">
                                            <div class="blood-group-display <?php echo $is_universal ? 'universal-bg' : 'regular-bg'; ?>">
                                                <?php echo htmlspecialchars($donor['group']); ?>
                                            </div>
                                            <div>
                                                <div class="flex flex-col sm:flex-row sm:items-center space-y-1 sm:space-y-0 sm:space-x-2">
                                                    <p class="font-medium text-gray-900 truncate-mobile"><?php echo htmlspecialchars($donor['name']); ?></p>
                                                    <?php if (isset($donor['added_by']) && $donor['added_by'] === 'admin'): ?>
                                                        <span class="admin-badge flex items-center">
                                                            <i class="fas fa-user-shield fa-icon-sm"></i>Admin
                                                        </span>
                                                    <?php endif; ?>
                                                    <?php if ($is_universal): ?>
                                                        <span class="success-badge flex items-center">
                                                            <i class="fas fa-star fa-icon-sm"></i>Universal
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="flex flex-col sm:flex-row flex-wrap gap-2 text-sm text-gray-500 mt-1">
                                                    <span class="flex items-center">
                                                        <i class="fas fa-map-marker-alt fa-icon-sm"></i> <span class="truncate-mobile"><?php echo htmlspecialchars($donor['location']); ?></span>
                                                    </span>
                                                    <span class="flex items-center">
                                                        <i class="fas fa-phone fa-icon-sm"></i> <span class="truncate-mobile"><?php echo htmlspecialchars($donor['contact']); ?></span>
                                                    </span>
                                                    <span class="flex items-center">
                                                        <i class="far fa-edit fa-icon-sm"></i> <?php echo date('j M, Y | g:i:s', strtotime($donor['date'])); ?>
                                                    </span>
                                                    <?php if (isset($donor['last_donation'])): // NEW: Display Last Donation Date ?>
                                                        <span class="flex items-center">
                                                            <i class="fa-solid fa-droplet fa-icon-sm"></i> <span>Last Donated: <?php echo date('j M, Y', strtotime($donor['last_donation'])); ?></span>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="flex items-center space-x-2 self-end sm:self-auto">
                                            <a href="?action=edit&id=<?php echo htmlspecialchars($donor['id']); ?>"
                                               class="action-btn edit-btn text-blue-600 mobile-touch-friendly"
                                               title="Edit Donor">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="?action=delete&id=<?php echo htmlspecialchars($donor['id']); ?>"
                                               class="action-btn delete-btn text-red-600 mobile-touch-friendly"
                                               title="Delete Donor"
                                               onclick="return confirm('Are you sure you want to delete <?php echo addslashes($donor['name']); ?>?');">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const interactiveEls = document.querySelectorAll('input, select, button, a');
            interactiveEls.forEach(el => {
                el.classList.add('mobile-touch-friendly');
            });
            
            document.addEventListener('touchstart', function(event) {
                if (event.target.tagName === 'INPUT' || event.target.tagName === 'SELECT' || event.target.tagName === 'TEXTAREA') {
                    event.target.style.fontSize = '16px';
                }
            }, false);
        });
    </script>
</body>
</html>