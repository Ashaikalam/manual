<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');     // Replace with your MySQL username
define('DB_PASS', '');         // Replace with your MySQL password
define('DB_NAME', 'sap_knowledge_hub');

// Site configuration
define('SITE_URL', 'http://localhost/sap-knowledge-hub'); // Replace with your site URL
define('UPLOAD_DIR', $_SERVER['DOCUMENT_ROOT'] . '/sap-knowledge-hub/uploads/');
define('UPLOAD_URL', SITE_URL . '/uploads/');
?>

Database connection file (
includes/db.php
):
<?php
require_once 'config.php';

// Create database connection
function getDbConnection() {
    static $conn;
    
    if ($conn === null) {
        $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if (!$conn) {
            die("Connection failed: " . mysqli_connect_error());
        }
        
        mysqli_set_charset($conn, "utf8mb4");
    }
    
    return $conn;
}

// Execute query and return result
function executeQuery($sql, $params = []) {
    $conn = getDbConnection();
    $stmt = mysqli_prepare($conn, $sql);
    
    if ($stmt === false) {
        die("Error preparing statement: " . mysqli_error($conn));
    }
    
    if (!empty($params)) {
        $types = str_repeat('s', count($params)); // Assume all strings for simplicity
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    return $result;
}

// Fetch all rows from a query
function fetchAll($sql, $params = []) {
    $result = executeQuery($sql, $params);
    $rows = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    
    return $rows;
}

// Fetch a single row
function fetchOne($sql, $params = []) {
    $result = executeQuery($sql, $params);
    return mysqli_fetch_assoc($result);
}

// Insert data and return the last inserted ID
function insert($sql, $params = []) {
    $conn = getDbConnection();
    $stmt = mysqli_prepare($conn, $sql);
    
    if ($stmt === false) {
        die("Error preparing statement: " . mysqli_error($conn));
    }
    
    if (!empty($params)) {
        $types = str_repeat('s', count($params));
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    
    mysqli_stmt_execute($stmt);
    return mysqli_insert_id($conn);
}

// Update data and return the number of affected rows
function update($sql, $params = []) {
    $conn = getDbConnection();
    $stmt = mysqli_prepare($conn, $sql);
    
    if ($stmt === false) {
        die("Error preparing statement: " . mysqli_error($conn));
    }
    
    if (!empty($params)) {
        $types = str_repeat('s', count($params));
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    
    mysqli_stmt_execute($stmt);
    return mysqli_affected_rows($conn);
}

// Delete data and return the number of affected rows
function delete($sql, $params = []) {
    return update($sql, $params);
}
?>

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

Login page (
login.php
):
<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';

$error = '';

// Check if user is already logged in
if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit;
}

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $loginType = $_POST['login_type'] ?? 'user';
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        if (login($username, $password)) {
            // Check if admin login was requested but user is not admin
            if ($loginType === 'admin' && !isAdmin()) {
                logout();
                $error = 'You do not have admin privileges.';
            } else {
                // Redirect based on role
                if (isAdmin()) {
                    header("Location: admin/index.php");
                } else {
                    header("Location: dashboard.php");
                }
                exit;
            }
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SAP Knowledge Hub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <?php include 'includes/header.php'; ?>

    <!-- Main Content -->
    <main class="py-16">
        <div class="container mx-auto px-4">
            <div class="max-w-md mx-auto bg-white rounded-lg shadow-sm overflow-hidden">
                <div class="p-6">
                    <div class="text-center mb-8">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-blue-600 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        <h2 class="text-2xl font-bold text-gray-800 mt-4">Login to SAP Knowledge Hub</h2>
                        <p class="text-gray-600 mt-2">Access your SAP guides and documentation</p>
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="mb-6">
                        <div class="flex border-b border-gray-300">
                            <button id="user-login-tab" class="w-1/2 py-2 text-center font-medium text-blue-600 border-b-2 border-blue-600" onclick="switchLoginTab('user')">
                                User Login
                            </button>
                            <button id="admin-login-tab" class="w-1/2 py-2 text-center font-medium text-gray-500 hover:text-gray-700" onclick="switchLoginTab('admin')">
                                Admin Login
                            </button>
                        </div>
                    </div>
                    
                    <form method="post" action="login.php" class="space-y-6">
                        <input type="hidden" id="login-type" name="login_type" value="user">
                        <div>
                            <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                            <input type="text" id="username" name="username" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                            <input type="password" id="password" name="password" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <input id="remember-me" name="remember-me" type="checkbox" class="h-4 w-4 text-blue-600 border-gray-300 rounded">
                                <label for="remember-me" class="ml-2 block text-sm text-gray-700">
                                    Remember me
                                </label>
                            </div>
                            <div class="text-sm">
                                <a href="#" class="text-blue-600 hover:text-blue-800">
                                    Forgot password?
                                </a>
                            </div>
                        </div>
                        <div>
                            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md transition-all">
                                Sign in
                            </button>
                        </div>
                    </form>
                    
                    <div class="mt-6 text-center text-sm">
                        <p class="text-gray-600">
                            Don't have an account? <a href="contact.php" class="text-blue-600 hover:text-blue-800 font-medium">Contact your administrator</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>

    <script>
        function switchLoginTab(tab) {
            const userTab = document.getElementById('user-login-tab');
            const adminTab = document.getElementById('admin-login-tab');
            const loginType = document.getElementById('login-type');
            
            if (tab === 'user') {
                userTab.classList.add('text-blue-600', 'border-blue-600');
                userTab.classList.remove('text-gray-500');
                adminTab.classList.remove('text-blue-600', 'border-blue-600');
                adminTab.classList.add('text-gray-500');
                loginType.value = 'user';
            } else {
                adminTab.classList.add('text-blue-600', 'border-blue-600');
                adminTab.classList.remove('text-gray-500');
                userTab.classList.remove('text-blue-600', 'border-blue-600');
                userTab.classList.add('text-gray-500');
                loginType.value = 'admin';
            }
        }
    </script>
</body>
</html>

User dashboard (
dashboard.php
):
<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Require user to be logged in
requireLogin();

// Get categories
$categories = fetchAll("SELECT * FROM categories ORDER BY name");

// Get guide ID from query string if present
$guideId = isset($_GET['guide']) ? (int)$_GET['guide'] : 0;

// If guide ID is provided, record as recently viewed
if ($guideId > 0) {
    recordRecentlyViewed($guideId);
}

// Get recently viewed guides
$recentlyViewed = getRecentlyViewed();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - SAP Knowledge Hub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        .step-item {
            border-left: 3px solid #e2e8f0;
            padding-left: 20px;
            position: relative;
        }
        .step-item:before {
            content: '';
            position: absolute;
            left: -9px;
            top: 0;
            width: 15px;
            height: 15px;
            border-radius: 50%;
            background-color: #3b82f6;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <?php include 'includes/header.php'; ?>

    <!-- Main Content -->
    <main class="py-8">
        <div class="container mx-auto px-4">
            <div class="flex flex-col md:flex-row gap-6">
                <!-- Sidebar -->
                <div class="w-full md:w-1/4">
                    <div class="bg-white rounded-lg shadow-sm p-4 mb-6">
                        <div class="flex items-center space-x-3 mb-6">
                            <div class="bg-blue-100 rounded-full w-12 h-12 flex items-center justify-center">
                                <i class="fas fa-user text-blue-600"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Welcome back,</p>
                                <h3 class="font-semibold text-gray-800"><?php echo htmlspecialchars($_SESSION['user_name']); ?></h3>
                            </div>
                        </div>
                        <div class="border-t border-gray-100 pt-4">
                            <h4 class="text-sm font-medium text-gray-500 mb-3">CATEGORIES</h4>
                            <ul class="space-y-2">
                                <li>
                                    <a href="dashboard.php" class="w-full text-left flex items-center text-gray-700 hover:text-blue-600 py-1">
                                        <i class="fas fa-layer-group mr-2"></i>
                                        All Guides
                                    </a>
                                </li>
                                <?php foreach ($categories as $category): ?>
                                <li>
                                    <a href="dashboard.php?category=<?php echo $category['id']; ?>" class="w-full text-left flex items-center text-gray-700 hover:text-blue-600 py-1">
                                        <span class="mr-2"><?php echo htmlspecialchars($category['icon']); ?></span>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </a>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow-sm p-4">
                        <h4 class="text-sm font-medium text-gray-500 mb-3">RECENTLY VIEWED</h4>
                        <ul class="space-y-2">
                            <?php if (empty($recentlyViewed)): ?>
                                <li class="text-gray-400 italic text-sm">No recently viewed guides</li>
                            <?php else: ?>
                                <?php foreach ($recentlyViewed as $guide): ?>
                                <li>
                                    <a href="dashboard.php?guide=<?php echo $guide['id']; ?>" class="w-full text-left text-gray-700 hover:text-blue-600 py-1 truncate block">
                                        <?php echo htmlspecialchars($guide['title']); ?>
                                    </a>
                                </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
                
                <!-- Main Content -->
                <div class="w-full md:w-3/4">
                    <?php if ($guideId > 0): ?>
                        <!-- Guide Detail View -->
                        <?php
                        $guide = fetchOne("SELECT g.*, c.name as category_name, c.icon as category_icon 
                                          FROM guides g 
                                          LEFT JOIN categories c ON g.category_id = c.id 
                                          WHERE g.id = ?", [$guideId]);
                        
                        if ($guide):
                            $steps = fetchAll("SELECT * FROM steps WHERE guide_id = ? ORDER BY position", [$guideId]);
                        ?>
                        <div class="bg-white rounded-lg shadow-sm p-6">
                            <div class="flex justify-between items-center mb-6">
                                <a href="dashboard.php<?php echo isset($_GET['category']) ? '?category=' . $_GET['category'] : ''; ?>" class="flex items-center text-blue-600 hover:text-blue-800">
                                    <i class="fas fa-arrow-left mr-1"></i>
                                    Back to Guides
                                </a>
                                <div>
                                    <span class="inline-block bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full">
                                        <?php echo htmlspecialchars($guide['category_name'] ?? 'Uncategorized'); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <h2 class="text-2xl font-bold text-gray-800 mb-2"><?php echo htmlspecialchars($guide['title']); ?></h2>
                            <p class="text-gray-600 mb-6"><?php echo htmlspecialchars($guide['description']); ?></p>
                            
                            <div class="space-y-8">
                                <?php foreach ($steps as $index => $step): ?>
                                <div class="step-item pb-8">
                                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Step <?php echo $index + 1; ?>: <?php echo htmlspecialchars($step['title']); ?></h3>
                                    <p class="text-gray-600"><?php echo nl2br(htmlspecialchars($step['content'])); ?></p>
                                    <?php if (!empty($step['image_path'])): ?>
                                    <div class="mt-4">
                                        <img src="<?php echo htmlspecialchars($step['image_path']); ?>" alt="Step <?php echo $index + 1; ?> screenshot" class="rounded-lg border border-gray-200 max-w-full">
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="bg-white rounded-lg shadow-sm p-6">
                            <div class="text-center py-8">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-gray-400 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <h3 class="text-xl font-medium text-gray-700 mb-2">Guide Not Found</h3>
                                <p class="text-gray-500">The guide you are looking for does not exist or has been removed.</p>
                                <a href="dashboard.php" class="mt-4 inline-block bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md transition-all">
                                    Back to All Guides
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <!-- Guide List View -->
                        <?php
                        $categoryId = isset($_GET['category']) ? (int)$_GET['category'] : 0;
                        $searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
                        
                        $params = [];
                        $sql = "SELECT g.*, c.name as category_name, c.icon as category_icon 
                                FROM guides g 
                                LEFT JOIN categories c ON g.category_id = c.id 
                                WHERE 1=1";
                        
                        if ($categoryId > 0) {
                            $sql .= " AND g.category_id = ?";
                            $params[] = $categoryId;
                            
                            $category = fetchOne("SELECT * FROM categories WHERE id = ?", [$categoryId]);
                            $categoryName = $category ? $category['name'] : 'Category';
                        } else {
                            $categoryName = 'All Guides';
                        }
                        
                        if (!empty($searchTerm)) {
                            $sql .= " AND (g.title LIKE ? OR g.description LIKE ?)";
                            $params[] = "%$searchTerm%";
                            $params[] = "%$searchTerm%";
                        }
                        
                        $sql .= " ORDER BY g.title";
                        $guides = fetchAll($sql, $params);
                        ?>
                        <div class="bg-white rounded-lg shadow-sm p-6">
                            <h2 class="text-xl font-semibold text-gray-800 mb-4"><?php echo htmlspecialchars($categoryName); ?></h2>
                            <form action="dashboard.php" method="get" class="mb-4">
                                <?php if ($categoryId > 0): ?>
                                <input type="hidden" name="category" value="<?php echo $categoryId; ?>">
                                <?php endif; ?>
                                <div class="flex">
                                    <input type="text" name="search" placeholder="Search guides..." class="flex-grow px-4 py-2 border border-gray-300 rounded-l-md focus:ring-blue-500 focus:border-blue-500" value="<?php echo htmlspecialchars($searchTerm); ?>">
                                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-r-md">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </form>
                            
                            <div class="space-y-4">
                                <?php if (empty($guides)): ?>
                                <p class="text-gray-500">No guides available in this category.</p>
                                <?php else: ?>
                                    <?php foreach ($guides as $guide): ?>
                                    <div class="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-md transition-all">
                                        <div class="flex justify-between items-start">
                                            <h3 class="text-lg font-semibold text-gray-800 mb-2"><?php echo htmlspecialchars($guide['title']); ?></h3>
                                            <span class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full">
                                                <?php echo htmlspecialchars($guide['category_name'] ?? 'Uncategorized'); ?>
                                            </span>
                                        </div>
                                        <p class="text-gray-600 mb-4"><?php echo htmlspecialchars($guide['description']); ?></p>
                                        <div class="flex justify-between items-center">
                                            <?php 
                                            $stepCount = fetchOne("SELECT COUNT(*) as count FROM steps WHERE guide_id = ?", [$guide['id']]);
                                            $count = $stepCount ? $stepCount['count'] : 0;
                                            ?>
                                            <span class="text-sm text-gray-500"><?php echo $count; ?> steps</span>
                                            <a href="dashboard.php?guide=<?php echo $guide['id']; ?><?php echo $categoryId > 0 ? '&category=' . $categoryId : ''; ?>" class="text-blue-600 hover:text-blue-800 font-medium inline-flex items-center">
                                                View Guide
                                                <i class="fas fa-chevron-right ml-1 text-xs"></i>
                                            </a>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>

    <!-- Toast Notification -->
    <div id="toast" class="fixed bottom-4 right-4 bg-gray-800 text-white px-6 py-3 rounded-lg shadow-lg transform translate-y-20 opacity-0 transition-all duration-300 flex items-center z-50">
        <span id="toast-message">Notification message</span>
    </div>

    <script>
        function showToast(message, duration = 3000) {
            const toast = document.getElementById('toast');
            document.getElementById('toast-message').textContent = message;
            
            toast.classList.remove('translate-y-20', 'opacity-0');
            
            setTimeout(() => {
                toast.classList.add('translate-y-20', 'opacity-0');
            }, duration);
        }
        
        <?php if (isset($_SESSION['flash_message'])): ?>
        showToast('<?php echo $_SESSION['flash_message']; ?>');
        <?php unset($_SESSION['flash_message']); ?>
        <?php endif; ?>
    </script>
</body>
</html>

Admin guide management (
admin/guides.php
):
<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

// Require admin privileges
requireAdmin();

// Get categories for dropdown
$categories = fetchAll("SELECT * FROM categories ORDER BY name");

// Handle guide deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $guideId = (int)$_GET['delete'];
    
    // Delete guide (steps will be deleted via ON DELETE CASCADE)
    delete("DELETE FROM guides WHERE id = ?", [$guideId]);
    
    $_SESSION['flash_message'] = "Guide deleted successfully.";
    header("Location: guides.php");
    exit;
}

// Get guides with category information
$categoryFilter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';

$params = [];
$sql = "SELECT g.*, c.name as category_name, c.icon as category_icon,
               (SELECT COUNT(*) FROM steps WHERE guide_id = g.id) as step_count
        FROM guides g 
        LEFT JOIN categories c ON g.category_id = c.id 
        WHERE 1=1";

if ($categoryFilter > 0) {
    $sql .= " AND g.category_id = ?";
    $params[] = $categoryFilter;
}

if (!empty($searchTerm)) {
    $sql .= " AND (g.title LIKE ? OR g.description LIKE ?)";
    $params[] = "%$searchTerm%";
    $params[] = "%$searchTerm%";
}

$sql .= " ORDER BY g.title";
$guides = fetchAll($sql, $params);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Guides - SAP Knowledge Hub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <?php include '../includes/admin-header.php'; ?>

    <!-- Main Content -->
    <main class="py-8">
        <div class="container mx-auto px-4">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-gray-800">Manage Guides</h1>
                <a href="guide-form.php" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md transition-all flex items-center">
                    <i class="fas fa-plus mr-2"></i>
                    Create New Guide
                </a>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm p-6">
                <form action="guides.php" method="get" class="mb-6 flex flex-col sm:flex-row gap-4">
                    <input type="text" name="search" placeholder="Search guides..." class="flex-grow px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500" value="<?php echo htmlspecialchars($searchTerm); ?>">
                    <select name="category" class="px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>" <?php echo $categoryFilter == $category['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md transition-all">
                        <i class="fas fa-search mr-2"></i>
                        Filter
                    </button>
                </form>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Steps</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($guides)): ?>
                            <tr>
                                <td colspan="4" class="px-6 py-4 text-center text-gray-500">No guides available. Create your first guide!</td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($guides as $guide): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($guide['title']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                            <?php echo htmlspecialchars($guide['category_name'] ?? 'Uncategorized'); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo $guide['step_count']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <a href="../dashboard.php?guide=<?php echo $guide['id']; ?>" class="text-green-600 hover:text-green-900 mr-3" target="_blank">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="guide-form.php?id=<?php echo $guide['id']; ?>" class="text-blue-600 hover:text-blue-900 mr-3">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="#" onclick="confirmDelete(<?php echo $guide['id']; ?>, '<?php echo addslashes($guide['title']); ?>')" class="text-red-600 hover:text-red-900">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <?php include '../includes/admin-footer.php'; ?>

    <!-- Delete Confirmation Modal -->
    <div id="delete-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-semibold text-gray-800">Delete Guide</h3>
                    <button onclick="closeModal()" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div id="modal-content" class="mb-6">
                    Are you sure you want to delete this guide? This action cannot be undone.
                </div>
                <div class="flex justify-end space-x-4">
                    <button onclick="closeModal()" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition-all">
                        Cancel
                    </button>
                    <a id="confirm-delete" href="#" class="bg-red-600 hover:bg-red-700 text-white font-medium py-2 px-4 rounded-md transition-all">
                        Delete
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="fixed bottom-4 right-4 bg-gray-800 text-white px-6 py-3 rounded-lg shadow-lg transform translate-y-20 opacity-0 transition-all duration-300 flex items-center z-50">
        <span id="toast-message">Notification message</span>
    </div>

    <script>
        function showToast(message, duration = 3000) {
            const toast = document.getElementById('toast');
            document.getElementById('toast-message').textContent = message;
            
            toast.classList.remove('translate-y-20', 'opacity-0');
            
            setTimeout(() => {
                toast.classList.add('translate-y-20', 'opacity-0');
            }, duration);
        }
        
        function confirmDelete(id, title) {
            document.getElementById('modal-content').textContent = `Are you sure you want to delete the guide "${title}"? This action cannot be undone.`;
            document.getElementById('confirm-delete').href = `guides.php?delete=${id}`;
            document.getElementById('delete-modal').classList.remove('hidden');
        }
        
        function closeModal() {
            document.getElementById('delete-modal').classList.add('hidden');
        }
        
        <?php if (isset($_SESSION['flash_message'])): ?>
        showToast('<?php echo $_SESSION['flash_message']; ?>');
        <?php unset($_SESSION['flash_message']); ?>
        <?php endif; ?>
    </script>
</body>
</html>

Guide form for creating/editing (
admin/guide-form.php
):
<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

// Require admin privileges
requireAdmin();

// Get categories for dropdown
$categories = fetchAll("SELECT * FROM categories ORDER BY name");

// Initialize variables
$guide = [
    'id' => '',
    'title' => '',
    'description' => '',
    'category_id' => ''
];
$steps = [];
$isEdit = false;

// Check if editing existing guide
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $guideId = (int)$_GET['id'];
    $guideData = fetchOne("SELECT * FROM guides WHERE id = ?", [$guideId]);
    
    if ($guideData) {
        $guide = $guideData;
        $steps = fetchAll("SELECT * FROM steps WHERE guide_id = ? ORDER BY position", [$guideId]);
        $isEdit = true;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $categoryId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
    $description = $_POST['description'] ?? '';
    $stepTitles = $_POST['step_title'] ?? [];
    $stepContents = $_POST['step_content'] ?? [];
    $stepImages = $_POST['step_image'] ?? [];
    $stepIds = $_POST['step_id'] ?? [];
    
    // Validate required fields
    if (empty($title)) {
        $error = 'Please enter a guide title.';
    } elseif ($categoryId <= 0) {
        $error = 'Please select a category.';
    } elseif (empty($stepTitles) || empty($stepContents)) {
        $error = 'Please add at least one step.';
    } else {
        // Begin transaction
        $conn = getDbConnection();
        mysqli_begin_transaction($conn);
        
        try {
            if ($isEdit) {
                // Update existing guide
                update("UPDATE guides SET title = ?, category_id = ?, description = ?, updated_at = NOW() WHERE id = ?", 
                       [$title, $categoryId, $description, $guide['id']]);
                $guideId = $guide['id'];
            } else {
                // Create new guide
                $guideId = insert("INSERT INTO guides (title, category_id, description, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())", 
                                 [$title, $categoryId, $description]);
            }
            
            // Get existing steps to track which ones to delete
            $existingStepIds = [];
            if ($isEdit) {
                $existingSteps = fetchAll("SELECT id FROM steps WHERE guide_id = ?", [$guideId]);
                foreach ($existingSteps as $step) {
                    $existingStepIds[] = $step['id'];
                }
            }
            
            // Process steps
            $keepStepIds = [];
            for ($i = 0; $i < count($stepTitles); $i++) {
                if (empty($stepTitles[$i]) || empty($stepContents[$i])) {
                    continue;
                }
                
                $stepId = isset($stepIds[$i]) && is_numeric($stepIds[$i]) ? (int)$stepIds[$i] : 0;
                $imagePath = $stepImages[$i] ?? '';
                
                if ($stepId > 0) {
                    // Update existing step
                    update("UPDATE steps SET title = ?, content = ?, image_path = ?, position = ?, updated_at = NOW() WHERE id = ? AND guide_id = ?", 
                           [$stepTitles[$i], $stepContents[$i], $imagePath, $i + 1, $stepId, $guideId]);
                    $keepStepIds[] = $stepId;
                } else {
                    // Create new step
                    insert("INSERT INTO steps (guide_id, title, content, image_path, position, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())", 
                           [$guideId, $stepTitles[$i], $stepContents[$i], $imagePath, $i + 1]);
                }
            }
            
            // Delete steps that were removed
            if ($isEdit) {
                $deleteStepIds = array_diff($existingStepIds, $keepStepIds);
                if (!empty($deleteStepIds)) {
                    foreach ($deleteStepIds as $deleteId) {
                        delete("DELETE FROM steps WHERE id = ? AND guide_id = ?", [$deleteId, $guideId]);
                    }
                }
            }
            
            // Commit transaction
            mysqli_commit($conn);
            
            $_SESSION['flash_message'] = $isEdit ? "Guide updated successfully." : "Guide created successfully.";
            header("Location: guides.php");
            exit;
        } catch (Exception $e) {
            // Rollback transaction on error
            mysqli_rollback($conn);
            $error = "An error occurred: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isEdit ? 'Edit' : 'Create'; ?> Guide - SAP Knowledge Hub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        .image-upload-preview {
            max-width: 300px;
            max-height: 200px;
            object-fit: contain;
            margin-top: 10px;
            border: 1px solid #e2e8f0;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <?php include '../includes/admin-header.php'; ?>

    <!-- Main Content -->
    <main class="py-8">
        <div class="container mx-auto px-4">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-gray-800"><?php echo $isEdit ? 'Edit' : 'Create'; ?> Guide</h1>
                <a href="guides.php" class="text-blue-600 hover:text-blue-800 font-medium">
                    <i class="fas fa-arrow-left mr-1"></i>
                    Back to Guides
                </a>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm p-6">
                <?php if (isset($error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo $error; ?>
                </div>
                <?php endif; ?>
                
                <form method="post" action="guide-form.php<?php echo $isEdit ? '?id=' . $guide['id'] : ''; ?>" class="space-y-6">
                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Guide Title</label>
                        <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($guide['title']); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div>
                        <label for="category_id" class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                        <select id="category_id" name="category_id" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Select a category</option>
                            <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" <?php echo $guide['category_id'] == $category['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea id="description" name="description" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"><?php echo htmlspecialchars($guide['description']); ?></textarea>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Steps</label>
                        <div id="steps-container" class="space-y-4">
                            <?php if (empty($steps)): ?>
                            <!-- Default empty step -->
                            <div class="step-form-item border border-gray-200 rounded-lg p-4">
                                <div class="flex justify-between items-center mb-3">
                                    <h4 class="font-medium text-gray-800">Step 1</h4>
                                    <button type="button" onclick="removeStep(this)" class="text-red-500 hover:text-red-700">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                                <div class="space-y-4">
                                    <input type="hidden" name="step_id[]" value="">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Step Title</label>
                                        <input type="text" name="step_title[]" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Step Content</label>
                                        <textarea name="step_content[]" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"></textarea>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Image URL</label>
                                        <input type="text" name="step_image[]" class="step-image-input w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500" placeholder="Enter image URL" onchange="previewImage(this)">
                                        <div class="image-preview mt-2"></div>
                                    </div>
                                </div>
                            </div>
                            <?php else: ?>
                            <!-- Existing steps -->
                            <?php foreach ($steps as $index => $step): ?>
                            <div class="step-form-item border border-gray-200 rounded-lg p-4">
                                <div class="flex justify-between items-center mb-3">
                                    <h4 class="font-medium text-gray-800">Step <?php echo $index + 1; ?></h4>
                                    <button type="button" onclick="removeStep(this)" class="text-red-500 hover:text-red-700">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                                <div class="space-y-4">
                                    <input type="hidden" name="step_id[]" value="<?php echo $step['id']; ?>">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Step Title</label>
                                        <input type="text" name="step_title[]" value="<?php echo htmlspecialchars($step['title']); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Step Content</label>
                                        <textarea name="step_content[]" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"><?php echo htmlspecialchars($step['content']); ?></textarea>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Image URL</label>
                                        <input type="text" name="step_image[]" value="<?php echo htmlspecialchars($step['image_path']); ?>" class="step-image-input w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500" placeholder="Enter image URL" onchange="previewImage(this)">
                                        <div class="image-preview mt-2">
                                            <?php if (!empty($step['image_path'])): ?>
                                            <img src="<?php echo htmlspecialchars($step['image_path']); ?>" alt="Step image" class="image-upload-preview">
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <button type="button" onclick="addStep()" class="mt-4 flex items-center text-blue-600 hover:text-blue-800">
                            <i class="fas fa-plus mr-1"></i>
                            Add Step
                        </button>
                    </div>
                    
                    <div class="flex justify-end space-x-4">
                        <a href="guides.php" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition-all">
                            Cancel
                        </a>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md transition-all">
                            <?php echo $isEdit ? 'Update' : 'Create'; ?> Guide
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <?php include '../includes/admin-footer.php'; ?>

    <script>
        function addStep() {
            const stepsContainer = document.getElementById('steps-container');
            const stepCount = stepsContainer.children.length + 1;
            
            const stepDiv = document.createElement('div');
            stepDiv.className = 'step-form-item border border-gray-200 rounded-lg p-4';
            
            stepDiv.innerHTML = `
                <div class="flex justify-between items-center mb-3">
                    <h4 class="font-medium text-gray-800">Step ${stepCount}</h4>
                    <button type="button" onclick="removeStep(this)" class="text-red-500 hover:text-red-700">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
                <div class="space-y-4">
                    <input type="hidden" name="step_id[]" value="">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Step Title</label>
                        <input type="text" name="step_title[]" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Step Content</label>
                        <textarea name="step_content[]" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Image URL</label>
                        <input type="text" name="step_image[]" class="step-image-input w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500" placeholder="Enter image URL" onchange="previewImage(this)">
                        <div class="image-preview mt-2"></div>
                    </div>
                </div>
            `;
            
            stepsContainer.appendChild(stepDiv);
            updateStepNumbers();
        }
        
        function removeStep(button) {
            const stepItem = button.closest('.step-form-item');
            
            // Don't allow removing the last step
            const stepsContainer = document.getElementById('steps-container');
            if (stepsContainer.children.length <= 1) {
                alert('You must have at least one step.');
                return;
            }
            
            stepItem.remove();
            updateStepNumbers();
        }
        
        function updateStepNumbers() {
            const steps = document.querySelectorAll('.step-form-item');
            steps.forEach((step, index) => {
                step.querySelector('h4').textContent = `Step ${index + 1}`;
            });
        }
        
        function previewImage(input) {
            const previewContainer = input.parentElement.querySelector('.image-preview');
            const imageUrl = input.value.trim();
            
            if (imageUrl) {
                let img = previewContainer.querySelector('img');
                if (!img) {
                    img = document.createElement('img');
                    img.className = 'image-upload-preview';
                    previewContainer.appendChild(img);
                }
                img.src = imageUrl;
                img.alt = 'Step image preview';
            } else {
                previewContainer.innerHTML = '';
            }
        }
        
        // Initialize image previews
        document.addEventListener('DOMContentLoaded', function() {
            const imageInputs = document.querySelectorAll('.step-image-input');
            imageInputs.forEach(input => {
                if (input.value.trim()) {
                    previewImage(input);
                }
            });
        });
    </script>
</body>
</html>
