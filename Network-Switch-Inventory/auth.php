<?php
/**
 * Authentication and Session Management
 * Network Switch Inventory Management System
 */

require_once 'config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Authenticate user with username and password
 */
function authenticateUser($username, $password)
{
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username AND is_active = true");
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Update last login
            $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = :id");
            $updateStmt->execute([':id' => $user['id']]);

            // Create session
            createUserSession($user);
            logAudit($pdo, $user['id'], 'LOGIN', 'USER', $user['id'], "User logged in: " . $user['username']);
            return ['success' => true, 'user' => $user];
        }

        return ['success' => false, 'message' => 'Invalid username or password'];
    } catch (Exception $e) {
        error_log("Authentication Error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Authentication failed'];
    }
}

/**
 * Create user session
 */
function createUserSession($user)
{
    // Generate session token
    $sessionToken = bin2hex(random_bytes(32));

    // Store session in database
    try {
        $pdo = getDBConnection();
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

        $stmt = $pdo->prepare("INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent, expires_at) 
                               VALUES (:user_id, :session_token, :ip_address, :user_agent, :expires_at)");
        $stmt->execute([
            ':user_id' => $user['id'],
            ':session_token' => $sessionToken,
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            ':expires_at' => $expiresAt
        ]);
    } catch (Exception $e) {
        error_log("Session Creation Error: " . $e->getMessage());
    }

    // Store in PHP session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['session_token'] = $sessionToken;
    $_SESSION['login_time'] = time();
}

/**
 * Check if user is authenticated
 */
function isAuthenticated()
{
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_token'])) {
        return false;
    }

    // Validate session token in database
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM user_sessions 
                               WHERE session_token = :token 
                               AND user_id = :user_id 
                               AND expires_at > NOW()");
        $stmt->execute([
            ':token' => $_SESSION['session_token'],
            ':user_id' => $_SESSION['user_id']
        ]);

        return $stmt->fetch() !== false;
    } catch (Exception $e) {
        error_log("Session Validation Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Require authentication - redirect to login if not authenticated
 */
function requireAuth()
{
    if (!isAuthenticated()) {
        header('Location: login');
        exit;
    }
}

/**
 * Check if user has specific role
 */
function hasRole($role)
{
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

/**
 * Check if user is admin
 */
function isAdmin()
{
    return hasRole('Admin');
}

/**
 * Require admin role - return 403 if not admin
 */
function requireAdmin()
{
    if (!isAdmin()) {
        http_response_code(403);
        if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
            echo json_encode(['success' => false, 'message' => 'Admin access required']);
        } else {
            echo "403 Forbidden - Admin access required";
        }
        exit;
    }
}

/**
 * Get current user information
 */
function getCurrentUser()
{
    if (!isAuthenticated()) {
        return null;
    }

    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'full_name' => $_SESSION['full_name'],
        'email' => $_SESSION['email'],
        'role' => $_SESSION['role']
    ];
}

/**
 * Logout user
 */
function logout()
{
    // Delete session from database
    if (isset($_SESSION['session_token'])) {
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE session_token = :token");
            $stmt->execute([':token' => $_SESSION['session_token']]);
        } catch (Exception $e) {
            error_log("Logout Error: " . $e->getMessage());
        }
    }

    if (isset($_SESSION['user_id'])) {
        try {
            $pdo = getDBConnection();
            logAudit($pdo, $_SESSION['user_id'], 'LOGOUT', 'USER', $_SESSION['user_id'], "User logged out: " . $_SESSION['username']);
        } catch (Exception $e) {
        }
    }

    // Clear PHP session
    $_SESSION = array();

    // Destroy session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }

    // Destroy session
    session_destroy();
}

/**
 * Clean expired sessions (should be called periodically)
 */
function cleanExpiredSessions()
{
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE expires_at < NOW()");
        $stmt->execute();
    } catch (Exception $e) {
        error_log("Clean Sessions Error: " . $e->getMessage());
    }
}
