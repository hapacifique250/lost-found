<?php
// Database configuration - UPDATE THESE VALUES WITH YOUR DATABASE CREDENTIALS
$host = 'bheewuqktoousmianri0-mysql.services.clever-cloud.com';          // Your database host (usually 'localhost')
$dbname = 'bheewuqktoousmianri0';    // Your database name
$username = 'un7jktglvtdihni2';           // Your database username
$password = 'K4I5u7xYVqNPTQaoo8nA';               // Your database password

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