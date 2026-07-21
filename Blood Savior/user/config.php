<?php
// config.php - Configuration file for blood groups and locations

// --- CSRF Token Functions ---
// These functions are required by index.php for security.
// Ensure session_start() has been called before using these functions if index.php did not call it already.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Generates a CSRF token and stores it in the session.
 * @return string The generated CSRF token.
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        // Generate a cryptographically secure token
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validates the submitted CSRF token against the session token.
 * Consumes the token upon successful validation to prevent replay attacks.
 * @param string $token The token submitted via the form.
 * @return bool True if the token is valid, false otherwise.
 */
function validateCSRFToken($token) {
    if (isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token)) {
        // Token is valid. Consume it to prevent replay attacks.
        unset($_SESSION['csrf_token']);
        return true;
    }
    return false;
}
// -----------------------------


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