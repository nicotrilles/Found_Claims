<?php
$host = "localhost";
$database = "school_lost_found";
$user = "root";
$password = "";

//PDO is recommended for multi-database support and flexibility
// try {
//     $conn = new PDO("mysql:host=$host;database=$database", $user, $password);
//     echo "Connected successfully";
// } catch (PDOException $e) {
//     echo "Connection failed: " . $e->getMessage();
// }

// MySQLi offers a dedicated, beginner-friendly approach specifically for MySQL.
$conn = new mysqli($host, $user, $password, $database);

if ($conn -> connect_error) {
    die ("Connection failed: " . $conn -> connect_error);
}

//echo "Connected successfully";
?>