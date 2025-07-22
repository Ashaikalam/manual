<?php
session_start();
if ($_SESSION['role'] !== 'admin') { header('Location: index.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Save logic to database
  echo 'Note added (mocked)';
}
?>
<form method='post'>
  Title: <input name='title'><br>
  Steps: <textarea name='steps'></textarea><br>
  <button>Add Note</button>
</form>