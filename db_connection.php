<?php
// db_connection.php

$conn = new mysqli("localhost", "root", "", "alumni_db");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>