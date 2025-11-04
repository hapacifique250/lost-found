<?php
$host = 'bheewuqktoousmianri0-mysql.services.clever-cloud.com';  
$dbname = 'bheewuqktoousmianri0';    // Your database name
$username = 'un7jktglvtdihni2';           // Your database username
$password = 'K4I5u7xYVqNPTQaoo8nA';   


// $host = 'localhost';  
// $dbname = 'lost_found';    // Your database name
// $username = 'root';           // Your database username
// $password = '';  // Your database password

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