<?php
/**
 * Authentication and Authorization Check
 * This file provides centralized security functions for the E-Commerce application
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if user is logged in
 * @return bool True if logged in, false otherwise
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Require user to be logged in or redirect to login page
 */
function require_login() {
    if (!is_logged_in()) {
        $base_path = '';
        $current_dir = dirname($_SERVER['PHP_SELF']);
        if (strpos($current_dir, '/Ecom') !== false) {
            $base_path = '/Ecom';
        }
        header("Location: " . $base_path . "/auth/login.php");
        exit();
    }
}

/**
 * Check if user has specific role
 * @param string $role Role to check (seller or buyer)
 * @return bool True if user has role, false otherwise
 */
function has_role($role) {
    return is_logged_in() && $_SESSION['role'] === $role;
}

/**
 * Require user to have specific role or redirect
 * @param string $role Role required (seller or buyer)
 * @param string $redirect_url URL to redirect if role check fails
 */
function require_role($role, $redirect_url = null) {
    require_login();
    
    if (!has_role($role)) {
        if ($redirect_url === null) {
            $base_path = '';
            $current_dir = dirname($_SERVER['PHP_SELF']);
            if (strpos($current_dir, '/Ecom') !== false) {
                $base_path = '/Ecom';
            }
            $redirect_url = has_role('seller') ? $base_path . "/seller/dashboard.php" : $base_path . "/buyer/dashboard.php";
        }
        header("Location: " . $redirect_url);
        exit();
    }
}

/**
 * Get base URL for the application
 * @return string Base URL
 */
function get_base_url() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $script_name = $_SERVER['SCRIPT_NAME'];
    $base_dir = dirname(dirname($script_name));
    $base_url = $protocol . '://' . $host . $base_dir;
    
    // Remove trailing slash if present
    return rtrim($base_url, '/');
}

/**
 * Sanitize input data
 * @param string $data Data to sanitize
 * @return string Sanitized data
 */
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Validate that a user owns a product
 * @param int $product_id Product ID to check
 * @param int $user_id User ID to verify ownership
 * @param mysqli $conn Database connection
 * @return bool True if user owns product, false otherwise
 */
function validate_product_ownership($product_id, $user_id, $conn) {
    $sql = "SELECT id FROM products WHERE id = ? AND seller_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $product_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
}

/**
 * Generate CSRF token
 * @return string CSRF token
 */
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 * @param string $token Token to verify
 * @return bool True if token is valid, false otherwise
 */
function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Add CSRF token to form
 * @return string HTML input field with CSRF token
 */
function csrf_token_field() {
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

/**
 * Check if CSRF token is valid in POST request
 * Exits with error if token is invalid
 */
function check_csrf_token() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
            http_response_code(403);
            die('CSRF token validation failed');
        }
    }
}

