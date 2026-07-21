<?php
session_start();
// Fix path to config.php (go up one level)
require_once '../config.php'; // Path remains as previously updated
// Note: This relies on ../config.php defining $blood_groups, $locations, 
// generateCSRFToken(), and validateCSRFToken().

// Define the JSON file paths
$registrationFile = 'registrationRequest.json'; // Contains donor details
$authFile = 'authDataRequest.json'; // Contains contact number and password hash

// Function to load registrations from JSON file
function load_registrations($file) {
    if (file_exists($file)) {
        $json = @file_get_contents($file); // Use @ to suppress file access warnings
        // Use true for associative array, or default to empty array
        return json_decode($json, true) ?: [];
    }
    return [];
}

// Function to save registrations to JSON file
function save_registrations($file, $data) {
    // Save as array of objects
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return file_put_contents($file, $json);
}

// Function to load authentication data from JSON file
function load_auth_data($file) {
    if (file_exists($file)) {
        $json = @file_get_contents($file); // Use @to suppress file access warnings
        // Save as key-value pairs (contact => password hash)
        return json_decode($json, true) ?: [];
    }
    return [];
}

// Function to save authentication data to JSON file
function save_auth_data($file, $data) {
    // Save as key-value pairs (contact => password hash)
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return file_put_contents($file, $json);
}

// Function to generate unique ID in format: ddmmyyyy + random 5 chars
function generateUniqueID() {
    $date_part = date('dmY'); // Current date in ddmmyyyy format
    $random_chars = substr(str_shuffle('abcdefghijklmnopqrstuvwxyz0123456789'), 0, 5); // 5 random chars
    return $date_part . $random_chars;
}

// Function to clean contact number (remove all non-digit characters)
function cleanContactNumber($contact) {
    // Removes spaces, dashes, etc. and ensures it's only digits
    $cleaned = preg_replace('/\D+/', '', $contact);
    
    // Explicitly cast to string to preserve leading zeros
    return (string) $cleaned; 
}

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    // Validate CSRF token (requires function from config.php)
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = 'Invalid CSRF token.';
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }

    // Input validation (simplified, add more robust checks as needed)
    if (empty($_POST['name']) || empty($_POST['contact']) || empty($_POST['location']) || empty($_POST['group']) || empty($_POST['password']) || empty($_POST['confirm_password']) || $_POST['password'] !== $_POST['confirm_password']) {
        $_SESSION['error'] = 'Registration failed. Please check all fields and ensure passwords match.';
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }

    // 1. Clean the contact number (FIX APPLIED HERE)
    $contact = cleanContactNumber($_POST['contact']);
    // $contact is already a string from the function, but keeping the cast for clarity/safety.
    $contact = (string) $contact; 
    $password = $_POST['password'];

    // 2. Load existing data
    $authData = load_auth_data($authFile);

    // 3. Check for duplicate contact number in authDataRequest.json
    if (isset($authData[$contact])) {
        $_SESSION['error'] = " ✘ Registration failed. The contact number ($contact) is already registered for authentication. ";
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }

    // --- Process and Save Data ---
    $registrations = load_registrations($registrationFile);
    $uniqueID = generateUniqueID();
    
    // Hash the password for authDataRequest.json
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // Prepare data for registrationRequest.json (excluding password)
    $newRegistration = [
        'id' => $uniqueID,
        'name' => $_POST['name'],
        'contact' => $contact,
        'location' => $_POST['location'],
        'group' => $_POST['group'],
        'last_donation' => $_POST['last_donation'] ?: null, // Allow empty/null
        'date' => date('Y-m-d H:i:s'),
        'added_by' => 'public_registered',
        'ip' => $_SERVER['REMOTE_ADDR'],
    ];

    // Save registration data
    $registrations[] = $newRegistration;
    $successReg = save_registrations($registrationFile, $registrations);

    // Use direct array assignment to ensure the contact number
    // is stored as an associative string key.
    $authData[$contact] = $passwordHash;
    
    // Save auth data
    $successAuth = save_auth_data($authFile, $authData);

    if ($successReg && $successAuth) {
        $_SESSION['success'] = " ✓ Registration successful. Please wait 24 hours for ADMIN APPROVAL to log in. ";
    } else {
        // If file saving fails, revert if possible, or log error
        $_SESSION['error'] = " ✘ Registration failed !!";
    }
    
    // Redirect to clear POST data and show message
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

// Generate CSRF token for the form (requires function from config.php)
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../../assets/images/favicon.ico">
    <title>Donor Registration - Save a Life</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../../assets/fontawesome7/css/all.min.css"/>
    <style>
        /* Define custom theme colors/shadows for a cleaner look */
        :root {
            --primary-red: #cc0000; /* Deep Blood Red */
            --primary-red-hover: #cc0000;
            --secondary-gray: #f9fafb; /* Lighter background */
            --border-color: #111111;
        }

        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap');
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--secondary-gray);
        }
        
        /* === Header Styles === */
        .app-header {
            background-color: #ffffff;
            border-bottom: 1px solid #f3f4f6;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
        }
        .nav-link {
            padding: 1rem 1.25rem;
            display: flex;
            align-items: center;
            font-weight: 600;
            color: #4b5563; /* Default text color */
            transition: color 0.2s, background-color 0.2s;
            text-decoration: none; /* Ensure no default underline */
        }
        .nav-link:hover {
            color: var(--primary-red);
            background-color: #fef2f2;
        }
        /* Active link styling (for the current page, which is effectively Home/Register) */
        .nav-link.active {
            color: var(--primary-red);
            border-bottom: 3px solid var(--primary-red);
            margin-bottom: -1px; /* Offset border-bottom */
        }
        .logo-box {
            display: flex;
            align-items: center;
            padding: 1rem 1.5rem;
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--primary-red);
        }

        /* Modern form card design */
        .form-container {
            max-width: 480px;
            margin: 4rem auto;
            background-color: #ffffff;
            border-radius: 16px;
            /* Enhanced Shadow */
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            padding: 3rem;
            border-top: 5px solid var(--primary-red); /* Thematic line */
        }

        .input-group {
            position: relative;
            margin-bottom: 1.5rem; /* Increased spacing */
        }
        
        .input-field {
            width: 100%;
            padding: 0.75rem 0.75rem 0.75rem 3rem; /* Padding for icon */
            border: 1px solid var(--border-color);
            border-radius: 8px;
            box-sizing: border-box;
            transition: all 0.2s;
            background-color: #fff;
        }
        
        .input-field:focus {
            border-color: var(--primary-red);
            box-shadow: 0 0 0 3px rgba(204, 0, 0, 0.15); /* Softer, thematic focus */
            outline: none;
        }

        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #000000; /* Black color for icons */
            pointer-events: none; /* Make icon unclickable */
            z-index: 10;
        }
        
        /* FIX: Ensure vertical alignment is stable when input type changes to date */
        input[type="date"].input-field {
            line-height: normal; 
            padding-top: 0.75rem; 
            padding-bottom: 0.75rem; 
        }

        /* Styling for Select inputs to hide default arrow and align with icon */
        .select-field {
            padding-right: 2.5rem; /* Space for the custom dropdown arrow */
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2020%2020%22%20fill%3D%22none%22%20stroke%3D%22%239ca3af%22%20stroke-width%3D%221.5%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C%2Fpolyline%3E%3C%2Fsvg%3E");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 16px 16px;
        }

        .register-btn {
            background-color: var(--primary-red);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            transition: background-color 0.2s, transform 0.1s;
            width: 100%;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .register-btn:hover {
            background-color: var(--primary-red-hover);
            box-shadow: 0 4px 6px -1px rgba(204, 0, 0, 0.4), 0 2px 4px -2px rgba(204, 0, 0, 0.4);
        }
        
        .register-btn:active {
            transform: scale(0.99);
        }
        
        /* Contact field specific styling for prefix */
        .contact-group .input-field {
            padding-left: 5.5rem; /* Adjusted padding to accommodate +88 and icon */
        }
        .contact-group .input-icon {
            left: 1rem; /* Phone icon position */
        }
        .contact-group .prefix-display {
            position: absolute;
            left: 3rem; /* Positioned after the icon */
            top: 50%;
            transform: translateY(-50%);
            color: #000000; /* Black for prefix */
            font-size: 1rem; /* text-sm */
            font-weight: 500; /* font-medium */
            border-right: 1px solid var(--border-color);
            padding-right: 0.5rem;
            line-height: 1;
            z-index: 10;
            pointer-events: none;
        }


        /* Date input specific styles */
        .date-placeholder {
            color: #a0aec0 !important; /* Lighter color for placeholder effect */
        }
        
        /* Ensure date input looks consistent after selection */
        input[type="date"] {
            color: #1f2937;
        }
        
        /* Message box styles */
        #messageContainer div {
            border-radius: 8px;
        }
    </style>
</head>
<body>

    <header class="app-header sticky top-0 z-40">
        <div class="container mx-auto flex items-center justify-between">
            <a href="../../" class="logo-box">
                <i class="fa-solid fa-droplet mr-2 text-2xl"></i>
                Blood Savior
            </a>
            
            <nav class="flex h-full">
                <a href="../../" class="nav-link active">
                    <i class="fas fa-home mr-2"></i> Home
                </a>
                <a href="../login/" class="nav-link">
                    <i class="fas fa-sign-in-alt mr-2"></i> Login
                </a>
            </nav>
        </div>
    </header>
    <div id="messageContainer" class="fixed top-20 left-0 w-full p-4 text-center z-50 transition-opacity duration-500 hidden opacity-100">
        <div id="successMessage" class="bg-green-600 text-white font-semibold py-3 px-4 shadow-xl mx-auto max-w-sm hidden"></div>
        <div id="errorMessage" class="bg-red-600 text-white font-semibold py-3 px-4 shadow-xl mx-auto max-w-sm hidden"></div>
    </div>

    <div class="form-container">
        <div class="text-center mb-8">
            <h2 class="text-3xl font-extrabold text-red-700">Become a Donor</h2>
            <p class="text-sm text-gray-500 mt-1">Join our mission to save lives. It only takes a minute.</p>
        </div>
        
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
            <input type="hidden" name="action" value="register">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            
            <div class="input-group">
                <i class="fas fa-user input-icon"></i>
                <input type="text" id="regName" name="name" class="input-field" placeholder="Full Name" required>
            </div>
            
            <div class="input-group contact-group">
                <i class="fas fa-phone input-icon"></i>
                <span class="prefix-display">+88 </span>
                <input type="tel" id="regContact" name="contact" class="input-field" placeholder="01XXXXXXXX" required minlength="11" maxlength="11" title="Enter 11 digits without the +88 prefix.">
            </div>

            <div class="input-group">
                <i class="fas fa-map-marker-alt input-icon"></i>
                <select id="regLocation" name="location" class="input-field select-field text-gray-500" required onchange="this.classList.remove('text-gray-500')">
                    <option value="" disabled selected class="text-gray-400">Select Your Location</option>
                    <?php if (isset($locations) && is_array($locations)): ?>
                        <?php foreach ($locations as $location): ?>
                            <option value="<?php echo htmlspecialchars($location); ?>" class="text-gray-800"><?php echo htmlspecialchars($location); ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            
            <div class="input-group">
                <i class="fa-solid fa-droplet input-icon"></i>
                <select id="regGroup" name="group" class="input-field select-field text-gray-500" required onchange="this.classList.remove('text-gray-500')">
                    <option value="" disabled selected class="text-gray-400">Select Blood Group</option>
                    <?php if (isset($blood_groups) && is_array($blood_groups)): ?>
                        <?php foreach ($blood_groups as $group): ?>
                            <option value="<?php echo htmlspecialchars($group); ?>" class="text-gray-800"><?php echo htmlspecialchars($group); ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            
            <div class="input-group">
                <i class="fas fa-calendar-alt input-icon"></i>
                <input type="text" id="regLastDonation" name="last_donation" class="input-field date-placeholder" placeholder="Last Donation Date (Optional)">
                <p class="text-xs text-gray-500 mt-1 pl-12">Leave blank if you've never donated.</p>
            </div>
            
            <div class="input-group">
                <i class="fas fa-key input-icon"></i>
                <input type="password" id="regPassword" name="password" class="input-field" placeholder="Set Password (min 6 characters)" required minlength="6">
            </div>

            <div class="input-group">
                <i class="fas fa-key input-icon"></i>
                <input type="password" id="regConfirmPassword" name="confirm_password" class="input-field" placeholder="Confirm Password" required minlength="6">
            </div>

            <button type="submit" class="register-btn mt-4">Register Me</button>
            
            <p class="text-center text-sm text-gray-500 mt-4">
                Already registered? <a href="../login/" class="text-red-600 font-semibold hover:text-red-700 transition">Log In Here</a>
            </p>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // --- Input Formatting and Validation ---
            
            // Format contact number input
            const contactInput = document.getElementById('regContact');
            contactInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, ''); // Remove non-digits
                
                // Keep only 11 digits
                if (value.length > 11) {
                    value = value.substring(0, 11);
                }
                
                e.target.value = value;
            });
            
            // Set max date for last donation field to today
            const lastDonationInput = document.getElementById('regLastDonation');
            const today = new Date().toISOString().split('T')[0];
            lastDonationInput.max = today;
            
            // Add placeholder behavior for date input (only if it's not a pre-filled value)
            lastDonationInput.addEventListener('focus', function() {
                this.type = 'date';
                this.classList.remove('date-placeholder');
            });
            
            lastDonationInput.addEventListener('blur', function() {
                if (!this.value) {
                    this.type = 'text';
                    this.classList.add('date-placeholder');
                }
            });

            // Initial state for date input (if empty)
            if (!lastDonationInput.value) {
                lastDonationInput.type = 'text';
                lastDonationInput.classList.add('date-placeholder');
            }
            
            // Initial state for select inputs to show placeholder color
            const regLocation = document.getElementById('regLocation');
            const regGroup = document.getElementById('regGroup');
            
            // Check if values are selected on load (e.g., after failed submission)
            if (regLocation.value === "") {
                regLocation.classList.add('text-gray-500');
            }
            if (regGroup.value === "") {
                regGroup.classList.add('text-gray-500');
            }

            // Auto-focus first input
            document.getElementById('regName').focus();

            // --- PHP Session Message Handling (Success/Error) ---
            
            const successMessageDiv = document.getElementById('successMessage');
            const errorMessageDiv = document.getElementById('errorMessage');
            const messageContainer = document.getElementById('messageContainer');

            // Function to show and hide message
            function showMessage(element, hideAfter = 0) {
                // Adjust position to account for fixed header
                messageContainer.classList.remove('hidden');
                element.classList.remove('hidden');
                messageContainer.style.opacity = '1';

                if (hideAfter > 0) {
                    setTimeout(() => {
                        messageContainer.style.opacity = '0';
                        setTimeout(() => {
                            messageContainer.classList.add('hidden');
                            element.classList.add('hidden');
                        }, 500); 
                    }, hideAfter);
                } else {
                    // Make error message dismissable
                    element.addEventListener('click', function() {
                        messageContainer.style.opacity = '0';
                        setTimeout(() => {
                            messageContainer.classList.add('hidden');
                            element.classList.add('hidden');
                        }, 500);
                    }, { once: true });
                }
            }
            
            // Handle PHP success message
            <?php if (isset($_SESSION['success'])): ?>
                successMessageDiv.textContent = "<?php echo htmlspecialchars($_SESSION['success']); ?>";
                showMessage(successMessageDiv, 20000); // 20 seconds display time
                <?php unset($_SESSION['success']); endif; ?>

            // Handle PHP error message
            <?php if (isset($_SESSION['error'])): ?>
                errorMessageDiv.textContent = "<?php echo htmlspecialchars($_SESSION['error']); ?>";
                showMessage(errorMessageDiv); // Don't auto-hide errors
                <?php unset($_SESSION['error']); endif; ?>
        });
    </script>
</body>
</html>