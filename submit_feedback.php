<?php
// Set JSON header
header('Content-Type: application/json');

// Include database configuration
require_once 'includes/db.php';

// Initialize response array
$response = array('success' => false, 'message' => '');

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method';
    echo json_encode($response);
    exit;
}

try {
    // Get and sanitize form data
    $name = isset($_POST['name']) ? sanitizeInput($_POST['name']) : '';
    $email = isset($_POST['email']) ? sanitizeInput($_POST['email']) : '';
    $service_used = isset($_POST['service_used']) ? sanitizeInput($_POST['service_used']) : '';
    $rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
    $feedback_text = isset($_POST['feedback_text']) ? sanitizeInput($_POST['feedback_text']) : '';
    
    // Validate required fields
    if (empty($name) || empty($email) || empty($service_used) || empty($feedback_text)) {
        $response['message'] = 'Please fill in all required fields';
        echo json_encode($response);
        exit;
    }
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['message'] = 'Please enter a valid email address';
        echo json_encode($response);
        exit;
    }
    
    // Validate rating
    if ($rating < 1 || $rating > 5) {
        $response['message'] = 'Please select a valid rating (1-5 stars)';
        echo json_encode($response);
        exit;
    }
    
    // Get or create user ID
    $userId = getUserIdByEmail($conn, $email, $name);
    
    // Insert feedback into database
    $stmt = $conn->prepare("INSERT INTO feedback (user_id, service_used, rating, feedback_text, is_published, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
    $stmt->bind_param("isis", $userId, $service_used, $rating, $feedback_text);
    
    if ($stmt->execute()) {
        $feedbackId = $stmt->insert_id;
        
        // Log activity
        logActivity($conn, $userId, 'Feedback Submitted', "Submitted feedback for $service_used with rating $rating");
        
        $response['success'] = true;
        $response['message'] = 'Thank you for your feedback! We appreciate your input.';
    } else {
        $response['message'] = 'Failed to submit feedback. Please try again.';
        error_log("Feedback insert error: " . $stmt->error);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    $response['message'] = 'An error occurred. Please try again later.';
    error_log("Feedback submission error: " . $e->getMessage());
}

// Close database connection
if ($conn) {
    $conn->close();
}

// Return JSON response
echo json_encode($response);
?>
