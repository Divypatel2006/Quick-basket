<?php
include 'includes/config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    
    if ($email) {
        // In a real application, you would save to database
        // For now, we'll just show a success message
        $_SESSION['success'] = "Thank you for subscribing to our newsletter!";
    } else {
        $_SESSION['error'] = "Please enter a valid email address.";
    }
}

header("Location: index.php");
exit;
?>