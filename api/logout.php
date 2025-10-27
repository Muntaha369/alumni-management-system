<?php
session_start(); // Access the existing session

// Unset all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to the login page
header("Location: ../index.html");
exit();
?>