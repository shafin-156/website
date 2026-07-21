<?php
// Configuration File - Keep sensitive data separate
define('SITE_NAME', 'Blood Savior');
define('ADMIN_EMAIL', 'admin@bloodsavior.com');
define('SESSION_TIMEOUT', 600); // 10 minutes in seconds

// Database/File Configuration - Note: config.php is now inside protected_data
define('DATA_DIR', __DIR__ . '/');
define('DONORS_FILE', DATA_DIR . 'donors.json');
define('LOG_FILE', DATA_DIR . 'security.log');

// Security Configuration
define('MAX_LOGIN_ATTEMPTS', 3);
define('LOCKOUT_TIME', 3600); // 60 minutes in seconds

// Load admin password from separate JSON file
$admin_users_file = DATA_DIR . 'admin_users.json';
$admin_password_hash = '';
if (file_exists($admin_users_file)) {
    $admin_users = json_decode(file_get_contents($admin_users_file), true);
    if (isset($admin_users['admin']['password_hash'])) {
        $admin_password_hash = $admin_users['admin']['password_hash'];
    }
}

// Blood Groups
$blood_groups = ["A+", "A-", "AB+", "AB-", "B+", "B-", "O+", "O-"];

// Locations/Addresses (Gazipur areas)
$locations = [
    "Bason",
    "Bhabanipur",
    "Board Bazar",
    "Chandana Chowrasta",
    "Gazipur Sadar",
    "Joydebpur",
    "Kaliganj",
    "Kaliakair",
    "Kapasia",
    "Kashimpur",
    "Konabari",
    "Mouchak",
    "National University",
    "Pubail",
    "Rajendrapur",
    "Sreepur",
    "Tongi",
    "Zirani"
];

// CSRF Token Generation
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Validate CSRF Token
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Input Sanitization
function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    
    $input = trim($input);
    $input = stripslashes($input);
    $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    return $input;
}

// Log Security Events
function logSecurityEvent($event, $user = 'guest') {
    $log = date('[Y-m-d H:i:s]') . " | User: $user | Event: $event | IP: " . $_SERVER['REMOTE_ADDR'] . PHP_EOL;
    @file_put_contents(LOG_FILE, $log, FILE_APPEND | LOCK_EX);
}

// Rate Limiting
$login_attempts_file = DATA_DIR . 'login_attempts.json';

function checkRateLimit($ip) {
    global $login_attempts_file;
    
    if (!file_exists($login_attempts_file)) {
        return true;
    }
    
    // Load all attempts (flat array of timestamps)
    $attempts = json_decode(file_get_contents($login_attempts_file), true) ?: [];
    
    // Handle legacy format (if file exists from previous version) or ensure it is an array
    if (!is_array($attempts)) {
        $attempts = [];
    }

    $current_time = time();
    
    // Remove expired attempts
    $active_attempts = array_filter($attempts, function($timestamp) use ($current_time) {
        // Ensure timestamp is numeric before calculation
        return is_numeric($timestamp) && ($current_time - $timestamp < LOCKOUT_TIME);
    });
    
    // GLOBAL CHECK: If total failed attempts >= MAX, block everyone
    if (count($active_attempts) >= MAX_LOGIN_ATTEMPTS) {
        return false;
    }
    
    return true;
}

function logFailedAttempt($ip) {
    global $login_attempts_file;
    
    $attempts = file_exists($login_attempts_file) ? 
                json_decode(file_get_contents($login_attempts_file), true) : [];
    
    // Ensure we are working with an array
    if (!is_array($attempts)) {
        $attempts = [];
    }

    // Add current failure (GLOBAL list, we do not key by IP anymore)
    $attempts[] = time();
    
    // Keep only recent attempts
    $current_time = time();
    $attempts = array_filter($attempts, function($timestamp) use ($current_time) {
        return is_numeric($timestamp) && ($current_time - $timestamp < LOCKOUT_TIME);
    });
    
    // Re-index array
    $attempts = array_values($attempts);
    
    @file_put_contents($login_attempts_file, json_encode($attempts, JSON_PRETTY_PRINT));
}

// Load donors data
function load_donors() {
    if (file_exists(DONORS_FILE)) {
        $json_data = @file_get_contents(DONORS_FILE);
        return $json_data ? json_decode($json_data, true) ?: [] : [];
    }
    return [];
}

// Save donors data
function save_donors($donors) {
    // Sort donors by newest first
    usort($donors, function($a, $b) {
        return strtotime($b['date']) <=> strtotime($a['date']);
    });
    
    return @file_put_contents(DONORS_FILE, json_encode($donors, JSON_PRETTY_PRINT));
}

// Find donor by ID
function find_donor_by_id($id) {
    $donors = load_donors();
    foreach ($donors as $donor) {
        if ($donor['id'] === $id) {
            return $donor;
        }
    }
    return null;
}

// Update Admin Last Login Time
function updateAdminLoginTime($username) {
    $file_path = DATA_DIR . 'admin_users.json';
    
    if (file_exists($file_path)) {
        // Decode existing data
        $users = json_decode(file_get_contents($file_path), true);
        
        // Check if the specific admin user exists
        if (isset($users[$username])) {
            // Update the last_login field with current timestamp
            $users[$username]['last_login'] = date('Y-m-d H:i:s');
            
            // Save data back to the file
            @file_put_contents($file_path, json_encode($users, JSON_PRETTY_PRINT));
        }
    }
}
?>