Authentication functions (
includes/auth.php
):
<?php
session_start();
require_once 'db.php';

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Check if user is an admin
function isAdmin() {
    return isLoggedIn() && $_SESSION['user_role'] === 'admin';
}

// Authenticate user
function login($username, $password) {
    $sql = "SELECT id, username, password, name, role FROM users WHERE username = ?";
    $user = fetchOne($sql, [$username]);
    
    if ($user && password_verify($password, $user['password'])) {
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];
        
        return true;
    }
    
    return false;
}

// Log out user
function logout() {
    // Unset all session variables
    $_SESSION = [];
    
    // Destroy the session
    session_destroy();
}

// Redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: " . SITE_URL . "/login.php");
        exit;
    }
}

// Redirect if not admin
function requireAdmin() {
    requireLogin();
    
    if (!isAdmin()) {
        header("Location: " . SITE_URL . "/dashboard.php");
        exit;
    }
}

// Record recently viewed guide
function recordRecentlyViewed($guideId) {
    if (!isLoggedIn()) {
        return;
    }
    
    $userId = $_SESSION['user_id'];
    
    // Delete existing record if exists
    $sql = "DELETE FROM recently_viewed WHERE user_id = ? AND guide_id = ?";
    delete($sql, [$userId, $guideId]);
    
    // Insert new record
    $sql = "INSERT INTO recently_viewed (user_id, guide_id) VALUES (?, ?)";
    insert($sql, [$userId, $guideId]);
    
    // Keep only the 10 most recent views
    $sql = "DELETE FROM recently_viewed 
            WHERE user_id = ? 
            AND id NOT IN (
                SELECT id FROM (
                    SELECT id FROM recently_viewed 
                    WHERE user_id = ? 
                    ORDER BY viewed_at DESC 
                    LIMIT 10
                ) AS recent
            )";
    delete($sql, [$userId, $userId]);
}

// Get recently viewed guides
function getRecentlyViewed() {
    if (!isLoggedIn()) {
        return [];
    }
    
    $userId = $_SESSION['user_id'];
    
    $sql = "SELECT g.id, g.title, g.description, c.name as category_name, rv.viewed_at
            FROM recently_viewed rv
            JOIN guides g ON rv.guide_id = g.id
            LEFT JOIN categories c ON g.category_id = c.id
            WHERE rv.user_id = ?
            ORDER BY rv.viewed_at DESC
            LIMIT 5";
    
    return fetchAll($sql, [$userId]);
}
?>
