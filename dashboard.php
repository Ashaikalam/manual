<?php
session_start();
if ($_SESSION['role'] !== 'user') { header('Location: index.php'); exit; }
?>
<h2>Welcome User: <?= $_SESSION['user'] ?></h2>
<div id='notes'></div>
<script>
let notes = JSON.parse(localStorage.getItem('sapNotes')) || [];
let html = notes.map(n => `<h4>${n.title}</h4><p>${n.steps}</p>`).join('');
document.getElementById('notes').innerHTML = html;
</script>