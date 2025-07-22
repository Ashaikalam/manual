<?php
session_start();
if ($_SESSION['role'] !== 'admin') { header('Location: index.php'); exit; }
?>
<h2>Admin Panel - <?= $_SESSION['user'] ?></h2>
<a href='add_note.php'>Add Note</a>
<!-- Notes list with edit/delete links would go here -->