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
