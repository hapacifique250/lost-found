<?php
// Database configuration - UPDATE THESE VALUES WITH YOUR DATABASE CREDENTIALS
$host = 'bnoykenhuvwibsbxjiig-mysql.services.clever-cloud.com';          // Your database host (usually 'localhost')
$dbname = 'bnoykenhuvwibsbxjiig';    // Your database name
$username = 'ucapzyitefhv9siz';           // Your database username
$password = 'qQLLfipQhXbVo5TW5igZ';               // Your database password

try {
    // Create connection
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    
    // Set error mode to exceptions
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    //echo "Connected successfully"; // Uncomment to test connection
    
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>