<?php
include 'includes/config.php';

// Destroy all session data
session_destroy();

// Redirect to home page
header("Location: index.php");
exit;
?>