<?php
session_start();
include 'config.php';
$type = $_GET['type'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $table = $type === 'admin' ? 'admins' : 'users';
    $sql = "SELECT * FROM $table WHERE username=? AND password=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $username, $password);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $_SESSION['user'] = $username;
        $_SESSION['role'] = $type;
        header('Location: ' . ($type === 'admin' ? 'admin_dashboard.php' : 'dashboard.php'));
        exit;
    } else {
        echo "Invalid login.";
    }
}
?>
<form method='post'>
  <h3><?= ucfirst($type) ?> Login</h3>
  Username: <input name='username'><br>
  Password: <input type='password' name='password'><br>
  <button type='submit'>Login</button>
</form>