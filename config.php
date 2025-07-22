<?php
// Database config
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'sap_notes';
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}
?>