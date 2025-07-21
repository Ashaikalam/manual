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
