<?php
session_start();
require_once '../config/connection.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('HTTP/1.1 401 Unauthorized');
    exit(json_encode(['success' => false, 'message' => 'Not authorized']));
}

$user_id = $_SESSION['user_id'];

try {
    // Delete all notifications for the user
    $stmt = $pdo->prepare("DELETE FROM notifications WHERE user_id = ?");
    $stmt->execute([$user_id]);
    
    echo json_encode(['success' => true, 'message' => 'All notifications cleared successfully']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error clearing notifications: ' . $e->getMessage()]);
}
?>